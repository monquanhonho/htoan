<?php
/**
 * Customer: Profile & Change Password
 * Lumina Hotel Management System
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';
require_role(['customer']);

require_once dirname(__DIR__) . '/models/UserModel.php';

$userModel = new UserModel();
$userId = (int)current_user_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('khach-hang/ho-so.php');
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'update_profile') {
            $email = trim((string)($_POST['email'] ?? ''));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Địa chỉ email không hợp lệ.');
            }
            $currentProfile = $userModel->findById($userId);
            if ($userModel->existsUsernameOrEmail($currentProfile['username'], $email, $userId)) {
                throw new RuntimeException('Email này đã được tài khoản khác sử dụng.');
            }

            $userModel->updateCustomerProfile($userId, [
                'full_name'     => trim((string)$_POST['full_name']),
                'phone'         => trim((string)$_POST['phone']),
                'email'         => $email,
                'id_card'       => trim((string)$_POST['id_card']),
                'date_of_birth' => trim((string)$_POST['date_of_birth']),
                'gender'        => trim((string)$_POST['gender']),
                'address'       => trim((string)$_POST['address']),
                'nationality'   => trim((string)$_POST['nationality']),
            ]);
            sync_user_session();
            log_security_event($userId, 'Cập nhật thông tin hồ sơ cá nhân', 'action', 'info');
            set_flash('success', 'Đã cập nhật thông tin cá nhân thành công.');
        } elseif ($action === 'change_password') {
            $curr = (string)($_POST['current_password'] ?? '');
            $new  = (string)($_POST['new_password'] ?? '');
            $conf = (string)($_POST['confirm_password'] ?? '');

            if (strlen($new) < 6) {
                throw new RuntimeException('Mật khẩu mới phải có ít nhất 6 ký tự.');
            }
            if ($new !== $conf) {
                throw new RuntimeException('Mật khẩu xác nhận không khớp.');
            }
            if (!$userModel->changePassword($userId, $curr, $new)) {
                throw new RuntimeException('Mật khẩu hiện tại không chính xác.');
            }
            log_security_event($userId, 'Đổi mật khẩu tài khoản', 'action', 'info');
            set_flash('success', 'Đổi mật khẩu thành công.');
        }
    } catch (Throwable $e) {
        set_flash('danger', $e->getMessage());
    }
    redirect('khach-hang/ho-so.php');
}

$profile = $userModel->findById($userId);

$pageTitle = 'Hồ sơ Cá nhân & Bảo mật';
include dirname(__DIR__) . '/includes/header.php';
?>

<div class="row g-4">
  <div class="col-lg-7">
    <div class="lum-card">
      <div class="lum-card-header">
        <h6 class="mb-0 font-serif fw-bold" style="color:var(--lum-navy);">Thông tin Cá nhân</h6>
        <span class="badge-lux badge-lux-navy">Hạng: <?= strtoupper(e($profile['vip_tier'] ?? 'standard')) ?></span>
      </div>
      <div class="lum-card-body">
        <form method="POST">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="update_profile">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Họ và tên *</label>
              <input type="text" name="full_name" class="form-control" value="<?= e($profile['full_name']) ?>" required>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Tên đăng nhập</label>
              <input type="text" class="form-control bg-light" value="<?= e($profile['username']) ?>" disabled>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Email *</label>
              <input type="email" name="email" class="form-control" value="<?= e($profile['email']) ?>" required>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Số điện thoại</label>
              <input type="text" name="phone" class="form-control" value="<?= e($profile['phone']) ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold">CCCD / Hộ chiếu</label>
              <input type="text" name="id_card" class="form-control" value="<?= e($profile['id_card']) ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Ngày sinh</label>
              <input type="date" name="date_of_birth" class="form-control" value="<?= e($profile['date_of_birth']) ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Giới tính</label>
              <select name="gender" class="form-select">
                <option value="">Chọn...</option>
                <option value="male" <?= $profile['gender'] === 'male' ? 'selected' : '' ?>>Nam</option>
                <option value="female" <?= $profile['gender'] === 'female' ? 'selected' : '' ?>>Nữ</option>
                <option value="other" <?= $profile['gender'] === 'other' ? 'selected' : '' ?>>Khác</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Quốc tịch</label>
              <input type="text" name="nationality" class="form-control" value="<?= e($profile['nationality'] ?: 'Việt Nam') ?>">
            </div>
            <div class="col-12">
              <label class="form-label small fw-semibold">Địa chỉ</label>
              <input type="text" name="address" class="form-control" value="<?= e($profile['address']) ?>">
            </div>
          </div>
          <div class="mt-4">
            <button type="submit" class="btn btn-lum-primary rounded-pill px-4">
              <i class="fa-solid fa-floppy-disk me-2"></i>Lưu thay đổi
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="lum-card">
      <div class="lum-card-header">
        <h6 class="mb-0 font-serif fw-bold" style="color:var(--lum-navy);">Đổi Mật khẩu</h6>
      </div>
      <div class="lum-card-body">
        <form method="POST">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="change_password">
          <div class="mb-3">
            <label class="form-label small fw-semibold">Mật khẩu hiện tại *</label>
            <input type="password" name="current_password" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Mật khẩu mới * (ít nhất 6 ký tự)</label>
            <input type="password" name="new_password" class="form-control" minlength="6" required>
          </div>
          <div class="mb-4">
            <label class="form-label small fw-semibold">Xác nhận mật khẩu mới *</label>
            <input type="password" name="confirm_password" class="form-control" minlength="6" required>
          </div>
          <button type="submit" class="btn btn-lum-gold w-100 rounded-pill">
            <i class="fa-solid fa-key me-2"></i>Cập nhật Mật khẩu
          </button>
        </form>
      </div>
    </div>
  </div>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
