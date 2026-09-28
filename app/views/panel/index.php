<?php
$summary = $data['summary'] ?? [];
$shopHealth = $data['shop_health'] ?? [];
$recentOrders = $data['recent_orders'] ?? [];
$realtimeShop = $shopHealth[0] ?? [];
$totalShops = (int)($summary['total_shops'] ?? 0);
$connectedShops = (int)($summary['connected_shops'] ?? 0);
$pendingOrderDetails = (int)($summary['pending_order_details'] ?? 0);
$completedOrderValue = (int)($summary['completed_order_value'] ?? 0);
$stockOutCount = (int)($summary['stock_out_count'] ?? 0);
$stockLowCount = (int)($summary['stock_low_count'] ?? 0);
$stockCriticalCount = (int)($summary['stock_critical_count'] ?? 0);
$lowStockProducts = $data['low_stock_products'] ?? [];
$lowStockByShop = $data['low_stock_by_shop'] ?? [];
$formatMoney = static function ($amount) {
  return 'Rp ' . number_format((int)$amount, 0, ',', '.');
};
$formatDate = static function ($date) {
  if (empty($date)) return 'Belum tersedia';
  $timestamp = strtotime($date);
  return $timestamp ? date('d M Y, H:i', $timestamp) : 'Belum tersedia';
};
$statusClass = static function ($status) {
  $normalized = strtolower((string)$status);
  if (strpos($normalized, 'completed') !== false || strpos($normalized, 'selesai') !== false) return 'badge-success';
  if (strpos($normalized, 'cancel') !== false || strpos($normalized, 'batal') !== false) return 'badge-error';
  if (strpos($normalized, 'menunggu') !== false || strpos($normalized, 'belum') !== false) return 'badge-warning';
  return 'badge-ghost';
};
?>

<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
  <div>
    <div class="mb-1 flex items-center gap-2 text-[10px] font-bold uppercase tracking-[0.16em] text-primary"><span class="material-symbols-outlined text-sm">space_dashboard</span>Operations overview</div>
    <h2 class="text-2xl font-black tracking-tight text-base-content">Dashboard</h2>
    <p class="mt-1 text-sm text-base-content/60">Ringkasan data lokal yang terakhir tersimpan dari channel penjualan.</p>
  </div>
  <?php if ($totalShops > 0): ?>
    <a href="<?= burl; ?>/panel/shops" class="btn btn-sm gap-2 rounded-lg border-base-content/10 bg-base-100"><span class="material-symbols-outlined text-base">storefront</span>Kelola toko</a>
  <?php else: ?>
    <a href="<?= burl; ?>/panel/shops" class="btn btn-sm btn-primary gap-2 rounded-lg"><span class="material-symbols-outlined text-base">add_business</span>Tambah toko</a>
  <?php endif; ?>
</div>

<?php if (!empty($realtimeShop['id'])): ?>
<section id="realtime-dashboard" class="mt-6 rounded-2xl border border-primary/20 bg-base-100 shadow-sm">
  <div class="flex flex-col gap-3 border-b border-base-content/10 p-4 sm:flex-row sm:items-center sm:justify-between sm:p-5">
    <div>
      <div class="flex items-center gap-2"><h3 class="font-black text-base-content">Monitoring realtime</h3><span id="realtime-live-badge" class="badge badge-ghost badge-sm">Memuat</span></div>
      <p class="mt-1 text-xs text-base-content/55">Data live dari Shopee untuk <span id="realtime-shop-name">Semua toko</span>.</p>
    </div>
    <div class="flex flex-wrap items-center gap-2 sm:justify-end">
      <?php if (count($shopHealth) > 1): ?>
        <details id="realtime-shop-filter" class="dropdown w-full sm:w-64">
          <summary class="btn min-h-11 w-full justify-between rounded-lg border-base-content/15 bg-base-100 px-3 font-medium normal-case" aria-label="Pilih toko monitoring realtime">
            <span id="realtime-shop-filter-label" class="truncate">Semua toko</span>
            <span class="material-symbols-outlined text-lg">expand_more</span>
          </summary>
          <div class="dropdown-content z-20 mt-2 w-full rounded-xl border border-base-content/10 bg-base-100 p-2 shadow-xl">
            <label class="flex min-h-11 cursor-pointer items-center gap-3 rounded-lg px-3 hover:bg-base-200">
              <input id="realtime-shop-all" type="checkbox" class="checkbox checkbox-primary checkbox-sm" checked>
              <span class="text-sm font-bold">Semua toko</span>
            </label>
            <div class="my-1 border-t border-base-content/10"></div>
            <div class="max-h-60 overflow-y-auto">
              <?php foreach ($shopHealth as $shop): ?>
                <label class="flex min-h-11 cursor-pointer items-center gap-3 rounded-lg px-3 hover:bg-base-200">
                  <input type="checkbox" class="realtime-shop-option checkbox checkbox-primary checkbox-sm" value="<?= (int)$shop['id']; ?>" data-name="<?= htmlspecialchars($shop['name'] ?: 'Toko tanpa nama', ENT_QUOTES); ?>">
                  <span class="min-w-0 truncate text-sm"><?= htmlspecialchars($shop['name'] ?: 'Toko tanpa nama'); ?></span>
                </label>
              <?php endforeach; ?>
            </div>
          </div>
        </details>
      <?php endif; ?>
      <span id="realtime-updated-at" class="text-xs text-base-content/50">Belum diperbarui</span>
    </div>
  </div>
  <div id="realtime-error" class="hidden border-b border-error/20 bg-error/5 px-5 py-3 text-xs text-error"></div>
  <div class="grid grid-cols-2 gap-4 p-4 sm:grid-cols-3 sm:gap-5 sm:p-5 xl:grid-cols-6">
    <div class="min-w-0"><div class="text-[10px] font-bold uppercase tracking-wide text-base-content/45">Pengunjung</div><div id="rt-uv" class="mt-1 text-2xl font-black">-</div></div>
    <div class="min-w-0"><div class="text-[10px] font-bold uppercase tracking-wide text-base-content/45">Tampilan</div><div id="rt-pv" class="mt-1 text-2xl font-black">-</div></div>
    <div class="min-w-0"><div class="text-[10px] font-bold uppercase tracking-wide text-base-content/45">Klik produk</div><div id="rt-clicks" class="mt-1 text-2xl font-black">-</div></div>
    <div class="min-w-0"><div class="text-[10px] font-bold uppercase tracking-wide text-base-content/45">Order</div><div id="rt-orders" class="mt-1 text-2xl font-black">-</div></div>
    <div class="min-w-0"><div class="text-[10px] font-bold uppercase tracking-wide text-base-content/45">Pembeli</div><div id="rt-buyers" class="mt-1 text-2xl font-black">-</div></div>
    <div class="min-w-0"><div class="text-[10px] font-bold uppercase tracking-wide text-base-content/45">Omzet realtime</div><div id="rt-sales" class="mt-1 truncate text-xl font-black">-</div></div>
  </div>
  <div class="grid grid-cols-1 gap-5 border-t border-base-content/10 p-4 sm:gap-6 sm:p-5 xl:grid-cols-2">
    <div class="min-w-0"><h4 class="text-sm font-black">Produk terlaris</h4><div id="realtime-top-products" class="mt-3 space-y-2 text-sm text-base-content/70"><div class="text-xs text-base-content/45">Menunggu data…</div></div></div>
    <div class="min-w-0"><h4 class="text-sm font-black">Penjualan per jam</h4><div class="mt-3 overflow-x-auto pb-1"><div id="realtime-hourly" class="grid min-w-[420px] grid-cols-11 items-end gap-1"></div></div></div>
  </div>
</section>
<script>
(() => {
  const root = document.getElementById('realtime-dashboard');
  if (!root) return;
  const number = value => new Intl.NumberFormat('id-ID').format(Number(value || 0));
  const money = value => 'Rp ' + number(value);
  const setText = (id, value) => { const el = document.getElementById(id); if (el) el.textContent = value; };
  const badge = document.getElementById('realtime-live-badge');
  const error = document.getElementById('realtime-error');
  const shopFilter = document.getElementById('realtime-shop-filter');
  const shopAll = document.getElementById('realtime-shop-all');
  const shopOptions = Array.from(document.querySelectorAll('.realtime-shop-option'));
  const shopFilterLabel = document.getElementById('realtime-shop-filter-label');
  const shopName = document.getElementById('realtime-shop-name');
  let activeShopIds = [];
  let inFlight = false;

  async function loadRealtime() {
    if (inFlight) return;
    inFlight = true;
    try {
      badge.textContent = 'Memuat'; badge.className = 'badge badge-ghost badge-sm';
      const query = activeShopIds.map(id => 'shop_ids[]=' + encodeURIComponent(id)).join('&');
      const response = await fetch('<?= burl; ?>/procrealtime/metrics' + (query ? '?' + query : ''), { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }, cache: 'no-store' });
      const payload = await response.json();
      if (!response.ok || payload.status !== 'success') throw new Error(payload.message || 'Metrik realtime gagal dimuat.');
      const metrics = payload.metrics || {};
      const key = metrics.key_metrics || {};
      setText('rt-uv', number(key.uv));
      setText('rt-pv', number(key.pv));
      setText('rt-clicks', number(key.product_clicks));
      setText('rt-orders', number(key.orders));
      setText('rt-buyers', number(key.buyers));
      setText('rt-sales', money(key.sales));
      const time = Number(metrics.time || 0) * 1000;
      setText('realtime-updated-at', time ? 'Diperbarui ' + new Date(time).toLocaleTimeString('id-ID', {hour:'2-digit', minute:'2-digit', second:'2-digit'}) : 'Baru saja');
      const failures = payload.failures || [];
      const selectedShopCount = Number(payload.selected_shop_count || payload.shop_count || 0);
      const liveShopCount = Number(payload.shop_count || 0);
      const hasFailures = failures.length > 0;
      badge.textContent = hasFailures ? 'Live ' + liveShopCount + '/' + selectedShopCount + ' toko' : 'Live';
      badge.className = hasFailures ? 'badge badge-warning badge-sm' : 'badge badge-success badge-sm text-white';
      if (hasFailures) {
        error.textContent = 'Tidak tersedia: ' + failures.map(item => (item.shop_name || ('Toko #' + item.shop_id)) + ' (' + (item.message || 'gagal mengambil data') + ')').join('; ') + '.';
        error.classList.remove('hidden');
      } else error.classList.add('hidden');

      const products = document.getElementById('realtime-top-products');
      products.innerHTML = (metrics.top_sales_items || []).slice(0, 5).map(item => '<div class="flex items-center justify-between gap-3 border-b border-base-content/10 pb-2 last:border-0"><span class="min-w-0 truncate" title="' + String(item.item_name || '').replace(/"/g, '&quot;') + '">' + String(item.item_name || 'Produk').replace(/[<>&]/g, c => ({'<':'&lt;','>':'&gt;','&':'&amp;'}[c])) + '</span><strong class="shrink-0 text-xs">' + money(item.sales) + '</strong></div>').join('') || '<div class="text-xs text-base-content/45">Belum ada produk terjual.</div>';

      const hourly = document.getElementById('realtime-hourly');
      const values = metrics.sales_hourly || [];
      const max = Math.max(...values.map(Number), 1);
      const startHour = Math.max(0, values.length - 22);
      hourly.innerHTML = values.slice(startHour).map((value, index) => '<div class="group flex h-24 flex-col justify-end gap-1"><div class="rounded-t bg-primary/70" style="height:' + Math.max(3, (Number(value || 0) / max) * 78) + 'px" title="' + money(value) + '"></div><span class="text-center text-[9px] text-base-content/40">' + (startHour + index) + '</span></div>').join('');
    } catch (err) {
      badge.textContent = 'Offline'; badge.className = 'badge badge-error badge-sm text-white';
      error.textContent = err.message || 'Metrik realtime gagal dimuat.';
      error.classList.remove('hidden');
    } finally {
      inFlight = false;
    }
  }
  function updateShopFilter() {
    const selected = shopOptions.filter(option => option.checked);
    const allSelected = !selected.length || selected.length === shopOptions.length;
    activeShopIds = allSelected ? [] : selected.map(option => option.value);
    shopAll.checked = allSelected;
    shopOptions.forEach(option => { if (allSelected) option.checked = false; });
    const labels = allSelected ? ['Semua toko'] : selected.map(option => option.dataset.name);
    const label = labels.length > 2 ? labels.length + ' toko dipilih' : labels.join(', ');
    if (shopName) shopName.textContent = label;
    if (shopFilterLabel) shopFilterLabel.textContent = label;
  }
  if (shopFilter) {
    shopAll.addEventListener('change', () => {
      if (shopAll.checked) shopOptions.forEach(option => { option.checked = false; });
      updateShopFilter();
      loadRealtime();
    });
    shopOptions.forEach(option => option.addEventListener('change', () => {
      shopAll.checked = false;
      updateShopFilter();
      loadRealtime();
    }));
    document.addEventListener('click', event => {
      if (shopFilter.open && !shopFilter.contains(event.target)) shopFilter.removeAttribute('open');
    });
  }
  loadRealtime();
  window.setInterval(loadRealtime, 30000);
})();
</script>
<?php endif; ?>

<?php if ($totalShops === 0): ?>
  <div class="mb-6 rounded-2xl border border-warning/30 bg-warning/10 p-5">
    <div class="flex items-start gap-3">
      <span class="material-symbols-outlined text-warning">info</span>
      <div>
        <h3 class="font-bold text-base-content">Belum ada toko yang terhubung</h3>
        <p class="mt-1 text-sm text-base-content/65">Tambahkan toko dari menu Toko untuk mulai mengambil produk dan pesanan.</p>
      </div>
    </div>
  </div>
<?php elseif ($pendingOrderDetails > 0): ?>
  <div class="mb-6 rounded-2xl border border-warning/30 bg-warning/10 p-5">
    <div class="flex items-start gap-3">
      <span class="material-symbols-outlined text-warning">sync_problem</span>
      <div class="min-w-0 flex-1">
        <h3 class="font-bold text-base-content">Data order masih dalam proses sinkronisasi detail</h3>
        <p class="mt-1 text-sm text-base-content/65"><?= number_format($pendingOrderDetails); ?> order sudah terdaftar, tetapi detailnya belum lengkap.</p>
      </div>
      <a href="<?= burl; ?>/panel/orders" class="btn btn-sm btn-warning shrink-0">Buka pesanan</a>
    </div>
  </div>
<?php endif; ?>

<div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
  <div class="rounded-2xl border border-base-content/10 bg-base-100 p-4 shadow-sm">
    <div class="flex items-center justify-between"><span class="text-xs font-bold uppercase tracking-wide text-base-content/50">Toko aktif</span><span class="grid h-9 w-9 place-items-center rounded-xl bg-primary/10 text-primary"><span class="material-symbols-outlined">storefront</span></span></div>
    <div class="mt-4 text-3xl font-black tracking-tight"><?= number_format($connectedShops); ?><span class="ml-1 text-sm font-semibold text-base-content/45">/ <?= number_format($totalShops); ?></span></div>
    <p class="mt-1 text-xs text-base-content/55">Terhubung ke channel</p>
  </div>
  <div class="rounded-2xl border border-base-content/10 bg-base-100 p-4 shadow-sm">
    <div class="flex items-center justify-between"><span class="text-xs font-bold uppercase tracking-wide text-base-content/50">Produk</span><span class="grid h-9 w-9 place-items-center rounded-xl bg-secondary/10 text-secondary"><span class="material-symbols-outlined">inventory_2</span></span></div>
    <div class="mt-4 text-3xl font-black tracking-tight"><?= number_format((int)($summary['total_products'] ?? 0)); ?></div>
    <p class="mt-1 text-xs text-base-content/55">Produk aktif di database</p>
  </div>
  <div class="rounded-2xl border border-base-content/10 bg-base-100 p-4 shadow-sm">
    <div class="flex items-center justify-between"><span class="text-xs font-bold uppercase tracking-wide text-base-content/50">Pesanan</span><span class="grid h-9 w-9 place-items-center rounded-xl bg-info/10 text-info"><span class="material-symbols-outlined">receipt_long</span></span></div>
    <div class="mt-4 text-3xl font-black tracking-tight"><?= number_format((int)($summary['total_orders'] ?? 0)); ?></div>
    <p class="mt-1 text-xs text-base-content/55"><?= number_format((int)($summary['completed_orders'] ?? 0)); ?> selesai</p>
  </div>
  <div class="rounded-2xl border border-base-content/10 bg-base-100 p-4 shadow-sm">
    <div class="flex items-center justify-between"><span class="text-xs font-bold uppercase tracking-wide text-base-content/50">Nilai order selesai</span><span class="grid h-9 w-9 place-items-center rounded-xl bg-success/10 text-success"><span class="material-symbols-outlined">payments</span></span></div>
    <div class="mt-4 truncate text-2xl font-black tracking-tight" title="<?= htmlspecialchars($formatMoney($completedOrderValue)); ?>"><?= htmlspecialchars($formatMoney($completedOrderValue)); ?></div>
    <p class="mt-1 text-xs text-base-content/55">Akumulasi total order berstatus selesai</p>
  </div>
</div>

<section class="mt-6 rounded-2xl border border-error/20 bg-base-100 shadow-sm">
  <div class="flex flex-wrap items-center justify-between gap-3 border-b border-base-content/10 px-5 py-4">
    <div class="flex items-start gap-3"><span class="material-symbols-outlined mt-0.5 text-2xl <?= $stockCriticalCount > 0 ? 'text-error' : 'text-success'; ?>"><?= $stockCriticalCount > 0 ? 'notification_important' : 'check_circle'; ?></span><div><h3 class="font-black text-base-content">Monitoring stok kritis</h3><p class="mt-1 text-xs text-base-content/55"><?= $stockCriticalCount > 0 ? 'Ada produk aktif yang perlu segera diperiksa.' : 'Tidak ada stok aktif di bawah batas 15.'; ?></p></div></div>
    <a href="<?= burl; ?>/panel/products?stock=critical" class="btn btn-sm <?= $stockCriticalCount > 0 ? 'btn-error text-error-content' : 'btn-ghost'; ?>">Lihat produk</a>
  </div>
  <div class="grid grid-cols-1 gap-3 p-5 sm:grid-cols-3">
    <div class="rounded-xl border border-error/20 bg-error/5 p-3"><div class="text-[10px] font-bold uppercase tracking-wide text-error/70">Stok habis</div><div class="mt-1 text-2xl font-black text-error"><?= number_format($stockOutCount); ?></div><div class="text-[11px] text-base-content/55">produk aktif</div></div>
    <div class="rounded-xl border border-warning/20 bg-warning/5 p-3"><div class="text-[10px] font-bold uppercase tracking-wide text-warning/80">Stok 1–14</div><div class="mt-1 text-2xl font-black text-warning-content"><?= number_format($stockLowCount); ?></div><div class="text-[11px] text-base-content/55">produk perlu dipantau</div></div>
    <div class="rounded-xl border border-base-content/10 bg-base-200/40 p-3"><div class="text-[10px] font-bold uppercase tracking-wide text-base-content/50">Total kritis</div><div class="mt-1 text-2xl font-black"><?= number_format($stockCriticalCount); ?></div><div class="text-[11px] text-base-content/55">stok di bawah 15</div></div>
  </div>
  <?php if ($lowStockByShop): ?>
    <div class="grid grid-cols-1 gap-2 border-t border-base-content/10 px-5 py-4 sm:grid-cols-2">
      <?php foreach ($lowStockByShop as $stockShop): if ((int)$stockShop['stock_critical_count'] < 1) continue; ?>
        <a href="<?= burl; ?>/panel/products?shop_id=<?= (int)$stockShop['shop_id']; ?>&stock=critical" class="flex items-center justify-between gap-3 rounded-xl border border-base-content/10 px-3 py-2.5 hover:bg-base-200"><span class="truncate text-xs font-bold"><?= htmlspecialchars($stockShop['shop_name'] ?: 'Toko tanpa nama'); ?></span><span class="shrink-0 text-[11px] text-base-content/60"><strong class="text-error"><?= number_format((int)$stockShop['stock_out_count']); ?></strong> habis · <strong class="text-warning-content"><?= number_format((int)$stockShop['stock_low_count']); ?></strong> kritis</span></a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
  <?php if ($lowStockProducts): ?>
    <div class="border-t border-base-content/10 px-5 py-4">
      <div class="mb-3 flex items-end justify-between gap-3">
        <div><div class="text-[10px] font-bold uppercase tracking-wide text-base-content/45">Produk yang perlu ditangani</div><p class="mt-1 text-xs text-base-content/50">Stok paling rendah, urut dari yang habis.</p></div>
        <a href="<?= burl; ?>/panel/products?stock=critical" class="shrink-0 text-xs font-bold text-primary hover:underline">Lihat semua</a>
      </div>
      <div class="grid min-w-0 gap-2 sm:grid-cols-2">
        <?php foreach ($lowStockProducts as $product): ?>
          <?php $productName = trim(str_replace(['<', '>'], '', (string)$product['name'])); ?>
          <a href="<?= burl; ?>/panel/products?shop_id=<?= (int)$product['shop_id']; ?>&stock=critical&highlight=<?= urlencode($product['id']); ?>" class="flex min-w-0 items-start justify-between gap-3 rounded-xl border border-base-content/10 px-3 py-2.5 transition-colors hover:border-primary/30 hover:bg-base-200">
            <span class="min-w-0 flex-1">
              <span class="block truncate text-xs font-black leading-5 text-base-content"><?= htmlspecialchars($product['shop_name'] ?: 'Toko tanpa nama'); ?></span>
              <span class="mt-0.5 block overflow-hidden text-[11px] font-medium leading-5 text-base-content/65 [display:-webkit-box] [-webkit-box-orient:vertical] [-webkit-line-clamp:2]" title="<?= htmlspecialchars($productName); ?>"><?= htmlspecialchars($productName); ?></span>
            </span>
            <span class="badge <?= (int)$product['total_stock'] === 0 ? 'badge-error' : 'badge-warning'; ?> badge-sm shrink-0"><?= (int)$product['total_stock'] === 0 ? 'Habis' : number_format((int)$product['total_stock']); ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>
</section>

<div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-[1.15fr_0.85fr]">
  <section class="rounded-2xl border border-base-content/10 bg-base-100 shadow-sm">
    <div class="flex items-center justify-between border-b border-base-content/10 px-5 py-4">
      <div><h3 class="font-black text-base-content">Kesehatan toko</h3><p class="mt-1 text-xs text-base-content/55">Status koneksi dan kelengkapan data per toko.</p></div>
      <a href="<?= burl; ?>/panel/shops" class="btn btn-ghost btn-sm">Lihat semua</a>
    </div>
    <?php if (!$shopHealth): ?>
      <div class="p-8 text-center text-sm text-base-content/50">Belum ada data toko.</div>
    <?php else: ?>
      <div class="divide-y divide-base-content/10">
        <?php foreach ($shopHealth as $shop): ?>
          <div class="flex flex-wrap items-center gap-3 px-5 py-4">
            <div class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-base-200 text-primary"><span class="material-symbols-outlined">store</span></div>
            <div class="min-w-0 flex-1"><div class="truncate text-sm font-bold"><?= htmlspecialchars($shop['name'] ?: 'Toko tanpa nama'); ?></div><div class="mt-0.5 truncate text-xs text-base-content/50">@<?= htmlspecialchars($shop['username'] ?: '-'); ?></div></div>
            <div class="text-right"><div class="text-sm font-black"><?= number_format((int)$shop['product_count']); ?> produk</div><div class="text-xs text-base-content/50"><?= number_format((int)$shop['order_count']); ?> order</div></div>
            <span class="badge <?= $shop['sync_status'] === 'connected' ? 'badge-success' : 'badge-warning'; ?> badge-sm"><?= htmlspecialchars($shop['sync_status'] ?: 'unknown'); ?></span>
          </div>
          <?php if ((int)$shop['pending_order_details'] > 0): ?>
            <div class="bg-warning/5 px-5 py-2 text-xs text-warning-content"><span class="font-semibold"><?= number_format((int)$shop['pending_order_details']); ?> order</span> masih menunggu detail.</div>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <section class="rounded-2xl border border-base-content/10 bg-base-100 shadow-sm">
    <div class="flex items-center justify-between border-b border-base-content/10 px-5 py-4"><div><h3 class="font-black text-base-content">Data pelanggan</h3><p class="mt-1 text-xs text-base-content/55">Pelanggan yang sudah terbentuk dari order detail.</p></div><a href="<?= burl; ?>/panel/customers" class="btn btn-ghost btn-sm">Buka pelanggan</a></div>
    <div class="p-5"><div class="text-4xl font-black tracking-tight"><?= number_format((int)($summary['total_customers'] ?? 0)); ?></div><p class="mt-1 text-sm text-base-content/55">Pelanggan tersimpan</p><div class="mt-5 h-2 overflow-hidden rounded-full bg-base-200"><div class="h-full rounded-full bg-primary" style="width: <?= $totalShops > 0 && (int)($summary['total_customers'] ?? 0) > 0 ? '100' : '0'; ?>%"></div></div><p class="mt-2 text-xs text-base-content/50">Sync pelanggan berjalan dari menu Pelanggan.</p></div>
  </section>
</div>

<section class="mt-6 rounded-2xl border border-base-content/10 bg-base-100 shadow-sm">
  <div class="flex items-center justify-between border-b border-base-content/10 px-5 py-4"><div><h3 class="font-black text-base-content">Order terbaru</h3><p class="mt-1 text-xs text-base-content/55">Order yang sudah memiliki detail atau waktu pembuatan.</p></div><a href="<?= burl; ?>/panel/orders" class="btn btn-ghost btn-sm">Buka pesanan</a></div>
  <?php if (!$recentOrders): ?>
    <div class="p-8 text-center text-sm text-base-content/50">Belum ada order detail untuk ditampilkan.</div>
  <?php else: ?>
    <div class="flex items-center gap-2 border-b border-base-content/10 px-5 py-2 text-[11px] text-base-content/50 sm:hidden"><span class="material-symbols-outlined text-sm">swipe</span><span>Geser ke samping untuk melihat kolom lainnya</span></div><div class="overflow-x-auto"><table class="table w-full"><thead><tr><th>Order</th><th>Toko</th><th>Status</th><th>Tanggal</th><th class="text-right">Total</th></tr></thead><tbody>
      <?php foreach ($recentOrders as $order): ?>
        <tr class="hover"><td><div class="font-mono text-xs font-bold"><?= htmlspecialchars($order['order_sn'] ?: $order['id']); ?></div></td><td class="text-xs"><?= htmlspecialchars($order['shop_name'] ?: '-'); ?></td><td><span class="badge <?= $statusClass($order['display_status']); ?> badge-sm"><?= htmlspecialchars($order['display_status']); ?></span></td><td class="whitespace-nowrap text-xs text-base-content/60"><?= htmlspecialchars($formatDate($order['created_at'])); ?></td><td class="whitespace-nowrap text-right text-xs font-bold"><?= htmlspecialchars($formatMoney($order['total_price'] ?? 0)); ?></td></tr>
      <?php endforeach; ?>
    </tbody></table></div>
  <?php endif; ?>
</section>

<script>
(() => {
  const key = 'shopdash-sync-pulse';
  const now = Date.now();
  const lastPulse = Number(sessionStorage.getItem(key) || 0);
  if (now - lastPulse < 30000) return;
  sessionStorage.setItem(key, String(now));
  fetch('<?= burl; ?>/procsync/pulse', { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }, cache: 'no-store' })
    .then(response => response.json())
    .then(payload => {
      if (payload.status !== 'accepted') return;
      const reloadKey = 'shopdash-sync-reload';
      const reloadCount = Number(sessionStorage.getItem(reloadKey) || 0);
      if (reloadCount >= 2) return;
      sessionStorage.setItem(reloadKey, String(reloadCount + 1));
      window.setTimeout(() => window.location.reload(), 10000);
    })
    .catch(() => {});
})();
</script>
