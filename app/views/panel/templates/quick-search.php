<dialog id="quick-search" aria-labelledby="quick-search-title" data-base="<?= htmlspecialchars(burl,ENT_QUOTES,'UTF-8'); ?>">
  <div class="quick-search-heading"><h2 id="quick-search-title">Cari di toko Anda</h2><button type="button" class="btn" id="quick-search-close" aria-label="Tutup pencarian"><span class="material-symbols-outlined" aria-hidden="true">close</span></button></div>
  <form id="quick-search-form" role="search">
    <label for="quick-search-input">Pesanan, resi, produk, SKU, atau pelanggan</label><input id="quick-search-input" class="input" type="search" maxlength="100" autocomplete="off" placeholder="Ketik minimal 2 karakter">
    <label for="quick-search-shop">Cari pada toko</label><select id="quick-search-shop" class="select"><option value="0">Semua toko</option><?php foreach ($data['shops'] ?? [] as $searchShop): ?><option value="<?= (int)$searchShop['id']; ?>"><?= htmlspecialchars($searchShop['name'],ENT_QUOTES,'UTF-8'); ?></option><?php endforeach; ?></select>
  </form>
  <p id="quick-search-status" role="status" aria-atomic="true">Hasil memakai data yang sudah tersimpan.</p>
  <div id="quick-search-results" aria-label="Hasil pencarian"></div>
</dialog>
<script src="<?= assets; ?>/js/quick-search.js?v=<?= filemtime(__DIR__.'/../../../../public/assets/js/quick-search.js'); ?>" defer></script>
