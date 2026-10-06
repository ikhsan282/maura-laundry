<?php
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_login();
require_permission('customers.view');

$db       = db();
$search   = trim($_GET['q'] ?? '');
$page     = max(1, (int)($_GET['page'] ?? 1));
$per_page = 15;
$offset   = ($page - 1) * $per_page;

if ($search !== '') {
    $like  = "%$search%";
    $total_stmt = $db->prepare("SELECT COUNT(*) FROM customers WHERE name LIKE ? OR phone LIKE ? OR email LIKE ?");
    $total_stmt->bind_param('sss', $like, $like, $like); $total_stmt->execute(); $total_stmt->bind_result($total); $total_stmt->fetch(); $total_stmt->close();
    $stmt = $db->prepare("SELECT c.*, (SELECT COUNT(*) FROM orders o WHERE o.customer_id=c.id) AS order_count FROM customers c WHERE c.name LIKE ? OR c.phone LIKE ? OR c.email LIKE ? ORDER BY c.name LIMIT ? OFFSET ?");
    $stmt->bind_param('sssii', $like, $like, $like, $per_page, $offset);
} else {
    $total_stmt = $db->prepare("SELECT COUNT(*) FROM customers"); $total_stmt->execute(); $total_stmt->bind_result($total); $total_stmt->fetch(); $total_stmt->close();
    $stmt = $db->prepare("SELECT c.*, (SELECT COUNT(*) FROM orders o WHERE o.customer_id=c.id) AS order_count FROM customers c ORDER BY c.name LIMIT ? OFFSET ?");
    $stmt->bind_param('ii', $per_page, $offset);
}
$stmt->execute();
$customers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$title = 'Data Pelanggan';
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <div><h4 class="fw-bold mb-0">Data Pelanggan</h4><p class="text-muted small mb-0">Total <?= $total ?> pelanggan</p></div>
  <?php if (can('customers.create')): ?>
  <a href="<?= APP_URL ?>/pages/customers/create.php" class="btn btn-primary"><i class="bi bi-person-plus me-1"></i>Tambah Pelanggan</a>
  <?php endif; ?>
</div>

<div class="card border-0 shadow-sm mb-3">
  <div class="card-body py-2">
    <form method="GET" class="d-flex gap-2">
      <div class="input-group input-group-sm">
        <span class="input-group-text"><i class="bi bi-search"></i></span>
        <input type="text" name="q" class="form-control" placeholder="Cari nama, telepon, email..." value="<?= h($search) ?>">
      </div>
      <button class="btn btn-sm btn-primary" type="submit">Cari</button>
      <?php if ($search): ?><a href="?" class="btn btn-sm btn-outline-secondary">Reset</a><?php endif; ?>
    </form>
  </div>
</div>

<div class="card border-0 shadow-sm">
  <div class="card-body p-0">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light">
        <tr><th>Nama</th><th>Telepon</th><th>Email</th><th>Alamat</th><th class="text-center">Order</th><th class="text-center">Aksi</th></tr>
      </thead>
      <tbody>
      <?php if (empty($customers)): ?>
        <tr><td colspan="6" class="text-center text-muted py-4">Tidak ada data pelanggan</td></tr>
      <?php else: foreach ($customers as $c): ?>
        <tr>
          <td class="fw-medium"><?= h($c['name']) ?></td>
          <td><?= h($c['phone'] ?? '-') ?></td>
          <td class="text-muted small"><?= h($c['email'] ?? '-') ?></td>
          <td class="text-muted small" style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= h($c['address'] ?? '-') ?></td>
          <td class="text-center"><span class="badge bg-primary-subtle text-primary"><?= $c['order_count'] ?></span></td>
          <td class="text-center">
            <div class="d-flex gap-1 justify-content-center">
              <?php if (can('customers.edit')): ?>
              <a href="<?= APP_URL ?>/pages/customers/edit.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit"><i class="bi bi-pencil"></i></a>
              <?php endif; ?>
              <?php if (can('customers.delete') && $c['order_count'] == 0): ?>
              <a href="<?= APP_URL ?>/pages/customers/delete.php?id=<?= $c['id'] ?>&csrf=<?= csrf_token() ?>"
                 class="btn btn-sm btn-outline-danger" data-confirm="Hapus pelanggan <?= h($c['name']) ?>?"><i class="bi bi-trash"></i></a>
              <?php endif; ?>
            </div>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ($total > $per_page): ?>
  <div class="card-footer bg-white d-flex justify-content-end">
    <?= paginate($total, $per_page, $page, '?'. http_build_query(array_merge($_GET,['page'=>'%d']))) ?>
  </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
