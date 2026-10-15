(() => {
  const root = document.getElementById('performance-report');
  if (!root) return;
  const $ = id => document.getElementById(id);
  const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const known = value => value !== null && value !== undefined && value !== '' && Number.isFinite(Number(value));
  const number = value => known(value) ? new Intl.NumberFormat('id-ID', {maximumFractionDigits:0}).format(value) : 'Belum tersedia';
  const money = value => known(value) ? 'Rp ' + number(value) : 'Belum tersedia';
  const percent = value => known(value) ? new Intl.NumberFormat('id-ID', {maximumFractionDigits:1}).format(value) + '%' : 'Belum dapat dihitung';
  const format = (metric, value) => metric === 'confirmed_gmv' ? money(value) : number(value);
  const labels = {confirmed_gmv:'Penjualan', confirmed_orders:'Pesanan terkonfirmasi', shop_uv:'Pengunjung unik', product_clicks:'Klik produk'};
  const date = value => new Date(value + 'T00:00:00+07:00').toLocaleDateString('id-ID', {day:'numeric',month:'short',year:'numeric',timeZone:'Asia/Jakarta'});
  const rangeText = range => `${date(range.start)} – ${date(range.end)}`;
  const coverage = (item, metric) => {
    const info = item.coverage?.current?.[metric];
    if (!info || !info.known_days) return 'Belum tersedia';
    return info.complete ? 'Data lengkap' : `Sebagian · ${info.known_days}/${info.expected_days} data harian`;
  };
  const metricValue = (row, metric) => `<strong>${esc(format(metric, row['current_' + metric]))}</strong>${known(row['current_' + metric]) && !row.coverage?.current?.[metric]?.complete ? `<small>${esc(coverage(row, metric))}</small>` : ''}`;
  const growth = value => known(value) ? `${Number(value) > 0 ? '+' : ''}${percent(value)}` : 'Belum dapat dibandingkan';
  const restored = new URLSearchParams(location.search);
  $('report-end-date').value = /^\d{4}-\d{2}-\d{2}$/.test(restored.get('end_date') || '') ? restored.get('end_date') : new Intl.DateTimeFormat('en-CA',{timeZone:'Asia/Jakarta'}).format(new Date());
  for (const [key, id] of Object.entries({sort:'report-sort', metric:'compare-metric'})) if ([...$(id).options].some(o => o.value === restored.get(key))) $(id).value = restored.get(key);
  let sequence = 0, compareSequence = 0, detailSequence = 0, latestSeries = [], latestRows = [], activeRange, detailShop, applied;
  const params = () => ({end_date:$('report-end-date').value, sort:$('report-sort').value, shop_id:$('report-shop').value});
  const persist = () => {
    const url = new URL(location.href);
    Object.entries({...params(),metric:$('compare-metric').value}).forEach(([key,value]) => url.searchParams.set(key,value));
    history.replaceState(null,'',url);
  };
  async function request(action, values) {
    const response = await fetch(`${root.dataset.base}/procreports/${action}?${new URLSearchParams(values)}`, {headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'},cache:'no-store'});
    let result;
    try { result = await response.json(); } catch (_) { throw new Error('Respons laporan belum dapat dibaca. Muat ulang halaman jika sesi sudah berakhir.'); }
    if (!response.ok || result.status !== 'success') throw new Error(result.message || 'Data belum dapat dimuat. Coba lagi.');
    return result;
  }
  root.querySelector('.shop-picker-list').addEventListener('click', event => {
    const option = event.target.closest('a[data-product-shop]');
    if (!option) return;
    event.preventDefault();
    const picker = option.closest('.shop-picker'), trigger = picker.querySelector('button');
    const arrow = trigger.lastElementChild.cloneNode(true);
    trigger.replaceChildren(...Array.from(option.children, child => child.cloneNode(true)), arrow);
    trigger.children[1].className = 'min-w-0 flex-1 truncate text-left';
    trigger.setAttribute('aria-label','Pilih toko: ' + trigger.children[1].textContent);
    picker.querySelectorAll('a').forEach(link => { link.classList.toggle('bg-base-200',link === option); if(link === option) link.setAttribute('aria-current','page'); else link.removeAttribute('aria-current'); });
    $('report-shop').value = option.dataset.productShop;
    picker.classList.remove('dropdown-open');
    picker.querySelector('.shop-picker-list').hidden = true;
    trigger.setAttribute('aria-expanded','false');
    trigger.focus();
    $('report-shop').dispatchEvent(new Event('change',{bubbles:true}));
  });
  function render(payload) {
    activeRange = payload.ranges.current;
    const rows = payload.rows || [], totals = payload.totals || {};
    latestRows = rows;
    $('current-range').textContent = rangeText(activeRange);
    $('previous-range').textContent = rangeText(payload.ranges.previous);
    for (const [id,metric] of Object.entries({gmv:'confirmed_gmv',orders:'confirmed_orders',uv:'shop_uv',clicks:'product_clicks'})) {
      $('total-' + id).textContent = format(metric,totals[metric]);
      $('total-' + id).classList.toggle('is-unknown',!known(totals[metric]));
      $('total-' + id + '-coverage').textContent = coverage(totals,metric);
    }
    $('total-gmv-growth').textContent = growth(totals.growth_percent);
    $('total-conversion').textContent = percent(totals.conversion_rate);
    $('total-conversion').classList.toggle('is-unknown',!known(totals.conversion_rate));
    $('total-conversion-coverage').textContent = 'Dihitung jika data pesanan dan UV lengkap.';
    $('coverage-note').textContent = rows.length ? `${rows.length} toko · urutan mengikuti angka yang tersedia` : 'Belum ada toko';
    const partial = rows.some(row => Object.keys(labels).some(metric => !row.coverage?.current?.[metric]?.complete));
    $('report-alert').hidden = !partial;
    $('report-alert').textContent = 'Data belum lengkap. Total hanya menjumlahkan data yang tersedia.';
    const identity = row => `<span class="report-shop-logo" data-logo-shop="${Number(row.shop_id)}"></span><span>${esc(row.shop_name)}${row.session_expired ? '<small>Sesi perlu diperbarui</small>' : ''}</span>`;
    $('report-rows').innerHTML = rows.length ? rows.map(row => `<tr><td>${row.rank}</td><th scope="row"><button type="button" class="report-shop-detail" data-shop-id="${Number(row.shop_id)}">${identity(row)}</button></th><td>${metricValue(row,'confirmed_gmv')}</td><td>${esc(growth(row.growth_percent))}</td><td>${metricValue(row,'confirmed_orders')}</td><td>${metricValue(row,'shop_uv')}</td><td>${metricValue(row,'product_clicks')}</td><td>${esc(percent(row.conversion_rate))}</td></tr>`).join('') : '<tr><td colspan="8">Belum ada toko untuk ditampilkan.</td></tr>';
    const selected = applied.sort;
    $('report-mobile-rows').innerHTML = rows.length ? rows.map(row => `<article><div class="report-shop-identity"><span class="report-rank">${row.rank}</span>${identity(row)}</div><div class="report-selected-metric"><span>${esc(labels[selected])}</span>${metricValue(row,selected)}</div><details><summary>Rincian metrik</summary><dl>${Object.keys(labels).filter(metric => metric !== selected).map(metric => `<div><dt>${esc(labels[metric])}</dt><dd>${metricValue(row,metric)}</dd></div>`).join('')}<div><dt>Perubahan penjualan</dt><dd>${esc(growth(row.growth_percent))}</dd></div><div><dt>Konversi</dt><dd>${esc(percent(row.conversion_rate))}</dd></div></dl><button type="button" class="btn report-shop-detail" data-shop-id="${Number(row.shop_id)}">Lihat per tanggal</button></details></article>`).join('') : '<p class="report-empty">Belum ada toko untuk ditampilkan.</p>';
    root.querySelectorAll('[data-logo-shop]').forEach(el => window.renderShopLogo?.(el,el.dataset.logoShop));
    $('report-content').hidden = false;
  }
  function drawCompare() {
    const metric = $('compare-metric').value;
    const dates = [];
    if (activeRange) for (let d = new Date(activeRange.start+'T00:00:00Z'), end = new Date(activeRange.end+'T00:00:00Z'); d <= end; d.setUTCDate(d.getUTCDate()+1)) dates.push(d.toISOString().slice(0,10));
    const values = latestSeries.flatMap(s => s.points.map(p => p[metric])).filter(known).map(Number);
    $('compare-empty').hidden = values.length > 0;
    const max = Math.max(...values,1), left = 68, top = 16, width = 710, height = 220;
    const x = i => left + (dates.length > 1 ? i / (dates.length-1) : .5) * width;
    const y = value => top + height - Number(value) / max * height;
    const palette = ['var(--color-primary)','var(--color-info)','var(--color-success)','var(--color-base-content)'];
    const grid = [0,.5,1].map(n => `<line x1="${left}" x2="778" y1="${y(max*n)}" y2="${y(max*n)}" stroke="currentColor" opacity=".15"/><text x="60" y="${y(max*n)+4}" text-anchor="end">${esc(new Intl.NumberFormat('id-ID',{notation:'compact',maximumFractionDigits:1}).format(max*n))}</text>`).join('');
    const paths = latestSeries.map((series,index) => {
      const points = new Map(series.points.map(p => [p.date,p[metric]]));
      let path = '', connected = false, circles = '';
      dates.forEach((d,i) => { const value = points.get(d); if (!known(value)) { connected = false; return; } path += `${connected?'L':'M'}${x(i)},${y(value)} `; connected = true; circles += `<circle cx="${x(i)}" cy="${y(value)}" r="3"><title>${esc(series.shop_name)} · ${esc(date(d))}: ${esc(format(metric,value))}</title></circle>`; });
      return `<g fill="${palette[index%4]}" stroke="${palette[index%4]}"><path d="${path}" fill="none" stroke-width="2" ${index>=4?'stroke-dasharray="5 4"':''}/>${circles}</g>`;
    }).join('');
    const ticks = dates.filter((d,i) => i===0 || i===dates.length-1 || i===Math.floor(dates.length/2)).map(d => `<text x="${x(dates.indexOf(d))}" y="263" text-anchor="middle">${esc(d.slice(8))}/${esc(d.slice(5,7))}</text>`).join('');
    $('compare-chart').innerHTML = values.length ? grid + paths + ticks : '';
    $('compare-chart').hidden = !values.length;
    $('compare-legend').innerHTML = latestSeries.map((s,i) => `<span><i style="background:${palette[i%4]}" aria-hidden="true"></i>${esc(s.shop_name)}${i>=4?' · garis putus':''}</span>`).join('');
    $('compare-values').innerHTML = latestSeries.map(s => {const points = new Map(s.points.map(p => [p.date,p[metric]]));return `<details><summary>${esc(s.shop_name)}</summary><dl>${dates.map(d => `<div><dt>${esc(date(d))}</dt><dd>${esc(format(metric,points.get(d)))}</dd></div>`).join('')}</dl></details>`;}).join('') || '<p>Belum ada data harian.</p>';
  }
  async function loadCompare() {
    const token = ++compareSequence, scope = {...applied};
    latestSeries = []; drawCompare(); $('compare-status').textContent = 'Memuat grafik…'; $('compare-retry').hidden = true;
    try {
      const payload = await request('compare',{end_date:scope.end_date,shop_ids:scope.shop_id});
      if (token !== compareSequence) return;
      latestSeries = payload.series || []; drawCompare(); $('compare-status').textContent = '';
    } catch (error) { if (token !== compareSequence) return; $('compare-status').textContent = error.message; $('compare-retry').hidden = false; }
  }
  async function load() {
    const token = ++sequence; ++compareSequence; ++detailSequence;
    applied = params(); persist();
    $('report-refresh').disabled = true; $('report-refreshed').textContent = 'Memuat data…';
    $('report-content').hidden = true; $('report-alert').hidden = true; $('report-loading').hidden = false; $('report-daily').hidden = true;
    $('current-range').textContent = 'Memuat…'; $('previous-range').textContent = 'Memuat…';
    try {
      const payload = await request('summary',applied);
      if (token !== sequence) return;
      render(payload); $('report-refreshed').textContent = 'Data tersimpan · bukan sinkronisasi baru'; loadCompare();
    } catch (error) {
      if (token !== sequence) return;
      $('report-alert').textContent = error.message + ' Tekan Muat ulang data untuk mencoba lagi.';
      $('report-alert').hidden = false; $('report-refreshed').textContent = 'Gagal dimuat';
      $('current-range').textContent = 'Belum dimuat'; $('previous-range').textContent = 'Belum dimuat';
    } finally { if (token === sequence) { $('report-loading').hidden = true; $('report-refresh').disabled = false; } }
  }
  async function detail(shopId, focus = true) {
    const token = ++detailSequence; detailShop = shopId;
    $('report-daily').hidden = false; $('detail-retry').hidden = true;
    const shop = latestRows.find(row => Number(row.shop_id) === Number(shopId));
    $('report-daily-title').textContent = 'Rincian harian · ' + (shop?.shop_name || 'Toko');
    $('detail-rows').innerHTML = '<tr><td colspan="5">Memuat rincian…</td></tr>';
    if (focus) $('report-daily-title').focus();
    try {
      const payload = await request('detail',{shop_id:shopId,end_date:applied.end_date});
      if (token !== detailSequence) return;
      const byDate = new Map(payload.rows.map(row => [row.metric_date,row]));
      const days = [];
      for (let d = new Date(payload.ranges.previous.start+'T00:00:00Z'), end = new Date(payload.ranges.current.end+'T00:00:00Z'); d <= end; d.setUTCDate(d.getUTCDate()+1)) days.push(d.toISOString().slice(0,10));
      $('detail-caption').textContent = rangeText(payload.ranges.previous) + ' dan ' + rangeText(payload.ranges.current);
      $('detail-rows').innerHTML = days.map(d => {const row = byDate.get(d) || {};return `<tr><th scope="row">${esc(date(d))}</th>${Object.entries(labels).map(([metric,label]) => `<td data-label="${esc(label)}">${esc(format(metric,row[metric]))}</td>`).join('')}</tr>`;}).join('');
    } catch (error) { if (token !== detailSequence) return; $('detail-rows').innerHTML = `<tr><td colspan="5">${esc(error.message)}</td></tr>`; $('detail-retry').hidden = false; }
  }
  $('report-filters').addEventListener('submit', event => { event.preventDefault(); load(); });
  for (const id of ['report-sort','report-shop','report-end-date']) $(id).addEventListener('change', () => { if ($('report-filters').reportValidity()) load(); });
  $('compare-metric').addEventListener('change', () => { persist(); drawCompare(); });
  $('compare-retry').addEventListener('click',loadCompare);
  $('detail-retry').addEventListener('click',() => detail(detailShop,false));
  root.addEventListener('click',event => { const button=event.target.closest('.report-shop-detail'); if(button) detail(button.dataset.shopId); });
  load();
})();
