<?php
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_login();
require_permission('customers.edit');

$db     = db();
$id     = (int)($_GET['id'] ?? 0);
$errors = [];

$stmt = $db->prepare("SELECT * FROM customers WHERE id = ?");
$stmt->bind_param('i', $id); $stmt->execute();
$customer = $stmt->get_result()->fetch_assoc(); $stmt->close();
if (!$customer) { flash('error', 'Pelanggan tidak ditemukan.'); redirect(APP_URL . '/pages/customers/index.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) { $errors[] = 'Token keamanan tidak valid.'; }
    $name    = trim($_POST['name'] ?? '');
    $phone   = trim($_POST['phone'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if ($name === '') $errors[] = 'Nama pelanggan wajib diisi.';
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Format email tidak valid.';

    if (empty($errors)) {
        $stmt = $db->prepare("UPDATE customers SET name=?,phone=?,email=?,address=? WHERE id=?");
        $stmt->bind_param('ssssi', $name, $phone ?: null, $email ?: null, $address ?: null, $id);
        $stmt->execute(); $stmt->close();
        flash('success', 'Data pelanggan berhasil diperbarui.');
        redirect(APP_URL . '/pages/customers/index.php');
    }
    // repopulate
    $customer = array_merge($customer, compact('name','phone','email','address'));
}

$title = 'Edit Pelanggan';
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <h4 class="fw-bold mb-0">Edit Pelanggan</h4>
  <a href="<?= APP_URL ?>/pages/customers/index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
</div>
<?php foreach ($errors as $e): ?><div class="alert alert-danger py-2 small"><?= h($e) ?></div><?php endforeach; ?>
<div class="card border-0 shadow-sm" style="max-width:600px">
  <div class="card-body">
    <form method="POST" novalidate>
      <?= csrf_field() ?>
      <div class="mb-3">
        <label class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control" value="<?= h($customer['name']) ?>" required autofocus>
      </div>
      <div class="mb-3">
        <label class="form-label">Nomor Telepon</label>
        <input type="text" name="phone" class="form-control" value="<?= h($customer['phone'] ?? '') ?>">
      </div>
      <div class="mb-3">
        <label class="form-label">Email</label>
        <input type="email" name="email" class="form-control" value="<?= h($customer['email'] ?? '') ?>">
      </div>
      <div class="mb-4">
        <label class="form-label">Alamat</label>
        <textarea name="address" class="form-control" rows="2"><?= h($customer['address'] ?? '') ?></textarea>
      </div>
      <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-1"></i>Simpan Perubahan</button>
        <a href="<?= APP_URL ?>/pages/customers/index.php" class="btn btn-outline-secondary">Batal</a>
      </div>
    </form>
  </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
