<?php
/**
 * Customer Dashboard
 * Lumina Hotel Management System
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';
require_role(['customer']);

require_once dirname(__DIR__) . '/models/BookingModel.php';
require_once dirname(__DIR__) . '/models/ServiceModel.php';
require_once dirname(__DIR__) . '/models/PaymentModel.php';
require_once dirname(__DIR__) . '/models/OperationsModel.php';

$user = current_user();
$customerId = (int)$user['customer_id'];

$bookingModel = new BookingModel();
$serviceModel = new ServiceModel();
$paymentModel = new PaymentModel();
$opsModel     = new OperationsModel();

$bookings = $bookingModel->getBookings(['customer_id' => $customerId]);
$services = $serviceModel->getServiceOrders(['customer_id' => $customerId]);
$payments = $paymentModel->getPayments(['customer_id' => $customerId]);
$tickets  = $opsModel->getSupportRequests($customerId);

$activeBookings = array_filter($bookings, fn($b) => in_array($b['status'], ['pending', 'confirmed', 'checked_in'], true));
$totalSpent = array_sum(array_map(fn($p) => $p['status'] === 'completed' ? (float)$p['amount'] : 0.0, $payments));
$serviceCost = array_sum(array_map(fn($s) => in_array($s['status'], ['confirmed', 'in_progress', 'completed'], true) ? (float)$s['total_price'] : 0.0, $services));

$pageTitle = 'Không gian Khách hàng';
include dirname(__DIR__) . '/includes/header.php';
?>

<div class="lum-card lum-hero p-4 mb-4 text-white">
  <div class="row align-items-center g-3">
    <div class="col-md-8">
      <div class="small text-uppercase" style="color:var(--lum-gold-light);letter-spacing:0.15em;">
        Hạng thành viên: <?= strtoupper(e($user['vip_tier'] ?? 'Standard')) ?>
      </div>
      <h3 class="font-serif mb-1">Xin chào, <?= e($user['full_name']) ?></h3>
      <p class="mb-0 opacity-75 small">Quản lý kỳ nghỉ, đặt phòng trực tuyến, gọi dịch vụ phòng và theo dõi hóa đơn tại Purple Lotus Hotel.</p>
    </div>
    <div class="col-md-4 text-md-end">
      <a href="<?= e(base_url('khach-hang/danh-sach-phong.php')) ?>" class="btn btn-lum-gold rounded-pill px-4">
        <i class="fa-solid fa-plus me-1"></i>Đặt phòng mới
      </a>
    </div>
  </div>
</div>

<!-- KPI Row -->
<div class="row g-3 mb-4">
  <div class="col-sm-6 col-xl-3">
    <div class="lum-kpi">
      <div class="d-flex justify-content-between align-items-center">
        <div>
          <div class="lum-kpi-label">Đặt phòng hiện tại</div>
          <div class="lum-kpi-value"><?= count($activeBookings) ?></div>
        </div>
        <div class="lum-kpi-icon"><i class="fa-solid fa-calendar-check"></i></div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="lum-kpi">
      <div class="d-flex justify-content-between align-items-center">
        <div>
          <div class="lum-kpi-label">Tổng lượt đặt</div>
          <div class="lum-kpi-value"><?= count($bookings) ?></div>
        </div>
        <div class="lum-kpi-icon"><i class="fa-solid fa-suitcase"></i></div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="lum-kpi">
      <div class="d-flex justify-content-between align-items-center">
        <div>
          <div class="lum-kpi-label">Chi phí dịch vụ</div>
          <div class="lum-kpi-value" style="font-size:1.25rem;"><?= e(format_currency($serviceCost)) ?></div>
        </div>
        <div class="lum-kpi-icon"><i class="fa-solid fa-spa"></i></div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="lum-kpi">
      <div class="d-flex justify-content-between align-items-center">
        <div>
          <div class="lum-kpi-label">Đã thanh toán</div>
          <div class="lum-kpi-value" style="font-size:1.25rem;"><?= e(format_currency($totalSpent)) ?></div>
        </div>
        <div class="lum-kpi-icon"><i class="fa-solid fa-wallet"></i></div>
      </div>
    </div>
  </div>
</div>

<div class="row g-4">
  <div class="col-lg-8">
    <div class="lum-card">
      <div class="lum-card-header">
        <h6 class="mb-0 font-serif fw-bold" style="color:var(--lum-navy);">Đặt phòng gần đây của bạn</h6>
        <a href="<?= e(base_url('khach-hang/dat-phong.php')) ?>" class="btn btn-sm btn-lum-outline rounded-pill px-3">Xem tất cả</a>
      </div>
      <div class="table-responsive">
        <table class="table lum-table">
          <thead>
            <tr>
              <th>Mã đơn</th>
              <th>Phòng</th>
              <th>Nhận - Trả</th>
              <th>Tổng tiền</th>
              <th>Trạng thái</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($bookings)): ?>
              <tr><td colspan="6" class="text-center text-muted py-4">Bạn chưa có đặt phòng nào.</td></tr>
            <?php else: ?>
              <?php foreach (array_slice($bookings, 0, 5) as $b): ?>
                <tr>
                  <td class="fw-semibold"><?= e($b['booking_code']) ?></td>
                  <td>Phòng <?= e($b['room_number']) ?> <div class="small text-muted"><?= e($b['room_type_name']) ?></div></td>
                  <td class="small"><?= e(format_date($b['check_in_date'])) ?> → <?= e(format_date($b['check_out_date'])) ?></td>
                  <td class="fw-semibold"><?= e(format_currency($b['total_amount'])) ?></td>
                  <td><?= status_badge($b['status'], 'booking') ?></td>
                  <td>
                    <a href="<?= e(base_url('khach-hang/hoa-don.php?id=' . (int)$b['id'])) ?>" class="btn btn-sm btn-light border" title="Chi tiết hóa đơn">
                      <i class="fa-solid fa-file-invoice"></i>
                    </a>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="lum-card h-100">
      <div class="lum-card-header">
        <h6 class="mb-0 font-serif fw-bold" style="color:var(--lum-navy);">Thao tác nhanh</h6>
      </div>
      <div class="lum-card-body d-grid gap-2">
        <a href="<?= e(base_url('khach-hang/danh-sach-phong.php')) ?>" class="btn btn-lum-primary text-start py-2 px-3 rounded-3">
          <i class="fa-solid fa-bed me-2"></i>Tìm phòng & Đặt phòng trực tuyến
        </a>
        <a href="<?= e(base_url('khach-hang/dich-vu.php')) ?>" class="btn btn-light border text-start py-2 px-3 rounded-3">
          <i class="fa-solid fa-bell-concierge me-2 text-warning"></i>Đặt Ăn uống / Giặt ủi / Spa
        </a>
        <a href="<?= e(base_url('khach-hang/thanh-toan.php')) ?>" class="btn btn-light border text-start py-2 px-3 rounded-3">
          <i class="fa-solid fa-credit-card me-2 text-info"></i>Thanh toán & Lịch sử giao dịch
        </a>
        <a href="<?= e(base_url('khach-hang/ho-tro.php')) ?>" class="btn btn-light border text-start py-2 px-3 rounded-3">
          <i class="fa-solid fa-headset me-2 text-danger"></i>Gửi yêu cầu hỗ trợ (<?= count($tickets) ?>)
        </a>
        <a href="<?= e(base_url('khach-hang/ho-so.php')) ?>" class="btn btn-light border text-start py-2 px-3 rounded-3">
          <i class="fa-solid fa-user-pen me-2 text-secondary"></i>Cập nhật hồ sơ & Đổi mật khẩu
        </a>
      </div>
    </div>
  </div>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
