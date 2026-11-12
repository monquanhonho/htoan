<?php
/**
 * Booking & Front-Desk Controller
 * Lumina Hotel Management System
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/models/BookingModel.php';
require_once dirname(__DIR__) . '/models/RoomModel.php';

class BookingController
{
    private BookingModel $bookingModel;

    public function __construct()
    {
        $this->bookingModel = new BookingModel();
    }

    public function createCustomerBooking(int $customerId, int $userId, array $input): array
    {
        $roomId   = (int)($input['room_id'] ?? 0);
        $checkIn  = trim((string)($input['check_in_date'] ?? ''));
        $checkOut = trim((string)($input['check_out_date'] ?? ''));
        $guests   = (int)($input['num_guests'] ?? 1);
        $requests = trim((string)($input['special_requests'] ?? ''));

        if ($roomId <= 0 || $checkIn === '' || $checkOut === '') {
            throw new RuntimeException('Vui lòng chọn phòng, ngày nhận phòng và ngày trả phòng.');
        }

        if ($checkIn < date('Y-m-d')) {
            throw new RuntimeException('Ngày nhận phòng không thể nhỏ hơn ngày hôm nay.');
        }

        $result = $this->bookingModel->createBooking([
            'room_id'          => $roomId,
            'customer_id'      => $customerId,
            'check_in_date'    => $checkIn,
            'check_out_date'   => $checkOut,
            'num_guests'       => $guests,
            'special_requests' => $requests,
            'initial_status'   => 'pending',
        ]);

        log_security_event(
            $userId,
            'Tạo đơn đặt phòng mới: ' . $result['booking_code'],
            'action',
            'info',
            sprintf('Phòng #%d, từ %s đến %s (%d đêm)', $roomId, $checkIn, $checkOut, $result['nights'])
        );

        return $result;
    }

    public function customerCancel(int $bookingId, int $customerId, int $userId, string $reason = ''): void
    {
        $booking = $this->bookingModel->findBooking($bookingId);
        if (!$booking || (int)$booking['customer_id'] !== $customerId) {
            throw new RuntimeException('Bạn không có quyền thao tác trên đơn đặt phòng này.');
        }

        // Cancellation policy: allowed when pending, or confirmed before check-in date
        if ($booking['status'] === 'confirmed' && $booking['check_in_date'] <= date('Y-m-d')) {
            throw new RuntimeException('Theo quy định, không thể tự hủy đơn đã xác nhận vào hoặc sau ngày nhận phòng. Vui lòng liên hệ lễ tân.');
        }

        $this->bookingModel->cancelOrRejectBooking($bookingId, 'cancelled', $reason ?: 'Khách hàng chủ động hủy');
        log_security_event($userId, 'Khách hàng hủy đặt phòng: ' . $booking['booking_code'], 'action', 'info', $reason);
    }

    public function staffConfirm(int $bookingId, int $staffUserId): void
    {
        $booking = $this->bookingModel->findBooking($bookingId);
        if (!$booking) {
            throw new RuntimeException('Không tìm thấy đơn đặt phòng.');
        }
        $this->bookingModel->confirmBooking($bookingId, $staffUserId);
        log_security_event($staffUserId, 'Xác nhận đặt phòng: ' . $booking['booking_code'], 'action', 'info');
    }

    public function staffReject(int $bookingId, int $staffUserId, string $reason = ''): void
    {
        $booking = $this->bookingModel->findBooking($bookingId);
        if (!$booking) {
            throw new RuntimeException('Không tìm thấy đơn đặt phòng.');
        }
        $this->bookingModel->cancelOrRejectBooking($bookingId, 'rejected', $reason ?: 'Khách sạn từ chối đơn');
        log_security_event($staffUserId, 'Từ chối đặt phòng: ' . $booking['booking_code'], 'action', 'warning', $reason);
    }

    public function performCheckIn(int $bookingId, int $staffUserId, bool $idVerified, float $deposit, string $notes): void
    {
        $booking = $this->bookingModel->findBooking($bookingId);
        if (!$booking) {
            throw new RuntimeException('Không tìm thấy đơn đặt phòng.');
        }
        $this->bookingModel->checkIn($bookingId, $staffUserId, $idVerified, $deposit, $notes);
        log_security_event(
            $staffUserId,
            'Thực hiện Check-in đơn ' . $booking['booking_code'] . ' (Phòng ' . $booking['room_number'] . ')',
            'action',
            'info',
            'Tiền cọc: ' . format_currency($deposit)
        );
    }

    public function performCheckOut(int $bookingId, int $staffUserId, string $notes): array
    {
        $updated = $this->bookingModel->checkOut($bookingId, $staffUserId, $notes);
        log_security_event(
            $staffUserId,
            'Thực hiện Check-out đơn ' . $updated['booking_code'] . ' (Phòng ' . $updated['room_number'] . ')',
            'action',
            'info',
            'Tổng hóa đơn: ' . format_currency($updated['total_amount']) . ' | Phòng chuyển sang Cần vệ sinh'
        );
        return $updated;
    }
}
