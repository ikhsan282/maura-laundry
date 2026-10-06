<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

if (is_logged_in()) redirect(APP_URL . '/pages/dashboard.php');

$step    = 'form'; // form | sent | reset
$error   = '';
$success = '';

// Step 1: request reset link
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {
    if (!csrf_verify()) { $error = 'Token keamanan tidak valid.'; }
    else {
        $email = trim($_POST['email'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Format email tidak valid.';
        } else {
            $db   = db();
            $stmt = $db->prepare("SELECT id, name FROM users WHERE email = ? AND is_active = 1 LIMIT 1");
            $stmt->bind_param('s', $email);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($user) {
                $token   = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
                $upd     = $db->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?");
                $upd->bind_param('ssi', $token, $expires, $user['id']);
                $upd->execute();
                $upd->close();

                $link    = APP_URL . '/auth/forgot-password.php?token=' . $token;
                $body    = "<p>Halo {$user['name']},</p>
                            <p>Klik link berikut untuk reset password (berlaku 1 jam):</p>
                            <p><a href='{$link}'>{$link}</a></p>
                            <p>Jika Anda tidak meminta reset password, abaikan email ini.</p>";
                send_email($email, 'Reset Password — ' . APP_NAME, $body);
            }
            // Always show same message to prevent email enumeration
            $step = 'sent';
        }
    }
}

// Step 2: show reset form
$token_param = trim($_GET['token'] ?? '');
if ($token_param !== '' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $db   = db();
    $stmt = $db->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_expires > NOW() LIMIT 1");
    $stmt->bind_param('s', $token_param);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) { $error = 'Link reset tidak valid atau sudah kedaluwarsa.'; }
    else        { $step  = 'reset'; }
}

// Step 3: do reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_password'])) {
    if (!csrf_verify()) { $error = 'Token keamanan tidak valid.'; }
    else {
        $token_post = trim($_POST['reset_token'] ?? '');
        $new_pass   = $_POST['new_password'] ?? '';
        $confirm    = $_POST['confirm_password'] ?? '';
        if (strlen($new_pass) < 8) {
            $error = 'Password minimal 8 karakter.';
            $step  = 'reset';
            $token_param = $token_post;
        } elseif ($new_pass !== $confirm) {
            $error = 'Konfirmasi password tidak cocok.';
            $step  = 'reset';
            $token_param = $token_post;
        } else {
            $db   = db();
            $stmt = $db->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_expires > NOW() LIMIT 1");
            $stmt->bind_param('s', $token_post);
            $stmt->execute();
            $row  = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$row) {
                $error = 'Link reset tidak valid atau sudah kedaluwarsa.';
            } else {
                $hash = password_hash($new_pass, PASSWORD_BCRYPT, ['cost' => 12]);
                $upd  = $db->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?");
                $upd->bind_param('si', $hash, $row['id']);
                $upd->execute();
                $upd->close();
                $success = 'Password berhasil direset! Silakan login.';
                $step    = 'form';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Lupa Password — <?= APP_NAME ?></title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="d-flex align-items-center justify-content-center min-vh-100 bg-light p-3">
  <div class="card border-0 shadow-sm p-4" style="max-width:420px;width:100%">
    <h5 class="fw-bold mb-1"><i class="bi bi-key me-2 text-primary"></i>Lupa Password</h5>
    <p class="text-muted small mb-3">
      <?= $step === 'reset' ? 'Masukkan password baru Anda.' : 'Masukkan email untuk menerima link reset.' ?>
    </p>

    <?php if ($error):   ?><div class="alert alert-danger py-2 small"><?= h($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success py-2 small"><?= h($success) ?></div><?php endif; ?>

    <?php if ($step === 'sent'): ?>
      <div class="alert alert-info"><i class="bi bi-envelope me-1"></i>Link reset telah dikirim ke email Anda. Cek inbox atau folder spam.</div>

    <?php elseif ($step === 'reset'): ?>
      <form method="POST" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="reset_token" value="<?= h($token_param) ?>">
        <div class="mb-3">
          <label class="form-label">Password Baru</label>
          <input type="password" name="new_password" class="form-control" placeholder="Min. 8 karakter" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Konfirmasi Password</label>
          <input type="password" name="confirm_password" class="form-control" placeholder="Ulangi password" required>
        </div>
        <button type="submit" class="btn btn-primary w-100">Reset Password</button>
      </form>

    <?php else: ?>
      <form method="POST" novalidate>
        <?= csrf_field() ?>
        <div class="mb-3">
          <label class="form-label">Alamat Email</label>
          <input type="email" name="email" class="form-control"
                 value="<?= h($_POST['email'] ?? '') ?>" placeholder="email@contoh.com" required autofocus>
        </div>
        <button type="submit" class="btn btn-primary w-100">Kirim Link Reset</button>
      </form>
    <?php endif; ?>

    <div class="text-center mt-3">
      <a href="<?= APP_URL ?>/auth/login.php" class="small text-muted"><i class="bi bi-arrow-left me-1"></i>Kembali ke Login</a>
    </div>
  </div>
</body>
</html>
