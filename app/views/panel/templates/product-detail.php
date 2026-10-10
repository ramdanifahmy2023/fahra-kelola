<?php
$detailEscape = static fn($value) => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
$detailPrice = static function ($min, $max) {
    if ($min === null || $min === '') return 'Belum tersedia';
    $label = 'Rp ' . number_format((float)$min, 0, ',', '.');
    return $max !== null && (float)$max > (float)$min ? $label . ' sampai Rp ' . number_format((float)$max, 0, ',', '.') : $label;
};
$detailName = trim(str_replace(['<', '>'], '', (string)$p['name']));
$detailStatus = match ((int)$p['status']) { 1 => 'Aktif', 2 => 'Habis', default => 'Diarsipkan' };
?>
<article class="product-detail">
  <div class="product-detail-identity">
    <div class="product-detail-image">
      <?php if (!empty($p['cover_image'])): ?>
      <img src="https://cf.shopee.co.id/file/<?= $detailEscape($p['cover_image']); ?>" alt="" width="96" height="96" class="product-cover" />
      <?php else: ?><span class="material-symbols-outlined" aria-hidden="true">inventory_2</span><?php endif; ?>
    </div>
    <div><h3><?= $detailEscape($detailName); ?></h3><p><?= $detailEscape($activeShop['name'] ?? 'Toko'); ?></p></div>
  </div>
  <dl class="product-detail-facts">
    <div><dt>Status</dt><dd><?= $detailEscape($detailStatus); ?></dd></div>
    <div><dt>Stok</dt><dd><?= isset($p['total_stock']) ? number_format((int)$p['total_stock'], 0, ',', '.') . ' buah' : 'Belum tersedia'; ?></dd></div>
    <div><dt>SKU utama</dt><dd><?= $detailEscape($p['parent_sku'] ?: 'Belum tersedia'); ?></dd></div>
    <?php if ((int)($p['variant_count'] ?? 0) > 0): ?>
    <div><dt>SKU varian</dt><dd>
      <?php if (!empty($p['variant_skus'])): ?><?= $detailEscape($p['variant_skus']); ?><?php else: ?>Belum tersedia<?php endif; ?>
      <span class="text-xs opacity-70"><?= (int)($p['variant_sku_count'] ?? 0); ?>/<?= (int)$p['variant_count']; ?> varian memiliki SKU</span>
    </dd></div>
    <?php endif; ?>
    <div><dt>ID produk</dt><dd><?= $detailEscape($p['id']); ?></dd></div>
    <div><dt>Harga tercatat</dt><dd><?= $detailPrice($p['price_min'] ?? null, $p['price_max'] ?? null); ?></dd></div>
    <div><dt>Harga jual</dt><dd><?= $detailPrice($p['selling_price_min'] ?? null, $p['selling_price_max'] ?? null); ?></dd></div>
    <div><dt>Terjual</dt><dd><?= isset($p['sold_count']) ? number_format((int)$p['sold_count'], 0, ',', '.') . ' buah' : 'Belum tersedia'; ?></dd></div>
  </dl>
  <p class="product-detail-note">Data produk tersimpan. Gunakan tombol Sync sekarang untuk memperbarui data toko.</p>
</article>
