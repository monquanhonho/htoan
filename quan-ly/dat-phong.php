<?php
/**
 * Admin: All Bookings Management
 * Lumina Hotel Management System
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';
require_role(['admin']);

require_once dirname(__DIR__) . '/models/BookingModel.php';
require_once dirname(__DIR__) . '/controllers/BookingController.php';

$user = current_user();
$bookingModel = new BookingModel();
$bookingCtrl  = new BookingController();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('quan-ly/dat-phong.php');
    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['booking_id'] ?? 0);
    try {
        if ($action === 'confirm') {
            $bookingCtrl->staffConfirm($id, (int)$user['id']);
            set_flash('success', 'Đã xác nhận đơn đặt phòng.');
        } elseif ($action === 'reject') {
            $bookingCtrl->staffReject($id, (int)$user['id'], trim((string)($_POST['reason'] ?? '')));
            set_flash('warning', 'Đã từ chối đơn đặt phòng.');
        } elseif ($action === 'cancel') {
            $bookingModel->cancelOrRejectBooking($id, 'cancelled', trim((string)($_POST['reason'] ?? 'Quản lý hủy đơn')));
            log_security_event((int)$user['id'], 'Quản lý hủy đặt phòng #' . $id, 'action', 'warning');
            set_flash('info', 'Đã hủy đơn đặt phòng.');
        }
    } catch (Throwable $e) {
        set_flash('danger', $e->getMessage());
    }
    redirect('quan-ly/dat-phong.php');
}

$filters = [
    'search'    => trim((string)($_GET['search'] ?? '')),
    'status'    => trim((string)($_GET['status'] ?? '')),
    'date_from' => trim((string)($_GET['date_from'] ?? '')),
    'date_to'   => trim((string)($_GET['date_to'] ?? '')),
];
$bookings = $bookingModel->getBookings($filters);

$pageTitle = 'Quản lý Đặt phòng Toàn hệ thống';
include dirname(__DIR__) . '/includes/header.php';
?>

<div class="lum-card mb-4">
  <div class="lum-card-body">
    <form method="GET" class="row g-2 align-items-end">
      <div class="col-md-4">
        <label class="form-label small fw-semibold">Tìm kiếm</label>
        <input type="text" name="search" class="form-control form-control-sm" placeholder="Mã đơn, tên khách, SĐT, số phòng..." value="<?= e($filters['search']) ?>">
      </div>
      <div class="col-md-2">
        <label class="form-label small fw-semibold">Trạng thái</label>
        <select name="status" class="form-select form-select-sm">
          <option value="">Tất cả</option>
          <option value="pending" <?= $filters['status'] === 'pending' ? 'selected' : '' ?>>Chờ xác nhận</option>
          <option value="confirmed" <?= $filters['status'] === 'confirmed' ? 'selected' : '' ?>>Đã xác nhận</option>
          <option value="checked_in" <?= $filters['status'] === 'checked_in' ? 'selected' : '' ?>>Đang lưu trú</option>
          <option value="checked_out" <?= $filters['status'] === 'checked_out' ? 'selected' : '' ?>>Đã trả phòng</option>
          <option value="cancelled" <?= $filters['status'] === 'cancelled' ? 'selected' : '' ?>>Đã hủy</option>
          <option value="rejected" <?= $filters['status'] === 'rejected' ? 'selected' : '' ?>>Từ chối</option>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label small fw-semibold">Từ ngày</label>
        <input type="date" name="date_from" class="form-control form-control-sm" value="<?= e($filters['date_from']) ?>">
      </div>
      <div class="col-md-2">
        <label class="form-label small fw-semibold">Đến ngày</label>
        <input type="date" name="date_to" class="form-control form-control-sm" value="<?= e($filters['date_to']) ?>">
      </div>
      <div class="col-md-2 d-flex gap-1">
        <button class="btn btn-sm btn-lum-primary w-100">Lọc</button>
        <a href="<?= e(base_url('quan-ly/dat-phong.php')) ?>" class="btn btn-sm btn-light border">Xóa</a>
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
          <th>Thanh toán</th>
          <th>Trạng thái</th>
          <th class="text-end">Thao tác</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($bookings)): ?>
          <tr><td colspan="8" class="text-center py-4 text-muted">Không có đơn đặt phòng nào.</td></tr>
        <?php else: ?>
          <?php foreach ($bookings as $b): ?>
            <tr>
              <td class="fw-bold"><?= e($b['booking_code']) ?></td>
              <td><?= e($b['customer_name']) ?><div class="small text-muted"><?= e($b['customer_phone']) ?></div></td>
              <td>Phòng <strong><?= e($b['room_number']) ?></strong> <div class="small text-muted"><?= e($b['room_type_name']) ?></div></td>
              <td class="small"><?= e(format_date($b['check_in_date'])) ?> → <?= e(format_date($b['check_out_date'])) ?> (<?= (int)$b['nights'] ?>đ)</td>
              <td class="fw-bold"><?= e(format_currency($b['total_amount'])) ?></td>
              <td class="small text-success fw-semibold"><?= e(format_currency($b['paid_amount'])) ?></td>
              <td><?= status_badge($b['status'], 'booking') ?></td>
              <td class="text-end text-nowrap">
                <a href="<?= e(base_url('khach-hang/hoa-don.php?id=' . (int)$b['id'])) ?>" class="btn btn-sm btn-light border" title="Chi tiết hóa đơn">
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
                    <button class="btn btn-sm btn-outline-danger" title="Từ chối"><i class="fa-solid fa-ban"></i></button>
                  </form>
                <?php elseif ($b['status'] === 'confirmed'): ?>
                  <form method="POST" class="d-inline" onsubmit="return confirm('Hủy đơn đã xác nhận này?')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="cancel">
                    <input type="hidden" name="booking_id" value="<?= (int)$b['id'] ?>">
                    <button class="btn btn-sm btn-outline-secondary" title="Hủy đơn"><i class="fa-solid fa-xmark"></i></button>
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
