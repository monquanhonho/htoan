<?php
/**
 * Staff: Equipment Maintenance & Incident Reporting
 * Lumina Hotel Management System
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';
require_role(['staff', 'admin']);

require_once dirname(__DIR__) . '/models/OperationsModel.php';
require_once dirname(__DIR__) . '/models/RoomModel.php';
require_once dirname(__DIR__) . '/models/UserModel.php';

$user = current_user();
$opsModel  = new OperationsModel();
$roomModel = new RoomModel();
$userModel = new UserModel();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('nhan-su/bao-tri.php');
    try {
        $action = $_POST['action'] ?? '';
        if ($action === 'create_maintenance') {
            $id = $opsModel->createMaintenance([
                'room_id'              => $_POST['room_id'] ?: null,
                'equipment_name'       => trim((string)$_POST['equipment_name']),
                'issue_description'    => trim((string)$_POST['issue_description']),
                'priority'             => $_POST['priority'] ?? 'medium',
                'reported_by'          => (int)$user['id'],
                'assigned_to'          => $_POST['assigned_to'] ?: null,
                'cost'                 => (float)($_POST['cost'] ?? 0),
                'set_room_maintenance' => !empty($_POST['set_room_maintenance']),
            ]);
            log_security_event((int)$user['id'], 'Ghi nhận sự cố bảo trì #' . $id, 'action', 'warning');
            set_flash('success', 'Đã ghi nhận sự cố bảo trì thiết bị.');
        } elseif ($action === 'update_maintenance') {
            $id = (int)$_POST['maintenance_id'];
            $status = (string)$_POST['status'];
            $opsModel->updateMaintenance(
                $id,
                $status,
                trim((string)($_POST['resolution_notes'] ?? '')),
                $_POST['cost'] !== '' ? (float)$_POST['cost'] : null
            );
            log_security_event((int)$user['id'], 'Cập nhật bảo trì #' . $id . ' -> ' . $status, 'action', 'info');
            set_flash('success', 'Đã cập nhật tiến độ sửa chữa bảo trì.');
        }
    } catch (Throwable $e) {
        set_flash('danger', $e->getMessage());
    }
    redirect('nhan-su/bao-tri.php');
}

$statusFilter = trim((string)($_GET['status'] ?? ''));
$items = $opsModel->getMaintenanceList($statusFilter);
$rooms = $roomModel->searchRooms([]);
$employees = $userModel->getAllEmployees();

$pageTitle = 'Quản lý Bảo trì Thiết bị';
include dirname(__DIR__) . '/includes/header.php';
?>

<div class="row g-4">
  <div class="col-lg-4">
    <div class="lum-card">
      <div class="lum-card-header">
        <h6 class="mb-0 font-serif fw-bold" style="color:var(--lum-navy);">Ghi nhận Sự cố / Bảo trì</h6>
      </div>
      <div class="lum-card-body">
        <form method="POST">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="create_maintenance">
          <div class="mb-3">
            <label class="form-label small fw-semibold">Khu vực / Phòng</label>
            <select name="room_id" class="form-select">
              <option value="">Khu vực chung (Sảnh, Hành lang...)</option>
              <?php foreach ($rooms as $r): ?>
                <option value="<?= (int)$r['id'] ?>">Phòng <?= e($r['room_number']) ?> (Tầng <?= (int)$r['floor'] ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Tên thiết bị *</label>
            <input type="text" name="equipment_name" class="form-control" placeholder="VD: Điều hòa, Khóa từ, Vòi sen..." required>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Mô tả sự cố *</label>
            <textarea name="issue_description" class="form-control" rows="3" required></textarea>
          </div>
          <div class="row g-2 mb-3">
            <div class="col-6">
              <label class="form-label small fw-semibold">Mức độ ưu tiên</label>
              <select name="priority" class="form-select">
                <option value="low">Thấp</option>
                <option value="medium" selected>Trung bình</option>
                <option value="high">Cao</option>
                <option value="urgent">Khẩn cấp</option>
              </select>
            </div>
            <div class="col-6">
              <label class="form-label small fw-semibold">Chi phí dự kiến</label>
              <input type="number" name="cost" class="form-control" value="0" min="0" step="50000">
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Phân công kỹ thuật viên</label>
            <select name="assigned_to" class="form-select">
              <option value="">-- Chưa chỉ định --</option>
              <?php foreach ($employees as $emp): ?>
                <option value="<?= (int)$emp['id'] ?>"><?= e($emp['full_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" name="set_room_maintenance" id="setMaint" value="1">
            <label class="form-check-label small" for="setMaint">Chuyển phòng sang trạng thái "Đang bảo trì"</label>
          </div>
          <button type="submit" class="btn btn-lum-primary w-100 rounded-pill">Ghi nhận Sự cố</button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-8">
    <div class="lum-card">
      <div class="lum-card-header">
        <h6 class="mb-0 font-serif fw-bold" style="color:var(--lum-navy);">Danh sách Bảo trì Thiết bị</h6>
        <form method="GET">
          <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
            <option value="">Tất cả trạng thái</option>
            <option value="reported" <?= $statusFilter === 'reported' ? 'selected' : '' ?>>Mới ghi nhận</option>
            <option value="in_progress" <?= $statusFilter === 'in_progress' ? 'selected' : '' ?>>Đang sửa chữa</option>
            <option value="resolved" <?= $statusFilter === 'resolved' ? 'selected' : '' ?>>Đã khắc phục</option>
          </select>
        </form>
      </div>
      <div class="table-responsive">
        <table class="table lum-table">
          <thead>
            <tr>
              <th>Thiết bị & Vị trí</th>
              <th>Mô tả sự cố</th>
              <th>Ưu tiên</th>
              <th>Chi phí</th>
              <th>Trạng thái</th>
              <th class="text-end">Cập nhật</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($items)): ?>
              <tr><td colspan="6" class="text-center py-4 text-muted">Không có phiếu bảo trì nào.</td></tr>
            <?php else: ?>
              <?php foreach ($items as $m): ?>
                <tr>
                  <td>
                    <div class="fw-bold"><?= e($m['equipment_name']) ?></div>
                    <div class="small text-muted"><?= $m['room_number'] ? 'Phòng ' . e($m['room_number']) : 'Khu vực chung' ?></div>
                  </td>
                  <td class="small">
                    <?= e($m['issue_description']) ?>
                    <?php if ($m['resolution_notes']): ?><div class="text-success mt-1"><i class="fa-solid fa-wrench me-1"></i><?= e($m['resolution_notes']) ?></div><?php endif; ?>
                  </td>
                  <td><?= status_badge($m['priority'], 'priority') ?></td>
                  <td class="small fw-semibold"><?= e(format_currency($m['cost'])) ?></td>
                  <td><?= status_badge($m['status'], 'maintenance') ?></td>
                  <td class="text-end">
                    <button class="btn btn-sm btn-lum-outline" data-bs-toggle="modal" data-bs-target="#maintModal<?= (int)$m['id'] ?>">Xử lý</button>
                  </td>
                </tr>

                <div class="modal fade" id="maintModal<?= (int)$m['id'] ?>" tabindex="-1">
                  <div class="modal-dialog">
                    <form method="POST" class="modal-content">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="update_maintenance">
                      <input type="hidden" name="maintenance_id" value="<?= (int)$m['id'] ?>">
                      <div class="modal-header">
                        <h6 class="modal-title font-serif fw-bold">Cập nhật Bảo trì: <?= e($m['equipment_name']) ?></h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                      </div>
                      <div class="modal-body">
                        <div class="mb-3">
                          <label class="form-label small fw-semibold">Trạng thái sửa chữa</label>
                          <select name="status" class="form-select">
                            <option value="reported" <?= $m['status'] === 'reported' ? 'selected' : '' ?>>Mới ghi nhận</option>
                            <option value="in_progress" <?= $m['status'] === 'in_progress' ? 'selected' : '' ?>>Đang sửa chữa</option>
                            <option value="resolved" <?= $m['status'] === 'resolved' ? 'selected' : '' ?>>Đã khắc phục (Phòng về Trống)</option>
                            <option value="cancelled" <?= $m['status'] === 'cancelled' ? 'selected' : '' ?>>Hủy</option>
                          </select>
                        </div>
                        <div class="mb-3">
                          <label class="form-label small fw-semibold">Chi phí thực tế (VNĐ)</label>
                          <input type="number" name="cost" class="form-control" value="<?= (int)$m['cost'] ?>" min="0" step="10000">
                        </div>
                        <div>
                          <label class="form-label small fw-semibold">Ghi chú kết quả sửa chữa</label>
                          <textarea name="resolution_notes" class="form-control" rows="2"><?= e($m['resolution_notes']) ?></textarea>
                        </div>
                      </div>
                      <div class="modal-footer">
                        <button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal">Đóng</button>
                        <button type="submit" class="btn btn-sm btn-lum-primary">Lưu cập nhật</button>
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
  </div>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
