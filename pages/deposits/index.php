<?php
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_login();
require_permission('deposits.view');

$db = db();
$customer_id = (int)($_GET['customer_id'] ?? 0);
$errors = [];

if ($customer_id) {
    $stmt = $db->prepare("SELECT c.*, COALESCE(SUM(dt.amount),0) AS deposit_balance FROM customers c LEFT JOIN customer_deposit_transactions dt ON dt.customer_id=c.id WHERE c.id=? GROUP BY c.id");
    $stmt->bind_param('i', $customer_id);
    $stmt->execute();
    $customer = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$customer) { flash('error', 'Pelanggan tidak ditemukan.'); redirect(APP_URL . '/pages/deposits/index.php'); }
} else {
    $customer = null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_permission('deposits.manage');
    if (!csrf_verify()) $errors[] = 'Token keamanan tidak valid.';
    $type = $_POST['type'] ?? '';
    $amount = (float)($_POST['amount'] ?? 0);
    $method = $_POST['method'] ?? 'cash';
    $reference = trim($_POST['reference'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    if (!in_array($type, ['topup','debit','refund'], true)) $errors[] = 'Jenis transaksi tidak valid.';
    if (!in_array($method, ['cash','transfer'], true)) $errors[] = 'Metode tidak valid.';
    if ($amount <= 0) $errors[] = 'Jumlah harus lebih dari 0.';

    if (!$errors) {
        $db->begin_transaction();
        try {
            // Serialize all balance-changing transactions on the customer row.
            // This lock makes the following SUM and validation atomic per customer.
            $lock = $db->prepare("SELECT id FROM customers WHERE id=? FOR UPDATE");
            $lock->bind_param('i', $customer_id); $lock->execute(); $lock->store_result(); $lock->close();
            $lock = $db->prepare("SELECT COALESCE(SUM(amount),0) AS balance FROM customer_deposit_transactions WHERE customer_id=?");
            $lock->bind_param('i', $customer_id); $lock->execute();
            $balance = (float)$lock->get_result()->fetch_assoc()['balance']; $lock->close();
            $signed = deposit_delta($type, $amount);
            if ($signed < 0 && $balance + $signed < -0.001) throw new RuntimeException('Saldo deposit tidak mencukupi.');
            $uid = (int)$_SESSION['user_id'];
            $stmt = $db->prepare("INSERT INTO customer_deposit_transactions (customer_id,user_id,type,amount,method,reference,notes) VALUES (?,?,?,?,?,?,?)");
            $ref = $reference ?: null; $note = $notes ?: null;
            $stmt->bind_param('iisdsss', $customer_id, $uid, $type, $signed, $method, $ref, $note);
            $stmt->execute(); $stmt->close();
            $db->commit();
            flash('success', 'Transaksi deposit berhasil dicatat.');
            redirect(APP_URL . '/pages/deposits/index.php?customer_id=' . $customer_id);
        } catch (Throwable $e) {
            $db->rollback();
            $errors[] = $e->getMessage();
        }
    }
}

if ($customer) {
    $stmt = $db->prepare("SELECT dt.*, u.name AS staff_name, o.order_number FROM customer_deposit_transactions dt JOIN users u ON u.id=dt.user_id LEFT JOIN orders o ON o.id=dt.order_id WHERE dt.customer_id=? ORDER BY dt.created_at DESC, dt.id DESC");
    $stmt->bind_param('i', $customer_id); $stmt->execute();
    $transactions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
} else {
    $transactions = [];
    $customers = $db->query("SELECT c.id,c.name,c.phone,(SELECT COALESCE(SUM(dt.amount),0) FROM customer_deposit_transactions dt WHERE dt.customer_id=c.id) AS balance,(SELECT COUNT(*) FROM customer_deposit_transactions dt2 WHERE dt2.customer_id=c.id) AS transaction_count FROM customers c ORDER BY c.name")->fetch_all(MYSQLI_ASSOC);
}

$title = 'Deposit Pelanggan';
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <div><h4 class="fw-bold mb-0">Deposit Pelanggan</h4><p class="text-muted small mb-0">Saldo dihitung dari ledger transaksi</p></div>
  <?php if ($customer): ?><a class="btn btn-outline-secondary btn-sm" href="<?= APP_URL ?>/pages/deposits/index.php">Kembali</a><?php endif; ?>
</div>
<?php foreach ($errors as $e): ?><div class="alert alert-danger py-2"><?= h($e) ?></div><?php endforeach; ?>
<?php if (!$customer): ?>
<div class="card border-0 shadow-sm"><div class="card-body p-0"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
<thead class="table-light"><tr><th>Pelanggan</th><th>Telepon</th><th class="text-end">Saldo</th><th class="text-center">Mutasi</th><th></th></tr></thead><tbody>
<?php foreach ($customers as $c): ?><tr><td class="fw-medium"><?= h($c['name']) ?></td><td><?= h($c['phone'] ?? '-') ?></td><td class="text-end fw-bold"><?= idr($c['balance']) ?></td><td class="text-center"><?= (int)$c['transaction_count'] ?></td><td class="text-end"><a class="btn btn-sm btn-outline-primary" href="?customer_id=<?= $c['id'] ?>">Detail</a></td></tr><?php endforeach; ?>
</tbody></table></div></div></div>
<?php else: ?>
<div class="row g-3"><div class="col-lg-4">
<div class="card border-0 shadow-sm mb-3"><div class="card-body"><div class="text-muted small">Pelanggan</div><h5><?= h($customer['name']) ?></h5><div class="text-muted small">Saldo Deposit</div><div class="fs-3 fw-bold text-primary"><?= idr($customer['deposit_balance']) ?></div></div></div>
<?php if (can('deposits.manage')): ?><div class="card border-0 shadow-sm"><div class="card-header bg-white fw-semibold">Catat Transaksi</div><div class="card-body"><form method="post"><?= csrf_field() ?>
<div class="mb-2"><label class="form-label">Jenis</label><select name="type" class="form-select" required><option value="topup">Top-up</option><option value="refund">Refund/Pengembalian Dana</option><option value="debit">Debit/Penyesuaian</option></select></div>
<div class="mb-2"><label class="form-label">Jumlah</label><input type="number" min="1" step="1" name="amount" class="form-control" required></div>
<div class="mb-2"><label class="form-label">Metode</label><select name="method" class="form-select"><option value="cash">Tunai</option><option value="transfer">Transfer</option></select></div>
<div class="mb-2"><label class="form-label">Referensi</label><input name="reference" maxlength="100" class="form-control"></div>
<div class="mb-3"><label class="form-label">Catatan</label><input name="notes" maxlength="255" class="form-control"></div>
<button class="btn btn-primary w-100">Simpan</button></form></div></div><?php endif; ?>
</div><div class="col-lg-8"><div class="card border-0 shadow-sm"><div class="card-header bg-white fw-semibold">Riwayat Mutasi</div><div class="card-body p-0"><div class="table-responsive"><table class="table align-middle mb-0"><thead class="table-light"><tr><th>Waktu</th><th>Jenis</th><th>Referensi</th><th>Petugas</th><th class="text-end">Nominal</th></tr></thead><tbody>
<?php if (!$transactions): ?><tr><td colspan="5" class="text-center text-muted py-4">Belum ada transaksi</td></tr><?php else: foreach ($transactions as $t): ?><tr><td class="small"><?= date('d/m/Y H:i', strtotime($t['created_at'])) ?></td><td><?= h(ucfirst($t['type'])) ?></td><td class="small"><?= h($t['order_number'] ?: ($t['reference'] ?: ($t['notes'] ?: '-'))) ?></td><td><?= h($t['staff_name']) ?></td><td class="text-end fw-bold <?= $t['amount'] >= 0 ? 'text-success' : 'text-danger' ?>"><?= ($t['amount'] >= 0 ? '+' : '') . idr($t['amount']) ?></td></tr><?php endforeach; endif; ?>
</tbody></table></div></div></div></div></div>
<?php endif; ?>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
