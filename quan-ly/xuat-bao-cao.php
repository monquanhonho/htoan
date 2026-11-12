<?php
/**
 * Admin: Export Financial Report (Excel CSV with UTF-8 BOM)
 * Lumina Hotel Management System
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';
require_role(['admin']);

require_once dirname(__DIR__) . '/models/PaymentModel.php';

$paymentModel = new PaymentModel();
$year = (int)($_GET['year'] ?? date('Y'));
$series   = $paymentModel->getMonthlyChartSeries($year);
$payments = $paymentModel->getPayments([]);
$expenses = $paymentModel->getExpenses();

log_security_event((int)current_user_id(), 'Xuất báo cáo tài chính Excel năm ' . $year, 'action', 'info');

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="purple_lotus_bao_cao_tai_chinh_' . $year . '.csv"');

$out = fopen('php://output', 'w');
// UTF-8 BOM for clean Vietnamese display in Microsoft Excel
fwrite($out, "\xEF\xBB\xBF");

fputcsv($out, ['BAO CAO TAI CHINH KHACH SAN PURPLE LOTUS HOTEL - NAM ' . $year]);
fputcsv($out, []);
fputcsv($out, ['TONG HOP THEO THANG']);
fputcsv($out, ['Thang', 'Doanh thu (VND)', 'Chi phi (VND)', 'Loi nhuan (VND)']);

foreach ($series['labels'] as $idx => $label) {
    $rev = $series['revenues'][$idx];
    $exp = $series['expenses'][$idx];
    fputcsv($out, [$label, $rev, $exp, $rev - $exp]);
}

fputcsv($out, []);
fputcsv($out, ['CHI TIET THANH TOAN']);
fputcsv($out, ['Ma GD', 'Ma Dat phong', 'Khach hang', 'So tien (VND)', 'Phuong thuc', 'Trang thai', 'Ngay thanh toan']);
foreach ($payments as $p) {
    fputcsv($out, [
        $p['payment_code'],
        $p['booking_code'],
        $p['customer_name'],
        $p['amount'],
        $p['payment_method'],
        $p['status'],
        $p['paid_at'] ?: $p['created_at'],
    ]);
}

fputcsv($out, []);
fputcsv($out, ['CHI PHI HOAT DONG']);
fputcsv($out, ['Khoan chi', 'Danh muc', 'So tien (VND)', 'Ngay chi', 'Mo ta']);
foreach ($expenses as $e) {
    fputcsv($out, [
        $e['title'],
        expense_category_label($e['category']),
        $e['amount'],
        $e['expense_date'],
        $e['description'],
    ]);
}

fclose($out);
exit;
