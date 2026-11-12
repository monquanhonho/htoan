<?php
/**
 * Staff: Booking Lookup & Processing
 * Lumina Hotel Management System
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';
require_role(['staff', 'admin']);

require_once dirname(__DIR__) . '/models/BookingModel.php';
require_once dirname(__DIR__) . '/controllers/BookingController.php';

$user = current_user();
$bookingModel = new BookingModel();
$bookingCtrl  = new BookingController();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('nhan-su/dat-phong.php');
    try {
        $action = $_POST['action'] ?? '';
        $id     = (int)($_POST['booking_id'] ?? 0);
        if ($action === 'confirm') {
            $bookingCtrl->staffConfirm($id, (int)$user['id']);
            set_flash('success', 'Đã xác nhận đơn đặt phòng.');
        } elseif ($action === 'reject') {
            $bookingCtrl->staffReject($id, (int)$user['id'], trim((string)($_POST['reason'] ?? '')));
            set_flash('warning', 'Đã từ chối đơn đặt phòng.');
        }
    } catch (Throwable $e) {
        set_flash('danger', $e->getMessage());
    }
    redirect('nhan-su/dat-phong.php');
}

$search = trim((string)($_GET['search'] ?? ''));
$status = trim((string)($_GET['status'] ?? ''));
$bookings = $bookingModel->getBookings(['search' => $search, 'status' => $status]);

$pageTitle = 'Tra cứu Đặt phòng';
include dirname(__DIR__) . '/includes/header.php';
?>

<div class="lum-card mb-4">
  <div class="lum-card-body">
    <form method="GET" class="row g-2 align-items-center">
      <div class="col-md-5">
        <input type="text" name="search" class="form-control" placeholder="Tìm theo mã đơn, tên khách, SĐT, số phòng..." value="<?= e($search) ?>">
      </div>
      <div class="col-md-3">
        <select name="status" class="form-select">
          <option value="">Tất cả trạng thái</option>
          <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Chờ xác nhận</option>
          <option value="confirmed" <?= $status === 'confirmed' ? 'selected' : '' ?>>Đã xác nhận</option>
          <option value="checked_in" <?= $status === 'checked_in' ? 'selected' : '' ?>>Đang lưu trú</option>
          <option value="checked_out" <?= $status === 'checked_out' ? 'selected' : '' ?>>Đã trả phòng</option>
          <option value="cancelled" <?= $status === 'cancelled' ? 'selected' : '' ?>>Đã hủy</option>
          <option value="rejected" <?= $status === 'rejected' ? 'selected' : '' ?>>Từ chối</option>
        </select>
      </div>
      <div class="col-md-2 d-grid">
        <button type="submit" class="btn btn-lum-primary"><i class="fa-solid fa-magnifying-glass me-1"></i>Tra cứu</button>
      </div>
      <div class="col-md-2 d-grid">
        <a href="<?= e(base_url('nhan-su/dat-phong.php')) ?>" class="btn btn-light border">Đặt lại</a>
      </div>
    </form>
  </div>
</div>

<div class="lum-card">
  <div class="table-responsive">
    <table class="table lum-table">
      <thead>
        <tr>
          <th>Mã đơn</th>
          <th>Khách hàng</th>
          <th>Phòng</th>
          <th>Nhận - Trả</th>
          <th>Tổng hóa đơn</th>
          <th>Trạng thái</th>
          <th class="text-end">Thao tác</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($bookings)): ?>
          <tr><td colspan="7" class="text-center py-4 text-muted">Không tìm thấy đơn đặt phòng phù hợp.</td></tr>
        <?php else: ?>
          <?php foreach ($bookings as $b): ?>
            <tr>
              <td class="fw-bold"><?= e($b['booking_code']) ?></td>
              <td><?= e($b['customer_name']) ?><div class="small text-muted"><?= e($b['customer_phone']) ?></div></td>
              <td>Phòng <strong><?= e($b['room_number']) ?></strong> <div class="small text-muted"><?= e($b['room_type_name']) ?></div></td>
              <td class="small"><?= e(format_date($b['check_in_date'])) ?> → <?= e(format_date($b['check_out_date'])) ?> (<?= (int)$b['nights'] ?>đ)</td>
              <td>
                <div class="fw-bold"><?= e(format_currency($b['total_amount'])) ?></div>
                <div class="small text-success">Đã trả: <?= e(format_currency($b['paid_amount'])) ?></div>
              </td>
              <td><?= status_badge($b['status'], 'booking') ?></td>
              <td class="text-end text-nowrap">
                <a href="<?= e(base_url('khach-hang/hoa-don.php?id=' . (int)$b['id'])) ?>" class="btn btn-sm btn-light border" title="Hóa đơn">
                  <i class="fa-solid fa-file-invoice"></i>
                </a>
                <?php if ($b['status'] === 'pending'): ?>
                  <form method="POST" class="d-inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="confirm">
                    <input type="hidden" name="booking_id" value="<?= (int)$b['id'] ?>">
                    <button class="btn btn-sm btn-success" title="Xác nhận"><i class="fa-solid fa-check"></i></button>
                  </form>
                  <form method="POST" class="d-inline" onsubmit="return confirm('Từ chối đơn đặt phòng này?')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="reject">
                    <input type="hidden" name="booking_id" value="<?= (int)$b['id'] ?>">
                    <button class="btn btn-sm btn-outline-danger" title="Từ chối"><i class="fa-solid fa-xmark"></i></button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
