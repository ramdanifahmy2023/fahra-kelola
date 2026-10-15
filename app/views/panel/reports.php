<?php $shops = $data['shops'] ?? []; ?>
<section id="performance-report" data-base="<?= htmlspecialchars(burl, ENT_QUOTES, 'UTF-8'); ?>">
  <header class="report-heading">
    <div><h1>Performa toko</h1><p>Penjualan dan kunjungan dari data Shopee yang tersimpan.</p></div>
    <p id="report-refreshed" role="status">Memuat data…</p>
  </header>
  <form id="report-filters" class="report-toolbar">
    <label for="report-end-date">Sampai tanggal<input id="report-end-date" type="date" class="input" required></label>
    <label for="report-sort">Urutkan menurut<select id="report-sort" class="select"><option value="confirmed_gmv">Penjualan (GMV)</option><option value="confirmed_orders">Pesanan terkonfirmasi</option><option value="shop_uv">Pengunjung unik</option><option value="product_clicks">Klik produk</option></select></label>
    <div><span class="report-field-label">Toko</span><?php require __DIR__ . '/templates/shop-picker.php'; ?></div>
    <button type="submit" id="report-refresh" class="btn btn-primary"><span class="material-symbols-outlined" aria-hidden="true">refresh</span>Muat ulang data</button>
  </form>
  <div class="report-ranges"><p>Berjalan <strong id="current-range">Belum dimuat</strong></p><p>Pembanding <strong id="previous-range">Belum dimuat</strong></p></div>
  <div id="report-alert" class="finance-notice" role="alert" hidden></div>
  <p id="report-loading" class="report-empty" role="status">Memuat performa toko…</p>
  <div id="report-content" hidden>
    <div class="report-totals">
      <?php foreach (['gmv'=>'Penjualan (GMV)', 'orders'=>'Pesanan terkonfirmasi', 'uv'=>'Pengunjung unik', 'clicks'=>'Klik produk', 'conversion'=>'Konversi pesanan / UV'] as $key=>$label): ?>
        <article><h2><?= $label; ?></h2><strong id="total-<?= $key; ?>">Belum tersedia</strong><p id="total-<?= $key; ?>-coverage"></p><?php if ($key === 'gmv'): ?><p id="total-gmv-growth"></p><?php endif; ?></article>
      <?php endforeach; ?>
    </div>
    <section class="report-section" aria-labelledby="report-ranking-title">
      <header><h2 id="report-ranking-title">Ranking toko</h2><p id="coverage-note"></p></header>
      <div id="report-mobile-rows" class="report-mobile-rows"></div>
      <div class="report-desktop-table"><table class="table"><caption class="sr-only">Perbandingan performa toko</caption><thead><tr><th scope="col">#</th><th scope="col">Toko</th><th scope="col">Penjualan</th><th scope="col">Perubahan</th><th scope="col">Pesanan</th><th scope="col">UV</th><th scope="col">Klik produk</th><th scope="col">Konversi</th></tr></thead><tbody id="report-rows"></tbody></table></div>
    </section>
    <details class="report-section report-chart-section">
      <summary>Grafik &amp; angka harian semua toko</summary>
      <div class="report-chart-tools"><label for="compare-metric">Metrik grafik<select id="compare-metric" class="select"><option value="confirmed_gmv">Penjualan</option><option value="confirmed_orders">Pesanan terkonfirmasi</option><option value="shop_uv">Pengunjung unik</option><option value="product_clicks">Klik produk</option></select></label><p id="compare-status" role="status"></p><button class="btn" id="compare-retry" type="button" hidden>Coba lagi</button></div>
      <p class="report-chart-help">Jeda garis berarti data belum tersedia. Angka per tanggal dapat dibuka di bawah grafik.</p>
      <div id="compare-legend" class="report-legend"></div>
      <svg id="compare-chart" viewBox="0 0 800 280" role="img" aria-label="Grafik harian toko; angka tersedia pada rincian di bawah"></svg>
      <div id="compare-empty" class="report-empty" hidden>Belum ada data untuk metrik ini.</div>
      <details class="report-numeric"><summary>Lihat angka per tanggal</summary><div id="compare-values"></div></details>
    </details>
    <section id="report-daily" class="report-section" aria-labelledby="report-daily-title" hidden>
      <header><h2 id="report-daily-title" tabindex="-1">Rincian harian</h2><button id="detail-retry" class="btn" type="button" hidden>Coba lagi</button></header>
      <div class="report-daily-scroll"><table class="table report-daily-table"><caption id="detail-caption">Periode berjalan dan pembanding</caption><thead><tr><th scope="col">Tanggal</th><th scope="col">Penjualan</th><th scope="col">Pesanan</th><th scope="col">UV</th><th scope="col">Klik produk</th></tr></thead><tbody id="detail-rows"></tbody></table></div>
    </section>
  </div>
  <?php require __DIR__ . '/templates/shop-logos.php'; ?>
  <script src="<?= assets; ?>/js/reports.js?v=<?= filemtime(__DIR__.'/../../../public/assets/js/reports.js'); ?>" defer></script>
</section>
