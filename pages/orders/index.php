<?php
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_login();
require_permission('orders.view');

$db = db();

// Filters
$status_filter = $_GET['status'] ?? '';
$search        = trim($_GET['q'] ?? '');
$page          = max(1, (int)($_GET['page'] ?? 1));
$per_page      = 15;
$offset        = ($page - 1) * $per_page;

$where  = ['1=1'];
$params = [];
$types  = '';

if ($status_filter !== '') {
    $where[]  = 'o.status = ?';
    $params[] = $status_filter;
    $types   .= 's';
}
if ($search !== '') {
    $like     = "%$search%";
    $where[]  = '(o.order_number LIKE ? OR c.name LIKE ? OR c.phone LIKE ?)';
    $params[] = $like; $params[] = $like; $params[] = $like;
    $types   .= 'sss';
}

$where_sql = implode(' AND ', $where);

// Count
$count_sql  = "SELECT COUNT(*) FROM orders o JOIN customers c ON c.id=o.customer_id WHERE $where_sql";
$stmt = $db->prepare($count_sql);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute(); $stmt->bind_result($total); $stmt->fetch(); $stmt->close();

// Fetch
$sql  = "SELECT o.id, o.order_number, o.status, o.total_amount, o.estimated_done, o.created_at,
                c.name AS customer_name, c.phone,
                u.name AS staff_name
         FROM orders o
         JOIN customers c ON c.id = o.customer_id
         JOIN users u     ON u.id = o.user_id
         WHERE $where_sql
         ORDER BY o.created_at DESC
         LIMIT ? OFFSET ?";
$params[] = $per_page; $params[] = $offset;
$types   .= 'ii';
$stmt = $db->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$statuses = ['diterima','dicuci','disetrika','selesai','diambil'];
$title    = 'Daftar Order';
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h4 class="fw-bold mb-0">Daftar Order</h4>
    <p class="text-muted small mb-0">Total <?= $total ?> order</p>
  </div>
  <?php if (can('orders.create')): ?>
  <a href="<?= APP_URL ?>/pages/orders/create.php" class="btn btn-primary">
    <i class="bi bi-plus-circle me-1"></i>Buat Order
  </a>
  <?php endif; ?>
</div>

<!-- Filters -->
<div class="card border-0 shadow-sm mb-3">
  <div class="card-body py-2">
    <form method="GET" class="row g-2 align-items-center">
      <div class="col-md-5">
        <div class="input-group input-group-sm">
          <span class="input-group-text"><i class="bi bi-search"></i></span>
          <input type="text" name="q" class="form-control" placeholder="Cari no. order, nama, telp..." value="<?= h($search) ?>">
        </div>
      </div>
      <div class="col-md-4">
        <select name="status" class="form-select form-select-sm">
          <option value="">Semua Status</option>
          <?php foreach ($statuses as $s): ?>
            <option value="<?= $s ?>" <?= $status_filter===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-auto">
        <button class="btn btn-sm btn-primary" type="submit"><i class="bi bi-funnel me-1"></i>Filter</button>
        <a href="<?= APP_URL ?>/pages/orders/index.php" class="btn btn-sm btn-outline-secondary ms-1">Reset</a>
      </div>
    </form>
  </div>
</div>

<!-- Status tabs -->
<div class="mb-3">
  <?php
  $counts = [];
  $res = $db->query("SELECT status, COUNT(*) cnt FROM orders GROUP BY status");
  while ($row = $res->fetch_assoc()) $counts[$row['status']] = $row['cnt'];
  ?>
  <div class="d-flex flex-wrap gap-1">
    <a href="?" class="badge text-decoration-none <?= $status_filter===''?'bg-dark':'bg-secondary bg-opacity-25 text-dark' ?>">Semua (<?= array_sum($counts) ?>)</a>
    <?php foreach ($statuses as $s): ?>
      <a href="?status=<?= $s ?>" class="badge text-decoration-none <?= $status_filter===$s?'bg-primary':'bg-secondary bg-opacity-25 text-dark' ?>">
        <?= ucfirst($s) ?> (<?= $counts[$s] ?? 0 ?>)
      </a>
    <?php endforeach; ?>
  </div>
</div>

<div class="card border-0 shadow-sm">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>No. Order</th><th>Pelanggan</th><th>Status</th>
            <th>Est. Selesai</th><th class="text-end">Total</th><th>Kasir</th><th>Tgl</th><th class="text-center">Aksi</th>
          </tr>
        </thead>
        <tbody>
        <?php if (empty($orders)): ?>
          <tr><td colspan="8" class="text-center text-muted py-4">Tidak ada data order</td></tr>
        <?php else: foreach ($orders as $o): ?>
          <tr>
            <td><span class="fw-medium font-monospace small"><?= h($o['order_number']) ?></span></td>
            <td>
              <div class="fw-medium"><?= h($o['customer_name']) ?></div>
              <div class="small text-muted"><?= h($o['phone']) ?></div>
            </td>
            <td><?= status_badge($o['status']) ?></td>
            <td class="small"><?= $o['estimated_done'] ? date('d/m/Y', strtotime($o['estimated_done'])) : '-' ?></td>
            <td class="text-end fw-medium"><?= idr($o['total_amount']) ?></td>
            <td class="small text-muted"><?= h($o['staff_name']) ?></td>
            <td class="small text-muted"><?= date('d/m/Y', strtotime($o['created_at'])) ?></td>
            <td class="text-center">
              <div class="d-flex gap-1 justify-content-center">
                <a href="<?= APP_URL ?>/pages/orders/view.php?order_number=<?= h($o['order_number']) ?>" class="btn btn-sm btn-outline-primary" title="Detail"><i class="bi bi-eye"></i></a>
                <?php if (can('orders.status') && next_status($o['status'])): ?>
                <a href="<?= APP_URL ?>/pages/orders/update-status.php?id=<?= $o['id'] ?>&csrf=<?= csrf_token() ?>"
                   class="btn btn-sm btn-outline-success" title="Update Status" data-confirm="Update status order ini?"
                ><i class="bi bi-arrow-right-circle"></i></a>
                <?php endif; ?>
                <?php if (can('payments.create') && !in_array($o['status'],['diambil'])): ?>
                <a href="<?= APP_URL ?>/pages/payments/create.php?order_id=<?= $o['id'] ?>" class="btn btn-sm btn-outline-warning" title="Bayar"><i class="bi bi-cash"></i></a>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php if ($total > $per_page): ?>
  <div class="card-footer bg-white d-flex justify-content-end">
    <?= paginate($total, $per_page, $page, '?'. http_build_query(array_merge($_GET,['page'=>'%d']))) ?>
  </div>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
