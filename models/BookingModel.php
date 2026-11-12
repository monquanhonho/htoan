<?php
/**
 * Booking & Check-in/Check-out Model
 * Lumina Hotel Management System
 */

declare(strict_types=1);

class BookingModel
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? getDB();
    }

    /**
     * Create a new booking with overlap prevention & automatic price calculation.
     */
    public function createBooking(array $data): array
    {
        $roomId   = (int)$data['room_id'];
        $custId   = (int)$data['customer_id'];
        $checkIn  = $data['check_in_date'];
        $checkOut = $data['check_out_date'];
        $guests   = max(1, (int)($data['num_guests'] ?? 1));
        $notes    = trim((string)($data['special_requests'] ?? ''));

        $nights = calculate_nights($checkIn, $checkOut);
        if ($nights <= 0) {
            throw new RuntimeException('Ngày trả phòng phải lớn hơn ngày nhận phòng ít nhất 1 đêm.');
        }

        $this->db->beginTransaction();
        try {
            // Lock room row to prevent race-condition double booking
            $stmtRoom = $this->db->prepare(
                'SELECT r.id, r.room_number, r.price_per_night, r.status, rt.capacity
                 FROM rooms r
                 JOIN room_types rt ON rt.id = r.room_type_id
                 WHERE r.id = ? FOR UPDATE'
            );
            $stmtRoom->execute([$roomId]);
            $room = $stmtRoom->fetch();

            if (!$room) {
                throw new RuntimeException('Phòng không tồn tại.');
            }
            if ($room['status'] === 'maintenance') {
                throw new RuntimeException('Phòng hiện đang bảo trì, không thể đặt.');
            }
            if ($guests > (int)$room['capacity']) {
                throw new RuntimeException('Số khách vượt quá sức chứa tối đa của phòng (' . $room['capacity'] . ' khách).');
            }

            // Overlap check
            $stmtOverlap = $this->db->prepare(
                'SELECT COUNT(*) FROM booking_details bd
                 JOIN bookings b ON b.id = bd.booking_id
                 WHERE bd.room_id = ?
                   AND b.status IN ("pending", "confirmed", "checked_in")
                   AND b.check_in_date < ?
                   AND b.check_out_date > ?'
            );
            $stmtOverlap->execute([$roomId, $checkOut, $checkIn]);
            if ((int)$stmtOverlap->fetchColumn() > 0) {
                throw new RuntimeException('Phòng ' . $room['room_number'] . ' đã có lịch đặt trong khoảng thời gian bạn chọn.');
            }

            $pricePerNight = (float)$room['price_per_night'];
            $subtotal      = $pricePerNight * $nights;
            $bookingCode   = generate_code('LUM');
            $initialStatus = $data['initial_status'] ?? 'pending';

            $stmtBooking = $this->db->prepare(
                'INSERT INTO bookings (booking_code, customer_id, check_in_date, check_out_date, num_guests, total_room_amount, total_service_amount, total_amount, status, special_requests, confirmed_by)
                 VALUES (?, ?, ?, ?, ?, ?, 0.00, ?, ?, ?, ?)'
            );
            $stmtBooking->execute([
                $bookingCode,
                $custId,
                $checkIn,
                $checkOut,
                $guests,
                $subtotal,
                $subtotal,
                $initialStatus,
                $notes ?: null,
                $initialStatus === 'confirmed' ? ($data['actor_user_id'] ?? null) : null,
            ]);
            $bookingId = (int)$this->db->lastInsertId();

            $stmtDetail = $this->db->prepare(
                'INSERT INTO booking_details (booking_id, room_id, price_per_night, nights, subtotal)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $stmtDetail->execute([$bookingId, $roomId, $pricePerNight, $nights, $subtotal]);

            if ($initialStatus === 'confirmed' && $room['status'] === 'available') {
                $this->db->prepare('UPDATE rooms SET status = "reserved" WHERE id = ?')->execute([$roomId]);
            }

            $this->db->commit();

            return [
                'id'           => $bookingId,
                'booking_code' => $bookingCode,
                'nights'       => $nights,
                'total_amount' => $subtotal,
            ];
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Recalculate service total and grand total for a booking.
     */
    public function recalculateTotals(int $bookingId): void
    {
        $stmtSrv = $this->db->prepare(
            'SELECT COALESCE(SUM(total_price), 0)
             FROM service_orders
             WHERE booking_id = ? AND status IN ("confirmed", "in_progress", "completed")'
        );
        $stmtSrv->execute([$bookingId]);
        $serviceTotal = (float)$stmtSrv->fetchColumn();

        $stmtUpd = $this->db->prepare(
            'UPDATE bookings
             SET total_service_amount = ?, total_amount = total_room_amount + ?
             WHERE id = ?'
        );
        $stmtUpd->execute([$serviceTotal, $serviceTotal, $bookingId]);
    }

    public function getBookings(array $filters = []): array
    {
        $sql = 'SELECT b.*,
                       u.full_name AS customer_name, u.phone AS customer_phone, u.email AS customer_email,
                       c.vip_tier, c.id_card,
                       bd.room_id, bd.nights, bd.price_per_night,
                       r.room_number, r.floor, r.status AS room_status,
                       rt.name AS room_type_name,
                       cc.checked_in_at, cc.checked_out_at, cc.deposit_amount, cc.id_verified,
                       COALESCE((SELECT SUM(p.amount) FROM payments p WHERE p.booking_id = b.id AND p.status = "completed"), 0) AS paid_amount
                FROM bookings b
                JOIN customers c ON c.id = b.customer_id
                JOIN users u ON u.id = c.user_id
                LEFT JOIN booking_details bd ON bd.booking_id = b.id
                LEFT JOIN rooms r ON r.id = bd.room_id
                LEFT JOIN room_types rt ON rt.id = r.room_type_id
                LEFT JOIN checkin_checkout cc ON cc.booking_id = b.id
                WHERE 1=1';
        $params = [];

        if (!empty($filters['customer_id'])) {
            $sql .= ' AND b.customer_id = ?';
            $params[] = (int)$filters['customer_id'];
        }
        if (!empty($filters['status'])) {
            $sql .= ' AND b.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['search'])) {
            $sql .= ' AND (b.booking_code LIKE ? OR u.full_name LIKE ? OR u.phone LIKE ? OR r.room_number LIKE ?)';
            $like = '%' . $filters['search'] . '%';
            array_push($params, $like, $like, $like, $like);
        }
        if (!empty($filters['date_from'])) {
            $sql .= ' AND b.check_in_date >= ?';
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $sql .= ' AND b.check_out_date <= ?';
            $params[] = $filters['date_to'];
        }

        $sql .= ' ORDER BY b.created_at DESC, b.id DESC';

        if (!empty($filters['limit'])) {
            $sql .= ' LIMIT ' . (int)$filters['limit'];
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function findBooking(int $bookingId): ?array
    {
        $sql = 'SELECT b.*,
                       u.id AS customer_user_id, u.full_name AS customer_name, u.phone AS customer_phone, u.email AS customer_email,
                       c.id_card, c.address, c.nationality, c.vip_tier,
                       bd.room_id, bd.nights, bd.price_per_night, bd.subtotal AS room_subtotal,
                       r.room_number, r.floor, r.status AS room_status,
                       rt.name AS room_type_name,
                       cc.checked_in_at, cc.checked_out_at, cc.deposit_amount, cc.id_verified, cc.notes AS checkin_notes,
                       COALESCE((SELECT SUM(p.amount) FROM payments p WHERE p.booking_id = b.id AND p.status = "completed"), 0) AS paid_amount
                FROM bookings b
                JOIN customers c ON c.id = b.customer_id
                JOIN users u ON u.id = c.user_id
                LEFT JOIN booking_details bd ON bd.booking_id = b.id
                LEFT JOIN rooms r ON r.id = bd.room_id
                LEFT JOIN room_types rt ON rt.id = r.room_type_id
                LEFT JOIN checkin_checkout cc ON cc.booking_id = b.id
                WHERE b.id = ? LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$bookingId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function confirmBooking(int $bookingId, int $actorUserId): void
    {
        $this->db->beginTransaction();
        try {
            $booking = $this->findBooking($bookingId);
            if (!$booking) {
                throw new RuntimeException('Không tìm thấy đặt phòng.');
            }
            if ($booking['status'] !== 'pending') {
                throw new RuntimeException('Chỉ có thể xác nhận đơn đặt phòng đang ở trạng thái chờ.');
            }

            $stmt = $this->db->prepare('UPDATE bookings SET status = "confirmed", confirmed_by = ? WHERE id = ?');
            $stmt->execute([$actorUserId, $bookingId]);

            if (!empty($booking['room_id']) && $booking['room_status'] === 'available') {
                $this->db->prepare('UPDATE rooms SET status = "reserved" WHERE id = ?')->execute([(int)$booking['room_id']]);
            }

            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function cancelOrRejectBooking(int $bookingId, string $newStatus, string $reason = ''): void
    {
        if (!in_array($newStatus, ['cancelled', 'rejected'], true)) {
            throw new InvalidArgumentException('Trạng thái hủy không hợp lệ.');
        }

        $this->db->beginTransaction();
        try {
            $booking = $this->findBooking($bookingId);
            if (!$booking) {
                throw new RuntimeException('Không tìm thấy đặt phòng.');
            }
            if (!in_array($booking['status'], ['pending', 'confirmed'], true)) {
                throw new RuntimeException('Không thể hủy hoặc từ chối đơn đặt phòng đã nhận phòng hoặc đã hoàn tất.');
            }

            $stmt = $this->db->prepare('UPDATE bookings SET status = ?, cancelled_reason = ? WHERE id = ?');
            $stmt->execute([$newStatus, $reason ?: null, $bookingId]);

            // Release room if it was reserved for this booking and no other confirmed booking holds it now
            if (!empty($booking['room_id']) && $booking['room_status'] === 'reserved') {
                $stmtOther = $this->db->prepare(
                    'SELECT COUNT(*) FROM booking_details bd
                     JOIN bookings b ON b.id = bd.booking_id
                     WHERE bd.room_id = ? AND b.id != ? AND b.status = "confirmed" AND b.check_in_date <= CURDATE() AND b.check_out_date > CURDATE()'
                );
                $stmtOther->execute([(int)$booking['room_id'], $bookingId]);
                if ((int)$stmtOther->fetchColumn() === 0) {
                    $this->db->prepare('UPDATE rooms SET status = "available" WHERE id = ?')->execute([(int)$booking['room_id']]);
                }
            }

            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Perform Check-in: booking -> checked_in, room -> occupied, create checkin_checkout record.
     */
    public function checkIn(int $bookingId, int $staffUserId, bool $idVerified = true, float $deposit = 0.0, string $notes = ''): void
    {
        $this->db->beginTransaction();
        try {
            $booking = $this->findBooking($bookingId);
            if (!$booking) {
                throw new RuntimeException('Không tìm thấy thông tin đặt phòng.');
            }
            if (!in_array($booking['status'], ['confirmed', 'pending'], true)) {
                throw new RuntimeException('Chỉ có thể Check-in cho đơn đã xác nhận hoặc đang chờ.');
            }
            if ($booking['room_status'] === 'cleaning') {
                throw new RuntimeException('Phòng ' . $booking['room_number'] . ' đang chờ vệ sinh. Vui lòng hoàn tất vệ sinh trước khi Check-in.');
            }
            if ($booking['room_status'] === 'maintenance') {
                throw new RuntimeException('Phòng ' . $booking['room_number'] . ' đang bảo trì, không thể Check-in.');
            }
            if ($booking['room_status'] === 'occupied') {
                throw new RuntimeException('Phòng ' . $booking['room_number'] . ' hiện đang có khách lưu trú.');
            }

            $this->db->prepare(
                'UPDATE bookings SET status = "checked_in", confirmed_by = COALESCE(confirmed_by, ?) WHERE id = ?'
            )->execute([$staffUserId, $bookingId]);

            $this->db->prepare(
                'INSERT INTO checkin_checkout (booking_id, checked_in_at, checked_in_by, id_verified, deposit_amount, notes)
                 VALUES (?, NOW(), ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE checked_in_at = NOW(), checked_in_by = VALUES(checked_in_by), id_verified = VALUES(id_verified), deposit_amount = VALUES(deposit_amount), notes = VALUES(notes)'
            )->execute([$bookingId, $staffUserId, $idVerified ? 1 : 0, $deposit, $notes ?: null]);

            if (!empty($booking['room_id'])) {
                $this->db->prepare('UPDATE rooms SET status = "occupied" WHERE id = ?')->execute([(int)$booking['room_id']]);
            }

            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Perform Check-out:
     * 1. Recalculate room + service totals
     * 2. Update checkin_checkout & booking -> checked_out
     * 3. Room -> cleaning & auto-create cleaning task
     */
    public function checkOut(int $bookingId, int $staffUserId, string $notes = ''): array
    {
        $this->db->beginTransaction();
        try {
            $booking = $this->findBooking($bookingId);
            if (!$booking) {
                throw new RuntimeException('Không tìm thấy thông tin đặt phòng.');
            }
            if ($booking['status'] !== 'checked_in') {
                throw new RuntimeException('Chỉ có thể Check-out cho khách đang lưu trú (Checked-in).');
            }

            $this->recalculateTotals($bookingId);

            $this->db->prepare('UPDATE bookings SET status = "checked_out" WHERE id = ?')->execute([$bookingId]);

            $this->db->prepare(
                'UPDATE checkin_checkout
                 SET checked_out_at = NOW(), checked_out_by = ?, notes = CONCAT_WS(" | ", notes, ?)
                 WHERE booking_id = ?'
            )->execute([$staffUserId, $notes ?: null, $bookingId]);

            if (!empty($booking['room_id'])) {
                $roomId = (int)$booking['room_id'];
                // Room transitions to cleaning after check-out
                $this->db->prepare('UPDATE rooms SET status = "cleaning" WHERE id = ?')->execute([$roomId]);

                // Automatically create a checkout cleaning record
                $this->db->prepare(
                    'INSERT INTO cleaning (room_id, booking_id, cleaning_type, status, notes)
                     VALUES (?, ?, "checkout", "pending", ?)'
                )->execute([
                    $roomId,
                    $bookingId,
                    'Vệ sinh tự động tạo sau khi khách trả phòng đơn ' . $booking['booking_code'],
                ]);
            }

            $updated = $this->findBooking($bookingId);
            $this->db->commit();
            return $updated;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function getTodayArrivals(): array
    {
        $stmt = $this->db->query(
            'SELECT b.*, u.full_name AS customer_name, u.phone AS customer_phone, r.room_number, rt.name AS room_type_name
             FROM bookings b
             JOIN customers c ON c.id = b.customer_id
             JOIN users u ON u.id = c.user_id
             JOIN booking_details bd ON bd.booking_id = b.id
             JOIN rooms r ON r.id = bd.room_id
             JOIN room_types rt ON rt.id = r.room_type_id
             WHERE b.status IN ("pending", "confirmed") AND b.check_in_date <= CURDATE()
             ORDER BY b.check_in_date ASC'
        );
        return $stmt->fetchAll();
    }

    public function getActiveStays(): array
    {
        $stmt = $this->db->query(
            'SELECT b.*, u.full_name AS customer_name, u.phone AS customer_phone, r.room_number, rt.name AS room_type_name,
                    cc.checked_in_at, cc.deposit_amount,
                    COALESCE((SELECT SUM(p.amount) FROM payments p WHERE p.booking_id = b.id AND p.status = "completed"), 0) AS paid_amount
             FROM bookings b
             JOIN customers c ON c.id = b.customer_id
             JOIN users u ON u.id = c.user_id
             JOIN booking_details bd ON bd.booking_id = b.id
             JOIN rooms r ON r.id = bd.room_id
             JOIN room_types rt ON rt.id = r.room_type_id
             LEFT JOIN checkin_checkout cc ON cc.booking_id = b.id
             WHERE b.status = "checked_in"
             ORDER BY b.check_out_date ASC'
        );
        return $stmt->fetchAll();
    }
}
