<?php
/**
 * Staff: Room Status Board
 * Lumina Hotel Management System
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';
require_role(['staff', 'admin']);

require_once dirname(__DIR__) . '/models/RoomModel.php';

$roomModel = new RoomModel();
$summary = $roomModel->getStatusSummary();

$status = trim((string)($_GET['status'] ?? ''));
$floor  = trim((string)($_GET['floor'] ?? ''));
$rooms  = $roomModel->searchRooms(['status' => $status, 'floor' => $floor]);

$pageTitle = 'Kiểm tra Tình trạng Phòng';
include dirname(__DIR__) . '/includes/header.php';
?>

<div class="lum-card mb-4">
  <div class="lum-card-body d-flex flex-wrap justify-content-between align-items-center gap-3">
    <form method="GET" class="d-flex flex-wrap gap-2">
      <select name="status" class="form-select form-select-sm" style="width:170px;" onchange="this.form.submit()">
        <option value="">Tất cả trạng thái</option>
        <option value="available" <?= $status === 'available' ? 'selected' : '' ?>>Trống (<?= (int)$summary['available'] ?>)</option>
        <option value="reserved" <?= $status === 'reserved' ? 'selected' : '' ?>>Đã đặt (<?= (int)$summary['reserved'] ?>)</option>
        <option value="occupied" <?= $status === 'occupied' ? 'selected' : '' ?>>Đang sử dụng (<?= (int)$summary['occupied'] ?>)</option>
        <option value="cleaning" <?= $status === 'cleaning' ? 'selected' : '' ?>>Cần vệ sinh (<?= (int)$summary['cleaning'] ?>)</option>
        <option value="maintenance" <?= $status === 'maintenance' ? 'selected' : '' ?>>Bảo trì (<?= (int)$summary['maintenance'] ?>)</option>
      </select>
      <select name="floor" class="form-select form-select-sm" style="width:130px;" onchange="this.form.submit()">
        <option value="">Mọi tầng</option>
        <?php for ($f = 1; $f <= 5; $f++): ?>
          <option value="<?= $f ?>" <?= $floor === (string)$f ? 'selected' : '' ?>>Tầng <?= $f ?></option>
        <?php endfor; ?>
      </select>
    </form>
    <div class="d-flex gap-2">
      <a href="<?= e(base_url('nhan-su/ve-sinh-phong.php')) ?>" class="btn btn-sm btn-outline-warning text-dark">Vệ sinh phòng</a>
      <a href="<?= e(base_url('nhan-su/bao-tri.php')) ?>" class="btn btn-sm btn-outline-danger">Báo bảo trì</a>
    </div>
  </div>
</div>

<div class="row g-3">
  <?php foreach ($rooms as $r): ?>
    <div class="col-sm-6 col-lg-4 col-xl-3">
      <div class="lum-card lum-card-hover h-100 p-3">
        <div class="d-flex justify-content-between align-items-start mb-2">
          <div>
            <span class="small text-muted">Tầng <?= (int)$r['floor'] ?></span>
            <h4 class="font-serif fw-bold mb-0" style="color:var(--lum-navy);">P.<?= e($r['room_number']) ?></h4>
          </div>
          <?= status_badge($r['status'], 'room') ?>
        </div>
        <div class="fw-semibold small mb-1"><?= e($r['type_name']) ?></div>
        <div class="small text-muted mb-2"><?= e(format_currency($r['price_per_night'])) ?> / đêm</div>
        <div class="small text-secondary border-top pt-2"><?= e($r['amenities']) ?></div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
