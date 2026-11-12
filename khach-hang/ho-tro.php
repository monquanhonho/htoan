<?php
/**
 * Customer: Support Requests & Feedback
 * Lumina Hotel Management System
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';
require_role(['customer']);

require_once dirname(__DIR__) . '/models/OperationsModel.php';
require_once dirname(__DIR__) . '/models/BookingModel.php';

$user = current_user();
$customerId = (int)$user['customer_id'];

$opsModel = new OperationsModel();
$bookingModel = new BookingModel();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('khach-hang/ho-tro.php');
    try {
        $subject = trim((string)($_POST['subject'] ?? ''));
        $message = trim((string)($_POST['message'] ?? ''));
        if ($subject === '' || $message === '') {
            throw new RuntimeException('Vui lòng nhập tiêu đề và nội dung yêu cầu.');
        }

        $opsModel->createSupportRequest([
            'customer_id' => $customerId,
            'booking_id'  => $_POST['booking_id'] ?: null,
            'subject'     => $subject,
            'category'    => $_POST['category'] ?? 'inquiry',
            'message'     => $message,
        ]);
        log_security_event((int)$user['id'], 'Khách hàng gửi yêu cầu hỗ trợ: ' . $subject, 'action', 'info');
        set_flash('success', 'Đã gửi yêu cầu hỗ trợ đến bộ phận CSKH Purple Lotus Hotel.');
    } catch (Throwable $e) {
        set_flash('danger', $e->getMessage());
    }
    redirect('khach-hang/ho-tro.php');
}

$bookings = $bookingModel->getBookings(['customer_id' => $customerId]);
$tickets  = $opsModel->getSupportRequests($customerId);

$pageTitle = 'Yêu cầu Hỗ trợ & Phản ánh';
include dirname(__DIR__) . '/includes/header.php';
?>

<div class="row g-4">
  <div class="col-lg-5">
    <div class="lum-card">
      <div class="lum-card-header">
        <h6 class="mb-0 font-serif fw-bold" style="color:var(--lum-navy);">Gửi Yêu cầu / Phản ánh mới</h6>
      </div>
      <div class="lum-card-body">
        <form method="POST">
          <?= csrf_field() ?>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Chủ đề hỗ trợ *</label>
            <select name="category" class="form-select">
              <option value="inquiry">Yêu cầu thông tin / Tư vấn</option>
              <option value="room_issue">Vấn đề trong phòng lưu trú</option>
              <option value="billing">Hóa đơn & Thanh toán</option>
              <option value="complaint">Phản ánh chất lượng dịch vụ</option>
              <option value="feedback">Góp ý & Đánh giá</option>
              <option value="other">Khác</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Liên quan đến đặt phòng (tùy chọn)</label>
            <select name="booking_id" class="form-select">
              <option value="">-- Không chọn --</option>
              <?php foreach ($bookings as $b): ?>
                <option value="<?= (int)$b['id'] ?>"><?= e($b['booking_code']) ?> (Phòng <?= e($b['room_number']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Tiêu đề *</label>
            <input type="text" name="subject" class="form-control" placeholder="VD: Yêu cầu thêm gối phụ, xuất hóa đơn VAT..." required>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Nội dung chi tiết *</label>
            <textarea name="message" class="form-control" rows="4" required placeholder="Mô tả chi tiết yêu cầu của quý khách..."></textarea>
          </div>
          <button type="submit" class="btn btn-lum-primary w-100 rounded-pill py-2">
            <i class="fa-solid fa-paper-plane me-2"></i>Gửi Yêu cầu
          </button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-7">
    <div class="lum-card">
      <div class="lum-card-header">
        <h6 class="mb-0 font-serif fw-bold" style="color:var(--lum-navy);">Lịch sử Yêu cầu & Phản hồi</h6>
      </div>
      <div class="lum-card-body">
        <?php if (empty($tickets)): ?>
          <p class="text-center text-muted py-4 small">Bạn chưa gửi yêu cầu hỗ trợ nào.</p>
        <?php else: ?>
          <div class="d-grid gap-3">
            <?php foreach ($tickets as $t): ?>
              <div class="p-3 rounded-3 border" style="background:var(--lum-cream);">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                  <div>
                    <span class="badge bg-dark me-1"><?= e($t['ticket_code']) ?></span>
                    <strong style="color:var(--lum-navy);"><?= e($t['subject']) ?></strong>
                    <?php if ($t['booking_code']): ?>
                      <span class="small text-muted ms-1">(<?= e($t['booking_code']) ?>)</span>
                    <?php endif; ?>
                  </div>
                  <?= status_badge($t['status'], 'support') ?>
                </div>
                <p class="small mb-2"><?= e($t['message']) ?></p>
                <div class="small text-muted mb-2">Gửi lúc: <?= e(format_datetime($t['created_at'])) ?></div>
                <?php if ($t['staff_response']): ?>
                  <div class="p-2 rounded bg-white border-start border-3 border-warning small">
                    <div class="fw-semibold text-dark"><i class="fa-solid fa-headset me-1 text-warning"></i>Phản hồi từ Khách sạn (<?= e($t['handler_name'] ?: 'Nhân viên') ?>):</div>
                    <div><?= e($t['staff_response']) ?></div>
                  </div>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
