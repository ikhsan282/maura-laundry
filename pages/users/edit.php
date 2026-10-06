<?php
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_login();
require_permission('users.manage');

$db     = db();
$id     = (int)($_GET['id'] ?? 0);
$errors = [];
$roles  = $db->query("SELECT * FROM roles ORDER BY id")->fetch_all(MYSQLI_ASSOC);

$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param('i', $id); $stmt->execute();
$user = $stmt->get_result()->fetch_assoc(); $stmt->close();
if (!$user) { flash('error', 'Pengguna tidak ditemukan.'); redirect(APP_URL . '/pages/users/index.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) { $errors[] = 'Token keamanan tidak valid.'; }
    $name     = trim($_POST['name'] ?? '');
    $role_id  = (int)($_POST['role_id'] ?? 0);
    $password = $_POST['password'] ?? '';

    if ($name === '') $errors[] = 'Nama wajib diisi.';
    if (!$role_id)    $errors[] = 'Pilih role.';

    if (empty($errors)) {
        if ($password !== '') {
            if (strlen($password) < 8) { $errors[] = 'Password minimal 8 karakter.'; }
            else {
                $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                $stmt = $db->prepare("UPDATE users SET name=?,role_id=?,password=? WHERE id=?");
                $stmt->bind_param('sisi', $name, $role_id, $hash, $id);
                $stmt->execute(); $stmt->close();
            }
        } else {
            $stmt = $db->prepare("UPDATE users SET name=?,role_id=? WHERE id=?");
            $stmt->bind_param('sii', $name, $role_id, $id);
            $stmt->execute(); $stmt->close();
        }
        if (empty($errors)) {
            flash('success', 'Data pengguna berhasil diperbarui.');
            redirect(APP_URL . '/pages/users/index.php');
        }
    }
    $user['name']    = $name;
    $user['role_id'] = $role_id;
}

$title = 'Edit Pengguna';
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <h4 class="fw-bold mb-0">Edit Pengguna</h4>
  <a href="<?= APP_URL ?>/pages/users/index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
</div>
<?php foreach ($errors as $e): ?><div class="alert alert-danger py-2 small"><?= h($e) ?></div><?php endforeach; ?>
<div class="card border-0 shadow-sm" style="max-width:600px">
  <div class="card-body">
    <form method="POST" novalidate>
      <?= csrf_field() ?>
      <div class="mb-3">
        <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control" value="<?= h($user['name']) ?>" required autofocus>
      </div>
      <div class="mb-3">
        <label class="form-label">Username</label>
        <input type="text" class="form-control" value="<?= h($user['username']) ?>" disabled>
      </div>
      <div class="mb-3">
        <label class="form-label">Email</label>
        <input type="text" class="form-control" value="<?= h($user['email']) ?>" disabled>
      </div>
      <div class="mb-3">
        <label class="form-label">Role <span class="text-danger">*</span></label>
        <select name="role_id" class="form-select" required>
          <?php foreach ($roles as $r): ?>
            <option value="<?= $r['id'] ?>" <?= $user['role_id'] == $r['id'] ? 'selected' : '' ?>><?= h($r['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="mb-4">
        <label class="form-label">Password Baru <span class="text-muted small">(kosongkan jika tidak diubah)</span></label>
        <input type="password" name="password" class="form-control" placeholder="Min. 8 karakter">
      </div>
      <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-1"></i>Simpan Perubahan</button>
        <a href="<?= APP_URL ?>/pages/users/index.php" class="btn btn-outline-secondary">Batal</a>
      </div>
    </form>
  </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
