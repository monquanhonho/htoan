<?php
/**
 * Authentication & Role-Based Access Control
 * Lumina Hotel Management System
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';

/**
 * Check if a user is logged in.
 */
function is_logged_in(): bool
{
    return !empty($_SESSION['user']['id']);
}

/**
 * Get current authenticated user array from session.
 */
function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

/**
 * Get current user ID.
 */
function current_user_id(): ?int
{
    return isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : null;
}

/**
 * Get current user's role ('customer', 'staff', 'admin').
 */
function current_role(): ?string
{
    return $_SESSION['user']['role'] ?? null;
}

/**
 * Return dashboard path for a given role.
 */
function role_dashboard_path(string $role): string
{
    return match ($role) {
        'admin'    => 'quan-ly/bang-dieu-khien.php',
        'staff'    => 'nhan-su/bang-dieu-khien.php',
        default    => 'khach-hang/trang-chu.php',
    };
}

/**
 * Refresh user session data from DB (ensures locked accounts are booted immediately).
 */
function sync_user_session(): void
{
    if (!is_logged_in()) {
        return;
    }

    $pdo = getDB();
    $stmt = $pdo->prepare(
        'SELECT u.id, u.username, u.email, u.full_name, u.phone, u.role, u.status, u.avatar,
                c.id AS customer_id, c.vip_tier,
                e.id AS employee_id, e.employee_code, e.position, e.department
         FROM users u
         LEFT JOIN customers c ON c.user_id = u.id
         LEFT JOIN employees e ON e.user_id = u.id
         WHERE u.id = ? LIMIT 1'
    );
    $stmt->execute([current_user_id()]);
    $user = $stmt->fetch();

    if (!$user || $user['status'] !== 'active') {
        $_SESSION = [];
        session_destroy();
        session_start();
        set_flash('danger', 'Tài khoản của bạn đã bị khóa hoặc không tồn tại.');
        redirect('dang-nhap.php');
    }

    $_SESSION['user'] = $user;
}

/**
 * Require login; redirect to dang-nhap.php if unauthenticated.
 */
function require_login(): void
{
    if (!is_logged_in()) {
        set_flash('warning', 'Vui lòng đăng nhập để tiếp tục.');
        redirect('dang-nhap.php');
    }
    sync_user_session();
}

/**
 * Require one of the allowed roles.
 * Prevents customers from accessing admin/staff pages and staff from accessing admin-only pages.
 */
function require_role(array|string $allowedRoles): void
{
    require_login();
    $allowed = (array)$allowedRoles;
    $role = current_role();

    if (!in_array($role, $allowed, true)) {
        log_security_event(
            current_user_id(),
            'Truy cập trái phép khu vực phân quyền',
            'incident',
            'warning',
            sprintf('User role "%s" attempted to access %s', (string)$role, $_SERVER['REQUEST_URI'] ?? ''),
            'logged'
        );
        set_flash('danger', 'Bạn không có quyền truy cập chức năng này.');
        redirect(role_dashboard_path((string)$role));
    }
}
