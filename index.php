<?php
/**
 * Public Landing & Room Showcase
 * Lumina Hotel Management System
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/models/RoomModel.php';
require_once __DIR__ . '/models/ServiceModel.php';

$roomModel = new RoomModel();
$serviceModel = new ServiceModel();

$roomTypes = $roomModel->getRoomTypes();
$services  = $serviceModel->getAllServices(true);

$filters = [
    'room_type_id' => $_GET['room_type_id'] ?? '',
    'max_price'    => $_GET['max_price'] ?? '',
    'check_in'     => $_GET['check_in'] ?? date('Y-m-d'),
    'check_out'    => $_GET['check_out'] ?? date('Y-m-d', strtotime('+2 days')),
];

// Validate dates if user searched
if ($filters['check_in'] !== '' && $filters['check_out'] !== '' && $filters['check_out'] <= $filters['check_in']) {
    $filters['check_out'] = date('Y-m-d', strtotime($filters['check_in'] . ' +1 day'));
}

$rooms = $roomModel->searchRooms($filters);
$user = current_user();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e(APP_NAME) ?> — Nghỉ dưỡng Thượng lưu</title>
  <link href="<?= e(base_url('assets/vendor/bootstrap/css/bootstrap.min.css')) ?>" rel="stylesheet">
  <link href="<?= e(base_url('assets/vendor/fontawesome/css/all.min.css')) ?>" rel="stylesheet">
  <link href="<?= e(base_url('assets/css/style.css?v=' . (@filemtime(ROOT_PATH . '/assets/css/style.css') ?: 1))) ?>" rel="stylesheet">
</head>
<body>

<!-- Top Public Navbar -->
<nav class="navbar navbar-expand-lg sticky-top bg-white border-bottom" style="border-color:var(--lum-border)!important;">
  <div class="container py-1">
    <a class="navbar-brand d-flex align-items-center gap-2" href="<?= e(base_url('index.php')) ?>">
      <img src="<?= e(base_url(APP_LOGO)) ?>" alt="Purple Lotus Hotel" class="lum-brand-logo" style="width:40px;height:40px;">
      <div>
        <div class="lum-brand-title" style="color:var(--lum-navy);font-size:1.05rem;">PURPLE LOTUS HOTEL</div>
        <div class="lum-brand-sub" style="color:var(--lum-gold-dark);">Luxury Sanctuary</div>
      </div>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#pubNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="pubNav">
      <ul class="navbar-nav ms-auto align-items-lg-center gap-2">
        <li class="nav-item"><a class="nav-link fw-medium" href="#rooms">Phòng & Suite</a></li>
        <li class="nav-item"><a class="nav-link fw-medium" href="#services">Dịch vụ 5 Sao</a></li>
        <?php if ($user): ?>
          <li class="nav-item">
            <a class="btn btn-lum-primary rounded-pill px-4 btn-sm" href="<?= e(base_url(role_dashboard_path($user['role']))) ?>">
              <i class="fa-solid fa-gauge-high me-1"></i>Vào Dashboard (<?= e($user['full_name']) ?>)
            </a>
          </li>
          <li class="nav-item">
            <a class="btn btn-lum-outline rounded-pill px-3 btn-sm" href="<?= e(base_url('dang-xuat.php')) ?>">Đăng xuất</a>
          </li>
        <?php else: ?>
          <li class="nav-item"><a class="btn btn-lum-outline rounded-pill px-4 btn-sm" href="<?= e(base_url('dang-nhap.php')) ?>">Đăng nhập</a></li>
          <li class="nav-item"><a class="btn btn-lum-gold rounded-pill px-4 btn-sm" href="<?= e(base_url('dang-ky.php')) ?>">Đăng ký</a></li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>

<!-- Hero Banner -->
<section class="lum-hero py-5">
  <div class="container py-4 position-relative">
    <div class="row align-items-center g-4">
      <div class="col-lg-7">
        <div class="text-uppercase small fw-semibold mb-2" style="letter-spacing:0.22em;color:var(--lum-gold-light);">
          <i class="fa-solid fa-star me-1"></i>Purple Lotus Hotel & Resort · 5-Star Hospitality
        </div>
        <h1 class="display-5 font-serif fw-bold mb-3">
          Không gian Nghỉ dưỡng <span style="color:var(--lum-gold-light);">Tinh tế & Đẳng cấp</span>
        </h1>
        <p class="lead opacity-75 mb-4" style="font-size:1.05rem;">
          Khám phá bộ sưu tập phòng Suite sang trọng, ẩm thực tinh hoa, Spa trị liệu và dịch vụ quản gia cá nhân hóa tại trung tâm thành phố.
        </p>
        <div class="d-flex flex-wrap gap-3">
          <a href="#rooms" class="btn btn-lum-gold rounded-pill px-4 py-2">
            <i class="fa-solid fa-magnifying-glass me-2"></i>Khám phá Phòng trống
          </a>
          <a href="<?= e(base_url($user ? 'khach-hang/danh-sach-phong.php' : 'dang-nhap.php')) ?>" class="btn btn-outline-light rounded-pill px-4 py-2">
            Đặt phòng Trực tuyến
          </a>
        </div>
      </div>
      <div class="col-lg-5">
        <!-- Search Widget -->
        <div class="p-4 rounded-4 bg-white text-dark shadow-lg" style="border:1px solid var(--lum-gold);">
          <h5 class="font-serif fw-bold mb-3" style="color:var(--lum-navy);">Tìm kiếm Phòng trống</h5>
          <form method="GET" action="#rooms">
            <div class="row g-3">
              <div class="col-6">
                <label class="form-label small fw-semibold">Ngày nhận phòng</label>
                <input type="date" name="check_in" class="form-control" min="<?= date('Y-m-d') ?>" value="<?= e($filters['check_in']) ?>">
              </div>
              <div class="col-6">
                <label class="form-label small fw-semibold">Ngày trả phòng</label>
                <input type="date" name="check_out" class="form-control" min="<?= date('Y-m-d', strtotime('+1 day')) ?>" value="<?= e($filters['check_out']) ?>">
              </div>
              <div class="col-12">
                <label class="form-label small fw-semibold">Hạng phòng</label>
                <select name="room_type_id" class="form-select">
                  <option value="">Tất cả hạng phòng</option>
                  <?php foreach ($roomTypes as $rt): ?>
                    <option value="<?= (int)$rt['id'] ?>" <?= (string)$filters['room_type_id'] === (string)$rt['id'] ? 'selected' : '' ?>>
                      <?= e($rt['name']) ?> (Từ <?= e(format_currency($rt['base_price'])) ?>/đêm)
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-12">
                <label class="form-label small fw-semibold">Ngân sách tối đa / đêm</label>
                <select name="max_price" class="form-select">
                  <option value="">Không giới hạn</option>
                  <option value="2000000" <?= $filters['max_price'] === '2000000' ? 'selected' : '' ?>>Dưới 2.000.000 ₫</option>
                  <option value="3500000" <?= $filters['max_price'] === '3500000' ? 'selected' : '' ?>>Dưới 3.500.000 ₫</option>
                  <option value="6000000" <?= $filters['max_price'] === '6000000' ? 'selected' : '' ?>>Dưới 6.000.000 ₫</option>
                </select>
              </div>
              <div class="col-12">
                <button type="submit" class="btn btn-lum-primary w-100 rounded-pill py-2">
                  <i class="fa-solid fa-calendar-check me-2"></i>Kiểm tra Phòng trống
                </button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Available Rooms Showcase -->
<section id="rooms" class="py-5">
  <div class="container">
    <div class="d-flex flex-wrap justify-content-between align-items-end mb-4 gap-2">
      <div>
        <span class="text-uppercase small fw-bold" style="color:var(--lum-gold-dark);letter-spacing:0.15em;">Bộ sưu tập Phòng</span>
        <h2 class="font-serif fw-bold mb-0" style="color:var(--lum-navy);">Phòng sẵn sàng từ <?= e(format_date($filters['check_in'])) ?> đến <?= e(format_date($filters['check_out'])) ?></h2>
      </div>
      <span class="badge-lux badge-lux-navy"><?= count($rooms) ?> phòng phù hợp</span>
    </div>

    <?php if (empty($rooms)): ?>
      <div class="lum-card p-5 text-center">
        <i class="fa-regular fa-calendar-xmark fa-2x text-muted mb-2"></i>
        <h5>Không có phòng phù hợp trong khoảng thời gian đã chọn</h5>
        <p class="text-muted small mb-3">Vui lòng thử thay đổi ngày lưu trú hoặc mở rộng mức giá tìm kiếm.</p>
        <a href="<?= e(base_url('index.php#rooms')) ?>" class="btn btn-lum-outline rounded-pill px-4">Xem tất cả phòng</a>
      </div>
    <?php else: ?>
      <div class="row g-4">
        <?php foreach ($rooms as $room): ?>
          <?php $img = $room['primary_image'] ?: 'assets/images/rooms/phong1.jpg'; ?>
          <div class="col-md-6 col-lg-4">
            <div class="lum-card lum-card-hover h-100 d-flex flex-column">
              <div class="lum-img-wrap">
                <img src="<?= e(base_url($img)) ?>" alt="Phòng <?= e($room['room_number']) ?>" class="lum-room-img">
                <span class="position-absolute top-0 end-0 m-3 badge rounded-pill px-3 py-2" style="background:rgba(15,28,46,0.88);color:var(--lum-gold-light);">
                  Phòng <?= e($room['room_number']) ?> · Tầng <?= (int)$room['floor'] ?>
                </span>
              </div>
              <div class="p-4 flex-grow-1 d-flex flex-column">
                <div class="d-flex justify-content-between align-items-start mb-2">
                  <h5 class="font-serif fw-bold mb-0" style="color:var(--lum-navy);"><?= e($room['type_name']) ?></h5>
                  <?= status_badge($room['status'], 'room') ?>
                </div>
                <div class="small text-muted mb-2">
                  <i class="fa-solid fa-maximize me-1 text-warning"></i><?= e($room['area_sqm']) ?> m² ·
                  <i class="fa-solid fa-user-group ms-2 me-1 text-warning"></i><?= (int)$room['capacity'] ?> khách ·
                  <?= e($room['bed_type']) ?>
                </div>
                <p class="small text-muted flex-grow-1"><?= e($room['description']) ?></p>
                <div class="pt-3 border-top d-flex justify-content-between align-items-center">
                  <div>
                    <div class="fw-bold fs-5" style="color:var(--lum-navy);"><?= e(format_currency($room['price_per_night'])) ?></div>
                    <small class="text-muted">/ đêm</small>
                  </div>
                  <a href="<?= e(base_url('khach-hang/chi-tiet-phong.php?id=' . (int)$room['id'] . '&check_in=' . urlencode($filters['check_in']) . '&check_out=' . urlencode($filters['check_out']))) ?>"
                     class="btn btn-lum-gold rounded-pill px-3 btn-sm">
                    Chi tiết & Đặt <i class="fa-solid fa-arrow-right ms-1"></i>
                  </a>
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<!-- Luxury Services Showcase -->
<section id="services" class="py-5 bg-white border-top">
  <div class="container">
    <div class="text-center mb-4">
      <span class="text-uppercase small fw-bold" style="color:var(--lum-gold-dark);letter-spacing:0.15em;">Purple Lotus Concierge</span>
      <h2 class="font-serif fw-bold" style="color:var(--lum-navy);">Dịch vụ Đặc quyền 5 Sao</h2>
    </div>
    <div class="row g-4">
      <?php foreach ($services as $srv): ?>
        <div class="col-md-6 col-lg-4">
          <div class="lum-card lum-card-hover h-100 overflow-hidden">
            <div class="lum-img-wrap">
              <img src="<?= e(base_url($srv['image_path'] ?: 'assets/images/services/amthucnhahang1.jpg')) ?>" alt="<?= e($srv['name']) ?>" class="lum-service-img" style="width:100%;height:175px;object-fit:cover;">
            </div>
            <div class="p-3">
              <div class="small fw-semibold text-uppercase mb-1" style="color:var(--lum-gold-dark);"><?= e(service_category_label($srv['category'])) ?></div>
              <h6 class="font-serif fw-bold mb-1" style="color:var(--lum-navy);"><?= e($srv['name']) ?></h6>
              <p class="small text-muted mb-2"><?= e($srv['description']) ?></p>
              <div class="fw-bold" style="color:var(--lum-navy);"><?= e(format_currency($srv['price'])) ?> <span class="fw-normal text-muted small">/ <?= e($srv['unit']) ?></span></div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<footer class="py-4 text-center text-white" style="background:var(--lum-navy);">
  <div class="container small">
    <div class="font-serif fs-5 mb-1" style="color:var(--lum-gold-light);">PURPLE LOTUS HOTEL & RESORT</div>
    <div class="opacity-75">© <?= date('Y') ?> Purple Lotus Hotel Management System · Hải Dương</div>
  </div>
</footer>

<script src="<?= e(base_url('assets/vendor/bootstrap/js/bootstrap.bundle.min.js')) ?>"></script>
<script src="<?= e(base_url('assets/js/app.js?v=' . (@filemtime(ROOT_PATH . '/assets/js/app.js') ?: 1))) ?>"></script>
</body>
</html>
