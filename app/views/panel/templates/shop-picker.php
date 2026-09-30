<?php
$pickerPage = $data['active_menu'];
$pickerShopId = (int)($data['active_shop_id'] ?? 0);
$pickerShops = $data['shops'] ?? [];
if (in_array($pickerPage, ['customers', 'reports'], true)) array_unshift($pickerShops, ['id' => 0, 'name' => 'Semua toko', 'shop_logo' => '']);
$pickerActive = null;
foreach ($pickerShops as $pickerShop) {
  if ((int)$pickerShop['id'] === $pickerShopId) { $pickerActive = $pickerShop; break; }
}
$pickerQuery = isset($data['limit']) ? ['limit' => (int)$data['limit']] : [];
if ($pickerPage === 'orders') $pickerQuery += ['startDate' => $data['start_date'], 'endDate' => $data['end_date']];
if ($pickerPage === 'products' && ($data['stock_filter'] ?? '') === 'critical') $pickerQuery['stock'] = 'critical';
if ($pickerPage === 'products' && ($data['movement_view'] ?? false)) {
  $pickerQuery['view'] = 'movement';
  if (!empty($data['movement_filter'])) $pickerQuery['movement'] = $data['movement_filter'];
}
?>
<div class="shop-picker dropdown dropdown-bottom dropdown-end">
  <button type="button" class="btn w-full justify-start gap-3 font-normal shadow-sm" id="selectedShopDisplay" aria-label="Pilih toko: <?= htmlspecialchars($pickerActive['name'] ?? 'Belum ada toko', ENT_QUOTES, 'UTF-8'); ?>" <?= !$pickerShops ? 'disabled' : ''; ?>>
    <?php if (!empty($pickerActive['shop_logo'])): ?>
      <img src="<?= htmlspecialchars($pickerActive['shop_logo'], ENT_QUOTES, 'UTF-8'); ?>" alt="" width="24" height="24" />
    <?php else: ?>
      <span class="material-symbols-outlined" aria-hidden="true">storefront</span>
    <?php endif; ?>
    <span class="min-w-0 flex-1 truncate text-left"><?= htmlspecialchars($pickerActive['name'] ?? 'Belum ada toko', ENT_QUOTES, 'UTF-8'); ?></span>
    <span class="material-symbols-outlined" aria-hidden="true">expand_more</span>
  </button>
  <ul tabindex="-1" class="shop-picker-list dropdown-content menu rounded-box mt-2 bg-base-100 p-2 shadow-lg" aria-label="Daftar toko">
    <?php foreach ($pickerShops as $pickerShop): ?>
      <li>
        <a href="<?= burl; ?>/panel/<?= $pickerPage; ?>?<?= htmlspecialchars(http_build_query(['shop_id' => (int)$pickerShop['id']] + $pickerQuery), ENT_QUOTES, 'UTF-8'); ?>" data-product-shop="<?= (int)$pickerShop['id']; ?>" <?= (int)$pickerShop['id'] === $pickerShopId ? 'aria-current="page" class="bg-base-200"' : ''; ?>>
          <?php if (!empty($pickerShop['shop_logo'])): ?>
            <img src="<?= htmlspecialchars($pickerShop['shop_logo'], ENT_QUOTES, 'UTF-8'); ?>" alt="" width="24" height="24" />
          <?php else: ?>
            <span class="material-symbols-outlined" aria-hidden="true">storefront</span>
          <?php endif; ?>
          <span><?= htmlspecialchars($pickerShop['name'] ?: 'Toko Tanpa Nama', ENT_QUOTES, 'UTF-8'); ?></span>
        </a>
      </li>
    <?php endforeach; ?>
  </ul>
  <input type="hidden" id="<?= $pickerPage === 'reports' ? 'report-shop' : 'selectedShopId'; ?>" value="<?= $pickerShopId; ?>" />
</div>
