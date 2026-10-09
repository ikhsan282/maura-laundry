<?php
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_login();
require_permission('orders.status');
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify()) { flash('error', 'Permintaan tidak valid.'); redirect(APP_URL . '/pages/orders/index.php'); }

$id = (int)($_POST['id'] ?? 0);
$kind = $_POST['kind'] ?? '';
$status = $_POST['status'] ?? '';
$allowed = [
    'pickup' => ['scheduled','on_the_way','picked_up','cancelled'],
    'delivery' => ['scheduled','on_the_way','delivered','cancelled'],
];
if (!isset($allowed[$kind]) || !in_array($status, $allowed[$kind], true)) { flash('error', 'Status antar-jemput tidak valid.'); redirect(APP_URL . '/pages/orders/index.php'); }
$db = db();
$stmt = $db->prepare("SELECT order_number,service_type FROM orders WHERE id=?");
$stmt->bind_param('i', $id); $stmt->execute(); $order = $stmt->get_result()->fetch_assoc(); $stmt->close();
if (!$order || !in_array($order['service_type'], [$kind,'both'], true)) { flash('error', 'Layanan antar-jemput tidak ditemukan.'); redirect(APP_URL . '/pages/orders/index.php'); }
$sql = $kind === 'pickup' ? "UPDATE orders SET pickup_status=? WHERE id=?" : "UPDATE orders SET delivery_status=? WHERE id=?";
$stmt = $db->prepare($sql); $stmt->bind_param('si', $status, $id); $stmt->execute(); $stmt->close();
flash('success', 'Status ' . $kind . ' berhasil diperbarui.');
redirect(APP_URL . '/pages/orders/view.php?order_number=' . urlencode($order['order_number']));
