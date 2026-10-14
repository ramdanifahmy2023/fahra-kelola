<section id="finance-page" class="dashboard-overview" data-user="<?= (int)(authUser()['id'] ?? 0); ?>" data-dashboard="true" data-base="<?= htmlspecialchars(burl,ENT_QUOTES,'UTF-8'); ?>" data-endpoint="<?= htmlspecialchars(burl.'/procFinance',ENT_QUOTES,'UTF-8'); ?>" data-csrf="<?= htmlspecialchars(authCsrfToken(),ENT_QUOTES,'UTF-8'); ?>" data-today="<?= $financeToday; ?>">
  <header class="finance-heading">
    <div><h1>Dashboard</h1></div>
    <?php if ($data['shops']): ?><nav class="dashboard-jump-nav" aria-label="Bagian dashboard"><a href="#dashboard-activity">Hari ini</a><a href="#finance-report-overview">Dana</a><a href="#finance-summary-panel">Per toko</a><a href="#dashboard-operations">Kondisi toko</a></nav><?php endif; ?>
  </header>
  <?php if (!$data['shops']): ?>
    <div class="finance-surface dashboard-empty"><span class="material-symbols-outlined" aria-hidden="true">storefront</span><h2>Mulai dengan menghubungkan toko</h2><p>Penjualan dan saldo toko akan tampil di sini.</p><a class="btn btn-primary" href="<?= burl; ?>/panel/shops">Hubungkan toko</a></div>
  <?php else: ?>
  <noscript>Aktifkan JavaScript untuk memuat Dashboard dan memilih toko.</noscript>
  <form id="finance-filters" class="finance-filters dashboard-toolbar">
    <div class="finance-shop-field"><label for="finance-shops">Toko</label><select id="finance-shops" multiple><?php foreach ($data['shops'] as $shop): ?><option selected value="<?= (int)$shop['id']; ?>"><?= htmlspecialchars($shop['name'],ENT_QUOTES,'UTF-8'); ?></option><?php endforeach; ?></select></div>
    <div><label for="finance-period">Periode laporan dana</label><select class="select" id="finance-period"><option value="month">Bulan ini</option><option value="today">Hari ini</option><option value="custom">Pilih tanggal sendiri</option></select></div>
    <button class="btn btn-primary" type="submit">Terapkan</button>
    <details class="dashboard-filter-help"><summary>Opsi toko</summary><div class="dashboard-filter-options"><button id="finance-all-shops" type="button" class="finance-text-button">Pilih semua toko</button><button id="finance-clear-shops" type="button" class="finance-text-button">Hapus pilihan toko</button><p id="finance-shop-hint">Pilih satu toko untuk fokus. Tambahkan toko lain untuk membandingkan.</p></div></details>
    <div id="finance-custom-dates" class="finance-custom-dates" hidden><div><label for="finance-start">Mulai tanggal</label><input class="input" id="finance-start" type="date" min="2015-01-01" max="<?= $financeToday; ?>" required value="<?= substr($financeToday,0,8); ?>01"></div><div><label for="finance-end">Sampai tanggal</label><input class="input" id="finance-end" type="date" min="2015-01-01" max="<?= $financeToday; ?>" required value="<?= $financeToday; ?>"></div></div>
    <p id="finance-filter-draft" class="finance-filter-draft" role="status" hidden>Pilihan berubah. Tekan Terapkan untuk memperbarui tampilan.</p>
  </form>
  <div id="finance-error" class="finance-notice" role="alert" hidden><p></p><button id="finance-retry" type="button" class="btn">Coba lagi</button></div>
  <div id="dashboard-attention" class="dashboard-attention" role="status"><span>Memeriksa kondisi toko…</span></div>

  <?php require __DIR__.'/dashboard-activity.php'; ?>

  <section id="finance-report-overview" aria-labelledby="dashboard-funds-title">
    <div class="finance-section-heading"><div><h2 id="dashboard-funds-title">Ringkasan dana</h2><p id="finance-scope-label"></p></div><div class="finance-actions"><button id="finance-sync" class="btn" type="button"><span class="material-symbols-outlined" aria-hidden="true">sync</span>Perbarui saldo Shopee</button></div></div>
    <div class="finance-balances" aria-label="Saldo Shopee">
      <article><div class="finance-metric-heading"><span class="material-symbols-outlined" aria-hidden="true">hourglass_top</span><h3>Dana tertahan <span class="dashboard-alias">(Pending)</span></h3></div><p class="finance-amount" id="finance-pending">Memuat…</p><p class="finance-meta">Belum dilepas Shopee · posisi terbaru</p><p id="finance-pending-quality"></p><details class="finance-metric-detail"><summary>Sumber &amp; pembaruan</summary><p id="finance-pending-note" class="finance-meta"></p><a class="finance-text-button" href="#finance-pending-breakdown">Lihat bagian pending</a></details></article>
      <article><div class="finance-metric-heading"><span class="material-symbols-outlined" aria-hidden="true">account_balance_wallet</span><h3>Sudah dilepas Shopee</h3></div><p class="finance-amount" id="finance-released">Memuat…</p><p class="finance-meta" id="finance-released-period">Selama periode pilihan</p><p id="finance-released-quality"></p><p class="finance-meta">Belum berarti sudah ditarik ke bank.</p><details class="finance-metric-detail"><summary>Sumber &amp; pembaruan</summary><p id="finance-released-note" class="finance-meta"></p></details></article>
      <article><div class="finance-metric-heading"><span class="material-symbols-outlined" aria-hidden="true">wallet</span><h3>Saldo Penjual</h3></div><p class="finance-amount" id="finance-wallet">Memuat…</p><p class="finance-meta">Dompet Shopee · posisi terbaru</p><p id="finance-wallet-quality"></p><a id="finance-wallet-restrictions" class="finance-text-button" href="#finance-summary-panel" hidden></a><details class="finance-metric-detail"><summary>Sumber &amp; pembaruan</summary><p id="finance-wallet-note" class="finance-meta"></p></details></article>
    </div>
    <p class="finance-help dashboard-balance-help">Tahap dana berbeda. Ketiga saldo tidak dijumlahkan.</p>
    <div id="finance-secondary" class="finance-secondary finance-surface" aria-label="Omset, top up, dan biaya iklan selama periode pilihan"></div>
    <div class="dashboard-source-footer"><p id="finance-status" role="status" aria-live="polite">Memuat ringkasan…</p><details class="finance-explanation finance-reading-guide"><summary>Arti saldo &amp; biaya</summary><p>Pending dan dana dilepas mengikuti Penghasilan Saya, sebelum penyesuaian. Saldo Penjual mengikuti “Saldo” di Saldo Saya.</p><p>Omset memakai pesanan dibayar. Top up mengisi saldo iklan, sedangkan biaya iklan adalah pemakaiannya. Keduanya tidak dijumlahkan sebagai satu biaya.</p></details></div>
  </section>

  <?php require __DIR__.'/dashboard-operations.php'; ?>
  <details class="dashboard-detail-section"><summary><span>Lihat tahap pesanan &amp; dana tertahan</span><span id="finance-pending-summary" class="dashboard-detail-note">Memuat rincian…</span></summary><?php require __DIR__.'/finance-pending.php'; ?></details>
  <section id="finance-summary-panel" class="finance-surface" aria-label="Ringkasan per toko">
    <div class="finance-section-heading"><div><h2>Ringkasan per toko</h2><p id="finance-store-period"></p></div><a id="finance-open" class="btn" href="<?= burl; ?>/panel/finance">Buka Keuangan &amp; HPP</a></div>
    <div id="finance-store-list"></div>
    <details class="finance-explanation"><summary>Cara membaca angka toko</summary><p>Pending dan Saldo Penjual adalah posisi terbaru. Dana dilepas, omset, top up, dan biaya iklan mengikuti periode laporan. Rincian pengiriman dan retur sudah termasuk Pending.</p><p>Ringkasan Shopee dan jumlah rincian dapat berbeda. Lihat sumber dan waktu pembaruan setiap toko sebelum membandingkan.</p></details>
  </section>
  <?php require __DIR__.'/shop-logos.php'; ?>
  <script src="<?= assets; ?>/js/shop-select.js?v=<?= filemtime(__DIR__.'/../../../../public/assets/js/shop-select.js'); ?>"></script>
  <script src="<?= assets; ?>/js/finance.js?v=<?= filemtime(__DIR__.'/../../../../public/assets/js/finance.js'); ?>" defer></script>
  <script src="<?= assets; ?>/js/dashboard.js?v=<?= filemtime(__DIR__.'/../../../../public/assets/js/dashboard.js'); ?>" defer></script>
  <?php endif; ?>
</section>
