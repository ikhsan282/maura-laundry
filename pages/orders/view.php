<?php
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_login();
require_permission('orders.view');

$db           = db();
$order_number = trim($_GET['order_number'] ?? '');

if (!$order_number) redirect(APP_URL . '/pages/orders/index.php');

$stmt = $db->prepare(
    "SELECT o.*, c.name AS customer_name, c.phone, c.address, c.email AS customer_email,
            u.name AS staff_name
     FROM orders o
     JOIN customers c ON c.id = o.customer_id
     JOIN users u     ON u.id = o.user_id
     WHERE o.order_number = ?"
);
$stmt->bind_param('s', $order_number);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) { flash('error', 'Order tidak ditemukan.'); redirect(APP_URL . '/pages/orders/index.php'); }

// Items
$items = $db->prepare(
    "SELECT oi.*, s.name AS service_name, s.type, s.unit
     FROM order_items oi JOIN services s ON s.id = oi.service_id
     WHERE oi.order_id = ?"
);
$items->bind_param('i', $order['id']); $items->execute();
$items = $items->get_result()->fetch_all(MYSQLI_ASSOC);

// Payments
$payments = $db->prepare("SELECT p.*, u.name AS cashier FROM payments p JOIN users u ON u.id=p.user_id WHERE p.order_id = ? ORDER BY p.paid_at");
$payments->bind_param('i', $order['id']); $payments->execute();
$payments = $payments->get_result()->fetch_all(MYSQLI_ASSOC);

$paid   = array_sum(array_column($payments, 'amount'));
$unpaid = max(0, $order['total_amount'] - $paid);

$title = 'Detail Order — ' . $order_number;
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4 no-print">
  <div>
    <h4 class="fw-bold mb-0">Detail Order</h4>
    <p class="text-muted small mb-0">No. <?= h($order['order_number']) ?></p>
  </div>
  <div class="d-flex gap-2">
    <button onclick="printReceipt()" class="btn btn-outline-secondary btn-sm"><i class="bi bi-printer me-1"></i>Cetak Nota</button>
    <?php if (can('orders.status') && next_status($order['status'])): ?>
    <a href="<?= APP_URL ?>/pages/orders/update-status.php?id=<?= $order['id'] ?>&csrf=<?= csrf_token() ?>"
       class="btn btn-success btn-sm" data-confirm="Update status ke '<?= ucfirst(next_status($order['status'])) ?>'?">
      <i class="bi bi-arrow-right-circle me-1"></i>Update Status
    </a>
    <?php endif; ?>
    <?php if (can('payments.create') && $unpaid > 0): ?>
    <a href="<?= APP_URL ?>/pages/payments/create.php?order_id=<?= $order['id'] ?>" class="btn btn-warning btn-sm">
      <i class="bi bi-cash me-1"></i>Bayar
    </a>
    <?php endif; ?>
    <a href="<?= APP_URL ?>/pages/orders/index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
  </div>
</div>

<!-- PRINT RECEIPT AREA -->
<div id="receiptArea" style="display:none">
  <div style="text-align:center">
    <strong style="font-size:14px">MAURA LAUNDRY</strong><br>
    <small>Sistem Manajemen Laundry</small><br>
    <hr>
  </div>
  <table style="width:100%;font-size:11px">
    <tr><td>No. Order</td><td>: <strong><?= h($order['order_number']) ?></strong></td></tr>
    <tr><td>Tanggal</td><td>: <?= date('d/m/Y H:i', strtotime($order['created_at'])) ?></td></tr>
    <tr><td>Pelanggan</td><td>: <?= h($order['customer_name']) ?></td></tr>
    <tr><td>Telepon</td><td>: <?= h($order['phone']) ?></td></tr>
    <tr><td>Est. Selesai</td><td>: <?= $order['estimated_done'] ? date('d/m/Y', strtotime($order['estimated_done'])) : '-' ?></td></tr>
    <tr><td>Kasir</td><td>: <?= h($order['staff_name']) ?></td></tr>
  </table>
  <hr>
  <table style="width:100%;font-size:11px">
    <thead><tr><th style="text-align:left">Layanan</th><th>Jml</th><th style="text-align:right">Subtotal</th></tr></thead>
    <tbody>
    <?php foreach ($items as $it): ?>
      <tr>
        <td><?= h($it['service_name']) ?></td>
        <td style="text-align:center"><?= rtrim(rtrim(number_format($it['quantity'],2),'0'),'.') ?> <?= h($it['unit']) ?></td>
        <td style="text-align:right"><?= idr($it['subtotal']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <hr>
  <table style="width:100%;font-size:12px">
    <tr><td><strong>TOTAL</strong></td><td style="text-align:right"><strong><?= idr($order['total_amount']) ?></strong></td></tr>
    <tr><td>Sudah Dibayar</td><td style="text-align:right"><?= idr($paid) ?></td></tr>
    <tr><td>Sisa</td><td style="text-align:right"><?= idr($unpaid) ?></td></tr>
  </table>
  <hr>
  <div style="text-align:center;font-size:10px">
    <?php if ($order['notes']): ?><div>Catatan: <?= h($order['notes']) ?></div><?php endif; ?>
    <div>Terima kasih telah mempercayai Maura Laundry!</div>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-8">
    <!-- Order Info -->
    <div class="card border-0 shadow-sm mb-3">
      <div class="card-header bg-white fw-semibold d-flex justify-content-between">
        <span><i class="bi bi-info-circle me-2 text-primary"></i>Informasi Order</span>
        <?= status_badge($order['status']) ?>
      </div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-sm-6">
            <div class="small text-muted">No. Order</div>
            <div class="fw-medium font-monospace"><?= h($order['order_number']) ?></div>
          </div>
          <div class="col-sm-6">
            <div class="small text-muted">Tanggal Order</div>
            <div><?= date('d F Y, H:i', strtotime($order['created_at'])) ?></div>
          </div>
          <div class="col-sm-6">
            <div class="small text-muted">Estimasi Selesai</div>
            <div><?= $order['estimated_done'] ? date('d F Y', strtotime($order['estimated_done'])) : '-' ?></div>
          </div>
          <div class="col-sm-6">
            <div class="small text-muted">Kasir / Operator</div>
            <div><?= h($order['staff_name']) ?></div>
          </div>
          <?php if ($order['notes']): ?>
          <div class="col-12">
            <div class="small text-muted">Catatan</div>
            <div><?= h($order['notes']) ?></div>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Items -->
    <div class="card border-0 shadow-sm mb-3">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-list-check me-2 text-primary"></i>Item Layanan</div>
      <div class="card-body p-0">
        <table class="table align-middle mb-0">
          <thead class="table-light">
            <tr><th>Layanan</th><th>Tipe</th><th class="text-center">Jumlah</th><th class="text-end">Harga</th><th class="text-end">Subtotal</th></tr>
          </thead>
          <tbody>
          <?php foreach ($items as $it): ?>
            <tr>
              <td><?= h($it['service_name']) ?></td>
              <td><span class="badge bg-secondary-subtle text-secondary"><?= type_label($it['type']) ?></span></td>
              <td class="text-center"><?= rtrim(rtrim(number_format($it['quantity'],2),'0'),'.') ?> <?= h($it['unit']) ?></td>
              <td class="text-end"><?= idr($it['price']) ?></td>
              <td class="text-end fw-medium"><?= idr($it['subtotal']) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
          <tfoot class="table-light">
            <tr><td colspan="4" class="text-end fw-bold">Total</td><td class="text-end fw-bold text-primary"><?= idr($order['total_amount']) ?></td></tr>
          </tfoot>
        </table>
      </div>
    </div>

    <!-- Tracking -->
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-geo-alt me-2 text-primary"></i>Tracking Status</div>
      <div class="card-body">
        <?php
        $flow = ['diterima','dicuci','disetrika','selesai','diambil'];
        $cur  = array_search($order['status'], $flow);
        ?>
        <div class="d-flex align-items-center gap-0">
          <?php foreach ($flow as $i => $s):
            $done   = $i <= $cur;
            $active = $i === $cur;
            $labels = ['diterima'=>'Diterima','dicuci'=>'Dicuci','disetrika'=>'Disetrika','selesai'=>'Selesai','diambil'=>'Diambil'];
            $icons  = ['diterima'=>'inbox','dicuci'=>'droplet','disetrika'=>'thermometer','selesai'=>'check-circle','diambil'=>'bag-check'];
          ?>
            <div class="text-center" style="flex:1;min-width:0">
              <div class="rounded-circle d-inline-flex align-items-center justify-content-center mb-1
                <?= $active ? 'bg-primary text-white' : ($done ? 'bg-success text-white' : 'bg-light text-muted') ?>"
                style="width:36px;height:36px;font-size:.85rem">
                <i class="bi bi-<?= $icons[$s] ?>"></i>
              </div>
              <div class="small <?= $active?'fw-bold text-primary':($done?'text-success':'text-muted') ?>" style="font-size:.7rem"><?= $labels[$s] ?></div>
            </div>
            <?php if ($i < count($flow)-1): ?>
              <div style="flex:1;height:2px;background:<?= $i<$cur?'#198754':'#dee2e6' ?>"></div>
            <?php endif; ?>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>

  <div class="col-lg-4">
    <!-- Customer -->
    <div class="card border-0 shadow-sm mb-3">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-person me-2 text-primary"></i>Pelanggan</div>
      <div class="card-body">
        <div class="fw-medium"><?= h($order['customer_name']) ?></div>
        <?php if ($order['phone']): ?><div class="small text-muted"><i class="bi bi-telephone me-1"></i><?= h($order['phone']) ?></div><?php endif; ?>
        <?php if ($order['customer_email']): ?><div class="small text-muted"><i class="bi bi-envelope me-1"></i><?= h($order['customer_email']) ?></div><?php endif; ?>
        <?php if ($order['address']): ?><div class="small text-muted mt-1"><i class="bi bi-geo me-1"></i><?= h($order['address']) ?></div><?php endif; ?>
      </div>
    </div>

    <!-- Payment -->
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-cash-coin me-2 text-primary"></i>Pembayaran</div>
      <div class="card-body">
        <div class="d-flex justify-content-between mb-1">
          <span class="small text-muted">Total</span><span class="fw-medium"><?= idr($order['total_amount']) ?></span>
        </div>
        <div class="d-flex justify-content-between mb-1">
          <span class="small text-muted">Dibayar</span><span class="text-success fw-medium"><?= idr($paid) ?></span>
        </div>
        <div class="d-flex justify-content-between">
          <span class="small text-muted">Sisa</span>
          <span class="fw-bold <?= $unpaid > 0 ? 'text-danger' : 'text-success' ?>"><?= idr($unpaid) ?></span>
        </div>
        <?php if (!empty($payments)): ?>
          <hr class="my-2">
          <div class="small text-muted mb-1">Riwayat Bayar</div>
          <?php foreach ($payments as $p): ?>
            <div class="d-flex justify-content-between small">
              <span><?= date('d/m/Y', strtotime($p['paid_at'])) ?> — <?= $p['method'] === 'tunai' ? 'Tunai' : 'Transfer' ?></span>
              <span class="text-success"><?= idr($p['amount']) ?></span>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
        <?php if (can('payments.create') && $unpaid > 0): ?>
          <a href="<?= APP_URL ?>/pages/payments/create.php?order_id=<?= $order['id'] ?>" class="btn btn-warning w-100 btn-sm mt-3">
            <i class="bi bi-cash me-1"></i>Catat Pembayaran
          </a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
