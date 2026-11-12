<?php
/**
 * Authentication Controller
 * Lumina Hotel Management System
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/models/UserModel.php';

class AuthController
{
    private UserModel $userModel;

    public function __construct()
    {
        $this->userModel = new UserModel();
    }

    public function handleLogin(string $identifier, string $password): array
    {
        $identifier = trim($identifier);
        if ($identifier === '' || $password === '') {
            return ['ok' => false, 'message' => 'Vui lòng nhập đầy đủ email/tên đăng nhập và mật khẩu.'];
        }

        $user = $this->userModel->findByIdentifier($identifier);
        if (!$user || !password_verify($password, $user['password_hash'])) {
            log_security_event(
                $user['id'] ?? null,
                'Đăng nhập thất bại',
                'login',
                'warning',
                'Đăng nhập thất bại với tài khoản: ' . $identifier,
                'logged'
            );
            return ['ok' => false, 'message' => 'Thông tin đăng nhập không chính xác.'];
        }

        if ($user['status'] !== 'active') {
            log_security_event(
                (int)$user['id'],
                'Nỗ lực đăng nhập vào tài khoản bị khóa',
                'incident',
                'warning',
                'Tài khoản ' . $user['username'] . ' đang bị khóa nhưng cố gắng đăng nhập.',
                'logged'
            );
            return ['ok' => false, 'message' => 'Tài khoản của bạn đã bị khóa. Vui lòng liên hệ quản lý khách sạn.'];
        }

        session_regenerate_id(true);
        unset($user['password_hash']);
        $_SESSION['user'] = $user;
        $this->userModel->updateLastLogin((int)$user['id']);

        log_security_event(
            (int)$user['id'],
            'Đăng nhập thành công (' . strtoupper($user['role']) . ')',
            'login',
            'info',
            'Người dùng ' . $user['full_name'] . ' đã đăng nhập.',
            'logged'
        );

        return ['ok' => true, 'redirect' => role_dashboard_path($user['role'])];
    }

    public function handleRegister(array $input): array
    {
        $fullName = trim((string)($input['full_name'] ?? ''));
        $username = trim((string)($input['username'] ?? ''));
        $email    = trim((string)($input['email'] ?? ''));
        $phone    = trim((string)($input['phone'] ?? ''));
        $password = (string)($input['password'] ?? '');
        $confirm  = (string)($input['confirm_password'] ?? '');

        if ($fullName === '' || $username === '' || $email === '' || $password === '') {
            return ['ok' => false, 'message' => 'Vui lòng điền đầy đủ các trường bắt buộc.'];
        }
        if (!preg_match('/^[a-zA-Z0-9_]{4,30}$/', $username)) {
            return ['ok' => false, 'message' => 'Tên đăng nhập chỉ gồm chữ, số, dấu gạch dưới (4–30 ký tự).'];
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'message' => 'Địa chỉ email không hợp lệ.'];
        }
        if (strlen($password) < 6) {
            return ['ok' => false, 'message' => 'Mật khẩu phải có ít nhất 6 ký tự.'];
        }
        if ($password !== $confirm) {
            return ['ok' => false, 'message' => 'Mật khẩu xác nhận không khớp.'];
        }
        if ($this->userModel->existsUsernameOrEmail($username, $email)) {
            return ['ok' => false, 'message' => 'Tên đăng nhập hoặc email đã được sử dụng.'];
        }

        $userId = $this->userModel->registerCustomer([
            'full_name'   => $fullName,
            'username'    => $username,
            'email'       => $email,
            'phone'       => $phone,
            'password'    => $password,
            'id_card'     => trim((string)($input['id_card'] ?? '')),
            'address'     => trim((string)($input['address'] ?? '')),
            'nationality' => trim((string)($input['nationality'] ?? 'Việt Nam')),
        ]);

        log_security_event(
            $userId,
            'Đăng ký tài khoản khách hàng mới',
            'action',
            'info',
            'Khách hàng ' . $fullName . ' (' . $email . ') đã tạo tài khoản.',
            'logged'
        );

        return ['ok' => true, 'message' => 'Đăng ký tài khoản thành công! Vui lòng đăng nhập để đặt phòng.'];
    }
}
