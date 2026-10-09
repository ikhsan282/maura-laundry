<?php
require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../includes/functions.php';
require_once __DIR__ . '/../../../includes/auth.php';
require_login();
require_permission('orders.create');

$db     = db();
$errors = [];

// Load active services
$services = $db->query("SELECT * FROM services WHERE is_active=1 ORDER BY type, name")->fetch_all(MYSQLI_ASSOC);

// Load customers for select2-style dropdown
$customers = $db->query("SELECT id, name, phone FROM customers ORDER BY name")->fetch_all(MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) { $errors[] = 'Token keamanan tidak valid.'; }

    $customer_id  = (int)($_POST['customer_id'] ?? 0);
    $notes        = trim($_POST['notes'] ?? '');
    $service_ids  = $_POST['service_id']  ?? [];
    $quantities   = $_POST['quantity']    ?? [];
    $service_type = $_POST['service_type'] ?? 'none';
    $pickup_address = trim($_POST['pickup_address'] ?? '');
    $pickup_contact = trim($_POST['pickup_contact'] ?? '');
    $pickup_fee = (float)($_POST['pickup_fee'] ?? 0);
    $delivery_address = trim($_POST['delivery_address'] ?? '');
    $delivery_contact = trim($_POST['delivery_contact'] ?? '');
    $delivery_fee = (float)($_POST['delivery_fee'] ?? 0);

    if (!$customer_id)          $errors[] = 'Pilih pelanggan.';
    if (empty($service_ids))    $errors[] = 'Tambahkan minimal 1 layanan.';
    if (!in_array($service_type, ['none','pickup','delivery','both'], true)) $errors[] = 'Tipe layanan tidak valid.';
    if (($service_type === 'pickup' || $service_type === 'both') && !$pickup_address) $errors[] = 'Alamat pickup wajib diisi.';
    if (($service_type === 'delivery' || $service_type === 'both') && !$delivery_address) $errors[] = 'Alamat delivery wajib diisi.';

    // Validate items
    $items       = [];
    $total       = 0;
    $max_days    = 0;
    foreach ($service_ids as $k => $sid) {
        $sid = (int)$sid;
        $qty = (float)($quantities[$k] ?? 0);
        if ($sid <= 0 || $qty <= 0) continue;

        // Fetch service
        $stmt = $db->prepare("SELECT * FROM services WHERE id = ? AND is_active = 1");
        $stmt->bind_param('i', $sid); $stmt->execute();
        $svc  = $stmt->get_result()->fetch_assoc(); $stmt->close();
        if (!$svc) continue;

        $subtotal   = $svc['price'] * $qty;
        $total     += $subtotal;
        $max_days   = max($max_days, $svc['duration_days']);
        $items[]    = ['service_id' => $sid, 'quantity' => $qty, 'price' => $svc['price'], 'subtotal' => $subtotal];
    }
    if (empty($items)) $errors[] = 'Item order tidak valid.';
    if ($pickup_fee < 0 || $delivery_fee < 0) $errors[] = 'Biaya pickup/delivery tidak boleh negatif.';
    if (!$errors) {
        $total = 0;
        foreach ($items as $it) $total += $it['subtotal'];
        $total = order_grand_total($total, $service_type, $pickup_fee, $delivery_fee);
    }

    if (empty($errors)) {
        $order_number  = generate_order_number();
        $estimated     = date('Y-m-d', strtotime("+{$max_days} days"));
        $user_id       = $_SESSION['user_id'];

        $db->begin_transaction();
        try {
            $pickup_status = in_array($service_type, ['pickup','both'], true) ? 'scheduled' : 'not_required';
            $delivery_status = in_array($service_type, ['delivery','both'], true) ? 'scheduled' : 'not_required';
            $pickup_at = null;
            if (!empty($_POST['pickup_scheduled_at'])) {
                $dt = DateTime::createFromFormat('Y-m-d\\TH:i', $_POST['pickup_scheduled_at']);
                $date_errors = DateTime::getLastErrors();
                if (!$dt || ($date_errors !== false && ($date_errors['warning_count'] || $date_errors['error_count']))) throw new Exception('Format jadwal pickup tidak valid.');
                $pickup_at = $dt->format('Y-m-d H:i:s');
            }
            $delivery_at = null;
            if (!empty($_POST['delivery_scheduled_at'])) {
                $dt = DateTime::createFromFormat('Y-m-d\\TH:i', $_POST['delivery_scheduled_at']);
                $date_errors = DateTime::getLastErrors();
                if (!$dt || ($date_errors !== false && ($date_errors['warning_count'] || $date_errors['error_count']))) throw new Exception('Format jadwal delivery tidak valid.');
                $delivery_at = $dt->format('Y-m-d H:i:s');
            }
            $stmt = $db->prepare("INSERT INTO orders (order_number,customer_id,user_id,total_amount,estimated_done,notes,service_type,pickup_address,pickup_contact,pickup_fee,pickup_scheduled_at,pickup_status,delivery_address,delivery_contact,delivery_fee,delivery_scheduled_at,delivery_status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->bind_param('siidsssssdssssdss', $order_number, $customer_id, $user_id, $total, $estimated, $notes, $service_type, $pickup_address, $pickup_contact, $pickup_fee, $pickup_at, $pickup_status, $delivery_address, $delivery_contact, $delivery_fee, $delivery_at, $delivery_status);
            $stmt->execute();
            $order_id = $db->insert_id;
            $stmt->close();

            $si = $db->prepare("INSERT INTO order_items (order_id,service_id,quantity,price,subtotal) VALUES (?,?,?,?,?)");
            foreach ($items as $it) {
                $si->bind_param('iiddd', $order_id, $it['service_id'], $it['quantity'], $it['price'], $it['subtotal']);
                $si->execute();
            }
            $si->close();

            $db->commit();
            flash('success', "Order {$order_number} berhasil dibuat!");
            redirect(APP_URL . '/pages/orders/view.php?order_number=' . urlencode($order_number));
        } catch (Exception $e) {
            $db->rollback();
            $errors[] = 'Gagal menyimpan order: ' . $e->getMessage();
        }
    }
}

$title = 'Buat Order';
require_once __DIR__ . '/../../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4">
  <div><h4 class="fw-bold mb-0">Buat Order Baru</h4></div>
  <a href="<?= APP_URL ?>/pages/orders/index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
</div>

<?php foreach ($errors as $e): ?>
  <div class="alert alert-danger py-2 small"><i class="bi bi-exclamation-circle me-1"></i><?= h($e) ?></div>
<?php endforeach; ?>

<form method="POST" id="orderForm">
  <?= csrf_field() ?>
  <div class="row g-3">
    <!-- Left -->
    <div class="col-lg-8">
      <!-- Customer -->
      <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold"><i class="bi bi-person me-2 text-primary"></i>Pilih Pelanggan</div>
        <div class="card-body">
          <div class="row g-2">
            <div class="col-md-8">
              <select name="customer_id" class="form-select ts-select" required id="customerSelect">
                <option value="">-- Pilih Pelanggan --</option>
                <?php foreach ($customers as $c): ?>
                  <option value="<?= $c['id'] ?>" data-phone="<?= h($c['phone']) ?>"
                    <?= (isset($_POST['customer_id']) && $_POST['customer_id'] == $c['id']) ? 'selected' : '' ?>>
                    <?= h($c['name']) ?> <?= $c['phone'] ? '('. h($c['phone']).')' : '' ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <a href="<?= APP_URL ?>/pages/customers/create.php?redirect=order" class="btn btn-outline-primary w-100">
                <i class="bi bi-person-plus me-1"></i>Pelanggan Baru
              </a>
            </div>
          </div>
        </div>
      </div>

      <!-- Items -->
      <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
          <span><i class="bi bi-list-check me-2 text-primary"></i>Item Layanan</span>
          <button type="button" class="btn btn-sm btn-primary" onclick="addRow()">
            <i class="bi bi-plus me-1"></i>Tambah Item
          </button>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table align-middle mb-0" id="itemsTable">
              <thead class="table-light">
                <tr><th style="width:40%">Layanan</th><th style="width:20%">Jml / Berat</th><th style="width:20%">Harga</th><th style="width:15%">Subtotal</th><th style="width:5%"></th></tr>
              </thead>
              <tbody id="itemsBody">
                <tr id="emptyRow"><td colspan="5" class="text-center text-muted py-3 small">Klik "Tambah Item" untuk menambah layanan</td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold"><i class="bi bi-truck me-2 text-primary"></i>Pickup / Delivery</div>
        <div class="card-body">
          <div class="mb-3"><label class="form-label">Jenis Layanan</label><select name="service_type" id="serviceType" class="form-select" onchange="toggleTransport();updateTotal()">
            <?php foreach (['none'=>'Tanpa antar-jemput','pickup'=>'Pickup','delivery'=>'Delivery','both'=>'Pickup + Delivery'] as $v=>$label): ?><option value="<?= $v ?>" <?= ($_POST['service_type'] ?? 'none')===$v?'selected':'' ?>><?= $label ?></option><?php endforeach; ?>
          </select></div>
          <div id="pickupFields" class="row g-2 mb-2"><div class="col-md-6"><label class="form-label">Alamat Pickup</label><textarea name="pickup_address" class="form-control" rows="2"><?= h($_POST['pickup_address'] ?? '') ?></textarea></div><div class="col-md-6"><label class="form-label">Kontak Pickup</label><input name="pickup_contact" class="form-control" value="<?= h($_POST['pickup_contact'] ?? '') ?>"><label class="form-label mt-2">Jadwal</label><input type="datetime-local" name="pickup_scheduled_at" class="form-control" value="<?= h($_POST['pickup_scheduled_at'] ?? '') ?>"></div><div class="col-md-4"><label class="form-label">Biaya Pickup</label><input type="number" min="0" step="1" id="pickupFee" name="pickup_fee" class="form-control" value="<?= h($_POST['pickup_fee'] ?? 0) ?>" oninput="updateTotal()"></div></div>
          <div id="deliveryFields" class="row g-2"><div class="col-md-6"><label class="form-label">Alamat Delivery</label><textarea name="delivery_address" class="form-control" rows="2"><?= h($_POST['delivery_address'] ?? '') ?></textarea></div><div class="col-md-6"><label class="form-label">Kontak Delivery</label><input name="delivery_contact" class="form-control" value="<?= h($_POST['delivery_contact'] ?? '') ?>"><label class="form-label mt-2">Jadwal</label><input type="datetime-local" name="delivery_scheduled_at" class="form-control" value="<?= h($_POST['delivery_scheduled_at'] ?? '') ?>"></div><div class="col-md-4"><label class="form-label">Biaya Delivery</label><input type="number" min="0" step="1" id="deliveryFee" name="delivery_fee" class="form-control" value="<?= h($_POST['delivery_fee'] ?? 0) ?>" oninput="updateTotal()"></div></div>
        </div>
      </div>

      <!-- Notes -->
      <div class="card border-0 shadow-sm">
        <div class="card-header bg-white fw-semibold"><i class="bi bi-chat-left-text me-2 text-primary"></i>Catatan</div>
        <div class="card-body">
          <textarea name="notes" class="form-control" rows="2" placeholder="Catatan khusus (opsional)..."><?= h($_POST['notes'] ?? '') ?></textarea>
        </div>
      </div>
    </div>

    <!-- Right: Summary -->
    <div class="col-lg-4">
      <div class="card border-0 shadow-sm sticky-top" style="top:1rem">
        <div class="card-header bg-white fw-semibold"><i class="bi bi-receipt me-2 text-primary"></i>Ringkasan Order</div>
        <div class="card-body">
          <div class="d-flex justify-content-between mb-2 small">
            <span class="text-muted">Estimasi Selesai</span>
            <span id="estDone" class="fw-medium">—</span>
          </div>
          <hr>
          <div class="d-flex justify-content-between fw-bold">
            <span>Total</span>
            <span id="totalDisplay" class="text-primary">Rp 0</span>
          </div>
          <input type="hidden" id="totalInput" name="total_amount" value="0">
          <button type="submit" class="btn btn-primary w-100 mt-3">
            <i class="bi bi-check-circle me-1"></i>Simpan Order
          </button>
        </div>
      </div>
    </div>
  </div>
</form>

<!-- Service data for JS -->
<script>
const SERVICES = <?= json_encode(array_map(fn($s) => [
    'id'    => $s['id'],
    'name'  => $s['name'],
    'type'  => $s['type'],
    'price' => (float)$s['price'],
    'unit'  => $s['unit'],
    'days'  => $s['duration_days'],
], $services)) ?>;

const APP_URL = '<?= APP_URL ?>';
let rowCount  = 0;

function addRow() {
  document.getElementById('emptyRow')?.remove();
  const body = document.getElementById('itemsBody');
  const idx  = rowCount++;
  const opts = SERVICES.map(s =>
    `<option value="${s.id}" data-price="${s.price}" data-unit="${s.unit}" data-days="${s.days}">${s.name} (${s.type}) — Rp ${s.price.toLocaleString('id-ID')}</option>`
  ).join('');
  const tr = document.createElement('tr');
  tr.id   = `row_${idx}`;
  tr.innerHTML = `
    <td><select name="service_id[]" class="form-select form-select-sm ts-service" id="service_${idx}" onchange="onServiceChange(${idx})" required>
      <option value="">Pilih layanan</option>${opts}
    </select></td>
    <td><div class="input-group input-group-sm">
      <input type="number" name="quantity[]" id="qty_${idx}" class="form-control" min="0.1" step="0.1" value="1" oninput="calcRow(${idx})" required>
      <span class="input-group-text" id="unit_${idx}">pcs</span>
    </div></td>
    <td><span id="price_${idx}" class="small">—</span></td>
    <td><span id="sub_${idx}" class="fw-medium small">—</span></td>
    <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="removeRow(${idx})"><i class="bi bi-trash"></i></button></td>`;
  body.appendChild(tr);
  new TomSelect('#service_'+idx, {});
}

function onServiceChange(idx) {
  const sel   = document.querySelector(`#row_${idx} select[name="service_id[]"]`);
  const opt   = sel.selectedOptions[0];
  const price = parseFloat(opt?.dataset?.price || 0);
  const unit  = opt?.dataset?.unit || 'pcs';
  document.getElementById(`unit_${idx}`).textContent  = unit;
  document.getElementById(`price_${idx}`).textContent = price ? 'Rp ' + price.toLocaleString('id-ID') : '—';
  calcRow(idx);
}

function calcRow(idx) {
  const sel   = document.querySelector(`#row_${idx} select[name="service_id[]"]`);
  const price = parseFloat(sel?.selectedOptions[0]?.dataset?.price || 0);
  const qty   = parseFloat(document.getElementById(`qty_${idx}`)?.value || 0);
  const sub   = price * qty;
  document.getElementById(`sub_${idx}`).textContent = sub ? 'Rp ' + sub.toLocaleString('id-ID') : '—';
  updateTotal();
}

function removeRow(idx) {
  document.getElementById(`row_${idx}`)?.remove();
  if (!document.getElementById('itemsBody').children.length) {
    const tr  = document.createElement('tr'); tr.id = 'emptyRow';
    tr.innerHTML = '<td colspan="5" class="text-center text-muted py-3 small">Klik "Tambah Item" untuk menambah layanan</td>';
    document.getElementById('itemsBody').appendChild(tr);
  }
  updateTotal();
}

function updateTotal() {
  let total = 0, maxDays = 0;
  document.querySelectorAll('#itemsBody tr[id^="row_"]').forEach((tr, i) => {
    const sel   = tr.querySelector('select[name="service_id[]"]');
    const price = parseFloat(sel?.selectedOptions[0]?.dataset?.price || 0);
    const days  = parseInt(sel?.selectedOptions[0]?.dataset?.days || 0);
    const qty   = parseFloat(tr.querySelector('input[name="quantity[]"]')?.value || 0);
    total   += price * qty;
    maxDays  = Math.max(maxDays, days);
  });
  const type = document.getElementById('serviceType')?.value || 'none';
  if (type === 'pickup' || type === 'both') total += parseFloat(document.getElementById('pickupFee')?.value || 0);
  if (type === 'delivery' || type === 'both') total += parseFloat(document.getElementById('deliveryFee')?.value || 0);
  document.getElementById('totalDisplay').textContent = 'Rp ' + total.toLocaleString('id-ID');
  document.getElementById('totalInput').value          = total;
  if (maxDays) {
    const d = new Date(); d.setDate(d.getDate() + maxDays);
    document.getElementById('estDone').textContent = d.toLocaleDateString('id-ID',{day:'2-digit',month:'long',year:'numeric'});
  } else {
    document.getElementById('estDone').textContent = '—';
  }
}

function toggleTransport() {
  const type = document.getElementById('serviceType').value;
  document.getElementById('pickupFields').style.display = ['pickup','both'].includes(type) ? '' : 'none';
  document.getElementById('deliveryFields').style.display = ['delivery','both'].includes(type) ? '' : 'none';
}

// Auto-add one row
addRow();
toggleTransport();
</script>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
