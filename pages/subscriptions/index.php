<?php
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_login();
require_permission('subscriptions.view');

$db = db();
$subscriptions = $db->query(
    "SELECT s.*, c.name AS customer_name, c.phone,
            GROUP_CONCAT(CONCAT(sv.name, ' × ', FORMAT(si.quantity, 2)) ORDER BY si.id SEPARATOR ', ') AS package
     FROM subscriptions s
     JOIN customers c ON c.id = s.customer_id
     LEFT JOIN subscription_items si ON si.subscription_id = s.id
     LEFT JOIN services sv ON sv.id = si.service_id
     GROUP BY s.id ORDER BY s.is_active DESC, s.next_due, c.name"
)->fetch_all(MYSQLI_ASSOC);
$frequencies = ['weekly' => 'Mingguan', 'biweekly' => '2 Mingguan', 'monthly' => 'Bulanan'];
$title = 'Langganan';
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <div><h4 class="fw-bold mb-0">Langganan Laundry</h4><p class="text-muted small mb-0">Order berulang yang dibuat manual saat dibutuhkan</p></div>
  <?php if (can('subscriptions.manage')): ?><a href="<?= APP_URL ?>/pages/subscriptions/form.php" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i>Tambah Langganan</a><?php endif; ?>
</div>
<div class="card border-0 shadow-sm"><div class="card-body p-0"><div class="table-responsive">
<table class="table table-hover align-middle mb-0">
  <thead class="table-light"><tr><th>Pelanggan</th><th>Paket</th><th>Frekuensi</th><th>Jatuh Tempo Berikutnya</th><th>Antar/Jemput</th><th>Status</th><?php if (can('subscriptions.manage')): ?><th class="text-center">Aksi</th><?php endif; ?></tr></thead>
  <tbody>
  <?php if (!$subscriptions): ?><tr><td colspan="7" class="text-center text-muted py-4">Belum ada langganan</td></tr><?php endif; ?>
  <?php foreach ($subscriptions as $sub): ?>
    <tr>
      <td><div class="fw-medium"><?= h($sub['customer_name']) ?></div><div class="small text-muted"><?= h($sub['phone'] ?? '') ?></div></td>
      <td class="small"><?= h($sub['package'] ?: '-') ?></td>
      <td><?= h($frequencies[$sub['frequency']] ?? $sub['frequency']) ?></td>
      <td><span class="<?= $sub['is_active'] && $sub['next_due'] <= date('Y-m-d') ? 'text-danger fw-bold' : '' ?>"><?= date('d/m/Y', strtotime($sub['next_due'])) ?></span></td>
      <td><?= $sub['service_type'] === 'none' ? '<span class="text-muted">-</span>' : h($sub['service_type'] === 'both' ? 'Pickup + Delivery' : ucfirst($sub['service_type'])) ?></td>
      <td><?= $sub['is_active'] ? '<span class="badge bg-success-subtle text-success">Aktif</span>' : '<span class="badge bg-secondary-subtle text-secondary">Nonaktif</span>' ?></td>
      <?php if (can('subscriptions.manage')): ?><td><div class="d-flex gap-1 justify-content-center">
        <?php if ($sub['is_active']): ?><form method="post" action="<?= APP_URL ?>/pages/subscriptions/action.php" onsubmit="return confirm('Buat order dari langganan ini?')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $sub['id'] ?>"><input type="hidden" name="action" value="generate"><button class="btn btn-sm btn-success" title="Buat order"><i class="bi bi-play-fill"></i></button></form><?php endif; ?>
        <a href="<?= APP_URL ?>/pages/subscriptions/form.php?id=<?= $sub['id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit"><i class="bi bi-pencil"></i></a>
        <form method="post" action="<?= APP_URL ?>/pages/subscriptions/action.php" onsubmit="return confirm('Hapus langganan ini? Order yang sudah dibuat tetap tersimpan.')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $sub['id'] ?>"><input type="hidden" name="action" value="delete"><button class="btn btn-sm btn-outline-danger" title="Hapus"><i class="bi bi-trash"></i></button></form>
      </div></td><?php endif; ?>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div></div></div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
