<section id="ads-monitor" data-endpoint="<?= htmlspecialchars(burl . '/procads/summary', ENT_QUOTES, 'UTF-8'); ?>" data-topups-endpoint="<?= htmlspecialchars(burl . '/procads/topups', ENT_QUOTES, 'UTF-8'); ?>" aria-labelledby="ads-title">
  <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div>
      <h2 id="ads-title" class="text-2xl font-black tracking-tight text-base-content">Monitoring iklan</h2>
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
    <details class="mt-2 text-sm">
      <summary class="min-h-11 cursor-pointer py-3 font-semibold">Tentang data iklan</summary>
      <p>Muat ulang menampilkan data tersimpan. <a href="<?= burl; ?>/panel/sync" class="ads-inline-link">Lihat jadwal sinkronisasi</a></p>
      <p>Pesanan mengikuti kartu Pesanan Seller Centre. Penjualan memakai atribusi total Shopee. Nilai antarjenis iklan tidak dijumlahkan. Data periode berjalan masih dapat berubah.</p>
      <p class="mt-2">CTR = klik ÷ tayangan. ROAS = penjualan ÷ biaya iklan. Tanda - berarti metrik belum tersedia, belum terverifikasi, atau tidak dapat dihitung.</p>
    </details>
  </div>
  <div id="ads-state" class="mb-5 rounded-xl border border-base-content/20 bg-base-100 p-4 text-sm text-base-content" role="status" aria-live="polite">Memuat laporan iklan…</div>
  <div id="ads-session-warning" class="mb-5 rounded-xl border border-base-content/20 bg-base-100 p-4 text-sm text-base-content" hidden>
    <p data-session-message></p>
    <a class="ads-inline-link" href="<?= burl; ?>/panel/shops">Perbarui koneksi toko</a>
  </div>
  <div id="ads-grid" class="grid min-w-0 grid-cols-1 gap-5" aria-busy="true"></div>
  <section class="mt-10 border-t border-base-content/15 pt-7" aria-labelledby="ads-topups-title">
    <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
      <div>
        <h2 id="ads-topups-title" class="text-xl font-black tracking-tight text-base-content">Riwayat topup saldo iklan</h2>
      </div>
      <p class="text-xs text-base-content/60" data-topups-updated>Belum tersinkron</p>
    </div>
    <div class="mb-4 rounded-xl border border-base-content/15 bg-base-100 p-4">
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div>
          <label for="ads-topup-shop" class="mb-2 block text-sm font-bold">Toko topup</label>
          <select id="ads-topup-shop" class="select min-h-11 w-full"><option value="">Semua toko</option></select>
        </div>
        <div>
          <label for="ads-topup-period" class="mb-2 block text-sm font-bold">Periode topup</label>
          <select id="ads-topup-period" class="select min-h-11 w-full">
            <option value="all">Semua sejak Agustus 2026</option>
            <option value="this_month">Bulan ini</option>
            <option value="last_month">Bulan lalu</option>
            <option value="last_3_months">3 bulan terakhir</option>
            <option value="custom">Pilih tanggal</option>
          </select>
        </div>
        <div id="ads-topup-custom-range" class="grid grid-cols-1 gap-4 sm:col-span-2 sm:grid-cols-2" hidden>
          <div>
            <label for="ads-topup-start" class="mb-2 block text-sm font-bold">Dari tanggal</label>
            <input id="ads-topup-start" class="input min-h-11 w-full" type="date" min="2026-08-01" max="<?= htmlspecialchars(date('Y-m-d'), ENT_QUOTES, 'UTF-8'); ?>" value="2026-08-01">
          </div>
          <div>
            <label for="ads-topup-end" class="mb-2 block text-sm font-bold">Sampai tanggal</label>
            <input id="ads-topup-end" class="input min-h-11 w-full" type="date" min="2026-08-01" max="<?= htmlspecialchars(date('Y-m-d'), ENT_QUOTES, 'UTF-8'); ?>" value="<?= htmlspecialchars(date('Y-m-d'), ENT_QUOTES, 'UTF-8'); ?>">
          </div>
        </div>
      </div>
      <p id="ads-topups-period-note" class="mt-3 text-sm text-base-content" aria-live="polite"></p>
    </div>
    <div id="ads-topups-state" class="mb-4 rounded-lg border border-base-content/15 bg-base-100 p-3 text-sm text-base-content" role="status" aria-live="polite">Memuat laporan topup…</div>
    <div class="overflow-x-auto rounded-xl border border-base-content/15 bg-base-100">
      <table class="w-full min-w-[22rem] text-left text-sm">
        <caption class="sr-only">Total topup saldo iklan termasuk PPN per bulan</caption>
        <thead class="border-b border-base-content/15 text-xs text-base-content/60">
          <tr><th scope="col" class="px-4 py-3 font-bold">Bulan</th><th scope="col" class="px-4 py-3 text-right font-bold">Topup berhasil (termasuk PPN)</th></tr>
        </thead>
        <tbody id="ads-topups-rows" class="divide-y divide-base-content/10"></tbody>
      </table>
    </div>
  </section>
</section>
<template id="ads-card-template">
  <article class="min-w-0 overflow-hidden rounded-xl border border-base-content/20 bg-base-100 text-base-content">
    <div class="flex flex-wrap items-start justify-between gap-3 border-b border-base-content/20 p-5">
      <div class="shop-identity"><span class="shop-logo" data-shop-logo></span><div class="min-w-0">
        <h3 class="break-words text-lg font-black" data-shop-name></h3>
        <p class="mt-1 text-sm" data-performance-period></p>
        <p class="mt-2 text-xs" data-shop-sync></p>
      </div></div>
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
    <div class="mx-5 mb-5 rounded-lg bg-base-200 p-3 text-sm" data-warning hidden>
      <p data-note></p>
      <a href="<?= burl; ?>/panel/sync" class="ads-inline-link" data-recovery>Lihat sinkronisasi</a>
    </div>
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
    <details class="border-t border-base-content/20 text-sm" data-source-detail>
      <summary class="min-h-11 cursor-pointer px-5 py-3 font-bold">Detail data</summary>
      <div class="px-5 pb-4">
        <p class="mb-2" data-source-note></p>
        <p data-session-status></p>
        <p class="mt-2" data-meta></p>
        <p class="mt-2" data-channels></p>
      </div>
    </details>
  </article>
</template>
<?php require __DIR__ . '/templates/shop-logos.php'; ?>
<script src="<?= assets; ?>/js/shop-select.js?v=<?= filemtime(__DIR__ . '/../../../public/assets/js/shop-select.js'); ?>"></script>
<script src="<?= assets; ?>/js/ads.js?v=<?= filemtime(__DIR__ . '/../../../public/assets/js/ads.js'); ?>" defer></script>
