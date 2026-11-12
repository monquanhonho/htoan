<?php
/**
 * Admin: Employee Management & Task Assignment
 * Lumina Hotel Management System
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';
require_role(['admin']);

require_once dirname(__DIR__) . '/models/UserModel.php';
require_once dirname(__DIR__) . '/models/OperationsModel.php';

$user = current_user();
$userModel = new UserModel();
$opsModel  = new OperationsModel();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('quan-ly/nhan-su.php');
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'create_employee') {
            $username = trim((string)$_POST['username']);
            $email    = trim((string)$_POST['email']);
            $password = (string)$_POST['password'];
            if (strlen($password) < 6) {
                throw new RuntimeException('Mật khẩu phải ít nhất 6 ký tự.');
            }
            if ($userModel->existsUsernameOrEmail($username, $email)) {
                throw new RuntimeException('Tên đăng nhập hoặc email đã tồn tại.');
            }
            $userModel->createEmployee([
                'full_name'     => trim((string)$_POST['full_name']),
                'username'      => $username,
                'email'         => $email,
                'phone'         => trim((string)$_POST['phone']),
                'password'      => $password,
                'employee_code' => trim((string)($_POST['employee_code'] ?? '')),
                'position'      => trim((string)$_POST['position']),
                'department'    => trim((string)$_POST['department']),
                'hire_date'     => $_POST['hire_date'] ?: date('Y-m-d'),
                'salary'        => (float)$_POST['salary'],
                'shift'         => $_POST['shift'] ?: 'full',
            ]);
            log_security_event((int)$user['id'], 'Thêm nhân viên mới: ' . $_POST['full_name'], 'action', 'info');
            set_flash('success', 'Đã thêm nhân viên mới.');
        } elseif ($action === 'update_employee') {
            $empId = (int)$_POST['employee_id'];
            $userModel->updateEmployee($empId, [
                'full_name'    => trim((string)$_POST['full_name']),
                'email'        => trim((string)$_POST['email']),
                'phone'        => trim((string)$_POST['phone']),
                'position'     => trim((string)$_POST['position']),
                'department'   => trim((string)$_POST['department']),
                'hire_date'    => $_POST['hire_date'],
                'salary'       => (float)$_POST['salary'],
                'shift'        => $_POST['shift'],
                'status'       => $_POST['status'],
                'new_password' => (string)($_POST['new_password'] ?? ''),
            ]);
            log_security_event((int)$user['id'], 'Cập nhật thông tin nhân viên #' . $empId, 'action', 'info');
            set_flash('success', 'Đã cập nhật thông tin nhân viên.');
        } elseif ($action === 'toggle_lock') {
            $uid = (int)$_POST['user_id'];
            $newStatus = $_POST['new_status'] === 'locked' ? 'locked' : 'active';
            $userModel->toggleUserStatus($uid, $newStatus);
            log_security_event((int)$user['id'], ($newStatus === 'locked' ? 'Khóa' : 'Mở khóa') . ' tài khoản nhân viên #' . $uid, 'action', 'warning');
            set_flash('success', 'Đã cập nhật trạng thái tài khoản nhân viên.');
        } elseif ($action === 'delete_employee') {
            $empId = (int)$_POST['employee_id'];
            $userModel->deleteEmployee($empId);
            log_security_event((int)$user['id'], 'Xóa nhân viên #' . $empId, 'action', 'warning');
            set_flash('success', 'Đã xóa nhân viên.');
        } elseif ($action === 'assign_task') {
            $opsModel->createTask([
                'title'       => trim((string)$_POST['title']),
                'description' => trim((string)$_POST['description']),
                'assigned_to' => (int)$_POST['assigned_to'],
                'assigned_by' => (int)$user['id'],
                'priority'    => $_POST['priority'] ?? 'medium',
                'due_date'    => $_POST['due_date'] ?: date('Y-m-d'),
                'notes'       => trim((string)($_POST['notes'] ?? '')),
            ]);
            log_security_event((int)$user['id'], 'Phân công công việc: ' . $_POST['title'], 'action', 'info');
            set_flash('success', 'Đã giao công việc cho nhân viên.');
        }
    } catch (Throwable $e) {
        set_flash('danger', $e->getMessage());
    }
    redirect('quan-ly/nhan-su.php');
}

$search    = trim((string)($_GET['search'] ?? ''));
$employees = $userModel->getAllEmployees($search);
$tasks     = $opsModel->getTasks(null);

$pageTitle = 'Quản lý Nhân sự & Phân công';
include dirname(__DIR__) . '/includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
  <form method="GET" class="d-flex gap-2">
    <input type="text" name="search" class="form-control form-control-sm" style="width:260px;" placeholder="Tìm tên, mã NV, chức vụ..." value="<?= e($search) ?>">
    <button class="btn btn-sm btn-lum-primary">Tìm</button>
  </form>
  <div class="d-flex gap-2">
    <button class="btn btn-sm btn-lum-outline rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#assignTaskModal">
      <i class="fa-solid fa-list-check me-1"></i>Phân công Công việc
    </button>
    <button class="btn btn-sm btn-lum-gold rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#addEmpModal">
      <i class="fa-solid fa-user-plus me-1"></i>Thêm Nhân viên
    </button>
  </div>
</div>

<!-- Employees Table -->
<div class="lum-card mb-4">
  <div class="lum-card-header">
    <h6 class="mb-0 font-serif fw-bold" style="color:var(--lum-navy);">Danh sách Nhân sự Khách sạn</h6>
    <span class="badge-lux badge-lux-navy"><?= count($employees) ?> nhân viên</span>
  </div>
  <div class="table-responsive">
    <table class="table lum-table">
      <thead>
        <tr>
          <th>Mã NV</th>
          <th>Họ tên & Liên hệ</th>
          <th>Chức vụ & Bộ phận</th>
          <th>Ca làm / Lương</th>
          <th>Công việc đang làm</th>
          <th>Tài khoản</th>
          <th class="text-end">Thao tác</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($employees as $emp): ?>
          <tr>
            <td class="fw-bold"><?= e($emp['employee_code']) ?></td>
            <td>
              <div class="fw-semibold"><?= e($emp['full_name']) ?></div>
              <div class="small text-muted"><?= e($emp['email']) ?> · <?= e($emp['phone']) ?></div>
            </td>
            <td>
              <div class="fw-semibold"><?= e($emp['position']) ?></div>
              <div class="small text-muted"><?= e($emp['department']) ?></div>
            </td>
            <td class="small">
              Ca: <strong><?= e(ucfirst($emp['shift'])) ?></strong>
              <div><?= e(format_currency($emp['salary'])) ?></div>
            </td>
            <td><span class="badge bg-light text-dark border"><?= (int)$emp['active_tasks'] ?> việc</span></td>
            <td><?= status_badge($emp['user_status'], 'user') ?></td>
            <td class="text-end text-nowrap">
              <button class="btn btn-sm btn-lum-outline" data-bs-toggle="modal" data-bs-target="#editEmpModal<?= (int)$emp['id'] ?>" title="Sửa">
                <i class="fa-solid fa-pen"></i>
              </button>
              <form method="POST" class="d-inline">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="toggle_lock">
                <input type="hidden" name="user_id" value="<?= (int)$emp['user_id'] ?>">
                <input type="hidden" name="new_status" value="<?= $emp['user_status'] === 'active' ? 'locked' : 'active' ?>">
                <button class="btn btn-sm <?= $emp['user_status'] === 'active' ? 'btn-outline-warning' : 'btn-outline-success' ?>"
                        title="<?= $emp['user_status'] === 'active' ? 'Khóa tài khoản' : 'Mở khóa' ?>">
                  <i class="fa-solid <?= $emp['user_status'] === 'active' ? 'fa-lock' : 'fa-lock-open' ?>"></i>
                </button>
              </form>
              <form method="POST" class="d-inline" onsubmit="return confirm('Xóa tài khoản nhân viên này?')">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete_employee">
                <input type="hidden" name="employee_id" value="<?= (int)$emp['id'] ?>">
                <button class="btn btn-sm btn-outline-danger" title="Xóa"><i class="fa-solid fa-trash"></i></button>
              </form>
            </td>
          </tr>

          <!-- Edit Employee Modal -->
          <div class="modal fade" id="editEmpModal<?= (int)$emp['id'] ?>" tabindex="-1">
            <div class="modal-dialog modal-lg">
              <form method="POST" class="modal-content">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update_employee">
                <input type="hidden" name="employee_id" value="<?= (int)$emp['id'] ?>">
                <div class="modal-header">
                  <h6 class="modal-title font-serif fw-bold">Cập nhật Nhân viên: <?= e($emp['full_name']) ?></h6>
                  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                  <div class="row g-3">
                    <div class="col-md-6"><label class="form-label small fw-semibold">Họ và tên</label><input type="text" name="full_name" class="form-control" value="<?= e($emp['full_name']) ?>" required></div>
                    <div class="col-md-6"><label class="form-label small fw-semibold">Email</label><input type="email" name="email" class="form-control" value="<?= e($emp['email']) ?>" required></div>
                    <div class="col-md-6"><label class="form-label small fw-semibold">Số điện thoại</label><input type="text" name="phone" class="form-control" value="<?= e($emp['phone']) ?>"></div>
                    <div class="col-md-6"><label class="form-label small fw-semibold">Chức vụ</label><input type="text" name="position" class="form-control" value="<?= e($emp['position']) ?>" required></div>
                    <div class="col-md-6"><label class="form-label small fw-semibold">Bộ phận</label><input type="text" name="department" class="form-control" value="<?= e($emp['department']) ?>" required></div>
                    <div class="col-md-6"><label class="form-label small fw-semibold">Mức lương (VNĐ)</label><input type="number" name="salary" class="form-control" value="<?= (int)$emp['salary'] ?>" min="0" step="500000"></div>
                    <div class="col-md-4">
                      <label class="form-label small fw-semibold">Ca làm</label>
                      <select name="shift" class="form-select">
                        <option value="morning" <?= $emp['shift'] === 'morning' ? 'selected' : '' ?>>Sáng</option>
                        <option value="afternoon" <?= $emp['shift'] === 'afternoon' ? 'selected' : '' ?>>Chiều</option>
                        <option value="night" <?= $emp['shift'] === 'night' ? 'selected' : '' ?>>Đêm</option>
                        <option value="full" <?= $emp['shift'] === 'full' ? 'selected' : '' ?>>Toàn thời gian</option>
                      </select>
                    </div>
                    <div class="col-md-4"><label class="form-label small fw-semibold">Ngày vào làm</label><input type="date" name="hire_date" class="form-control" value="<?= e($emp['hire_date']) ?>"></div>
                    <div class="col-md-4">
                      <label class="form-label small fw-semibold">Trạng thái công tác</label>
                      <select name="status" class="form-select">
                        <option value="active" <?= $emp['status'] === 'active' ? 'selected' : '' ?>>Đang làm</option>
                        <option value="on_leave" <?= $emp['status'] === 'on_leave' ? 'selected' : '' ?>>Nghỉ phép</option>
                        <option value="inactive" <?= $emp['status'] === 'inactive' ? 'selected' : '' ?>>Ngừng làm</option>
                      </select>
                    </div>
                    <div class="col-12"><label class="form-label small fw-semibold">Đặt mật khẩu mới (bỏ trống nếu giữ nguyên)</label><input type="password" name="new_password" class="form-control" minlength="6"></div>
                  </div>
                </div>
                <div class="modal-footer">
                  <button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal">Đóng</button>
                  <button type="submit" class="btn btn-sm btn-lum-primary px-4">Lưu thay đổi</button>
                </div>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Task Tracking Table -->
<div class="lum-card">
  <div class="lum-card-header">
    <h6 class="mb-0 font-serif fw-bold" style="color:var(--lum-navy);">Theo dõi Tiến độ Công việc Nhân sự</h6>
  </div>
  <div class="table-responsive">
    <table class="table lum-table">
      <thead><tr><th>Công việc</th><th>Nhân viên phụ trách</th><th>Ưu tiên</th><th>Hạn</th><th>Trạng thái</th></tr></thead>
      <tbody>
        <?php foreach ($tasks as $t): ?>
          <tr>
            <td><div class="fw-semibold"><?= e($t['title']) ?></div><div class="small text-muted"><?= e($t['description']) ?></div></td>
            <td class="small"><?= e($t['assigned_to_name']) ?> (<?= e($t['employee_code']) ?>)</td>
            <td><?= status_badge($t['priority'], 'priority') ?></td>
            <td class="small"><?= e(format_date($t['due_date'])) ?></td>
            <td><?= status_badge($t['status'], 'task') ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add Employee Modal -->
<div class="modal fade" id="addEmpModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <form method="POST" class="modal-content">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="create_employee">
      <div class="modal-header">
        <h6 class="modal-title font-serif fw-bold">Thêm Nhân viên mới</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-md-6"><label class="form-label small fw-semibold">Họ và tên *</label><input type="text" name="full_name" class="form-control" required></div>
          <div class="col-md-6"><label class="form-label small fw-semibold">Tên đăng nhập *</label><input type="text" name="username" class="form-control" required></div>
          <div class="col-md-6"><label class="form-label small fw-semibold">Email *</label><input type="email" name="email" class="form-control" required></div>
          <div class="col-md-6"><label class="form-label small fw-semibold">Mật khẩu *</label><input type="password" name="password" class="form-control" minlength="6" required></div>
          <div class="col-md-4"><label class="form-label small fw-semibold">Số điện thoại</label><input type="text" name="phone" class="form-control"></div>
          <div class="col-md-4"><label class="form-label small fw-semibold">Chức vụ *</label><input type="text" name="position" class="form-control" placeholder="Lễ tân / Buồng phòng..." required></div>
          <div class="col-md-4"><label class="form-label small fw-semibold">Bộ phận *</label><input type="text" name="department" class="form-control" placeholder="Tiền sảnh / Kỹ thuật..." required></div>
          <div class="col-md-4"><label class="form-label small fw-semibold">Mã nhân viên</label><input type="text" name="employee_code" class="form-control" placeholder="Tự động nếu trống"></div>
          <div class="col-md-4"><label class="form-label small fw-semibold">Lương cơ bản</label><input type="number" name="salary" class="form-control" value="12000000" step="500000"></div>
          <div class="col-md-4"><label class="form-label small fw-semibold">Ca làm</label><select name="shift" class="form-select"><option value="full">Toàn thời gian</option><option value="morning">Sáng</option><option value="afternoon">Chiều</option><option value="night">Đêm</option></select></div>
          <input type="hidden" name="hire_date" value="<?= date('Y-m-d') ?>">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal">Đóng</button>
        <button type="submit" class="btn btn-sm btn-lum-gold px-4">Tạo Tài khoản Nhân sự</button>
      </div>
    </form>
  </div>
</div>

<!-- Assign Task Modal -->
<div class="modal fade" id="assignTaskModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" class="modal-content">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="assign_task">
      <div class="modal-header">
        <h6 class="modal-title font-serif fw-bold">Phân công Công việc cho Nhân viên</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3"><label class="form-label small fw-semibold">Tiêu đề công việc *</label><input type="text" name="title" class="form-control" required></div>
        <div class="mb-3">
          <label class="form-label small fw-semibold">Nhân viên thực hiện *</label>
          <select name="assigned_to" class="form-select" required>
            <?php foreach ($employees as $emp): ?>
              <option value="<?= (int)$emp['id'] ?>"><?= e($emp['full_name']) ?> — <?= e($emp['position']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="row g-2 mb-3">
          <div class="col-6">
            <label class="form-label small fw-semibold">Mức ưu tiên</label>
            <select name="priority" class="form-select"><option value="low">Thấp</option><option value="medium" selected>Trung bình</option><option value="high">Cao</option><option value="urgent">Khẩn cấp</option></select>
          </div>
          <div class="col-6"><label class="form-label small fw-semibold">Hạn hoàn thành</label><input type="date" name="due_date" class="form-control" value="<?= date('Y-m-d') ?>"></div>
        </div>
        <div><label class="form-label small fw-semibold">Mô tả chi tiết</label><textarea name="description" class="form-control" rows="2"></textarea></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal">Đóng</button>
        <button type="submit" class="btn btn-sm btn-lum-primary px-4">Giao việc</button>
      </div>
    </form>
  </div>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
