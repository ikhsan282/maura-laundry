<?php
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_login();
require_permission('users.manage');

$db    = db();
$users = $db->query(
    "SELECT u.*, r.name AS role_name FROM users u
     JOIN roles r ON r.id = u.role_id
     ORDER BY u.created_at DESC"
)->fetch_all(MYSQLI_ASSOC);

$title = 'Kelola Pengguna';
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <div><h4 class="fw-bold mb-0">Kelola Pengguna</h4><p class="text-muted small mb-0"><?= count($users) ?> pengguna terdaftar</p></div>
  <a href="<?= APP_URL ?>/pages/users/create.php" class="btn btn-primary"><i class="bi bi-person-plus me-1"></i>Tambah Pengguna</a>
</div>

<div class="card border-0 shadow-sm">
  <div class="card-body p-0">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light">
        <tr><th>Nama</th><th>Username</th><th>Email</th><th>Role</th><th>Status</th><th>Email Verified</th><th>Dibuat</th><th class="text-center">Aksi</th></tr>
      </thead>
      <tbody>
      <?php foreach ($users as $u): ?>
        <tr>
          <td class="fw-medium"><?= h($u['name']) ?></td>
          <td class="font-monospace small"><?= h($u['username']) ?></td>
          <td class="small text-muted"><?= h($u['email']) ?></td>
          <td><span class="badge bg-primary-subtle text-primary"><?= h($u['role_name']) ?></span></td>
          <td><?= $u['is_active'] ? '<span class="badge bg-success-subtle text-success">Aktif</span>' : '<span class="badge bg-danger-subtle text-danger">Nonaktif</span>' ?></td>
          <td><?= $u['email_verified'] ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<i class="bi bi-x-circle text-muted"></i>' ?></td>
          <td class="small text-muted"><?= date('d/m/Y', strtotime($u['created_at'])) ?></td>
          <td class="text-center">
            <div class="d-flex gap-1 justify-content-center">
              <a href="<?= APP_URL ?>/pages/users/edit.php?id=<?= $u['id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit"><i class="bi bi-pencil"></i></a>
              <?php if ($u['id'] !== $_SESSION['user_id']): ?>
              <form method="POST" action="<?= APP_URL ?>/pages/users/toggle.php" style="display:inline;margin:0">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="id" value="<?= $u['id'] ?>">
                <button type="submit" class="btn btn-sm <?= $u['is_active'] ? 'btn-outline-warning' : 'btn-outline-success' ?>"
                   onclick="return confirm('<?= $u['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?> pengguna ini?')"
                   title="<?= $u['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?>">
                  <i class="bi bi-<?= $u['is_active'] ? 'pause-circle' : 'play-circle' ?>"></i>
                </button>
              </form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
