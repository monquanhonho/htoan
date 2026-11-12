<?php
/**
 * Staff Dashboard
 * Lumina Hotel Management System
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';
require_role(['staff', 'admin']);

require_once dirname(__DIR__) . '/models/BookingModel.php';
require_once dirname(__DIR__) . '/models/RoomModel.php';
require_once dirname(__DIR__) . '/models/ServiceModel.php';
require_once dirname(__DIR__) . '/models/PaymentModel.php';
require_once dirname(__DIR__) . '/models/OperationsModel.php';

$user = current_user();
$bookingModel = new BookingModel();
$roomModel    = new RoomModel();
$serviceModel = new ServiceModel();
$paymentModel = new PaymentModel();
$opsModel     = new OperationsModel();

$arrivals        = $bookingModel->getTodayArrivals();
$activeStays     = $bookingModel->getActiveStays();
$roomSummary     = $roomModel->getStatusSummary();
$pendingServices = $serviceModel->getServiceOrders(['status' => 'pending']);
$pendingPayments = $paymentModel->getPayments(['status' => 'pending']);
$cleaningPending = $opsModel->getCleaningList('pending');
$openSupport     = $opsModel->getSupportRequests(null, 'open');
$myTasks         = $opsModel->getTasks(!empty($user['employee_id']) ? (int)$user['employee_id'] : null);

$pageTitle = 'Dashboard Nhân sự';
include dirname(__DIR__) . '/includes/header.php';
?>

<div class="row g-3 mb-4">
  <div class="col-sm-6 col-xl-3">
    <div class="lum-kpi">
      <div class="d-flex justify-content-between align-items-center">
        <div>
          <div class="lum-kpi-label">Chờ Check-in</div>
          <div class="lum-kpi-value"><?= count($arrivals) ?></div>
        </div>
        <div class="lum-kpi-icon"><i class="fa-solid fa-door-open"></i></div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="lum-kpi">
      <div class="d-flex justify-content-between align-items-center">
        <div>
          <div class="lum-kpi-label">Đang lưu trú</div>
          <div class="lum-kpi-value"><?= count($activeStays) ?></div>
        </div>
        <div class="lum-kpi-icon"><i class="fa-solid fa-bed"></i></div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="lum-kpi">
      <div class="d-flex justify-content-between align-items-center">
        <div>
          <div class="lum-kpi-label">Phòng cần vệ sinh</div>
          <div class="lum-kpi-value"><?= count($cleaningPending) ?></div>
        </div>
        <div class="lum-kpi-icon"><i class="fa-solid fa-broom"></i></div>
      </div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="lum-kpi">
      <div class="d-flex justify-content-between align-items-center">
        <div>
          <div class="lum-kpi-label">Dịch vụ / Hỗ trợ chờ</div>
          <div class="lum-kpi-value"><?= count($pendingServices) + count($openSupport) ?></div>
        </div>
        <div class="lum-kpi-icon"><i class="fa-solid fa-bell-concierge"></i></div>
      </div>
    </div>
  </div>
</div>

<!-- Room Status Bar -->
<div class="lum-card mb-4">
  <div class="lum-card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
    <div class="fw-semibold" style="color:var(--lum-navy);">Tổng quan <?= (int)$roomSummary['total'] ?> phòng:</div>
    <div class="d-flex flex-wrap gap-2">
      <span class="badge-lux badge-lux-success">Trống: <?= (int)$roomSummary['available'] ?></span>
      <span class="badge-lux badge-lux-info">Đã đặt: <?= (int)$roomSummary['reserved'] ?></span>
      <span class="badge-lux badge-lux-navy">Đang sử dụng: <?= (int)$roomSummary['occupied'] ?></span>
      <span class="badge-lux badge-lux-warning">Cần vệ sinh: <?= (int)$roomSummary['cleaning'] ?></span>
      <span class="badge-lux badge-lux-danger">Bảo trì: <?= (int)$roomSummary['maintenance'] ?></span>
    </div>
    <a href="<?= e(base_url('nhan-su/tiep-don.php')) ?>" class="btn btn-sm btn-lum-primary rounded-pill px-3">
      Quầy Lễ tân <i class="fa-solid fa-arrow-right ms-1"></i>
    </a>
  </div>
</div>

<div class="row g-4">
  <!-- Arrivals -->
  <div class="col-lg-6">
    <div class="lum-card h-100">
      <div class="lum-card-header">
        <h6 class="mb-0 font-serif fw-bold" style="color:var(--lum-navy);">Khách dự kiến Check-in</h6>
        <a href="<?= e(base_url('nhan-su/tiep-don.php')) ?>" class="small fw-semibold">Xử lý ngay</a>
      </div>
      <div class="table-responsive">
        <table class="table lum-table">
          <thead><tr><th>Mã đơn</th><th>Khách hàng</th><th>Phòng</th><th>Ngày nhận</th><th>Trạng thái</th></tr></thead>
          <tbody>
            <?php if (empty($arrivals)): ?>
              <tr><td colspan="5" class="text-center text-muted py-4">Không có khách chờ nhận phòng hôm nay.</td></tr>
            <?php else: ?>
              <?php foreach ($arrivals as $a): ?>
                <tr>
                  <td class="fw-semibold"><?= e($a['booking_code']) ?></td>
                  <td><?= e($a['customer_name']) ?><div class="small text-muted"><?= e($a['customer_phone']) ?></div></td>
                  <td>Phòng <?= e($a['room_number']) ?></td>
                  <td class="small"><?= e(format_date($a['check_in_date'])) ?></td>
                  <td><?= status_badge($a['status'], 'booking') ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Assigned Tasks -->
  <div class="col-lg-6">
    <div class="lum-card h-100">
      <div class="lum-card-header">
        <h6 class="mb-0 font-serif fw-bold" style="color:var(--lum-navy);">Công việc được phân công</h6>
        <a href="<?= e(base_url('nhan-su/cong-viec.php')) ?>" class="small fw-semibold">Xem tất cả</a>
      </div>
      <div class="table-responsive">
        <table class="table lum-table">
          <thead><tr><th>Công việc</th><th>Ưu tiên</th><th>Hạn</th><th>Trạng thái</th></tr></thead>
          <tbody>
            <?php if (empty($myTasks)): ?>
              <tr><td colspan="4" class="text-center text-muted py-4">Chưa có công việc nào được giao.</td></tr>
            <?php else: ?>
              <?php foreach (array_slice($myTasks, 0, 5) as $t): ?>
                <tr>
                  <td>
                    <div class="fw-semibold"><?= e($t['title']) ?></div>
                    <div class="small text-muted"><?= e($t['assigned_to_name']) ?></div>
                  </td>
                  <td><?= status_badge($t['priority'], 'priority') ?></td>
                  <td class="small"><?= e(format_date($t['due_date'])) ?></td>
                  <td><?= status_badge($t['status'], 'task') ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Pending Payments & Cleaning -->
  <div class="col-12">
    <div class="lum-card">
      <div class="lum-card-header">
        <h6 class="mb-0 font-serif fw-bold" style="color:var(--lum-navy);">Giao dịch chờ đối soát (<?= count($pendingPayments) ?>)</h6>
        <a href="<?= e(base_url('nhan-su/thanh-toan.php')) ?>" class="btn btn-sm btn-lum-outline rounded-pill px-3">Quản lý Thanh toán</a>
      </div>
      <div class="table-responsive">
        <table class="table lum-table">
          <thead><tr><th>Mã GD</th><th>Khách hàng</th><th>Đơn / Phòng</th><th>Số tiền</th><th>Phương thức</th><th>Mã tham chiếu</th></tr></thead>
          <tbody>
            <?php if (empty($pendingPayments)): ?>
              <tr><td colspan="6" class="text-center text-muted py-3">Không có giao dịch chờ xác nhận.</td></tr>
            <?php else: ?>
              <?php foreach ($pendingPayments as $p): ?>
                <tr>
                  <td class="fw-semibold"><?= e($p['payment_code']) ?></td>
                  <td><?= e($p['customer_name']) ?></td>
                  <td><?= e($p['booking_code']) ?> · Phòng <?= e($p['room_number']) ?></td>
                  <td class="fw-bold text-primary"><?= e(format_currency($p['amount'])) ?></td>
                  <td><?= $p['payment_method'] === 'cash' ? 'Tiền mặt' : 'Chuyển khoản' ?></td>
                  <td><?= e($p['transaction_ref'] ?: '—') ?></td>
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
