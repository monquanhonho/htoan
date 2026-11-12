<?php
/**
 * Room & Room Type Model
 * Lumina Hotel Management System
 */

declare(strict_types=1);

class RoomModel
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? getDB();
    }

    /* ---------------- ROOM TYPES ---------------- */

    public function getRoomTypes(): array
    {
        return $this->db->query('SELECT * FROM room_types ORDER BY base_price ASC')->fetchAll();
    }

    public function findRoomType(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM room_types WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function createRoomType(array $data): int
    {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $data['name']), '-')) . '-' . substr(bin2hex(random_bytes(2)), 0, 4);
        $stmt = $this->db->prepare(
            'INSERT INTO room_types (name, slug, base_price, capacity, bed_type, area_sqm, description, amenities)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['name'],
            $slug,
            (float)$data['base_price'],
            (int)$data['capacity'],
            $data['bed_type'],
            (float)$data['area_sqm'],
            $data['description'],
            $data['amenities'],
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function updateRoomType(int $id, array $data): void
    {
        $stmt = $this->db->prepare(
            'UPDATE room_types SET name = ?, base_price = ?, capacity = ?, bed_type = ?, area_sqm = ?, description = ?, amenities = ? WHERE id = ?'
        );
        $stmt->execute([
            $data['name'],
            (float)$data['base_price'],
            (int)$data['capacity'],
            $data['bed_type'],
            (float)$data['area_sqm'],
            $data['description'],
            $data['amenities'],
            $id,
        ]);
    }

    public function deleteRoomType(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM room_types WHERE id = ?');
        $stmt->execute([$id]);
    }

    /* ---------------- ROOMS ---------------- */

    /**
     * Search rooms with filters (room_type_id, max_price, min_price, status, check_in, check_out, search).
     * When check_in & check_out are provided, excludes rooms booked during overlapping dates and rooms under maintenance.
     */
    public function searchRooms(array $filters = []): array
    {
        $sql = 'SELECT r.*, rt.name AS type_name, rt.capacity, rt.bed_type, rt.area_sqm,
                       (SELECT image_path FROM room_images ri WHERE ri.room_id = r.id ORDER BY ri.is_primary DESC, ri.sort_order ASC, ri.id ASC LIMIT 1) AS primary_image
                FROM rooms r
                JOIN room_types rt ON rt.id = r.room_type_id
                WHERE 1=1';
        $params = [];

        if (!empty($filters['room_type_id'])) {
            $sql .= ' AND r.room_type_id = ?';
            $params[] = (int)$filters['room_type_id'];
        }
        if (!empty($filters['min_price'])) {
            $sql .= ' AND r.price_per_night >= ?';
            $params[] = (float)$filters['min_price'];
        }
        if (!empty($filters['max_price'])) {
            $sql .= ' AND r.price_per_night <= ?';
            $params[] = (float)$filters['max_price'];
        }
        if (!empty($filters['status'])) {
            $sql .= ' AND r.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['floor'])) {
            $sql .= ' AND r.floor = ?';
            $params[] = (int)$filters['floor'];
        }
        if (!empty($filters['guests'])) {
            $sql .= ' AND rt.capacity >= ?';
            $params[] = (int)$filters['guests'];
        }
        if (!empty($filters['search'])) {
            $sql .= ' AND (r.room_number LIKE ? OR rt.name LIKE ? OR r.description LIKE ?)';
            $like = '%' . $filters['search'] . '%';
            array_push($params, $like, $like, $like);
        }

        if (!empty($filters['check_in']) && !empty($filters['check_out'])) {
            $sql .= ' AND r.status != "maintenance"
                      AND r.id NOT IN (
                          SELECT bd.room_id
                          FROM booking_details bd
                          JOIN bookings b ON b.id = bd.booking_id
                          WHERE b.status IN ("pending", "confirmed", "checked_in")
                            AND b.check_in_date < ?
                            AND b.check_out_date > ?
                      )';
            $params[] = $filters['check_out'];
            $params[] = $filters['check_in'];
        }

        $sql .= ' ORDER BY r.floor ASC, r.room_number ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function findRoom(int $roomId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT r.*, rt.name AS type_name, rt.capacity, rt.bed_type, rt.area_sqm, rt.description AS type_description
             FROM rooms r
             JOIN room_types rt ON rt.id = r.room_type_id
             WHERE r.id = ? LIMIT 1'
        );
        $stmt->execute([$roomId]);
        $room = $stmt->fetch();
        if (!$room) {
            return null;
        }

        $room['images'] = $this->getRoomImages($roomId);
        return $room;
    }

    public function getRoomImages(int $roomId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM room_images WHERE room_id = ? ORDER BY is_primary DESC, sort_order ASC, id ASC');
        $stmt->execute([$roomId]);
        return $stmt->fetchAll();
    }

    public function isRoomAvailable(int $roomId, string $checkIn, string $checkOut, ?int $excludeBookingId = null): bool
    {
        $stmtRoom = $this->db->prepare('SELECT status FROM rooms WHERE id = ? LIMIT 1');
        $stmtRoom->execute([$roomId]);
        $room = $stmtRoom->fetch();
        if (!$room || $room['status'] === 'maintenance') {
            return false;
        }

        $sql = 'SELECT COUNT(*) FROM booking_details bd
                JOIN bookings b ON b.id = bd.booking_id
                WHERE bd.room_id = ?
                  AND b.status IN ("pending", "confirmed", "checked_in")
                  AND b.check_in_date < ?
                  AND b.check_out_date > ?';
        $params = [$roomId, $checkOut, $checkIn];

        if ($excludeBookingId !== null) {
            $sql .= ' AND b.id != ?';
            $params[] = $excludeBookingId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn() === 0;
    }

    public function createRoom(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO rooms (room_number, floor, room_type_id, price_per_night, status, description, amenities)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['room_number'],
            (int)$data['floor'],
            (int)$data['room_type_id'],
            (float)$data['price_per_night'],
            $data['status'] ?: 'available',
            $data['description'],
            $data['amenities'],
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function updateRoom(int $roomId, array $data): void
    {
        $stmt = $this->db->prepare(
            'UPDATE rooms SET room_number = ?, floor = ?, room_type_id = ?, price_per_night = ?, status = ?, description = ?, amenities = ? WHERE id = ?'
        );
        $stmt->execute([
            $data['room_number'],
            (int)$data['floor'],
            (int)$data['room_type_id'],
            (float)$data['price_per_night'],
            $data['status'],
            $data['description'],
            $data['amenities'],
            $roomId,
        ]);
    }

    public function updateRoomStatus(int $roomId, string $status): void
    {
        $stmt = $this->db->prepare('UPDATE rooms SET status = ? WHERE id = ?');
        $stmt->execute([$status, $roomId]);
    }

    public function deleteRoom(int $roomId): void
    {
        $stmt = $this->db->prepare('DELETE FROM rooms WHERE id = ?');
        $stmt->execute([$roomId]);
    }

    public function addRoomImage(int $roomId, string $imagePath, bool $isPrimary = false): void
    {
        if ($isPrimary) {
            $this->db->prepare('UPDATE room_images SET is_primary = 0 WHERE room_id = ?')->execute([$roomId]);
        }
        $stmt = $this->db->prepare('INSERT INTO room_images (room_id, image_path, is_primary) VALUES (?, ?, ?)');
        $stmt->execute([$roomId, $imagePath, $isPrimary ? 1 : 0]);
    }

    public function setPrimaryImage(int $roomId, int $imageId): void
    {
        $this->db->prepare('UPDATE room_images SET is_primary = 0 WHERE room_id = ?')->execute([$roomId]);
        $this->db->prepare('UPDATE room_images SET is_primary = 1 WHERE id = ? AND room_id = ?')->execute([$imageId, $roomId]);
    }

    public function deleteRoomImage(int $imageId): void
    {
        $stmt = $this->db->prepare('DELETE FROM room_images WHERE id = ?');
        $stmt->execute([$imageId]);
    }

    public function getStatusSummary(): array
    {
        $rows = $this->db->query('SELECT status, COUNT(*) AS cnt FROM rooms GROUP BY status')->fetchAll();
        $summary = [
            'total'       => 0,
            'available'   => 0,
            'reserved'    => 0,
            'occupied'    => 0,
            'cleaning'    => 0,
            'maintenance' => 0,
        ];
        foreach ($rows as $row) {
            $summary[$row['status']] = (int)$row['cnt'];
            $summary['total'] += (int)$row['cnt'];
        }
        return $summary;
    }
}
