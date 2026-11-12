<?php
/**
 * Staff: Service Request Processing
 * Lumina Hotel Management System
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';
require_role(['staff', 'admin']);

require_once dirname(__DIR__) . '/models/ServiceModel.php';

$user = current_user();
$serviceModel = new ServiceModel();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('nhan-su/dich-vu.php');
    try {
        $orderId = (int)($_POST['order_id'] ?? 0);
        $status  = (string)($_POST['status'] ?? '');
        $serviceModel->updateServiceOrderStatus($orderId, $status, (int)$user['id']);
        log_security_event((int)$user['id'], 'Cập nhật yêu cầu dịch vụ #' . $orderId . ' -> ' . $status, 'action', 'info');
        set_flash('success', 'Đã cập nhật trạng thái dịch vụ và đồng bộ hóa đơn.');
    } catch (Throwable $e) {
        set_flash('danger', $e->getMessage());
    }
    redirect('nhan-su/dich-vu.php');
}

$statusFilter = trim((string)($_GET['status'] ?? ''));
$orders = $serviceModel->getServiceOrders(['status' => $statusFilter]);

$pageTitle = 'Tiếp nhận & Xử lý Dịch vụ';
include dirname(__DIR__) . '/includes/header.php';
?>

<div class="lum-card mb-4">
  <div class="lum-card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
    <div class="d-flex flex-wrap gap-2">
      <?php
        $statuses = ['' => 'Tất cả', 'pending' => 'Chờ xử lý', 'confirmed' => 'Đã tiếp nhận', 'in_progress' => 'Đang thực hiện', 'completed' => 'Hoàn thành'];
        foreach ($statuses as $k => $lbl):
      ?>
        <a href="<?= e(base_url('nhan-su/dich-vu.php' . ($k ? '?status=' . $k : ''))) ?>"
           class="btn btn-sm rounded-pill px-3 <?= $statusFilter === $k ? 'btn-lum-primary' : 'btn-light border' ?>">
          <?= e($lbl) ?>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<div class="lum-card">
  <div class="table-responsive">
    <table class="table lum-table">
      <thead>
        <tr>
          <th>Dịch vụ</th>
          <th>Khách hàng / Phòng</th>
          <th>SL</th>
          <th>Thành tiền</th>
          <th>Giờ yêu cầu</th>
          <th>Trạng thái</th>
          <th class="text-end">Cập nhật xử lý</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($orders)): ?>
          <tr><td colspan="7" class="text-center py-4 text-muted">Không có yêu cầu dịch vụ nào.</td></tr>
        <?php else: ?>
          <?php foreach ($orders as $o): ?>
            <tr>
              <td>
                <div class="fw-semibold"><?= e($o['service_name']) ?></div>
                <?php if ($o['notes']): ?><div class="small text-muted"><?= e($o['notes']) ?></div><?php endif; ?>
              </td>
              <td>
                <div><?= e($o['customer_name']) ?></div>
                <div class="small text-muted"><?= e($o['booking_code']) ?> · P.<?= e($o['room_number']) ?></div>
              </td>
              <td><?= (int)$o['quantity'] ?> <?= e($o['unit']) ?></td>
              <td class="fw-bold"><?= e(format_currency($o['total_price'])) ?></td>
              <td class="small"><?= e(format_datetime($o['requested_time'])) ?></td>
              <td><?= status_badge($o['status'], 'service') ?></td>
              <td class="text-end text-nowrap">
                <form method="POST" class="d-inline-flex gap-1">
                  <?= csrf_field() ?>
                  <input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>">
                  <select name="status" class="form-select form-select-sm" style="width:140px;">
                    <option value="pending" <?= $o['status'] === 'pending' ? 'selected' : '' ?>>Chờ xử lý</option>
                    <option value="confirmed" <?= $o['status'] === 'confirmed' ? 'selected' : '' ?>>Tiếp nhận</option>
                    <option value="in_progress" <?= $o['status'] === 'in_progress' ? 'selected' : '' ?>>Đang thực hiện</option>
                    <option value="completed" <?= $o['status'] === 'completed' ? 'selected' : '' ?>>Hoàn thành</option>
                    <option value="cancelled" <?= $o['status'] === 'cancelled' ? 'selected' : '' ?>>Hủy</option>
                  </select>
                  <button type="submit" class="btn btn-sm btn-lum-primary">Lưu</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
