<section id="dashboard-activity" class="finance-surface dashboard-section" aria-labelledby="dashboard-activity-title">
  <div class="finance-section-heading"><div><h2 id="dashboard-activity-title">Aktivitas hari ini</h2><p id="dashboard-live-scope">Hari ini, WIB · mengikuti pilihan toko</p></div><button id="dashboard-live-refresh" class="btn" type="button">Perbarui aktivitas</button></div>
  <p class="finance-help">Laporan harian Shopee terpisah dari omset dibayar di atas. Diperiksa setiap 30 detik selama halaman aktif.</p>
  <p id="dashboard-live-status" role="status">Memuat aktivitas…</p>
  <div id="dashboard-live-error" class="finance-notice" role="alert" hidden></div>
  <dl id="dashboard-live-metrics" class="dashboard-live-metrics"></dl>
  <div class="dashboard-live-detail">
    <div><h3>Produk terlaris hari ini</h3><div id="dashboard-top-products"></div></div>
    <div><h3>Penjualan terkonfirmasi per jam</h3><p class="finance-meta">Rupiah · jam WIB · pilih jam untuk melihat nominal</p><div id="dashboard-hourly"></div><p id="dashboard-hour-value" role="status"></p><details><summary>Lihat angka per jam</summary><div id="dashboard-hour-table"></div></details></div>
  </div>
</section>
<section id="dashboard-operations" class="finance-surface dashboard-section" aria-labelledby="dashboard-operations-title">
  <div class="finance-section-heading"><div><h2 id="dashboard-operations-title">Kondisi toko</h2><p>Produk, stok, koneksi, dan pelanggan: posisi terakhir tersimpan.</p></div><a class="btn" href="<?= burl; ?>/panel/shops">Kelola toko</a></div>
  <p id="dashboard-operations-status" role="status">Memuat kondisi toko…</p>
  <div id="dashboard-operations-error" class="finance-notice" role="alert" hidden><p></p><button id="dashboard-operations-retry" class="btn" type="button">Coba lagi</button></div>
  <div id="dashboard-operations-content"></div>
</section>
