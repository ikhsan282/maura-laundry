<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_permission('customers.delete');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify()) {
    flash('error', 'Permintaan tidak valid.');
    redirect(APP_URL . '/pages/customers/index.php');
}

$db = db();
$id = (int)($_POST['id'] ?? 0);

// Check no orders
$stmt = $db->prepare("SELECT COUNT(*) FROM orders WHERE customer_id = ?");
$stmt->bind_param('i', $id); $stmt->execute(); $stmt->bind_result($cnt); $stmt->fetch(); $stmt->close();
if ($cnt > 0) { flash('error', 'Pelanggan tidak dapat dihapus karena memiliki riwayat order.'); redirect(APP_URL . '/pages/customers/index.php'); }

$stmt = $db->prepare("DELETE FROM customers WHERE id = ?");
$stmt->bind_param('i', $id); $stmt->execute(); $stmt->close();
flash('success', 'Pelanggan berhasil dihapus.');
redirect(APP_URL . '/pages/customers/index.php');
