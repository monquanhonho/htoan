<?php
/**
 * Customer: Order Hotel Services & Track Costs
 * Lumina Hotel Management System
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';
require_role(['customer']);

require_once dirname(__DIR__) . '/models/ServiceModel.php';
require_once dirname(__DIR__) . '/models/BookingModel.php';

$user = current_user();
$customerId = (int)$user['customer_id'];

$serviceModel = new ServiceModel();
$bookingModel = new BookingModel();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf('khach-hang/dich-vu.php');
    try {
        $action = $_POST['action'] ?? '';
        if ($action === 'order_service') {
            $orderId = $serviceModel->createServiceOrder(
                (int)$_POST['booking_id'],
                $customerId,
                (int)$_POST['service_id'],
                (int)$_POST['quantity'],
                $_POST['requested_time'] ?? null,
                trim((string)($_POST['notes'] ?? ''))
            );
            log_security_event((int)$user['id'], 'Khách đăng ký sử dụng dịch vụ #' . $orderId, 'action', 'info');
            set_flash('success', 'Đã gửi yêu cầu sử dụng dịch vụ! Nhân viên sẽ tiếp nhận và thực hiện sớm nhất.');
        } elseif ($action === 'cancel_order') {
            $orderId = (int)$_POST['order_id'];
            $orders = $serviceModel->getServiceOrders(['customer_id' => $customerId]);
            $owned = array_filter($orders, fn($o) => (int)$o['id'] === $orderId && $o['status'] === 'pending');
            if (empty($owned)) {
                throw new RuntimeException('Chỉ có thể hủy yêu cầu dịch vụ đang ở trạng thái chờ.');
            }
            $serviceModel->updateServiceOrderStatus($orderId, 'cancelled', (int)$user['id']);
            set_flash('success', 'Đã hủy yêu cầu dịch vụ.');
        }
    } catch (Throwable $e) {
        set_flash('danger', $e->getMessage());
    }
    redirect('khach-hang/dich-vu.php');
}

$catFilter = trim((string)($_GET['category'] ?? ''));
$services = $serviceModel->getAllServices(true, $catFilter);
$myBookings = array_filter(
    $bookingModel->getBookings(['customer_id' => $customerId]),
    fn($b) => in_array($b['status'], ['confirmed', 'checked_in'], true)
);
$myOrders = $serviceModel->getServiceOrders(['customer_id' => $customerId]);

$confirmedServiceTotal = array_sum(array_map(
    fn($o) => in_array($o['status'], ['confirmed', 'in_progress', 'completed'], true) ? (float)$o['total_price'] : 0.0,
    $myOrders
));

$pageTitle = 'Dịch vụ Khách sạn';
include dirname(__DIR__) . '/includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
  <div class="d-flex flex-wrap gap-2">
    <?php
      $cats = ['' => 'Tất cả', 'dining' => 'Ẩm thực', 'laundry' => 'Giặt ủi', 'spa' => 'Spa', 'transport' => 'Đưa đón'];
      foreach ($cats as $k => $label):
    ?>
      <a href="<?= e(base_url('khach-hang/dich-vu.php' . ($k ? '?category=' . $k : ''))) ?>"
         class="btn btn-sm rounded-pill px-3 <?= $catFilter === $k ? 'btn-lum-primary' : 'btn-light border' ?>">
        <?= e($label) ?>
      </a>
    <?php endforeach; ?>
  </div>
  <div class="px-3 py-2 rounded-pill bg-white border small">
    Tổng chi phí dịch vụ đã xác nhận: <strong style="color:var(--lum-navy);"><?= e(format_currency($confirmedServiceTotal)) ?></strong>
  </div>
</div>

<!-- Service Catalog -->
<div class="row g-4 mb-5">
  <?php foreach ($services as $srv): ?>
    <div class="col-md-6 col-xl-4">
      <div class="lum-card lum-card-hover h-100 d-flex flex-column overflow-hidden">
        <div class="lum-img-wrap">
          <img src="<?= e(base_url($srv['image_path'] ?: 'assets/images/services/amthucnhahang1.jpg')) ?>" alt="<?= e($srv['name']) ?>" class="lum-service-img" style="width:100%;height:175px;object-fit:cover;">
        </div>
        <div class="p-4 flex-grow-1 d-flex flex-column">
          <div class="small text-uppercase fw-semibold mb-1" style="color:var(--lum-gold-dark);"><?= e(service_category_label($srv['category'])) ?></div>
          <h6 class="font-serif fw-bold mb-1" style="color:var(--lum-navy);"><?= e($srv['name']) ?></h6>
          <p class="small text-muted flex-grow-1"><?= e($srv['description']) ?></p>
          <div class="d-flex justify-content-between align-items-center pt-3 border-top">
            <div>
              <span class="fw-bold fs-5" style="color:var(--lum-navy);"><?= e(format_currency($srv['price'])) ?></span>
              <span class="small text-muted">/ <?= e($srv['unit']) ?></span>
            </div>
            <?php if (!empty($myBookings)): ?>
              <button class="btn btn-sm btn-lum-gold rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#orderModal<?= (int)$srv['id'] ?>">
                Đặt dịch vụ
              </button>
            <?php else: ?>
              <span class="small text-muted">Cần có phòng đã xác nhận</span>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <?php if (!empty($myBookings)): ?>
      <div class="modal fade" id="orderModal<?= (int)$srv['id'] ?>" tabindex="-1">
        <div class="modal-dialog">
          <form method="POST" class="modal-content">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="order_service">
            <input type="hidden" name="service_id" value="<?= (int)$srv['id'] ?>">
            <div class="modal-header">
              <h6 class="modal-title font-serif fw-bold"><?= e($srv['name']) ?></h6>
              <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
              <div class="mb-3">
                <label class="form-label small fw-semibold">Áp dụng cho Đặt phòng *</label>
                <select name="booking_id" class="form-select" required>
                  <?php foreach ($myBookings as $mb): ?>
                    <option value="<?= (int)$mb['id'] ?>">
                      <?= e($mb['booking_code']) ?> — Phòng <?= e($mb['room_number']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="row g-3 mb-3">
                <div class="col-5">
                  <label class="form-label small fw-semibold">Số lượng (<?= e($srv['unit']) ?>)</label>
                  <input type="number" name="quantity" class="form-control" value="1" min="1" max="20" required>
                </div>
                <div class="col-7">
                  <label class="form-label small fw-semibold">Thời gian phục vụ mong muốn</label>
                  <input type="datetime-local" name="requested_time" class="form-control" value="<?= date('Y-m-d\TH:i') ?>">
                </div>
              </div>
              <div>
                <label class="form-label small fw-semibold">Ghi chú thêm</label>
                <textarea name="notes" class="form-control" rows="2" placeholder="Yêu cầu riêng cho nhân viên..."></textarea>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal">Đóng</button>
              <button type="submit" class="btn btn-sm btn-lum-gold px-4">Gửi Yêu cầu (<?= e(format_currency($srv['price'])) ?>/<?= e($srv['unit']) ?>)</button>
            </div>
          </form>
        </div>
      </div>
    <?php endif; ?>
  <?php endforeach; ?>
</div>

<!-- Service Orders History -->
<div class="lum-card">
  <div class="lum-card-header">
    <h6 class="mb-0 font-serif fw-bold" style="color:var(--lum-navy);">Theo dõi Chi phí & Yêu cầu Dịch vụ đã đặt</h6>
  </div>
  <div class="table-responsive">
    <table class="table lum-table">
      <thead>
        <tr>
          <th>Dịch vụ</th>
          <th>Mã đơn / Phòng</th>
          <th>Số lượng</th>
          <th>Thành tiền</th>
          <th>Thời gian yêu cầu</th>
          <th>Trạng thái</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($myOrders)): ?>
          <tr><td colspan="7" class="text-center py-4 text-muted">Bạn chưa sử dụng dịch vụ nào.</td></tr>
        <?php else: ?>
          <?php foreach ($myOrders as $o): ?>
            <tr>
              <td>
                <div class="fw-semibold"><?= e($o['service_name']) ?></div>
                <?php if ($o['notes']): ?><div class="small text-muted"><?= e($o['notes']) ?></div><?php endif; ?>
              </td>
              <td class="small"><?= e($o['booking_code']) ?> · Phòng <?= e($o['room_number']) ?></td>
              <td><?= (int)$o['quantity'] ?> <?= e($o['unit']) ?></td>
              <td class="fw-bold"><?= e(format_currency($o['total_price'])) ?></td>
              <td class="small"><?= e(format_datetime($o['requested_time'])) ?></td>
              <td><?= status_badge($o['status'], 'service') ?></td>
              <td class="text-end">
                <?php if ($o['status'] === 'pending'): ?>
                  <form method="POST" class="d-inline" onsubmit="return confirm('Hủy yêu cầu dịch vụ này?')">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="cancel_order">
                    <input type="hidden" name="order_id" value="<?= (int)$o['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger">Hủy</button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include dirname(__DIR__) . '/includes/footer.php'; ?>
