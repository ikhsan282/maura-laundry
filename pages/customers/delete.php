<?php
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_login();
require_permission('customers.delete');

$db = db();
$id = (int)($_GET['id'] ?? 0);

if (!hash_equals($_SESSION['csrf_token'] ?? '', $_GET['csrf'] ?? '')) {
    flash('error', 'Token keamanan tidak valid.'); redirect(APP_URL . '/pages/customers/index.php');
}

// Check no orders
$stmt = $db->prepare("SELECT COUNT(*) FROM orders WHERE customer_id = ?");
$stmt->bind_param('i', $id); $stmt->execute(); $stmt->bind_result($cnt); $stmt->fetch(); $stmt->close();
if ($cnt > 0) { flash('error', 'Pelanggan tidak dapat dihapus karena memiliki riwayat order.'); redirect(APP_URL . '/pages/customers/index.php'); }

$stmt = $db->prepare("DELETE FROM customers WHERE id = ?");
$stmt->bind_param('i', $id); $stmt->execute(); $stmt->close();
flash('success', 'Pelanggan berhasil dihapus.');
redirect(APP_URL . '/pages/customers/index.php');
