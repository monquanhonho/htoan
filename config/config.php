<?php
/**
 * Core Application Configuration & Security Helpers
 * Lumina Hotel Management System
 */

declare(strict_types=1);

date_default_timezone_set('Asia/Ho_Chi_Minh');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}
if (!headers_sent() && PHP_SAPI !== 'cli') {
    header('Content-Type: text/html; charset=UTF-8');
}

define('APP_NAME', 'PURPLE LOTUS HOTEL');
define('APP_TAGLINE', 'Luxury Hotel Management System');
define('APP_LOGO', 'assets/images/services/logo-lotus-hotel.png');
define('ROOT_PATH', dirname(__DIR__));

// Auto-detect base URL relative to web server document root
$docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;
$appRoot = realpath(ROOT_PATH);
$detectedBase = '';
if ($docRoot && $appRoot) {
    $docRootNorm = str_replace('\\', '/', rtrim($docRoot, '\\/'));
    $appRootNorm = str_replace('\\', '/', rtrim($appRoot, '\\/'));
    if (stripos($appRootNorm, $docRootNorm) === 0) {
        $detectedBase = substr($appRootNorm, strlen($docRootNorm));
    }
}
define('BASE_URL', rtrim($detectedBase, '/'));

// Secure session startup
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => BASE_URL !== '' ? BASE_URL : '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

require_once ROOT_PATH . '/config/database.php';

/**
 * Build URL relative to project root.
 */
function base_url(string $path = ''): string
{
    $clean = ltrim($path, '/');
    return (BASE_URL !== '' ? BASE_URL : '') . ($clean !== '' ? '/' . $clean : '/');
}

/**
 * Redirect helper.
 */
function redirect(string $path): never
{
    $target = str_starts_with($path, 'http') ? $path : base_url($path);
    header('Location: ' . $target);
    exit;
}

/**
 * Escape HTML to prevent XSS.
 */
function e(mixed $value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Generate or return existing CSRF token.
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Hidden HTML input containing CSRF token.
 */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/**
 * Verify CSRF token from POST or custom header.
 */
function verify_csrf(): bool
{
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    return is_string($token) && $token !== '' && hash_equals(csrf_token(), $token);
}

/**
 * Enforce CSRF verification or terminate with error/redirect.
 */
function require_csrf(?string $redirectBack = null): void
{
    if (!verify_csrf()) {
        if ($redirectBack !== null) {
            set_flash('danger', 'Phiên làm việc không hợp lệ hoặc đã hết hạn (CSRF). Vui lòng thử lại.');
            redirect($redirectBack);
        }
        http_response_code(403);
        exit('403 Forbidden - Invalid CSRF Token.');
    }
}

/**
 * Flash message helpers.
 */
function set_flash(string $type, string $message): void
{
    $_SESSION['flash_messages'][] = [
        'type'    => $type,
        'message' => $message,
    ];
}

function get_flashes(): array
{
    $messages = $_SESSION['flash_messages'] ?? [];
    unset($_SESSION['flash_messages']);
    return $messages;
}

/**
 * Format Vietnamese Dong currency.
 */
function format_currency(float|int|string $amount): string
{
    return number_format((float)$amount, 0, ',', '.') . ' ₫';
}

function format_date(?string $date): string
{
    if (!$date) {
        return '—';
    }
    $ts = strtotime($date);
    return $ts ? date('d/m/Y', $ts) : '—';
}

function format_datetime(?string $datetime): string
{
    if (!$datetime) {
        return '—';
    }
    $ts = strtotime($datetime);
    return $ts ? date('d/m/Y H:i', $ts) : '—';
}

/**
 * Calculate number of nights between check-in and check-out.
 */
function calculate_nights(string $checkIn, string $checkOut): int
{
    $d1 = DateTimeImmutable::createFromFormat('Y-m-d', $checkIn);
    $d2 = DateTimeImmutable::createFromFormat('Y-m-d', $checkOut);
    if (!$d1 || !$d2 || $d2 <= $d1) {
        return 0;
    }
    return (int)$d1->diff($d2)->days;
}

/**
 * Generate unique reference code (e.g. LUM-20261110-1234).
 */
function generate_code(string $prefix): string
{
    return strtoupper($prefix) . '-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
}

/**
 * Render consistent Vietnamese status badge.
 */
function status_badge(string $status, string $domain = 'general'): string
{
    $maps = [
        'room' => [
            'available'   => ['Trống', 'badge-lux-success'],
            'reserved'    => ['Đã đặt', 'badge-lux-info'],
            'occupied'    => ['Đang sử dụng', 'badge-lux-navy'],
            'cleaning'    => ['Cần vệ sinh', 'badge-lux-warning'],
            'maintenance' => ['Đang bảo trì', 'badge-lux-danger'],
        ],
        'booking' => [
            'pending'     => ['Chờ xác nhận', 'badge-lux-warning'],
            'confirmed'   => ['Đã xác nhận', 'badge-lux-info'],
            'checked_in'  => ['Đang lưu trú', 'badge-lux-navy'],
            'checked_out' => ['Đã trả phòng', 'badge-lux-success'],
            'cancelled'   => ['Đã hủy', 'badge-lux-secondary'],
            'rejected'    => ['Từ chối', 'badge-lux-danger'],
        ],
        'payment' => [
            'pending'   => ['Chờ xác nhận', 'badge-lux-warning'],
            'completed' => ['Đã thanh toán', 'badge-lux-success'],
            'failed'    => ['Thất bại', 'badge-lux-danger'],
            'refunded'  => ['Đã hoàn tiền', 'badge-lux-secondary'],
        ],
        'service' => [
            'pending'     => ['Chờ xử lý', 'badge-lux-warning'],
            'confirmed'   => ['Đã tiếp nhận', 'badge-lux-info'],
            'in_progress' => ['Đang thực hiện', 'badge-lux-navy'],
            'completed'   => ['Hoàn thành', 'badge-lux-success'],
            'cancelled'   => ['Đã hủy', 'badge-lux-secondary'],
        ],
        'task' => [
            'pending'     => ['Chờ xử lý', 'badge-lux-warning'],
            'in_progress' => ['Đang thực hiện', 'badge-lux-info'],
            'completed'   => ['Hoàn thành', 'badge-lux-success'],
            'cancelled'   => ['Đã hủy', 'badge-lux-secondary'],
        ],
        'maintenance' => [
            'reported'    => ['Mới ghi nhận', 'badge-lux-warning'],
            'in_progress' => ['Đang sửa chữa', 'badge-lux-info'],
            'resolved'    => ['Đã khắc phục', 'badge-lux-success'],
            'cancelled'   => ['Đã hủy', 'badge-lux-secondary'],
        ],
        'cleaning' => [
            'pending'     => ['Chờ vệ sinh', 'badge-lux-warning'],
            'in_progress' => ['Đang dọn phòng', 'badge-lux-info'],
            'completed'   => ['Sạch sẽ', 'badge-lux-success'],
        ],
        'support' => [
            'open'        => ['Mới gửi', 'badge-lux-warning'],
            'in_progress' => ['Đang xử lý', 'badge-lux-info'],
            'resolved'    => ['Đã giải quyết', 'badge-lux-success'],
            'closed'      => ['Đã đóng', 'badge-lux-secondary'],
        ],
        'user' => [
            'active' => ['Hoạt động', 'badge-lux-success'],
            'locked' => ['Đã khóa', 'badge-lux-danger'],
        ],
        'priority' => [
            'low'    => ['Thấp', 'badge-lux-secondary'],
            'medium' => ['Trung bình', 'badge-lux-info'],
            'high'   => ['Cao', 'badge-lux-warning'],
            'urgent' => ['Khẩn cấp', 'badge-lux-danger'],
        ],
        'severity' => [
            'info'     => ['Thông tin', 'badge-lux-info'],
            'warning'  => ['Cảnh báo', 'badge-lux-warning'],
            'critical' => ['Nghiêm trọng', 'badge-lux-danger'],
        ],
        'security_status' => [
            'logged'        => ['Đã ghi nhận', 'badge-lux-secondary'],
            'investigating' => ['Đang điều tra', 'badge-lux-warning'],
            'resolved'      => ['Đã xử lý', 'badge-lux-success'],
        ],
    ];

    $label = ucfirst(str_replace('_', ' ', $status));
    $class = 'badge-lux-secondary';

    if (isset($maps[$domain][$status])) {
        [$label, $class] = $maps[$domain][$status];
    }

    return '<span class="badge-lux ' . e($class) . '">' . e($label) . '</span>';
}

function service_category_label(string $cat): string
{
    return match ($cat) {
        'dining'    => 'Ẩm thực & Nhà hàng',
        'laundry'   => 'Giặt ủi cao cấp',
        'spa'       => 'Spa & Thư giãn',
        'transport' => 'Đưa đón & Xe sang',
        default     => 'Dịch vụ khác',
    };
}

function expense_category_label(string $cat): string
{
    return match ($cat) {
        'utilities'   => 'Điện, nước & Tiện ích',
        'maintenance' => 'Bảo trì thiết bị',
        'supplies'    => 'Vật tư & Amenities',
        'salary'      => 'Lương & Thưởng',
        'marketing'   => 'Marketing & Quảng bá',
        default       => 'Chi phí khác',
    };
}

/**
 * Record an entry in security_logs.
 */
function log_security_event(
    ?int $userId,
    string $action,
    string $logType = 'action',
    string $severity = 'info',
    string $details = '',
    string $status = 'logged'
): void {
    try {
        $pdo = getDB();
        $stmt = $pdo->prepare(
            'INSERT INTO security_logs (user_id, action, log_type, severity, status, ip_address, user_agent, details)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $userId,
            $action,
            $logType,
            $severity,
            $status,
            $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            substr($_SERVER['HTTP_USER_AGENT'] ?? 'CLI/Unknown', 0, 250),
            $details,
        ]);
    } catch (Throwable $e) {
        error_log('Failed to write security_log: ' . $e->getMessage());
    }
}

/**
 * Safely upload an image into uploads/{subdir}/.
 * Returns relative path on success, or null if no file uploaded.
 * Throws RuntimeException on invalid file.
 */
function upload_image(array $file, string $subdir = 'rooms'): ?string
{
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Lỗi tải ảnh lên (Mã lỗi: ' . $file['error'] . ').');
    }
    if ($file['size'] > 5 * 1024 * 1024) {
        throw new RuntimeException('Kích thước ảnh không được vượt quá 5MB.');
    }

    $allowedExt = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) {
        throw new RuntimeException('Chỉ hỗ trợ định dạng ảnh JPG, PNG, WEBP, GIF hoặc SVG.');
    }

    if ($ext !== 'svg') {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        if (!in_array($mime, $allowedMimes, true)) {
            throw new RuntimeException('Tệp tải lên không phải hình ảnh hợp lệ.');
        }
    }

    $safeDir = preg_replace('/[^a-z0-9_-]/i', '', $subdir);
    $targetDir = ROOT_PATH . '/uploads/' . $safeDir;
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0755, true);
    }

    $filename = $safeDir . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $destPath = $targetDir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        throw new RuntimeException('Không thể lưu tệp hình ảnh.');
    }

    return 'uploads/' . $safeDir . '/' . $filename;
}
