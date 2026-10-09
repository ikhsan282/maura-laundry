    </div><!-- /p-4 -->
  </div><!-- /main-content -->
</div><!-- /wrapper -->
<script>const APP_URL = <?= json_encode(APP_URL) ?>;</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('.ts-select').forEach(el => {
    if (!el.tomselect) new TomSelect(el, {});
  });
});
</script>
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
</body>
</html>
