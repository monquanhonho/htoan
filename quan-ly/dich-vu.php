<?php
/**
 * Admin: Service Catalog CRUD & Service Orders Management
 * Lumina Hotel Management System
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';
require_role(['admin']);

require_once dirname(__DIR__) . '/models/ServiceModel.php';

$user = current_user();
$serviceModel = new ServiceModel();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('quan-ly/dich-vu.php');
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'create_service') {
            $imgPath = match ($_POST['category'] ?? 'other') {
                'laundry'   => 'assets/images/services/giatuicaocap.jpg',
                'spa'       => 'assets/images/services/spacaocap1.webp',
                'transport' => 'assets/images/services/duadonxesang.jpg',
                default     => 'assets/images/services/amthucnhahang1.jpg',
            };
            if (!empty($_FILES['service_image']['name'])) {
                $uploaded = upload_image($_FILES['service_image'], 'services');
                if ($uploaded) {
                    $imgPath = $uploaded;
                }
            }
            $serviceModel->createService([
                'name'        => trim((string)$_POST['name']),
                'category'    => $_POST['category'] ?? 'other',
                'description' => trim((string)$_POST['description']),
                'price'       => (float)$_POST['price'],
                'unit'        => trim((string)$_POST['unit']),
                'image_path'  => $imgPath,
                'is_active'   => !empty($_POST['is_active']) ? 1 : 0,
            ]);
            log_security_event((int)$user['id'], 'Thêm dịch vụ mới: ' . $_POST['name'], 'action', 'info');
            set_flash('success', 'Đã thêm dịch vụ mới.');
        } elseif ($action === 'update_service') {
            $imgPath = null;
            if (!empty($_FILES['service_image']['name'])) {
                $imgPath = upload_image($_FILES['service_image'], 'services');
            }
            $serviceModel->updateService((int)$_POST['service_id'], [
                'name'        => trim((string)$_POST['name']),
                'category'    => $_POST['category'] ?? 'other',
                'description' => trim((string)$_POST['description']),
                'price'       => (float)$_POST['price'],
                'unit'        => trim((string)$_POST['unit']),
                'image_path'  => $imgPath,
                'is_active'   => !empty($_POST['is_active']) ? 1 : 0,
            ]);
            set_flash('success', 'Đã cập nhật thông tin dịch vụ.');
        } elseif ($action === 'delete_service') {
            $serviceModel->deleteService((int)$_POST['service_id']);
            set_flash('success', 'Đã xóa dịch vụ.');
        } elseif ($action === 'update_order') {
            $serviceModel->updateServiceOrderStatus((int)$_POST['order_id'], $_POST['status'], (int)$user['id']);
            set_flash('success', 'Đã cập nhật trạng thái đơn dịch vụ.');
        }
    } catch (Throwable $e) {
        set_flash('danger', $e->getMessage());
    }
    redirect('quan-ly/dich-vu.php');
}

$services = $serviceModel->getAllServices(false);
$orders   = $serviceModel->getServiceOrders([]);

$pageTitle = 'Quản lý Dịch vụ Khách sạn';
include dirname(__DIR__) . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <div class="small text-muted">Quản lý danh mục dịch vụ và các yêu cầu sử dụng dịch vụ của khách hàng.</div>
  <button class="btn btn-sm btn-lum-gold rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#addSrvModal">
    <i class="fa-solid fa-plus me-1"></i>Thêm Dịch vụ mới
  </button>
</div>

<!-- Service Catalog Table -->
<div class="lum-card mb-4">
  <div class="lum-card-header">
    <h6 class="mb-0 font-serif fw-bold" style="color:var(--lum-navy);">Danh mục Dịch vụ</h6>
  </div>
  <div class="table-responsive">
    <table class="table lum-table">
      <thead><tr><th>Ảnh</th><th>Tên dịch vụ</th><th>Nhóm</th><th>Đơn giá</th><th>Trạng thái</th><th class="text-end">Thao tác</th></tr></thead>
      <tbody>
        <?php foreach ($services as $s): ?>
          <tr>
            <td><img src="<?= e(base_url($s['image_path'] ?: 'assets/images/services/amthucnhahang1.jpg')) ?>" style="width:60px;height:40px;object-fit:cover;border-radius:6px;"></td>
            <td>
              <div class="fw-bold"><?= e($s['name']) ?></div>
              <div class="small text-muted"><?= e($s['description']) ?></div>
            </td>
            <td class="small"><?= e(service_category_label($s['category'])) ?></td>
            <td class="fw-semibold"><?= e(format_currency($s['price'])) ?> <span class="small text-muted">/ <?= e($s['unit']) ?></span></td>
            <td><?= $s['is_active'] ? '<span class="badge-lux badge-lux-success">Đang cung cấp</span>' : '<span class="badge-lux badge-lux-secondary">Tạm ẩn</span>' ?></td>
            <td class="text-end text-nowrap">
              <button class="btn btn-sm btn-lum-outline" data-bs-toggle="modal" data-bs-target="#editSrvModal<?= (int)$s['id'] ?>"><i class="fa-solid fa-pen"></i></button>
              <form method="POST" class="d-inline" onsubmit="return confirm('Xóa dịch vụ này?')">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete_service">
                <input type="hidden" name="service_id" value="<?= (int)$s['id'] ?>">
                <button class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-trash"></i></button>
              </form>
            </td>
          </tr>

          <div class="modal fade" id="editSrvModal<?= (int)$s['id'] ?>" tabindex="-1">
            <div class="modal-dialog">
              <form method="POST" enctype="multipart/form-data" class="modal-content">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update_service">
                <input type="hidden" name="service_id" value="<?= (int)$s['id'] ?>">
                <div class="modal-header"><h6 class="modal-title font-serif fw-bold">Sửa Dịch vụ</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                  <div class="mb-3"><label class="form-label small fw-semibold">Tên dịch vụ *</label><input type="text" name="name" class="form-control" value="<?= e($s['name']) ?>" required></div>
                  <div class="row g-2 mb-3">
                    <div class="col-6">
                      <label class="form-label small fw-semibold">Danh mục</label>
                      <select name="category" class="form-select">
                        <option value="dining" <?= $s['category'] === 'dining' ? 'selected' : '' ?>>Ẩm thực</option>
                        <option value="laundry" <?= $s['category'] === 'laundry' ? 'selected' : '' ?>>Giặt ủi</option>
                        <option value="spa" <?= $s['category'] === 'spa' ? 'selected' : '' ?>>Spa</option>
                        <option value="transport" <?= $s['category'] === 'transport' ? 'selected' : '' ?>>Đưa đón</option>
                        <option value="other" <?= $s['category'] === 'other' ? 'selected' : '' ?>>Khác</option>
                      </select>
                    </div>
                    <div class="col-6"><label class="form-label small fw-semibold">Đơn vị tính</label><input type="text" name="unit" class="form-control" value="<?= e($s['unit']) ?>" required></div>
                  </div>
                  <div class="mb-3"><label class="form-label small fw-semibold">Giá (VNĐ) *</label><input type="number" name="price" class="form-control" value="<?= (int)$s['price'] ?>" min="1000" step="10000" required></div>
                  <div class="mb-3"><label class="form-label small fw-semibold">Mô tả</label><textarea name="description" class="form-control" rows="2"><?= e($s['description']) ?></textarea></div>
                  <div class="mb-3"><label class="form-label small fw-semibold">Thay đổi hình ảnh</label><input type="file" name="service_image" class="form-control" accept="image/*"></div>
                  <div class="form-check"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="act<?= (int)$s['id'] ?>" <?= $s['is_active'] ? 'checked' : '' ?>><label class="form-check-label small" for="act<?= (int)$s['id'] ?>">Đang hoạt động</label></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal">Đóng</button><button type="submit" class="btn btn-sm btn-lum-primary">Lưu</button></div>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Service Orders Table -->
<div class="lum-card">
  <div class="lum-card-header">
    <h6 class="mb-0 font-serif fw-bold" style="color:var(--lum-navy);">Theo dõi Yêu cầu Sử dụng Dịch vụ</h6>
  </div>
  <div class="table-responsive">
    <table class="table lum-table">
      <thead><tr><th>Dịch vụ</th><th>Khách / Phòng</th><th>SL</th><th>Thành tiền</th><th>Trạng thái</th><th class="text-end">Cập nhật</th></tr></thead>
      <tbody>
        <?php foreach ($orders as $o): ?>
          <tr>
            <td class="fw-semibold"><?= e($o['service_name']) ?></td>
            <td class="small"><?= e($o['customer_name']) ?> · <?= e($o['booking_code']) ?> (P.<?= e($o['room_number']) ?>)</td>
            <td><?= (int)$o['quantity'] ?> <?= e($o['unit']) ?></td>
            <td class="fw-bold"><?= e(format_currency($o['total_price'])) ?></td>
            <td><?= status_badge($o['status'], 'service') ?></td>
            <td class="text-end">
              <form method="POST" class="d-inline-flex gap-1">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update_order">
                <input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>">
                <select name="status" class="form-select form-select-sm" style="width:135px;">
                  <option value="pending" <?= $o['status'] === 'pending' ? 'selected' : '' ?>>Chờ xử lý</option>
                  <option value="confirmed" <?= $o['status'] === 'confirmed' ? 'selected' : '' ?>>Tiếp nhận</option>
                  <option value="in_progress" <?= $o['status'] === 'in_progress' ? 'selected' : '' ?>>Đang thực hiện</option>
                  <option value="completed" <?= $o['status'] === 'completed' ? 'selected' : '' ?>>Hoàn thành</option>
                  <option value="cancelled" <?= $o['status'] === 'cancelled' ? 'selected' : '' ?>>Hủy</option>
                </select>
                <button class="btn btn-sm btn-lum-primary">Lưu</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add Service Modal -->
<div class="modal fade" id="addSrvModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" enctype="multipart/form-data" class="modal-content">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="create_service">
      <div class="modal-header"><h6 class="modal-title font-serif fw-bold">Thêm Dịch vụ mới</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="mb-3"><label class="form-label small fw-semibold">Tên dịch vụ *</label><input type="text" name="name" class="form-control" required></div>
        <div class="row g-2 mb-3">
          <div class="col-6">
            <label class="form-label small fw-semibold">Danh mục</label>
            <select name="category" class="form-select"><option value="dining">Ẩm thực</option><option value="laundry">Giặt ủi</option><option value="spa">Spa</option><option value="transport">Đưa đón</option><option value="other">Khác</option></select>
          </div>
          <div class="col-6"><label class="form-label small fw-semibold">Đơn vị tính</label><input type="text" name="unit" class="form-control" value="Lần" required></div>
        </div>
        <div class="mb-3"><label class="form-label small fw-semibold">Đơn giá (VNĐ) *</label><input type="number" name="price" class="form-control" min="1000" step="10000" required></div>
        <div class="mb-3"><label class="form-label small fw-semibold">Mô tả</label><textarea name="description" class="form-control" rows="2"></textarea></div>
        <div class="mb-3"><label class="form-label small fw-semibold">Ảnh minh họa</label><input type="file" name="service_image" class="form-control" accept="image/*"></div>
        <div class="form-check"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="actNew" checked><label class="form-check-label small" for="actNew">Kích hoạt ngay</label></div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal">Đóng</button><button type="submit" class="btn btn-sm btn-lum-gold px-4">Thêm Dịch vụ</button></div>
    </form>
  </div>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
