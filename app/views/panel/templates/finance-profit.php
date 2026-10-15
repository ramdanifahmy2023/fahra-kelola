<section id="finance-profit-panel" class="finance-surface finance-profit-panel" aria-label="Laporan laba rugi" <?= $financeDashboard ? '' : 'hidden'; ?>>
  <div class="finance-section-heading"><div><h2>Laporan laba rugi</h2><p id="finance-profit-scope">Mengikuti toko dan periode pilihan.</p></div><div><label for="finance-profit-basis">Dasar tanggal</label><select id="finance-profit-basis" class="select"><option value="accrual">Pesanan dibuat (ACCRUAL)</option><option value="cash">Dana dilepas (CASH)</option></select></div></div>
  <p id="finance-profit-status" class="finance-help" role="status">Memuat laporan laba rugi…</p>
  <div id="finance-profit-error" class="finance-notice" role="alert" hidden><p></p><button id="finance-profit-retry" class="btn" type="button">Coba lagi</button></div>
  <div id="finance-profit-content"></div>
  <details class="finance-explanation"><summary>Sumber &amp; batas perhitungan</summary><div id="finance-profit-method"><p>ACCRUAL memakai nilai pesanan selesai menurut tanggal pesanan dibuat di WIB. CASH memakai penghasilan bersih Shopee menurut tanggal dana dilepas. HPP mengikuti rincian item dan versi biaya pada tanggal pesanan dibuat.</p><p>Biaya iklan mengikuti periode pilihan. Biaya platform per jenis dan kerugian barang retur belum terverifikasi, sehingga hasil ini belum merupakan laba bersih final.</p></div></details>
</section>
