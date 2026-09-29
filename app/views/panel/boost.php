<section id="boost-monitor" data-endpoint="<?= burl; ?>/procproducts" data-shops-url="<?= burl; ?>/panel/shops">
  <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <h2 class="text-2xl font-black tracking-tight">Naikkan produk</h2>
    <a href="<?= burl; ?>/panel/products" class="btn btn-sm min-h-11 rounded-lg">Kelola produk</a>
  </div>
  <div id="boost-state" class="mb-5 text-sm" role="status">Memuat status toko…</div>
  <div id="boost-grid" class="grid grid-cols-1 items-start gap-4 2xl:grid-cols-2"></div>
</section>

<template id="boost-card-template">
  <article class="min-w-0 rounded-xl border border-base-content/20 bg-base-100" data-card>
    <div class="flex flex-wrap items-start justify-between gap-3 p-5">
      <div class="min-w-0"><h3 class="break-words text-base font-bold" data-shop-name></h3><p class="mt-1 text-sm" data-shop-status>Memuat produk…</p></div>
      <span class="text-sm" data-session-status></span>
    </div>
    <div class="flex flex-wrap items-center justify-between gap-3 px-5 pb-4 text-sm">
      <p>Sisa kuota <strong data-remaining>-</strong> / 5</p>
      <p data-cooldown></p>
    </div>
    <div class="px-5 pb-4 text-sm" data-load-state role="status" hidden></div>
    <div class="px-5 pb-4 text-sm" data-selection-help hidden></div>
    <a class="mx-5 mb-4 inline-flex min-h-11 items-center underline" data-reconnect hidden>Perbarui koneksi toko</a>
    <details class="border-t border-base-content/20" data-product-detail>
      <summary class="min-h-11 cursor-pointer px-5 py-3 text-sm font-bold">Lihat 10 produk terlaris <span class="font-normal" data-collapsed-count></span></summary>
      <div class="px-5 pb-5">
        <p class="mb-3 text-sm">Rekomendasi berdasarkan jumlah terjual, stok, dan status naikkan produk.</p>
        <div class="mb-4 flex flex-wrap items-center gap-2">
          <button type="button" class="btn btn-sm min-h-11 rounded-lg" data-recommend disabled>Pilih rekomendasi</button>
          <button type="button" class="btn btn-ghost btn-sm min-h-11 rounded-lg" data-clear disabled>Hapus pilihan</button>
          <span class="text-sm" data-selection-count role="status">0 dipilih</span>
        </div>
        <p class="mb-3 text-sm" data-recommendation-note role="status" hidden></p>
        <div data-products class="space-y-2"></div>
        <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
          <button type="button" class="btn btn-ghost btn-sm min-h-11 rounded-lg" data-refresh>Muat ulang status</button>
          <button type="button" class="btn btn-primary btn-sm min-h-11 rounded-lg" data-boost disabled>Naikkan produk</button>
        </div>
        <div class="mt-3 rounded-lg border border-base-content/20 p-3 text-sm" data-action-result role="status" hidden></div>
        <details class="mt-4 border-t border-base-content/20 pt-2"><summary class="min-h-11 cursor-pointer py-3 text-sm font-bold">Riwayat toko</summary><div class="space-y-2 text-sm" data-history></div></details>
      </div>
    </details>
  </article>
</template>
<script id="boost-shops" type="application/json"><?= json_encode(array_map(static function ($shop) { return ['id' => (int)$shop['id'], 'name' => $shop['name'] ?? 'Toko tanpa nama']; }, $data['shops'] ?? []), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?></script>
<script src="<?= assets; ?>/js/boost.js?v=<?= filemtime(__DIR__ . '/../../../public/assets/js/boost.js'); ?>" defer></script>
