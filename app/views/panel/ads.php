<section id="ads-monitor" data-endpoint="<?= htmlspecialchars(burl . '/procads/summary', ENT_QUOTES, 'UTF-8'); ?>" aria-labelledby="ads-title">
  <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div>
      <h2 id="ads-title" class="text-2xl font-black tracking-tight text-base-content">Monitoring iklan</h2>
      <p class="mt-1 max-w-2xl text-sm text-base-content">Performa iklan per toko, dari ringkasan periode hingga rincian setiap tanggal.</p>
    </div>
    <button id="ads-refresh" type="button" class="btn btn-sm min-h-11 rounded-lg">Muat ulang data</button>
  </div>
  <div class="mb-5 rounded-xl border border-base-content/20 bg-base-100 p-4">
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
      <div>
        <label for="ads-period" class="mb-2 block text-sm font-bold">Periode laporan</label>
        <select id="ads-period" class="select min-h-11 w-full">
          <option value="daily">Harian · hari ini</option>
          <option value="weekly">Mingguan · minggu berjalan</option>
          <option value="monthly">Bulanan · bulan berjalan</option>
        </select>
      </div>
      <div>
        <label for="ads-channel" class="mb-2 block text-sm font-bold">Jenis iklan</label>
        <select id="ads-channel" class="select min-h-11 w-full">
          <option value="product">Iklan produk</option>
          <option value="shop">Iklan toko</option>
          <option value="live">Iklan live</option>
        </select>
      </div>
      <div>
        <label for="ads-shop" class="mb-2 block text-sm font-bold">Toko</label>
        <select id="ads-shop" class="select min-h-11 w-full"><option value="">Semua toko</option></select>
      </div>
    </div>
    <p id="ads-period-help" class="mt-3 text-sm text-base-content"></p>
    <p class="mt-2 text-xs text-base-content">Sinkronisasi berjalan di latar belakang. Muat ulang menampilkan hasil tersimpan terbaru. <a href="<?= burl; ?>/panel/sync" class="ads-inline-link">Lihat sinkronisasi</a></p>
  </div>
  <div id="ads-state" class="mb-5 rounded-xl border border-base-content/20 bg-base-100 p-4 text-sm text-base-content" role="status" aria-live="polite">Memuat laporan iklan…</div>
  <div id="ads-session-warning" class="mb-5 rounded-xl border border-base-content/20 bg-base-100 p-4 text-sm text-base-content" hidden>
    <p data-session-message></p>
    <a class="ads-inline-link" href="<?= burl; ?>/panel/shops">Perbarui cookie toko</a>
  </div>
  <div id="ads-grid" class="grid min-w-0 grid-cols-1 gap-5" aria-busy="true"></div>
  <p class="mt-4 text-xs text-base-content">Jumlah pesanan mengikuti kartu Pesanan Seller Centre. Penjualan memakai atribusi total Shopee. Nilai antarjenis iklan tidak dijumlahkan. Tanda - berarti metrik belum tersedia, belum terverifikasi untuk jenis iklan ini, atau rasio tidak dapat dihitung.</p>
</section>
<template id="ads-card-template">
  <article class="min-w-0 overflow-hidden rounded-xl border border-base-content/20 bg-base-100 text-base-content">
    <div class="flex flex-wrap items-start justify-between gap-3 border-b border-base-content/20 p-5">
      <div class="min-w-0">
        <h3 class="break-words text-lg font-black" data-shop-name></h3>
        <p class="mt-1 text-sm" data-performance-period></p>
        <p class="mt-2 text-xs" data-shop-sync></p>
      </div>
      <span class="ads-report-status" data-status></span>
    </div>
    <dl class="ads-metrics p-5">
      <div><dt>Iklan dilihat</dt><dd data-impressions>-</dd></div>
      <div><dt>Jumlah klik</dt><dd data-clicks>-</dd></div>
      <div><dt>Persentase klik</dt><dd data-ctr>-</dd></div>
      <div><dt>Pesanan</dt><dd data-orders>-</dd></div>
      <div><dt>Produk terjual</dt><dd data-items-sold>-</dd></div>
      <div><dt>Penjualan</dt><dd data-sales>-</dd></div>
      <div><dt>Biaya iklan</dt><dd data-ad-cost>-</dd></div>
      <div><dt>ROAS</dt><dd data-roas>-</dd></div>
    </dl>
    <div class="mx-5 mb-5 rounded-lg bg-base-200 p-3 text-sm" data-note></div>
    <details class="ads-detail border-t border-base-content/20" data-detail>
      <summary class="min-h-11 cursor-pointer px-5 py-3 text-sm font-bold">Rincian per tanggal <span class="font-normal" data-detail-count></span></summary>
      <p class="px-5 pb-4 text-sm" data-detail-empty></p>
      <div class="ads-table-scroll" data-table-region role="region" tabindex="0" hidden>
        <table class="ads-table">
          <caption class="sr-only" data-table-caption></caption>
          <thead><tr><th scope="col">Tanggal (WIB)</th><th scope="col">Iklan dilihat</th><th scope="col">Jumlah klik</th><th scope="col">Persentase klik</th><th scope="col">Pesanan</th><th scope="col">Produk terjual</th><th scope="col">Penjualan</th><th scope="col">Biaya iklan</th><th scope="col">ROAS</th></tr></thead>
          <tbody data-daily-rows></tbody>
        </table>
      </div>
    </details>
    <div class="border-t border-base-content/20 px-5 py-4 text-xs">
      <p data-session-status></p>
      <p class="mt-2" data-meta></p>
      <p class="mt-2" data-channels></p>
    </div>
  </article>
</template>
<script src="<?= assets; ?>/js/ads.js" defer></script>
