<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();
require_permission('dashboard.view');

$db = db();

// Today's stats
$today = date('Y-m-d');

$stmt = $db->prepare("SELECT COUNT(*) FROM orders WHERE DATE(created_at) = ?");
$stmt->bind_param('s', $today); $stmt->execute(); $stmt->bind_result($orders_today); $stmt->fetch(); $stmt->close();

$stmt = $db->prepare("SELECT COUNT(*) FROM orders WHERE status NOT IN ('selesai','diambil')");
$stmt->execute(); $stmt->bind_result($orders_pending); $stmt->fetch(); $stmt->close();

$stmt = $db->prepare("SELECT COUNT(*) FROM orders WHERE status = 'selesai'");
$stmt->execute(); $stmt->bind_result($orders_done); $stmt->fetch(); $stmt->close();

$stmt = $db->prepare("SELECT COALESCE(SUM(amount),0) FROM payments WHERE DATE(paid_at) = ?");
$stmt->bind_param('s', $today); $stmt->execute(); $stmt->bind_result($revenue_today); $stmt->fetch(); $stmt->close();

$stmt = $db->prepare("SELECT COALESCE(SUM(amount),0) FROM payments WHERE MONTH(paid_at) = MONTH(NOW()) AND YEAR(paid_at) = YEAR(NOW())");
$stmt->execute(); $stmt->bind_result($revenue_month); $stmt->fetch(); $stmt->close();

$stmt = $db->prepare("SELECT COUNT(*) FROM customers");
$stmt->execute(); $stmt->bind_result($total_customers); $stmt->fetch(); $stmt->close();

// Recent orders
$recent = $db->query(
    "SELECT o.order_number, o.status, o.total_amount, o.created_at,
            c.name AS customer_name
     FROM orders o JOIN customers c ON c.id = o.customer_id
     ORDER BY o.created_at DESC LIMIT 8"
)->fetch_all(MYSQLI_ASSOC);

// Orders by status chart data
$status_data = [];
$res = $db->query("SELECT status, COUNT(*) as cnt FROM orders GROUP BY status");
while ($row = $res->fetch_assoc()) $status_data[$row['status']] = $row['cnt'];

// Revenue last 7 days chart data
$rev_labels = [];
$rev_values = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $rev_labels[] = date('d M', strtotime($d));
    $stmt = $db->prepare("SELECT COALESCE(SUM(amount),0) FROM payments WHERE DATE(paid_at) = ?");
    $stmt->bind_param('s', $d); $stmt->execute(); $stmt->bind_result($v); $stmt->fetch(); $stmt->close();
    $rev_values[] = (float) $v;
}

$title = 'Dashboard';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h4 class="fw-bold mb-0">Dashboard</h4>
    <p class="text-muted small mb-0">Selamat datang, <?= h(current_user()['name']) ?>! Hari ini <?= date('d F Y') ?></p>
  </div>
</div>

<!-- Stat Cards -->
<div class="row g-3 mb-4">
  <div class="col-6 col-xl-3">
    <div class="card stat-card h-100">
      <div class="card-body d-flex align-items-center gap-3">
        <div class="stat-icon bg-primary-subtle text-primary"><i class="bi bi-clipboard-plus"></i></div>
        <div>
          <div class="fs-4 fw-bold"><?= $orders_today ?></div>
          <div class="small text-muted">Order Hari Ini</div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="card stat-card h-100">
      <div class="card-body d-flex align-items-center gap-3">
        <div class="stat-icon bg-warning-subtle text-warning"><i class="bi bi-hourglass-split"></i></div>
        <div>
          <div class="fs-4 fw-bold"><?= $orders_pending ?></div>
          <div class="small text-muted">Order Pending</div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="card stat-card h-100">
      <div class="card-body d-flex align-items-center gap-3">
        <div class="stat-icon bg-success-subtle text-success"><i class="bi bi-check-circle"></i></div>
        <div>
          <div class="fs-4 fw-bold"><?= $orders_done ?></div>
          <div class="small text-muted">Selesai Belum Diambil</div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="card stat-card h-100">
      <div class="card-body d-flex align-items-center gap-3">
        <div class="stat-icon bg-info-subtle text-info"><i class="bi bi-cash-coin"></i></div>
        <div>
          <div class="fs-4 fw-bold" style="font-size:1rem!important"><?= idr($revenue_today) ?></div>
          <div class="small text-muted">Pendapatan Hari Ini</div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-md-6 col-xl-3">
    <div class="card stat-card">
      <div class="card-body d-flex align-items-center gap-3">
        <div class="stat-icon bg-purple-subtle" style="background:#f3e8ff;color:#6f42c1"><i class="bi bi-graph-up-arrow"></i></div>
        <div>
          <div class="fw-bold"><?= idr($revenue_month) ?></div>
          <div class="small text-muted">Pendapatan Bulan Ini</div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-md-6 col-xl-3">
    <div class="card stat-card">
      <div class="card-body d-flex align-items-center gap-3">
        <div class="stat-icon bg-secondary-subtle text-secondary"><i class="bi bi-people"></i></div>
        <div>
          <div class="fw-bold"><?= $total_customers ?></div>
          <div class="small text-muted">Total Pelanggan</div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-lg-8">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-header bg-white py-3"><h6 class="mb-0 fw-semibold"><i class="bi bi-graph-up me-2 text-primary"></i>Pendapatan 7 Hari Terakhir</h6></div>
      <div class="card-body"><canvas id="revenueChart" height="110"></canvas></div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card border-0 shadow-sm h-100">
      <div class="card-header bg-white py-3"><h6 class="mb-0 fw-semibold"><i class="bi bi-pie-chart me-2 text-primary"></i>Status Order</h6></div>
      <div class="card-body"><canvas id="statusChart" height="220"></canvas></div>
    </div>
  </div>
</div>

<!-- Recent Orders -->
<div class="card border-0 shadow-sm">
  <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
    <h6 class="mb-0 fw-semibold"><i class="bi bi-clock-history me-2 text-primary"></i>Order Terbaru</h6>
    <a href="<?= APP_URL ?>/pages/orders/index.php" class="btn btn-sm btn-outline-primary">Lihat Semua</a>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th>No. Order</th><th>Pelanggan</th><th>Status</th>
            <th class="text-end">Total</th><th>Tgl Order</th><th></th>
          </tr>
        </thead>
        <tbody>
        <?php if (empty($recent)): ?>
          <tr><td colspan="6" class="text-center text-muted py-4">Belum ada order</td></tr>
        <?php else: foreach ($recent as $r): ?>
          <tr>
            <td><a href="<?= APP_URL ?>/pages/orders/view.php?id=<?= $r['order_number'] ?>" class="fw-medium text-decoration-none"><?= h($r['order_number']) ?></a></td>
            <td><?= h($r['customer_name']) ?></td>
            <td><?= status_badge($r['status']) ?></td>
            <td class="text-end fw-medium"><?= idr($r['total_amount']) ?></td>
            <td class="text-muted small"><?= date('d/m/Y H:i', strtotime($r['created_at'])) ?></td>
            <td>
              <a href="<?= APP_URL ?>/pages/orders/view.php?order_number=<?= h($r['order_number']) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a>
            </td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.5.1/dist/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById('revenueChart'), {
  type: 'line',
  data: {
    labels: <?= json_encode($rev_labels) ?>,
    datasets: [{ label: 'Pendapatan', data: <?= json_encode($rev_values) ?>, borderColor: '#0d6efd', backgroundColor: 'rgba(13,110,253,.12)', fill: true, tension: .3 }]
  },
  options: {
    interaction: { mode: 'index', intersect: false },
    scales: { y: { beginAtZero: true, ticks: { callback: v => 'Rp ' + Number(v).toLocaleString('id-ID') } } },
    plugins: { tooltip: { callbacks: { label: ctx => 'Rp ' + Number(ctx.parsed.y).toLocaleString('id-ID') } } }
  }
});
new Chart(document.getElementById('statusChart'), {
  type: 'doughnut',
  data: {
    labels: <?= json_encode(array_map('ucfirst', array_keys($status_data))) ?>,
    datasets: [{ data: <?= json_encode(array_values($status_data)) ?>, backgroundColor: ['#0d6efd','#ffc107','#6f42c1','#198754','#6c757d'], borderWidth: 0 }]
  },
  options: { plugins: { legend: { position: 'bottom' } } }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
