<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

$message = '';
$type    = 'info';

$token = trim($_GET['token'] ?? '');
if ($token === '') {
    $message = 'Token verifikasi tidak ditemukan.';
    $type    = 'danger';
} else {
    $db   = db();
    $stmt = $db->prepare("SELECT id FROM users WHERE email_token = ? AND email_verified = 0 LIMIT 1");
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user) {
        $message = 'Token tidak valid atau email sudah diverifikasi.';
        $type    = 'warning';
    } else {
        $upd = $db->prepare("UPDATE users SET email_verified = 1, email_token = NULL WHERE id = ?");
        $upd->bind_param('i', $user['id']);
        $upd->execute();
        $upd->close();
        $message = 'Email berhasil diverifikasi! Silakan login.';
        $type    = 'success';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Verifikasi Email — <?= APP_NAME ?></title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css">
</head>
<body class="d-flex align-items-center justify-content-center min-vh-100 bg-light">
  <div class="card border-0 shadow-sm p-4 text-center" style="max-width:420px;width:100%">
    <i class="bi bi-envelope-check fs-1 text-<?= $type === 'success' ? 'success' : 'warning' ?> mb-3"></i>
    <h5 class="fw-bold">Verifikasi Email</h5>
    <div class="alert alert-<?= h($type) ?> mt-3"><?= h($message) ?></div>
    <a href="<?= APP_URL ?>/auth/login.php" class="btn btn-primary mt-2">Ke Halaman Login</a>
  </div>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.2/font/bootstrap-icons.min.css">
</body>
</html>
