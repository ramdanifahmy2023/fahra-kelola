<?php
$shops = $data['shops'] ?? [];
?>
<section class="space-y-5" id="performance-report">
  <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
    <div>
      <div class="mb-2 flex items-center gap-2 text-[11px] font-bold uppercase tracking-[0.16em] text-primary"><span class="material-symbols-outlined text-base">monitoring</span>Analitik toko</div>
      <h1 class="text-2xl font-black tracking-tight text-base-content sm:text-3xl">Performa toko</h1>
      <p class="mt-1 max-w-2xl text-sm leading-relaxed text-base-content/55">Dibandingkan dengan periode yang sama bulan lalu.</p>
    </div>
    <div class="flex items-center gap-2 text-xs text-base-content/55" id="report-refreshed"><span class="h-2 w-2 rounded-full bg-warning"></span>Memuat data…</div>
  </div>

  <div class="rounded-2xl border border-base-300 bg-base-100 p-4 shadow-sm sm:p-5">
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-[1fr_1fr_1fr_auto] lg:items-end">
      <label class="form-control"><span class="mb-1 text-xs font-bold text-base-content/60">Tanggal akhir</span><input id="report-end-date" type="date" class="input input-bordered input-sm w-full" /></label>
      <label class="form-control"><span class="mb-1 text-xs font-bold text-base-content/60">Urutkan ranking</span><select id="report-sort" class="select select-bordered select-sm w-full"><option value="confirmed_gmv">Penjualan (GMV)</option><option value="confirmed_orders">Pesanan selesai</option><option value="shop_uv">Pengunjung unik</option><option value="product_clicks">Klik produk</option></select></label>
      <label class="form-control"><span class="mb-1 text-xs font-bold text-base-content/60">Filter toko</span><select id="report-shop" class="select select-bordered select-sm w-full"><option value="0">Semua toko</option><?php foreach ($shops as $shop): ?><option value="<?= (int)$shop['id']; ?>"><?= htmlspecialchars($shop['name'] ?: ('Toko #' . $shop['id']), ENT_QUOTES, 'UTF-8'); ?></option><?php endforeach; ?></select></label>
      <button type="button" id="report-refresh" class="btn btn-primary btn-sm min-h-9"><span class="material-symbols-outlined text-base">refresh</span>Perbarui</button>
    </div>
    <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-[11px] text-base-content/50"><span>Periode berjalan: <b id="current-range">—</b></span><span>Periode pembanding: <b id="previous-range">—</b></span></div>
  </div>

  <div id="report-alert" class="hidden rounded-xl border border-warning/30 bg-warning/10 p-3 text-sm text-warning-content"></div>
  <div id="report-loading" class="rounded-2xl border border-base-300 bg-base-100 p-10 text-center text-sm text-base-content/50">Memuat performa toko…</div>
  <div id="report-content" class="hidden space-y-5">
    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
      <article class="rounded-2xl border border-base-300 bg-base-100 p-4 shadow-sm"><div class="flex items-center justify-between text-xs font-bold text-base-content/55">Penjualan <span class="material-symbols-outlined text-primary">payments</span></div><div id="total-gmv" class="mt-2 text-xl font-black">—</div><div id="total-gmv-growth" class="mt-1 text-xs">—</div></article>
      <article class="rounded-2xl border border-base-300 bg-base-100 p-4 shadow-sm"><div class="flex items-center justify-between text-xs font-bold text-base-content/55">Pesanan selesai <span class="material-symbols-outlined text-primary">shopping_bag</span></div><div id="total-orders" class="mt-2 text-xl font-black">—</div><div class="mt-1 text-xs text-base-content/45">periode berjalan</div></article>
      <article class="rounded-2xl border border-base-300 bg-base-100 p-4 shadow-sm"><div class="flex items-center justify-between text-xs font-bold text-base-content/55">Pengunjung unik <span class="material-symbols-outlined text-primary">group</span></div><div id="total-uv" class="mt-2 text-xl font-black">—</div></article>
      <article class="rounded-2xl border border-base-300 bg-base-100 p-4 shadow-sm"><div class="flex items-center justify-between text-xs font-bold text-base-content/55">Klik produk <span class="material-symbols-outlined text-primary">ads_click</span></div><div id="total-clicks" class="mt-2 text-xl font-black">—</div></article>
      <article class="rounded-2xl border border-base-300 bg-base-100 p-4 shadow-sm"><div class="flex items-center justify-between text-xs font-bold text-base-content/55">Konversi <span class="material-symbols-outlined text-primary">trending_up</span></div><div id="total-conversion" class="mt-2 text-xl font-black">—</div><div class="mt-1 text-xs text-base-content/45">pesanan / UV</div></article>
    </div>

    <div class="rounded-2xl border border-base-300 bg-base-100 shadow-sm">
      <div class="flex flex-col gap-2 border-b border-base-300 p-4 sm:flex-row sm:items-center sm:justify-between sm:p-5"><div><h2 class="font-black">Ranking toko</h2></div><div class="text-xs text-base-content/50" id="coverage-note">—</div></div>
      <div class="flex items-center gap-2 border-b border-base-300 px-4 py-2 text-[11px] text-base-content/50 sm:hidden"><span class="material-symbols-outlined text-sm">swipe</span><span>Geser ke samping untuk melihat kolom lainnya</span></div><div class="overflow-x-auto"><table class="table table-sm min-w-[820px]"><thead><tr class="text-[11px] uppercase tracking-wide text-base-content/50"><th>#</th><th>Toko</th><th>Penjualan</th><th>vs bulan lalu</th><th>Pesanan</th><th>UV</th><th>Klik produk</th><th>Konversi</th><th>Data</th></tr></thead><tbody id="report-rows"><tr><td colspan="9" class="py-10 text-center text-sm text-base-content/50">—</td></tr></tbody></table></div>
    </div>

    <div class="rounded-2xl border border-base-300 bg-base-100 shadow-sm">
      <div class="flex flex-col gap-3 border-b border-base-300 p-4 sm:flex-row sm:items-center sm:justify-between sm:p-5"><div><h2 class="font-black">Grafik perbandingan toko</h2></div><label class="form-control w-full sm:w-48"><span class="mb-1 text-[11px] font-bold text-base-content/55">Metrik grafik</span><select id="compare-metric" class="select select-bordered select-sm"><option value="confirmed_gmv">Penjualan</option><option value="confirmed_orders">Pesanan</option><option value="shop_uv">UV</option><option value="product_clicks">Klik produk</option></select></label></div>
      <div class="overflow-x-auto p-3 sm:p-5"><div id="compare-legend" class="mb-3 flex min-h-6 flex-wrap gap-x-4 gap-y-2 text-xs font-semibold"></div><div class="relative min-w-[680px]"><svg id="compare-chart" viewBox="0 0 1000 320" class="h-auto w-full overflow-visible" role="img" aria-label="Grafik perbandingan performa toko"></svg><div id="compare-empty" class="hidden py-12 text-center text-sm text-base-content/50">Belum ada data grafik.</div></div></div>
    </div>

    <div class="rounded-2xl border border-base-300 bg-base-100 shadow-sm"><div class="border-b border-base-300 p-4 sm:p-5"><h2 class="font-black">Perbandingan harian</h2></div><div class="flex items-center gap-2 border-b border-base-300 px-4 py-2 text-[11px] text-base-content/50 sm:hidden"><span class="material-symbols-outlined text-sm">swipe</span><span>Geser ke samping untuk melihat kolom lainnya</span></div><div class="overflow-x-auto"><table class="table table-sm min-w-[640px]"><thead><tr class="text-[11px] uppercase tracking-wide text-base-content/50"><th>Tanggal</th><th>Penjualan</th><th>Pesanan</th><th>UV</th><th>Klik produk</th></tr></thead><tbody id="detail-rows"><tr><td colspan="5" class="py-8 text-center text-sm text-base-content/50">Pilih toko untuk melihat detail.</td></tr></tbody></table></div></div>
  </div>
</section>
<script>
(() => {
  const base = '<?= burl; ?>';
  const today = new Date();
  const dateInput = document.getElementById('report-end-date');
  dateInput.value = new Intl.DateTimeFormat('en-CA', {timeZone: 'Asia/Jakarta'}).format(today);
  const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
  const money = value => new Intl.NumberFormat('id-ID', {style:'currency', currency:'IDR', maximumFractionDigits:0}).format(Number(value || 0));
  const number = value => new Intl.NumberFormat('id-ID', {maximumFractionDigits:0}).format(Number(value || 0));
  const percent = value => value === null || value === undefined || Number.isNaN(Number(value)) ? '—' : `${Number(value).toFixed(1)}%`;
  const signedPercent = value => value === null || value === undefined || Number.isNaN(Number(value)) ? '<span class="text-base-content/45">Belum ada pembanding</span>' : `<span class="${Number(value) >= 0 ? 'text-success' : 'text-error'}">${Number(value) >= 0 ? '+' : ''}${Number(value).toFixed(1)}%</span>`;
  const state = document.getElementById('report-refreshed');
  const alertBox = document.getElementById('report-alert');
  let latestRows = [];
  let latestSeries = [];
  function showAlert(message) { alertBox.textContent = message; alertBox.classList.remove('hidden'); }
  function clearAlert() { alertBox.textContent = ''; alertBox.classList.add('hidden'); }
  function render(payload) {
    clearAlert();
    const totals = payload.totals || {};
    const rows = payload.rows || [];
    latestRows = rows;
    loadCompare();
    document.getElementById('current-range').textContent = `${payload.ranges.current.start} – ${payload.ranges.current.end}`;
    document.getElementById('previous-range').textContent = `${payload.ranges.previous.start} – ${payload.ranges.previous.end}`;
    document.getElementById('total-gmv').textContent = money(totals.confirmed_gmv);
    document.getElementById('total-gmv-growth').innerHTML = signedPercent(totals.growth_percent);
    document.getElementById('total-orders').textContent = number(totals.confirmed_orders);
    document.getElementById('total-uv').textContent = number(totals.shop_uv);
    document.getElementById('total-clicks').textContent = number(totals.product_clicks);
    document.getElementById('total-conversion').textContent = percent(totals.conversion_rate);
    document.getElementById('coverage-note').textContent = rows.length ? `${rows.length} toko · ${payload.comparison_available ? 'pembanding lengkap' : 'pembanding bulan lalu belum lengkap'}` : 'Data belum tersedia';
    if (!payload.comparison_available && rows.length) showAlert('Data pembanding bulan lalu belum lengkap. Perbandingan tersedia setelah data terkumpul.');
    const body = document.getElementById('report-rows');
    body.innerHTML = rows.length ? rows.map(row => `<tr class="hover"><td class="font-black">${row.rank}</td><td><button type="button" data-shop-id="${row.shop_id}" class="report-shop-detail flex min-h-9 items-center gap-2 text-left font-bold hover:text-primary"><span class="grid h-8 w-8 place-items-center rounded-lg bg-primary/10 text-primary"><span class="material-symbols-outlined text-base">storefront</span></span><span>${esc(row.shop_name || ('Toko #' + row.shop_id))}<small class="block text-[10px] font-normal text-base-content/45">${row.session_expired ? 'Sesi perlu diperbarui' : 'Terhubung'}</small></span></button></td><td class="font-semibold">${money(row.current_confirmed_gmv)}</td><td>${signedPercent(row.growth_percent)}</td><td>${number(row.current_confirmed_orders)}</td><td>${number(row.current_shop_uv)}</td><td>${number(row.current_product_clicks)}</td><td>${percent(row.conversion_rate)}</td><td><span class="badge badge-sm ${Number(row.coverage_percent) >= 90 ? 'badge-success' : 'badge-warning'}">${Number(row.coverage_percent).toFixed(0)}%</span></td></tr>`).join('') : '<tr><td colspan="9" class="py-10 text-center text-sm text-base-content/50">Data belum tersedia. Tunggu sinkronisasi berikutnya.</td></tr>';
    document.getElementById('report-loading').classList.add('hidden'); document.getElementById('report-content').classList.remove('hidden');
    state.textContent = '';
  }
  const palette = ['#2f80ed','#f26b45','#60769b','#18a779','#a855f7','#d69e2e','#db2777','#0891b2','#65a30d','#7c3aed'];
  const chartNumber = value => document.getElementById('compare-metric').value === 'confirmed_gmv' ? money(value) : number(value);
  function drawCompare(series) {
    latestSeries = series || [];
    const svg = document.getElementById('compare-chart'); const empty = document.getElementById('compare-empty'); const legend = document.getElementById('compare-legend');
    if (!latestSeries.length) { svg.innerHTML = ''; legend.innerHTML = ''; empty.classList.remove('hidden'); return; }
    empty.classList.add('hidden');
    const metric = document.getElementById('compare-metric').value; const dates = [...new Set(latestSeries.flatMap(item => item.points.map(point => point.date)))].sort();
    const values = latestSeries.flatMap(item => item.points.map(point => Number(point[metric] || 0))); const max = Math.max(...values, 1); const left = 58, right = 18, top = 18, bottom = 38, width = 1000 - left - right, height = 320 - top - bottom;
    const x = index => dates.length > 1 ? left + (index / (dates.length - 1)) * width : left + width / 2; const y = value => top + height - (Number(value || 0) / max) * height;
    const grid = [0, .25, .5, .75, 1].map(step => { const yy = y(max * step); return `<line x1="${left}" x2="${left + width}" y1="${yy}" y2="${yy}" stroke="currentColor" stroke-opacity=".12"/><text x="${left - 10}" y="${yy + 4}" text-anchor="end" font-size="10" fill="currentColor" fill-opacity=".55">${metric === 'confirmed_gmv' ? new Intl.NumberFormat('id-ID',{notation:'compact',maximumFractionDigits:1}).format(max * step) : number(max * step)}</text>`; }).join('');
    const xLabels = dates.filter((date, index) => index === 0 || index === dates.length - 1 || index % Math.max(1, Math.floor(dates.length / 5)) === 0).map(date => { const idx = dates.indexOf(date); return `<text x="${x(idx)}" y="${top + height + 25}" text-anchor="middle" font-size="10" fill="currentColor" fill-opacity=".55">${esc(date.slice(5))}</text>`; }).join('');
    const paths = latestSeries.map((item, seriesIndex) => { const points = item.points.map(point => [x(dates.indexOf(point.date)), y(point[metric])]); const path = points.map((point, index) => `${index ? 'L' : 'M'}${point[0].toFixed(1)},${point[1].toFixed(1)}`).join(' '); const circles = points.map((point, index) => `<circle cx="${point[0]}" cy="${point[1]}" r="3" fill="${palette[seriesIndex % palette.length]}" stroke="var(--color-base-100)" stroke-width="1.5"><title>${esc(item.shop_name)} · ${esc(dates[index])}: ${esc(chartNumber(item.points[index][metric]))}</title></circle>`).join(''); return `<path d="${path}" fill="none" stroke="${palette[seriesIndex % palette.length]}" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>${circles}`; }).join('');
    svg.innerHTML = `<g>${grid}${xLabels}<line x1="${left}" x2="${left}" y1="${top}" y2="${top + height}" stroke="currentColor" stroke-opacity=".16"/><line x1="${left}" x2="${left + width}" y1="${top + height}" y2="${top + height}" stroke="currentColor" stroke-opacity=".16"/>${paths}</g>`;
    legend.innerHTML = latestSeries.map((item, index) => `<span class="inline-flex items-center gap-1.5"><span class="h-2 w-2 rounded-full" style="background:${palette[index % palette.length]}"></span>${esc(item.shop_name)}</span>`).join('');
  }
  async function loadCompare() {
    try { const response = await fetch(`${base}/procreports/compare?end_date=${encodeURIComponent(dateInput.value)}&shop_ids=${encodeURIComponent(document.getElementById('report-shop').value || '')}`, {headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}, cache:'no-store'}); const payload = await response.json(); if (response.ok && payload.status === 'success') drawCompare(payload.series); } catch (error) { drawCompare([]); }
  }
  async function load() {
    const button = document.getElementById('report-refresh'); button.disabled = true; state.innerHTML = '<span class="loading loading-spinner loading-xs"></span>Memuat data…';
    const params = new URLSearchParams({end_date: dateInput.value, sort: document.getElementById('report-sort').value, shop_id: document.getElementById('report-shop').value});
    try { const response = await fetch(`${base}/procreports/summary?${params}`, {headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}, cache:'no-store'}); const payload = await response.json(); if (!response.ok || payload.status !== 'success') throw new Error(payload.message || 'Laporan gagal dimuat.'); render(payload); } catch (error) { document.getElementById('report-loading').classList.add('hidden'); document.getElementById('report-content').classList.remove('hidden'); showAlert(error.message); state.innerHTML = '<span class="h-2 w-2 rounded-full bg-error"></span>Gagal dimuat'; } finally { button.disabled = false; }
  }
  async function detail(shopId) {
    const body = document.getElementById('detail-rows'); body.innerHTML = '<tr><td colspan="5" class="py-8 text-center text-sm text-base-content/50">Memuat detail…</td></tr>';
    try { const response = await fetch(`${base}/procreports/detail?shop_id=${encodeURIComponent(shopId)}&end_date=${encodeURIComponent(dateInput.value)}`, {headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}, cache:'no-store'}); const payload = await response.json(); if (!response.ok || payload.status !== 'success') throw new Error(payload.message || 'Detail gagal dimuat.'); body.innerHTML = payload.rows.length ? payload.rows.map(row => `<tr><td>${esc(row.metric_date)}</td><td>${money(row.confirmed_gmv)}</td><td>${number(row.confirmed_orders)}</td><td>${number(row.shop_uv)}</td><td>${number(row.product_clicks)}</td></tr>`).join('') : '<tr><td colspan="5" class="py-8 text-center text-sm text-base-content/50">Belum ada data harian untuk toko ini.</td></tr>'; } catch (error) { body.innerHTML = `<tr><td colspan="5" class="py-8 text-center text-sm text-error">${esc(error.message)}</td></tr>`; }
  }
  document.getElementById('report-refresh').addEventListener('click', load); document.getElementById('report-sort').addEventListener('change', load); document.getElementById('report-shop').addEventListener('change', load);
  document.getElementById('compare-metric').addEventListener('change', () => drawCompare(latestSeries));
  document.getElementById('report-rows').addEventListener('click', event => { const button = event.target.closest('.report-shop-detail'); if (button) detail(button.dataset.shopId); });
  load();
})();
</script>
