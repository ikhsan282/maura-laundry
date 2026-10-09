<?php
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_login();
require_permission('reports.view');

$db   = db();
$type = $_GET['type'] ?? 'daily'; // daily | monthly | service | transport
if (!in_array($type, ['daily', 'monthly', 'service', 'transport'], true)) $type = 'daily';
$month = $_GET['month'] ?? date('Y-m');
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to   = $_GET['date_to']   ?? date('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['format'] ?? '') === 'pdf') {
    require_once __DIR__ . '/../../../includes/pdf.php';

    $money = static fn($n): string => 'Rp ' . number_format((float)$n, 0, ',', '.');
    $pdf = new PDFGenerator();
    $title = '';
    $headers = $rows = $widths = $aligns = [];

    if ($type === 'daily') {
        $stmt = $db->prepare("SELECT DATE(p.paid_at) AS tgl, COUNT(DISTINCT p.order_id) AS order_count, SUM(CASE WHEN p.method='tunai' THEN p.amount ELSE 0 END) AS tunai, SUM(CASE WHEN p.method='transfer' THEN p.amount ELSE 0 END) AS transfer, SUM(p.amount) AS total FROM payments p WHERE DATE(p.paid_at) BETWEEN ? AND ? GROUP BY DATE(p.paid_at) ORDER BY tgl DESC");
        $stmt->bind_param('ss', $date_from, $date_to); $stmt->execute();
        $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
        $title = "Laporan Harian {$date_from} - {$date_to}";
        $headers = ['Tanggal', 'Order', 'Tunai', 'Transfer', 'Total'];
        $widths = [90, 55, 115, 115, 115]; $aligns = ['left', 'center', 'right', 'right', 'right'];
        foreach ($data as $r) $rows[] = [$r['tgl'], $r['order_count'], $money($r['tunai']), $money($r['transfer']), $money($r['total'])];
        $rows[] = ['Grand Total', '', '', '', $money(array_sum(array_column($data, 'total')))];
    } elseif ($type === 'monthly') {
        [$yr, $mo] = array_map('intval', explode('-', $month));
        $stmt = $db->prepare("SELECT DATE(p.paid_at) AS tgl, COUNT(DISTINCT p.order_id) AS order_count, SUM(p.amount) AS total FROM payments p WHERE YEAR(p.paid_at)=? AND MONTH(p.paid_at)=? GROUP BY DATE(p.paid_at) ORDER BY tgl ASC");
        $stmt->bind_param('ii', $yr, $mo); $stmt->execute();
        $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
        $title = "Laporan Bulanan {$month}";
        $headers = ['Tanggal', 'Order', 'Pendapatan'];
        $widths = [180, 100, 210]; $aligns = ['left', 'center', 'right'];
        foreach ($data as $r) $rows[] = [$r['tgl'], $r['order_count'], $money($r['total'])];
        $rows[] = ['Grand Total', array_sum(array_column($data, 'order_count')), $money(array_sum(array_column($data, 'total')))];
    } elseif ($type === 'service') {
        $stmt = $db->prepare("SELECT s.name, s.type, s.unit, COUNT(DISTINCT o.id) AS order_count, SUM(oi.quantity) AS total_qty, SUM(oi.subtotal) AS revenue FROM order_items oi JOIN services s ON s.id=oi.service_id JOIN orders o ON o.id=oi.order_id WHERE DATE(o.created_at) BETWEEN ? AND ? GROUP BY s.id ORDER BY revenue DESC");
        $stmt->bind_param('ss', $date_from, $date_to); $stmt->execute();
        $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
        $title = "Laporan Per Layanan {$date_from} - {$date_to}";
        $headers = ['Layanan', 'Tipe', 'Order', 'Qty', 'Pendapatan'];
        $widths = [145, 75, 55, 75, 140]; $aligns = ['left', 'left', 'center', 'right', 'right'];
        foreach ($data as $r) $rows[] = [$r['name'], type_label($r['type']), $r['order_count'], number_format((float)$r['total_qty'], 1) . ' ' . $r['unit'], $money($r['revenue'])];
        $rows[] = ['Grand Total', '', '', '', $money(array_sum(array_column($data, 'revenue')))];
    } else {
        $stmt = $db->prepare("SELECT service_type, COUNT(*) AS order_count, SUM(pickup_fee) AS pickup_fees, SUM(delivery_fee) AS delivery_fees, SUM(total_amount) AS order_total FROM orders WHERE service_type<>'none' AND DATE(created_at) BETWEEN ? AND ? GROUP BY service_type ORDER BY service_type");
        $stmt->bind_param('ss', $date_from, $date_to); $stmt->execute();
        $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
        $title = "Laporan Pickup / Delivery {$date_from} - {$date_to}";
        $headers = ['Tipe', 'Order', 'Biaya Pickup', 'Biaya Delivery', 'Total Order'];
        $widths = [80, 55, 115, 115, 125]; $aligns = ['left', 'center', 'right', 'right', 'right'];
        foreach ($data as $r) $rows[] = [ucfirst($r['service_type']), $r['order_count'], $money($r['pickup_fees']), $money($r['delivery_fees']), $money($r['order_total'])];
    }

    $pdf->text(APP_NAME, 16);
    $pdf->text($title, 12);
    $pdf->text('Dibuat: ' . date('d/m/Y H:i'), 9);
    $pdf->text(' ', 4);
    $pdf->table($headers, $rows ?: [array_pad(['Tidak ada data'], count($headers), '')], $widths, $aligns);
    $pdf->download('laporan-' . $type . '-' . date('Ymd-His') . '.pdf');
    exit;
}

$title = 'Laporan';
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <h4 class="fw-bold mb-0">Laporan</h4>
</div>

<!-- Tab nav -->
<ul class="nav nav-tabs mb-4">
  <li class="nav-item"><a class="nav-link <?= $type==='daily'?'active':'' ?>" href="?type=daily">Harian</a></li>
  <li class="nav-item"><a class="nav-link <?= $type==='monthly'?'active':'' ?>" href="?type=monthly">Bulanan</a></li>
  <li class="nav-item"><a class="nav-link <?= $type==='service'?'active':'' ?>" href="?type=service">Per Layanan</a></li>
  <li class="nav-item"><a class="nav-link <?= $type==='transport'?'active':'' ?>" href="?type=transport">Pickup / Delivery</a></li>
</ul>

<?php $pdf_qs = http_build_query(['type' => $type, 'month' => $month, 'date_from' => $date_from, 'date_to' => $date_to, 'format' => 'pdf']); ?>
<div class="d-flex justify-content-end mb-3">
  <a class="btn btn-sm btn-outline-danger" href="?<?= h($pdf_qs) ?>"><i class="bi bi-file-earmark-pdf me-1"></i>Export PDF</a>
</div>

<?php if ($type === 'daily'): ?>
<!-- Daily Report -->
<div class="card border-0 shadow-sm mb-3">
  <div class="card-body py-2">
    <form method="GET" class="row g-2 align-items-center">
      <input type="hidden" name="type" value="daily">
      <div class="col-auto"><label class="col-form-label col-form-label-sm">Dari</label></div>
      <div class="col-auto"><input type="date" name="date_from" class="form-control form-control-sm" value="<?= h($date_from) ?>"></div>
      <div class="col-auto"><label class="col-form-label col-form-label-sm">Sampai</label></div>
      <div class="col-auto"><input type="date" name="date_to" class="form-control form-control-sm" value="<?= h($date_to) ?>"></div>
      <div class="col-auto"><button class="btn btn-sm btn-primary" type="submit"><i class="bi bi-funnel me-1"></i>Tampilkan</button></div>
    </form>
  </div>
</div>
<?php
$stmt = $db->prepare(
    "SELECT
        DATE(p.paid_at) AS tgl,
        COUNT(DISTINCT p.order_id) AS order_count,
        SUM(CASE WHEN p.method='tunai' THEN p.amount ELSE 0 END) AS tunai,
        SUM(CASE WHEN p.method='transfer' THEN p.amount ELSE 0 END) AS transfer,
        SUM(p.amount) AS total
     FROM payments p
     WHERE DATE(p.paid_at) BETWEEN ? AND ?
     GROUP BY DATE(p.paid_at)
     ORDER BY tgl DESC"
);
$stmt->bind_param('ss', $date_from, $date_to); $stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
$grand = array_sum(array_column($rows, 'total'));
?>
<div class="card border-0 shadow-sm">
  <div class="card-header bg-white d-flex justify-content-between align-items-center">
    <span class="fw-semibold">Laporan Harian: <?= date('d/m/Y', strtotime($date_from)) ?> — <?= date('d/m/Y', strtotime($date_to)) ?></span>
    <span class="badge bg-success-subtle text-success fw-semibold">Total: <?= idr($grand) ?></span>
  </div>
  <div class="card-body p-0">
    <table class="table align-middle mb-0">
      <thead class="table-light">
        <tr><th>Tanggal</th><th class="text-center">Order</th><th class="text-end">Tunai</th><th class="text-end">Transfer</th><th class="text-end">Total</th></tr>
      </thead>
      <tbody>
      <?php if (empty($rows)): ?>
        <tr><td colspan="5" class="text-center text-muted py-4">Tidak ada data pada periode ini</td></tr>
      <?php else: foreach ($rows as $r): ?>
        <tr>
          <td><?= date('d F Y', strtotime($r['tgl'])) ?></td>
          <td class="text-center"><?= $r['order_count'] ?></td>
          <td class="text-end"><?= idr($r['tunai']) ?></td>
          <td class="text-end"><?= idr($r['transfer']) ?></td>
          <td class="text-end fw-bold text-success"><?= idr($r['total']) ?></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
      <?php if (!empty($rows)): ?>
      <tfoot class="table-light">
        <tr>
          <td colspan="4" class="text-end fw-bold">Grand Total</td>
          <td class="text-end fw-bold text-success"><?= idr($grand) ?></td>
        </tr>
      </tfoot>
      <?php endif; ?>
    </table>
  </div>
</div>

<?php elseif ($type === 'monthly'): ?>
<!-- Monthly Report -->
<div class="card border-0 shadow-sm mb-3">
  <div class="card-body py-2">
    <form method="GET" class="row g-2 align-items-center">
      <input type="hidden" name="type" value="monthly">
      <div class="col-auto"><label class="col-form-label col-form-label-sm">Bulan</label></div>
      <div class="col-auto"><input type="month" name="month" class="form-control form-control-sm" value="<?= h($month) ?>"></div>
      <div class="col-auto"><button class="btn btn-sm btn-primary" type="submit"><i class="bi bi-funnel me-1"></i>Tampilkan</button></div>
    </form>
  </div>
</div>
<?php
[$yr, $mo] = explode('-', $month);
$stmt = $db->prepare(
    "SELECT
        DATE(p.paid_at) AS tgl,
        COUNT(DISTINCT p.order_id) AS order_count,
        SUM(p.amount) AS total
     FROM payments p
     WHERE YEAR(p.paid_at) = ? AND MONTH(p.paid_at) = ?
     GROUP BY DATE(p.paid_at)
     ORDER BY tgl ASC"
);
$stmt->bind_param('ii', $yr, $mo); $stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
$grand = array_sum(array_column($rows, 'total'));
$grand_orders = array_sum(array_column($rows, 'order_count'));

// Summary by service for the month
$stmt2 = $db->prepare(
    "SELECT s.name, s.type, SUM(oi.quantity) AS qty, SUM(oi.subtotal) AS revenue
     FROM order_items oi
     JOIN services s ON s.id = oi.service_id
     JOIN orders o   ON o.id = oi.order_id
     JOIN payments p ON p.order_id = o.id
     WHERE YEAR(p.paid_at) = ? AND MONTH(p.paid_at) = ?
     GROUP BY s.id ORDER BY revenue DESC"
);
$stmt2->bind_param('ii', $yr, $mo); $stmt2->execute();
$svc_rows = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC); $stmt2->close();
?>
<div class="row g-3 mb-3">
  <div class="col-md-4"><div class="card border-0 shadow-sm text-center p-3">
    <div class="fs-4 fw-bold text-primary"><?= idr($grand) ?></div>
    <div class="small text-muted">Total Pendapatan</div>
  </div></div>
  <div class="col-md-4"><div class="card border-0 shadow-sm text-center p-3">
    <div class="fs-4 fw-bold"><?= $grand_orders ?></div>
    <div class="small text-muted">Total Order</div>
  </div></div>
  <div class="col-md-4"><div class="card border-0 shadow-sm text-center p-3">
    <div class="fs-4 fw-bold"><?= count($rows) ?></div>
    <div class="small text-muted">Hari Aktif</div>
  </div></div>
</div>
<div class="card border-0 shadow-sm">
  <div class="card-header bg-white fw-semibold">Transaksi Harian — <?= date('F Y', mktime(0,0,0,$mo,1,$yr)) ?></div>
  <div class="card-body p-0">
    <table class="table align-middle mb-0">
      <thead class="table-light"><tr><th>Tanggal</th><th class="text-center">Order</th><th class="text-end">Pendapatan</th></tr></thead>
      <tbody>
      <?php if (empty($rows)): ?>
        <tr><td colspan="3" class="text-center text-muted py-4">Tidak ada transaksi bulan ini</td></tr>
      <?php else: foreach ($rows as $r): ?>
        <tr>
          <td><?= date('d F Y', strtotime($r['tgl'])) ?></td>
          <td class="text-center"><?= $r['order_count'] ?></td>
          <td class="text-end fw-medium text-success"><?= idr($r['total']) ?></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php elseif ($type === 'service'): ?>
<!-- Per-service Report -->
<div class="card border-0 shadow-sm mb-3">
  <div class="card-body py-2">
    <form method="GET" class="row g-2 align-items-center">
      <input type="hidden" name="type" value="service">
      <div class="col-auto"><label class="col-form-label col-form-label-sm">Dari</label></div>
      <div class="col-auto"><input type="date" name="date_from" class="form-control form-control-sm" value="<?= h($date_from) ?>"></div>
      <div class="col-auto"><label class="col-form-label col-form-label-sm">Sampai</label></div>
      <div class="col-auto"><input type="date" name="date_to" class="form-control form-control-sm" value="<?= h($date_to) ?>"></div>
      <div class="col-auto"><button class="btn btn-sm btn-primary" type="submit"><i class="bi bi-funnel me-1"></i>Tampilkan</button></div>
    </form>
  </div>
</div>
<?php
$stmt = $db->prepare(
    "SELECT s.name, s.type, s.unit,
            COUNT(DISTINCT o.id) AS order_count,
            SUM(oi.quantity) AS total_qty,
            SUM(oi.subtotal) AS revenue
     FROM order_items oi
     JOIN services s ON s.id = oi.service_id
     JOIN orders o   ON o.id = oi.order_id
     WHERE DATE(o.created_at) BETWEEN ? AND ?
     GROUP BY s.id ORDER BY revenue DESC"
);
$stmt->bind_param('ss', $date_from, $date_to); $stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
$grand = array_sum(array_column($rows, 'revenue'));
?>
<div class="card border-0 shadow-sm">
  <div class="card-header bg-white d-flex justify-content-between align-items-center">
    <span class="fw-semibold">Laporan Per Layanan: <?= date('d/m/Y', strtotime($date_from)) ?> — <?= date('d/m/Y', strtotime($date_to)) ?></span>
    <span class="badge bg-success-subtle text-success fw-semibold">Total: <?= idr($grand) ?></span>
  </div>
  <div class="card-body p-0">
    <table class="table align-middle mb-0">
      <thead class="table-light">
        <tr><th>Nama Layanan</th><th>Tipe</th><th class="text-center">Jml Order</th><th class="text-center">Total Qty</th><th class="text-end">Pendapatan</th><th class="text-end">%</th></tr>
      </thead>
      <tbody>
      <?php if (empty($rows)): ?>
        <tr><td colspan="6" class="text-center text-muted py-4">Tidak ada data</td></tr>
      <?php else: foreach ($rows as $r): ?>
        <tr>
          <td class="fw-medium"><?= h($r['name']) ?></td>
          <td><span class="badge bg-secondary-subtle text-secondary"><?= type_label($r['type']) ?></span></td>
          <td class="text-center"><?= $r['order_count'] ?></td>
          <td class="text-center"><?= number_format($r['total_qty'],1) ?> <?= h($r['unit']) ?></td>
          <td class="text-end text-success fw-medium"><?= idr($r['revenue']) ?></td>
          <td class="text-end text-muted small"><?= $grand > 0 ? number_format($r['revenue']/$grand*100,1).'%' : '-' ?></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
      <?php if (!empty($rows)): ?>
      <tfoot class="table-light">
        <tr><td colspan="4" class="text-end fw-bold">Grand Total</td><td class="text-end fw-bold text-success"><?= idr($grand) ?></td><td></td></tr>
      </tfoot>
      <?php endif; ?>
    </table>
  </div>
</div>
<?php elseif ($type === 'transport'): ?>
<?php
$stmt = $db->prepare("SELECT service_type,COUNT(*) AS order_count,SUM(pickup_fee) AS pickup_fees,SUM(delivery_fee) AS delivery_fees,SUM(total_amount) AS order_total FROM orders WHERE service_type<>'none' AND DATE(created_at) BETWEEN ? AND ? GROUP BY service_type ORDER BY service_type");
$stmt->bind_param('ss', $date_from, $date_to); $stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
?>
<div class="card border-0 shadow-sm mb-3"><div class="card-body py-2"><form method="get" class="row g-2 align-items-center"><input type="hidden" name="type" value="transport"><div class="col-auto"><input type="date" name="date_from" class="form-control form-control-sm" value="<?= h($date_from) ?>"></div><div class="col-auto"><input type="date" name="date_to" class="form-control form-control-sm" value="<?= h($date_to) ?>"></div><div class="col-auto"><button class="btn btn-sm btn-primary">Tampilkan</button></div></form></div></div>
<div class="card border-0 shadow-sm"><div class="card-header bg-white fw-semibold">Pickup / Delivery</div><div class="card-body p-0"><table class="table align-middle mb-0"><thead class="table-light"><tr><th>Tipe</th><th class="text-center">Order</th><th class="text-end">Biaya Pickup</th><th class="text-end">Biaya Delivery</th><th class="text-end">Total Order</th></tr></thead><tbody>
<?php if (!$rows): ?><tr><td colspan="5" class="text-center text-muted py-4">Tidak ada data</td></tr><?php else: foreach ($rows as $r): ?><tr><td><?= h(ucfirst($r['service_type'])) ?></td><td class="text-center"><?= $r['order_count'] ?></td><td class="text-end"><?= idr($r['pickup_fees']) ?></td><td class="text-end"><?= idr($r['delivery_fees']) ?></td><td class="text-end fw-bold"><?= idr($r['order_total']) ?></td></tr><?php endforeach; endif; ?>
</tbody></table></div></div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
