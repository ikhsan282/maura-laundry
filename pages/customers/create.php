<?php
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_login();
require_permission('customers.create');

$db     = db();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) { $errors[] = 'Token keamanan tidak valid.'; }
    $name    = trim($_POST['name'] ?? '');
    $phone   = trim($_POST['phone'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if ($name === '') $errors[] = 'Nama pelanggan wajib diisi.';
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Format email tidak valid.';

    if (empty($errors)) {
        $stmt = $db->prepare("INSERT INTO customers (name,phone,email,address) VALUES (?,?,?,?)");
        $stmt->bind_param('ssss', $name, $phone, $email ?: null, $address ?: null);
        $stmt->execute(); $new_id = $db->insert_id; $stmt->close();
        flash('success', 'Pelanggan berhasil ditambahkan.');
        $redirect = $_GET['redirect'] ?? '';
        redirect($redirect === 'order' ? APP_URL . '/pages/orders/create.php' : APP_URL . '/pages/customers/index.php');
    }
}

$title = 'Tambah Pelanggan';
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <h4 class="fw-bold mb-0">Tambah Pelanggan</h4>
  <a href="<?= APP_URL ?>/pages/customers/index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
</div>

<?php foreach ($errors as $e): ?>
  <div class="alert alert-danger py-2 small"><?= h($e) ?></div>
<?php endforeach; ?>

<div class="card border-0 shadow-sm" style="max-width:600px">
  <div class="card-body">
    <form method="POST" novalidate>
      <?= csrf_field() ?>
      <div class="mb-3">
        <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control" value="<?= h($_POST['name'] ?? '') ?>" required autofocus>
      </div>
      <div class="mb-3">
        <label class="form-label">Nomor Telepon</label>
        <input type="text" name="phone" class="form-control" value="<?= h($_POST['phone'] ?? '') ?>" placeholder="08xx-xxxx-xxxx">
      </div>
      <div class="mb-3">
        <label class="form-label">Email</label>
        <input type="email" name="email" class="form-control" value="<?= h($_POST['email'] ?? '') ?>">
      </div>
      <div class="mb-4">
        <label class="form-label">Alamat</label>
        <textarea name="address" class="form-control" rows="2"><?= h($_POST['address'] ?? '') ?></textarea>
      </div>
      <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-1"></i>Simpan</button>
        <a href="<?= APP_URL ?>/pages/customers/index.php" class="btn btn-outline-secondary">Batal</a>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
