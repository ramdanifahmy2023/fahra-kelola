<section id="boost-monitor" data-endpoint="<?= burl; ?>/procBoost" data-csrf="<?= htmlspecialchars(authCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>" data-shops-url="<?= burl; ?>/panel/shops">
  <header class="boost-heading">
    <div><h1>Naikkan produk</h1><p>Ulangi produk pilihan setiap toko saat produk dan slot tersedia.</p></div>
    <a href="<?= burl; ?>/panel/products" class="btn boost-secondary">Kelola produk</a>
  </header>
  <div class="boost-intro"><p>Simpan pilihan, lalu aktifkan pengulangan. Produk yang belum tersedia akan menunggu tanpa diganti produk lain.</p></div>
  <p id="boost-state" role="status">Memuat status toko…</p>
  <div id="boost-grid"></div>

  <dialog id="boost-editor" aria-labelledby="boost-editor-title">
    <header class="boost-dialog-heading"><div><p data-editor-shop></p><h2 id="boost-editor-title">Pilih produk untuk diulang</h2></div><button type="button" class="btn boost-secondary" data-editor-close>Tutup</button></header>
    <div class="boost-editor-content">
    <p data-editor-intro>Pilih hingga 5 produk. Produk yang belum tersedia akan menunggu tanpa diganti produk lain.</p>
    <div class="boost-draft-heading"><strong data-draft-count role="status">0 / 5 dipilih</strong><span data-dirty></span></div>
    <ul class="boost-chosen" data-chosen aria-label="Pilihan pengulangan"></ul>
    <form data-search-form class="boost-search"><label for="boost-search">Cari produk di toko ini</label><div><input id="boost-search" type="search" class="input" autocomplete="off" maxlength="100" placeholder="Nama produk"><button type="submit" class="btn boost-secondary">Cari</button></div></form>
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
      <div class="boost-overview"><div><span>Produk tersimpan</span><strong data-saved-count>Belum dimuat</strong></div><div><span>Pemeriksaan berikutnya</span><strong data-next-check>Belum tersedia</strong></div><div><span>Perkiraan slot lokal</span><strong data-remaining>Belum tersedia</strong></div></div>
      <p class="boost-notice" data-operation-note></p><p data-load-state role="status"></p><p data-action-result role="status" hidden></p>
      <ul class="boost-saved-products" data-saved-products></ul>
      <div class="boost-store-actions"><button type="button" class="btn boost-secondary" data-edit disabled>Pilih produk</button><button type="button" class="btn btn-primary" data-toggle disabled>Aktifkan pengulangan</button><button type="button" class="btn boost-secondary" data-manual disabled>Naikkan sekali</button><button type="button" class="boost-text-button" data-refresh>Muat ulang status</button></div>
      <p class="boost-caption" data-freshness></p><a class="boost-text-button" data-reconnect hidden>Perbarui koneksi toko</a>
      <details class="boost-history"><summary>Riwayat &amp; pemeriksaan hasil</summary><p>Jeda aman lokal: 4 jam 15 menit per produk. Status Shopee tetap diperiksa sebelum pengiriman.</p><button type="button" class="btn boost-secondary" data-inspect>Periksa status di Shopee</button><p data-inspection role="status" hidden></p><div data-unresolved></div><div data-history></div></details>
    </div>
  </article>
</template>
<?php require __DIR__ . '/templates/shop-logos.php'; ?>
<script id="boost-shops" type="application/json"><?= json_encode(array_map(static function ($shop) { return ['id' => (int)$shop['id'], 'name' => $shop['name'] ?? 'Toko tanpa nama']; }, $data['shops'] ?? []), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?></script>
<script src="<?= assets; ?>/js/boost.js?v=<?= filemtime(__DIR__ . '/../../../public/assets/js/boost.js'); ?>" defer></script>
