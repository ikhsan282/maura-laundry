<?php
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_login();
require_permission('services.create');

$db = db(); $errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) { $errors[] = 'Token keamanan tidak valid.'; }
    $name     = trim($_POST['name'] ?? '');
    $type     = $_POST['type'] ?? '';
    $price    = (float)($_POST['price'] ?? 0);
    $unit     = trim($_POST['unit'] ?? 'kg');
    $days     = (int)($_POST['duration_days'] ?? 3);
    $desc     = trim($_POST['description'] ?? '');
    $active   = isset($_POST['is_active']) ? 1 : 0;

    if ($name === '')                              $errors[] = 'Nama layanan wajib diisi.';
    if (!in_array($type, ['kiloan','satuan','express'])) $errors[] = 'Tipe layanan tidak valid.';
    if ($price <= 0)                               $errors[] = 'Harga harus lebih dari 0.';
    if ($unit === '')                              $errors[] = 'Satuan wajib diisi.';

    if (empty($errors)) {
        $stmt = $db->prepare("INSERT INTO services (name,type,price,unit,duration_days,description,is_active) VALUES (?,?,?,?,?,?,?)");
        $stmt->bind_param('ssdsssi', $name, $type, $price, $unit, $days, $desc ?: null, $active);
        $stmt->execute(); $stmt->close();
        flash('success', 'Layanan berhasil ditambahkan.');
        redirect(APP_URL . '/pages/services/index.php');
    }
}

$title = 'Tambah Layanan';
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <h4 class="fw-bold mb-0">Tambah Layanan</h4>
  <a href="<?= APP_URL ?>/pages/services/index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
</div>
<?php foreach ($errors as $e): ?><div class="alert alert-danger py-2 small"><?= h($e) ?></div><?php endforeach; ?>
<div class="card border-0 shadow-sm" style="max-width:600px">
  <div class="card-body">
    <form method="POST" novalidate>
      <?= csrf_field() ?>
      <div class="mb-3">
        <label class="form-label">Nama Layanan <span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control" value="<?= h($_POST['name'] ?? '') ?>" required autofocus>
      </div>
      <div class="row g-3 mb-3">
        <div class="col-md-6">
          <label class="form-label">Tipe <span class="text-danger">*</span></label>
          <select name="type" class="form-select" required onchange="setUnit(this.value)">
            <option value="">-- Pilih Tipe --</option>
            <?php foreach (['kiloan','satuan','express'] as $t): ?>
              <option value="<?= $t ?>" <?= ($_POST['type'] ?? '') === $t ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label">Satuan <span class="text-danger">*</span></label>
          <input type="text" name="unit" id="unitInput" class="form-control" value="<?= h($_POST['unit'] ?? 'kg') ?>" required>
        </div>
      </div>
      <div class="row g-3 mb-3">
        <div class="col-md-6">
          <label class="form-label">Harga (Rp) <span class="text-danger">*</span></label>
          <input type="number" name="price" class="form-control" min="0" step="500" value="<?= h($_POST['price'] ?? '') ?>" required>
        </div>
        <div class="col-md-6">
          <label class="form-label">Estimasi Selesai (Hari)</label>
          <input type="number" name="duration_days" class="form-control" min="1" value="<?= h($_POST['duration_days'] ?? 3) ?>">
        </div>
      </div>
      <div class="mb-3">
        <label class="form-label">Deskripsi</label>
        <textarea name="description" class="form-control" rows="2"><?= h($_POST['description'] ?? '') ?></textarea>
      </div>
      <div class="mb-4">
        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" name="is_active" id="isActive" <?= !isset($_POST['name']) || isset($_POST['is_active']) ? 'checked' : '' ?>>
          <label class="form-check-label" for="isActive">Layanan Aktif</label>
        </div>
      </div>
      <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-circle me-1"></i>Simpan</button>
        <a href="<?= APP_URL ?>/pages/services/index.php" class="btn btn-outline-secondary">Batal</a>
      </div>
    </form>
  </div>
</div>
<script>
function setUnit(type) {
  const u = document.getElementById('unitInput');
  if (type === 'kiloan' || type === 'express') u.value = 'kg';
  else if (type === 'satuan') u.value = 'pcs';
}
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
