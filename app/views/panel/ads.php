<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
  <div>
    <div class="mb-1 flex items-center gap-2 text-[10px] font-bold uppercase tracking-[0.16em] text-primary"><span class="material-symbols-outlined text-sm">campaign</span>Ads operations</div>
    <h2 class="text-2xl font-black tracking-tight text-base-content">Rekap performa iklan</h2>
    <p class="mt-1 max-w-2xl text-sm text-base-content/60">Perbandingan performa tujuh hari terakhir per toko dan channel Seller Centre.</p>
  </div>
  <button id="ads-refresh" type="button" class="btn btn-sm min-h-11 gap-2 rounded-lg border-base-content/10 bg-base-100"><span class="material-symbols-outlined text-base">refresh</span>Perbarui</button>
</div>

<div id="ads-period" class="mb-4 rounded-xl border border-primary/15 bg-primary/5 p-4 text-sm text-base-content/70">Periode: memuat data, timezone Asia/Jakarta.</div>
<div id="ads-state" class="mb-5 rounded-xl border border-base-content/10 bg-base-100 p-4 text-sm text-base-content/60" role="status" aria-live="polite">Memuat rekap iklan setiap toko...</div>
<div id="ads-session-warning" class="mb-5 hidden rounded-xl border border-warning/30 bg-warning/10 p-4 text-sm text-warning-content" role="alert"></div>
<div id="ads-grid" class="grid grid-cols-1 gap-5 xl:grid-cols-2"></div>

<template id="ads-card-template">
  <article class="overflow-hidden rounded-2xl border border-base-content/10 bg-base-100 shadow-sm">
    <div class="flex flex-wrap items-start justify-between gap-3 border-b border-base-content/10 p-5">
      <div class="min-w-0"><div class="truncate text-base font-black" data-shop-name></div><div class="mt-1 text-xs text-base-content/50" data-shop-sync></div></div>
      <div class="flex flex-wrap items-center gap-2"><button type="button" class="btn btn-ghost btn-xs min-h-9 border border-base-content/10" data-mcp-import>Ambil dari Sniper</button><span class="badge badge-ghost badge-sm" data-status></span></div>
    </div>
    <div class="grid grid-cols-2 gap-4 p-5 sm:grid-cols-4">
      <div><div class="text-[10px] font-bold uppercase tracking-wide text-base-content/45">Kredit iklan</div><div class="mt-1 text-lg font-black" data-credit>-</div></div>
      <div><div class="text-[10px] font-bold uppercase tracking-wide text-base-content/45">Biaya hari ini</div><div class="mt-1 text-lg font-black" data-expense>-</div></div>
      <div><div class="text-[10px] font-bold uppercase tracking-wide text-base-content/45">Channel aktif</div><div class="mt-1 text-lg font-black" data-active>-</div></div>
      <div><div class="text-[10px] font-bold uppercase tracking-wide text-base-content/45">Campaign day</div><div class="mt-1 text-lg font-black" data-campaign-day>-</div></div>
    </div>
    <div class="border-t border-base-content/10 p-5">
      <div class="mb-3 flex flex-wrap items-center justify-between gap-2"><h3 class="text-sm font-black">Total tujuh hari</h3><span class="text-[11px] text-base-content/45" data-performance-period></span></div>
      <div class="grid grid-cols-2 gap-4 sm:grid-cols-4 lg:grid-cols-8">
        <div><div class="text-[10px] font-bold uppercase tracking-wide text-base-content/45">Impression</div><div class="mt-1 text-lg font-black" data-impressions>-</div></div>
        <div><div class="text-[10px] font-bold uppercase tracking-wide text-base-content/45">Klik</div><div class="mt-1 text-lg font-black" data-clicks>-</div></div>
        <div><div class="text-[10px] font-bold uppercase tracking-wide text-base-content/45">CTR</div><div class="mt-1 text-lg font-black" data-ctr>-</div></div>
        <div><div class="text-[10px] font-bold uppercase tracking-wide text-base-content/45">Pesanan</div><div class="mt-1 text-lg font-black" data-orders>-</div></div>
        <div><div class="text-[10px] font-bold uppercase tracking-wide text-base-content/45">Produk terjual</div><div class="mt-1 text-lg font-black" data-items-sold>-</div></div>
        <div><div class="text-[10px] font-bold uppercase tracking-wide text-base-content/45">Penjualan</div><div class="mt-1 text-lg font-black" data-sales>-</div></div>
        <div><div class="text-[10px] font-bold uppercase tracking-wide text-base-content/45">Biaya iklan</div><div class="mt-1 text-lg font-black" data-ad-cost>-</div></div>
        <div><div class="text-[10px] font-bold uppercase tracking-wide text-base-content/45">ROAS</div><div class="mt-1 text-lg font-black" data-roas>-</div></div>
      </div>
    </div>
    <div class="border-t border-base-content/10 p-5">
      <div class="mb-3 flex items-center justify-between"><h3 class="text-sm font-black">Performa per channel</h3><span class="text-[11px] text-base-content/45">Attribution langsung</span></div>
      <div class="overflow-x-auto rounded-lg border border-base-content/10">
        <table class="table table-sm w-full">
          <thead><tr><th>Channel</th><th class="text-right">Impression</th><th class="text-right">Klik</th><th class="text-right">Pesanan</th><th class="text-right">Penjualan</th><th class="text-right">ROAS</th></tr></thead>
          <tbody data-channel-rows><tr><td colspan="6" class="text-center text-xs text-base-content/50">Belum ada data channel.</td></tr></tbody>
        </table>
      </div>
      <div class="mt-4 rounded-lg bg-base-200/60 p-3 text-xs text-base-content/55" data-note>Data channel menunggu response report Seller Centre.</div>
    </div>
  </article>
</template>

<script>
(() => {
  const grid = document.getElementById('ads-grid');
  const state = document.getElementById('ads-state');
  const periodState = document.getElementById('ads-period');
  const refresh = document.getElementById('ads-refresh');
  const template = document.getElementById('ads-card-template');
  if (!grid || !state || !periodState || !refresh || !template) return;
  const integer = value => new Intl.NumberFormat('id-ID', {maximumFractionDigits: 0}).format(Number(value || 0));
  const decimal = value => new Intl.NumberFormat('id-ID', {minimumFractionDigits: 0, maximumFractionDigits: 2}).format(Number(value || 0));
  const money = value => 'Rp ' + integer(value);
  const escapeHtml = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
  const channelLabels = {product_homepage_v2:'Produk', shop_homepage:'Shop', live_stream_homepage:'Live stream'};
  let inFlight = false;

  const formatPeriod = period => {
    if (!period || !period.from || !period.to) return 'Periode tujuh hari terakhir';
    const from = new Date(period.from + 'T00:00:00+07:00').toLocaleDateString('id-ID', {day:'numeric', month:'short', year:'numeric'});
    const to = new Date(period.to + 'T00:00:00+07:00').toLocaleDateString('id-ID', {day:'numeric', month:'short', year:'numeric'});
    return from + ' sampai ' + to + ' WIB';
  };

  function setMetric(node, selector, value, formatter = integer, empty = 'Belum tersedia') {
    node.querySelector(selector).textContent = value === null || value === undefined ? empty : formatter(value);
  }

  function renderChannels(node, channels) {
    const rows = node.querySelector('[data-channel-rows]');
    rows.innerHTML = '';
    const entries = Object.entries(channels || {});
    if (!entries.length) {
      rows.innerHTML = '<tr><td colspan="6" class="text-center text-xs text-base-content/50">Belum ada channel dengan data.</td></tr>';
      return;
    }
    entries.forEach(([key, metrics]) => {
      const row = document.createElement('tr');
      const values = [
        metrics.label || channelLabels[key] || key,
        integer(metrics.impressions),
        integer(metrics.clicks),
        integer(metrics.orders),
        money(metrics.sales),
        metrics.roas === null || metrics.roas === undefined ? 'Belum tersedia' : decimal(metrics.roas) + 'x'
      ];
      values.forEach((value, index) => {
        const cell = document.createElement('td');
        cell.textContent = value;
        if (index > 0) cell.className = 'text-right';
        row.appendChild(cell);
      });
      rows.appendChild(row);
    });
  }

  async function loadAds() {
    if (inFlight) return;
    inFlight = true;
    refresh.disabled = true;
    state.textContent = 'Memuat rekap iklan setiap toko...';
    state.className = 'mb-5 rounded-xl border border-base-content/10 bg-base-100 p-4 text-sm text-base-content/60';
    try {
      const response = await fetch('<?= burl; ?>/procads/summary', {headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}, cache:'no-store'});
      const payload = await response.json();
      if (!response.ok || payload.status !== 'success') throw new Error(payload.message || 'Rekap iklan gagal dimuat.');
      periodState.textContent = 'Periode: ' + formatPeriod(payload.period) + '. Zona waktu Asia/Jakarta.';
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
        const performance = metrics.performance || {};
        const totals = performance.totals || performance;
        const available = Boolean(performance.available && totals);
        const channelData = performance.channels || {};
        node.querySelector('[data-shop-name]').textContent = shop.shop_name || 'Toko tanpa nama';
        const importButton = node.querySelector('[data-mcp-import]');
        importButton.dataset.shopId = shop.shop_id;
        importButton.addEventListener('click', async () => {
          importButton.disabled = true;
          importButton.textContent = 'Mengambil...';
          try {
            const body = new URLSearchParams({shop_id: String(shop.shop_id)});
            const response = await fetch('<?= burl; ?>/procads/mcp', {method:'POST', headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json','Content-Type':'application/x-www-form-urlencoded'}, body});
            const result = await response.json();
            if (!response.ok || result.status !== 'success') throw new Error(result.message || 'History Sniper gagal diimpor.');
            await loadAds();
          } catch (error) {
            state.textContent = error.message || 'History Sniper gagal diimpor.';
            state.className = 'mb-5 rounded-xl border border-error/20 bg-error/5 p-4 text-sm text-error';
          } finally {
            importButton.disabled = false;
            importButton.textContent = 'Ambil dari Sniper';
          }
        });
        node.querySelector('[data-shop-sync]').textContent = shop.synced_at ? 'Diperbarui ' + new Date(shop.synced_at.replace(' ', 'T') + 'Z').toLocaleString('id-ID') : 'Belum pernah disinkronkan';
        const status = node.querySelector('[data-status]');
        if (shop.session_expired || shop.session_status === 'expired' || shop.status === 'expired') { status.textContent = 'Sesi habis'; status.className = 'badge badge-warning badge-sm'; }
        else if (shop.status === 'partial' || performance.partial) { status.textContent = 'Sebagian tersedia'; status.className = 'badge badge-warning badge-sm'; }
        else if (shop.status === 'ok' && !shop.stale) { status.textContent = 'Tersedia'; status.className = 'badge badge-success badge-sm text-white'; }
        else if (shop.status === 'ok') { status.textContent = 'Stale'; status.className = 'badge badge-warning badge-sm'; }
        else if (shop.status === 'error') { status.textContent = 'Gagal'; status.className = 'badge badge-error badge-sm text-white'; }
        else { status.textContent = 'Menunggu'; }
        setMetric(node, '[data-credit]', credit.total, money);
        setMetric(node, '[data-expense]', metrics.ads_expense_today, money);
        setMetric(node, '[data-active]', Object.keys(hasAds).filter(key => hasAds[key]).length);
        node.querySelector('[data-campaign-day]').textContent = campaignDay.is_campaign_day ? 'Ya' : 'Tidak';
        node.querySelector('[data-performance-period]').textContent = available ? formatPeriod(performance.period) : 'Report belum tersedia';
        setMetric(node, '[data-impressions]', available ? totals.impressions : null);
        setMetric(node, '[data-clicks]', available ? totals.clicks : null);
        setMetric(node, '[data-ctr]', available ? totals.ctr : null, decimal, 'Belum tersedia');
        if (available) node.querySelector('[data-ctr]').textContent += '%';
        setMetric(node, '[data-orders]', available ? totals.orders : null);
        setMetric(node, '[data-items-sold]', available ? totals.items_sold : null);
        setMetric(node, '[data-sales]', available ? totals.sales : null, money);
        setMetric(node, '[data-ad-cost]', available ? totals.ad_cost : null, money);
        node.querySelector('[data-roas]').textContent = available && totals.roas !== null && totals.roas !== undefined ? decimal(totals.roas) + 'x' : 'Belum tersedia';
        renderChannels(node, channelData);
        const note = node.querySelector('[data-note]');
        if (shop.session_expired || shop.session_status === 'expired' || shop.status === 'expired') note.textContent = shop.error_message || 'Sesi Shopee toko habis. Perbarui cookie toko.';
        else if (performance.partial) note.textContent = 'Sebagian channel tidak mengembalikan report. ' + Object.values(performance.errors || {}).join(' ');
        else if (!available) note.textContent = shop.error_message || 'Report Seller Centre belum tersedia. Worker akan mencoba lagi pada jadwal berikutnya.';
        else note.textContent = 'Nilai penjualan dan ROAS memakai attribution langsung dari Seller Centre.';
        grid.appendChild(node);
      });
      state.textContent = 'Rekap per toko diperbarui ' + new Date().toLocaleTimeString('id-ID');
    } catch (error) {
      state.textContent = error.message || 'Rekap iklan gagal dimuat.';
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
