<section id="sync-page" data-base="<?= htmlspecialchars(burl,ENT_QUOTES,'UTF-8'); ?>" data-csrf="<?= htmlspecialchars(authCsrfToken(),ENT_QUOTES,'UTF-8'); ?>">
  <header class="sync-page-heading"><div><h1>Status sinkronisasi</h1><p>Data tersimpan tetap dapat dibuka saat pembaruan berjalan.</p></div><button type="button" class="btn" id="sync-reload"><span class="material-symbols-outlined" aria-hidden="true">refresh</span>Muat ulang</button></header>
  <div class="sync-page-filters"><label for="sync-shop">Toko<select id="sync-shop" class="select"><option value="0">Semua toko</option><?php foreach ($data['shops'] as $shop): ?><option value="<?= (int)$shop['id']; ?>" <?= (int)($_GET['shop_id'] ?? 0)===(int)$shop['id']?'selected':''; ?>><?= htmlspecialchars($shop['name'],ENT_QUOTES,'UTF-8'); ?></option><?php endforeach; ?></select></label><p id="sync-refreshed" role="status" aria-atomic="true">Memuat status…</p></div>
  <p id="sync-error" role="alert" hidden></p><div id="sync-dashboard" aria-busy="true"></div>
</section>
<?php require __DIR__.'/templates/shop-logos.php'; ?>
<script src="<?= assets; ?>/js/shop-select.js?v=<?= filemtime(__DIR__.'/../../../public/assets/js/shop-select.js'); ?>"></script>
<script src="<?= assets; ?>/js/sync-status.js?v=<?= filemtime(__DIR__.'/../../../public/assets/js/sync-status.js'); ?>" defer></script>
