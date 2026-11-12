<?php
/**
 * Payment & Financial Model
 * Lumina Hotel Management System
 */

declare(strict_types=1);

class PaymentModel
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? getDB();
    }

    public function createPayment(array $data): array
    {
        $bookingId = (int)$data['booking_id'];
        $custId    = (int)$data['customer_id'];
        $amount    = (float)$data['amount'];
        $method    = $data['payment_method'];
        $type      = $data['payment_type'] ?? 'full';
        $ref       = trim((string)($data['transaction_ref'] ?? ''));
        $notes     = trim((string)($data['notes'] ?? ''));

        if ($amount <= 0) {
            throw new RuntimeException('Số tiền thanh toán phải lớn hơn 0.');
        }
        if (!in_array($method, ['cash', 'bank_transfer'], true)) {
            throw new RuntimeException('Phương thức thanh toán không hợp lệ.');
        }

        $code = generate_code('PAY');
        // Staff/admin direct confirmation marks completed immediately; customer submission is pending until verified
        $isDirectConfirm = !empty($data['confirmed_by']);
        $status = $isDirectConfirm ? 'completed' : 'pending';
        $paidAt = $isDirectConfirm ? date('Y-m-d H:i:s') : null;

        $stmt = $this->db->prepare(
            'INSERT INTO payments (payment_code, booking_id, customer_id, amount, payment_method, payment_type, status, transaction_ref, paid_at, confirmed_by, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $code,
            $bookingId,
            $custId,
            $amount,
            $method,
            $type,
            $status,
            $ref ?: null,
            $paidAt,
            $data['confirmed_by'] ?? null,
            $notes ?: null,
        ]);

        return [
            'id'           => (int)$this->db->lastInsertId(),
            'payment_code' => $code,
            'status'       => $status,
        ];
    }

    public function confirmPayment(int $paymentId, int $actorUserId, string $newStatus = 'completed'): void
    {
        if (!in_array($newStatus, ['completed', 'failed', 'refunded'], true)) {
            throw new InvalidArgumentException('Trạng thái thanh toán không hợp lệ.');
        }

        $stmt = $this->db->prepare(
            'UPDATE payments
             SET status = ?,
                 confirmed_by = ?,
                 paid_at = CASE WHEN ? = "completed" THEN COALESCE(paid_at, NOW()) ELSE paid_at END
             WHERE id = ?'
        );
        $stmt->execute([$newStatus, $actorUserId, $newStatus, $paymentId]);
    }

    public function getPayments(array $filters = []): array
    {
        $sql = 'SELECT p.*,
                       b.booking_code, b.total_amount AS booking_total, b.status AS booking_status,
                       u.full_name AS customer_name, u.phone AS customer_phone,
                       uc.full_name AS confirmed_by_name,
                       r.room_number
                FROM payments p
                JOIN bookings b ON b.id = p.booking_id
                JOIN customers c ON c.id = p.customer_id
                JOIN users u ON u.id = c.user_id
                LEFT JOIN users uc ON uc.id = p.confirmed_by
                LEFT JOIN booking_details bd ON bd.booking_id = b.id
                LEFT JOIN rooms r ON r.id = bd.room_id
                WHERE 1=1';
        $params = [];

        if (!empty($filters['customer_id'])) {
            $sql .= ' AND p.customer_id = ?';
            $params[] = (int)$filters['customer_id'];
        }
        if (!empty($filters['booking_id'])) {
            $sql .= ' AND p.booking_id = ?';
            $params[] = (int)$filters['booking_id'];
        }
        if (!empty($filters['status'])) {
            $sql .= ' AND p.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['payment_method'])) {
            $sql .= ' AND p.payment_method = ?';
            $params[] = $filters['payment_method'];
        }
        if (!empty($filters['search'])) {
            $sql .= ' AND (p.payment_code LIKE ? OR b.booking_code LIKE ? OR u.full_name LIKE ? OR p.transaction_ref LIKE ?)';
            $like = '%' . $filters['search'] . '%';
            array_push($params, $like, $like, $like, $like);
        }
        $sql .= ' ORDER BY p.created_at DESC, p.id DESC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Financial KPIs for Admin Dashboard & Finance page.
     */
    public function getRevenueSummary(): array
    {
        $today = (float)$this->db->query(
            'SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = "completed" AND DATE(paid_at) = CURDATE()'
        )->fetchColumn();

        $month = (float)$this->db->query(
            'SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = "completed" AND YEAR(paid_at) = YEAR(CURDATE()) AND MONTH(paid_at) = MONTH(CURDATE())'
        )->fetchColumn();

        $year = (float)$this->db->query(
            'SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = "completed" AND YEAR(paid_at) = YEAR(CURDATE())'
        )->fetchColumn();

        $roomRevenue = (float)$this->db->query(
            'SELECT COALESCE(SUM(total_room_amount), 0) FROM bookings WHERE status IN ("checked_in", "checked_out")'
        )->fetchColumn();

        $serviceRevenue = (float)$this->db->query(
            'SELECT COALESCE(SUM(total_service_amount), 0) FROM bookings WHERE status IN ("checked_in", "checked_out")'
        )->fetchColumn();

        $monthExpense = (float)$this->db->query(
            'SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE YEAR(expense_date) = YEAR(CURDATE()) AND MONTH(expense_date) = MONTH(CURDATE())'
        )->fetchColumn();

        $yearExpense = (float)$this->db->query(
            'SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE YEAR(expense_date) = YEAR(CURDATE())'
        )->fetchColumn();

        return [
            'today'           => $today,
            'month'           => $month,
            'year'            => $year,
            'room_revenue'    => $roomRevenue,
            'service_revenue' => $serviceRevenue,
            'month_expense'   => $monthExpense,
            'year_expense'    => $yearExpense,
            'month_profit'    => $month - $monthExpense,
            'year_profit'     => $year - $yearExpense,
        ];
    }

    /**
     * Monthly revenue & expense breakdown for Chart.js (12 months of selected year).
     */
    public function getMonthlyChartSeries(?int $year = null): array
    {
        $targetYear = $year ?? (int)date('Y');
        $revenues = array_fill(1, 12, 0.0);
        $expenses = array_fill(1, 12, 0.0);

        $stmtRev = $this->db->prepare(
            'SELECT MONTH(paid_at) AS m, COALESCE(SUM(amount), 0) AS total
             FROM payments
             WHERE status = "completed" AND YEAR(paid_at) = ?
             GROUP BY MONTH(paid_at)'
        );
        $stmtRev->execute([$targetYear]);
        foreach ($stmtRev->fetchAll() as $r) {
            $revenues[(int)$r['m']] = (float)$r['total'];
        }

        $stmtExp = $this->db->prepare(
            'SELECT MONTH(expense_date) AS m, COALESCE(SUM(amount), 0) AS total
             FROM expenses
             WHERE YEAR(expense_date) = ?
             GROUP BY MONTH(expense_date)'
        );
        $stmtExp->execute([$targetYear]);
        foreach ($stmtExp->fetchAll() as $e) {
            $expenses[(int)$e['m']] = (float)$e['total'];
        }

        $labels = [];
        for ($i = 1; $i <= 12; $i++) {
            $labels[] = 'Tháng ' . $i;
        }

        return [
            'year'     => $targetYear,
            'labels'   => $labels,
            'revenues' => array_values($revenues),
            'expenses' => array_values($expenses),
        ];
    }

    /* ---------------- EXPENSES ---------------- */

    public function getExpenses(string $category = '', string $month = ''): array
    {
        $sql = 'SELECT e.*, u.full_name AS recorder_name
                FROM expenses e
                LEFT JOIN users u ON u.id = e.recorded_by
                WHERE 1=1';
        $params = [];

        if ($category !== '') {
            $sql .= ' AND e.category = ?';
            $params[] = $category;
        }
        if ($month !== '') {
            $sql .= ' AND DATE_FORMAT(e.expense_date, "%Y-%m") = ?';
            $params[] = $month;
        }
        $sql .= ' ORDER BY e.expense_date DESC, e.id DESC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function createExpense(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO expenses (title, category, amount, expense_date, description, recorded_by)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['title'],
            $data['category'],
            (float)$data['amount'],
            $data['expense_date'],
            $data['description'] ?: null,
            $data['recorded_by'],
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function deleteExpense(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM expenses WHERE id = ?');
        $stmt->execute([$id]);
    }
}
