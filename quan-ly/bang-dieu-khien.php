<?php
/**
 * Admin / Manager Overview Dashboard
 * Lumina Hotel Management System
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';
require_role(['admin']);

require_once dirname(__DIR__) . '/models/RoomModel.php';
require_once dirname(__DIR__) . '/models/UserModel.php';
require_once dirname(__DIR__) . '/models/PaymentModel.php';
require_once dirname(__DIR__) . '/models/BookingModel.php';

$roomModel    = new RoomModel();
$userModel    = new UserModel();
$paymentModel = new PaymentModel();
$bookingModel = new BookingModel();

$roomSummary   = $roomModel->getStatusSummary();
$customerCount = $userModel->countCustomers();
$employeeCount = $userModel->countEmployees();
$revenue       = $paymentModel->getRevenueSummary();
$chartYear     = (int)($_GET['year'] ?? date('Y'));
$monthlySeries = $paymentModel->getMonthlyChartSeries($chartYear);
$recentBookings = $bookingModel->getBookings(['limit' => 8]);

$pageTitle = 'Dashboard Quản trị Tổng quan';
include dirname(__DIR__) . '/includes/header.php';
?>

<!-- Row 1: Room & People KPIs -->
<div class="row g-3 mb-3">
  <div class="col-6 col-lg-4 col-xl-2">
    <div class="lum-kpi">
      <div class="lum-kpi-label">Tổng số phòng</div>
      <div class="lum-kpi-value"><?= (int)$roomSummary['total'] ?></div>
    </div>
  </div>
  <div class="col-6 col-lg-4 col-xl-2">
    <div class="lum-kpi">
      <div class="lum-kpi-label">Phòng trống</div>
      <div class="lum-kpi-value text-success"><?= (int)$roomSummary['available'] ?></div>
    </div>
  </div>
  <div class="col-6 col-lg-4 col-xl-2">
    <div class="lum-kpi">
      <div class="lum-kpi-label">Đã đặt</div>
      <div class="lum-kpi-value text-primary"><?= (int)$roomSummary['reserved'] ?></div>
    </div>
  </div>
  <div class="col-6 col-lg-4 col-xl-2">
    <div class="lum-kpi">
      <div class="lum-kpi-label">Đang sử dụng</div>
      <div class="lum-kpi-value"><?= (int)$roomSummary['occupied'] ?></div>
    </div>
  </div>
  <div class="col-6 col-lg-4 col-xl-2">
    <div class="lum-kpi">
      <div class="lum-kpi-label">Khách hàng</div>
      <div class="lum-kpi-value"><?= $customerCount ?></div>
    </div>
  </div>
  <div class="col-6 col-lg-4 col-xl-2">
    <div class="lum-kpi">
      <div class="lum-kpi-label">Nhân sự</div>
      <div class="lum-kpi-value"><?= $employeeCount ?></div>
    </div>
  </div>
</div>

<!-- Row 2: Revenue KPIs -->
<div class="row g-3 mb-4">
  <div class="col-md-4">
    <div class="lum-kpi">
      <div class="d-flex justify-content-between align-items-center">
        <div>
          <div class="lum-kpi-label">Doanh thu Hôm nay</div>
          <div class="lum-kpi-value" style="font-size:1.35rem;"><?= e(format_currency($revenue['today'])) ?></div>
        </div>
        <div class="lum-kpi-icon"><i class="fa-solid fa-sun"></i></div>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="lum-kpi">
      <div class="d-flex justify-content-between align-items-center">
        <div>
          <div class="lum-kpi-label">Doanh thu Tháng <?= date('m/Y') ?></div>
          <div class="lum-kpi-value" style="font-size:1.35rem;"><?= e(format_currency($revenue['month'])) ?></div>
        </div>
        <div class="lum-kpi-icon"><i class="fa-solid fa-calendar-days"></i></div>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="lum-kpi">
      <div class="d-flex justify-content-between align-items-center">
        <div>
          <div class="lum-kpi-label">Doanh thu Năm <?= date('Y') ?></div>
          <div class="lum-kpi-value" style="font-size:1.35rem;"><?= e(format_currency($revenue['year'])) ?></div>
        </div>
        <div class="lum-kpi-icon"><i class="fa-solid fa-chart-line"></i></div>
      </div>
    </div>
  </div>
</div>

<!-- Row 3: Charts -->
<div class="row g-4 mb-4">
  <div class="col-lg-8">
    <div class="lum-card h-100">
      <div class="lum-card-header">
        <h6 class="mb-0 font-serif fw-bold" style="color:var(--lum-navy);">Biểu đồ Doanh thu & Chi phí Năm <?= $chartYear ?></h6>
        <form method="GET" class="d-flex gap-2">
          <select name="year" class="form-select form-select-sm" onchange="this.form.submit()">
            <?php for ($y = (int)date('Y'); $y >= (int)date('Y') - 3; $y--): ?>
              <option value="<?= $y ?>" <?= $chartYear === $y ? 'selected' : '' ?>>Năm <?= $y ?></option>
            <?php endfor; ?>
          </select>
        </form>
      </div>
      <div class="lum-card-body">
        <canvas id="revenueChart" height="115"></canvas>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="lum-card h-100">
      <div class="lum-card-header">
        <h6 class="mb-0 font-serif fw-bold" style="color:var(--lum-navy);">Thống kê Tình trạng Phòng</h6>
      </div>
      <div class="lum-card-body d-flex flex-column justify-content-center">
        <canvas id="roomStatusChart" height="200"></canvas>
        <div class="row g-1 mt-3 small text-center">
          <div class="col-6">Vệ sinh: <strong><?= (int)$roomSummary['cleaning'] ?></strong></div>
          <div class="col-6">Bảo trì: <strong><?= (int)$roomSummary['maintenance'] ?></strong></div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Row 4: Recent Bookings -->
<div class="lum-card">
  <div class="lum-card-header">
    <h6 class="mb-0 font-serif fw-bold" style="color:var(--lum-navy);">Danh sách Đặt phòng Gần đây</h6>
    <a href="<?= e(base_url('quan-ly/dat-phong.php')) ?>" class="btn btn-sm btn-lum-outline rounded-pill px-3">Quản lý tất cả</a>
  </div>
  <div class="table-responsive">
    <table class="table lum-table">
      <thead>
        <tr>
          <th>Mã đơn</th>
          <th>Khách hàng</th>
          <th>Phòng</th>
          <th>Nhận - Trả</th>
          <th>Tổng tiền</th>
          <th>Trạng thái</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($recentBookings as $b): ?>
          <tr>
            <td class="fw-bold"><?= e($b['booking_code']) ?></td>
            <td><?= e($b['customer_name']) ?><div class="small text-muted"><?= e($b['customer_phone']) ?></div></td>
            <td>Phòng <?= e($b['room_number']) ?> <div class="small text-muted"><?= e($b['room_type_name']) ?></div></td>
            <td class="small"><?= e(format_date($b['check_in_date'])) ?> → <?= e(format_date($b['check_out_date'])) ?></td>
            <td class="fw-semibold"><?= e(format_currency($b['total_amount'])) ?></td>
            <td><?= status_badge($b['status'], 'booking') ?></td>
            <td class="text-end">
              <a href="<?= e(base_url('khach-hang/hoa-don.php?id=' . (int)$b['id'])) ?>" class="btn btn-sm btn-light border"><i class="fa-solid fa-eye"></i></a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const revCtx = document.getElementById('revenueChart');
  if (revCtx) {
    new Chart(revCtx, {
      type: 'bar',
      data: {
        labels: <?= json_encode($monthlySeries['labels']) ?>,
        datasets: [
          {
            label: 'Doanh thu (VNĐ)',
            data: <?= json_encode($monthlySeries['revenues']) ?>,
            backgroundColor: 'rgba(15, 28, 46, 0.88)',
            borderColor: '#C9A96E',
            borderWidth: 1,
            borderRadius: 6
          },
          {
            label: 'Chi phí (VNĐ)',
            data: <?= json_encode($monthlySeries['expenses']) ?>,
            backgroundColor: 'rgba(201, 169, 110, 0.75)',
            borderRadius: 6
          }
        ]
      },
      options: {
        responsive: true,
        plugins: { legend: { position: 'top' } },
        scales: { y: { beginAtZero: true } }
      }
    });
  }

  const roomCtx = document.getElementById('roomStatusChart');
  if (roomCtx) {
    new Chart(roomCtx, {
      type: 'doughnut',
      data: {
        labels: ['Trống', 'Đã đặt', 'Đang sử dụng', 'Cần vệ sinh', 'Bảo trì'],
        datasets: [{
          data: [
            <?= (int)$roomSummary['available'] ?>,
            <?= (int)$roomSummary['reserved'] ?>,
            <?= (int)$roomSummary['occupied'] ?>,
            <?= (int)$roomSummary['cleaning'] ?>,
            <?= (int)$roomSummary['maintenance'] ?>
          ],
          backgroundColor: ['#198754', '#0D6EFD', '#0F1C2E', '#E0A800', '#DC3545']
        }]
      },
      options: {
        responsive: true,
        plugins: { legend: { position: 'bottom' } }
      }
    });
  }
});
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
