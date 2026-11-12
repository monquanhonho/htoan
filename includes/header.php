<?php
/**
 * Shared Authenticated Layout Header
 * Lumina Hotel Management System
 */

require_once dirname(__DIR__) . '/models/OperationsModel.php';

$pageTitle = $pageTitle ?? 'Dashboard';
$user = current_user();
$roleLabel = match ($user['role'] ?? 'customer') {
    'admin' => 'Quản lý',
    'staff' => 'Nhân sự',
    default => 'Khách hàng',
};

$opsModel = new OperationsModel();
$notifications = $user ? $opsModel->getNotificationsForUser($user) : [];
$initials = mb_strtoupper(mb_substr(trim((string)($user['full_name'] ?? 'L')), 0, 1, 'UTF-8'), 'UTF-8');
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
  <script src="<?= e(base_url('assets/vendor/chartjs/chart.umd.min.js')) ?>"></script>
</head>
<body>
<div class="lum-wrapper">
  <?php include __DIR__ . '/sidebar.php'; ?>

  <div class="lum-main">
    <header class="lum-header no-print">
      <div class="d-flex align-items-center gap-3">
        <button class="btn btn-sm btn-outline-secondary d-lg-none" id="lumSidebarToggle" type="button" aria-label="Mở menu">
          <i class="fa-solid fa-bars"></i>
        </button>
        <div>
          <h5 class="mb-0 fw-bold font-serif" style="color:var(--lum-navy);"><?= e($pageTitle) ?></h5>
          <small class="text-muted d-none d-sm-inline"><?= e(APP_NAME) ?> · <?= e(date('d/m/Y')) ?></small>
        </div>
      </div>

      <div class="d-flex align-items-center gap-3">
        <!-- Notifications Dropdown -->
        <div class="dropdown">
          <button class="btn btn-light position-relative rounded-circle border" style="width:40px;height:40px;" data-bs-toggle="dropdown" aria-expanded="false" title="Thông báo">
            <i class="fa-regular fa-bell" style="color:var(--lum-navy);"></i>
            <?php if (count($notifications) > 0): ?>
              <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:0.65rem;">
                <?= count($notifications) ?>
              </span>
            <?php endif; ?>
          </button>
          <div class="dropdown-menu dropdown-menu-end shadow-lg border-0 p-0" style="width:320px;border-radius:12px;overflow:hidden;">
            <div class="px-3 py-2 text-white d-flex justify-content-between align-items-center" style="background:var(--lum-navy);">
              <span class="fw-semibold small">Thông báo hệ thống</span>
              <span class="badge bg-warning text-dark"><?= count($notifications) ?></span>
            </div>
            <?php if (empty($notifications)): ?>
              <div class="p-3 text-center text-muted small">Không có thông báo mới.</div>
            <?php else: ?>
              <div class="list-group list-group-flush">
                <?php foreach ($notifications as $n): ?>
                  <a href="<?= e(base_url($n['link'])) ?>" class="list-group-item list-group-item-action py-2 px-3 d-flex align-items-center gap-2 small">
                    <i class="fa-solid <?= e($n['icon']) ?> text-<?= e($n['color']) ?>"></i>
                    <span><?= e($n['text']) ?></span>
                  </a>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Account Info -->
        <div class="d-flex align-items-center gap-2 ps-2 border-start">
          <div class="lum-avatar"><?= e($initials) ?></div>
          <div class="d-none d-md-block lh-sm">
            <div class="fw-semibold small" style="color:var(--lum-navy);"><?= e($user['full_name'] ?? '') ?></div>
            <span class="badge rounded-pill" style="background:rgba(201,169,110,0.22);color:var(--lum-navy);font-size:0.68rem;">
              <?= e($roleLabel) ?>
            </span>
          </div>
        </div>

        <a href="<?= e(base_url('dang-xuat.php')) ?>" class="btn btn-sm btn-lum-outline rounded-pill px-3" title="Đăng xuất">
          <i class="fa-solid fa-right-from-bracket me-1"></i>
          <span class="d-none d-sm-inline">Đăng xuất</span>
        </a>
      </div>
    </header>

    <main class="lum-content">
      <?php foreach (get_flashes() as $flash): ?>
        <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show alert-auto-dismiss shadow-sm no-print" role="alert">
          <?= e($flash['message']) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
      <?php endforeach; ?>
