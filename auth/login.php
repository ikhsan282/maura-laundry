<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

// Redirect if already logged in
if (is_logged_in()) {
    redirect(APP_URL . '/pages/dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $error = 'Token keamanan tidak valid. Silakan coba lagi.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($username === '' || $password === '') {
            $error = 'Username dan password wajib diisi.';
        } else {
            $db   = db();
            $stmt = $db->prepare(
                "SELECT u.*, r.name AS role_name FROM users u
                 JOIN roles r ON r.id = u.role_id
                 WHERE (u.username = ? OR u.email = ?) AND u.is_active = 1
                 LIMIT 1"
            );
            $stmt->bind_param('ss', $username, $username);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($user && password_verify($password, $user['password'])) {
                if (!$user['email_verified']) {
                    $error = 'Email belum diverifikasi. Cek inbox email Anda.';
                } else {
                    session_regenerate_id(true);
                    $_SESSION['user_id']   = $user['id'];
                    $_SESSION['user_name'] = $user['name'];
                    $_SESSION['role_id']   = $user['role_id'];
                    flash('success', 'Selamat datang, ' . $user['name'] . '!');
                    redirect(APP_URL . '/pages/dashboard.php');
                }
            } else {
                $error = 'Username atau password salah.';
            }
        }
    }
}
$title = 'Login';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login — <?= APP_NAME ?></title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <style>
    body { background: linear-gradient(135deg,#0d6efd11 0%,#6610f211 100%); min-height:100vh; }
    .login-card { max-width:420px; border:none; border-radius:1rem; box-shadow:0 8px 32px rgba(0,0,0,.1); }
    .brand-icon { width:56px;height:56px;border-radius:1rem;background:linear-gradient(135deg,#0d6efd,#6610f2); }
  </style>
</head>
<body class="d-flex align-items-center justify-content-center p-3">
  <div class="w-100">
    <div class="card login-card mx-auto">
      <div class="card-body p-4 p-md-5">
        <div class="text-center mb-4">
          <div class="brand-icon d-inline-flex align-items-center justify-content-center mb-3">
            <i class="bi bi-water text-white fs-2"></i>
          </div>
          <h4 class="fw-bold mb-0"><?= APP_NAME ?></h4>
          <p class="text-muted small">Sistem Manajemen Laundry</p>
        </div>
        <?php if ($error): ?>
          <div class="alert alert-danger py-2 small"><i class="bi bi-exclamation-circle me-1"></i><?= h($error) ?></div>
        <?php endif; ?>
        <form method="POST" novalidate>
          <?= csrf_field() ?>
          <div class="mb-3">
            <label class="form-label fw-medium">Username / Email</label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-person"></i></span>
              <input type="text" name="username" class="form-control"
                     value="<?= h($_POST['username'] ?? '') ?>"
                     placeholder="Masukkan username" required autofocus>
            </div>
          </div>
          <div class="mb-4">
            <label class="form-label fw-medium">Password</label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-lock"></i></span>
              <input type="password" name="password" class="form-control" placeholder="Masukkan password" required>
              <button type="button" class="btn btn-outline-secondary" onclick="togglePwd(this)">
                <i class="bi bi-eye"></i>
              </button>
            </div>
          </div>
          <button type="submit" class="btn btn-primary w-100 fw-semibold">
            <i class="bi bi-box-arrow-in-right me-1"></i>Masuk
          </button>
        </form>
        <div class="text-center mt-3">
          <a href="<?= APP_URL ?>/auth/forgot-password.php" class="small text-muted">Lupa password?</a>
        </div>
      </div>
    </div>
    <p class="text-center text-muted small mt-3">&copy; <?= date('Y') ?> <?= APP_NAME ?>. All rights reserved.</p>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    function togglePwd(btn) {
      const inp = btn.previousElementSibling;
      const show = inp.type === 'password';
      inp.type = show ? 'text' : 'password';
      btn.querySelector('i').className = 'bi bi-eye' + (show ? '-slash' : '');
    }
  </script>
</body>
</html>
