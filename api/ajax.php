<?php
/**
 * AJAX / Fetch JSON API Endpoint
 * Lumina Hotel Management System
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/models/RoomModel.php';
require_once dirname(__DIR__) . '/models/PaymentModel.php';

header('Content-Type: application/json; charset=utf-8');

$action = $_REQUEST['action'] ?? '';

try {
    switch ($action) {
        case 'check_availability':
            $roomId   = (int)($_GET['room_id'] ?? 0);
            $checkIn  = trim((string)($_GET['check_in'] ?? ''));
            $checkOut = trim((string)($_GET['check_out'] ?? ''));

            if ($roomId <= 0 || $checkIn === '' || $checkOut === '') {
                echo json_encode(['ok' => true, 'available' => false, 'message' => 'Vui lòng chọn đầy đủ ngày nhận và trả phòng.']);
                exit;
            }
            if ($checkIn < date('Y-m-d')) {
                echo json_encode(['ok' => true, 'available' => false, 'message' => 'Ngày nhận phòng phải từ hôm nay trở đi.']);
                exit;
            }
            $nights = calculate_nights($checkIn, $checkOut);
            if ($nights <= 0) {
                echo json_encode(['ok' => true, 'available' => false, 'message' => 'Ngày trả phòng phải lớn hơn ngày nhận phòng ít nhất 1 đêm.']);
                exit;
            }

            $roomModel = new RoomModel();
            $room = $roomModel->findRoom($roomId);
            if (!$room) {
                echo json_encode(['ok' => true, 'available' => false, 'message' => 'Phòng không tồn tại.']);
                exit;
            }

            $available = $roomModel->isRoomAvailable($roomId, $checkIn, $checkOut);
            echo json_encode([
                'ok'              => true,
                'available'       => $available,
                'nights'          => $nights,
                'price_per_night' => (float)$room['price_per_night'],
                'total_amount'    => $nights * (float)$room['price_per_night'],
                'message'         => $available ? 'Phòng sẵn sàng.' : 'Phòng đã có khách đặt trong khoảng thời gian này hoặc đang bảo trì.',
            ]);
            break;

        case 'revenue_series':
            if (!is_logged_in() || current_role() !== 'admin') {
                http_response_code(403);
                echo json_encode(['ok' => false, 'message' => 'Không có quyền truy cập.']);
                exit;
            }
            $year = (int)($_GET['year'] ?? date('Y'));
            $paymentModel = new PaymentModel();
            $series = $paymentModel->getMonthlyChartSeries($year);
            echo json_encode(['ok' => true, 'data' => $series]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['ok' => false, 'message' => 'Yêu cầu API không hợp lệ.']);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}
