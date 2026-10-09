<?php
// header.php — requires config/config.php already loaded by calling page
$user  = current_user();
$flash = get_flash();
$title = $title ?? APP_NAME;
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h($title) ?> — <?= APP_NAME ?></title>
  <meta name="theme-color" content="#0d6efd">
  <link rel="manifest" href="<?= APP_URL ?>/manifest.json">
  <script>
  (function(){try{var t=localStorage.getItem('ml_theme');if(!t)t=matchMedia('(prefers-color-scheme:dark)').matches?'dark':'light';document.documentElement.setAttribute('data-theme',t);}catch(e){}})();
  </script>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.2/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.css">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
</head>
<body>
<div class="wrapper d-flex">
  <?php require_once __DIR__ . '/sidebar.php'; ?>
  <div class="main-content flex-grow-1">
    <!-- Topbar -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom px-3 py-2">
      <button class="btn btn-sm btn-outline-secondary me-3" id="sidebarToggle">
        <i class="bi bi-list fs-5"></i>
      </button>
      <span class="navbar-brand mb-0 fw-semibold text-primary">
        <i class="bi bi-water me-1"></i><?= APP_NAME ?>
      </span>
      <div class="ms-auto d-flex align-items-center gap-2">
        <button class="btn btn-sm btn-outline-secondary" id="darkToggle" title="Toggle tema">
          <i class="bi bi-moon-stars"></i>
        </button>
        <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
          <?= h($user['role_name'] ?? '') ?>
        </span>
        <div class="dropdown">
          <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
            <i class="bi bi-person-circle me-1"></i><?= h($user['name'] ?? '') ?>
          </button>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><span class="dropdown-item-text small text-muted"><?= h($user['email'] ?? '') ?></span></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-danger" href="<?= APP_URL ?>/auth/logout.php">
              <i class="bi bi-box-arrow-right me-1"></i>Keluar
            </a></li>
          </ul>
        </div>
      </div>
    </nav>
    <!-- Page content -->
    <div class="p-4">
      <?php if ($flash): ?>
        <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : h($flash['type']) ?> alert-dismissible fade show" role="alert">
          <?= h($flash['message']) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>
