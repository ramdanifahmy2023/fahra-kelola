<section id="sync-page" data-base="<?= htmlspecialchars(burl,ENT_QUOTES,'UTF-8'); ?>" data-csrf="<?= htmlspecialchars(authCsrfToken(),ENT_QUOTES,'UTF-8'); ?>">
  <header class="sync-page-heading"><div><h1>Status sinkronisasi</h1><p>Data tersimpan tetap dapat dibuka saat pembaruan berjalan.</p></div><button type="button" class="btn" id="sync-reload"><span class="material-symbols-outlined" aria-hidden="true">refresh</span>Muat ulang</button></header>
  <div class="sync-page-filters"><label for="sync-shop">Toko<select id="sync-shop" class="select"><option value="0">Semua toko</option><?php foreach ($data['shops'] as $shop): ?><option value="<?= (int)$shop['id']; ?>" <?= (int)($_GET['shop_id'] ?? 0)===(int)$shop['id']?'selected':''; ?>><?= htmlspecialchars($shop['name'],ENT_QUOTES,'UTF-8'); ?></option><?php endforeach; ?></select></label><p id="sync-refreshed" role="status" aria-atomic="true">Memuat status…</p></div>
  <section class="page-recap sync-recap" id="sync-recap" aria-labelledby="sync-recap-title" hidden>
    <header class="page-recap-heading"><div><h2 id="sync-recap-title">Ringkasan sinkronisasi</h2><p id="sync-recap-scope">Status percobaan terbaru per jenis data.</p></div><p class="page-recap-scope" id="sync-recap-coverage"></p></header>
    <div class="page-recap-summary">
      <div class="page-recap-primary"><span>Perlu diperiksa</span><strong id="sync-recap-failed">–</strong><p>Jadwal dengan percobaan terakhir yang gagal atau butuh perhatian.</p></div>
      <div class="page-recap-metrics">
        <div class="page-recap-metric"><span>Sedang berjalan</span><strong id="sync-recap-running">–</strong><small>Pekerjaan yang sedang diproses</small></div>
        <div class="page-recap-metric"><span>Menunggu giliran</span><strong id="sync-recap-queued">–</strong><small>Pekerjaan di antrean</small></div>
        <div class="page-recap-metric"><span>Selesai / terjadwal</span><strong id="sync-recap-other">–</strong><small>Status lainnya, termasuk jadwal tanpa pekerjaan</small></div>
        <div class="page-recap-metric"><span>Jenis sinkronisasi</span><strong id="sync-recap-total">–</strong><small>Pada cakupan toko yang dipilih</small></div>
      </div>
    </div>
    <div class="page-recap-visual-row">
      <div class="page-recap-breakdown"><h3>Status percobaan terbaru</h3><div id="sync-recap-bar" class="page-recap-bar"></div><ul id="sync-recap-legend" class="page-recap-legend"></ul></div>
      <div class="page-recap-shop-rank"><h3>Toko yang perlu diperiksa</h3><ol id="sync-recap-shops"></ol></div>
    </div>
    <div id="sync-recap-filters" class="page-recap-filters" role="group" aria-label="Filter status sinkronisasi"></div>
  </section>
  <p id="sync-error" role="alert" hidden></p><div id="sync-dashboard" aria-busy="true"></div>
</section>
<?php require __DIR__.'/templates/shop-logos.php'; ?>
<script src="<?= assets; ?>/js/shop-select.js?v=<?= filemtime(__DIR__.'/../../../public/assets/js/shop-select.js'); ?>"></script>
<script src="<?= assets; ?>/js/sync-status.js?v=<?= filemtime(__DIR__.'/../../../public/assets/js/sync-status.js'); ?>" defer></script>
