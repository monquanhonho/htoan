<?php
/**
 * Role-Aware Left Navigation Sidebar
 * Lumina Hotel Management System
 */

$user = current_user();
$role = $user['role'] ?? 'customer';
$currentScript = basename($_SERVER['PHP_SELF'] ?? '');

$navSections = [];

if ($role === 'admin') {
    $navSections = [
        'Tổng quan' => [
            ['label' => 'Dashboard Quản trị', 'icon' => 'fa-chart-pie', 'href' => 'quan-ly/bang-dieu-khien.php', 'key' => 'dashboard.php'],
        ],
        'Vận hành Khách sạn' => [
            ['label' => 'Quản lý Đặt phòng', 'icon' => 'fa-calendar-check', 'href' => 'quan-ly/dat-phong.php', 'key' => 'bookings.php'],
            ['label' => 'Quản lý Phòng & Hạng phòng', 'icon' => 'fa-bed', 'href' => 'quan-ly/quan-ly-phong.php', 'key' => 'rooms.php'],
            ['label' => 'Quản lý Dịch vụ', 'icon' => 'fa-bell-concierge', 'href' => 'quan-ly/dich-vu.php', 'key' => 'services.php'],
            ['label' => 'Quản lý Khách hàng', 'icon' => 'fa-users', 'href' => 'quan-ly/khach-hang.php', 'key' => 'customers.php'],
        ],
        'Quản trị & Tài chính' => [
            ['label' => 'Nhân sự & Phân công', 'icon' => 'fa-user-tie', 'href' => 'quan-ly/nhan-su.php', 'key' => 'employees.php'],
            ['label' => 'Tài chính & Báo cáo', 'icon' => 'fa-coins', 'href' => 'quan-ly/tai-chinh.php', 'key' => 'finance.php'],
            ['label' => 'An ninh & Nhật ký', 'icon' => 'fa-shield-halved', 'href' => 'quan-ly/an-ninh.php', 'key' => 'security.php'],
        ],
        'Nghiệp vụ Lễ tân' => [
            ['label' => 'Tiếp đón & Check-in/Out', 'icon' => 'fa-door-open', 'href' => 'nhan-su/tiep-don.php', 'key' => 'reception.php'],
            ['label' => 'Vệ sinh & Bảo trì', 'icon' => 'fa-broom', 'href' => 'nhan-su/ve-sinh-phong.php', 'key' => 'cleaning.php'],
        ],
    ];
} elseif ($role === 'staff') {
    $navSections = [
        'Tổng quan' => [
            ['label' => 'Dashboard Nhân sự', 'icon' => 'fa-gauge-high', 'href' => 'nhan-su/bang-dieu-khien.php', 'key' => 'dashboard.php'],
        ],
        'Lễ tân & Lưu trú' => [
            ['label' => 'Tiếp đón (Check-in/Out)', 'icon' => 'fa-key', 'href' => 'nhan-su/tiep-don.php', 'key' => 'reception.php'],
            ['label' => 'Tra cứu Đặt phòng', 'icon' => 'fa-calendar-days', 'href' => 'nhan-su/dat-phong.php', 'key' => 'bookings.php'],
            ['label' => 'Tình trạng Phòng', 'icon' => 'fa-hotel', 'href' => 'nhan-su/tinh-trang-phong.php', 'key' => 'rooms.php'],
            ['label' => 'Xác nhận Thanh toán', 'icon' => 'fa-money-bill-wave', 'href' => 'nhan-su/thanh-toan.php', 'key' => 'payments.php'],
            ['label' => 'Yêu cầu Dịch vụ', 'icon' => 'fa-martini-glass-citrus', 'href' => 'nhan-su/dich-vu.php', 'key' => 'services.php'],
        ],
        'Buồng phòng & Hỗ trợ' => [
            ['label' => 'Vệ sinh Phòng', 'icon' => 'fa-broom', 'href' => 'nhan-su/ve-sinh-phong.php', 'key' => 'cleaning.php'],
            ['label' => 'Bảo trì Thiết bị', 'icon' => 'fa-screwdriver-wrench', 'href' => 'nhan-su/bao-tri.php', 'key' => 'maintenance.php'],
            ['label' => 'Hỗ trợ Khách hàng', 'icon' => 'fa-headset', 'href' => 'nhan-su/ho-tro.php', 'key' => 'support.php'],
            ['label' => 'Công việc được giao', 'icon' => 'fa-list-check', 'href' => 'nhan-su/cong-viec.php', 'key' => 'tasks.php'],
        ],
    ];
} else {
    $navSections = [
        'Khám phá' => [
            ['label' => 'Trang chủ Khách hàng', 'icon' => 'fa-house-chimney', 'href' => 'khach-hang/trang-chu.php', 'key' => 'dashboard.php'],
            ['label' => 'Tìm & Đặt phòng', 'icon' => 'fa-bed', 'href' => 'khach-hang/danh-sach-phong.php', 'key' => 'rooms.php'],
            ['label' => 'Dịch vụ Khách sạn', 'icon' => 'fa-spa', 'href' => 'khach-hang/dich-vu.php', 'key' => 'services.php'],
        ],
        'Lưu trú của tôi' => [
            ['label' => 'Đặt phòng của tôi', 'icon' => 'fa-calendar-check', 'href' => 'khach-hang/dat-phong.php', 'key' => 'bookings.php'],
            ['label' => 'Lịch sử Thanh toán', 'icon' => 'fa-receipt', 'href' => 'khach-hang/thanh-toan.php', 'key' => 'payments.php'],
            ['label' => 'Yêu cầu Hỗ trợ', 'icon' => 'fa-comments', 'href' => 'khach-hang/ho-tro.php', 'key' => 'support.php'],
        ],
        'Tài khoản' => [
            ['label' => 'Hồ sơ & Đổi mật khẩu', 'icon' => 'fa-user-gear', 'href' => 'khach-hang/ho-so.php', 'key' => 'profile.php'],
        ],
    ];
}
?>
<div class="lum-sidebar-backdrop" id="lumSidebarBackdrop"></div>
<aside class="lum-sidebar" id="lumSidebar">
  <a href="<?= e(base_url(role_dashboard_path($role))) ?>" class="lum-sidebar-brand">
    <img src="<?= e(base_url(APP_LOGO)) ?>" alt="Purple Lotus Hotel" class="lum-brand-logo">
    <div>
      <div class="lum-brand-title" style="font-size:0.95rem;letter-spacing:0.08em;">PURPLE LOTUS</div>
      <div class="lum-brand-sub">Luxury Hotel</div>
    </div>
  </a>

  <nav class="lum-sidebar-nav">
    <?php foreach ($navSections as $sectionName => $links): ?>
      <div class="lum-nav-section"><?= e($sectionName) ?></div>
      <?php foreach ($links as $item): ?>
        <?php
          $isActive = ($currentScript === basename($item['href']))
            && str_contains($_SERVER['PHP_SELF'] ?? '', dirname($item['href']));
        ?>
        <a href="<?= e(base_url($item['href'])) ?>" class="lum-nav-link <?= $isActive ? 'active' : '' ?>">
          <i class="fa-solid <?= e($item['icon']) ?>"></i>
          <span><?= e($item['label']) ?></span>
        </a>
      <?php endforeach; ?>
    <?php endforeach; ?>
  </nav>

  <div class="lum-sidebar-footer">
    <div class="d-flex align-items-center justify-content-between">
      <span><i class="fa-solid fa-crown text-warning me-1"></i>Purple Lotus PMS</span>
      <a href="<?= e(base_url('index.php')) ?>" class="text-decoration-none" style="color:var(--lum-gold-light);" title="Trang giới thiệu">
        <i class="fa-solid fa-globe"></i>
      </a>
    </div>
  </div>
</aside>
