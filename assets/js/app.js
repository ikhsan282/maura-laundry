// Maura Laundry — App JS

// Sidebar toggle
document.addEventListener('DOMContentLoaded', () => {
  const toggle  = document.getElementById('sidebarToggle');
  const sidebar = document.getElementById('sidebar');
  if (toggle && sidebar) {
    toggle.addEventListener('click', () => sidebar.classList.toggle('collapsed'));
  }

  // ── Dark mode ─────────────────────────────────────────────
  const darkToggle = document.getElementById('darkToggle');
  function syncThemeIcon() {
    const icon = darkToggle?.querySelector('i');
    if (icon) icon.className = document.documentElement.getAttribute('data-theme') === 'dark' ? 'bi bi-sun' : 'bi bi-moon-stars';
  }
  darkToggle?.addEventListener('click', () => {
    const next = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', next);
    localStorage.setItem('ml_theme', next);
    syncThemeIcon();
  });
  syncThemeIcon();

  // Service worker
  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register(APP_URL + '/sw.js').catch(() => {});
  }

  // Auto-dismiss alerts after 4 s
  document.querySelectorAll('.alert.alert-dismissible').forEach(el => {
    setTimeout(() => {
      const bsAlert = bootstrap.Alert.getOrCreateInstance(el);
      bsAlert.close();
    }, 4000);
  });

  // Confirm dialogs on delete links/buttons
  document.querySelectorAll('[data-confirm]').forEach(el => {
    el.addEventListener('click', e => {
      if (!confirm(el.dataset.confirm || 'Yakin ingin menghapus data ini?')) e.preventDefault();
    });
  });
});

// Print receipt
function printReceipt() {
  const area = document.getElementById('receiptArea');
  if (!area) return;
  const w = window.open('', '_blank', 'width=400,height=600');
  w.document.write('<html><head><title>Nota</title><style>body{font-family:monospace;font-size:12px;width:80mm;margin:0 auto}hr{border-top:1px dashed #000}table{width:100%}td{vertical-align:top}</style></head><body>');
  w.document.write(area.innerHTML);
  w.document.write('</body></html>');
  w.document.close();
  w.focus();
  setTimeout(() => { w.print(); w.close(); }, 300);
}
