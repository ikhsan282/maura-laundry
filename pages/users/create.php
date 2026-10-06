<?php
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_login();
require_permission('users.manage');

$db       = db();
$errors   = [];
$roles    = $db->query("SELECT * FROM roles ORDER BY id")->fetch_all(MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) { $errors[] = 'Token keamanan tidak valid.'; }
    $name     = trim($_POST['name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role_id  = (int)($_POST['role_id'] ?? 0);

    if ($name === '')                                    $errors[] = 'Nama wajib diisi.';
    if ($username === '')                                $errors[] = 'Username wajib diisi.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))      $errors[] = 'Format email tidak valid.';
    if (strlen($password) < 8)                           $errors[] = 'Password minimal 8 karakter.';
    if (!$role_id)                                       $errors[] = 'Pilih role.';

    // Check unique
    if (empty($errors)) {
        $chk = $db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $chk->bind_param('ss', $username, $email); $chk->execute();
        if ($chk->get_result()->num_rows > 0) $errors[] = 'Username atau email sudah digunakan.';
        $chk->close();
    }

    if (empty($errors)) {
        $hash  = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $token = bin2hex(random_bytes(32));
        $stmt  = $db->prepare("INSERT INTO users (role_id,name,username,email,password,email_token) VALUES (?,?,?,?,?,?)");
        $stmt->bind_param('isssss', $role_id, $name, $username, $email, $hash, $token);
        $stmt->execute(); $stmt->close();

        // Send verification email
        $link = APP_URL . '/auth/verify-email.php?token=' . $token;
        $body = "<p>Halo {$name},</p><p>Akun Maura Laundry Anda telah dibuat. Klik link berikut untuk verifikasi email:</p><p><a href='{$link}'>{$link}</a></p>";
        send_email($email, 'Verifikasi Email — ' . APP_NAME, $body);

        flash('success', "Pengguna {$username} berhasil ditambahkan. Email verifikasi dikirim.");
        redirect(APP_URL . '/pages/users/index.php');
    }
}

$title = 'Tambah Pengguna';
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <h4 class="fw-bold mb-0">Tambah Pengguna</h4>
  <a href="<?= APP_URL ?>/pages/users/index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
</div>
<?php foreach ($errors as $e): ?><div class="alert alert-danger py-2 small"><?= h($e) ?></div><?php endforeach; ?>
<div class="card border-0 shadow-sm" style="max-width:600px">
  <div class="card-body">
    <form method="POST" novalidate>
      <?= csrf_field() ?>
      <div class="mb-3">
        <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control" value="<?= h($_POST['name'] ?? '') ?>" required autofocus>
      </div>
      <div class="row g-3 mb-3">
        <div class="col-md-6">
          <label class="form-label">Username <span class="text-danger">*</span></label>
          <input type="text" name="username" class="form-control" value="<?= h($_POST['username'] ?? '') ?>" required>
        </div>
        <div class="col-md-6">
          <label class="form-label">Role <span class="text-danger">*</span></label>
          <select name="role_id" class="form-select" required>
            <option value="">-- Pilih Role --</option>
            <?php foreach ($roles as $r): ?>
              <option value="<?= $r['id'] ?>" <?= ($_POST['role_id'] ?? '') == $r['id'] ? 'selected' : '' ?>><?= h($r['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="mb-3">
        <label class="form-label">Email <span class="text-danger">*</span></label>
        <input type="email" name="email" class="form-control" value="<?= h($_POST['email'] ?? '') ?>" required>
      </div>
      <div class="mb-4">
        <label class="form-label">Password <span class="text-danger">*</span></label>
        <input type="password" name="password" class="form-control" placeholder="Min. 8 karakter" required>
      </div>
      <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-1"></i>Simpan</button>
        <a href="<?= APP_URL ?>/pages/users/index.php" class="btn btn-outline-secondary">Batal</a>
      </div>
    </form>
  </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
