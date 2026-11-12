<?php
/**
 * Customer: My Bookings & Cancellation
 * Lumina Hotel Management System
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';
require_role(['customer']);

require_once dirname(__DIR__) . '/models/BookingModel.php';
require_once dirname(__DIR__) . '/controllers/BookingController.php';

$user = current_user();
$customerId = (int)$user['customer_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel') {
    require_csrf('khach-hang/dat-phong.php');
    try {
        $ctrl = new BookingController();
        $ctrl->customerCancel(
            (int)$_POST['booking_id'],
            $customerId,
            (int)$user['id'],
            trim((string)($_POST['reason'] ?? ''))
        );
        set_flash('success', 'Đã hủy đặt phòng thành công.');
    } catch (Throwable $e) {
        set_flash('danger', $e->getMessage());
    }
    redirect('khach-hang/dat-phong.php');
}

$bookingModel = new BookingModel();
$statusFilter = trim((string)($_GET['status'] ?? ''));
$bookings = $bookingModel->getBookings([
    'customer_id' => $customerId,
    'status'      => $statusFilter,
]);

$pageTitle = 'Đặt phòng của tôi';
include dirname(__DIR__) . '/includes/header.php';
?>

<div class="lum-card mb-4">
  <div class="lum-card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
    <form method="GET" class="d-flex gap-2 align-items-center">
      <select name="status" class="form-select form-select-sm" style="width:200px;" onchange="this.form.submit()">
        <option value="">Tất cả trạng thái</option>
        <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Chờ xác nhận</option>
        <option value="confirmed" <?= $statusFilter === 'confirmed' ? 'selected' : '' ?>>Đã xác nhận</option>
        <option value="checked_in" <?= $statusFilter === 'checked_in' ? 'selected' : '' ?>>Đang lưu trú</option>
        <option value="checked_out" <?= $statusFilter === 'checked_out' ? 'selected' : '' ?>>Đã trả phòng</option>
        <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>Đã hủy</option>
      </select>
    </form>
    <a href="<?= e(base_url('khach-hang/danh-sach-phong.php')) ?>" class="btn btn-sm btn-lum-gold rounded-pill px-3">
      <i class="fa-solid fa-plus me-1"></i>Đặt thêm phòng
    </a>
  </div>
</div>

<div class="lum-card">
  <div class="table-responsive">
    <table class="table lum-table">
      <thead>
        <tr>
          <th>Mã đặt phòng</th>
          <th>Phòng & Hạng phòng</th>
          <th>Thời gian lưu trú</th>
          <th>Tiền phòng</th>
          <th>Dịch vụ</th>
          <th>Tổng hóa đơn</th>
          <th>Đã thanh toán</th>
          <th>Trạng thái</th>
          <th class="text-end">Thao tác</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($bookings)): ?>
          <tr><td colspan="9" class="text-center py-4 text-muted">Không có đơn đặt phòng nào.</td></tr>
        <?php else: ?>
          <?php foreach ($bookings as $b): ?>
            <?php
              $remaining = max(0, (float)$b['total_amount'] - (float)$b['paid_amount']);
              $canCancel = $b['status'] === 'pending' || ($b['status'] === 'confirmed' && $b['check_in_date'] > date('Y-m-d'));
            ?>
            <tr>
              <td class="fw-bold" style="color:var(--lum-navy);"><?= e($b['booking_code']) ?></td>
              <td>
                <div class="fw-semibold">Phòng <?= e($b['room_number']) ?></div>
                <div class="small text-muted"><?= e($b['room_type_name']) ?></div>
              </td>
              <td class="small">
                <?= e(format_date($b['check_in_date'])) ?> → <?= e(format_date($b['check_out_date'])) ?>
                <div class="text-muted"><?= (int)$b['nights'] ?> đêm · <?= (int)$b['num_guests'] ?> khách</div>
              </td>
              <td><?= e(format_currency($b['total_room_amount'])) ?></td>
              <td><?= e(format_currency($b['total_service_amount'])) ?></td>
              <td class="fw-bold"><?= e(format_currency($b['total_amount'])) ?></td>
              <td class="text-success fw-semibold"><?= e(format_currency($b['paid_amount'])) ?></td>
              <td><?= status_badge($b['status'], 'booking') ?></td>
              <td class="text-end text-nowrap">
                <a href="<?= e(base_url('khach-hang/hoa-don.php?id=' . (int)$b['id'])) ?>" class="btn btn-sm btn-light border" title="Xem hóa đơn">
                  <i class="fa-solid fa-file-invoice"></i>
                </a>
                <?php if ($remaining > 0 && in_array($b['status'], ['pending', 'confirmed', 'checked_in'], true)): ?>
                  <a href="<?= e(base_url('khach-hang/thanh-toan.php?booking_id=' . (int)$b['id'])) ?>" class="btn btn-sm btn-lum-gold" title="Thanh toán">
                    <i class="fa-solid fa-credit-card"></i>
                  </a>
                <?php endif; ?>
                <?php if ($canCancel): ?>
                  <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#cancelModal<?= (int)$b['id'] ?>" title="Hủy đặt phòng">
                    <i class="fa-solid fa-xmark"></i>
                  </button>
                <?php endif; ?>
              </td>
            </tr>

            <?php if ($canCancel): ?>
              <div class="modal fade" id="cancelModal<?= (int)$b['id'] ?>" tabindex="-1">
                <div class="modal-dialog">
                  <form method="POST" class="modal-content">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="cancel">
                    <input type="hidden" name="booking_id" value="<?= (int)$b['id'] ?>">
                    <div class="modal-header">
                      <h6 class="modal-title font-serif fw-bold">Hủy đơn <?= e($b['booking_code']) ?></h6>
                      <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                      <p class="small text-muted">Bạn có chắc chắn muốn hủy đặt phòng <strong><?= e($b['room_number']) ?></strong> (<?= e(format_date($b['check_in_date'])) ?> - <?= e(format_date($b['check_out_date'])) ?>)?</p>
                      <label class="form-label small fw-semibold">Lý do hủy</label>
                      <input type="text" name="reason" class="form-control" placeholder="Thay đổi lịch trình...">
                    </div>
                    <div class="modal-footer">
                      <button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal">Đóng</button>
                      <button type="submit" class="btn btn-sm btn-danger">Xác nhận Hủy</button>
                    </div>
                  </form>
                </div>
              </div>
            <?php endif; ?>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
