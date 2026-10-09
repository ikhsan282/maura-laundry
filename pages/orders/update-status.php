<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_permission('orders.status');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify()) {
    flash('error', 'Permintaan tidak valid.');
    redirect(APP_URL . '/pages/orders/index.php');
}

$db = db();
$id = (int)($_POST['id'] ?? 0);

$stmt = $db->prepare("SELECT id, order_number, status FROM orders WHERE id = ?");
$stmt->bind_param('i', $id); $stmt->execute();
$order = $stmt->get_result()->fetch_assoc(); $stmt->close();

if (!$order) { flash('error', 'Order tidak ditemukan.'); redirect(APP_URL . '/pages/orders/index.php'); }

$next = next_status($order['status']);
if (!$next) { flash('warning', 'Order sudah pada status akhir.'); redirect(APP_URL . '/pages/orders/view.php?order_number=' . urlencode($order['order_number'])); }

$upd = $db->prepare("UPDATE orders SET status = ? WHERE id = ?");
$upd->bind_param('si', $next, $order['id']); $upd->execute(); $upd->close();

flash('success', "Status order {$order['order_number']} diperbarui ke: " . ucfirst($next));
redirect(APP_URL . '/pages/orders/view.php?order_number=' . urlencode($order['order_number']));
