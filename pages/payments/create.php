<?php
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_login();
require_permission('payments.create');

$db       = db();
$order_id = (int)($_GET['order_id'] ?? 0);
$errors   = [];

$stmt = $db->prepare(
    "SELECT o.*, c.name AS customer_name
     FROM orders o JOIN customers c ON c.id = o.customer_id
     WHERE o.id = ?"
);
$stmt->bind_param('i', $order_id); $stmt->execute();
$order = $stmt->get_result()->fetch_assoc(); $stmt->close();

if (!$order) { flash('error', 'Order tidak ditemukan.'); redirect(APP_URL . '/pages/orders/index.php'); }

// Already paid total
$stmt = $db->prepare("SELECT COALESCE(SUM(amount),0) FROM payments WHERE order_id = ?");
$stmt->bind_param('i', $order_id); $stmt->execute(); $stmt->bind_result($paid); $stmt->fetch(); $stmt->close();
$remaining = max(0, $order['total_amount'] - $paid);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) { $errors[] = 'Token keamanan tidak valid.'; }
    $amount    = (float)($_POST['amount'] ?? 0);
    $method    = $_POST['method'] ?? 'tunai';
    $reference = trim($_POST['reference'] ?? '');

    if ($amount <= 0)                             $errors[] = 'Jumlah pembayaran harus lebih dari 0.';
    if ($amount > $remaining + 0.01)              $errors[] = 'Jumlah melebihi sisa tagihan ' . idr($remaining) . '.';
    if (!in_array($method, ['tunai','transfer'])) $errors[] = 'Metode pembayaran tidak valid.';

    if (empty($errors)) {
        $user_id = $_SESSION['user_id'];
        $stmt = $db->prepare("INSERT INTO payments (order_id,user_id,amount,method,reference) VALUES (?,?,?,?,?)");
        $stmt->bind_param('iidss', $order_id, $user_id, $amount, $method, $reference ?: null);
        $stmt->execute(); $stmt->close();

        // If fully paid and status is still diterima/dicuci, don't auto-advance — keep manual
        flash('success', 'Pembayaran sebesar ' . idr($amount) . ' berhasil dicatat.');
        redirect(APP_URL . '/pages/orders/view.php?order_number=' . urlencode($order['order_number']));
    }
}

$title = 'Catat Pembayaran';
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <h4 class="fw-bold mb-0">Catat Pembayaran</h4>
  <a href="<?= APP_URL ?>/pages/orders/view.php?order_number=<?= h($order['order_number']) ?>" class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-arrow-left me-1"></i>Kembali ke Order
  </a>
</div>

<?php foreach ($errors as $e): ?><div class="alert alert-danger py-2 small"><?= h($e) ?></div><?php endforeach; ?>

<div class="row g-3">
  <div class="col-lg-6">
    <div class="card border-0 shadow-sm mb-3">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-receipt me-2 text-primary"></i>Info Order</div>
      <div class="card-body">
        <div class="row g-2 small">
          <div class="col-6 text-muted">No. Order</div><div class="col-6 fw-medium font-monospace"><?= h($order['order_number']) ?></div>
          <div class="col-6 text-muted">Pelanggan</div><div class="col-6"><?= h($order['customer_name']) ?></div>
          <div class="col-6 text-muted">Total</div><div class="col-6 fw-bold"><?= idr($order['total_amount']) ?></div>
          <div class="col-6 text-muted">Sudah Dibayar</div><div class="col-6 text-success"><?= idr($paid) ?></div>
          <div class="col-6 text-muted">Sisa Tagihan</div>
          <div class="col-6 fw-bold <?= $remaining > 0 ? 'text-danger' : 'text-success' ?>"><?= idr($remaining) ?></div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-cash-coin me-2 text-primary"></i>Form Pembayaran</div>
      <div class="card-body">
        <?php if ($remaining <= 0): ?>
          <div class="alert alert-success"><i class="bi bi-check-circle me-1"></i>Order ini sudah lunas.</div>
        <?php else: ?>
        <form method="POST" novalidate>
          <?= csrf_field() ?>
          <div class="mb-3">
            <label class="form-label">Jumlah Bayar (Rp) <span class="text-danger">*</span></label>
            <input type="number" name="amount" class="form-control"
                   min="1" step="500" max="<?= $remaining ?>"
                   value="<?= h($_POST['amount'] ?? $remaining) ?>" required autofocus>
            <div class="form-text">Sisa tagihan: <?= idr($remaining) ?></div>
          </div>
          <div class="mb-3">
            <label class="form-label">Metode Bayar <span class="text-danger">*</span></label>
            <div class="d-flex gap-3">
              <div class="form-check">
                <input class="form-check-input" type="radio" name="method" id="tunai" value="tunai"
                       <?= ($_POST['method'] ?? 'tunai') === 'tunai' ? 'checked' : '' ?> onchange="toggleRef(this)">
                <label class="form-check-label" for="tunai"><i class="bi bi-cash me-1"></i>Tunai</label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="radio" name="method" id="transfer" value="transfer"
                       <?= ($_POST['method'] ?? '') === 'transfer' ? 'checked' : '' ?> onchange="toggleRef(this)">
                <label class="form-check-label" for="transfer"><i class="bi bi-phone me-1"></i>Transfer</label>
              </div>
            </div>
          </div>
          <div class="mb-4" id="refField" style="display:<?= ($_POST['method'] ?? '') === 'transfer' ? 'block' : 'none' ?>">
            <label class="form-label">No. Referensi Transfer</label>
            <input type="text" name="reference" class="form-control" value="<?= h($_POST['reference'] ?? '') ?>" placeholder="Nomor transaksi bank">
          </div>
          <button type="submit" class="btn btn-warning w-100 fw-semibold">
            <i class="bi bi-check-circle me-1"></i>Simpan Pembayaran
          </button>
        </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<script>
function toggleRef(radio) {
  document.getElementById('refField').style.display = radio.value === 'transfer' ? 'block' : 'none';
}
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
