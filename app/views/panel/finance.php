<?php
$financeToday=(new DateTimeImmutable('now',new DateTimeZone('Asia/Jakarta')))->format('Y-m-d');
$financeDashboard=!empty($data['finance_dashboard']);
?>
<section id="finance-page" data-dashboard="<?= $financeDashboard ? 'true' : 'false'; ?>" data-base="<?= htmlspecialchars(burl,ENT_QUOTES,'UTF-8'); ?>" data-endpoint="<?= htmlspecialchars(burl.'/procFinance',ENT_QUOTES,'UTF-8'); ?>" data-csrf="<?= htmlspecialchars(authCsrfToken(),ENT_QUOTES,'UTF-8'); ?>" data-today="<?= $financeToday; ?>">
  <header class="finance-heading"><div><h1><?= $financeDashboard ? 'Dashboard' : 'Keuangan'; ?></h1><?php if (!$financeDashboard): ?><p>Saldo Shopee, rincian penghasilan, dan HPP produk.</p><?php endif; ?></div><div class="finance-actions"><button id="finance-sync" class="btn btn-primary" type="button" <?= !$data['shops'] ? 'disabled' : ''; ?>><span class="material-symbols-outlined" aria-hidden="true">sync</span>Perbarui saldo Shopee</button></div></header>
  <?php if (!$data['shops']): ?>
    <div class="finance-surface"><h2>Tambahkan toko untuk mulai</h2><p>Saldo dan HPP akan disimpan terpisah untuk setiap toko.</p><a class="btn" href="<?= burl; ?>/panel/shops">Kelola toko</a></div>
  <?php else: ?>
  <noscript>Aktifkan JavaScript untuk memilih toko, melihat saldo, dan mengisi HPP.</noscript>
  <form id="finance-filters" class="finance-filters">
    <div class="finance-shop-field"><label for="finance-shops">Toko</label><select id="finance-shops" multiple><?php foreach ($data['shops'] as $shop): ?><option selected value="<?= (int)$shop['id']; ?>"><?= htmlspecialchars($shop['name'],ENT_QUOTES,'UTF-8'); ?></option><?php endforeach; ?></select><button id="finance-all-shops" type="button" class="finance-text-button">Pilih semua toko</button></div>
    <div><label for="finance-period">Periode laporan</label><select class="select" id="finance-period"><option value="month">Bulan ini</option><option value="today">Hari ini</option><option value="custom">Pilih tanggal sendiri</option></select></div>
    <button class="btn" type="submit">Terapkan</button>
    <div id="finance-custom-dates" class="finance-custom-dates" hidden>
      <div><label for="finance-start">Mulai tanggal</label><input class="input" id="finance-start" type="date" min="2015-01-01" max="<?= $financeToday; ?>" required value="<?= substr($financeToday,0,8); ?>01"></div>
      <div><label for="finance-end">Sampai tanggal</label><input class="input" id="finance-end" type="date" min="2015-01-01" max="<?= $financeToday; ?>" required value="<?= $financeToday; ?>"></div>
    </div>
    <p id="finance-filter-draft" class="finance-filter-draft" role="status" hidden>Pilihan berubah. Tekan Terapkan untuk memperbarui tampilan.</p>
  </form>
  <div class="finance-scope"><p id="finance-scope-label"></p><p id="finance-status" role="status" aria-live="polite">Memuat ringkasan…</p></div>
  <div id="finance-error" class="finance-notice" role="alert" hidden><p></p><button id="finance-retry" type="button" class="btn">Coba lagi</button></div>
  <div class="finance-balances" aria-label="Saldo Shopee">
    <article><div class="finance-metric-heading"><span class="finance-metric-icon material-symbols-outlined" aria-hidden="true">hourglass_top</span><div><h2>Pending</h2><p>Belum dilepas Shopee · posisi terbaru</p></div></div><p class="finance-amount" id="finance-pending">Memuat…</p><p id="finance-pending-quality"></p><p id="finance-pending-note" class="finance-meta"></p><a class="finance-text-button" href="#finance-pending-breakdown">Lihat bagian pending</a></article>
    <article><div class="finance-metric-heading"><span class="finance-metric-icon material-symbols-outlined" aria-hidden="true">account_balance_wallet</span><div><h2>Sudah dilepas Shopee</h2><p id="finance-released-period">Selama periode pilihan</p></div></div><p class="finance-amount" id="finance-released">Memuat…</p><p id="finance-released-quality"></p><p id="finance-released-note" class="finance-meta"></p><p class="finance-meta">Belum berarti sudah ditarik ke rekening bank.</p></article>
    <article><div class="finance-metric-heading"><span class="finance-metric-icon material-symbols-outlined" aria-hidden="true">wallet</span><div><h2>Saldo Penjual</h2><p>Saldo di dompet Shopee · posisi terbaru</p></div></div><p class="finance-amount" id="finance-wallet">Memuat…</p><p id="finance-wallet-quality"></p><p id="finance-wallet-note" class="finance-meta"></p><a id="finance-wallet-restrictions" class="finance-text-button" href="#finance-summary-panel" hidden></a></article>
  </div>
  <p class="finance-help">Pending dan Sudah dilepas mengikuti Penghasilan Saya, belum termasuk penyesuaian. Saldo Penjual mengikuti “Saldo” di Saldo Saya. Ketiganya tidak dijumlahkan.</p>
  <div id="finance-secondary" class="finance-secondary finance-surface" aria-label="Omset, top up, dan biaya iklan selama periode pilihan"></div>
  <p class="finance-help">Top up adalah uang untuk mengisi saldo iklan. Biaya terpakai adalah pemakaiannya. Jangan dijumlahkan sebagai satu biaya. Riwayat top up tersedia sejak 1 Agustus 2026.</p>
  <section id="finance-pending-breakdown" class="finance-surface finance-pending-breakdown" aria-labelledby="finance-pending-title"><div class="finance-section-heading"><div><h2 id="finance-pending-title">Isi rincian Pending</h2><p>Sudah termasuk total Pending. Tidak dijumlahkan lagi.</p></div></div><div id="finance-pending-states"></div><details class="finance-explanation"><summary>Arti tahap pesanan</summary><p>Perlu dikirim: pengiriman belum diatur. Pickup / verifikasi kurir: pickup tercatat atau masih menunggu verifikasi jasa kirim. Dalam pengiriman: paket dalam perjalanan. Tiba, menunggu dilepas: barang sudah diterima, dananya masih ditahan Shopee.</p><p>Paket berbeda tahap dihitung sekali per pesanan. Retur proses belum berarti refund sudah dipotong.</p></details></section>
  <?php if (!$financeDashboard): ?>
  <nav class="finance-tabs" aria-label="Bagian keuangan"><button type="button" data-finance-tab="summary" aria-pressed="true">Ringkasan toko</button><button type="button" data-finance-tab="details" aria-pressed="false">Rincian penghasilan</button><button type="button" data-finance-tab="cost" aria-pressed="false">HPP produk</button></nav>
  <?php endif; ?>
  <section id="finance-summary-panel" class="finance-surface" aria-label="Ringkasan per toko">
    <div class="finance-section-heading"><div><h2>Ringkasan per toko</h2><p id="finance-store-period">Pending dan Saldo Penjual: posisi terbaru. Angka lainnya mengikuti periode pilihan.</p></div><?php if ($financeDashboard): ?><a id="finance-open" class="btn" href="<?= burl; ?>/panel/finance"><span class="material-symbols-outlined" aria-hidden="true">account_balance_wallet</span>Rincian &amp; HPP</a><?php endif; ?></div>
    <div id="finance-store-list"></div>
    <details class="finance-explanation"><summary>Cara membaca angka ini</summary><p>Omset memakai nilai pesanan yang sudah dibayar dari laporan Shopee. Biaya iklan memakai biaya pemakaian iklan, bukan top up. Jumlah hari yang tersedia ditulis agar data yang belum lengkap tidak terlihat seolah lengkap.</p><p>Dalam pengiriman dan retur proses adalah bagian dari rincian pending, jadi jangan ditambahkan lagi ke total pending. Status yang belum bisa dipastikan ditulis terpisah. Retur proses belum berarti uang refund sudah dipotong.</p><p>Untuk minggu atau bulan berjalan yang persis cocok dengan periode ringkasan Shopee, angka utama dilepas memakai ringkasan tersebut. Periode custom memakai jumlah rincian berdasarkan tanggal pelepasan. Ringkasan dan rincian bisa berbeda; keduanya tetap ditampilkan tanpa menebak penyebab selisih.</p></details>
  </section>
  <?php if (!$financeDashboard): ?>
  <section id="finance-details-panel" class="finance-surface" aria-label="Rincian penghasilan" hidden>
    <div class="finance-section-heading"><div><h2>Rincian penghasilan</h2><p id="finance-detail-scope"></p></div><div><label for="finance-category">Status dana</label><select class="select" id="finance-category"><option value="1">Pending</option><option value="2">Sudah dilepas</option></select></div></div>
    <div id="finance-state-field"><label for="finance-state">Bagian Pending</label><select class="select" id="finance-state"><option value="">Semua bagian Pending</option><option value="preparing">Perlu dikirim</option><option value="pickup">Pickup / verifikasi kurir</option><option value="shipping">Dalam pengiriman</option><option value="delivered">Tiba, menunggu dilepas</option><option value="return">Retur proses</option><option value="mixed">Paket berbeda tahap</option><option value="unknown">Status belum dipastikan</option></select></div>
    <form id="finance-order-search-form" class="finance-search"><label for="finance-order-search">Nomor pesanan</label><input class="input" id="finance-order-search" maxlength="100" placeholder="Cari nomor pesanan"><button class="btn" type="submit">Cari</button></form>
    <div id="finance-detail-list" aria-live="polite"></div><div id="finance-detail-pagination" class="finance-pagination"></div>
  </section>
  <section id="finance-cost-panel" class="finance-surface" aria-label="HPP produk" hidden>
    <div class="finance-section-heading"><div><h2>HPP produk</h2><p>Satu angka modal per unit, untuk setiap varian di setiap toko.</p></div></div>
    <form id="finance-cost-search-form" class="finance-search"><label for="finance-cost-search">SKU atau produk</label><input class="input" id="finance-cost-search" maxlength="100" placeholder="Cari SKU, produk, atau varian"><button class="btn" type="submit">Cari</button></form>
    <p class="finance-help">Perubahan HPP mengikuti tanggal pesanan dibuat (WIB). Riwayat modal tetap tersimpan saat produk diarsipkan. HPP di sini tidak mengubah harga jual di Shopee.</p>
    <div id="finance-cost-list" aria-live="polite"></div><div id="finance-cost-pagination" class="finance-pagination"></div>
  </section>
  <dialog id="finance-cost-dialog" aria-labelledby="finance-cost-title">
    <div class="finance-dialog-heading"><h2 id="finance-cost-title">Atur HPP</h2><button type="button" id="finance-close-cost" class="btn" aria-label="Tutup formulir HPP">Tutup</button></div>
    <p id="finance-cost-item"></p>
    <form id="finance-cost-form">
      <label for="finance-cost-value">Modal per unit (Rp)</label><input class="input" type="number" id="finance-cost-value" min="0" max="1000000000" step="1" required inputmode="numeric">
      <label for="finance-cost-date">Berlaku mulai tanggal pesanan dibuat</label><input class="input" type="date" id="finance-cost-date" min="2015-01-01" max="2100-01-01" required>
      <p class="finance-help">Isi 0 hanya jika produk memang tanpa modal. Tanggal mundur akan menghitung ulang modal pesanan yang tersimpan.</p>
      <div id="finance-cost-preview" class="finance-notice" role="status" hidden></div>
      <p id="finance-cost-error" class="finance-notice" role="alert" hidden></p>
      <div class="finance-dialog-actions"><button id="finance-preview-cost" class="btn" type="submit">Periksa dampak</button><button id="finance-save-cost" class="btn btn-primary" type="button" disabled>Simpan HPP</button></div>
    </form>
    <details class="finance-explanation"><summary>Riwayat modal</summary><div id="finance-cost-history"></div></details>
  </dialog>
  <?php else: require __DIR__.'/templates/dashboard-activity.php'; endif; ?>
  <?php require __DIR__.'/templates/shop-logos.php'; ?>
  <script src="<?= assets; ?>/js/shop-select.js?v=<?= filemtime(__DIR__.'/../../../public/assets/js/shop-select.js'); ?>"></script>
  <script src="<?= assets; ?>/js/finance.js?v=<?= filemtime(__DIR__.'/../../../public/assets/js/finance.js'); ?>" defer></script>
  <?php if ($financeDashboard): ?><script src="<?= assets; ?>/js/dashboard.js?v=<?= filemtime(__DIR__.'/../../../public/assets/js/dashboard.js'); ?>" defer></script><?php endif; ?>
  <?php endif; ?>
</section>
