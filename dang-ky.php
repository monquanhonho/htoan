<?php
/**
 * Customer Registration Page
 * Lumina Hotel Management System
 */

declare(strict_types=1);

require_once __DIR__ . '/controllers/AuthController.php';

if (is_logged_in()) {
    redirect(role_dashboard_path((string)current_role()));
}

$error = '';
$form = [
    'full_name' => '',
    'username'  => '',
    'email'     => '',
    'phone'     => '',
    'id_card'   => '',
    'address'   => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('dang-ky.php');
    foreach ($form as $k => $_) {
        $form[$k] = trim((string)($_POST[$k] ?? ''));
    }

    $auth = new AuthController();
    $res = $auth->handleRegister($_POST);
    if ($res['ok']) {
        set_flash('success', $res['message']);
        redirect('dang-nhap.php');
    }
    $error = $res['message'];
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Đăng ký tài khoản | <?= e(APP_NAME) ?></title>
  <link href="<?= e(base_url('assets/vendor/bootstrap/css/bootstrap.min.css')) ?>" rel="stylesheet">
  <link href="<?= e(base_url('assets/vendor/fontawesome/css/all.min.css')) ?>" rel="stylesheet">
  <link href="<?= e(base_url('assets/css/style.css?v=' . (@filemtime(ROOT_PATH . '/assets/css/style.css') ?: 1))) ?>" rel="stylesheet">
</head>
<body style="background:var(--lum-cream);">
<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-lg-7">
      <div class="lum-card overflow-hidden">
        <div class="p-4 text-white lum-hero d-flex justify-content-between align-items-center">
          <div class="d-flex align-items-center gap-3">
            <img src="<?= e(base_url(APP_LOGO)) ?>" alt="Purple Lotus Hotel" class="lum-brand-logo">
            <div>
              <div class="small text-uppercase" style="letter-spacing:0.16em;color:var(--lum-gold-light);">Purple Lotus Membership</div>
              <h3 class="font-serif mb-0">Đăng ký Tài khoản Khách hàng</h3>
            </div>
          </div>
          <a href="<?= e(base_url('index.php')) ?>" class="btn btn-sm btn-outline-light rounded-pill px-3">Trang chủ</a>
        </div>

        <div class="p-4 p-md-5 bg-white">
          <?php if ($error !== ''): ?>
            <div class="alert alert-danger py-2 small"><i class="fa-solid fa-triangle-exclamation me-1"></i><?= e($error) ?></div>
          <?php endif; ?>

          <form method="POST" action="">
            <?= csrf_field() ?>
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label small fw-semibold">Họ và tên *</label>
                <input type="text" name="full_name" class="form-control" value="<?= e($form['full_name']) ?>" required>
              </div>
              <div class="col-md-6">
                <label class="form-label small fw-semibold">Tên đăng nhập *</label>
                <input type="text" name="username" class="form-control" value="<?= e($form['username']) ?>" placeholder="vd: nguyenvanan" required>
              </div>
              <div class="col-md-6">
                <label class="form-label small fw-semibold">Email *</label>
                <input type="email" name="email" class="form-control" value="<?= e($form['email']) ?>" required>
              </div>
              <div class="col-md-6">
                <label class="form-label small fw-semibold">Số điện thoại</label>
                <input type="text" name="phone" class="form-control" value="<?= e($form['phone']) ?>" placeholder="09xxxxxxxx">
              </div>
              <div class="col-md-6">
                <label class="form-label small fw-semibold">Số CCCD / Hộ chiếu</label>
                <input type="text" name="id_card" class="form-control" value="<?= e($form['id_card']) ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label small fw-semibold">Địa chỉ</label>
                <input type="text" name="address" class="form-control" value="<?= e($form['address']) ?>">
              </div>
              <div class="col-md-6">
                <label class="form-label small fw-semibold">Mật khẩu * (tối thiểu 6 ký tự)</label>
                <input type="password" name="password" class="form-control" minlength="6" required>
              </div>
              <div class="col-md-6">
                <label class="form-label small fw-semibold">Xác nhận mật khẩu *</label>
                <input type="password" name="confirm_password" class="form-control" minlength="6" required>
              </div>
            </div>

            <div class="mt-4 d-grid">
              <button type="submit" class="btn btn-lum-gold py-2 rounded-pill">
                <i class="fa-solid fa-user-plus me-2"></i>Hoàn tất Đăng ký
              </button>
            </div>
          </form>

          <div class="text-center mt-4 small">
            Đã có tài khoản? <a href="<?= e(base_url('dang-nhap.php')) ?>" class="fw-semibold" style="color:var(--lum-navy);">Đăng nhập ngay</a>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
</body>
</html>
