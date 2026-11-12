<?php
/**
 * Logout Handler
 * Lumina Hotel Management System
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    $uid = current_user_id();
    $name = current_user()['full_name'] ?? '';
    log_security_event($uid, 'Đăng xuất khỏi hệ thống', 'login', 'info', 'Người dùng ' . $name . ' đã đăng xuất.');
}

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}
session_destroy();

session_start();
set_flash('info', 'Bạn đã đăng xuất an toàn.');
redirect('dang-nhap.php');
