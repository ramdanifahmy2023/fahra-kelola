<?php $financeToday=(new DateTimeImmutable('now',new DateTimeZone('Asia/Jakarta')))->format('Y-m-d'); ?>
<section id="finance-page" data-endpoint="<?= htmlspecialchars(burl.'/procFinance',ENT_QUOTES,'UTF-8'); ?>" data-csrf="<?= htmlspecialchars(authCsrfToken(),ENT_QUOTES,'UTF-8'); ?>" data-today="<?= $financeToday; ?>">
  <header class="finance-heading"><div><h1>Keuangan</h1><p>Lihat uang yang masih pending, sudah dilepas, dan modal produk.</p></div><button id="finance-sync" class="btn btn-primary" type="button" <?= !$data['shops'] ? 'disabled' : ''; ?>>Perbarui data</button></header>
  <?php if (!$data['shops']): ?>
    <div class="finance-surface"><h2>Tambahkan toko untuk mulai</h2><p>Saldo dan HPP akan disimpan terpisah untuk setiap toko.</p><a class="btn" href="<?= burl; ?>/panel/shops">Kelola toko</a></div>
  <?php else: ?>
  <noscript>Aktifkan JavaScript untuk memilih toko, melihat saldo, dan mengisi HPP.</noscript>
  <form id="finance-filters" class="finance-filters">
    <div class="finance-shop-field"><label for="finance-shops">Toko</label><select id="finance-shops" multiple><?php foreach ($data['shops'] as $shop): ?><option selected value="<?= (int)$shop['id']; ?>"><?= htmlspecialchars($shop['name'],ENT_QUOTES,'UTF-8'); ?></option><?php endforeach; ?></select><button id="finance-all-shops" type="button" class="finance-text-button">Pilih semua toko</button></div>
    <div><label for="finance-start">Mulai tanggal</label><input class="input" id="finance-start" type="date" min="2015-01-01" max="<?= $financeToday; ?>" required value="<?= substr($financeToday,0,8); ?>01"></div>
    <div><label for="finance-end">Sampai tanggal</label><input class="input" id="finance-end" type="date" min="2015-01-01" max="<?= $financeToday; ?>" required value="<?= $financeToday; ?>"></div>
    <button class="btn" type="submit">Terapkan</button>
  </form>
  <p class="finance-help">Tanggal memakai WIB. Pilihan periode berlaku untuk dana dilepas, omset, dan biaya iklan. Pending menampilkan posisi terbaru.</p>
  <p id="finance-status" role="status" aria-live="polite">Memuat data keuangan tersimpan…</p>
  <div id="finance-error" class="finance-notice" role="alert" hidden><p></p><button id="finance-retry" type="button" class="btn">Coba lagi</button></div>
  <div class="finance-balances" aria-label="Saldo penghasilan">
    <article><h2>Pending</h2><p class="finance-amount" id="finance-pending">Belum dimuat</p><p id="finance-pending-note">Posisi terbaru yang berhasil diambil.</p></article>
    <article><h2>Sudah dilepas</h2><p class="finance-amount" id="finance-released">Belum dimuat</p><p id="finance-released-note">Sesuai tanggal dana dilepas.</p></article>
  </div>
  <p class="finance-help">Seperti di Penghasilan Saya Shopee, kedua angka ini belum termasuk penyesuaian. Dana dilepas adalah penghasilan yang dilepas Shopee; bukan catatan penarikan ke bank.</p>
  <nav class="finance-tabs" aria-label="Bagian keuangan"><button type="button" data-finance-tab="summary" aria-pressed="true">Ringkasan toko</button><button type="button" data-finance-tab="details" aria-pressed="false">Rincian penghasilan</button><button type="button" data-finance-tab="cost" aria-pressed="false">HPP produk</button></nav>
  <section id="finance-summary-panel" class="finance-surface" aria-label="Ringkasan per toko">
    <div id="finance-secondary" class="finance-secondary"></div>
    <div id="finance-store-list"></div>
    <details class="finance-explanation"><summary>Cara membaca angka ini</summary><p>Omset memakai nilai pesanan yang sudah dibayar dari laporan Shopee. Biaya iklan memakai biaya pemakaian iklan, bukan top up. Jumlah hari yang tersedia ditulis agar data yang belum lengkap tidak terlihat seolah lengkap.</p><p>Dalam pengiriman dan retur proses adalah bagian dari rincian pending, jadi jangan ditambahkan lagi ke total pending. Status yang belum bisa dipastikan ditulis terpisah. Retur proses belum berarti uang refund sudah dipotong.</p><p>Untuk minggu atau bulan berjalan yang persis cocok dengan periode ringkasan Shopee, angka utama dilepas memakai ringkasan tersebut. Periode custom memakai jumlah rincian berdasarkan tanggal pelepasan. Ringkasan dan rincian bisa berbeda; keduanya tetap ditampilkan tanpa menebak penyebab selisih.</p></details>
  </section>
  <section id="finance-details-panel" class="finance-surface" aria-label="Rincian penghasilan" hidden>
    <div class="finance-section-heading"><div><h2>Rincian penghasilan</h2><p id="finance-detail-scope"></p></div><div><label for="finance-category">Status dana</label><select class="select" id="finance-category"><option value="1">Pending</option><option value="2">Sudah dilepas</option></select></div></div>
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
  <?php require __DIR__.'/templates/shop-logos.php'; ?>
  <script src="<?= assets; ?>/js/shop-select.js?v=<?= filemtime(__DIR__.'/../../../public/assets/js/shop-select.js'); ?>"></script>
  <script src="<?= assets; ?>/js/finance.js?v=<?= filemtime(__DIR__.'/../../../public/assets/js/finance.js'); ?>" defer></script>
  <?php endif; ?>
</section>
