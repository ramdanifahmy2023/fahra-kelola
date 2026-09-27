<?php
$shops = $data['shops'] ?? [];
?>
<section class="space-y-5" id="performance-report">
  <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
    <div>
      <div class="mb-2 flex items-center gap-2 text-[11px] font-bold uppercase tracking-[0.16em] text-primary"><span class="material-symbols-outlined text-base">monitoring</span>Analitik toko</div>
      <h1 class="text-2xl font-black tracking-tight text-base-content sm:text-3xl">Performa toko</h1>
      <p class="mt-1 max-w-2xl text-sm leading-relaxed text-base-content/55">Bandingkan performa bulan berjalan dengan periode yang sama di bulan sebelumnya. Data diperbarui oleh sinkronisasi background.</p>
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
    <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-[11px] text-base-content/50"><span>Periode berjalan: <b id="current-range">—</b></span><span>Periode pembanding: <b id="previous-range">—</b></span><span class="inline-flex items-center gap-1"><span class="h-1.5 w-1.5 rounded-full bg-success"></span>Snapshot lokal</span></div>
  </div>

  <div id="report-alert" class="hidden rounded-xl border border-warning/30 bg-warning/10 p-3 text-sm text-warning-content"></div>
  <div id="report-loading" class="rounded-2xl border border-base-300 bg-base-100 p-10 text-center text-sm text-base-content/50">Memuat performa toko…</div>
  <div id="report-content" class="hidden space-y-5">
    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
      <article class="rounded-2xl border border-base-300 bg-base-100 p-4 shadow-sm"><div class="flex items-center justify-between text-xs font-bold text-base-content/55">Penjualan <span class="material-symbols-outlined text-primary">payments</span></div><div id="total-gmv" class="mt-2 text-xl font-black">—</div><div id="total-gmv-growth" class="mt-1 text-xs">—</div></article>
      <article class="rounded-2xl border border-base-300 bg-base-100 p-4 shadow-sm"><div class="flex items-center justify-between text-xs font-bold text-base-content/55">Pesanan selesai <span class="material-symbols-outlined text-primary">shopping_bag</span></div><div id="total-orders" class="mt-2 text-xl font-black">—</div><div class="mt-1 text-xs text-base-content/45">periode berjalan</div></article>
      <article class="rounded-2xl border border-base-300 bg-base-100 p-4 shadow-sm"><div class="flex items-center justify-between text-xs font-bold text-base-content/55">Pengunjung unik <span class="material-symbols-outlined text-primary">group</span></div><div id="total-uv" class="mt-2 text-xl font-black">—</div><div class="mt-1 text-xs text-base-content/45">shop UV</div></article>
      <article class="rounded-2xl border border-base-300 bg-base-100 p-4 shadow-sm"><div class="flex items-center justify-between text-xs font-bold text-base-content/55">Klik produk <span class="material-symbols-outlined text-primary">ads_click</span></div><div id="total-clicks" class="mt-2 text-xl font-black">—</div><div class="mt-1 text-xs text-base-content/45">product clicks</div></article>
      <article class="rounded-2xl border border-base-300 bg-base-100 p-4 shadow-sm"><div class="flex items-center justify-between text-xs font-bold text-base-content/55">Konversi <span class="material-symbols-outlined text-primary">trending_up</span></div><div id="total-conversion" class="mt-2 text-xl font-black">—</div><div class="mt-1 text-xs text-base-content/45">pesanan / UV</div></article>
    </div>

    <div class="rounded-2xl border border-base-300 bg-base-100 shadow-sm">
      <div class="flex flex-col gap-2 border-b border-base-300 p-4 sm:flex-row sm:items-center sm:justify-between sm:p-5"><div><h2 class="font-black">Ranking toko</h2><p class="mt-1 text-xs text-base-content/50">Peringkat dihitung dari metrik yang dipilih.</p></div><div class="text-xs text-base-content/50" id="coverage-note">—</div></div>
      <div class="overflow-x-auto"><table class="table table-sm min-w-[820px]"><thead><tr class="text-[11px] uppercase tracking-wide text-base-content/50"><th>#</th><th>Toko</th><th>Penjualan</th><th>vs bulan lalu</th><th>Pesanan</th><th>UV</th><th>Klik produk</th><th>Konversi</th><th>Data</th></tr></thead><tbody id="report-rows"><tr><td colspan="9" class="py-10 text-center text-sm text-base-content/50">—</td></tr></tbody></table></div>
    </div>

    <div class="rounded-2xl border border-base-300 bg-base-100 shadow-sm"><div class="border-b border-base-300 p-4 sm:p-5"><h2 class="font-black">Perbandingan harian</h2><p class="mt-1 text-xs text-base-content/50">Pilih toko pada ranking untuk melihat snapshot harian.</p></div><div class="overflow-x-auto"><table class="table table-sm min-w-[640px]"><thead><tr class="text-[11px] uppercase tracking-wide text-base-content/50"><th>Tanggal</th><th>Penjualan</th><th>Pesanan</th><th>UV</th><th>Klik produk</th></tr></thead><tbody id="detail-rows"><tr><td colspan="5" class="py-8 text-center text-sm text-base-content/50">Pilih toko untuk melihat detail.</td></tr></tbody></table></div></div>
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
  function showAlert(message) { alertBox.textContent = message; alertBox.classList.remove('hidden'); }
  function clearAlert() { alertBox.textContent = ''; alertBox.classList.add('hidden'); }
  function render(payload) {
    clearAlert();
    const totals = payload.totals || {};
    const rows = payload.rows || [];
    latestRows = rows;
    document.getElementById('current-range').textContent = `${payload.ranges.current.start} – ${payload.ranges.current.end}`;
    document.getElementById('previous-range').textContent = `${payload.ranges.previous.start} – ${payload.ranges.previous.end}`;
    document.getElementById('total-gmv').textContent = money(totals.confirmed_gmv);
    document.getElementById('total-gmv-growth').innerHTML = signedPercent(totals.growth_percent);
    document.getElementById('total-orders').textContent = number(totals.confirmed_orders);
    document.getElementById('total-uv').textContent = number(totals.shop_uv);
    document.getElementById('total-clicks').textContent = number(totals.product_clicks);
    document.getElementById('total-conversion').textContent = percent(totals.conversion_rate);
    document.getElementById('coverage-note').textContent = rows.length ? `${rows.length} toko · ${payload.comparison_available ? 'pembanding lengkap' : 'pembanding bulan lalu belum lengkap'}` : 'Belum ada snapshot performa';
    if (!payload.comparison_available && rows.length) showAlert('Data performa berjalan sudah tersedia. Endpoint Shopee yang direkam hanya memberi rolling 30 hari, jadi perbandingan bulan lalu akan aktif setelah snapshot periode tersebut terkumpul.');
    const body = document.getElementById('report-rows');
    body.innerHTML = rows.length ? rows.map(row => `<tr class="hover"><td class="font-black">${row.rank}</td><td><button type="button" data-shop-id="${row.shop_id}" class="report-shop-detail flex min-h-9 items-center gap-2 text-left font-bold hover:text-primary"><span class="grid h-8 w-8 place-items-center rounded-lg bg-primary/10 text-primary"><span class="material-symbols-outlined text-base">storefront</span></span><span>${esc(row.shop_name || ('Toko #' + row.shop_id))}<small class="block text-[10px] font-normal text-base-content/45">${row.session_expired ? 'Sesi perlu diperbarui' : 'Terhubung'}</small></span></button></td><td class="font-semibold">${money(row.current_confirmed_gmv)}</td><td>${signedPercent(row.growth_percent)}</td><td>${number(row.current_confirmed_orders)}</td><td>${number(row.current_shop_uv)}</td><td>${number(row.current_product_clicks)}</td><td>${percent(row.conversion_rate)}</td><td><span class="badge badge-sm ${Number(row.coverage_percent) >= 90 ? 'badge-success' : 'badge-warning'}">${Number(row.coverage_percent).toFixed(0)}%</span></td></tr>`).join('') : '<tr><td colspan="9" class="py-10 text-center text-sm text-base-content/50">Belum ada data. Worker akan mengisi setelah sinkronisasi performa berjalan.</td></tr>';
    document.getElementById('report-loading').classList.add('hidden'); document.getElementById('report-content').classList.remove('hidden');
    state.innerHTML = '<span class="h-2 w-2 rounded-full bg-success"></span>Snapshot siap';
  }
  async function load() {
    const button = document.getElementById('report-refresh'); button.disabled = true; state.innerHTML = '<span class="loading loading-spinner loading-xs"></span>Mengambil snapshot…';
    const params = new URLSearchParams({end_date: dateInput.value, sort: document.getElementById('report-sort').value, shop_id: document.getElementById('report-shop').value});
    try { const response = await fetch(`${base}/procreports/summary?${params}`, {headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}, cache:'no-store'}); const payload = await response.json(); if (!response.ok || payload.status !== 'success') throw new Error(payload.message || 'Laporan gagal dimuat.'); render(payload); } catch (error) { document.getElementById('report-loading').classList.add('hidden'); document.getElementById('report-content').classList.remove('hidden'); showAlert(error.message); state.innerHTML = '<span class="h-2 w-2 rounded-full bg-error"></span>Gagal dimuat'; } finally { button.disabled = false; }
  }
  async function detail(shopId) {
    const body = document.getElementById('detail-rows'); body.innerHTML = '<tr><td colspan="5" class="py-8 text-center text-sm text-base-content/50">Memuat detail…</td></tr>';
    try { const response = await fetch(`${base}/procreports/detail?shop_id=${encodeURIComponent(shopId)}&end_date=${encodeURIComponent(dateInput.value)}`, {headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}, cache:'no-store'}); const payload = await response.json(); if (!response.ok || payload.status !== 'success') throw new Error(payload.message || 'Detail gagal dimuat.'); body.innerHTML = payload.rows.length ? payload.rows.map(row => `<tr><td>${esc(row.metric_date)}</td><td>${money(row.confirmed_gmv)}</td><td>${number(row.confirmed_orders)}</td><td>${number(row.shop_uv)}</td><td>${number(row.product_clicks)}</td></tr>`).join('') : '<tr><td colspan="5" class="py-8 text-center text-sm text-base-content/50">Belum ada data harian untuk toko ini.</td></tr>'; } catch (error) { body.innerHTML = `<tr><td colspan="5" class="py-8 text-center text-sm text-error">${esc(error.message)}</td></tr>`; }
  }
  document.getElementById('report-refresh').addEventListener('click', load); document.getElementById('report-sort').addEventListener('change', load); document.getElementById('report-shop').addEventListener('change', load);
  document.getElementById('report-rows').addEventListener('click', event => { const button = event.target.closest('.report-shop-detail'); if (button) detail(button.dataset.shopId); });
  load();
})();
</script>
