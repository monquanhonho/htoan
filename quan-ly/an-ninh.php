<?php
/**
 * Admin: Security Monitoring, Login History & Incident Management
 * Lumina Hotel Management System
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';
require_role(['admin']);

require_once dirname(__DIR__) . '/models/OperationsModel.php';

$user = current_user();
$opsModel = new OperationsModel();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('quan-ly/an-ninh.php');
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'report_incident') {
            $title = trim((string)$_POST['action_title']);
            $sev   = $_POST['severity'] ?? 'warning';
            $det   = trim((string)$_POST['details']);
            log_security_event((int)$user['id'], $title, 'incident', $sev, $det, 'investigating');
            set_flash('warning', 'Đã ghi nhận sự cố an ninh mới.');
        } elseif ($action === 'update_incident') {
            $logId  = (int)$_POST['log_id'];
            $status = (string)$_POST['status'];
            $opsModel->updateSecurityIncidentStatus($logId, $status, (int)$user['id']);
            set_flash('success', 'Đã cập nhật trạng thái xử lý sự cố.');
        }
    } catch (Throwable $e) {
        set_flash('danger', $e->getMessage());
    }
    redirect('quan-ly/an-ninh.php');
}

$logType  = trim((string)($_GET['log_type'] ?? ''));
$severity = trim((string)($_GET['severity'] ?? ''));
$search   = trim((string)($_GET['search'] ?? ''));

$logs = $opsModel->getSecurityLogs($logType, $severity, $search, 150);

$pageTitle = 'Quản lý An ninh & Nhật ký Hệ thống';
include dirname(__DIR__) . '/includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
  <form method="GET" class="d-flex flex-wrap gap-2">
    <input type="text" name="search" class="form-control form-control-sm" style="width:220px;" placeholder="Tìm hành động, IP, người dùng..." value="<?= e($search) ?>">
    <select name="log_type" class="form-select form-select-sm" style="width:160px;" onchange="this.form.submit()">
      <option value="">Tất cả loại nhật ký</option>
      <option value="login" <?= $logType === 'login' ? 'selected' : '' ?>>Lịch sử đăng nhập</option>
      <option value="action" <?= $logType === 'action' ? 'selected' : '' ?>>Hoạt động hệ thống</option>
      <option value="incident" <?= $logType === 'incident' ? 'selected' : '' ?>>Sự cố an ninh</option>
    </select>
    <select name="severity" class="form-select form-select-sm" style="width:150px;" onchange="this.form.submit()">
      <option value="">Mọi mức độ</option>
      <option value="info" <?= $severity === 'info' ? 'selected' : '' ?>>Thông tin</option>
      <option value="warning" <?= $severity === 'warning' ? 'selected' : '' ?>>Cảnh báo</option>
      <option value="critical" <?= $severity === 'critical' ? 'selected' : '' ?>>Nghiêm trọng</option>
    </select>
    <button class="btn btn-sm btn-lum-primary">Lọc</button>
  </form>

  <button class="btn btn-sm btn-danger rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#incidentModal">
    <i class="fa-solid fa-shield-virus me-1"></i>Ghi nhận Sự cố An ninh
  </button>
</div>

<div class="lum-card">
  <div class="table-responsive">
    <table class="table lum-table">
      <thead>
        <tr>
          <th>Thời gian</th>
          <th>Tài khoản</th>
          <th>Loại</th>
          <th>Mức độ</th>
          <th>Hành động & Chi tiết</th>
          <th>Địa chỉ IP</th>
          <th>Trạng thái</th>
          <th class="text-end">Xử lý</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($logs)): ?>
          <tr><td colspan="8" class="text-center py-4 text-muted">Không có nhật ký nào.</td></tr>
        <?php else: ?>
          <?php foreach ($logs as $l): ?>
            <tr>
              <td class="small text-nowrap"><?= e(format_datetime($l['created_at'])) ?></td>
              <td class="small">
                <?php if ($l['full_name']): ?>
                  <strong><?= e($l['full_name']) ?></strong>
                  <div class="text-muted"><?= e($l['role']) ?></div>
                <?php else: ?>
                  <span class="text-muted">Hệ thống / Khách</span>
                <?php endif; ?>
              </td>
              <td class="small text-uppercase fw-semibold"><?= e($l['log_type']) ?></td>
              <td><?= status_badge($l['severity'], 'severity') ?></td>
              <td>
                <div class="fw-semibold"><?= e($l['action']) ?></div>
                <?php if ($l['details']): ?><div class="small text-muted"><?= e($l['details']) ?></div><?php endif; ?>
              </td>
              <td class="small font-monospace"><?= e($l['ip_address']) ?></td>
              <td><?= status_badge($l['status'], 'security_status') ?></td>
              <td class="text-end">
                <?php if ($l['log_type'] === 'incident' || $l['severity'] !== 'info'): ?>
                  <form method="POST" class="d-inline-flex gap-1">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="update_incident">
                    <input type="hidden" name="log_id" value="<?= (int)$l['id'] ?>">
                    <select name="status" class="form-select form-select-sm" style="width:130px;">
                      <option value="logged" <?= $l['status'] === 'logged' ? 'selected' : '' ?>>Ghi nhận</option>
                      <option value="investigating" <?= $l['status'] === 'investigating' ? 'selected' : '' ?>>Điều tra</option>
                      <option value="resolved" <?= $l['status'] === 'resolved' ? 'selected' : '' ?>>Đã xử lý</option>
                    </select>
                    <button class="btn btn-sm btn-lum-primary">Lưu</button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Incident Modal -->
<div class="modal fade" id="incidentModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" class="modal-content">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="report_incident">
      <div class="modal-header">
        <h6 class="modal-title font-serif fw-bold text-danger">Ghi nhận Sự cố An ninh</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3"><label class="form-label small fw-semibold">Tiêu đề sự cố *</label><input type="text" name="action_title" class="form-control" required placeholder="VD: Truy cập trái phép, mất thẻ từ tầng 4..."></div>
        <div class="mb-3">
          <label class="form-label small fw-semibold">Mức độ</label>
          <select name="severity" class="form-select">
            <option value="warning">Cảnh báo</option>
            <option value="critical">Nghiêm trọng</option>
          </select>
        </div>
        <div><label class="form-label small fw-semibold">Mô tả chi tiết *</label><textarea name="details" class="form-control" rows="3" required></textarea></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal">Đóng</button>
        <button type="submit" class="btn btn-sm btn-danger px-4">Ghi nhận Sự cố</button>
      </div>
    </form>
  </div>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
