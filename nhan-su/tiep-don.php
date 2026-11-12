<?php
/**
 * Staff: Front Desk Reception (Check-in & Check-out)
 * Lumina Hotel Management System
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';
require_role(['staff', 'admin']);

require_once dirname(__DIR__) . '/models/BookingModel.php';
require_once dirname(__DIR__) . '/models/PaymentModel.php';
require_once dirname(__DIR__) . '/controllers/BookingController.php';

$user = current_user();
$bookingModel = new BookingModel();
$bookingCtrl  = new BookingController();
$paymentModel = new PaymentModel();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('nhan-su/tiep-don.php');
    $action = $_POST['action'] ?? '';
    $bookingId = (int)($_POST['booking_id'] ?? 0);

    try {
        if ($action === 'confirm') {
            $bookingCtrl->staffConfirm($bookingId, (int)$user['id']);
            set_flash('success', 'Đã xác nhận đơn đặt phòng.');
        } elseif ($action === 'checkin') {
            $deposit = (float)($_POST['deposit_amount'] ?? 0);
            $idVer   = !empty($_POST['id_verified']);
            $notes   = trim((string)($_POST['notes'] ?? ''));

            $bookingCtrl->performCheckIn($bookingId, (int)$user['id'], $idVer, $deposit, $notes);

            if ($deposit > 0) {
                $bk = $bookingModel->findBooking($bookingId);
                $paymentModel->createPayment([
                    'booking_id'      => $bookingId,
                    'customer_id'     => (int)$bk['customer_id'],
                    'amount'          => $deposit,
                    'payment_method'  => $_POST['deposit_method'] ?? 'cash',
                    'payment_type'    => 'deposit',
                    'transaction_ref' => 'DEP-' . date('YmdHis'),
                    'confirmed_by'    => (int)$user['id'],
                    'notes'           => 'Tiền đặt cọc thu khi Check-in',
                ]);
            }
            set_flash('success', 'Check-in thành công! Phòng đã chuyển sang trạng thái Đang sử dụng.');
        } elseif ($action === 'checkout') {
            $collectAmount = (float)($_POST['collect_amount'] ?? 0);
            $collectMethod = $_POST['collect_method'] ?? 'cash';
            $notes = trim((string)($_POST['notes'] ?? ''));

            if ($collectAmount > 0) {
                $bk = $bookingModel->findBooking($bookingId);
                $paymentModel->createPayment([
                    'booking_id'      => $bookingId,
                    'customer_id'     => (int)$bk['customer_id'],
                    'amount'          => $collectAmount,
                    'payment_method'  => $collectMethod,
                    'payment_type'    => 'full',
                    'transaction_ref' => 'CHKOUT-' . date('YmdHis'),
                    'confirmed_by'    => (int)$user['id'],
                    'notes'           => 'Thanh toán tất toán khi Check-out',
                ]);
            }

            $updated = $bookingCtrl->performCheckOut($bookingId, (int)$user['id'], $notes);
            set_flash(
                'success',
                'Check-out hoàn tất cho đơn ' . $updated['booking_code'] . ' (Tổng hóa đơn: ' . format_currency($updated['total_amount']) . '). Phòng ' . $updated['room_number'] . ' đã chuyển sang Cần vệ sinh.'
            );
        }
    } catch (Throwable $e) {
        set_flash('danger', $e->getMessage());
    }
    redirect('nhan-su/tiep-don.php');
}

$arrivals    = $bookingModel->getTodayArrivals();
$activeStays = $bookingModel->getActiveStays();
$upcoming    = $bookingModel->getBookings(['status' => 'confirmed']);

$pageTitle = 'Tiếp đón Khách hàng (Check-in / Check-out)';
include dirname(__DIR__) . '/includes/header.php';
?>

<!-- Section 1: Pending & Confirmed Check-ins -->
<div class="lum-card mb-4">
  <div class="lum-card-header">
    <h6 class="mb-0 font-serif fw-bold" style="color:var(--lum-navy);">
      <i class="fa-solid fa-door-open me-2 text-warning"></i>Khách chờ Nhận phòng (Check-in)
    </h6>
    <span class="badge-lux badge-lux-info"><?= count($arrivals) ?> lượt hôm nay/quá hạn</span>
  </div>
  <div class="table-responsive">
    <table class="table lum-table">
      <thead>
        <tr>
          <th>Mã đơn</th>
          <th>Khách hàng</th>
          <th>Phòng</th>
          <th>Lịch lưu trú</th>
          <th>Trạng thái</th>
          <th class="text-end">Thao tác Lễ tân</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($arrivals)): ?>
          <tr><td colspan="6" class="text-center text-muted py-4">Không có khách chờ Check-in hôm nay.</td></tr>
        <?php else: ?>
          <?php foreach ($arrivals as $a): ?>
            <tr>
              <td class="fw-bold"><?= e($a['booking_code']) ?></td>
              <td>
                <div class="fw-semibold"><?= e($a['customer_name']) ?></div>
                <div class="small text-muted"><?= e($a['customer_phone']) ?></div>
              </td>
              <td>Phòng <strong><?= e($a['room_number']) ?></strong> <div class="small text-muted"><?= e($a['room_type_name']) ?></div></td>
              <td class="small"><?= e(format_date($a['check_in_date'])) ?> → <?= e(format_date($a['check_out_date'])) ?></td>
              <td><?= status_badge($a['status'], 'booking') ?></td>
              <td class="text-end text-nowrap">
                <?php if ($a['status'] === 'pending'): ?>
                  <form method="POST" class="d-inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="confirm">
                    <input type="hidden" name="booking_id" value="<?= (int)$a['id'] ?>">
                    <button class="btn btn-sm btn-outline-primary">Xác nhận đơn</button>
                  </form>
                <?php endif; ?>
                <button class="btn btn-sm btn-lum-gold" data-bs-toggle="modal" data-bs-target="#checkinModal<?= (int)$a['id'] ?>">
                  <i class="fa-solid fa-key me-1"></i>Check-in
                </button>
              </td>
            </tr>

            <div class="modal fade" id="checkinModal<?= (int)$a['id'] ?>" tabindex="-1">
              <div class="modal-dialog">
                <form method="POST" class="modal-content">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="checkin">
                  <input type="hidden" name="booking_id" value="<?= (int)$a['id'] ?>">
                  <div class="modal-header">
                    <h6 class="modal-title font-serif fw-bold">Check-in: <?= e($a['booking_code']) ?> — Phòng <?= e($a['room_number']) ?></h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                  </div>
                  <div class="modal-body">
                    <div class="mb-3 p-2 rounded bg-light small">
                      Khách hàng: <strong><?= e($a['customer_name']) ?></strong> · SĐT: <?= e($a['customer_phone']) ?>
                    </div>
                    <div class="form-check mb-3">
                      <input class="form-check-input" type="checkbox" name="id_verified" id="idVer<?= (int)$a['id'] ?>" value="1" checked required>
                      <label class="form-check-label small fw-semibold" for="idVer<?= (int)$a['id'] ?>">Đã đối chiếu CCCD / Hộ chiếu hợp lệ</label>
                    </div>
                    <div class="row g-2 mb-3">
                      <div class="col-7">
                        <label class="form-label small fw-semibold">Thu tiền đặt cọc (VNĐ)</label>
                        <input type="number" name="deposit_amount" class="form-control" value="0" min="0" step="100000">
                      </div>
                      <div class="col-5">
                        <label class="form-label small fw-semibold">Hình thức cọc</label>
                        <select name="deposit_method" class="form-select">
                          <option value="cash">Tiền mặt</option>
                          <option value="bank_transfer">Chuyển khoản</option>
                        </select>
                      </div>
                    </div>
                    <div>
                      <label class="form-label small fw-semibold">Ghi chú nhận phòng</label>
                      <input type="text" name="notes" class="form-control" placeholder="Đã giao 2 thẻ từ phòng...">
                    </div>
                  </div>
                  <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal">Đóng</button>
                    <button type="submit" class="btn btn-sm btn-lum-primary px-4">Hoàn tất Check-in</button>
                  </div>
                </form>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Section 2: Active Stays & Check-out -->
<div class="lum-card">
  <div class="lum-card-header">
    <h6 class="mb-0 font-serif fw-bold" style="color:var(--lum-navy);">
      <i class="fa-solid fa-bed me-2 text-primary"></i>Khách Đang lưu trú & Trả phòng (Check-out)
    </h6>
    <span class="badge-lux badge-lux-navy"><?= count($activeStays) ?> phòng đang sử dụng</span>
  </div>
  <div class="table-responsive">
    <table class="table lum-table">
      <thead>
        <tr>
          <th>Mã đơn</th>
          <th>Khách hàng</th>
          <th>Phòng</th>
          <th>Check-in lúc</th>
          <th>Tiền phòng + Dịch vụ</th>
          <th>Đã trả</th>
          <th>Còn lại</th>
          <th class="text-end">Thao tác</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($activeStays)): ?>
          <tr><td colspan="8" class="text-center text-muted py-4">Hiện không có khách đang lưu trú.</td></tr>
        <?php else: ?>
          <?php foreach ($activeStays as $s): ?>
            <?php $rem = max(0, (float)$s['total_amount'] - (float)$s['paid_amount']); ?>
            <tr>
              <td class="fw-bold"><?= e($s['booking_code']) ?></td>
              <td><?= e($s['customer_name']) ?><div class="small text-muted"><?= e($s['customer_phone']) ?></div></td>
              <td>Phòng <strong><?= e($s['room_number']) ?></strong> <div class="small text-muted"><?= e($s['room_type_name']) ?></div></td>
              <td class="small"><?= e(format_datetime($s['checked_in_at'])) ?></td>
              <td>
                <div class="fw-bold"><?= e(format_currency($s['total_amount'])) ?></div>
                <div class="small text-muted">Phòng: <?= e(format_currency($s['total_room_amount'])) ?> · DV: <?= e(format_currency($s['total_service_amount'])) ?></div>
              </td>
              <td class="text-success fw-semibold"><?= e(format_currency($s['paid_amount'])) ?></td>
              <td class="<?= $rem > 0 ? 'text-danger fw-bold' : 'text-success fw-semibold' ?>"><?= e(format_currency($rem)) ?></td>
              <td class="text-end text-nowrap">
                <a href="<?= e(base_url('khach-hang/hoa-don.php?id=' . (int)$s['id'])) ?>" class="btn btn-sm btn-light border" title="Xem hóa đơn">
                  <i class="fa-solid fa-file-invoice"></i>
                </a>
                <button class="btn btn-sm btn-lum-primary" data-bs-toggle="modal" data-bs-target="#checkoutModal<?= (int)$s['id'] ?>">
                  <i class="fa-solid fa-right-from-bracket me-1"></i>Check-out
                </button>
              </td>
            </tr>

            <div class="modal fade" id="checkoutModal<?= (int)$s['id'] ?>" tabindex="-1">
              <div class="modal-dialog">
                <form method="POST" class="modal-content">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="checkout">
                  <input type="hidden" name="booking_id" value="<?= (int)$s['id'] ?>">
                  <div class="modal-header">
                    <h6 class="modal-title font-serif fw-bold">Check-out & Lập Hóa đơn — Phòng <?= e($s['room_number']) ?></h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                  </div>
                  <div class="modal-body">
                    <div class="p-3 rounded-3 mb-3" style="background:var(--lum-cream);">
                      <div class="d-flex justify-content-between small mb-1"><span>Tiền phòng:</span><strong><?= e(format_currency($s['total_room_amount'])) ?></strong></div>
                      <div class="d-flex justify-content-between small mb-1"><span>Tiền dịch vụ:</span><strong><?= e(format_currency($s['total_service_amount'])) ?></strong></div>
                      <div class="d-flex justify-content-between small mb-1"><span>Đã thanh toán:</span><strong class="text-success"><?= e(format_currency($s['paid_amount'])) ?></strong></div>
                      <hr class="my-2">
                      <div class="d-flex justify-content-between"><span>Còn phải thu:</span><strong class="text-danger fs-5"><?= e(format_currency($rem)) ?></strong></div>
                    </div>

                    <?php if ($rem > 0): ?>
                      <div class="row g-2 mb-3">
                        <div class="col-7">
                          <label class="form-label small fw-semibold">Thu thêm khi Check-out (VNĐ)</label>
                          <input type="number" name="collect_amount" class="form-control" value="<?= (int)$rem ?>" min="0" step="1000">
                        </div>
                        <div class="col-5">
                          <label class="form-label small fw-semibold">Phương thức</label>
                          <select name="collect_method" class="form-select">
                            <option value="cash">Tiền mặt</option>
                            <option value="bank_transfer">Chuyển khoản</option>
                          </select>
                        </div>
                      </div>
                    <?php else: ?>
                      <input type="hidden" name="collect_amount" value="0">
                    <?php endif; ?>

                    <div class="mb-2">
                      <label class="form-label small fw-semibold">Ghi chú trả phòng</label>
                      <input type="text" name="notes" class="form-control" placeholder="Kiểm tra minibar, nhận lại thẻ phòng...">
                    </div>
                    <div class="alert alert-warning py-2 small mb-0">
                      <i class="fa-solid fa-broom me-1"></i>Sau Check-out, phòng <strong><?= e($s['room_number']) ?></strong> sẽ tự động chuyển sang <strong>Cần vệ sinh</strong>.
                    </div>
                  </div>
                  <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal">Đóng</button>
                    <button type="submit" class="btn btn-sm btn-lum-gold px-4">Xác nhận Check-out</button>
                  </div>
                </form>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
