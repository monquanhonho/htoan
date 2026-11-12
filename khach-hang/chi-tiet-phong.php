<?php
/**
 * Room Detail & Online Booking Page
 * Lumina Hotel Management System
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/models/RoomModel.php';
require_once dirname(__DIR__) . '/controllers/BookingController.php';

$roomId = (int)($_GET['id'] ?? 0);
$roomModel = new RoomModel();
$room = $roomModel->findRoom($roomId);

if (!$room) {
    set_flash('danger', 'Không tìm thấy thông tin phòng.');
    redirect(is_logged_in() ? 'khach-hang/danh-sach-phong.php' : 'index.php');
}

$checkIn  = trim((string)($_GET['check_in'] ?? date('Y-m-d')));
$checkOut = trim((string)($_GET['check_out'] ?? date('Y-m-d', strtotime('+2 days'))));
if ($checkOut <= $checkIn) {
    $checkOut = date('Y-m-d', strtotime($checkIn . ' +1 day'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!is_logged_in() || current_role() !== 'customer') {
        set_flash('warning', 'Vui lòng đăng nhập bằng tài khoản Khách hàng để đặt phòng.');
        redirect('dang-nhap.php');
    }
    require_csrf('khach-hang/chi-tiet-phong.php?id=' . $roomId);

    try {
        $bookingCtrl = new BookingController();
        $user = current_user();
        $res = $bookingCtrl->createCustomerBooking((int)$user['customer_id'], (int)$user['id'], [
            'room_id'          => $roomId,
            'check_in_date'    => $_POST['check_in_date'] ?? '',
            'check_out_date'   => $_POST['check_out_date'] ?? '',
            'num_guests'       => $_POST['num_guests'] ?? 1,
            'special_requests' => $_POST['special_requests'] ?? '',
        ]);
        set_flash('success', 'Đặt phòng thành công! Mã đơn của bạn là ' . $res['booking_code'] . '. Bạn có thể tiến hành thanh toán ngay.');
        redirect('khach-hang/dat-phong.php');
    } catch (Throwable $e) {
        set_flash('danger', $e->getMessage());
        redirect('khach-hang/chi-tiet-phong.php?id=' . $roomId . '&check_in=' . urlencode($_POST['check_in_date'] ?? $checkIn) . '&check_out=' . urlencode($_POST['check_out_date'] ?? $checkOut));
    }
}

$images = $room['images'] ?: [['image_path' => 'assets/images/rooms/phong1.jpg', 'is_primary' => 1]];
$amenitiesList = array_filter(array_map('trim', explode(',', (string)$room['amenities'])));

$pageTitle = 'Chi tiết Phòng ' . $room['room_number'];

if (is_logged_in()) {
    include dirname(__DIR__) . '/includes/header.php';
} else {
    // Public wrapper header
    ?>
    <!DOCTYPE html>
    <html lang="vi">
    <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
      <title><?= e($pageTitle) ?> | <?= e(APP_NAME) ?></title>
      <link href="<?= e(base_url('assets/vendor/bootstrap/css/bootstrap.min.css')) ?>" rel="stylesheet">
      <link href="<?= e(base_url('assets/vendor/fontawesome/css/all.min.css')) ?>" rel="stylesheet">
      <link href="<?= e(base_url('assets/css/style.css?v=' . (@filemtime(ROOT_PATH . '/assets/css/style.css') ?: 1))) ?>" rel="stylesheet">
    </head>
    <body>
    <nav class="navbar bg-white border-bottom mb-4">
      <div class="container py-1">
        <a class="navbar-brand font-serif fw-bold d-flex align-items-center gap-2" style="color:var(--lum-navy);" href="<?= e(base_url('index.php')) ?>">
          <img src="<?= e(base_url(APP_LOGO)) ?>" alt="Purple Lotus Hotel" class="lum-brand-logo" style="width:34px;height:34px;">
          <span>PURPLE LOTUS HOTEL</span>
        </a>
        <div class="d-flex gap-2">
          <a href="<?= e(base_url('dang-nhap.php')) ?>" class="btn btn-sm btn-lum-outline rounded-pill px-3">Đăng nhập</a>
          <a href="<?= e(base_url('dang-ky.php')) ?>" class="btn btn-sm btn-lum-gold rounded-pill px-3">Đăng ký</a>
        </div>
      </div>
    </nav>
    <div class="container pb-5">
    <?php
}
?>

<div class="mb-3">
  <a href="<?= e(base_url(is_logged_in() ? 'khach-hang/danh-sach-phong.php' : 'index.php#rooms')) ?>" class="btn btn-sm btn-light border rounded-pill px-3">
    <i class="fa-solid fa-arrow-left me-1"></i>Quay lại danh sách phòng
  </a>
</div>

<div class="row g-4">
  <!-- Gallery & Info -->
  <div class="col-lg-7">
    <div class="lum-card overflow-hidden mb-4">
      <div id="roomGalleryCarousel" class="carousel slide" data-bs-ride="carousel">
        <div class="carousel-inner">
          <?php foreach ($images as $idx => $img): ?>
            <div class="carousel-item <?= $idx === 0 ? 'active' : '' ?>">
              <img src="<?= e(base_url($img['image_path'])) ?>" class="d-block w-100" style="height:380px;object-fit:cover;" alt="Ảnh phòng">
            </div>
          <?php endforeach; ?>
        </div>
        <?php if (count($images) > 1): ?>
          <button class="carousel-control-prev" type="button" data-bs-target="#roomGalleryCarousel" data-bs-slide="prev">
            <span class="carousel-control-prev-icon"></span>
          </button>
          <button class="carousel-control-next" type="button" data-bs-target="#roomGalleryCarousel" data-bs-slide="next">
            <span class="carousel-control-next-icon"></span>
          </button>
        <?php endif; ?>
      </div>

      <div class="lum-card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
          <div>
            <span class="small text-uppercase fw-semibold" style="color:var(--lum-gold-dark);letter-spacing:0.12em;">
              Phòng <?= e($room['room_number']) ?> · Tầng <?= (int)$room['floor'] ?>
            </span>
            <h3 class="font-serif fw-bold mb-0" style="color:var(--lum-navy);"><?= e($room['type_name']) ?></h3>
          </div>
          <?= status_badge($room['status'], 'room') ?>
        </div>

        <div class="row g-2 mb-4 text-center">
          <div class="col-4">
            <div class="p-2 rounded-3" style="background:var(--lum-cream);">
              <div class="small text-muted">Diện tích</div>
              <div class="fw-bold"><?= e($room['area_sqm']) ?> m²</div>
            </div>
          </div>
          <div class="col-4">
            <div class="p-2 rounded-3" style="background:var(--lum-cream);">
              <div class="small text-muted">Sức chứa</div>
              <div class="fw-bold"><?= (int)$room['capacity'] ?> khách</div>
            </div>
          </div>
          <div class="col-4">
            <div class="p-2 rounded-3" style="background:var(--lum-cream);">
              <div class="small text-muted">Loại giường</div>
              <div class="fw-bold small"><?= e($room['bed_type']) ?></div>
            </div>
          </div>
        </div>

        <h6 class="font-serif fw-bold" style="color:var(--lum-navy);">Mô tả phòng</h6>
        <p class="text-muted small lh-lg"><?= e($room['description'] ?: $room['type_description']) ?></p>

        <h6 class="font-serif fw-bold mt-3" style="color:var(--lum-navy);">Tiện nghi cao cấp</h6>
        <div class="row g-2 mt-1">
          <?php foreach ($amenitiesList as $am): ?>
            <div class="col-sm-6 small">
              <i class="fa-solid fa-check text-warning me-2"></i><?= e($am) ?>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Booking Form -->
  <div class="col-lg-5">
    <div class="lum-card sticky-top" style="top:90px;">
      <div class="lum-card-header" style="background:var(--lum-navy);color:var(--lum-white);border-top-left-radius:14px;border-top-right-radius:14px;">
        <div>
          <div class="small opacity-75">Giá niêm yết</div>
          <div class="fs-4 font-serif fw-bold" style="color:var(--lum-gold-light);"><?= e(format_currency($room['price_per_night'])) ?> <span class="fs-6 fw-normal">/ đêm</span></div>
        </div>
        <i class="fa-solid fa-crown fa-lg" style="color:var(--lum-gold);"></i>
      </div>

      <div class="lum-card-body">
        <form method="POST" action="">
          <?= csrf_field() ?>
          <input type="hidden" id="calcRoomId" value="<?= (int)$room['id'] ?>">

          <div class="row g-3">
            <div class="col-6">
              <label class="form-label small fw-semibold">Ngày nhận phòng *</label>
              <input type="date" name="check_in_date" id="calcCheckIn" class="form-control" min="<?= date('Y-m-d') ?>" value="<?= e($checkIn) ?>" required>
            </div>
            <div class="col-6">
              <label class="form-label small fw-semibold">Ngày trả phòng *</label>
              <input type="date" name="check_out_date" id="calcCheckOut" class="form-control" min="<?= date('Y-m-d', strtotime('+1 day')) ?>" value="<?= e($checkOut) ?>" required>
            </div>
            <div class="col-12">
              <label class="form-label small fw-semibold">Số lượng khách *</label>
              <select name="num_guests" class="form-select" required>
                <?php for ($g = 1; $g <= (int)$room['capacity']; $g++): ?>
                  <option value="<?= $g ?>"><?= $g ?> khách</option>
                <?php endfor; ?>
              </select>
            </div>
            <div class="col-12">
              <label class="form-label small fw-semibold">Yêu cầu đặc biệt</label>
              <textarea name="special_requests" class="form-control" rows="2" placeholder="VD: Check-in muộn, thêm gối, phòng yên tĩnh..."></textarea>
            </div>
            <div class="col-12" id="bookingCalcSummary">
              <div class="small text-muted">Đang kiểm tra lịch phòng và tính giá...</div>
            </div>
            <div class="col-12 d-grid mt-2">
              <?php if (is_logged_in() && current_role() === 'customer'): ?>
                <button type="submit" id="bookingSubmitBtn" class="btn btn-lum-gold py-2 rounded-pill">
                  <i class="fa-solid fa-calendar-check me-2"></i>Xác nhận Đặt phòng
                </button>
              <?php elseif (is_logged_in()): ?>
                <div class="alert alert-info small mb-0">Bạn đang đăng nhập với quyền <?= e(current_role()) ?>. Vui lòng dùng tài khoản Khách hàng để đặt phòng.</div>
              <?php else: ?>
                <a href="<?= e(base_url('dang-nhap.php')) ?>" class="btn btn-lum-primary py-2 rounded-pill">
                  <i class="fa-solid fa-right-to-bracket me-2"></i>Đăng nhập để Đặt phòng
                </a>
              <?php endif; ?>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  initBookingCalculator('<?= e(base_url('api/ajax.php')) ?>');
});
</script>

<?php
if (is_logged_in()) {
    include dirname(__DIR__) . '/includes/footer.php';
} else {
    echo '</div><script src="' . e(base_url('assets/vendor/bootstrap/js/bootstrap.bundle.min.js')) . '"></script><script src="' . e(base_url('assets/js/app.js?v=' . (@filemtime(ROOT_PATH . '/assets/js/app.js') ?: 1))) . '"></script></body></html>';
}
?>
