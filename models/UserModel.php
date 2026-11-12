<?php
/**
 * User, Customer & Employee Model
 * Lumina Hotel Management System
 */

declare(strict_types=1);

class UserModel
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? getDB();
    }

    public function findByIdentifier(string $identifier): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT u.*, c.id AS customer_id, c.vip_tier,
                    e.id AS employee_id, e.employee_code, e.position, e.department
             FROM users u
             LEFT JOIN customers c ON c.user_id = u.id
             LEFT JOIN employees e ON e.user_id = u.id
             WHERE u.email = ? OR u.username = ?
             LIMIT 1'
        );
        $stmt->execute([$identifier, $identifier]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findById(int $userId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT u.*, c.id AS customer_id, c.id_card, c.date_of_birth, c.gender, c.address, c.nationality, c.vip_tier, c.notes AS customer_notes,
                    e.id AS employee_id, e.employee_code, e.position, e.department, e.hire_date, e.salary, e.shift, e.status AS employee_status
             FROM users u
             LEFT JOIN customers c ON c.user_id = u.id
             LEFT JOIN employees e ON e.user_id = u.id
             WHERE u.id = ? LIMIT 1'
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function existsUsernameOrEmail(string $username, string $email, ?int $excludeUserId = null): bool
    {
        if ($excludeUserId !== null) {
            $stmt = $this->db->prepare('SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ? LIMIT 1');
            $stmt->execute([$username, $email, $excludeUserId]);
        } else {
            $stmt = $this->db->prepare('SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1');
            $stmt->execute([$username, $email]);
        }
        return (bool)$stmt->fetch();
    }

    public function updateLastLogin(int $userId): void
    {
        $stmt = $this->db->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?');
        $stmt->execute([$userId]);
    }

    /**
     * Register a new customer (users + customers inside transaction).
     */
    public function registerCustomer(array $data): int
    {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                'INSERT INTO users (username, email, password_hash, full_name, phone, role, status)
                 VALUES (?, ?, ?, ?, ?, "customer", "active")'
            );
            $stmt->execute([
                $data['username'],
                $data['email'],
                password_hash($data['password'], PASSWORD_DEFAULT),
                $data['full_name'],
                $data['phone'] ?? null,
            ]);
            $userId = (int)$this->db->lastInsertId();

            $stmtCust = $this->db->prepare(
                'INSERT INTO customers (user_id, id_card, address, nationality)
                 VALUES (?, ?, ?, ?)'
            );
            $stmtCust->execute([
                $userId,
                $data['id_card'] ?? null,
                $data['address'] ?? null,
                $data['nationality'] ?? 'Việt Nam',
            ]);

            $this->db->commit();
            return $userId;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Update customer profile (self-service or admin edit).
     */
    public function updateCustomerProfile(int $userId, array $data): void
    {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                'UPDATE users SET full_name = ?, phone = ?, email = ? WHERE id = ?'
            );
            $stmt->execute([
                $data['full_name'],
                $data['phone'] ?: null,
                $data['email'],
                $userId,
            ]);

            $stmtCust = $this->db->prepare(
                'UPDATE customers
                 SET id_card = ?, date_of_birth = ?, gender = ?, address = ?, nationality = ?, vip_tier = COALESCE(?, vip_tier), notes = COALESCE(?, notes)
                 WHERE user_id = ?'
            );
            $stmtCust->execute([
                $data['id_card'] ?: null,
                $data['date_of_birth'] ?: null,
                $data['gender'] ?: null,
                $data['address'] ?: null,
                $data['nationality'] ?: 'Việt Nam',
                $data['vip_tier'] ?? null,
                $data['notes'] ?? null,
                $userId,
            ]);

            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function changePassword(int $userId, string $currentPassword, string $newPassword): bool
    {
        $stmt = $this->db->prepare('SELECT password_hash FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        if (!$row || !password_verify($currentPassword, $row['password_hash'])) {
            return false;
        }

        $upd = $this->db->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
        $upd->execute([password_hash($newPassword, PASSWORD_DEFAULT), $userId]);
        return true;
    }

    public function toggleUserStatus(int $userId, string $status): void
    {
        $stmt = $this->db->prepare('UPDATE users SET status = ? WHERE id = ?');
        $stmt->execute([$status, $userId]);
    }

    /* ---------------- CUSTOMERS (Admin/Staff) ---------------- */

    public function getAllCustomers(string $search = '', string $vipTier = ''): array
    {
        $sql = 'SELECT c.*, u.id AS user_id, u.username, u.email, u.full_name, u.phone, u.status AS user_status, u.created_at AS joined_at,
                       (SELECT COUNT(*) FROM bookings b WHERE b.customer_id = c.id) AS total_bookings,
                       (SELECT COALESCE(SUM(p.amount), 0) FROM payments p WHERE p.customer_id = c.id AND p.status = "completed") AS total_spent
                FROM customers c
                JOIN users u ON u.id = c.user_id
                WHERE 1=1';
        $params = [];

        if ($search !== '') {
            $sql .= ' AND (u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ? OR c.id_card LIKE ?)';
            $like = '%' . $search . '%';
            array_push($params, $like, $like, $like, $like);
        }
        if ($vipTier !== '') {
            $sql .= ' AND c.vip_tier = ?';
            $params[] = $vipTier;
        }
        $sql .= ' ORDER BY c.id DESC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function countCustomers(): int
    {
        return (int)$this->db->query('SELECT COUNT(*) FROM customers')->fetchColumn();
    }

    /* ---------------- EMPLOYEES (Admin) ---------------- */

    public function getAllEmployees(string $search = '', string $department = ''): array
    {
        $sql = 'SELECT e.*, u.id AS user_id, u.username, u.email, u.full_name, u.phone, u.status AS user_status, u.last_login_at,
                       (SELECT COUNT(*) FROM tasks t WHERE t.assigned_to = e.id AND t.status IN ("pending","in_progress")) AS active_tasks
                FROM employees e
                JOIN users u ON u.id = e.user_id
                WHERE 1=1';
        $params = [];

        if ($search !== '') {
            $sql .= ' AND (u.full_name LIKE ? OR e.employee_code LIKE ? OR u.email LIKE ? OR e.position LIKE ?)';
            $like = '%' . $search . '%';
            array_push($params, $like, $like, $like, $like);
        }
        if ($department !== '') {
            $sql .= ' AND e.department = ?';
            $params[] = $department;
        }
        $sql .= ' ORDER BY e.id DESC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function countEmployees(): int
    {
        return (int)$this->db->query('SELECT COUNT(*) FROM employees')->fetchColumn();
    }

    public function createEmployee(array $data): int
    {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                'INSERT INTO users (username, email, password_hash, full_name, phone, role, status)
                 VALUES (?, ?, ?, ?, ?, "staff", "active")'
            );
            $stmt->execute([
                $data['username'],
                $data['email'],
                password_hash($data['password'], PASSWORD_DEFAULT),
                $data['full_name'],
                $data['phone'] ?: null,
            ]);
            $userId = (int)$this->db->lastInsertId();

            $empCode = $data['employee_code'] ?: ('EMP-' . str_pad((string)$userId, 3, '0', STR_PAD_LEFT));
            $stmtEmp = $this->db->prepare(
                'INSERT INTO employees (user_id, employee_code, position, department, hire_date, salary, shift, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmtEmp->execute([
                $userId,
                $empCode,
                $data['position'],
                $data['department'],
                $data['hire_date'] ?: date('Y-m-d'),
                (float)$data['salary'],
                $data['shift'] ?: 'full',
                'active',
            ]);

            $this->db->commit();
            return (int)$this->db->lastInsertId();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function updateEmployee(int $employeeId, array $data): void
    {
        $this->db->beginTransaction();
        try {
            $stmtGet = $this->db->prepare('SELECT user_id FROM employees WHERE id = ? LIMIT 1');
            $stmtGet->execute([$employeeId]);
            $emp = $stmtGet->fetch();
            if (!$emp) {
                throw new RuntimeException('Nhân viên không tồn tại.');
            }
            $userId = (int)$emp['user_id'];

            $stmtUser = $this->db->prepare('UPDATE users SET full_name = ?, phone = ?, email = ? WHERE id = ?');
            $stmtUser->execute([$data['full_name'], $data['phone'] ?: null, $data['email'], $userId]);

            if (!empty($data['new_password'])) {
                $stmtPwd = $this->db->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
                $stmtPwd->execute([password_hash($data['new_password'], PASSWORD_DEFAULT), $userId]);
            }

            $stmtEmp = $this->db->prepare(
                'UPDATE employees SET position = ?, department = ?, hire_date = ?, salary = ?, shift = ?, status = ? WHERE id = ?'
            );
            $stmtEmp->execute([
                $data['position'],
                $data['department'],
                $data['hire_date'],
                (float)$data['salary'],
                $data['shift'],
                $data['status'],
                $employeeId,
            ]);

            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function deleteEmployee(int $employeeId): void
    {
        $stmt = $this->db->prepare('SELECT user_id FROM employees WHERE id = ? LIMIT 1');
        $stmt->execute([$employeeId]);
        $row = $stmt->fetch();
        if ($row) {
            $del = $this->db->prepare('DELETE FROM users WHERE id = ? AND role = "staff"');
            $del->execute([(int)$row['user_id']]);
        }
    }
}
