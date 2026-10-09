<?php
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_once __DIR__ . '/../../../includes/subscriptions.php';
require_login();
require_permission('subscriptions.manage');

$db = db(); $errors = []; $id = (int)($_GET['id'] ?? 0);
$frequencies = ['weekly' => 'Mingguan', 'biweekly' => '2 Mingguan', 'monthly' => 'Bulanan'];

$sub = ['customer_id' => 0, 'frequency' => 'weekly', 'next_due' => date('Y-m-d'), 'is_active' => 1, 'service_type' => 'none', 'address' => '', 'notes' => '', 'items' => []];
if ($id) {
    $stmt = $db->prepare("SELECT * FROM subscriptions WHERE id = ?");
    $stmt->bind_param('i', $id); $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc(); $stmt->close();
    if (!$row) { flash('error', 'Langganan tidak ditemukan.'); redirect(APP_URL . '/pages/subscriptions/index.php'); }
    $sub = $row;
    $stmt = $db->prepare("SELECT service_id, quantity FROM subscription_items WHERE subscription_id = ?");
    $stmt->bind_param('i', $id); $stmt->execute();
    $sub['items'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) $errors[] = 'Token keamanan tidak valid.';
    $customer_id = (int)($_POST['customer_id'] ?? 0);
    $frequency   = $_POST['frequency'] ?? '';
    $next_due    = trim($_POST['next_due'] ?? '');
    $service_type = $_POST['service_type'] ?? 'none';
    $address     = trim($_POST['address'] ?? '');
    $notes       = trim($_POST['notes'] ?? '');
    $active      = isset($_POST['is_active']) ? 1 : 0;
    $items = [];
    foreach ((is_array($_POST['item_service'] ?? null) ? $_POST['item_service'] : []) as $k => $sid) {
        $sid = (int)$sid; $qty = (float)($_POST['item_quantity'][$k] ?? 0);
        if ($sid > 0 && $qty > 0 && !isset($items[$sid])) $items[$sid] = $qty;
    }

    if (!$customer_id) $errors[] = 'Pilih pelanggan.';
    if (!array_key_exists($frequency, $frequencies)) $errors[] = 'Frekuensi tidak valid.';
    $due = DateTimeImmutable::createFromFormat('!Y-m-d', $next_due);
    $due_err = DateTimeImmutable::getLastErrors();
    if (!$due || ($due_err !== false && ($due_err['warning_count'] || $due_err['error_count']))) $errors[] = 'Tanggal jatuh tempo tidak valid.';
    if (!in_array($service_type, ['none', 'pickup', 'delivery', 'both'], true)) $errors[] = 'Tipe layanan tidak valid.';
    if (($service_type === 'pickup' || $service_type === 'both') && $address === '') $errors[] = 'Alamat wajib diisi untuk layanan ini.';
    if (!$items) $errors[] = 'Tambahkan minimal 1 item layanan.';

    if (!$errors) {
        $stmt = $db->prepare("SELECT id FROM services WHERE id = ? AND is_active = 1");
        foreach ($items as $sid => $qty) {
            $stmt->bind_param('i', $sid); $stmt->execute();
            if (!$stmt->get_result()->fetch_assoc()) { $errors[] = 'Item layanan tidak valid atau nonaktif.'; break; }
        }
        $stmt->close();
    }
    if (!$errors) {
        $db->begin_transaction();
        try {
            if ($id) {
                $addr_db = $address ?: null; $notes_db = $notes ?: null;
                $stmt = $db->prepare("UPDATE subscriptions SET customer_id=?,frequency=?,next_due=?,is_active=?,service_type=?,address=?,notes=? WHERE id=?");
                $stmt->bind_param('ississsi', $customer_id, $frequency, $next_due, $active, $service_type, $addr_db, $notes_db, $id);
                $stmt->execute(); $stmt->close();
                $stmt = $db->prepare("DELETE FROM subscription_items WHERE subscription_id = ?");
                $stmt->bind_param('i', $id); $stmt->execute(); $stmt->close();
                flash('success', 'Langganan berhasil diperbarui.');
            } else {
                $addr_db = $address ?: null; $notes_db = $notes ?: null;
                $stmt = $db->prepare("INSERT INTO subscriptions (customer_id,frequency,next_due,is_active,service_type,address,notes) VALUES (?,?,?,?,?,?,?)");
                $stmt->bind_param('ississs', $customer_id, $frequency, $next_due, $active, $service_type, $addr_db, $notes_db);
                $stmt->execute(); $id = $db->insert_id; $stmt->close();
                flash('success', 'Langganan berhasil ditambahkan.');
            }
            $si = $db->prepare("INSERT INTO subscription_items (subscription_id,service_id,quantity) VALUES (?,?,?)");
            foreach ($items as $sid => $qty) { $si->bind_param('iid', $id, $sid, $qty); $si->execute(); }
            $si->close();
            $db->commit();
            redirect(APP_URL . '/pages/subscriptions/index.php');
        } catch (Throwable $e) {
            $db->rollback();
            $errors[] = 'Gagal menyimpan langganan: ' . $e->getMessage();
        }
    }
    $sub = array_merge($sub, ['customer_id' => $customer_id, 'frequency' => $frequency, 'next_due' => $next_due, 'is_active' => $active, 'service_type' => $service_type, 'address' => $address, 'notes' => $notes]);
    $sub['items'] = [];
    foreach (($_POST['item_service'] ?? []) as $k => $sid) {
        $qty = (float)($_POST['item_quantity'][$k] ?? 0);
        if ((int)$sid > 0 && $qty > 0) $sub['items'][] = ['service_id' => (int)$sid, 'quantity' => $qty];
    }
}

$services  = $db->query("SELECT id, name, type, price, unit FROM services WHERE is_active = 1 ORDER BY type, name")->fetch_all(MYSQLI_ASSOC);
$customers = $db->query("SELECT id, name, phone FROM customers ORDER BY name")->fetch_all(MYSQLI_ASSOC);
$selected  = array_column($sub['items'], null, 'service_id');
$title     = $id ? 'Edit Langganan' : 'Tambah Langganan';
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <h4 class="fw-bold mb-0"><?= $title ?></h4>
  <a href="<?= APP_URL ?>/pages/subscriptions/index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
</div>
<?php foreach ($errors as $e): ?><div class="alert alert-danger py-2 small"><?= h($e) ?></div><?php endforeach; ?>
<form method="POST" novalidate>
  <?= csrf_field() ?>
  <div class="row g-3">
    <div class="col-lg-7">
      <div class="card border-0 shadow-sm mb-3"><div class="card-body">
        <div class="row g-3">
          <div class="col-md-6"><label class="form-label">Pelanggan <span class="text-danger">*</span></label>
            <select name="customer_id" class="form-select ts-select" required>
              <option value="">-- Pilih Pelanggan --</option>
              <?php foreach ($customers as $c): ?><option value="<?= $c['id'] ?>" <?= (int)$sub['customer_id'] === (int)$c['id'] ? 'selected' : '' ?>><?= h($c['name']) ?><?= $c['phone'] ? ' (' . h($c['phone']) . ')' : '' ?></option><?php endforeach; ?>
            </select></div>
          <div class="col-md-6"><label class="form-label">Frekuensi <span class="text-danger">*</span></label>
            <select name="frequency" class="form-select" required><?php foreach ($frequencies as $v => $l): ?><option value="<?= $v ?>" <?= $sub['frequency'] === $v ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
          <div class="col-md-4"><label class="form-label">Jatuh Tempo Berikutnya <span class="text-danger">*</span></label>
            <input type="date" name="next_due" class="form-control" value="<?= h($sub['next_due']) ?>" required></div>
          <div class="col-md-4"><label class="form-label">Antar / Jemput</label>
            <select name="service_type" class="form-select" onchange="toggleAddress()"><?php foreach (['none' => 'Tanpa antar-jemput', 'pickup' => 'Pickup', 'delivery' => 'Delivery', 'both' => 'Pickup + Delivery'] as $v => $l): ?><option value="<?= $v ?>" <?= $sub['service_type'] === $v ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
          <div class="col-md-4 d-flex align-items-end"><div class="form-check form-switch mb-2">
            <input class="form-check-input" type="checkbox" name="is_active" id="isActive" <?= $sub['is_active'] ? 'checked' : '' ?>>
            <label class="form-check-label" for="isActive">Langganan Aktif</label></div></div>
          <div class="col-12" id="addressField"><label class="form-label">Alamat Antar/Jemput</label>
            <textarea name="address" class="form-control" rows="2"><?= h($sub['address']) ?></textarea></div>
          <div class="col-12"><label class="form-label">Catatan</label>
            <textarea name="notes" class="form-control" rows="2"><?= h($sub['notes']) ?></textarea></div>
        </div>
      </div></div>
    </div>
    <div class="col-lg-5">
      <div class="card border-0 shadow-sm"><div class="card-header bg-white fw-semibold">Item Paket <button type="button" class="btn btn-sm btn-primary float-end" onclick="addItem()"><i class="bi bi-plus"></i> Tambah</button></div>
        <div class="card-body p-0">
          <table class="table align-middle mb-0"><thead class="table-light"><tr><th>Layanan</th><th style="width:110px">Jml</th><th style="width:40px"></th></tr></thead>
          <tbody id="itemsBody"></tbody></table>
        </div>
        <div class="card-footer bg-white small text-muted">Total per siklus dihitung dari harga layanan saat order dibuat.</div>
      </div>
      <button type="submit" class="btn btn-primary w-100 mt-3"><i class="bi bi-check-circle me-1"></i>Simpan Langganan</button>
    </div>
  </div>
</form>
<script>
const SERVICES = <?= json_encode(array_map(fn($s) => ['id' => (int)$s['id'], 'name' => $s['name'], 'type' => $s['type'], 'price' => (float)$s['price'], 'unit' => $s['unit']], $services)) ?>;
const SELECTED = <?= json_encode($selected) ?>;
function addItem(serviceId, qty) {
  const body = document.getElementById('itemsBody');
  const tr = document.createElement('tr');
  const opts = SERVICES.map(s => `<option value="${s.id}" ${String(serviceId) === String(s.id) ? 'selected' : ''}>${s.name} — Rp ${s.price.toLocaleString('id-ID')}/${s.unit}</option>`).join('');
  tr.innerHTML = `<td><select name="item_service[]" class="form-select form-select-sm" required><option value="">Pilih layanan</option>${opts}</select></td>
    <td><input type="number" name="item_quantity[]" class="form-control form-control-sm" min="0.1" step="0.1" value="${qty ?? 1}" required></td>
    <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('tr').remove()"><i class="bi bi-trash"></i></button></td>`;
  body.appendChild(tr);
}
function toggleAddress() {
  document.getElementById('addressField').style.display = ['pickup','delivery','both'].includes(document.querySelector('[name=service_type]').value) ? '' : 'none';
}
SELECTED.length ? Object.entries(SELECTED).forEach(([sid, row]) => addItem(sid, row.quantity)) : addItem();
toggleAddress();
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
