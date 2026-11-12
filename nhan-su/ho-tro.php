<?php
/**
 * Staff: Customer Support Ticket Handling
 * Lumina Hotel Management System
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';
require_role(['staff', 'admin']);

require_once dirname(__DIR__) . '/models/OperationsModel.php';

$user = current_user();
$opsModel = new OperationsModel();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('nhan-su/ho-tro.php');
    try {
        $ticketId = (int)($_POST['ticket_id'] ?? 0);
        $status   = (string)($_POST['status'] ?? 'resolved');
        $response = trim((string)($_POST['staff_response'] ?? ''));

        $opsModel->respondSupportRequest($ticketId, $status, $response, (int)$user['id']);
        log_security_event((int)$user['id'], 'Xử lý yêu cầu hỗ trợ #' . $ticketId . ' -> ' . $status, 'action', 'info');
        set_flash('success', 'Đã cập nhật phản hồi hỗ trợ khách hàng.');
    } catch (Throwable $e) {
        set_flash('danger', $e->getMessage());
    }
    redirect('nhan-su/ho-tro.php');
}

$statusFilter = trim((string)($_GET['status'] ?? ''));
$tickets = $opsModel->getSupportRequests(null, $statusFilter);

$pageTitle = 'Tiếp nhận & Hỗ trợ Khách hàng';
include dirname(__DIR__) . '/includes/header.php';
?>

<div class="lum-card mb-4">
  <div class="lum-card-body d-flex flex-wrap gap-2">
    <?php
      $tabs = ['' => 'Tất cả', 'open' => 'Mới gửi', 'in_progress' => 'Đang xử lý', 'resolved' => 'Đã giải quyết', 'closed' => 'Đã đóng'];
      foreach ($tabs as $k => $lbl):
    ?>
      <a href="<?= e(base_url('nhan-su/ho-tro.php' . ($k ? '?status=' . $k : ''))) ?>"
         class="btn btn-sm rounded-pill px-3 <?= $statusFilter === $k ? 'btn-lum-primary' : 'btn-light border' ?>">
        <?= e($lbl) ?>
      </a>
    <?php endforeach; ?>
  </div>
</div>

<div class="lum-card">
  <div class="table-responsive">
    <table class="table lum-table">
      <thead>
        <tr>
          <th>Mã Ticket</th>
          <th>Khách hàng</th>
          <th>Nội dung yêu cầu</th>
          <th>Trạng thái</th>
          <th>Phản hồi</th>
          <th class="text-end">Xử lý</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($tickets)): ?>
          <tr><td colspan="6" class="text-center py-4 text-muted">Không có yêu cầu hỗ trợ nào.</td></tr>
        <?php else: ?>
          <?php foreach ($tickets as $t): ?>
            <tr>
              <td class="fw-bold"><?= e($t['ticket_code']) ?></td>
              <td>
                <div class="fw-semibold"><?= e($t['customer_name']) ?></div>
                <div class="small text-muted"><?= e($t['customer_phone']) ?></div>
                <?php if ($t['booking_code']): ?><div class="small text-primary"><?= e($t['booking_code']) ?></div><?php endif; ?>
              </td>
              <td>
                <div class="fw-semibold"><?= e($t['subject']) ?></div>
                <div class="small text-muted"><?= e($t['message']) ?></div>
              </td>
              <td><?= status_badge($t['status'], 'support') ?></td>
              <td class="small"><?= e($t['staff_response'] ?: 'Chưa phản hồi') ?></td>
              <td class="text-end">
                <button class="btn btn-sm btn-lum-gold" data-bs-toggle="modal" data-bs-target="#supModal<?= (int)$t['id'] ?>">Phản hồi</button>
              </td>
            </tr>

            <div class="modal fade" id="supModal<?= (int)$t['id'] ?>" tabindex="-1">
              <div class="modal-dialog">
                <form method="POST" class="modal-content">
                  <?= csrf_field() ?>
                  <input type="hidden" name="ticket_id" value="<?= (int)$t['id'] ?>">
                  <div class="modal-header">
                    <h6 class="modal-title font-serif fw-bold">Xử lý Ticket <?= e($t['ticket_code']) ?></h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                  </div>
                  <div class="modal-body">
                    <div class="p-2 rounded bg-light small mb-3">
                      <strong><?= e($t['subject']) ?></strong><br><?= e($t['message']) ?>
                    </div>
                    <div class="mb-3">
                      <label class="form-label small fw-semibold">Trạng thái xử lý</label>
                      <select name="status" class="form-select">
                        <option value="open" <?= $t['status'] === 'open' ? 'selected' : '' ?>>Mới gửi</option>
                        <option value="in_progress" <?= $t['status'] === 'in_progress' ? 'selected' : '' ?>>Đang xử lý</option>
                        <option value="resolved" <?= $t['status'] === 'resolved' ? 'selected' : '' ?>>Đã giải quyết</option>
                        <option value="closed" <?= $t['status'] === 'closed' ? 'selected' : '' ?>>Đã đóng</option>
                      </select>
                    </div>
                    <div>
                      <label class="form-label small fw-semibold">Nội dung phản hồi tới khách hàng *</label>
                      <textarea name="staff_response" class="form-control" rows="3" required><?= e($t['staff_response']) ?></textarea>
                    </div>
                  </div>
                  <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal">Đóng</button>
                    <button type="submit" class="btn btn-sm btn-lum-primary">Lưu & Gửi phản hồi</button>
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
