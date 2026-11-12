<?php
/**
 * Staff: Assigned Tasks & History
 * Lumina Hotel Management System
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';
require_role(['staff', 'admin']);

require_once dirname(__DIR__) . '/models/OperationsModel.php';

$user = current_user();
$opsModel = new OperationsModel();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('nhan-su/cong-viec.php');
    try {
        $taskId = (int)($_POST['task_id'] ?? 0);
        $status = (string)($_POST['status'] ?? '');
        $notes  = trim((string)($_POST['notes'] ?? ''));

        $opsModel->updateTaskStatus($taskId, $status, $notes);
        log_security_event((int)$user['id'], 'Cập nhật công việc #' . $taskId . ' -> ' . $status, 'action', 'info');
        set_flash('success', 'Đã cập nhật tiến độ công việc.');
    } catch (Throwable $e) {
        set_flash('danger', $e->getMessage());
    }
    redirect('nhan-su/cong-viec.php');
}

$empId = ($user['role'] === 'staff' && !empty($user['employee_id'])) ? (int)$user['employee_id'] : null;
$statusFilter = trim((string)($_GET['status'] ?? ''));
$tasks = $opsModel->getTasks($empId, $statusFilter);

$pageTitle = 'Công việc được phân công';
include dirname(__DIR__) . '/includes/header.php';
?>

<div class="lum-card mb-4">
  <div class="lum-card-body d-flex flex-wrap gap-2">
    <?php
      $tabs = ['' => 'Tất cả', 'pending' => 'Chờ xử lý', 'in_progress' => 'Đang thực hiện', 'completed' => 'Hoàn thành'];
      foreach ($tabs as $k => $lbl):
    ?>
      <a href="<?= e(base_url('nhan-su/cong-viec.php' . ($k ? '?status=' . $k : ''))) ?>"
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
          <th>Tiêu đề & Mô tả</th>
          <th>Người giao</th>
          <th>Mức ưu tiên</th>
          <th>Hạn hoàn thành</th>
          <th>Trạng thái</th>
          <th class="text-end">Cập nhật tiến độ</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($tasks)): ?>
          <tr><td colspan="6" class="text-center py-4 text-muted">Không có công việc nào.</td></tr>
        <?php else: ?>
          <?php foreach ($tasks as $t): ?>
            <tr>
              <td>
                <div class="fw-bold"><?= e($t['title']) ?></div>
                <div class="small text-muted"><?= e($t['description']) ?></div>
                <?php if ($t['notes']): ?><div class="small text-info mt-1">Ghi chú: <?= e($t['notes']) ?></div><?php endif; ?>
              </td>
              <td class="small"><?= e($t['assigned_by_name']) ?> <div class="text-muted">Giao cho: <?= e($t['assigned_to_name']) ?></div></td>
              <td><?= status_badge($t['priority'], 'priority') ?></td>
              <td class="small"><?= e(format_date($t['due_date'])) ?></td>
              <td>
                <?= status_badge($t['status'], 'task') ?>
                <?php if ($t['completed_at']): ?><div class="small text-muted"><?= e(format_datetime($t['completed_at'])) ?></div><?php endif; ?>
              </td>
              <td class="text-end">
                <form method="POST" class="d-inline-flex gap-1">
                  <?= csrf_field() ?>
                  <input type="hidden" name="task_id" value="<?= (int)$t['id'] ?>">
                  <select name="status" class="form-select form-select-sm" style="width:140px;">
                    <option value="pending" <?= $t['status'] === 'pending' ? 'selected' : '' ?>>Chờ xử lý</option>
                    <option value="in_progress" <?= $t['status'] === 'in_progress' ? 'selected' : '' ?>>Đang làm</option>
                    <option value="completed" <?= $t['status'] === 'completed' ? 'selected' : '' ?>>Hoàn thành</option>
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
