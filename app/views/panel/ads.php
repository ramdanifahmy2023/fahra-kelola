<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
  <div>
    <div class="mb-1 flex items-center gap-2 text-[10px] font-bold uppercase tracking-[0.16em] text-primary"><span class="material-symbols-outlined text-sm">campaign</span>Ads operations</div>
    <h2 class="text-2xl font-black tracking-tight text-base-content">Monitoring iklan</h2>
    <p class="mt-1 max-w-2xl text-sm text-base-content/60">Ringkasan performa dan status iklan setiap toko. Data disegarkan berkala dari Seller Centre.</p>
  </div>
  <button id="ads-refresh" type="button" class="btn btn-sm min-h-11 gap-2 rounded-lg border-base-content/10 bg-base-100"><span class="material-symbols-outlined text-base">refresh</span>Perbarui</button>
</div>

<div id="ads-state" class="mb-5 rounded-xl border border-base-content/10 bg-base-100 p-4 text-sm text-base-content/60">Memuat ringkasan iklan setiap toko…</div>
<div id="ads-session-warning" class="mb-5 hidden rounded-xl border border-warning/30 bg-warning/10 p-4 text-sm text-warning-content" role="alert"></div>
<div id="ads-grid" class="grid grid-cols-1 gap-5 xl:grid-cols-2"></div>

<template id="ads-card-template">
  <article class="overflow-hidden rounded-2xl border border-base-content/10 bg-base-100 shadow-sm">
    <div class="flex flex-wrap items-start justify-between gap-3 border-b border-base-content/10 p-5">
      <div class="min-w-0"><div class="truncate text-base font-black" data-shop-name></div><div class="mt-1 text-xs text-base-content/50" data-shop-sync></div></div>
      <span class="badge badge-ghost badge-sm" data-status></span>
    </div>
    <div class="grid grid-cols-2 gap-4 p-5 sm:grid-cols-4">
      <div><div class="text-[10px] font-bold uppercase tracking-wide text-base-content/45">Kredit iklan</div><div class="mt-1 text-lg font-black" data-credit>-</div></div>
      <div><div class="text-[10px] font-bold uppercase tracking-wide text-base-content/45">Biaya hari ini</div><div class="mt-1 text-lg font-black" data-expense>-</div></div>
      <div><div class="text-[10px] font-bold uppercase tracking-wide text-base-content/45">Iklan aktif</div><div class="mt-1 text-lg font-black" data-active>-</div></div>
      <div><div class="text-[10px] font-bold uppercase tracking-wide text-base-content/45">Campaign day</div><div class="mt-1 text-lg font-black" data-campaign-day>-</div></div>
    </div>
    <div class="border-t border-base-content/10 p-5">
      <div class="mb-3 flex items-center justify-between"><h3 class="text-sm font-black">Ringkasan performa hari ini</h3><span class="text-[11px] text-base-content/45" data-performance-period></span></div>
      <div class="grid grid-cols-2 gap-4 sm:grid-cols-4 lg:grid-cols-8">
        <div><div class="text-[10px] font-bold uppercase tracking-wide text-base-content/45">Iklan dilihat</div><div class="mt-1 text-lg font-black" data-impressions>-</div></div>
        <div><div class="text-[10px] font-bold uppercase tracking-wide text-base-content/45">Jumlah klik</div><div class="mt-1 text-lg font-black" data-clicks>-</div></div>
        <div><div class="text-[10px] font-bold uppercase tracking-wide text-base-content/45">Persentase klik</div><div class="mt-1 text-lg font-black" data-ctr>-</div></div>
        <div><div class="text-[10px] font-bold uppercase tracking-wide text-base-content/45">Pesanan</div><div class="mt-1 text-lg font-black" data-orders>-</div></div>
        <div><div class="text-[10px] font-bold uppercase tracking-wide text-base-content/45">Produk terjual</div><div class="mt-1 text-lg font-black" data-items-sold>-</div></div>
        <div><div class="text-[10px] font-bold uppercase tracking-wide text-base-content/45">Penjualan</div><div class="mt-1 text-lg font-black" data-sales>-</div></div>
        <div><div class="text-[10px] font-bold uppercase tracking-wide text-base-content/45">Biaya iklan</div><div class="mt-1 text-lg font-black" data-ad-cost>-</div></div>
        <div><div class="text-[10px] font-bold uppercase tracking-wide text-base-content/45">ROAS</div><div class="mt-1 text-lg font-black" data-roas>-</div></div>
      </div>
    </div>
    <div class="border-t border-base-content/10 p-5">
      <div class="mb-3 flex items-center justify-between"><h3 class="text-sm font-black">Status channel iklan</h3><span class="text-[11px] text-base-content/45" data-source>Meta iklan Shopee</span></div>
      <div class="flex flex-wrap gap-2 text-xs" data-channels></div>
      <div class="mt-4 rounded-lg bg-base-200/60 p-3 text-xs text-base-content/55" data-note>Detail campaign per produk memerlukan discovery campaign dari payload Shopee.</div>
    </div>
  </article>
</template>

<script>
(() => {
  const grid = document.getElementById('ads-grid');
  const state = document.getElementById('ads-state');
  const refresh = document.getElementById('ads-refresh');
  const template = document.getElementById('ads-card-template');
  if (!grid || !state || !template) return;
  const number = value => new Intl.NumberFormat('id-ID').format(Number(value || 0));
  const money = value => 'Rp ' + number(value);
  const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
  const channelLabels = {shop_auto:'Shop auto', shop:'Shop ads', product_manual:'Produk manual', live_stream:'Live stream'};
  let inFlight = false;
  async function loadAds() {
    if (inFlight) return;
    inFlight = true;
    refresh.disabled = true;
    state.textContent = 'Memuat ringkasan iklan setiap toko…';
    state.className = 'mb-5 rounded-xl border border-base-content/10 bg-base-100 p-4 text-sm text-base-content/60';
    try {
      const response = await fetch('<?= burl; ?>/procads/summary', {headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}, cache:'no-store'});
      const payload = await response.json();
      if (!response.ok || payload.status !== 'success') throw new Error(payload.message || 'Ringkasan iklan gagal dimuat.');
      grid.innerHTML = '';
      const shops = payload.shops || [];
      if (!shops.length) {
        state.textContent = 'Belum ada toko terhubung untuk dipantau.';
        return;
      }
      const expiredShops = shops.filter(shop => shop.session_expired || shop.session_status === 'expired' || shop.status === 'expired');
      const warning = document.getElementById('ads-session-warning');
      if (warning) {
        if (expiredShops.length) {
          warning.className = 'mb-5 rounded-xl border border-warning/30 bg-warning/10 p-4 text-sm text-warning-content';
          warning.innerHTML = '<div class="flex flex-wrap items-start gap-3"><span class="material-symbols-outlined text-xl">warning</span><div><strong>Sesi Shopee perlu diperbarui</strong><div class="mt-1">Cookie atau sesi toko berikut sudah tidak valid: <strong>' + expiredShops.map(shop => escapeHtml(shop.shop_name || ('Toko #' + shop.shop_id))).join(', ') + '</strong>.</div><a class="mt-2 inline-flex min-h-11 items-center font-bold underline underline-offset-2" href="<?= burl; ?>/panel/shops">Perbarui cookie toko</a></div></div>';
        } else {
          warning.className = 'mb-5 hidden rounded-xl border border-warning/30 bg-warning/10 p-4 text-sm text-warning-content';
          warning.textContent = '';
        }
      }
      shops.forEach(shop => {
        const node = template.content.cloneNode(true);
        const metrics = shop.metrics || {};
        const credit = metrics.ads_credit || {};
        const hasAds = metrics.has_ads || {};
      const campaignDay = metrics.campaign_day || {};
        const performance = metrics.performance || null;
        node.querySelector('[data-shop-name]').textContent = shop.shop_name || 'Toko tanpa nama';
        node.querySelector('[data-shop-sync]').textContent = shop.synced_at ? 'Diperbarui ' + new Date(shop.synced_at.replace(' ', 'T') + 'Z').toLocaleString('id-ID') : 'Belum pernah disinkronkan';
        const status = node.querySelector('[data-status]');
        if (shop.session_expired || shop.session_status === 'expired' || shop.status === 'expired') { status.textContent = 'Sesi habis'; status.className = 'badge badge-warning badge-sm'; }
        else if (shop.status === 'ok' && !shop.stale) { status.textContent = 'Tersedia'; status.className = 'badge badge-success badge-sm text-white'; }
        else if (shop.status === 'ok') { status.textContent = 'Stale'; status.className = 'badge badge-warning badge-sm'; }
        else if (shop.status === 'error') { status.textContent = 'Gagal'; status.className = 'badge badge-error badge-sm text-white'; }
        else { status.textContent = 'Menunggu'; }
        node.querySelector('[data-credit]').textContent = money(credit.total);
        node.querySelector('[data-expense]').textContent = money(metrics.ads_expense_today);
        const activeChannels = Object.keys(channelLabels).filter(key => hasAds[key]);
        node.querySelector('[data-active]').textContent = number(activeChannels.length);
        node.querySelector('[data-campaign-day]').textContent = campaignDay.is_campaign_day ? 'Ya' : 'Tidak';
        const performanceAvailable = performance && performance.available;
        node.querySelector('[data-performance-period]').textContent = performanceAvailable ? performance.period : 'Metrik report belum tersedia';
        node.querySelector('[data-impressions]').textContent = performanceAvailable ? number(performance.impressions) : '—';
        node.querySelector('[data-clicks]').textContent = performanceAvailable ? number(performance.clicks) : '—';
        node.querySelector('[data-ctr]').textContent = performanceAvailable ? number(performance.ctr) + '%' : '—';
        node.querySelector('[data-orders]').textContent = performanceAvailable ? number(performance.orders) : '—';
        node.querySelector('[data-items-sold]').textContent = performanceAvailable ? number(performance.items_sold) : '—';
        node.querySelector('[data-sales]').textContent = performanceAvailable ? money(performance.sales) : '—';
        node.querySelector('[data-ad-cost]').textContent = performanceAvailable ? money(performance.ad_cost) : money(metrics.ads_expense_today);
        node.querySelector('[data-roas]').textContent = performanceAvailable && performance.roas !== null ? Number(performance.roas).toLocaleString('id-ID', {maximumFractionDigits:2}) + 'x' : '—';
        const channels = node.querySelector('[data-channels]');
        channels.innerHTML = activeChannels.length ? activeChannels.map(key => '<span class="badge badge-outline badge-sm">' + channelLabels[key] + '</span>').join('') : '<span class="text-base-content/45">Tidak ada channel iklan aktif pada payload.</span>';
        if (shop.session_expired || shop.session_status === 'expired' || shop.status === 'expired') node.querySelector('[data-note]').textContent = escapeHtml(shop.error_message || 'Sesi Shopee toko habis. Perbarui cookie toko.');
        else if (shop.status === 'error') node.querySelector('[data-note]').textContent = escapeHtml(shop.error_message || 'Data iklan toko tidak tersedia. Periksa sesi toko.');
        else if (!performanceAvailable) node.querySelector('[data-note]').textContent = 'Biaya iklan memakai ringkasan harian. Metrik impression, klik, pesanan, penjualan, dan ROAS menunggu response report Seller Centre.';
        grid.appendChild(node);
      });
      state.textContent = 'Ringkasan per toko diperbarui ' + new Date().toLocaleTimeString('id-ID');
    } catch (error) {
      state.textContent = error.message || 'Ringkasan iklan gagal dimuat.';
      state.className = 'mb-5 rounded-xl border border-error/20 bg-error/5 p-4 text-sm text-error';
    } finally {
      inFlight = false;
      refresh.disabled = false;
    }
  }
  refresh.addEventListener('click', loadAds);
  loadAds();
  window.setInterval(loadAds, 300000);
})();
</script>
