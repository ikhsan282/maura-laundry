<?php
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_login();
require_permission('services.edit');

$db = db(); $id = (int)($_GET['id'] ?? 0); $errors = [];

$stmt = $db->prepare("SELECT * FROM services WHERE id = ?");
$stmt->bind_param('i', $id); $stmt->execute();
$svc = $stmt->get_result()->fetch_assoc(); $stmt->close();
if (!$svc) { flash('error', 'Layanan tidak ditemukan.'); redirect(APP_URL . '/pages/services/index.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) { $errors[] = 'Token keamanan tidak valid.'; }
    $name   = trim($_POST['name'] ?? '');
    $type   = $_POST['type'] ?? '';
    $price  = (float)($_POST['price'] ?? 0);
    $unit   = trim($_POST['unit'] ?? 'kg');
    $days   = (int)($_POST['duration_days'] ?? 3);
    $desc   = trim($_POST['description'] ?? '');
    $active = isset($_POST['is_active']) ? 1 : 0;

    if ($name === '') $errors[] = 'Nama layanan wajib diisi.';
    if (!in_array($type, ['kiloan','satuan','express'])) $errors[] = 'Tipe tidak valid.';
    if ($price <= 0)  $errors[] = 'Harga harus lebih dari 0.';

    if (empty($errors)) {
        $stmt = $db->prepare("UPDATE services SET name=?,type=?,price=?,unit=?,duration_days=?,description=?,is_active=? WHERE id=?");
        $stmt->bind_param('ssdssisi', $name, $type, $price, $unit, $days, $desc ?: null, $active, $id);
        $stmt->execute(); $stmt->close();
        flash('success', 'Layanan berhasil diperbarui.');
        redirect(APP_URL . '/pages/services/index.php');
    }
    $svc = array_merge($svc, compact('name','type','price','unit','days','desc','active'));
}

$title = 'Edit Layanan';
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <h4 class="fw-bold mb-0">Edit Layanan</h4>
  <a href="<?= APP_URL ?>/pages/services/index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
</div>
<?php foreach ($errors as $e): ?><div class="alert alert-danger py-2 small"><?= h($e) ?></div><?php endforeach; ?>
<div class="card border-0 shadow-sm" style="max-width:600px">
  <div class="card-body">
    <form method="POST" novalidate>
      <?= csrf_field() ?>
      <div class="mb-3">
        <label class="form-label">Nama Layanan <span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control" value="<?= h($svc['name']) ?>" required autofocus>
      </div>
      <div class="row g-3 mb-3">
        <div class="col-md-6">
          <label class="form-label">Tipe <span class="text-danger">*</span></label>
          <select name="type" class="form-select" required>
            <?php foreach (['kiloan','satuan','express'] as $t): ?>
              <option value="<?= $t ?>" <?= $svc['type']===$t?'selected':'' ?>><?= ucfirst($t) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label">Satuan</label>
          <input type="text" name="unit" class="form-control" value="<?= h($svc['unit']) ?>">
        </div>
      </div>
      <div class="row g-3 mb-3">
        <div class="col-md-6">
          <label class="form-label">Harga (Rp)</label>
          <input type="number" name="price" class="form-control" min="0" step="500" value="<?= h($svc['price']) ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label">Estimasi Selesai (Hari)</label>
          <input type="number" name="duration_days" class="form-control" min="1" value="<?= h($svc['duration_days']) ?>">
        </div>
      </div>
      <div class="mb-3">
        <label class="form-label">Deskripsi</label>
        <textarea name="description" class="form-control" rows="2"><?= h($svc['description'] ?? '') ?></textarea>
      </div>
      <div class="mb-4">
        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" name="is_active" id="isActive" <?= $svc['is_active'] ? 'checked' : '' ?>>
          <label class="form-check-label" for="isActive">Layanan Aktif</label>
        </div>
      </div>
      <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-1"></i>Simpan Perubahan</button>
        <a href="<?= APP_URL ?>/pages/services/index.php" class="btn btn-outline-secondary">Batal</a>
      </div>
    </form>
  </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
