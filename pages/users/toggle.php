<?php
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_login();
require_permission('users.manage');

$db = db();
$id = (int)($_GET['id'] ?? 0);

if (!hash_equals($_SESSION['csrf_token'] ?? '', $_GET['csrf'] ?? '')) {
    flash('error', 'Token keamanan tidak valid.'); redirect(APP_URL . '/pages/users/index.php');
}
if ($id === $_SESSION['user_id']) {
    flash('error', 'Tidak dapat menonaktifkan akun sendiri.'); redirect(APP_URL . '/pages/users/index.php');
}

$stmt = $db->prepare("UPDATE users SET is_active = 1 - is_active WHERE id = ?");
$stmt->bind_param('i', $id); $stmt->execute(); $stmt->close();
flash('success', 'Status pengguna berhasil diperbarui.');
redirect(APP_URL . '/pages/users/index.php');
