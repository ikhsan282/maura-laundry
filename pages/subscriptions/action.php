<?php
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/subscriptions.php';
require_login();
require_permission('subscriptions.manage');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify()) {
    flash('error', 'Permintaan tidak valid.');
    redirect(APP_URL . '/pages/subscriptions/index.php');
}

$db = db();
$id = (int)($_POST['id'] ?? 0);
$action = $_POST['action'] ?? '';

if ($action === 'generate') {
    try {
        $order_number = generate_subscription_order($db, $id, $_SESSION['user_id']);
        flash('success', "Order {$order_number} berhasil dibuat dari langganan.");
        redirect(APP_URL . '/pages/orders/view.php?order_number=' . urlencode($order_number));
    } catch (Throwable $e) {
        flash('error', 'Gagal membuat order: ' . $e->getMessage());
        redirect(APP_URL . '/pages/subscriptions/index.php');
    }
} elseif ($action === 'delete') {
    $stmt = $db->prepare("SELECT COUNT(*) FROM orders WHERE subscription_id = ?");
    $stmt->bind_param('i', $id); $stmt->execute(); $stmt->bind_result($count); $stmt->fetch(); $stmt->close();
    if ($count > 0) {
        flash('error', "Langganan tidak dapat dihapus karena sudah memiliki {$count} order.");
    } else {
        $stmt = $db->prepare("DELETE FROM subscriptions WHERE id = ?");
        $stmt->bind_param('i', $id); $stmt->execute(); $stmt->close();
        flash('success', 'Langganan berhasil dihapus.');
    }
    redirect(APP_URL . '/pages/subscriptions/index.php');
} else {
    flash('error', 'Aksi tidak valid.');
    redirect(APP_URL . '/pages/subscriptions/index.php');
}
