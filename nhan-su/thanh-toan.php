<?php
/**
 * Staff: Payment Confirmation & Front Desk Collection
 * Lumina Hotel Management System
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';
require_role(['staff', 'admin']);

require_once dirname(__DIR__) . '/models/PaymentModel.php';
require_once dirname(__DIR__) . '/models/BookingModel.php';

$user = current_user();
$paymentModel = new PaymentModel();
$bookingModel = new BookingModel();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('nhan-su/thanh-toan.php');
    try {
        $action = $_POST['action'] ?? '';
        if ($action === 'confirm_status') {
            $paymentId = (int)$_POST['payment_id'];
            $newStatus = $_POST['new_status'] ?? 'completed';
            $paymentModel->confirmPayment($paymentId, (int)$user['id'], $newStatus);
            log_security_event((int)$user['id'], 'Xác nhận trạng thái thanh toán #' . $paymentId . ' -> ' . $newStatus, 'action', 'info');
            set_flash('success', 'Đã cập nhật trạng thái giao dịch thanh toán.');
        } elseif ($action === 'record_payment') {
            $bookingId = (int)$_POST['booking_id'];
            $bk = $bookingModel->findBooking($bookingId);
            if (!$bk) {
                throw new RuntimeException('Không tìm thấy đơn đặt phòng.');
            }
            $res = $paymentModel->createPayment([
                'booking_id'      => $bookingId,
                'customer_id'     => (int)$bk['customer_id'],
                'amount'          => (float)$_POST['amount'],
                'payment_method'  => $_POST['payment_method'] ?? 'cash',
                'payment_type'    => $_POST['payment_type'] ?? 'full',
                'transaction_ref' => trim((string)($_POST['transaction_ref'] ?? '')) ?: ('STAFF-' . date('His')),
                'confirmed_by'    => (int)$user['id'],
                'notes'           => trim((string)($_POST['notes'] ?? '')),
            ]);
            log_security_event((int)$user['id'], 'Lễ tân ghi nhận thanh toán ' . $res['payment_code'], 'action', 'info');
            set_flash('success', 'Đã ghi nhận thanh toán ' . $res['payment_code'] . ' thành công.');
        }
    } catch (Throwable $e) {
        set_flash('danger', $e->getMessage());
    }
    redirect('nhan-su/thanh-toan.php');
}

$statusFilter = trim((string)($_GET['status'] ?? ''));
$payments = $paymentModel->getPayments(['status' => $statusFilter]);
$activeBookings = $bookingModel->getBookings([]);

$pageTitle = 'Xác nhận Thanh toán';
include dirname(__DIR__) . '/includes/header.php';
?>

<div class="row g-4">
  <div class="col-lg-4">
    <div class="lum-card">
      <div class="lum-card-header">
        <h6 class="mb-0 font-serif fw-bold" style="color:var(--lum-navy);">Thu tiền trực tiếp tại Quầy</h6>
      </div>
      <div class="lum-card-body">
        <form method="POST">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="record_payment">
          <div class="mb-3">
            <label class="form-label small fw-semibold">Đơn đặt phòng *</label>
            <select name="booking_id" class="form-select" required>
              <?php foreach ($activeBookings as $b): ?>
                <?php if (in_array($b['status'], ['pending', 'confirmed', 'checked_in', 'checked_out'], true)): ?>
                  <option value="<?= (int)$b['id'] ?>">
                    <?= e($b['booking_code']) ?> · <?= e($b['customer_name']) ?> (Còn: <?= e(format_currency(max(0, $b['total_amount'] - $b['paid_amount']))) ?>)
                  </option>
                <?php endif; ?>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Số tiền thu (VNĐ) *</label>
            <input type="number" name="amount" class="form-control" min="10000" step="10000" required>
          </div>
          <div class="row g-2 mb-3">
            <div class="col-6">
              <label class="form-label small fw-semibold">Hình thức</label>
              <select name="payment_method" class="form-select">
                <option value="cash">Tiền mặt</option>
                <option value="bank_transfer">Chuyển khoản</option>
              </select>
            </div>
            <div class="col-6">
              <label class="form-label small fw-semibold">Loại thu</label>
              <select name="payment_type" class="form-select">
                <option value="full">Thanh toán đủ</option>
                <option value="deposit">Đặt cọc</option>
                <option value="room">Tiền phòng</option>
                <option value="service">Tiền dịch vụ</option>
              </select>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Mã tham chiếu / Ghi chú</label>
            <input type="text" name="transaction_ref" class="form-control" placeholder="Số biên lai / Mã GD...">
          </div>
          <button type="submit" class="btn btn-lum-gold w-100 rounded-pill">
            <i class="fa-solid fa-check-double me-1"></i>Xác nhận Đã thu tiền
          </button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-8">
    <div class="lum-card">
      <div class="lum-card-header">
        <h6 class="mb-0 font-serif fw-bold" style="color:var(--lum-navy);">Danh sách Giao dịch</h6>
        <form method="GET">
          <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
            <option value="">Tất cả trạng thái</option>
            <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Chờ xác nhận</option>
            <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : '' ?>>Đã thanh toán</option>
            <option value="failed" <?= $statusFilter === 'failed' ? 'selected' : '' ?>>Thất bại</option>
          </select>
        </form>
      </div>
      <div class="table-responsive">
        <table class="table lum-table">
          <thead>
            <tr>
              <th>Mã GD</th>
              <th>Khách / Đơn</th>
              <th>Số tiền</th>
              <th>Hình thức & Ref</th>
              <th>Trạng thái</th>
              <th class="text-end">Đối soát</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($payments)): ?>
              <tr><td colspan="6" class="text-center py-4 text-muted">Không có giao dịch nào.</td></tr>
            <?php else: ?>
              <?php foreach ($payments as $p): ?>
                <tr>
                  <td class="fw-semibold"><?= e($p['payment_code']) ?></td>
                  <td>
                    <div><?= e($p['customer_name']) ?></div>
                    <div class="small text-muted"><?= e($p['booking_code']) ?> · P.<?= e($p['room_number']) ?></div>
                  </td>
                  <td class="fw-bold"><?= e(format_currency($p['amount'])) ?></td>
                  <td class="small">
                    <?= $p['payment_method'] === 'cash' ? 'Tiền mặt' : 'Chuyển khoản' ?>
                    <div class="text-muted"><?= e($p['transaction_ref'] ?: '—') ?></div>
                  </td>
                  <td><?= status_badge($p['status'], 'payment') ?></td>
                  <td class="text-end text-nowrap">
                    <?php if ($p['status'] === 'pending'): ?>
                      <form method="POST" class="d-inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="confirm_status">
                        <input type="hidden" name="payment_id" value="<?= (int)$p['id'] ?>">
                        <input type="hidden" name="new_status" value="completed">
                        <button class="btn btn-sm btn-success" title="Xác nhận đã nhận tiền"><i class="fa-solid fa-check"></i> Duyệt</button>
                      </form>
                      <form method="POST" class="d-inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="confirm_status">
                        <input type="hidden" name="payment_id" value="<?= (int)$p['id'] ?>">
                        <input type="hidden" name="new_status" value="failed">
                        <button class="btn btn-sm btn-outline-danger" title="Từ chối"><i class="fa-solid fa-xmark"></i></button>
                      </form>
                    <?php else: ?>
                      <span class="small text-muted"><?= e($p['confirmed_by_name'] ?: '—') ?></span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
