<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_permission('reports.view');

require_once __DIR__ . '/../../includes/pdf.php';

$db = db();
$type = $_GET['type'] ?? 'daily';
if (!in_array($type, ['daily', 'monthly', 'service', 'transport'], true)) $type = 'daily';
$month = $_GET['month'] ?? date('Y-m');
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');

$money = static fn($n): string => 'Rp ' . number_format((float)$n, 0, ',', '.');
$pdf = new PDFGenerator();
$title = '';
$headers = $rows = $widths = $aligns = [];

if ($type === 'daily') {
    $stmt = $db->prepare("SELECT DATE(p.paid_at) AS tgl, COUNT(DISTINCT p.order_id) AS order_count, SUM(CASE WHEN p.method='tunai' THEN p.amount ELSE 0 END) AS tunai, SUM(CASE WHEN p.method='transfer' THEN p.amount ELSE 0 END) AS transfer, SUM(p.amount) AS total FROM payments p WHERE DATE(p.paid_at) BETWEEN ? AND ? GROUP BY DATE(p.paid_at) ORDER BY tgl DESC");
    $stmt->bind_param('ss', $date_from, $date_to);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $title = "Laporan Harian {$date_from} - {$date_to}";
    $headers = ['Tanggal', 'Order', 'Tunai', 'Transfer', 'Total'];
    $widths = [90, 55, 115, 115, 115];
    $aligns = ['left', 'center', 'right', 'right', 'right'];
    foreach ($data as $r) $rows[] = [$r['tgl'], $r['order_count'], $money($r['tunai']), $money($r['transfer']), $money($r['total'])];
    $rows[] = ['Grand Total', '', '', '', $money(array_sum(array_column($data, 'total')))];
} elseif ($type === 'monthly') {
    [$yr, $mo] = array_map('intval', explode('-', $month));
    $stmt = $db->prepare("SELECT DATE(p.paid_at) AS tgl, COUNT(DISTINCT p.order_id) AS order_count, SUM(p.amount) AS total FROM payments p WHERE YEAR(p.paid_at)=? AND MONTH(p.paid_at)=? GROUP BY DATE(p.paid_at) ORDER BY tgl ASC");
    $stmt->bind_param('ii', $yr, $mo);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $title = "Laporan Bulanan {$month}";
    $headers = ['Tanggal', 'Order', 'Pendapatan'];
    $widths = [180, 100, 210];
    $aligns = ['left', 'center', 'right'];
    foreach ($data as $r) $rows[] = [$r['tgl'], $r['order_count'], $money($r['total'])];
    $rows[] = ['Grand Total', array_sum(array_column($data, 'order_count')), $money(array_sum(array_column($data, 'total')))];
} elseif ($type === 'service') {
    $stmt = $db->prepare("SELECT s.name, s.type, s.unit, COUNT(DISTINCT o.id) AS order_count, SUM(oi.quantity) AS total_qty, SUM(oi.subtotal) AS revenue FROM order_items oi JOIN services s ON s.id=oi.service_id JOIN orders o ON o.id=oi.order_id WHERE DATE(o.created_at) BETWEEN ? AND ? GROUP BY s.id ORDER BY revenue DESC");
    $stmt->bind_param('ss', $date_from, $date_to);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $title = "Laporan Per Layanan {$date_from} - {$date_to}";
    $headers = ['Layanan', 'Tipe', 'Order', 'Qty', 'Pendapatan'];
    $widths = [145, 75, 55, 75, 140];
    $aligns = ['left', 'left', 'center', 'right', 'right'];
    foreach ($data as $r) $rows[] = [$r['name'], type_label($r['type']), $r['order_count'], number_format((float)$r['total_qty'], 1) . ' ' . $r['unit'], $money($r['revenue'])];
    $rows[] = ['Grand Total', '', '', '', $money(array_sum(array_column($data, 'revenue')))];
} else {
    $stmt = $db->prepare("SELECT service_type, COUNT(*) AS order_count, SUM(pickup_fee) AS pickup_fees, SUM(delivery_fee) AS delivery_fees, SUM(total_amount) AS order_total FROM orders WHERE service_type<>'none' AND DATE(created_at) BETWEEN ? AND ? GROUP BY service_type ORDER BY service_type");
    $stmt->bind_param('ss', $date_from, $date_to);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $title = "Laporan Pickup / Delivery {$date_from} - {$date_to}";
    $headers = ['Tipe', 'Order', 'Biaya Pickup', 'Biaya Delivery', 'Total Order'];
    $widths = [80, 55, 115, 115, 125];
    $aligns = ['left', 'center', 'right', 'right', 'right'];
    foreach ($data as $r) $rows[] = [ucfirst($r['service_type']), $r['order_count'], $money($r['pickup_fees']), $money($r['delivery_fees']), $money($r['order_total'])];
}

$pdf->text(APP_NAME, 16);
$pdf->text($title, 12);
$pdf->text('Dibuat: ' . date('d/m/Y H:i'), 9);
$pdf->text(' ', 4);
$pdf->table($headers, $rows ?: [array_pad(['Tidak ada data'], count($headers), '')], $widths, $aligns);
$pdf->download('laporan-' . $type . '-' . date('Ymd-His') . '.pdf');
