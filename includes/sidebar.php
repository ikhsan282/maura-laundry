<?php
$current = $_SERVER['PHP_SELF'];
function nav_link(string $href, string $icon, string $label, string $current): string {
    $active = (strpos($current, $href) !== false) ? ' active' : '';
    return '<li class="nav-item">
      <a class="nav-link' . $active . '" href="' . APP_URL . $href . '">
        <i class="bi bi-' . $icon . ' me-2"></i>' . $label . '
      </a>
    </li>';
}
?>
<nav id="sidebar" class="sidebar bg-dark text-white d-flex flex-column">
  <div class="sidebar-header px-3 py-3 border-bottom border-secondary">
    <div class="d-flex align-items-center gap-2">
      <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center" style="width:36px;height:36px">
        <i class="bi bi-water text-white fs-5"></i>
      </div>
      <div>
        <div class="fw-bold">Maura Laundry</div>
        <div class="small text-secondary">Management System</div>
      </div>
    </div>
  </div>
  <ul class="nav flex-column px-2 py-3 flex-grow-1 overflow-auto">
    <li class="nav-item-label px-2 mb-1 small text-uppercase text-secondary fw-semibold" style="font-size:.65rem;letter-spacing:.08em">Utama</li>
    <?= nav_link('/pages/dashboard.php', 'speedometer2', 'Dashboard', $current) ?>

    <?php if (can('orders.view')): ?>
    <li class="nav-item-label px-2 mt-3 mb-1 small text-uppercase text-secondary fw-semibold" style="font-size:.65rem;letter-spacing:.08em">Order</li>
    <?= nav_link('/pages/orders/index.php',  'clipboard-check', 'Daftar Order', $current) ?>
    <?php if (can('orders.create')): ?>
    <?= nav_link('/pages/orders/create.php', 'plus-circle',     'Buat Order', $current) ?>
    <?php endif; ?>
    <?php endif; ?>

    <?php if (can('customers.view')): ?>
    <li class="nav-item-label px-2 mt-3 mb-1 small text-uppercase text-secondary fw-semibold" style="font-size:.65rem;letter-spacing:.08em">Master Data</li>
    <?= nav_link('/pages/customers/index.php', 'people',       'Pelanggan', $current) ?>
    <?php endif; ?>
    <?php if (can('services.view')): ?>
    <?= nav_link('/pages/services/index.php',  'tags',         'Layanan', $current) ?>
    <?php endif; ?>

    <?php if (can('payments.view')): ?>
    <li class="nav-item-label px-2 mt-3 mb-1 small text-uppercase text-secondary fw-semibold" style="font-size:.65rem;letter-spacing:.08em">Keuangan</li>
    <?= nav_link('/pages/payments/index.php', 'cash-coin', 'Pembayaran', $current) ?>
    <?php endif; ?>
    <?php if (can('deposits.view')): ?>
    <?= nav_link('/pages/deposits/index.php', 'wallet2', 'Deposit Pelanggan', $current) ?>
    <?php endif; ?>
    <?php if (can('subscriptions.view')): ?>
    <?= nav_link('/pages/subscriptions/index.php', 'arrow-repeat', 'Langganan', $current) ?>
    <?php endif; ?>

    <?php if (can('reports.view')): ?>
    <li class="nav-item-label px-2 mt-3 mb-1 small text-uppercase text-secondary fw-semibold" style="font-size:.65rem;letter-spacing:.08em">Laporan</li>
    <?= nav_link('/pages/reports/index.php', 'bar-chart-line', 'Laporan', $current) ?>
    <?php endif; ?>

    <?php if (can('users.manage')): ?>
    <li class="nav-item-label px-2 mt-3 mb-1 small text-uppercase text-secondary fw-semibold" style="font-size:.65rem;letter-spacing:.08em">Pengaturan</li>
    <?= nav_link('/pages/users/index.php', 'person-gear', 'Pengguna', $current) ?>
    <?php endif; ?>
  </ul>
  <div class="px-3 py-2 border-top border-secondary small text-secondary">
    v<?= APP_VERSION ?>
  </div>
</nav>
