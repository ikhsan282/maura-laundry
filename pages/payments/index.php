<?php
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_login();
require_permission('payments.view');

$db       = db();
$page     = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;
$offset   = ($page - 1) * $per_page;
$method   = $_GET['method'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to   = $_GET['date_to'] ?? '';

$where = ['1=1']; $params = []; $types = '';
if ($method) { $where[] = 'p.method = ?'; $params[] = $method; $types .= 's'; }
if ($date_from) { $where[] = 'DATE(p.paid_at) >= ?'; $params[] = $date_from; $types .= 's'; }
if ($date_to)   { $where[] = 'DATE(p.paid_at) <= ?'; $params[] = $date_to;   $types .= 's'; }
$w = implode(' AND ', $where);

$cnt_stmt = $db->prepare("SELECT COUNT(*), COALESCE(SUM(p.amount),0) FROM payments p WHERE $w");
if ($params) $cnt_stmt->bind_param($types, ...$params);
$cnt_stmt->execute(); $cnt_stmt->bind_result($total, $total_amount); $cnt_stmt->fetch(); $cnt_stmt->close();

$p2 = $params; $t2 = $types; $p2[] = $per_page; $p2[] = $offset; $t2 .= 'ii';
$stmt = $db->prepare(
    "SELECT p.*, o.order_number, c.name AS customer_name, u.name AS cashier
     FROM payments p
     JOIN orders o    ON o.id = p.order_id
     JOIN customers c ON c.id = o.customer_id
     JOIN users u     ON u.id = p.user_id
     WHERE $w ORDER BY p.paid_at DESC LIMIT ? OFFSET ?"
);
$stmt->bind_param($t2, ...$p2); $stmt->execute();
$payments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();

$title = 'Riwayat Pembayaran';
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h4 class="fw-bold mb-0">Riwayat Pembayaran</h4>
    <p class="text-muted small mb-0"><?= $total ?> transaksi · Total <?= idr($total_amount) ?></p>
  </div>
</div>

<div class="card border-0 shadow-sm mb-3">
  <div class="card-body py-2">
    <form method="GET" class="row g-2 align-items-center">
      <div class="col-auto">
        <select name="method" class="form-select form-select-sm">
          <option value="">Semua Metode</option>
          <option value="tunai"    <?= $method==='tunai'?'selected':'' ?>>Tunai</option>
          <option value="transfer" <?= $method==='transfer'?'selected':'' ?>>Transfer</option>
        </select>
      </div>
      <div class="col-auto">
        <input type="date" name="date_from" class="form-control form-control-sm" value="<?= h($date_from) ?>" placeholder="Dari tanggal">
      </div>
      <div class="col-auto">
        <input type="date" name="date_to" class="form-control form-control-sm" value="<?= h($date_to) ?>" placeholder="Sampai tanggal">
      </div>
      <div class="col-auto">
        <button class="btn btn-sm btn-primary" type="submit"><i class="bi bi-funnel me-1"></i>Filter</button>
        <a href="?" class="btn btn-sm btn-outline-secondary ms-1">Reset</a>
      </div>
    </form>
  </div>
</div>

<div class="card border-0 shadow-sm">
  <div class="card-body p-0">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light">
        <tr><th>Tanggal</th><th>No. Order</th><th>Pelanggan</th><th>Kasir</th><th>Metode</th><th>Referensi</th><th class="text-end">Jumlah</th></tr>
      </thead>
      <tbody>
      <?php if (empty($payments)): ?>
        <tr><td colspan="7" class="text-center text-muted py-4">Tidak ada data pembayaran</td></tr>
      <?php else: foreach ($payments as $p): ?>
        <tr>
          <td class="small"><?= date('d/m/Y H:i', strtotime($p['paid_at'])) ?></td>
          <td><a href="<?= APP_URL ?>/pages/orders/view.php?order_number=<?= h($p['order_number']) ?>" class="font-monospace small text-decoration-none"><?= h($p['order_number']) ?></a></td>
          <td><?= h($p['customer_name']) ?></td>
          <td class="small text-muted"><?= h($p['cashier']) ?></td>
          <td>
            <?php if ($p['method'] === 'tunai'): ?>
              <span class="badge bg-success-subtle text-success"><i class="bi bi-cash me-1"></i>Tunai</span>
            <?php else: ?>
              <span class="badge bg-info-subtle text-info"><i class="bi bi-phone me-1"></i>Transfer</span>
            <?php endif; ?>
          </td>
          <td class="small text-muted"><?= h($p['reference'] ?? '-') ?></td>
          <td class="text-end fw-bold text-success"><?= idr($p['amount']) ?></td>
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
