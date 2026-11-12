<?php
/**
 * Itemized Booking Invoice (Room + Services + Payments)
 * Lumina Hotel Management System
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';
require_login();

require_once dirname(__DIR__) . '/models/BookingModel.php';
require_once dirname(__DIR__) . '/models/ServiceModel.php';
require_once dirname(__DIR__) . '/models/PaymentModel.php';

$bookingId = (int)($_GET['id'] ?? 0);
$bookingModel = new BookingModel();
$bookingModel->recalculateTotals($bookingId);
$booking = $bookingModel->findBooking($bookingId);

$user = current_user();
if (!$booking || ($user['role'] === 'customer' && (int)$booking['customer_id'] !== (int)$user['customer_id'])) {
    set_flash('danger', 'Không tìm thấy hóa đơn hoặc bạn không có quyền xem.');
    redirect(role_dashboard_path($user['role']));
}

$serviceModel = new ServiceModel();
$paymentModel = new PaymentModel();

$serviceOrders = $serviceModel->getServiceOrders(['booking_id' => $bookingId]);
$payments      = $paymentModel->getPayments(['booking_id' => $bookingId]);
$remaining     = max(0, (float)$booking['total_amount'] - (float)$booking['paid_amount']);

$pageTitle = 'Hóa đơn ' . $booking['booking_code'];
include dirname(__DIR__) . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3 no-print">
  <a href="<?= e(base_url($user['role'] === 'customer' ? 'khach-hang/dat-phong.php' : ($user['role'] === 'staff' ? 'nhan-su/dat-phong.php' : 'quan-ly/dat-phong.php'))) ?>"
     class="btn btn-sm btn-light border rounded-pill px-3">
    <i class="fa-solid fa-arrow-left me-1"></i>Quay lại
  </a>
  <div class="d-flex gap-2">
    <?php if ($user['role'] === 'customer' && $remaining > 0): ?>
      <a href="<?= e(base_url('khach-hang/thanh-toan.php?booking_id=' . $bookingId)) ?>" class="btn btn-sm btn-lum-gold rounded-pill px-3">
        <i class="fa-solid fa-credit-card me-1"></i>Thanh toán ngay
      </a>
    <?php endif; ?>
    <button onclick="window.print()" class="btn btn-sm btn-lum-primary rounded-pill px-3">
      <i class="fa-solid fa-print me-1"></i>In / Xuất PDF
    </button>
  </div>
</div>

<div class="lum-card p-4 p-md-5">
  <div class="row border-bottom pb-4 mb-4 align-items-center">
    <div class="col-sm-6 d-flex align-items-center gap-3">
      <img src="<?= e(base_url(APP_LOGO)) ?>" alt="Purple Lotus Hotel" class="lum-brand-logo" style="width:52px;height:52px;">
      <div>
        <div class="lum-brand-title fs-4" style="color:var(--lum-navy);">PURPLE LOTUS HOTEL</div>
        <div class="small text-muted">Hải Dương</div>
        <div class="small text-muted">Hotline: 1900 6868 · contact@purplelotushotel.vn</div>
      </div>
    </div>
    <div class="col-sm-6 text-sm-end mt-3 mt-sm-0">
      <h4 class="font-serif fw-bold mb-1" style="color:var(--lum-navy);">HÓA ĐƠN LƯU TRÚ</h4>
      <div class="small">Mã đơn: <strong><?= e($booking['booking_code']) ?></strong></div>
      <div class="small">Ngày lập: <?= e(date('d/m/Y H:i')) ?></div>
      <div class="mt-1"><?= status_badge($booking['status'], 'booking') ?></div>
    </div>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-md-6">
      <div class="p-3 rounded-3" style="background:var(--lum-cream);">
        <div class="small text-uppercase fw-bold text-muted mb-1">Thông tin Khách hàng</div>
        <div class="fw-bold"><?= e($booking['customer_name']) ?></div>
        <div class="small">SĐT: <?= e($booking['customer_phone'] ?: '—') ?> · Email: <?= e($booking['customer_email']) ?></div>
        <div class="small">CCCD/Hộ chiếu: <?= e($booking['id_card'] ?: '—') ?></div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="p-3 rounded-3" style="background:var(--lum-cream);">
        <div class="small text-uppercase fw-bold text-muted mb-1">Chi tiết Lưu trú</div>
        <div class="fw-bold">Phòng <?= e($booking['room_number']) ?> — <?= e($booking['room_type_name']) ?></div>
        <div class="small">Nhận phòng: <?= e(format_date($booking['check_in_date'])) ?> · Trả phòng: <?= e(format_date($booking['check_out_date'])) ?> (<?= (int)$booking['nights'] ?> đêm)</div>
        <?php if (!empty($booking['checked_in_at'])): ?>
          <div class="small text-muted">Check-in thực tế: <?= e(format_datetime($booking['checked_in_at'])) ?></div>
        <?php endif; ?>
        <?php if (!empty($booking['checked_out_at'])): ?>
          <div class="small text-muted">Check-out thực tế: <?= e(format_datetime($booking['checked_out_at'])) ?></div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <h6 class="font-serif fw-bold mb-2" style="color:var(--lum-navy);">1. Chi phí Tiền phòng & Dịch vụ</h6>
  <div class="table-responsive mb-4">
    <table class="table lum-table border">
      <thead>
        <tr>
          <th>Hạng mục</th>
          <th>Đơn giá</th>
          <th>Số lượng</th>
          <th>Trạng thái</th>
          <th class="text-end">Thành tiền</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td>
            <strong>Tiền phòng <?= e($booking['room_number']) ?> (<?= e($booking['room_type_name']) ?>)</strong>
            <div class="small text-muted"><?= e(format_date($booking['check_in_date'])) ?> → <?= e(format_date($booking['check_out_date'])) ?></div>
          </td>
          <td><?= e(format_currency($booking['price_per_night'])) ?></td>
          <td><?= (int)$booking['nights'] ?> đêm</td>
          <td><span class="badge-lux badge-lux-navy">Lưu trú</span></td>
          <td class="text-end fw-semibold"><?= e(format_currency($booking['total_room_amount'])) ?></td>
        </tr>
        <?php foreach ($serviceOrders as $so): ?>
          <tr>
            <td>
              <strong><?= e($so['service_name']) ?></strong>
              <?php if ($so['notes']): ?><div class="small text-muted"><?= e($so['notes']) ?></div><?php endif; ?>
            </td>
            <td><?= e(format_currency($so['unit_price'])) ?></td>
            <td><?= (int)$so['quantity'] ?> <?= e($so['unit']) ?></td>
            <td><?= status_badge($so['status'], 'service') ?></td>
            <td class="text-end fw-semibold">
              <?= in_array($so['status'], ['confirmed', 'in_progress', 'completed'], true) ? e(format_currency($so['total_price'])) : '<span class="text-muted text-decoration-line-through">' . e(format_currency($so['total_price'])) . '</span>' ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="row justify-content-end mb-4">
    <div class="col-md-5">
      <table class="table table-sm mb-0">
        <tr>
          <td class="text-muted">Tổng tiền phòng:</td>
          <td class="text-end fw-semibold"><?= e(format_currency($booking['total_room_amount'])) ?></td>
        </tr>
        <tr>
          <td class="text-muted">Tổng tiền dịch vụ (đã xác nhận):</td>
          <td class="text-end fw-semibold"><?= e(format_currency($booking['total_service_amount'])) ?></td>
        </tr>
        <tr class="border-top">
          <td class="fw-bold fs-6" style="color:var(--lum-navy);">TỔNG CỘNG HÓA ĐƠN:</td>
          <td class="text-end fw-bold fs-5" style="color:var(--lum-navy);"><?= e(format_currency($booking['total_amount'])) ?></td>
        </tr>
        <tr>
          <td class="text-success">Đã thanh toán:</td>
          <td class="text-end text-success fw-semibold"><?= e(format_currency($booking['paid_amount'])) ?></td>
        </tr>
        <tr>
          <td class="fw-bold text-danger">Còn lại phải thanh toán:</td>
          <td class="text-end fw-bold text-danger"><?= e(format_currency($remaining)) ?></td>
        </tr>
      </table>
    </div>
  </div>

  <?php if (!empty($payments)): ?>
    <h6 class="font-serif fw-bold mb-2" style="color:var(--lum-navy);">2. Lịch sử Giao dịch Thanh toán</h6>
    <div class="table-responsive">
      <table class="table table-sm lum-table border">
        <thead>
          <tr>
            <th>Mã GD</th>
            <th>Phương thức</th>
            <th>Mã tham chiếu</th>
            <th>Số tiền</th>
            <th>Trạng thái</th>
            <th>Thời gian</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($payments as $p): ?>
            <tr>
              <td><?= e($p['payment_code']) ?></td>
              <td><?= $p['payment_method'] === 'cash' ? 'Tiền mặt tại khách sạn' : 'Chuyển khoản ngân hàng' ?></td>
              <td><?= e($p['transaction_ref'] ?: '—') ?></td>
              <td class="fw-semibold"><?= e(format_currency($p['amount'])) ?></td>
              <td><?= status_badge($p['status'], 'payment') ?></td>
              <td class="small"><?= e(format_datetime($p['paid_at'] ?: $p['created_at'])) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
