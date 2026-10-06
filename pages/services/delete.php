<?php
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_login();
require_permission('services.delete');

$db = db();
$id = (int)($_GET['id'] ?? 0);

if (!hash_equals($_SESSION['csrf_token'] ?? '', $_GET['csrf'] ?? '')) {
    flash('error', 'Token keamanan tidak valid.'); redirect(APP_URL . '/pages/services/index.php');
}

$stmt = $db->prepare("SELECT COUNT(*) FROM order_items WHERE service_id = ?");
$stmt->bind_param('i', $id); $stmt->execute(); $stmt->bind_result($cnt); $stmt->fetch(); $stmt->close();
if ($cnt > 0) { flash('error', 'Layanan tidak dapat dihapus karena sudah digunakan di order.'); redirect(APP_URL . '/pages/services/index.php'); }

$stmt = $db->prepare("DELETE FROM services WHERE id = ?");
$stmt->bind_param('i', $id); $stmt->execute(); $stmt->close();
flash('success', 'Layanan berhasil dihapus.');
redirect(APP_URL . '/pages/services/index.php');
