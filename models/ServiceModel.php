<?php
/**
 * Service & Service Orders Model
 * Lumina Hotel Management System
 */

declare(strict_types=1);

require_once __DIR__ . '/BookingModel.php';

class ServiceModel
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? getDB();
    }

    public function getAllServices(bool $activeOnly = false, string $category = ''): array
    {
        $sql = 'SELECT * FROM services WHERE 1=1';
        $params = [];
        if ($activeOnly) {
            $sql .= ' AND is_active = 1';
        }
        if ($category !== '') {
            $sql .= ' AND category = ?';
            $params[] = $category;
        }
        $sql .= ' ORDER BY category ASC, id DESC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function findService(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM services WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function createService(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO services (name, category, description, price, unit, image_path, is_active)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['name'],
            $data['category'],
            $data['description'],
            (float)$data['price'],
            $data['unit'] ?: 'Lần',
            $data['image_path'] ?: 'assets/images/services/amthucnhahang1.jpg',
            isset($data['is_active']) ? (int)$data['is_active'] : 1,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function updateService(int $id, array $data): void
    {
        $stmt = $this->db->prepare(
            'UPDATE services SET name = ?, category = ?, description = ?, price = ?, unit = ?, image_path = COALESCE(?, image_path), is_active = ? WHERE id = ?'
        );
        $stmt->execute([
            $data['name'],
            $data['category'],
            $data['description'],
            (float)$data['price'],
            $data['unit'] ?: 'Lần',
            $data['image_path'] ?? null,
            isset($data['is_active']) ? (int)$data['is_active'] : 1,
            $id,
        ]);
    }

    public function deleteService(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM services WHERE id = ?');
        $stmt->execute([$id]);
    }

    /* ---------------- SERVICE ORDERS ---------------- */

    public function createServiceOrder(int $bookingId, int $customerId, int $serviceId, int $quantity, ?string $requestedTime, string $notes = ''): int
    {
        $service = $this->findService($serviceId);
        if (!$service || !(int)$service['is_active']) {
            throw new RuntimeException('Dịch vụ không tồn tại hoặc tạm ngừng cung cấp.');
        }

        // Verify booking belongs to customer and is active
        $stmtB = $this->db->prepare('SELECT id, status FROM bookings WHERE id = ? AND customer_id = ? LIMIT 1');
        $stmtB->execute([$bookingId, $customerId]);
        $booking = $stmtB->fetch();
        if (!$booking || !in_array($booking['status'], ['confirmed', 'checked_in'], true)) {
            throw new RuntimeException('Chỉ có thể đặt dịch vụ cho đơn đặt phòng đã xác nhận hoặc đang lưu trú.');
        }

        $qty = max(1, $quantity);
        $unitPrice = (float)$service['price'];
        $totalPrice = $unitPrice * $qty;

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                'INSERT INTO service_orders (booking_id, customer_id, service_id, quantity, unit_price, total_price, requested_time, status, notes)
                 VALUES (?, ?, ?, ?, ?, ?, ?, "pending", ?)'
            );
            $stmt->execute([
                $bookingId,
                $customerId,
                $serviceId,
                $qty,
                $unitPrice,
                $totalPrice,
                $requestedTime ?: date('Y-m-d H:i:s'),
                $notes ?: null,
            ]);
            $orderId = (int)$this->db->lastInsertId();

            $this->db->commit();
            return $orderId;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function updateServiceOrderStatus(int $orderId, string $status, ?int $handlerUserId = null): void
    {
        $allowed = ['pending', 'confirmed', 'in_progress', 'completed', 'cancelled'];
        if (!in_array($status, $allowed, true)) {
            throw new InvalidArgumentException('Trạng thái dịch vụ không hợp lệ.');
        }

        $this->db->beginTransaction();
        try {
            $stmtGet = $this->db->prepare('SELECT booking_id FROM service_orders WHERE id = ? LIMIT 1');
            $stmtGet->execute([$orderId]);
            $order = $stmtGet->fetch();
            if (!$order) {
                throw new RuntimeException('Không tìm thấy yêu cầu dịch vụ.');
            }

            $stmt = $this->db->prepare('UPDATE service_orders SET status = ?, handled_by = COALESCE(?, handled_by) WHERE id = ?');
            $stmt->execute([$status, $handlerUserId, $orderId]);

            $bookingModel = new BookingModel($this->db);
            $bookingModel->recalculateTotals((int)$order['booking_id']);

            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function getServiceOrders(array $filters = []): array
    {
        $sql = 'SELECT so.*, s.name AS service_name, s.category AS service_category, s.unit,
                       b.booking_code, b.status AS booking_status,
                       u.full_name AS customer_name, u.phone AS customer_phone,
                       r.room_number,
                       uh.full_name AS handler_name
                FROM service_orders so
                JOIN services s ON s.id = so.service_id
                JOIN bookings b ON b.id = so.booking_id
                JOIN customers c ON c.id = so.customer_id
                JOIN users u ON u.id = c.user_id
                LEFT JOIN booking_details bd ON bd.booking_id = b.id
                LEFT JOIN rooms r ON r.id = bd.room_id
                LEFT JOIN users uh ON uh.id = so.handled_by
                WHERE 1=1';
        $params = [];

        if (!empty($filters['customer_id'])) {
            $sql .= ' AND so.customer_id = ?';
            $params[] = (int)$filters['customer_id'];
        }
        if (!empty($filters['booking_id'])) {
            $sql .= ' AND so.booking_id = ?';
            $params[] = (int)$filters['booking_id'];
        }
        if (!empty($filters['status'])) {
            $sql .= ' AND so.status = ?';
            $params[] = $filters['status'];
        }
        $sql .= ' ORDER BY so.created_at DESC, so.id DESC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
