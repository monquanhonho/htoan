<?php
/**
 * Customer: Payments & History
 * Lumina Hotel Management System
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';
require_role(['customer']);

require_once dirname(__DIR__) . '/models/BookingModel.php';
require_once dirname(__DIR__) . '/models/PaymentModel.php';

$user = current_user();
$customerId = (int)$user['customer_id'];

$bookingModel = new BookingModel();
$paymentModel = new PaymentModel();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('khach-hang/thanh-toan.php');
    try {
        $bookingId = (int)($_POST['booking_id'] ?? 0);
        $booking = $bookingModel->findBooking($bookingId);
        if (!$booking || (int)$booking['customer_id'] !== $customerId) {
            throw new RuntimeException('Đơn đặt phòng không hợp lệ.');
        }

        $method = $_POST['payment_method'] ?? 'bank_transfer';
        $amount = (float)($_POST['amount'] ?? 0);
        $ref    = trim((string)($_POST['transaction_ref'] ?? ''));

        if ($method === 'bank_transfer' && $ref === '') {
            throw new RuntimeException('Vui lòng nhập mã giao dịch chuyển khoản ngân hàng để lễ tân đối soát.');
        }

        $res = $paymentModel->createPayment([
            'booking_id'      => $bookingId,
            'customer_id'     => $customerId,
            'amount'          => $amount,
            'payment_method'  => $method,
            'payment_type'    => $_POST['payment_type'] ?? 'full',
            'transaction_ref' => $ref ?: ('CASH-REQ-' . date('His')),
            'notes'           => trim((string)($_POST['notes'] ?? '')),
        ]);

        log_security_event(
            (int)$user['id'],
            'Khách gửi yêu cầu thanh toán ' . $res['payment_code'],
            'action',
            'info',
            'Số tiền: ' . format_currency($amount) . ' | Phương thức: ' . $method
        );

        set_flash('success', 'Đã ghi nhận yêu cầu thanh toán ' . $res['payment_code'] . '. Nhân viên lễ tân sẽ xác nhận ngay khi đối soát.');
    } catch (Throwable $e) {
        set_flash('danger', $e->getMessage());
    }
    redirect('khach-hang/thanh-toan.php');
}

$allBookings = $bookingModel->getBookings(['customer_id' => $customerId]);
$payableBookings = array_filter($allBookings, function ($b) {
    $rem = (float)$b['total_amount'] - (float)$b['paid_amount'];
    return $rem > 0 && in_array($b['status'], ['pending', 'confirmed', 'checked_in', 'checked_out'], true);
});
$payments = $paymentModel->getPayments(['customer_id' => $customerId]);
$selectedBookingId = (int)($_GET['booking_id'] ?? 0);

$pageTitle = 'Thanh toán & Lịch sử';
include dirname(__DIR__) . '/includes/header.php';
?>

<div class="row g-4">
  <div class="col-lg-5">
    <div class="lum-card">
      <div class="lum-card-header">
        <h6 class="mb-0 font-serif fw-bold" style="color:var(--lum-navy);">Thanh toán Tiền phòng & Dịch vụ</h6>
      </div>
      <div class="lum-card-body">
        <?php if (empty($payableBookings)): ?>
          <div class="text-center py-4 text-muted small">
            <i class="fa-solid fa-circle-check text-success fa-2x mb-2"></i>
            <div>Tất cả các đơn đặt phòng của bạn đã được thanh toán đầy đủ.</div>
          </div>
        <?php else: ?>
          <form method="POST" action="">
            <?= csrf_field() ?>
            <div class="mb-3">
              <label class="form-label small fw-semibold">Chọn đơn đặt phòng *</label>
              <select name="booking_id" id="payBookingSelect" class="form-select" required onchange="updatePayAmount()">
                <?php foreach ($payableBookings as $b): ?>
                  <?php $rem = max(0, (float)$b['total_amount'] - (float)$b['paid_amount']); ?>
                  <option value="<?= (int)$b['id'] ?>" data-rem="<?= (int)$rem ?>" <?= $selectedBookingId === (int)$b['id'] ? 'selected' : '' ?>>
                    <?= e($b['booking_code']) ?> · Phòng <?= e($b['room_number']) ?> (Còn: <?= e(format_currency($rem)) ?>)
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="mb-3">
              <label class="form-label small fw-semibold">Số tiền thanh toán (VNĐ) *</label>
              <input type="number" name="amount" id="payAmountInput" class="form-control" min="10000" step="10000" required>
            </div>

            <div class="mb-3">
              <label class="form-label small fw-semibold">Hình thức thanh toán *</label>
              <div class="row g-2">
                <div class="col-6">
                  <div class="form-check border rounded-3 p-2 ps-4">
                    <input class="form-check-input" type="radio" name="payment_method" id="pmBank" value="bank_transfer" checked onchange="toggleBankBox()">
                    <label class="form-check-label small fw-semibold" for="pmBank">Chuyển khoản</label>
                  </div>
                </div>
                <div class="col-6">
                  <div class="form-check border rounded-3 p-2 ps-4">
                    <input class="form-check-input" type="radio" name="payment_method" id="pmCash" value="cash" onchange="toggleBankBox()">
                    <label class="form-check-label small fw-semibold" for="pmCash">Tiền mặt tại KS</label>
                  </div>
                </div>
              </div>
            </div>

            <div id="bankInfoBox" class="p-3 rounded-3 mb-3 small" style="background:var(--lum-cream);border:1px solid var(--lum-border);">
              <div class="fw-bold mb-1" style="color:var(--lum-navy);"><i class="fa-solid fa-building-columns me-1 text-warning"></i>Thông tin chuyển khoản Purple Lotus Hotel</div>
              <div>Ngân hàng: <strong>Vietcombank (CN Sài Gòn)</strong></div>
              <div>Số tài khoản: <strong>0071 000 888 999</strong></div>
              <div>Chủ tài khoản: <strong>CONG TY KHACH SAN PURPLE LOTUS</strong></div>
              <div class="mt-2">
                <label class="form-label small fw-semibold mb-1">Mã giao dịch chuyển khoản *</label>
                <input type="text" name="transaction_ref" id="txRefInput" class="form-control form-control-sm" placeholder="VD: VCB-12345678">
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label small fw-semibold">Ghi chú</label>
              <input type="text" name="notes" class="form-control" placeholder="Thanh toán tiền phòng / đặt cọc...">
            </div>

            <button type="submit" class="btn btn-lum-gold w-100 rounded-pill py-2">
              <i class="fa-solid fa-paper-plane me-2"></i>Gửi Xác nhận Thanh toán
            </button>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="lum-card">
      <div class="lum-card-header">
        <h6 class="mb-0 font-serif fw-bold" style="color:var(--lum-navy);">Lịch sử Thanh toán</h6>
      </div>
      <div class="table-responsive">
        <table class="table lum-table">
          <thead>
            <tr>
              <th>Mã GD</th>
              <th>Đơn đặt phòng</th>
              <th>Phương thức</th>
              <th>Số tiền</th>
              <th>Trạng thái</th>
              <th>Thời gian</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($payments)): ?>
              <tr><td colspan="6" class="text-center py-4 text-muted">Chưa có giao dịch thanh toán nào.</td></tr>
            <?php else: ?>
              <?php foreach ($payments as $p): ?>
                <tr>
                  <td class="fw-semibold"><?= e($p['payment_code']) ?></td>
                  <td>
                    <a href="<?= e(base_url('khach-hang/hoa-don.php?id=' . (int)$p['booking_id'])) ?>" class="fw-semibold text-decoration-underline" style="color:var(--lum-navy);">
                      <?= e($p['booking_code']) ?>
                    </a>
                    <div class="small text-muted">Phòng <?= e($p['room_number']) ?></div>
                  </td>
                  <td class="small">
                    <?= $p['payment_method'] === 'cash' ? '<i class="fa-solid fa-money-bill-wave text-success me-1"></i>Tiền mặt' : '<i class="fa-solid fa-building-columns text-primary me-1"></i>Chuyển khoản' ?>
                    <?php if ($p['transaction_ref']): ?><div class="text-muted">Ref: <?= e($p['transaction_ref']) ?></div><?php endif; ?>
                  </td>
                  <td class="fw-bold"><?= e(format_currency($p['amount'])) ?></td>
                  <td><?= status_badge($p['status'], 'payment') ?></td>
                  <td class="small"><?= e(format_datetime($p['paid_at'] ?: $p['created_at'])) ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<script>
function updatePayAmount() {
  const sel = document.getElementById('payBookingSelect');
  const inp = document.getElementById('payAmountInput');
  if (sel && inp && sel.selectedOptions[0]) {
    inp.value = sel.selectedOptions[0].dataset.rem || 0;
  }
}
function toggleBankBox() {
  const bankChecked = document.getElementById('pmBank')?.checked;
  const box = document.getElementById('bankInfoBox');
  const ref = document.getElementById('txRefInput');
  if (box) box.style.display = bankChecked ? 'block' : 'none';
  if (ref) ref.required = !!bankChecked;
}
document.addEventListener('DOMContentLoaded', () => {
  updatePayAmount();
  toggleBankBox();
});
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
