<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_login();
$user = current_user();
if ($user['role_id'] != 5) redirect(APP_URL . '/pages/dashboard.php');
$db = db();
$uid = (int)$user['id'];

// Get customer linked to this user (if exists)
$stmt = $db->prepare("SELECT * FROM customers WHERE user_id = ? LIMIT 1");
$stmt->bind_param('i', $uid);
$stmt->execute();
$customer = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get orders for this user (as customer)
$orders = [];
if ($customer) {
    $stmt = $db->prepare("
        SELECT o.id, o.order_number, o.status, o.total_amount, o.created_at, o.estimated_done, o.notes,
               (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count,
               (SELECT COALESCE(SUM(amount),0) FROM payments WHERE order_id = o.id) as paid
        FROM orders o
        WHERE o.customer_id = ?
        ORDER BY o.created_at DESC
    ");
    $stmt->bind_param('i', $customer['id']);
    $stmt->execute();
    $orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Recent 5 orders
$recent = array_slice($orders, 0, 5);
?><!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>Portal Pelanggan</title>
<meta name="theme-color" content="#0d6efd">
<link rel="manifest" href="<?=APP_URL?>/manifest.json">
<script>(function(){try{var t=localStorage.getItem('ml_theme')||'light';document.documentElement.setAttribute('data-bs-theme',t)}catch(e){}})();</script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.2/font/bootstrap-icons.min.css">
<style>
body{padding-bottom:76px;background:var(--bs-body-bg)}
.view{display:none}.view.active{display:block}
.bottom{position:fixed;bottom:0;left:0;right:0;background:var(--bs-body-bg);border-top:1px solid var(--bs-border-color);display:flex;z-index:10}
.bottom button{flex:1;border:0;background:none;padding:.6rem .2rem;color:var(--bs-secondary-color);font-size:.75rem}
.bottom button.active{color:var(--bs-primary)}
.bottom i{display:block;font-size:1.3rem}
.card{border-color:var(--bs-border-color)}
.order-card{cursor:pointer;transition:all .2s}
.order-card:hover{box-shadow:0 2px 8px rgba(0,0,0,.1)}
</style>
</head>
<body>
<header class="sticky-top bg-primary text-white p-3 d-flex justify-content-between align-items-center">
<strong><i class="bi bi-water"></i> Maura Laundry</strong>
<button class="btn btn-sm btn-outline-light" id="theme"><i class="bi bi-moon-stars"></i></button>
</header>

<main class="container py-3">
<section class="view active" id="home">
<h5>Halo, <?=h($user['name'])?></h5>
<p class="text-muted small">Pelanggan Portal</p>
<div class="row g-2 my-3">
<div class="col-6">
<div class="card p-3 text-center">
<small class="text-muted">Total Order</small>
<b class="fs-3"><?=count($orders)?></b>
</div>
</div>
<div class="col-6">
<div class="card p-3 text-center">
<small class="text-muted">Sedang Proses</small>
<b class="fs-3"><?=count(array_filter($orders,fn($o)=>$o['status']!=='diambil'))?></b>
</div>
</div>
</div>

<h6>Order Terbaru</h6>
<?php if (empty($recent)): ?>
<div class="alert alert-info">Belum ada order. Hubungi kasir untuk membuat order baru.</div>
<?php else: foreach($recent as $o): ?>
<div class="card p-3 mb-2 order-card" data-order="<?=$o['id']?>">
<div class="d-flex justify-content-between align-items-start">
<div>
<b><?=h($o['order_number'])?></b>
<small class="d-block text-muted"><?=$o['item_count']?> item · <?=date('d/m/Y',strtotime($o['created_at']))?></small>
</div>
<span><?=status_badge($o['status'])?></span>
</div>
<div class="mt-2 d-flex justify-content-between">
<strong><?=idr($o['total_amount'])?></strong>
<?php if($o['paid']<$o['total_amount']):?>
<small class="text-danger">Belum lunas</small>
<?php else:?>
<small class="text-success">Lunas</small>
<?php endif;?>
</div>
</div>
<?php endforeach; endif; ?>
</section>

<section class="view" id="orders">
<div class="d-flex justify-content-between align-items-center mb-3">
<h5 class="mb-0">Semua Order</h5>
<select class="form-select form-select-sm w-auto" id="filter">
<option value="">Semua</option>
<option value="diterima">Diterima</option>
<option value="dicuci">Dicuci</option>
<option value="disetrika">Disetrika</option>
<option value="selesai">Selesai</option>
<option value="diambil">Diambil</option>
</select>
</div>
<div id="orderList">
<?php if (empty($orders)): ?>
<div class="alert alert-info">Belum ada order</div>
<?php else: foreach($orders as $o): ?>
<div class="card p-3 mb-2 order-card order-item" data-order="<?=$o['id']?>" data-status="<?=$o['status']?>">
<div class="d-flex justify-content-between align-items-start">
<div>
<b><?=h($o['order_number'])?></b>
<small class="d-block text-muted"><?=date('d/m/Y',strtotime($o['created_at']))?></small>
</div>
<span><?=status_badge($o['status'])?></span>
</div>
<div class="mt-2">
<strong><?=idr($o['total_amount'])?></strong>
<?php if($o['paid']<$o['total_amount']):?>
<small class="text-danger ms-2">Sisa: <?=idr($o['total_amount']-$o['paid'])?></small>
<?php endif;?>
</div>
</div>
<?php endforeach; endif; ?>
</div>
</section>

<section class="view" id="detail" style="display:none">
<button class="btn btn-sm btn-outline-secondary mb-3" id="back"><i class="bi bi-arrow-left"></i> Kembali</button>
<div id="detailContent"></div>
</section>

<section class="view" id="profile">
<div class="card p-4">
<div class="text-center mb-4">
<i class="bi bi-person-circle fs-1 text-primary"></i>
<h5 class="mt-2"><?=h($user['name'])?></h5>
<p class="text-muted mb-0"><?=h($user['email'])?></p>
<?php if($user['phone']):?>
<p class="text-muted small"><?=h($user['phone'])?></p>
<?php endif;?>
<span class="badge bg-primary"><?=h($user['role_name'])?></span>
</div>
<?php if($customer):?>
<hr>
<div>
<strong>Data Pelanggan</strong>
<p class="mb-1"><i class="bi bi-phone"></i> <?=h($customer['phone']??'-')?></p>
<p class="mb-0 small text-muted"><?=h($customer['address']??'-')?></p>
</div>
<?php endif;?>
<hr>
<a class="btn btn-danger w-100" href="<?=APP_URL?>/auth/logout.php"><i class="bi bi-box-arrow-right"></i> Keluar</a>
</div>
</section>
</main>

<nav class="bottom">
<button data-view="home" class="active"><i class="bi bi-house"></i>Beranda</button>
<button data-view="orders"><i class="bi bi-list-ul"></i>Order</button>
<button data-view="profile"><i class="bi bi-person"></i>Profil</button>
</nav>

<script>
const orders = <?=json_encode($orders,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;
document.querySelectorAll('[data-view]').forEach(b=>b.onclick=()=>{
  document.querySelectorAll('.view,.bottom button').forEach(x=>x.classList.remove('active'));
  document.getElementById(b.dataset.view).classList.add('active');
  b.classList.add('active');
});

document.querySelectorAll('.order-card').forEach(c=>c.onclick=()=>{
  const id = parseInt(c.dataset.order);
  const o = orders.find(x=>x.id===id);
  if(!o)return;
  const escHtml=s=>String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  document.getElementById('detailContent').innerHTML=`
<div class="card p-3 mb-3">
<h6>Order #${escHtml(o.order_number)}</h6>
<p class="mb-1"><strong>Status:</strong> ${escHtml(o.status)}</p>
<p class="mb-1"><strong>Total:</strong> ${new Intl.NumberFormat('id-ID',{style:'currency',currency:'IDR',minimumFractionDigits:0}).format(o.total_amount)}</p>
<p class="mb-1"><strong>Dibayar:</strong> ${new Intl.NumberFormat('id-ID',{style:'currency',currency:'IDR',minimumFractionDigits:0}).format(o.paid)}</p>
<p class="mb-1"><strong>Tanggal:</strong> ${new Date(o.created_at).toLocaleDateString('id-ID')}</p>
${o.estimated_done?`<p class="mb-0"><strong>Estimasi Selesai:</strong> ${new Date(o.estimated_done).toLocaleDateString('id-ID')}</p>`:''}
${o.notes?`<hr><small class="text-muted">${escHtml(o.notes)}</small>`:''}
</div>
<div class="alert alert-info">Detail item dan riwayat pembayaran dapat dilihat di kasir atau hubungi admin.</div>
  `;
  document.querySelectorAll('.view').forEach(v=>v.classList.remove('active'));
  document.getElementById('detail').style.display='block';
});

back.onclick=()=>{
  document.getElementById('detail').style.display='none';
  document.getElementById('orders').classList.add('active');
  document.querySelector('[data-view="orders"]').classList.add('active');
};

filter.onchange=()=>{
  const v=filter.value;
  document.querySelectorAll('.order-item').forEach(c=>{
    c.hidden=v&&c.dataset.status!==v;
  });
};

theme.onclick=()=>{
  let t=document.documentElement.getAttribute('data-bs-theme')==='dark'?'light':'dark';
  document.documentElement.setAttribute('data-bs-theme',t);
  localStorage.setItem('ml_theme',t);
  theme.querySelector('i').className=t==='dark'?'bi bi-sun':'bi bi-moon-stars';
};

if('serviceWorker'in navigator)navigator.serviceWorker.register('<?=APP_URL?>/sw.js');

let deferredPrompt;
window.addEventListener('beforeinstallprompt',e=>{
  e.preventDefault();
  deferredPrompt=e;
});
</script>
</body>
</html>
