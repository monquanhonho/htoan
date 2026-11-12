<?php
/**
 * Admin: Room, Room Type & Room Image Management
 * Lumina Hotel Management System
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';
require_role(['admin']);

require_once dirname(__DIR__) . '/models/RoomModel.php';

$user = current_user();
$roomModel = new RoomModel();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('quan-ly/quan-ly-phong.php');
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'create_room') {
            $roomId = $roomModel->createRoom([
                'room_number'     => trim((string)$_POST['room_number']),
                'floor'           => (int)$_POST['floor'],
                'room_type_id'    => (int)$_POST['room_type_id'],
                'price_per_night' => (float)$_POST['price_per_night'],
                'status'          => $_POST['status'] ?? 'available',
                'description'     => trim((string)$_POST['description']),
                'amenities'       => trim((string)$_POST['amenities']),
            ]);

            if (!empty($_FILES['room_image']['name'])) {
                $path = upload_image($_FILES['room_image'], 'rooms');
                if ($path) {
                    $roomModel->addRoomImage($roomId, $path, true);
                }
            } else {
                $roomModel->addRoomImage($roomId, 'assets/images/rooms/phong1.jpg', true);
            }
            log_security_event((int)$user['id'], 'Thêm phòng mới: ' . $_POST['room_number'], 'action', 'info');
            set_flash('success', 'Đã thêm phòng mới thành công.');
        } elseif ($action === 'update_room') {
            $roomId = (int)$_POST['room_id'];
            $roomModel->updateRoom($roomId, [
                'room_number'     => trim((string)$_POST['room_number']),
                'floor'           => (int)$_POST['floor'],
                'room_type_id'    => (int)$_POST['room_type_id'],
                'price_per_night' => (float)$_POST['price_per_night'],
                'status'          => $_POST['status'] ?? 'available',
                'description'     => trim((string)$_POST['description']),
                'amenities'       => trim((string)$_POST['amenities']),
            ]);

            if (!empty($_FILES['room_image']['name'])) {
                $path = upload_image($_FILES['room_image'], 'rooms');
                if ($path) {
                    $roomModel->addRoomImage($roomId, $path, !empty($_POST['set_primary']));
                }
            }
            log_security_event((int)$user['id'], 'Cập nhật phòng #' . $roomId, 'action', 'info');
            set_flash('success', 'Đã cập nhật thông tin phòng.');
        } elseif ($action === 'delete_room') {
            $roomId = (int)$_POST['room_id'];
            $roomModel->deleteRoom($roomId);
            log_security_event((int)$user['id'], 'Xóa phòng #' . $roomId, 'action', 'warning');
            set_flash('success', 'Đã xóa phòng.');
        } elseif ($action === 'set_primary_img') {
            $roomModel->setPrimaryImage((int)$_POST['room_id'], (int)$_POST['image_id']);
            set_flash('success', 'Đã đặt ảnh đại diện cho phòng.');
        } elseif ($action === 'delete_img') {
            $roomModel->deleteRoomImage((int)$_POST['image_id']);
            set_flash('success', 'Đã xóa hình ảnh phòng.');
        } elseif ($action === 'create_type') {
            $roomModel->createRoomType([
                'name'        => trim((string)$_POST['name']),
                'base_price'  => (float)$_POST['base_price'],
                'capacity'    => (int)$_POST['capacity'],
                'bed_type'    => trim((string)$_POST['bed_type']),
                'area_sqm'    => (float)$_POST['area_sqm'],
                'description' => trim((string)$_POST['description']),
                'amenities'   => trim((string)$_POST['amenities']),
            ]);
            set_flash('success', 'Đã thêm hạng phòng mới.');
        } elseif ($action === 'update_type') {
            $roomModel->updateRoomType((int)$_POST['type_id'], [
                'name'        => trim((string)$_POST['name']),
                'base_price'  => (float)$_POST['base_price'],
                'capacity'    => (int)$_POST['capacity'],
                'bed_type'    => trim((string)$_POST['bed_type']),
                'area_sqm'    => (float)$_POST['area_sqm'],
                'description' => trim((string)$_POST['description']),
                'amenities'   => trim((string)$_POST['amenities']),
            ]);
            set_flash('success', 'Đã cập nhật hạng phòng.');
        }
    } catch (Throwable $e) {
        set_flash('danger', 'Lỗi: ' . $e->getMessage());
    }
    redirect('quan-ly/quan-ly-phong.php');
}

$statusFilter = trim((string)($_GET['status'] ?? ''));
$typeFilter   = trim((string)($_GET['room_type_id'] ?? ''));
$rooms        = $roomModel->searchRooms(['status' => $statusFilter, 'room_type_id' => $typeFilter]);
$roomTypes    = $roomModel->getRoomTypes();

$pageTitle = 'Quản lý Phòng & Hạng phòng';
include dirname(__DIR__) . '/includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
  <form method="GET" class="d-flex flex-wrap gap-2">
    <select name="status" class="form-select form-select-sm" style="width:165px;" onchange="this.form.submit()">
      <option value="">Tất cả trạng thái</option>
      <option value="available" <?= $statusFilter === 'available' ? 'selected' : '' ?>>Trống</option>
      <option value="reserved" <?= $statusFilter === 'reserved' ? 'selected' : '' ?>>Đã đặt</option>
      <option value="occupied" <?= $statusFilter === 'occupied' ? 'selected' : '' ?>>Đang sử dụng</option>
      <option value="cleaning" <?= $statusFilter === 'cleaning' ? 'selected' : '' ?>>Cần vệ sinh</option>
      <option value="maintenance" <?= $statusFilter === 'maintenance' ? 'selected' : '' ?>>Đang bảo trì</option>
    </select>
    <select name="room_type_id" class="form-select form-select-sm" style="width:185px;" onchange="this.form.submit()">
      <option value="">Tất cả hạng phòng</option>
      <?php foreach ($roomTypes as $rt): ?>
        <option value="<?= (int)$rt['id'] ?>" <?= $typeFilter === (string)$rt['id'] ? 'selected' : '' ?>><?= e($rt['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </form>

  <div class="d-flex gap-2">
    <button class="btn btn-sm btn-lum-outline rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#roomTypesModal">
      <i class="fa-solid fa-layer-group me-1"></i>Quản lý Hạng phòng
    </button>
    <button class="btn btn-sm btn-lum-gold rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#addRoomModal">
      <i class="fa-solid fa-plus me-1"></i>Thêm Phòng mới
    </button>
  </div>
</div>

<!-- Rooms Table -->
<div class="lum-card">
  <div class="table-responsive">
    <table class="table lum-table">
      <thead>
        <tr>
          <th>Ảnh</th>
          <th>Số phòng</th>
          <th>Tầng</th>
          <th>Loại phòng</th>
          <th>Giá / đêm</th>
          <th>Trạng thái</th>
          <th class="text-end">Thao tác</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rooms as $r): ?>
          <?php $imgs = $roomModel->getRoomImages((int)$r['id']); ?>
          <tr>
            <td>
              <img src="<?= e(base_url($r['primary_image'] ?: 'assets/images/rooms/phong1.jpg')) ?>"
                   alt="P.<?= e($r['room_number']) ?>" style="width:68px;height:44px;object-fit:cover;border-radius:8px;">
            </td>
            <td class="fw-bold" style="color:var(--lum-navy);">Phòng <?= e($r['room_number']) ?></td>
            <td>Tầng <?= (int)$r['floor'] ?></td>
            <td><?= e($r['type_name']) ?> <div class="small text-muted"><?= (int)$r['capacity'] ?> khách · <?= e($r['area_sqm']) ?> m²</div></td>
            <td class="fw-semibold"><?= e(format_currency($r['price_per_night'])) ?></td>
            <td><?= status_badge($r['status'], 'room') ?></td>
            <td class="text-end text-nowrap">
              <button class="btn btn-sm btn-light border" data-bs-toggle="modal" data-bs-target="#imgModal<?= (int)$r['id'] ?>" title="Ảnh phòng (<?= count($imgs) ?>)">
                <i class="fa-regular fa-images"></i>
              </button>
              <button class="btn btn-sm btn-lum-outline" data-bs-toggle="modal" data-bs-target="#editRoomModal<?= (int)$r['id'] ?>" title="Chỉnh sửa">
                <i class="fa-solid fa-pen"></i>
              </button>
              <form method="POST" class="d-inline" onsubmit="return confirm('Xóa phòng <?= e($r['room_number']) ?>? Thao tác không thể thực hiện nếu phòng đã có lịch sử đặt phòng.')">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete_room">
                <input type="hidden" name="room_id" value="<?= (int)$r['id'] ?>">
                <button type="submit" class="btn btn-sm btn-outline-danger" title="Xóa"><i class="fa-solid fa-trash"></i></button>
              </form>
            </td>
          </tr>

          <!-- Edit Room Modal -->
          <div class="modal fade" id="editRoomModal<?= (int)$r['id'] ?>" tabindex="-1">
            <div class="modal-dialog modal-lg">
              <form method="POST" enctype="multipart/form-data" class="modal-content">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update_room">
                <input type="hidden" name="room_id" value="<?= (int)$r['id'] ?>">
                <div class="modal-header">
                  <h6 class="modal-title font-serif fw-bold">Cập nhật Phòng <?= e($r['room_number']) ?></h6>
                  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                  <div class="row g-3">
                    <div class="col-md-4">
                      <label class="form-label small fw-semibold">Số phòng *</label>
                      <input type="text" name="room_number" class="form-control" value="<?= e($r['room_number']) ?>" required>
                    </div>
                    <div class="col-md-4">
                      <label class="form-label small fw-semibold">Tầng *</label>
                      <input type="number" name="floor" class="form-control" value="<?= (int)$r['floor'] ?>" min="1" required>
                    </div>
                    <div class="col-md-4">
                      <label class="form-label small fw-semibold">Trạng thái</label>
                      <select name="status" class="form-select">
                        <option value="available" <?= $r['status'] === 'available' ? 'selected' : '' ?>>Trống</option>
                        <option value="reserved" <?= $r['status'] === 'reserved' ? 'selected' : '' ?>>Đã đặt</option>
                        <option value="occupied" <?= $r['status'] === 'occupied' ? 'selected' : '' ?>>Đang sử dụng</option>
                        <option value="cleaning" <?= $r['status'] === 'cleaning' ? 'selected' : '' ?>>Đang vệ sinh</option>
                        <option value="maintenance" <?= $r['status'] === 'maintenance' ? 'selected' : '' ?>>Đang bảo trì</option>
                      </select>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label small fw-semibold">Loại phòng *</label>
                      <select name="room_type_id" class="form-select" required>
                        <?php foreach ($roomTypes as $rt): ?>
                          <option value="<?= (int)$rt['id'] ?>" <?= (int)$r['room_type_id'] === (int)$rt['id'] ? 'selected' : '' ?>><?= e($rt['name']) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label small fw-semibold">Giá mỗi đêm (VNĐ) *</label>
                      <input type="number" name="price_per_night" class="form-control" value="<?= (int)$r['price_per_night'] ?>" min="100000" step="50000" required>
                    </div>
                    <div class="col-12">
                      <label class="form-label small fw-semibold">Tiện nghi (phân cách bằng dấu phẩy)</label>
                      <input type="text" name="amenities" class="form-control" value="<?= e($r['amenities']) ?>">
                    </div>
                    <div class="col-12">
                      <label class="form-label small fw-semibold">Mô tả chi tiết</label>
                      <textarea name="description" class="form-control" rows="2"><?= e($r['description']) ?></textarea>
                    </div>
                    <div class="col-12">
                      <label class="form-label small fw-semibold">Tải thêm ảnh phòng (tùy chọn)</label>
                      <input type="file" name="room_image" class="form-control" accept="image/*">
                      <div class="form-check mt-1">
                        <input class="form-check-input" type="checkbox" name="set_primary" value="1" id="prim<?= (int)$r['id'] ?>">
                        <label class="form-check-label small" for="prim<?= (int)$r['id'] ?>">Đặt làm ảnh đại diện chính</label>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="modal-footer">
                  <button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal">Đóng</button>
                  <button type="submit" class="btn btn-sm btn-lum-primary px-4">Lưu thay đổi</button>
                </div>
              </form>
            </div>
          </div>

          <!-- Room Images Modal -->
          <div class="modal fade" id="imgModal<?= (int)$r['id'] ?>" tabindex="-1">
            <div class="modal-dialog modal-lg">
              <div class="modal-content">
                <div class="modal-header">
                  <h6 class="modal-title font-serif fw-bold">Thư viện ảnh Phòng <?= e($r['room_number']) ?></h6>
                  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                  <div class="row g-3">
                    <?php foreach ($imgs as $im): ?>
                      <div class="col-sm-6 col-md-4">
                        <div class="border rounded-3 overflow-hidden p-2 bg-light">
                          <img src="<?= e(base_url($im['image_path'])) ?>" class="w-100 rounded" style="height:120px;object-fit:cover;">
                          <div class="d-flex justify-content-between align-items-center mt-2">
                            <?php if ($im['is_primary']): ?>
                              <span class="badge bg-success">Ảnh chính</span>
                            <?php else: ?>
                              <form method="POST">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="set_primary_img">
                                <input type="hidden" name="room_id" value="<?= (int)$r['id'] ?>">
                                <input type="hidden" name="image_id" value="<?= (int)$im['id'] ?>">
                                <button class="btn btn-xs btn-outline-primary py-0 px-2 small">Đặt chính</button>
                              </form>
                            <?php endif; ?>
                            <form method="POST">
                              <?= csrf_field() ?>
                              <input type="hidden" name="action" value="delete_img">
                              <input type="hidden" name="image_id" value="<?= (int)$im['id'] ?>">
                              <button class="btn btn-xs btn-outline-danger py-0 px-2 small"><i class="fa-solid fa-trash"></i></button>
                            </form>
                          </div>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  </div>
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add Room Modal -->
<div class="modal fade" id="addRoomModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <form method="POST" enctype="multipart/form-data" class="modal-content">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="create_room">
      <div class="modal-header">
        <h6 class="modal-title font-serif fw-bold">Thêm Phòng mới</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label small fw-semibold">Số phòng *</label>
            <input type="text" name="room_number" class="form-control" placeholder="VD: 502" required>
          </div>
          <div class="col-md-4">
            <label class="form-label small fw-semibold">Tầng *</label>
            <input type="number" name="floor" class="form-control" value="1" min="1" required>
          </div>
          <div class="col-md-4">
            <label class="form-label small fw-semibold">Trạng thái ban đầu</label>
            <select name="status" class="form-select">
              <option value="available">Trống</option>
              <option value="maintenance">Đang bảo trì</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label small fw-semibold">Hạng phòng *</label>
            <select name="room_type_id" class="form-select" required>
              <?php foreach ($roomTypes as $rt): ?>
                <option value="<?= (int)$rt['id'] ?>"><?= e($rt['name']) ?> (<?= e(format_currency($rt['base_price'])) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label small fw-semibold">Giá phòng / đêm (VNĐ) *</label>
            <input type="number" name="price_per_night" class="form-control" value="2000000" min="100000" step="50000" required>
          </div>
          <div class="col-12">
            <label class="form-label small fw-semibold">Tiện nghi</label>
            <input type="text" name="amenities" class="form-control" value="Wifi 6, Smart TV, Minibar, Két sắt, Bồn tắm">
          </div>
          <div class="col-12">
            <label class="form-label small fw-semibold">Mô tả</label>
            <textarea name="description" class="form-control" rows="2"></textarea>
          </div>
          <div class="col-12">
            <label class="form-label small fw-semibold">Hình ảnh đại diện phòng</label>
            <input type="file" name="room_image" class="form-control" accept="image/*">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal">Đóng</button>
        <button type="submit" class="btn btn-sm btn-lum-gold px-4">Thêm Phòng</button>
      </div>
    </form>
  </div>
</div>

<!-- Room Types Modal -->
<div class="modal fade" id="roomTypesModal" tabindex="-1">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <div class="modal-header">
        <h6 class="modal-title font-serif fw-bold">Quản lý Hạng phòng</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form method="POST" class="row g-2 p-3 rounded-3 mb-4 border" style="background:var(--lum-cream);">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="create_type">
          <div class="col-md-3"><input type="text" name="name" class="form-control form-control-sm" placeholder="Tên hạng phòng *" required></div>
          <div class="col-md-2"><input type="number" name="base_price" class="form-control form-control-sm" placeholder="Giá gốc *" required></div>
          <div class="col-md-1"><input type="number" name="capacity" class="form-control form-control-sm" value="2" min="1" required title="Số khách"></div>
          <div class="col-md-2"><input type="text" name="bed_type" class="form-control form-control-sm" placeholder="Loại giường" value="1 Giường King"></div>
          <div class="col-md-1"><input type="number" name="area_sqm" class="form-control form-control-sm" value="40" step="0.5" title="m2"></div>
          <div class="col-md-3 d-grid"><button type="submit" class="btn btn-sm btn-lum-primary">Thêm Hạng phòng</button></div>
          <div class="col-md-6"><input type="text" name="amenities" class="form-control form-control-sm" placeholder="Tiện nghi mặc định"></div>
          <div class="col-md-6"><input type="text" name="description" class="form-control form-control-sm" placeholder="Mô tả ngắn"></div>
        </form>

        <div class="table-responsive">
          <table class="table table-sm lum-table">
            <thead><tr><th>Tên hạng</th><th>Giá gốc</th><th>Sức chứa</th><th>Giường</th><th>Diện tích</th><th>Lưu</th></tr></thead>
            <tbody>
              <?php foreach ($roomTypes as $rt): ?>
                <tr>
                  <form method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="update_type">
                    <input type="hidden" name="type_id" value="<?= (int)$rt['id'] ?>">
                    <input type="hidden" name="description" value="<?= e($rt['description']) ?>">
                    <input type="hidden" name="amenities" value="<?= e($rt['amenities']) ?>">
                    <td><input type="text" name="name" class="form-control form-control-sm" value="<?= e($rt['name']) ?>"></td>
                    <td><input type="number" name="base_price" class="form-control form-control-sm" value="<?= (int)$rt['base_price'] ?>"></td>
                    <td><input type="number" name="capacity" class="form-control form-control-sm" value="<?= (int)$rt['capacity'] ?>" style="width:70px;"></td>
                    <td><input type="text" name="bed_type" class="form-control form-control-sm" value="<?= e($rt['bed_type']) ?>"></td>
                    <td><input type="number" name="area_sqm" class="form-control form-control-sm" value="<?= e($rt['area_sqm']) ?>" step="0.5" style="width:80px;"></td>
                    <td><button class="btn btn-sm btn-lum-outline">Lưu</button></td>
                  </form>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
