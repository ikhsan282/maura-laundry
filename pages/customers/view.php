<?php
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_login();
require_permission('customers.view');

$db = db();
$id = (int)($_GET['id'] ?? 0);
$errors = [];

$stmt = $db->prepare("SELECT c.*, COALESCE(cp.points,0) AS points FROM customers c LEFT JOIN customer_points cp ON cp.customer_id=c.id WHERE c.id=?");
$stmt->bind_param('i', $id); $stmt->execute();
$customer = $stmt->get_result()->fetch_assoc(); $stmt->close();

if (!$customer) { flash('error', 'Pelanggan tidak ditemukan.'); redirect(APP_URL . '/pages/customers/index.php'); }

// Redemption POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['redeem'])) {
    if (!can('customers.edit')) { $errors[] = 'Anda tidak memiliki izin untuk menukar poin.'; }
    if (!csrf_verify()) { $errors[] = 'Token keamanan tidak valid.'; }
    $redeem_points = (int)($_POST['redeem_points'] ?? 0);
    if ($redeem_points <= 0) $errors[] = 'Jumlah poin harus lebih dari 0.';
    if ($redeem_points > $customer['points']) $errors[] = 'Poin tidak mencukupi.';
    $notes = trim($_POST['notes'] ?? '');
    if (!$notes) $errors[] = 'Catatan wajib diisi.';

    if (empty($errors)) {
        $user_id = (int)$_SESSION['user_id'];
        $db->begin_transaction();
        try {
            $db->query("INSERT INTO customer_points (customer_id,points) VALUES ({$id},-{$redeem_points}) ON DUPLICATE KEY UPDATE points=points-{$redeem_points}");
            $neg = -$redeem_points;
            $pt = $db->prepare("INSERT INTO customer_point_transactions (customer_id,user_id,type,points,notes) VALUES (?,?,'redeem',?,?)");
            $pt->bind_param('iiis', $id, $user_id, $neg, $notes);
            $pt->execute(); $pt->close();
            $db->commit();
            flash('success', "Berhasil menukar {$redeem_points} poin.");
            redirect(APP_URL . '/pages/customers/view.php?id=' . $id);
        } catch (Throwable $e) {
            $db->rollback();
            $errors[] = 'Penukaran gagal: ' . $e->getMessage();
        }
    }
}

// Points history
$stmt = $db->prepare("SELECT pt.*, u.name AS user_name FROM customer_point_transactions pt JOIN users u ON u.id=pt.user_id WHERE pt.customer_id=? ORDER BY pt.created_at DESC LIMIT 50");
$stmt->bind_param('i', $id); $stmt->execute();
$history = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();

$title = 'Detail Pelanggan';
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <h4 class="fw-bold mb-0">Detail Pelanggan</h4>
  <a href="<?= APP_URL ?>/pages/customers/index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
</div>

<?php foreach ($errors as $e): ?><div class="alert alert-danger py-2 small"><?= h($e) ?></div><?php endforeach; ?>

<div class="row g-3">
  <div class="col-lg-6">
    <div class="card border-0 shadow-sm mb-3">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-person me-2 text-primary"></i>Info Pelanggan</div>
      <div class="card-body">
        <div class="row g-2 small">
          <div class="col-4 text-muted">Nama</div><div class="col-8 fw-medium"><?= h($customer['name']) ?></div>
          <div class="col-4 text-muted">Telepon</div><div class="col-8"><?= h($customer['phone'] ?? '-') ?></div>
          <div class="col-4 text-muted">Email</div><div class="col-8"><?= h($customer['email'] ?? '-') ?></div>
          <div class="col-4 text-muted">Alamat</div><div class="col-8"><?= h($customer['address'] ?? '-') ?></div>
        </div>
      </div>
    </div>

    <div class="card border-0 shadow-sm">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-star me-2 text-warning"></i>Loyalty Points</div>
      <div class="card-body text-center">
        <h1 class="display-4 fw-bold text-warning mb-0"><?= $customer['points'] ?></h1>
        <p class="text-muted small mb-0">Poin tersedia</p>
      </div>
    </div>
  </div>

  <div class="col-lg-6">
    <?php if (can('customers.edit')): ?>
    <div class="card border-0 shadow-sm mb-3">
      <div class="card-header bg-white fw-semibold"><i class="bi bi-arrow-down-circle me-2 text-danger"></i>Tukar Poin</div>
      <div class="card-body">
        <form method="POST">
          <?= csrf_field() ?>
          <input type="hidden" name="redeem" value="1">
          <div class="mb-3">
            <label class="form-label">Jumlah Poin <span class="text-danger">*</span></label>
            <input type="number" name="redeem_points" class="form-control" min="1" max="<?= $customer['points'] ?>" required>
            <div class="form-text">Tersedia: <?= $customer['points'] ?> poin</div>
          </div>
          <div class="mb-3">
            <label class="form-label">Catatan <span class="text-danger">*</span></label>
            <input type="text" name="notes" class="form-control" placeholder="Tukar hadiah, diskon, dll" required>
          </div>
          <button type="submit" class="btn btn-danger w-100" <?= $customer['points'] <= 0 ? 'disabled' : '' ?>>
            <i class="bi bi-check-circle me-1"></i>Tukar Poin
          </button>
        </form>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<div class="card border-0 shadow-sm mt-3">
  <div class="card-header bg-white fw-semibold"><i class="bi bi-clock-history me-2 text-info"></i>Riwayat Poin</div>
  <div class="card-body p-0">
    <table class="table table-hover align-middle mb-0">
      <thead class="table-light">
        <tr><th>Tanggal</th><th>Tipe</th><th class="text-end">Poin</th><th>Catatan</th><th>Oleh</th></tr>
      </thead>
      <tbody>
      <?php if (empty($history)): ?>
        <tr><td colspan="5" class="text-center text-muted py-4">Belum ada riwayat poin</td></tr>
      <?php else: foreach ($history as $h): ?>
        <tr>
          <td class="small"><?= date('d/m/Y H:i', strtotime($h['created_at'])) ?></td>
          <td><span class="badge bg-<?= $h['type'] === 'earn' ? 'success' : 'danger' ?>-subtle text-<?= $h['type'] === 'earn' ? 'success' : 'danger' ?>"><?= $h['type'] === 'earn' ? 'Dapat' : 'Tukar' ?></span></td>
          <td class="text-end fw-bold <?= $h['points'] > 0 ? 'text-success' : 'text-danger' ?>"><?= $h['points'] > 0 ? '+' : '' ?><?= $h['points'] ?></td>
          <td class="small text-muted"><?= h($h['notes'] ?? '-') ?></td>
          <td class="small"><?= h($h['user_name']) ?></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
