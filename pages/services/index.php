<?php
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_login();
require_permission('services.view');

$db = db();
$services = $db->query("SELECT * FROM services ORDER BY type, name")->fetch_all(MYSQLI_ASSOC);

$title = 'Layanan';
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <div><h4 class="fw-bold mb-0">Layanan Laundry</h4><p class="text-muted small mb-0">Daftar layanan dan harga</p></div>
  <?php if (can('services.create')): ?>
  <a href="<?= APP_URL ?>/pages/services/create.php" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i>Tambah Layanan</a>
  <?php endif; ?>
</div>

<?php
$grouped = [];
foreach ($services as $s) $grouped[$s['type']][] = $s;
$type_labels = ['kiloan' => 'Kiloan', 'satuan' => 'Satuan', 'express' => 'Express'];
$type_colors = ['kiloan' => 'primary', 'satuan' => 'success', 'express' => 'warning'];
foreach ($grouped as $type => $list):
?>
<div class="card border-0 shadow-sm mb-4">
  <div class="card-header bg-white d-flex align-items-center gap-2 py-3">
    <span class="badge bg-<?= $type_colors[$type] ?? 'secondary' ?>-subtle text-<?= $type_colors[$type] ?? 'secondary' ?> border border-<?= $type_colors[$type] ?? 'secondary' ?>-subtle px-3"><?= $type_labels[$type] ?? $type ?></span>
    <span class="fw-semibold"><?= count($list) ?> layanan</span>
  </div>
  <div class="card-body p-0">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light">
        <tr><th>Nama Layanan</th><th>Harga</th><th>Satuan</th><th>Est. Hari</th><th>Status</th><th>Deskripsi</th><?php if (can('services.edit')): ?><th class="text-center">Aksi</th><?php endif; ?></tr>
      </thead>
      <tbody>
      <?php foreach ($list as $s): ?>
        <tr>
          <td class="fw-medium"><?= h($s['name']) ?></td>
          <td class="fw-bold text-primary"><?= idr($s['price']) ?></td>
          <td><span class="badge bg-secondary-subtle text-secondary">/ <?= h($s['unit']) ?></span></td>
          <td><?= $s['duration_days'] ?> hari</td>
          <td><?= $s['is_active'] ? '<span class="badge bg-success-subtle text-success">Aktif</span>' : '<span class="badge bg-secondary-subtle text-secondary">Nonaktif</span>' ?></td>
          <td class="text-muted small"><?= h($s['description'] ?? '-') ?></td>
          <?php if (can('services.edit')): ?>
          <td class="text-center">
            <div class="d-flex gap-1 justify-content-center">
              <a href="<?= APP_URL ?>/pages/services/edit.php?id=<?= $s['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
              <?php if (can('services.delete')): ?>
              <form method="POST" action="<?= APP_URL ?>/pages/services/delete.php" style="display:inline;margin:0">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="id" value="<?= $s['id'] ?>">
                <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Hapus layanan ini?')"><i class="bi bi-trash"></i></button>
              </form>
              <?php endif; ?>
            </div>
          </td>
          <?php endif; ?>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endforeach; ?>
<?php if (empty($services)): ?>
  <div class="alert alert-info">Belum ada layanan. <a href="<?= APP_URL ?>/pages/services/create.php">Tambah layanan pertama</a>.</div>
<?php endif; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
