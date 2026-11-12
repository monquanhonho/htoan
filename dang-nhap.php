<?php
/**
 * Login Page
 * Lumina Hotel Management System
 */

declare(strict_types=1);

require_once __DIR__ . '/controllers/AuthController.php';

if (is_logged_in()) {
    redirect(role_dashboard_path((string)current_role()));
}

$error = '';
$identifier = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('dang-nhap.php');
    $identifier = trim((string)($_POST['identifier'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    $auth = new AuthController();
    $res = $auth->handleLogin($identifier, $password);
    if ($res['ok']) {
        set_flash('success', 'Chào mừng bạn quay trở lại Purple Lotus Hotel!');
        redirect($res['redirect']);
    }
    $error = $res['message'];
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Đăng nhập | <?= e(APP_NAME) ?></title>
  <link href="<?= e(base_url('assets/vendor/bootstrap/css/bootstrap.min.css')) ?>" rel="stylesheet">
  <link href="<?= e(base_url('assets/vendor/fontawesome/css/all.min.css')) ?>" rel="stylesheet">
  <link href="<?= e(base_url('assets/css/style.css?v=' . (@filemtime(ROOT_PATH . '/assets/css/style.css') ?: 1))) ?>" rel="stylesheet">
</head>
<body style="background:var(--lum-cream);">
<div class="container-fluid min-vh-100 d-flex align-items-center justify-content-center py-4">
  <div class="row w-100 shadow-lg overflow-hidden bg-white" style="max-width:980px;border-radius:18px;border:1px solid var(--lum-border);">
    <!-- Left Luxury Panel -->
    <div class="col-lg-5 d-none d-lg-flex flex-column justify-content-between p-5 text-white lum-hero">
      <div>
        <div class="d-flex align-items-center gap-3 mb-4">
          <img src="<?= e(base_url(APP_LOGO)) ?>" alt="Purple Lotus Hotel" class="lum-brand-logo" style="width:50px;height:50px;">
          <div>
            <div class="lum-brand-title" style="font-size:1.05rem;">PURPLE LOTUS</div>
            <div class="lum-brand-sub">Luxury Hotel & Resort</div>
          </div>
        </div>
        <h2 class="font-serif mt-4 mb-3" style="color:var(--lum-gold-light);">Nghệ thuật Lưu trú Đẳng cấp</h2>
        <p class="text-light opacity-75 small lh-lg">
          Trải nghiệm hệ thống quản lý & đặt phòng khách sạn chuẩn 5 sao với không gian tinh tế, dịch vụ cá nhân hóa và quy trình vận hành thông minh.
        </p>
      </div>

      <div class="p-3 rounded-3" style="background:rgba(255,255,255,0.06);border:1px solid rgba(201,169,110,0.3);">
        <div class="small fw-semibold mb-2" style="color:var(--lum-gold-light);">
          <i class="fa-solid fa-key me-1"></i>Tài khoản Demo nhanh
        </div>
        <div class="d-grid gap-1 small">
          <button type="button" class="btn btn-sm btn-outline-light text-start border-0" onclick="fillDemo('admin@purplelotushotel.vn','Admin@123')">
            <i class="fa-solid fa-user-shield text-warning me-2"></i>Quản lý: admin@purplelotushotel.vn / Admin@123
          </button>
          <button type="button" class="btn btn-sm btn-outline-light text-start border-0" onclick="fillDemo('staff@purplelotushotel.vn','Staff@123')">
            <i class="fa-solid fa-user-tie text-info me-2"></i>Nhân sự: staff@purplelotushotel.vn / Staff@123
          </button>
          <button type="button" class="btn btn-sm btn-outline-light text-start border-0" onclick="fillDemo('customer@purplelotushotel.vn','Customer@123')">
            <i class="fa-solid fa-user text-success me-2"></i>Khách hàng: customer@purplelotushotel.vn / Customer@123
          </button>
        </div>
      </div>
    </div>

    <!-- Right Form Panel -->
    <div class="col-lg-7 p-4 p-md-5">
      <div class="d-flex justify-content-between align-items-center mb-4">
        <a href="<?= e(base_url('index.php')) ?>" class="text-muted small"><i class="fa-solid fa-arrow-left me-1"></i>Về trang chủ</a>
      </div>

      <h3 class="font-serif fw-bold mb-1" style="color:var(--lum-navy);">Đăng nhập Hệ thống</h3>
      <p class="text-muted small mb-4">Nhập thông tin tài khoản Khách hàng, Nhân sự hoặc Quản lý.</p>

      <?php foreach (get_flashes() as $flash): ?>
        <div class="alert alert-<?= e($flash['type']) ?> py-2 small"><?= e($flash['message']) ?></div>
      <?php endforeach; ?>

      <?php if ($error !== ''): ?>
        <div class="alert alert-danger py-2 small"><i class="fa-solid fa-circle-exclamation me-1"></i><?= e($error) ?></div>
      <?php endif; ?>

      <form method="POST" action="">
        <?= csrf_field() ?>
        <div class="mb-3">
          <label class="form-label fw-semibold small">Email hoặc Tên đăng nhập</label>
          <div class="input-group">
            <span class="input-group-text bg-light"><i class="fa-regular fa-envelope text-muted"></i></span>
            <input type="text" name="identifier" id="loginIdentifier" class="form-control" value="<?= e($identifier) ?>" placeholder="example@purplelotushotel.vn" required autofocus>
          </div>
        </div>

        <div class="mb-4">
          <label class="form-label fw-semibold small">Mật khẩu</label>
          <div class="input-group">
            <span class="input-group-text bg-light"><i class="fa-solid fa-key text-muted"></i></span>
            <input type="password" name="password" id="loginPassword" class="form-control" placeholder="••••••••" required>
          </div>
        </div>

        <button type="submit" class="btn btn-lum-primary w-100 py-2 rounded-pill">
          <i class="fa-solid fa-right-to-bracket me-2"></i>Đăng nhập
        </button>
      </form>

      <div class="text-center mt-4 pt-3 border-top small">
        Chưa có tài khoản khách hàng?
        <a href="<?= e(base_url('dang-ky.php')) ?>" class="fw-semibold" style="color:var(--lum-gold-dark);">Đăng ký ngay</a>
      </div>
    </div>
  </div>
</div>

<script>
function fillDemo(id, pwd) {
  document.getElementById('loginIdentifier').value = id;
  document.getElementById('loginPassword').value = pwd;
}
</script>
<script src="<?= e(base_url('assets/vendor/bootstrap/js/bootstrap.bundle.min.js')) ?>"></script>
</body>
</html>
