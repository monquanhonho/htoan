<?php
/**
 * Customer: Search & Browse Hotel Rooms
 * Lumina Hotel Management System
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';
require_role(['customer']);

require_once dirname(__DIR__) . '/models/RoomModel.php';

$roomModel = new RoomModel();
$roomTypes = $roomModel->getRoomTypes();

$filters = [
    'room_type_id' => $_GET['room_type_id'] ?? '',
    'max_price'    => $_GET['max_price'] ?? '',
    'guests'       => $_GET['guests'] ?? '',
    'check_in'     => $_GET['check_in'] ?? date('Y-m-d'),
    'check_out'    => $_GET['check_out'] ?? date('Y-m-d', strtotime('+2 days')),
];

if ($filters['check_in'] !== '' && $filters['check_out'] !== '' && $filters['check_out'] <= $filters['check_in']) {
    $filters['check_out'] = date('Y-m-d', strtotime($filters['check_in'] . ' +1 day'));
}

$rooms = $roomModel->searchRooms($filters);

$pageTitle = 'Tìm & Đặt phòng';
include dirname(__DIR__) . '/includes/header.php';
?>

<div class="lum-card mb-4">
  <div class="lum-card-body">
    <form method="GET" class="row g-3 align-items-end">
      <div class="col-md-3 col-lg-2">
        <label class="form-label small fw-semibold">Ngày nhận phòng</label>
        <input type="date" name="check_in" class="form-control" min="<?= date('Y-m-d') ?>" value="<?= e($filters['check_in']) ?>">
      </div>
      <div class="col-md-3 col-lg-2">
        <label class="form-label small fw-semibold">Ngày trả phòng</label>
        <input type="date" name="check_out" class="form-control" min="<?= date('Y-m-d', strtotime('+1 day')) ?>" value="<?= e($filters['check_out']) ?>">
      </div>
      <div class="col-md-3 col-lg-3">
        <label class="form-label small fw-semibold">Loại phòng</label>
        <select name="room_type_id" class="form-select">
          <option value="">Tất cả loại phòng</option>
          <?php foreach ($roomTypes as $rt): ?>
            <option value="<?= (int)$rt['id'] ?>" <?= (string)$filters['room_type_id'] === (string)$rt['id'] ? 'selected' : '' ?>>
              <?= e($rt['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3 col-lg-2">
        <label class="form-label small fw-semibold">Giá tối đa / đêm</label>
        <input type="number" name="max_price" class="form-control" step="100000" placeholder="VD: 5000000" value="<?= e($filters['max_price']) ?>">
      </div>
      <div class="col-md-3 col-lg-1">
        <label class="form-label small fw-semibold">Số khách</label>
        <input type="number" name="guests" class="form-control" min="1" max="6" value="<?= e($filters['guests']) ?>">
      </div>
      <div class="col-md-3 col-lg-2 d-grid">
        <button type="submit" class="btn btn-lum-primary">
          <i class="fa-solid fa-magnifying-glass me-1"></i>Tìm phòng
        </button>
      </div>
    </form>
  </div>
</div>

<?php if (empty($rooms)): ?>
  <div class="lum-card p-5 text-center">
    <i class="fa-regular fa-calendar-xmark fa-2x text-muted mb-2"></i>
    <h5>Không có phòng trống phù hợp với tiêu chí</h5>
    <p class="text-muted small">Vui lòng thử khoảng ngày khác hoặc xóa bộ lọc giá.</p>
    <a href="<?= e(base_url('khach-hang/danh-sach-phong.php')) ?>" class="btn btn-lum-outline rounded-pill px-4">Đặt lại bộ lọc</a>
  </div>
<?php else: ?>
  <div class="row g-4">
    <?php foreach ($rooms as $room): ?>
      <?php $img = $room['primary_image'] ?: 'assets/images/rooms/phong1.jpg'; ?>
      <div class="col-md-6 col-xl-4">
        <div class="lum-card lum-card-hover h-100 d-flex flex-column">
          <div class="lum-img-wrap">
            <img src="<?= e(base_url($img)) ?>" alt="Phòng <?= e($room['room_number']) ?>" class="lum-room-img">
            <span class="position-absolute top-0 start-0 m-3 badge rounded-pill px-3 py-2" style="background:rgba(15,28,46,0.9);color:var(--lum-gold-light);">
              Phòng <?= e($room['room_number']) ?> · Tầng <?= (int)$room['floor'] ?>
            </span>
          </div>
          <div class="p-4 flex-grow-1 d-flex flex-column">
            <div class="d-flex justify-content-between align-items-start mb-2">
              <h5 class="font-serif fw-bold mb-0" style="color:var(--lum-navy);"><?= e($room['type_name']) ?></h5>
              <?= status_badge($room['status'], 'room') ?>
            </div>
            <div class="small text-muted mb-2">
              <i class="fa-solid fa-ruler-combined me-1 text-warning"></i><?= e($room['area_sqm']) ?> m² ·
              <i class="fa-solid fa-users ms-2 me-1 text-warning"></i>Tối đa <?= (int)$room['capacity'] ?> khách
            </div>
            <p class="small text-muted mb-2"><?= e($room['description']) ?></p>
            <div class="small text-secondary mb-3">
              <i class="fa-solid fa-sparkles me-1 text-warning"></i><?= e($room['amenities']) ?>
            </div>
            <div class="mt-auto pt-3 border-top d-flex justify-content-between align-items-center">
              <div>
                <div class="fw-bold fs-5" style="color:var(--lum-navy);"><?= e(format_currency($room['price_per_night'])) ?></div>
                <small class="text-muted">/ đêm</small>
              </div>
              <a href="<?= e(base_url('khach-hang/chi-tiet-phong.php?id=' . (int)$room['id'] . '&check_in=' . urlencode($filters['check_in']) . '&check_out=' . urlencode($filters['check_out']))) ?>"
                 class="btn btn-lum-gold rounded-pill px-3">
                Xem & Đặt phòng
              </a>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
