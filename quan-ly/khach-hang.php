<?php
/**
 * Admin: Customer Management, Stay History & Payment History
 * Lumina Hotel Management System
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';
require_role(['admin']);

require_once dirname(__DIR__) . '/models/UserModel.php';
require_once dirname(__DIR__) . '/models/BookingModel.php';
require_once dirname(__DIR__) . '/models/PaymentModel.php';

$user = current_user();
$userModel    = new UserModel();
$bookingModel = new BookingModel();
$paymentModel = new PaymentModel();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('quan-ly/khach-hang.php');
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'update_customer') {
            $uid = (int)$_POST['user_id'];
            $userModel->updateCustomerProfile($uid, [
                'full_name'     => trim((string)$_POST['full_name']),
                'phone'         => trim((string)$_POST['phone']),
                'email'         => trim((string)$_POST['email']),
                'id_card'       => trim((string)$_POST['id_card']),
                'date_of_birth' => $_POST['date_of_birth'] ?: null,
                'gender'        => $_POST['gender'] ?: null,
                'address'       => trim((string)$_POST['address']),
                'nationality'   => trim((string)$_POST['nationality']),
                'vip_tier'      => $_POST['vip_tier'] ?? 'standard',
                'notes'         => trim((string)$_POST['notes']),
            ]);
            log_security_event((int)$user['id'], 'Cập nhật thông tin khách hàng user #' . $uid, 'action', 'info');
            set_flash('success', 'Đã cập nhật hồ sơ khách hàng.');
        } elseif ($action === 'toggle_status') {
            $uid = (int)$_POST['user_id'];
            $st  = $_POST['new_status'] === 'locked' ? 'locked' : 'active';
            $userModel->toggleUserStatus($uid, $st);
            set_flash('success', 'Đã cập nhật trạng thái tài khoản khách hàng.');
        }
    } catch (Throwable $e) {
        set_flash('danger', $e->getMessage());
    }
    redirect('quan-ly/khach-hang.php');
}

$search    = trim((string)($_GET['search'] ?? ''));
$vipTier   = trim((string)($_GET['vip_tier'] ?? ''));
$customers = $userModel->getAllCustomers($search, $vipTier);

$inspectId = (int)($_GET['inspect'] ?? 0);
$inspectBookings = $inspectId ? $bookingModel->getBookings(['customer_id' => $inspectId]) : [];
$inspectPayments = $inspectId ? $paymentModel->getPayments(['customer_id' => $inspectId]) : [];
$inspectCustomer = null;
foreach ($customers as $c) {
    if ((int)$c['id'] === $inspectId) {
        $inspectCustomer = $c;
        break;
    }
}

$pageTitle = 'Quản lý Khách hàng';
include dirname(__DIR__) . '/includes/header.php';
?>

<div class="lum-card mb-4">
  <div class="lum-card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
    <form method="GET" class="d-flex flex-wrap gap-2">
      <input type="text" name="search" class="form-control form-control-sm" style="width:250px;" placeholder="Tìm tên, email, SĐT, CCCD..." value="<?= e($search) ?>">
      <select name="vip_tier" class="form-select form-select-sm" style="width:150px;" onchange="this.form.submit()">
        <option value="">Tất cả hạng</option>
        <option value="standard" <?= $vipTier === 'standard' ? 'selected' : '' ?>>Standard</option>
        <option value="silver" <?= $vipTier === 'silver' ? 'selected' : '' ?>>Silver</option>
        <option value="gold" <?= $vipTier === 'gold' ? 'selected' : '' ?>>Gold</option>
        <option value="platinum" <?= $vipTier === 'platinum' ? 'selected' : '' ?>>Platinum</option>
      </select>
      <button class="btn btn-sm btn-lum-primary">Tra cứu</button>
    </form>
    <span class="badge-lux badge-lux-navy"><?= count($customers) ?> khách hàng</span>
  </div>
</div>

<?php if ($inspectCustomer): ?>
  <div class="lum-card mb-4 border-warning">
    <div class="lum-card-header" style="background:var(--lum-cream);">
      <h6 class="mb-0 font-serif fw-bold" style="color:var(--lum-navy);">
        Hồ sơ Chi tiết & Lịch sử: <?= e($inspectCustomer['full_name']) ?>
      </h6>
      <a href="<?= e(base_url('quan-ly/khach-hang.php')) ?>" class="btn btn-sm btn-light border">Đóng chi tiết</a>
    </div>
    <div class="lum-card-body">
      <div class="row g-4">
        <div class="col-lg-6">
          <h6 class="small text-uppercase fw-bold text-muted mb-2">Lịch sử Lưu trú (<?= count($inspectBookings) ?>)</h6>
          <div class="table-responsive">
            <table class="table table-sm lum-table border">
              <thead><tr><th>Mã đơn</th><th>Phòng</th><th>Ngày</th><th>Tổng</th><th>Trạng thái</th></tr></thead>
              <tbody>
                <?php if (empty($inspectBookings)): ?>
                  <tr><td colspan="5" class="text-center text-muted py-3">Chưa có đặt phòng.</td></tr>
                <?php else: ?>
                  <?php foreach ($inspectBookings as $ib): ?>
                    <tr>
                      <td><a href="<?= e(base_url('khach-hang/hoa-don.php?id=' . (int)$ib['id'])) ?>" class="fw-semibold"><?= e($ib['booking_code']) ?></a></td>
                      <td>P.<?= e($ib['room_number']) ?></td>
                      <td class="small"><?= e(format_date($ib['check_in_date'])) ?></td>
                      <td class="fw-semibold"><?= e(format_currency($ib['total_amount'])) ?></td>
                      <td><?= status_badge($ib['status'], 'booking') ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
        <div class="col-lg-6">
          <h6 class="small text-uppercase fw-bold text-muted mb-2">Lịch sử Thanh toán (<?= count($inspectPayments) ?>)</h6>
          <div class="table-responsive">
            <table class="table table-sm lum-table border">
              <thead><tr><th>Mã GD</th><th>Số tiền</th><th>Phương thức</th><th>Trạng thái</th><th>Ngày</th></tr></thead>
              <tbody>
                <?php if (empty($inspectPayments)): ?>
                  <tr><td colspan="5" class="text-center text-muted py-3">Chưa có giao dịch.</td></tr>
                <?php else: ?>
                  <?php foreach ($inspectPayments as $ip): ?>
                    <tr>
                      <td class="fw-semibold"><?= e($ip['payment_code']) ?></td>
                      <td class="fw-bold"><?= e(format_currency($ip['amount'])) ?></td>
                      <td class="small"><?= $ip['payment_method'] === 'cash' ? 'Tiền mặt' : 'Chuyển khoản' ?></td>
                      <td><?= status_badge($ip['status'], 'payment') ?></td>
                      <td class="small"><?= e(format_date($ip['paid_at'] ?: $ip['created_at'])) ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
<?php endif; ?>

<div class="lum-card">
  <div class="table-responsive">
    <table class="table lum-table">
      <thead>
        <tr>
          <th>Khách hàng</th>
          <th>CCCD / Quốc tịch</th>
          <th>Hạng VIP</th>
          <th>Lượt đặt</th>
          <th>Tổng chi tiêu</th>
          <th>Trạng thái</th>
          <th class="text-end">Thao tác</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($customers as $c): ?>
          <tr>
            <td>
              <div class="fw-bold"><?= e($c['full_name']) ?></div>
              <div class="small text-muted"><?= e($c['email']) ?> · <?= e($c['phone']) ?></div>
            </td>
            <td class="small"><?= e($c['id_card'] ?: '—') ?> <div class="text-muted"><?= e($c['nationality']) ?></div></td>
            <td><span class="badge rounded-pill text-uppercase" style="background:rgba(201,169,110,0.25);color:var(--lum-navy);"><?= e($c['vip_tier']) ?></span></td>
            <td><?= (int)$c['total_bookings'] ?> đơn</td>
            <td class="fw-bold text-success"><?= e(format_currency($c['total_spent'])) ?></td>
            <td><?= status_badge($c['user_status'], 'user') ?></td>
            <td class="text-end text-nowrap">
              <a href="<?= e(base_url('quan-ly/khach-hang.php?inspect=' . (int)$c['id'])) ?>" class="btn btn-sm btn-light border" title="Xem lịch sử lưu trú & thanh toán">
                <i class="fa-solid fa-clock-rotate-left"></i> Lịch sử
              </a>
              <button class="btn btn-sm btn-lum-outline" data-bs-toggle="modal" data-bs-target="#editCustModal<?= (int)$c['id'] ?>" title="Chỉnh sửa">
                <i class="fa-solid fa-pen"></i>
              </button>
              <form method="POST" class="d-inline">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="toggle_status">
                <input type="hidden" name="user_id" value="<?= (int)$c['user_id'] ?>">
                <input type="hidden" name="new_status" value="<?= $c['user_status'] === 'active' ? 'locked' : 'active' ?>">
                <button class="btn btn-sm <?= $c['user_status'] === 'active' ? 'btn-outline-warning' : 'btn-outline-success' ?>">
                  <i class="fa-solid <?= $c['user_status'] === 'active' ? 'fa-lock' : 'fa-lock-open' ?>"></i>
                </button>
              </form>
            </td>
          </tr>

          <div class="modal fade" id="editCustModal<?= (int)$c['id'] ?>" tabindex="-1">
            <div class="modal-dialog modal-lg">
              <form method="POST" class="modal-content">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update_customer">
                <input type="hidden" name="user_id" value="<?= (int)$c['user_id'] ?>">
                <div class="modal-header"><h6 class="modal-title font-serif fw-bold">Cập nhật Khách hàng: <?= e($c['full_name']) ?></h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                  <div class="row g-3">
                    <div class="col-md-6"><label class="form-label small fw-semibold">Họ và tên</label><input type="text" name="full_name" class="form-control" value="<?= e($c['full_name']) ?>" required></div>
                    <div class="col-md-6"><label class="form-label small fw-semibold">Email</label><input type="email" name="email" class="form-control" value="<?= e($c['email']) ?>" required></div>
                    <div class="col-md-6"><label class="form-label small fw-semibold">Số điện thoại</label><input type="text" name="phone" class="form-control" value="<?= e($c['phone']) ?>"></div>
                    <div class="col-md-6"><label class="form-label small fw-semibold">CCCD / Hộ chiếu</label><input type="text" name="id_card" class="form-control" value="<?= e($c['id_card']) ?>"></div>
                    <div class="col-md-4">
                      <label class="form-label small fw-semibold">Hạng thành viên VIP</label>
                      <select name="vip_tier" class="form-select">
                        <option value="standard" <?= $c['vip_tier'] === 'standard' ? 'selected' : '' ?>>Standard</option>
                        <option value="silver" <?= $c['vip_tier'] === 'silver' ? 'selected' : '' ?>>Silver</option>
                        <option value="gold" <?= $c['vip_tier'] === 'gold' ? 'selected' : '' ?>>Gold</option>
                        <option value="platinum" <?= $c['vip_tier'] === 'platinum' ? 'selected' : '' ?>>Platinum</option>
                      </select>
                    </div>
                    <div class="col-md-4"><label class="form-label small fw-semibold">Ngày sinh</label><input type="date" name="date_of_birth" class="form-control" value="<?= e($c['date_of_birth']) ?>"></div>
                    <div class="col-md-4">
                      <label class="form-label small fw-semibold">Giới tính</label>
                      <select name="gender" class="form-select">
                        <option value="">—</option>
                        <option value="male" <?= $c['gender'] === 'male' ? 'selected' : '' ?>>Nam</option>
                        <option value="female" <?= $c['gender'] === 'female' ? 'selected' : '' ?>>Nữ</option>
                        <option value="other" <?= $c['gender'] === 'other' ? 'selected' : '' ?>>Khác</option>
                      </select>
                    </div>
                    <div class="col-md-6"><label class="form-label small fw-semibold">Quốc tịch</label><input type="text" name="nationality" class="form-control" value="<?= e($c['nationality']) ?>"></div>
                    <div class="col-md-6"><label class="form-label small fw-semibold">Địa chỉ</label><input type="text" name="address" class="form-control" value="<?= e($c['address']) ?>"></div>
                    <div class="col-12"><label class="form-label small fw-semibold">Ghi chú sở thích</label><textarea name="notes" class="form-control" rows="2"><?= e($c['notes']) ?></textarea></div>
                  </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal">Đóng</button><button type="submit" class="btn btn-sm btn-lum-primary px-4">Lưu thay đổi</button></div>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
