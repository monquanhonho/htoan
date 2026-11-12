<?php
/**
 * Admin: Financial Management, Expenses & Reports
 * Lumina Hotel Management System
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';
require_role(['admin']);

require_once dirname(__DIR__) . '/models/PaymentModel.php';

$user = current_user();
$paymentModel = new PaymentModel();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('quan-ly/tai-chinh.php');
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'create_expense') {
            $id = $paymentModel->createExpense([
                'title'        => trim((string)$_POST['title']),
                'category'     => $_POST['category'] ?? 'other',
                'amount'       => (float)$_POST['amount'],
                'expense_date' => $_POST['expense_date'] ?: date('Y-m-d'),
                'description'  => trim((string)($_POST['description'] ?? '')),
                'recorded_by'  => (int)$user['id'],
            ]);
            log_security_event((int)$user['id'], 'Ghi nhận chi phí hoạt động #' . $id . ': ' . $_POST['title'], 'action', 'info');
            set_flash('success', 'Đã ghi nhận khoản chi phí mới.');
        } elseif ($action === 'delete_expense') {
            $id = (int)$_POST['expense_id'];
            $paymentModel->deleteExpense($id);
            log_security_event((int)$user['id'], 'Xóa khoản chi phí #' . $id, 'action', 'warning');
            set_flash('success', 'Đã xóa khoản chi phí.');
        } elseif ($action === 'update_payment') {
            $paymentModel->confirmPayment((int)$_POST['payment_id'], (int)$user['id'], $_POST['status']);
            log_security_event((int)$user['id'], 'Cập nhật thanh toán #' . $_POST['payment_id'] . ' -> ' . $_POST['status'], 'action', 'info');
            set_flash('success', 'Đã cập nhật trạng thái thanh toán.');
        }
    } catch (Throwable $e) {
        set_flash('danger', $e->getMessage());
    }
    redirect('quan-ly/tai-chinh.php');
}

$summary       = $paymentModel->getRevenueSummary();
$chartYear     = (int)($_GET['year'] ?? date('Y'));
$monthlySeries = $paymentModel->getMonthlyChartSeries($chartYear);
$payments      = $paymentModel->getPayments([]);
$expenses      = $paymentModel->getExpenses();

$pageTitle = 'Quản lý Tài chính & Báo cáo';
include dirname(__DIR__) . '/includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2 no-print">
  <div class="small text-muted">Thống kê doanh thu tiền phòng, dịch vụ, thanh toán và chi phí vận hành.</div>
  <div class="d-flex gap-2">
    <a href="<?= e(base_url('quan-ly/xuat-bao-cao.php?format=excel&year=' . $chartYear)) ?>" class="btn btn-sm btn-success rounded-pill px-3">
      <i class="fa-solid fa-file-excel me-1"></i>Xuất Excel (.csv)
    </a>
    <button onclick="window.print()" class="btn btn-sm btn-lum-primary rounded-pill px-3">
      <i class="fa-solid fa-file-pdf me-1"></i>Xuất Báo cáo PDF
    </button>
    <button class="btn btn-sm btn-lum-gold rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#addExpenseModal">
      <i class="fa-solid fa-plus me-1"></i>Thêm Chi phí
    </button>
  </div>
</div>

<!-- Financial KPIs -->
<div class="row g-3 mb-4">
  <div class="col-sm-6 col-xl-3">
    <div class="lum-kpi">
      <div class="lum-kpi-label">Doanh thu Phòng</div>
      <div class="lum-kpi-value" style="font-size:1.3rem;"><?= e(format_currency($summary['room_revenue'])) ?></div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="lum-kpi">
      <div class="lum-kpi-label">Doanh thu Dịch vụ</div>
      <div class="lum-kpi-value" style="font-size:1.3rem;"><?= e(format_currency($summary['service_revenue'])) ?></div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="lum-kpi">
      <div class="lum-kpi-label">Chi phí Năm <?= date('Y') ?></div>
      <div class="lum-kpi-value text-danger" style="font-size:1.3rem;"><?= e(format_currency($summary['year_expense'])) ?></div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="lum-kpi">
      <div class="lum-kpi-label">Lợi nhuận ròng Năm <?= date('Y') ?></div>
      <div class="lum-kpi-value text-success" style="font-size:1.3rem;"><?= e(format_currency($summary['year_profit'])) ?></div>
    </div>
  </div>
</div>

<div class="row g-4 mb-4">
  <div class="col-lg-8">
    <div class="lum-card h-100">
      <div class="lum-card-header">
        <h6 class="mb-0 font-serif fw-bold" style="color:var(--lum-navy);">Báo cáo Doanh thu & Chi phí theo Tháng (<?= $chartYear ?>)</h6>
      </div>
      <div class="lum-card-body">
        <canvas id="financeChart" height="110"></canvas>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="lum-card h-100">
      <div class="lum-card-header">
        <h6 class="mb-0 font-serif fw-bold" style="color:var(--lum-navy);">Cơ cấu Doanh thu</h6>
      </div>
      <div class="lum-card-body">
        <canvas id="mixChart" height="190"></canvas>
        <div class="mt-3 small">
          <div class="d-flex justify-content-between"><span>Hôm nay:</span><strong><?= e(format_currency($summary['today'])) ?></strong></div>
          <div class="d-flex justify-content-between"><span>Tháng này:</span><strong><?= e(format_currency($summary['month'])) ?></strong></div>
          <div class="d-flex justify-content-between"><span>Lợi nhuận tháng:</span><strong class="text-success"><?= e(format_currency($summary['month_profit'])) ?></strong></div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="row g-4">
  <!-- Payments -->
  <div class="col-lg-7">
    <div class="lum-card">
      <div class="lum-card-header">
        <h6 class="mb-0 font-serif fw-bold" style="color:var(--lum-navy);">Quản lý Các khoản Thanh toán</h6>
      </div>
      <div class="table-responsive">
        <table class="table lum-table">
          <thead><tr><th>Mã GD</th><th>Khách / Đơn</th><th>Số tiền</th><th>Phương thức</th><th>Trạng thái</th><th class="no-print"></th></tr></thead>
          <tbody>
            <?php foreach ($payments as $p): ?>
              <tr>
                <td class="fw-semibold"><?= e($p['payment_code']) ?></td>
                <td><?= e($p['customer_name']) ?><div class="small text-muted"><?= e($p['booking_code']) ?></div></td>
                <td class="fw-bold"><?= e(format_currency($p['amount'])) ?></td>
                <td class="small"><?= $p['payment_method'] === 'cash' ? 'Tiền mặt' : 'Chuyển khoản' ?></td>
                <td><?= status_badge($p['status'], 'payment') ?></td>
                <td class="text-end no-print">
                  <?php if ($p['status'] === 'pending'): ?>
                    <form method="POST" class="d-inline">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="update_payment">
                      <input type="hidden" name="payment_id" value="<?= (int)$p['id'] ?>">
                      <input type="hidden" name="status" value="completed">
                      <button class="btn btn-sm btn-success">Duyệt</button>
                    </form>
                  <?php elseif ($p['status'] === 'completed'): ?>
                    <form method="POST" class="d-inline" onsubmit="return confirm('Đánh dấu hoàn tiền giao dịch này?')">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="update_payment">
                      <input type="hidden" name="payment_id" value="<?= (int)$p['id'] ?>">
                      <input type="hidden" name="status" value="refunded">
                      <button class="btn btn-sm btn-outline-secondary">Hoàn tiền</button>
                    </form>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Expenses -->
  <div class="col-lg-5">
    <div class="lum-card">
      <div class="lum-card-header">
        <h6 class="mb-0 font-serif fw-bold" style="color:var(--lum-navy);">Chi phí Hoạt động</h6>
      </div>
      <div class="table-responsive">
        <table class="table lum-table">
          <thead><tr><th>Khoản chi</th><th>Ngày</th><th>Số tiền</th><th class="no-print"></th></tr></thead>
          <tbody>
            <?php foreach ($expenses as $ex): ?>
              <tr>
                <td>
                  <div class="fw-semibold"><?= e($ex['title']) ?></div>
                  <div class="small text-muted"><?= e(expense_category_label($ex['category'])) ?></div>
                </td>
                <td class="small"><?= e(format_date($ex['expense_date'])) ?></td>
                <td class="fw-bold text-danger"><?= e(format_currency($ex['amount'])) ?></td>
                <td class="text-end no-print">
                  <form method="POST" class="d-inline" onsubmit="return confirm('Xóa khoản chi phí này?')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete_expense">
                    <input type="hidden" name="expense_id" value="<?= (int)$ex['id'] ?>">
                    <button class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-trash"></i></button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Add Expense Modal -->
<div class="modal fade" id="addExpenseModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" class="modal-content">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="create_expense">
      <div class="modal-header">
        <h6 class="modal-title font-serif fw-bold">Ghi nhận Chi phí Hoạt động</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3"><label class="form-label small fw-semibold">Tên khoản chi *</label><input type="text" name="title" class="form-control" required></div>
        <div class="row g-2 mb-3">
          <div class="col-6">
            <label class="form-label small fw-semibold">Danh mục</label>
            <select name="category" class="form-select">
              <option value="utilities">Điện, nước & Tiện ích</option>
              <option value="maintenance">Bảo trì thiết bị</option>
              <option value="supplies">Vật tư & Amenities</option>
              <option value="salary">Lương & Thưởng</option>
              <option value="marketing">Marketing</option>
              <option value="other">Khác</option>
            </select>
          </div>
          <div class="col-6"><label class="form-label small fw-semibold">Ngày chi *</label><input type="date" name="expense_date" class="form-control" value="<?= date('Y-m-d') ?>" required></div>
        </div>
        <div class="mb-3"><label class="form-label small fw-semibold">Số tiền (VNĐ) *</label><input type="number" name="amount" class="form-control" min="1000" step="10000" required></div>
        <div><label class="form-label small fw-semibold">Mô tả chi tiết</label><textarea name="description" class="form-control" rows="2"></textarea></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal">Đóng</button>
        <button type="submit" class="btn btn-sm btn-lum-primary px-4">Lưu Chi phí</button>
      </div>
    </form>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  new Chart(document.getElementById('financeChart'), {
    type: 'line',
    data: {
      labels: <?= json_encode($monthlySeries['labels']) ?>,
      datasets: [
        {
          label: 'Doanh thu (VNĐ)',
          data: <?= json_encode($monthlySeries['revenues']) ?>,
          borderColor: '#0F1C2E',
          backgroundColor: 'rgba(15,28,46,0.1)',
          fill: true,
          tension: 0.3
        },
        {
          label: 'Chi phí (VNĐ)',
          data: <?= json_encode($monthlySeries['expenses']) ?>,
          borderColor: '#C9A96E',
          backgroundColor: 'rgba(201,169,110,0.15)',
          fill: true,
          tension: 0.3
        }
      ]
    },
    options: { responsive: true, scales: { y: { beginAtZero: true } } }
  });

  new Chart(document.getElementById('mixChart'), {
    type: 'pie',
    data: {
      labels: ['Tiền phòng', 'Dịch vụ'],
      datasets: [{
        data: [<?= (float)$summary['room_revenue'] ?>, <?= (float)$summary['service_revenue'] ?>],
        backgroundColor: ['#0F1C2E', '#C9A96E']
      }]
    },
    options: { responsive: true, plugins: { legend: { position: 'bottom' } } }
  });
});
</script>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
