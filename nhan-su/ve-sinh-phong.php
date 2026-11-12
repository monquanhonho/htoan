<?php
/**
 * Staff: Room Cleaning Management
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
    require_csrf('nhan-su/ve-sinh-phong.php');
    try {
        $action = $_POST['action'] ?? '';
        if ($action === 'update_status') {
            $cleaningId = (int)$_POST['cleaning_id'];
            $status     = (string)$_POST['status'];
            $empId      = !empty($user['employee_id']) ? (int)$user['employee_id'] : null;
            $opsModel->updateCleaningStatus($cleaningId, $status, $empId, trim((string)($_POST['notes'] ?? '')));
            log_security_event((int)$user['id'], 'Cập nhật vệ sinh phòng #' . $cleaningId . ' -> ' . $status, 'action', 'info');
            set_flash('success', $status === 'completed'
                ? 'Đã hoàn tất vệ sinh! Phòng đã tự động chuyển sang trạng thái Trống sẵn sàng đón khách.'
                : 'Đã cập nhật trạng thái vệ sinh phòng.');
        } elseif ($action === 'create_cleaning') {
            $opsModel->createCleaning(
                (int)$_POST['room_id'],
                $_POST['cleaning_type'] ?? 'daily',
                !empty($_POST['assigned_to']) ? (int)$_POST['assigned_to'] : null,
                trim((string)($_POST['notes'] ?? ''))
            );
            set_flash('success', 'Đã tạo phiếu vệ sinh phòng mới.');
        }
    } catch (Throwable $e) {
        set_flash('danger', $e->getMessage());
    }
    redirect('nhan-su/ve-sinh-phong.php');
}

$statusFilter = trim((string)($_GET['status'] ?? ''));
$cleanings = $opsModel->getCleaningList($statusFilter);
$rooms     = $roomModel->searchRooms([]);
$employees = $userModel->getAllEmployees();

$pageTitle = 'Quản lý Vệ sinh Phòng';
include dirname(__DIR__) . '/includes/header.php';
?>

<div class="row g-4">
  <div class="col-lg-4">
    <div class="lum-card">
      <div class="lum-card-header">
        <h6 class="mb-0 font-serif fw-bold" style="color:var(--lum-navy);">Tạo Yêu cầu Vệ sinh</h6>
      </div>
      <div class="lum-card-body">
        <form method="POST">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="create_cleaning">
          <div class="mb-3">
            <label class="form-label small fw-semibold">Chọn phòng *</label>
            <select name="room_id" class="form-select" required>
              <?php foreach ($rooms as $r): ?>
                <option value="<?= (int)$r['id'] ?>">Phòng <?= e($r['room_number']) ?> (<?= e($r['type_name']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Loại vệ sinh</label>
            <select name="cleaning_type" class="form-select">
              <option value="daily">Dọn phòng hàng ngày</option>
              <option value="checkout">Vệ sinh sau trả phòng</option>
              <option value="deep">Tổng vệ sinh định kỳ</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Phân công nhân viên</label>
            <select name="assigned_to" class="form-select">
              <option value="">-- Tự động / Chưa chỉ định --</option>
              <?php foreach ($employees as $emp): ?>
                <option value="<?= (int)$emp['id'] ?>"><?= e($emp['full_name']) ?> (<?= e($emp['position']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Ghi chú</label>
            <textarea name="notes" class="form-control" rows="2" placeholder="Thay ga giường, bổ sung minibar..."></textarea>
          </div>
          <button type="submit" class="btn btn-lum-primary w-100 rounded-pill">Tạo Phiếu Vệ sinh</button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-8">
    <div class="lum-card">
      <div class="lum-card-header">
        <h6 class="mb-0 font-serif fw-bold" style="color:var(--lum-navy);">Danh sách Phòng Vệ sinh</h6>
        <form method="GET">
          <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
            <option value="">Tất cả</option>
            <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Chờ vệ sinh</option>
            <option value="in_progress" <?= $statusFilter === 'in_progress' ? 'selected' : '' ?>>Đang dọn phòng</option>
            <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : '' ?>>Đã sạch</option>
          </select>
        </form>
      </div>
      <div class="table-responsive">
        <table class="table lum-table">
          <thead>
            <tr>
              <th>Phòng</th>
              <th>Loại vệ sinh</th>
              <th>Tình trạng phòng</th>
              <th>Trạng thái dọn</th>
              <th>Ghi chú</th>
              <th class="text-end">Cập nhật</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($cleanings)): ?>
              <tr><td colspan="6" class="text-center py-4 text-muted">Không có phiếu vệ sinh nào.</td></tr>
            <?php else: ?>
              <?php foreach ($cleanings as $c): ?>
                <tr>
                  <td>
                    <div class="fw-bold">Phòng <?= e($c['room_number']) ?></div>
                    <div class="small text-muted">Tầng <?= (int)$c['floor'] ?></div>
                  </td>
                  <td class="small">
                    <?= match($c['cleaning_type']) { 'checkout' => 'Sau Check-out', 'deep' => 'Tổng vệ sinh', default => 'Hàng ngày' } ?>
                    <?php if ($c['booking_code']): ?><div class="text-muted"><?= e($c['booking_code']) ?></div><?php endif; ?>
                  </td>
                  <td><?= status_badge($c['room_status'], 'room') ?></td>
                  <td><?= status_badge($c['status'], 'cleaning') ?></td>
                  <td class="small text-muted"><?= e($c['notes'] ?: '—') ?></td>
                  <td class="text-end text-nowrap">
                    <?php if ($c['status'] !== 'completed'): ?>
                      <form method="POST" class="d-inline-flex gap-1">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="cleaning_id" value="<?= (int)$c['id'] ?>">
                        <?php if ($c['status'] === 'pending'): ?>
                          <button type="submit" name="status" value="in_progress" class="btn btn-sm btn-outline-primary">Bắt đầu</button>
                        <?php endif; ?>
                        <button type="submit" name="status" value="completed" class="btn btn-sm btn-success">
                          <i class="fa-solid fa-check me-1"></i>Hoàn tất
                        </button>
                      </form>
                    <?php else: ?>
                      <span class="small text-success"><i class="fa-solid fa-check-circle me-1"></i><?= e(format_datetime($c['completed_at'])) ?></span>
                    <?php endif; ?>
                  </td>
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
