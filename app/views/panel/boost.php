<section id="boost-monitor" data-endpoint="<?= burl; ?>/procBoost" data-csrf="<?= htmlspecialchars(authCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>" data-shops-url="<?= burl; ?>/panel/shops">
  <header class="boost-heading">
    <?php require __DIR__.'/templates/boost-icons.php'; ?>
    <div class="boost-title-group"><span class="boost-title-icon"><?= $boostIcon('repeat'); ?></span><div><h1>Naikkan produk</h1><p>Produk pilihan, terulang saat slot tersedia.</p></div></div>
    <a href="<?= burl; ?>/panel/products" class="btn boost-secondary"><?= $boostIcon('box'); ?>Kelola produk</a>
  </header>
  <div class="boost-dashboard-summary" aria-label="Ringkasan pengulangan">
    <div><?= $boostIcon('repeat'); ?><span>Toko aktif<strong data-total-active>Belum dimuat</strong></span></div>
    <div><?= $boostIcon('box'); ?><span>Produk pilihan<strong data-total-products>Belum dimuat</strong></span></div>
    <div><?= $boostIcon('alert'); ?><span>Perlu diperiksa<strong data-total-attention>Belum dimuat</strong></span></div>
  </div>
  <div class="boost-intro"><details class="boost-guide"><summary><?= $boostIcon('info'); ?>Cara kerja pengulangan</summary><p>Simpan pilihan, lalu aktifkan pengulangan. Produk yang belum tersedia akan menunggu tanpa diganti produk lain. Slot lokal berupa perkiraan; status Shopee diperiksa sebelum pengiriman.</p></details><span class="boost-worker-state" data-worker-state><?= $boostIcon('worker'); ?>Memuat worker…</span></div>
  <p id="boost-state" role="status">Memuat status toko…</p>
  <div id="boost-grid"></div>

  <dialog id="boost-editor" aria-labelledby="boost-editor-title">
    <header class="boost-dialog-heading"><div><div class="boost-editor-identity"><span class="shop-logo" data-editor-logo></span><p data-editor-shop></p></div><h2 id="boost-editor-title">Pilih produk untuk diulang</h2></div><button type="button" class="btn boost-secondary" data-editor-close>Tutup</button></header>
    <div class="boost-editor-content">
    <p data-editor-intro>Pilih hingga 5 produk. Produk yang belum tersedia akan menunggu tanpa diganti produk lain.</p>
    <div class="boost-recommendation" data-recommendation>
      <strong class="boost-section-label"><?= $boostIcon('trend'); ?>Rekomendasi terlaris</strong>
      <p id="boost-recommendation-help">Pilih hingga 5 produk terlaris yang aktif dan berstok. Berdasarkan penjualan tersinkron di toko ini; mengganti pilihan sementara.</p>
      <div class="boost-recommendation-actions"><button type="button" class="btn boost-secondary" data-recommend aria-describedby="boost-recommendation-help"><?= $boostIcon('trend'); ?>Pilih rekomendasi</button><button type="button" class="boost-text-button" data-undo-recommendation hidden>Kembalikan pilihan</button></div>
      <p data-recommendation-status role="status" hidden></p>
    </div>
    <div class="boost-draft-heading"><strong data-draft-count role="status">0 / 5 dipilih</strong><span data-dirty></span></div>
    <ul class="boost-chosen" data-chosen aria-label="Pilihan pengulangan"></ul>
    <form data-search-form class="boost-search"><label for="boost-search">Cari produk di toko ini</label><div><input id="boost-search" type="search" class="input" autocomplete="off" maxlength="100" placeholder="Nama produk"><button type="submit" class="btn boost-secondary"><?= $boostIcon('search'); ?>Cari</button></div></form>
    <p data-catalog-status role="status"></p><div data-catalog></div>
    <nav class="boost-pagination" aria-label="Halaman katalog"><button type="button" class="btn boost-secondary" data-prev>Sebelumnya</button><span data-page></span><button type="button" class="btn boost-secondary" data-next>Berikutnya</button></nav>
    <p data-editor-error role="alert" hidden></p><button type="button" class="btn boost-secondary" data-reload-version hidden>Muat versi terbaru</button>
    </div>
    <footer class="boost-dialog-footer"><p data-editor-note>Menyimpan pilihan tidak mengaktifkan pengulangan baru.</p><div><button type="button" class="btn boost-secondary" data-editor-cancel>Batalkan</button><button type="button" class="btn btn-primary" data-save>Simpan pilihan</button></div></footer>
  </dialog>

  <dialog id="boost-confirm" aria-labelledby="boost-confirm-title">
    <h2 id="boost-confirm-title"></h2><p data-confirm-copy></p><div data-confirm-products></div>
    <p data-confirm-error role="alert" hidden></p>
    <footer class="boost-dialog-footer"><button type="button" class="btn boost-secondary" data-confirm-cancel>Batal</button><button type="button" class="btn btn-primary" data-confirm-action></button></footer>
  </dialog>

  <dialog id="boost-resolution" aria-labelledby="boost-resolution-title">
    <h2 id="boost-resolution-title">Pastikan hasil pengiriman</h2><p data-resolution-name></p>
    <p>Periksa produk ini di Seller Centre. Status tersedia saat ini saja tidak membuktikan apakah pengiriman sebelumnya berhasil.</p>
    <label class="boost-check"><input type="checkbox" data-resolution-confirm> Saya sudah memeriksa hasil di Seller Centre.</label>
    <label for="boost-resolution-outcome">Hasil yang sudah dipastikan</label><select id="boost-resolution-outcome" class="select"><option value="">Pilih hasil</option><option value="confirmed_sent">Produk berhasil dinaikkan</option><option value="confirmed_not_sent">Produk dipastikan tidak dinaikkan</option></select>
    <p>Konfirmasi disimpan dalam riwayat. Jika belum yakin, batalkan dan biarkan status belum pasti.</p><p data-resolution-error role="alert" hidden></p>
    <footer class="boost-dialog-footer"><button type="button" class="btn boost-secondary" data-resolution-cancel>Batal</button><button type="button" class="btn btn-primary" data-resolution-save disabled>Simpan hasil pemeriksaan</button></footer>
  </dialog>
</section>

<template id="boost-card-template">
  <article class="boost-store" data-card>
    <header class="boost-store-heading"><div class="shop-identity"><span class="shop-logo" data-shop-logo></span><div><h2 data-shop-name></h2><p data-session-status></p></div></div><span class="boost-mode" data-mode>Memuat…</span></header>
    <div class="boost-store-body">
      <div class="boost-overview"><div><?= $boostIcon('box'); ?><span>Produk tersimpan</span><strong data-saved-count>Belum dimuat</strong><span class="boost-slot-meter" data-saved-slots aria-hidden="true"></span></div><div><?= $boostIcon('clock'); ?><span>Pemeriksaan berikutnya</span><strong data-next-check>Belum tersedia</strong></div><div><?= $boostIcon('slots'); ?><span>Perkiraan slot lokal</span><strong data-remaining>Belum tersedia</strong><span class="boost-slot-meter" data-capacity-slots aria-hidden="true"></span></div></div>
      <p class="boost-notice" data-operation-note></p><p data-load-state role="status"></p><p data-action-result role="status" hidden></p>
      <ul class="boost-saved-products" data-saved-products></ul>
      <div class="boost-store-actions"><button type="button" class="btn boost-secondary" data-edit disabled><?= $boostIcon('edit'); ?>Pilih produk</button><button type="button" class="btn btn-primary" data-toggle disabled><?= $boostIcon('play'); ?>Aktifkan pengulangan</button><button type="button" class="btn boost-secondary" data-manual disabled><?= $boostIcon('up'); ?>Naikkan sekali</button><button type="button" class="boost-text-button" data-refresh><?= $boostIcon('refresh'); ?>Muat ulang status</button></div>
      <p class="boost-caption" data-freshness></p><a class="boost-text-button" data-reconnect hidden>Perbarui koneksi toko</a>
      <details class="boost-history"><summary><?= $boostIcon('history'); ?>Riwayat &amp; pemeriksaan hasil</summary><p>Jeda aman lokal: 4 jam 15 menit per produk. Status Shopee tetap diperiksa sebelum pengiriman.</p><button type="button" class="btn boost-secondary" data-inspect><?= $boostIcon('search'); ?>Periksa status di Shopee</button><p data-inspection role="status" hidden></p><div data-unresolved></div><div data-history></div></details>
    </div>
  </article>
</template>
<?php require __DIR__ . '/templates/shop-logos.php'; ?>
<script id="boost-shops" type="application/json"><?= json_encode(array_map(static function ($shop) { return ['id' => (int)$shop['id'], 'name' => $shop['name'] ?? 'Toko tanpa nama']; }, $data['shops'] ?? []), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?></script>
<script src="<?= assets; ?>/js/boost.js?v=<?= filemtime(__DIR__ . '/../../../public/assets/js/boost.js'); ?>" defer></script>
