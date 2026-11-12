<?php
/**
 * Shared Authenticated Layout Footer
 * Lumina Hotel Management System
 */
?>
    </main>

    <footer class="px-4 py-3 border-top bg-white text-muted small d-flex flex-wrap justify-content-between align-items-center no-print">
      <span>© <?= date('Y') ?> <strong style="color:var(--lum-navy);">PURPLE LOTUS HOTEL</strong> — Hệ thống Quản lý Khách sạn Cao cấp</span>
      <span>Chăm sóc khách hàng 24/7 · Hotline: 1900 6868</span>
    </footer>
  </div>
</div>

<script src="<?= e(base_url('assets/vendor/bootstrap/js/bootstrap.bundle.min.js')) ?>"></script>
<script src="<?= e(base_url('assets/js/app.js?v=' . (@filemtime(ROOT_PATH . '/assets/js/app.js') ?: 1))) ?>"></script>
</body>
</html>
