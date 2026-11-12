<?php
/**
 * Operations Model: Cleaning, Maintenance, Tasks, Support & Security Logs
 * Lumina Hotel Management System
 */

declare(strict_types=1);

class OperationsModel
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? getDB();
    }

    /* ---------------- CLEANING ---------------- */

    public function getCleaningList(string $status = ''): array
    {
        $sql = 'SELECT c.*, r.room_number, r.floor, r.status AS room_status,
                       rt.name AS room_type_name,
                       u.full_name AS assigned_staff_name,
                       b.booking_code
                FROM cleaning c
                JOIN rooms r ON r.id = c.room_id
                JOIN room_types rt ON rt.id = r.room_type_id
                LEFT JOIN employees emp ON emp.id = c.assigned_to
                LEFT JOIN users u ON u.id = emp.user_id
                LEFT JOIN bookings b ON b.id = c.booking_id
                WHERE 1=1';
        $params = [];
        if ($status !== '') {
            $sql .= ' AND c.status = ?';
            $params[] = $status;
        }
        $sql .= ' ORDER BY FIELD(c.status, "pending", "in_progress", "completed"), c.created_at DESC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function createCleaning(int $roomId, string $type = 'daily', ?int $assignedEmployeeId = null, string $notes = ''): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO cleaning (room_id, assigned_to, cleaning_type, status, notes)
             VALUES (?, ?, ?, "pending", ?)'
        );
        $stmt->execute([$roomId, $assignedEmployeeId, $type, $notes ?: null]);
        return (int)$this->db->lastInsertId();
    }

    public function updateCleaningStatus(int $cleaningId, string $status, ?int $employeeId = null, string $notes = ''): void
    {
        if (!in_array($status, ['pending', 'in_progress', 'completed'], true)) {
            throw new InvalidArgumentException('Trạng thái vệ sinh không hợp lệ.');
        }

        $this->db->beginTransaction();
        try {
            $stmtGet = $this->db->prepare('SELECT * FROM cleaning WHERE id = ? LIMIT 1');
            $stmtGet->execute([$cleaningId]);
            $item = $stmtGet->fetch();
            if (!$item) {
                throw new RuntimeException('Không tìm thấy phiếu vệ sinh.');
            }

            $startedAt = $status === 'in_progress' && !$item['started_at'] ? date('Y-m-d H:i:s') : $item['started_at'];
            $completedAt = $status === 'completed' ? date('Y-m-d H:i:s') : null;

            $stmt = $this->db->prepare(
                'UPDATE cleaning
                 SET status = ?,
                     assigned_to = COALESCE(?, assigned_to),
                     started_at = COALESCE(?, started_at),
                     completed_at = ?,
                     notes = COALESCE(NULLIF(?, ""), notes)
                 WHERE id = ?'
            );
            $stmt->execute([$status, $employeeId, $startedAt, $completedAt, $notes, $cleaningId]);

            // When cleaning completes, transition room from 'cleaning' back to 'available'
            if ($status === 'completed') {
                $this->db->prepare(
                    'UPDATE rooms SET status = "available" WHERE id = ? AND status = "cleaning"'
                )->execute([(int)$item['room_id']]);
            }

            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /* ---------------- MAINTENANCE ---------------- */

    public function getMaintenanceList(string $status = ''): array
    {
        $sql = 'SELECT m.*, r.room_number, r.floor,
                       ur.full_name AS reporter_name,
                       ua.full_name AS assigned_staff_name
                FROM maintenance m
                LEFT JOIN rooms r ON r.id = m.room_id
                JOIN users ur ON ur.id = m.reported_by
                LEFT JOIN employees emp ON emp.id = m.assigned_to
                LEFT JOIN users ua ON ua.id = emp.user_id
                WHERE 1=1';
        $params = [];
        if ($status !== '') {
            $sql .= ' AND m.status = ?';
            $params[] = $status;
        }
        $sql .= ' ORDER BY FIELD(m.status, "reported", "in_progress", "resolved", "cancelled"), m.created_at DESC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function createMaintenance(array $data): int
    {
        $this->db->beginTransaction();
        try {
            $roomId = !empty($data['room_id']) ? (int)$data['room_id'] : null;
            $stmt = $this->db->prepare(
                'INSERT INTO maintenance (room_id, equipment_name, issue_description, priority, status, reported_by, assigned_to, cost)
                 VALUES (?, ?, ?, ?, "reported", ?, ?, ?)'
            );
            $stmt->execute([
                $roomId,
                $data['equipment_name'],
                $data['issue_description'],
                $data['priority'] ?: 'medium',
                (int)$data['reported_by'],
                !empty($data['assigned_to']) ? (int)$data['assigned_to'] : null,
                (float)($data['cost'] ?? 0),
            ]);
            $id = (int)$this->db->lastInsertId();

            if ($roomId !== null && !empty($data['set_room_maintenance'])) {
                $this->db->prepare(
                    'UPDATE rooms SET status = "maintenance" WHERE id = ? AND status IN ("available", "cleaning")'
                )->execute([$roomId]);
            }

            $this->db->commit();
            return $id;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function updateMaintenance(int $id, string $status, string $notes = '', ?float $cost = null): void
    {
        if (!in_array($status, ['reported', 'in_progress', 'resolved', 'cancelled'], true)) {
            throw new InvalidArgumentException('Trạng thái bảo trì không hợp lệ.');
        }

        $this->db->beginTransaction();
        try {
            $stmtGet = $this->db->prepare('SELECT * FROM maintenance WHERE id = ? LIMIT 1');
            $stmtGet->execute([$id]);
            $item = $stmtGet->fetch();
            if (!$item) {
                throw new RuntimeException('Không tìm thấy phiếu bảo trì.');
            }

            $resolvedAt = $status === 'resolved' ? date('Y-m-d H:i:s') : $item['resolved_at'];
            $stmt = $this->db->prepare(
                'UPDATE maintenance
                 SET status = ?,
                     resolution_notes = COALESCE(NULLIF(?, ""), resolution_notes),
                     cost = COALESCE(?, cost),
                     resolved_at = ?
                 WHERE id = ?'
            );
            $stmt->execute([$status, $notes, $cost, $resolvedAt, $id]);

            if (!empty($item['room_id'])) {
                if ($status === 'in_progress') {
                    $this->db->prepare(
                        'UPDATE rooms SET status = "maintenance" WHERE id = ? AND status IN ("available", "cleaning")'
                    )->execute([(int)$item['room_id']]);
                } elseif ($status === 'resolved' || $status === 'cancelled') {
                    // Return room from maintenance to cleaning/available if no other active maintenance exists
                    $stmtOther = $this->db->prepare(
                        'SELECT COUNT(*) FROM maintenance WHERE room_id = ? AND id != ? AND status IN ("reported", "in_progress")'
                    );
                    $stmtOther->execute([(int)$item['room_id'], $id]);
                    if ((int)$stmtOther->fetchColumn() === 0) {
                        $this->db->prepare(
                            'UPDATE rooms SET status = "available" WHERE id = ? AND status = "maintenance"'
                        )->execute([(int)$item['room_id']]);
                    }
                }
            }

            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /* ---------------- STAFF TASKS ---------------- */

    public function getTasks(?int $employeeId = null, string $status = ''): array
    {
        $sql = 'SELECT t.*,
                       ue.full_name AS assigned_to_name, emp.employee_code, emp.department,
                       ua.full_name AS assigned_by_name
                FROM tasks t
                JOIN employees emp ON emp.id = t.assigned_to
                JOIN users ue ON ue.id = emp.user_id
                JOIN users ua ON ua.id = t.assigned_by
                WHERE 1=1';
        $params = [];

        if ($employeeId !== null) {
            $sql .= ' AND t.assigned_to = ?';
            $params[] = $employeeId;
        }
        if ($status !== '') {
            $sql .= ' AND t.status = ?';
            $params[] = $status;
        }
        $sql .= ' ORDER BY FIELD(t.status, "pending", "in_progress", "completed", "cancelled"), t.due_date ASC, t.id DESC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function createTask(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO tasks (title, description, assigned_to, assigned_by, priority, status, due_date, notes)
             VALUES (?, ?, ?, ?, ?, "pending", ?, ?)'
        );
        $stmt->execute([
            $data['title'],
            $data['description'] ?: null,
            (int)$data['assigned_to'],
            (int)$data['assigned_by'],
            $data['priority'] ?: 'medium',
            $data['due_date'] ?: date('Y-m-d'),
            $data['notes'] ?: null,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function updateTaskStatus(int $taskId, string $status, string $notes = ''): void
    {
        if (!in_array($status, ['pending', 'in_progress', 'completed', 'cancelled'], true)) {
            throw new InvalidArgumentException('Trạng thái công việc không hợp lệ.');
        }
        $completedAt = $status === 'completed' ? date('Y-m-d H:i:s') : null;
        $stmt = $this->db->prepare(
            'UPDATE tasks SET status = ?, completed_at = ?, notes = COALESCE(NULLIF(?, ""), notes) WHERE id = ?'
        );
        $stmt->execute([$status, $completedAt, $notes, $taskId]);
    }

    /* ---------------- SUPPORT REQUESTS ---------------- */

    public function getSupportRequests(?int $customerId = null, string $status = ''): array
    {
        $sql = 'SELECT sr.*, u.full_name AS customer_name, u.phone AS customer_phone,
                       b.booking_code, uh.full_name AS handler_name
                FROM support_requests sr
                JOIN customers c ON c.id = sr.customer_id
                JOIN users u ON u.id = c.user_id
                LEFT JOIN bookings b ON b.id = sr.booking_id
                LEFT JOIN users uh ON uh.id = sr.handled_by
                WHERE 1=1';
        $params = [];

        if ($customerId !== null) {
            $sql .= ' AND sr.customer_id = ?';
            $params[] = $customerId;
        }
        if ($status !== '') {
            $sql .= ' AND sr.status = ?';
            $params[] = $status;
        }
        $sql .= ' ORDER BY FIELD(sr.status, "open", "in_progress", "resolved", "closed"), sr.created_at DESC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function createSupportRequest(array $data): int
    {
        $code = generate_code('SUP');
        $stmt = $this->db->prepare(
            'INSERT INTO support_requests (ticket_code, customer_id, booking_id, subject, category, message, status)
             VALUES (?, ?, ?, ?, ?, ?, "open")'
        );
        $stmt->execute([
            $code,
            (int)$data['customer_id'],
            !empty($data['booking_id']) ? (int)$data['booking_id'] : null,
            $data['subject'],
            $data['category'] ?: 'inquiry',
            $data['message'],
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function respondSupportRequest(int $id, string $status, string $response, int $staffUserId): void
    {
        if (!in_array($status, ['open', 'in_progress', 'resolved', 'closed'], true)) {
            throw new InvalidArgumentException('Trạng thái hỗ trợ không hợp lệ.');
        }
        $resolvedAt = in_array($status, ['resolved', 'closed'], true) ? date('Y-m-d H:i:s') : null;
        $stmt = $this->db->prepare(
            'UPDATE support_requests
             SET status = ?, staff_response = ?, handled_by = ?, resolved_at = COALESCE(?, resolved_at)
             WHERE id = ?'
        );
        $stmt->execute([$status, $response, $staffUserId, $resolvedAt, $id]);
    }

    /* ---------------- SECURITY LOGS & INCIDENTS ---------------- */

    public function getSecurityLogs(string $logType = '', string $severity = '', string $search = '', int $limit = 100): array
    {
        $sql = 'SELECT sl.*, u.username, u.full_name, u.role, ur.full_name AS resolver_name
                FROM security_logs sl
                LEFT JOIN users u ON u.id = sl.user_id
                LEFT JOIN users ur ON ur.id = sl.resolved_by
                WHERE 1=1';
        $params = [];

        if ($logType !== '') {
            $sql .= ' AND sl.log_type = ?';
            $params[] = $logType;
        }
        if ($severity !== '') {
            $sql .= ' AND sl.severity = ?';
            $params[] = $severity;
        }
        if ($search !== '') {
            $sql .= ' AND (sl.action LIKE ? OR sl.details LIKE ? OR sl.ip_address LIKE ? OR u.full_name LIKE ?)';
            $like = '%' . $search . '%';
            array_push($params, $like, $like, $like, $like);
        }
        $sql .= ' ORDER BY sl.created_at DESC, sl.id DESC LIMIT ' . (int)$limit;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function updateSecurityIncidentStatus(int $logId, string $status, int $resolverId): void
    {
        if (!in_array($status, ['logged', 'investigating', 'resolved'], true)) {
            throw new InvalidArgumentException('Trạng thái xử lý sự cố không hợp lệ.');
        }
        $resolvedAt = $status === 'resolved' ? date('Y-m-d H:i:s') : null;
        $stmt = $this->db->prepare(
            'UPDATE security_logs SET status = ?, resolved_by = ?, resolved_at = ? WHERE id = ?'
        );
        $stmt->execute([$status, $resolverId, $resolvedAt, $logId]);
    }

    /* ---------------- LIVE NOTIFICATIONS ---------------- */

    public function getNotificationsForUser(array $user): array
    {
        $role = $user['role'] ?? 'customer';
        $items = [];

        if ($role === 'admin' || $role === 'staff') {
            $pendingBookings = (int)$this->db->query('SELECT COUNT(*) FROM bookings WHERE status = "pending"')->fetchColumn();
            $pendingPayments = (int)$this->db->query('SELECT COUNT(*) FROM payments WHERE status = "pending"')->fetchColumn();
            $pendingServices = (int)$this->db->query('SELECT COUNT(*) FROM service_orders WHERE status = "pending"')->fetchColumn();
            $pendingCleaning = (int)$this->db->query('SELECT COUNT(*) FROM cleaning WHERE status = "pending"')->fetchColumn();
            $openSupport     = (int)$this->db->query('SELECT COUNT(*) FROM support_requests WHERE status IN ("open", "in_progress")')->fetchColumn();

            if ($pendingBookings > 0) {
                $items[] = [
                    'icon'  => 'fa-calendar-check',
                    'color' => 'warning',
                    'text'  => "$pendingBookings đơn đặt phòng mới chờ xác nhận",
                    'link'  => $role === 'admin' ? 'quan-ly/dat-phong.php?status=pending' : 'nhan-su/dat-phong.php?status=pending',
                ];
            }
            if ($pendingPayments > 0) {
                $items[] = [
                    'icon'  => 'fa-credit-card',
                    'color' => 'info',
                    'text'  => "$pendingPayments giao dịch thanh toán chờ đối soát",
                    'link'  => $role === 'admin' ? 'quan-ly/tai-chinh.php' : 'nhan-su/thanh-toan.php',
                ];
            }
            if ($pendingServices > 0) {
                $items[] = [
                    'icon'  => 'fa-bell-concierge',
                    'color' => 'primary',
                    'text'  => "$pendingServices yêu cầu dịch vụ đang chờ xử lý",
                    'link'  => $role === 'admin' ? 'quan-ly/dich-vu.php' : 'nhan-su/dich-vu.php',
                ];
            }
            if ($pendingCleaning > 0) {
                $items[] = [
                    'icon'  => 'fa-broom',
                    'color' => 'warning',
                    'text'  => "$pendingCleaning phòng đang chờ vệ sinh",
                    'link'  => 'nhan-su/ve-sinh-phong.php',
                ];
            }
            if ($openSupport > 0) {
                $items[] = [
                    'icon'  => 'fa-headset',
                    'color' => 'danger',
                    'text'  => "$openSupport yêu cầu hỗ trợ từ khách hàng",
                    'link'  => 'nhan-su/ho-tro.php',
                ];
            }
        } else {
            $custId = (int)($user['customer_id'] ?? 0);
            if ($custId > 0) {
                $stmtB = $this->db->prepare('SELECT COUNT(*) FROM bookings WHERE customer_id = ? AND status = "confirmed"');
                $stmtB->execute([$custId]);
                $conf = (int)$stmtB->fetchColumn();
                if ($conf > 0) {
                    $items[] = [
                        'icon'  => 'fa-suitcase-rolling',
                        'color' => 'success',
                        'text'  => "Bạn có $conf đặt phòng đã được xác nhận",
                        'link'  => 'khach-hang/dat-phong.php',
                    ];
                }

                $stmtS = $this->db->prepare('SELECT COUNT(*) FROM support_requests WHERE customer_id = ? AND status = "resolved"');
                $stmtS->execute([$custId]);
                $res = (int)$stmtS->fetchColumn();
                if ($res > 0) {
                    $items[] = [
                        'icon'  => 'fa-comment-dots',
                        'color' => 'info',
                        'text'  => "$res phản hồi hỗ trợ đã được giải đáp",
                        'link'  => 'khach-hang/ho-tro.php',
                    ];
                }
            }
        }

        return $items;
    }
}
