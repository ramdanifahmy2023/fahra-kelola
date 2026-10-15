(() => {
  const root = document.getElementById('ads-monitor');
  if (!root) return;
  const grid = root.querySelector('#ads-grid');
  const state = root.querySelector('#ads-state');
  const refresh = root.querySelector('#ads-refresh');
  const syncButton = root.querySelector('#ads-sync');
  const syncStatus = root.querySelector('#ads-sync-status');
  const period = root.querySelector('#ads-period');
  const channel = root.querySelector('#ads-channel');
  const shopFilter = root.querySelector('#ads-shop');
  const topupState = root.querySelector('#ads-topups-state');
  const topupRows = root.querySelector('#ads-topups-rows');
  const topupUpdated = root.querySelector('[data-topups-updated]');
  const topupShop = root.querySelector('#ads-topup-shop');
  const syncShopPicker = window.enhanceShopSelect(shopFilter);
  const syncTopupPicker = window.enhanceShopSelect(topupShop);
  const topupPeriod = root.querySelector('#ads-topup-period');
  const topupCustomRange = root.querySelector('#ads-topup-custom-range');
  const topupStart = root.querySelector('#ads-topup-start');
  const topupEnd = root.querySelector('#ads-topup-end');
  const topupPeriodNote = root.querySelector('#ads-topups-period-note');
  const saved=new URLSearchParams(location.search);
  for(const [key,control] of Object.entries({period,channel,topup_period:topupPeriod,topup_start:topupStart,topup_end:topupEnd}))if(saved.has(key))control.value=saved.get(key);
  let initialShop=saved.get('shop_id')||'',initialTopup=saved.get('topup_shop')||'';
  if(initialTopup==='0')initialTopup='';if(initialShop==='0')initialShop='';
  const warning = root.querySelector('#ads-session-warning');
  const template = document.getElementById('ads-card-template');
  const periodLabels = {daily: 'Hari ini', weekly: 'Minggu berjalan', monthly: 'Bulan berjalan'};
  const channelLabels = {shop_auto: 'Toko otomatis', shop: 'Toko', product_manual: 'Produk', live_stream: 'Live'};
  const fields = ['impressions', 'clicks', 'ctr', 'orders', 'items_sold', 'sales', 'ad_cost', 'roas'];
  const selectors = ['impressions', 'clicks', 'ctr', 'orders', 'items-sold', 'sales', 'ad-cost', 'roas'];
  const numeric = value => value !== null && value !== undefined && value !== '' && Number.isFinite(Number(value));
  const number = (value, digits = 0) => numeric(value) ? Number(value).toLocaleString('id-ID', {maximumFractionDigits: digits}) : '-';
  const money = value => numeric(value) ? 'Rp ' + number(value, 2) : '-';
  const metric = (field, value) => {
    if (!numeric(value)) return '-';
    if (field === 'sales' || field === 'ad_cost') return money(value);
    return number(value, field === 'ctr' || field === 'roas' ? 2 : 0) + (field === 'ctr' ? '%' : field === 'roas' ? 'x' : '');
  };
  const date = value => value ? new Date(value + 'T00:00:00+07:00').toLocaleDateString('id-ID', {day: 'numeric', month: 'short', year: 'numeric', timeZone: 'Asia/Jakarta'}) : '-';
  const timestamp = value => value ? new Date(value).toLocaleString('id-ID', {timeZone: 'Asia/Jakarta'}) + ' WIB' : 'belum tersedia';
  const expired = shop => shop.session_expired || shop.session_status === 'expired' || shop.status === 'expired';
  let controller;
  let shops = [];
  let loaded = false;
  let syncInProgress = false;
  let requestId = 0;
  let topupRequestId = 0;

  function renderRecap(visible) {
    const recap = root.querySelector('#ads-recap');
    if (!visible.length) { recap.hidden = true; return; }
    recap.hidden = false;

    const reports = visible.map(shop => ({shop, report: shop.metrics?.performance || {}}));
    const available = reports.filter(item => item.report.available === true);
    const aggregate = field => {
      const entries = available.filter(item => numeric(item.report[field]));
      return {count: entries.length, value: entries.reduce((sum, item) => sum + Number(item.report[field]), 0)};
    };
    const cost = aggregate('ad_cost');
    const sales = aggregate('sales');
    const issues = visible.length - reports.filter(item => item.report.available === true && !item.report.stale && !expired(item.shop)).length;
    const states = {ready: 0, stale: 0, expired: 0, failed: 0, pending: 0};
    const labels = {ready: 'Tersedia', stale: 'Data lama', expired: 'Sesi habis', failed: 'Gagal', pending: 'Menunggu'};
    reports.forEach(({shop, report}) => {
      const key = expired(shop) ? 'expired'
        : shop.status === 'error' || report.error_code ? 'failed'
        : report.available === true && report.stale ? 'stale'
        : report.available === true ? 'ready'
        : report.request_state === 'queued' || report.request_state === 'pending' ? 'pending'
        : 'failed';
      states[key]++;
    });

    root.querySelector('#ads-recap-scope').textContent = periodLabels[period.value] + ' · ' + channel.selectedOptions[0].text + ' · mengikuti filter toko.';
    root.querySelector('#ads-recap-coverage').textContent = visible.length + ' toko dalam cakupan';
    root.querySelector('#ads-recap-cost').textContent = cost.count ? money(cost.value) : 'Belum tersedia';
    root.querySelector('#ads-recap-cost-context').textContent = cost.count + ' dari ' + visible.length + ' toko memiliki biaya iklan pada laporan terpilih.';
    root.querySelector('#ads-recap-sales').textContent = sales.count ? money(sales.value) : 'Belum tersedia';
    root.querySelector('#ads-recap-sales-context').textContent = sales.count + ' dari ' + visible.length + ' toko memiliki penjualan teratribusikan.';
    root.querySelector('#ads-recap-roas').textContent = cost.count && sales.count && cost.value > 0 ? number(sales.value / cost.value, 2) + 'x' : 'Belum tersedia';
    root.querySelector('#ads-recap-roas-context').textContent = 'Penjualan tersedia dibagi biaya iklan tersedia; cakupan mengikuti laporan di atas.';
    root.querySelector('#ads-recap-available').textContent = number(available.length);
    root.querySelector('#ads-recap-issues').textContent = number(issues);
    root.querySelector('#ads-recap-insight').textContent = cost.count
      ? money(cost.value) + ' biaya iklan dan ' + (sales.count ? money(sales.value) + ' penjualan teratribusikan' : 'penjualan teratribusikan belum tersedia') + ' dari ' + cost.count + ' toko. Data yang belum tersedia ditampilkan terpisah.'
      : 'Laporan biaya iklan belum tersedia. Periksa status sinkronisasi dan koneksi toko sebelum menyimpulkan tidak ada aktivitas.';

    const tones = {ready: 'success', stale: 'warning', expired: 'error', failed: 'error', pending: 'neutral'};
    const bar = root.querySelector('#ads-recap-bar');
    bar.replaceChildren();
    bar.setAttribute('role', 'img');
    bar.setAttribute('aria-label', Object.keys(states).map(key => labels[key] + ': ' + states[key]).join(', '));
    Object.keys(states).forEach(key => {
      if (!states[key]) return;
      const segment = document.createElement('span');
      segment.dataset.tone = tones[key];
      segment.style.setProperty('--page-recap-share', (states[key] / visible.length * 100).toFixed(3) + '%');
      bar.append(segment);
    });
    const legend = root.querySelector('#ads-recap-legend');
    legend.replaceChildren();
    Object.keys(states).forEach(key => {
      const item = document.createElement('li');
      const line = document.createElement('span');
      line.className = 'page-recap-segment-link';
      const swatch = document.createElement('span');
      swatch.className = 'page-recap-swatch';
      swatch.dataset.tone = tones[key];
      swatch.setAttribute('aria-hidden', 'true');
      line.append(swatch, Object.assign(document.createElement('span'), {textContent: labels[key]}), Object.assign(document.createElement('strong'), {textContent: number(states[key])}));
      item.append(line);
      legend.append(item);
    });

    const ranked = reports.filter(item => item.report.available === true && numeric(item.report.ad_cost))
      .sort((a, b) => Number(b.report.ad_cost) - Number(a.report.ad_cost)).slice(0, 5);
    const target = root.querySelector('#ads-recap-shops');
    target.replaceChildren();
    if (!ranked.length) target.append(Object.assign(document.createElement('li'), {textContent: 'Perbandingan biaya belum tersedia.'}));
    const max = Math.max(1, ...ranked.map(item => Number(item.report.ad_cost)));
    ranked.forEach(({shop, report}) => {
      const item = document.createElement('li');
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'page-recap-shop-button';
      button.setAttribute('aria-label', 'Tampilkan laporan iklan ' + (shop.shop_name || 'toko'));
      const line = document.createElement('span');
      line.className = 'page-recap-shop-line';
      line.append(Object.assign(document.createElement('span'), {textContent: shop.shop_name || 'Toko #' + shop.shop_id}), Object.assign(document.createElement('strong'), {textContent: money(report.ad_cost)}));
      const track = document.createElement('span');
      track.className = 'page-recap-shop-track';
      track.setAttribute('aria-hidden', 'true');
      const fill = document.createElement('span');
      fill.style.width = (Number(report.ad_cost) / max * 100).toFixed(2) + '%';
      track.append(fill);
      button.append(line, track);
      button.addEventListener('click', () => {
        shopFilter.value = String(shop.shop_id);
        syncShopPicker();
        render();
        shopFilter.focus();
      });
      item.append(button);
      target.append(item);
    });
  }

  function updateHelp() {
    const descriptions = {
      daily: 'Hari ini sejak 00.00 WIB',
      weekly: 'Senin sampai hari ini · WIB',
      monthly: 'Tanggal 1 sampai hari ini · WIB'
    };
    root.querySelector('#ads-period-help').textContent = descriptions[period.value];
  }

  function render() {
    const openShops = new Set(Array.from(grid.querySelectorAll('article')).filter(card => card.querySelector('[data-detail]').open).map(card => card.dataset.shopId));
    const openSources = new Set(Array.from(grid.querySelectorAll('article')).filter(card => card.querySelector('[data-source-detail]').open).map(card => card.dataset.shopId));
    grid.replaceChildren();
    const visible = shops.filter(shop => !shopFilter.value || String(shop.shop_id) === shopFilter.value);
    renderRecap(visible);
    const expiredShops = visible.filter(expired);
    warning.hidden = expiredShops.length === 0;
    warning.querySelector('[data-session-message]').textContent = 'Sesi Shopee perlu diperbarui: ' + expiredShops.map(shop => shop.shop_name).join(', ') + '.';
    if (!visible.length) {
      state.textContent = shops.length ? 'Tidak ada toko untuk filter ini. Pilih Semua toko.' : 'Belum ada toko terhubung. Tambahkan toko melalui halaman Toko.';
      return;
    }
    visible.forEach(shop => {
      const node = template.content.cloneNode(true);
      const card = node.querySelector('article');
      card.dataset.shopId = String(shop.shop_id);
      const metrics = shop.metrics || {};
      const report = metrics.performance || {};
      const available = report.available === true;
      const set = (key, value) => node.querySelector('[data-' + key + ']').textContent = value;
      set('shop-name', shop.shop_name || 'Toko #' + shop.shop_id);
      window.renderShopLogo(node.querySelector('[data-shop-logo]'), shop.shop_id);
      const range = report.start_date === report.end_date ? date(report.start_date) : date(report.start_date) + ' s.d. ' + date(report.end_date);
      set('performance-period', (report.channel_label || channel.selectedOptions[0].text) + ' · ' + range);
      set('shop-sync', report.fetched_at ? 'Diperbarui ' + timestamp(report.fetched_at) : 'Belum diperbarui');
      const status = node.querySelector('[data-status]');
      status.textContent = expired(shop) ? 'Sesi habis' : available ? (report.stale ? 'Belum diperbarui' : 'Tersedia') : (report.request_state === 'skipped' ? 'Belum diambil' : report.error_code ? 'Gagal dimuat' : 'Belum tersedia');
      status.dataset.state = available && !report.stale && !expired(shop) ? 'ready' : 'pending';
      fields.forEach((field, index) => set(selectors[index], available ? metric(field, report[field]) : '-'));
      const notes = [];
      if (report.collection_method === 'browser_capture') notes.push('Sumber: laporan Seller Centre melalui import browser. Pembaruan pilot dilakukan secara manual.');
      if (report.collection_method === 'cookie_campaign') notes.push('Sumber: laporan seluruh campaign yang diambil melalui koneksi toko.');
      if (report.mapping_note) notes.push(report.mapping_note);
      if (report.request_state === 'reused') notes.push('Rentang ini sama dengan laporan lain dalam sinkronisasi; hasil request yang sama digunakan kembali.');
      if (report.error_message) notes.push(report.error_message);
      if (shop.status === 'error' && shop.error_message) notes.push(shop.error_message);
      if (report.attempted_at) notes.push('Percobaan terakhir: ' + timestamp(report.attempted_at));
      set('source-note', notes.join(' '));
      node.querySelector('[data-source-note]').hidden = notes.length === 0;
      node.querySelector('[data-source-detail]').open = openSources.has(String(shop.shop_id));
      const warnings = [];
      if (expired(shop)) warnings.push('Perbarui koneksi toko.');
      if (available && report.stale) warnings.push('Menampilkan data terakhir yang berhasil diambil.');
      if (!available) warnings.push('Laporan belum tersedia. Periksa sinkronisasi.');
      else if (report.error_message || shop.status === 'error') warnings.push('Pembaruan gagal. Periksa sinkronisasi.');
      if (report.reconciliation?.status === 'mismatch') warnings.push('Rincian tanggal berbeda dari ringkasan. Total mengikuti ringkasan Shopee.');
      set('note', warnings.join(' '));
      node.querySelector('[data-warning]').hidden = warnings.length === 0;
      const recovery = node.querySelector('[data-recovery]');
      if (expired(shop)) {
        recovery.href = warning.querySelector('a').href;
        recovery.textContent = 'Perbarui koneksi';
      }
      const rows = available && Array.isArray(report.daily) ? report.daily : [];
      const detail = node.querySelector('[data-detail]');
      detail.open = openShops.has(String(shop.shop_id));
      set('detail-count', rows.length ? '(' + rows.length + ' tanggal)' : '(belum tersedia)');
      const empty = node.querySelector('[data-detail-empty]');
      empty.hidden = rows.length > 0;
      empty.textContent = available ? (report.detail_message || 'Ringkasan tersedia, tetapi rincian per tanggal belum diterima dari Shopee.') : 'Rincian akan tampil setelah laporan periode ini berhasil disinkronkan.';
      const region = node.querySelector('[data-table-region]');
      region.hidden = rows.length === 0;
      region.setAttribute('aria-label', 'Rincian harian ' + shop.shop_name + '. Geser untuk melihat seluruh metrik.');
      set('table-caption', 'Rincian ' + (report.channel_label || '') + ' ' + shop.shop_name + ', ' + range);
      const body = node.querySelector('[data-daily-rows]');
      rows.forEach(row => {
        const tr = document.createElement('tr');
        const th = document.createElement('th');
        th.scope = 'row';
        th.textContent = date(row.date);
        tr.appendChild(th);
        fields.forEach(field => {
          const td = document.createElement('td');
          td.textContent = metric(field, row[field]);
          tr.appendChild(td);
        });
        body.appendChild(tr);
      });
      set('session-status', 'Koneksi toko: ' + (expired(shop) ? 'sesi habis' : shop.session_status === 'connected' ? 'terhubung' : 'belum terverifikasi') + '. Meta terakhir: ' + timestamp(shop.synced_at ? shop.synced_at.replace(' ', 'T') + 'Z' : null) + (shop.stale ? ' (belum diperbarui)' : '') + '.');
      set('meta', 'Saldo iklan: ' + money(metrics.ads_credit?.total) + ' · Biaya hari ini dari ringkasan saldo: ' + money(metrics.ads_expense_today));
      const channels = metrics.has_ads;
      const active = channels ? Object.keys(channelLabels).filter(key => channels[key]).map(key => channelLabels[key]) : [];
      set('channels', channels ? 'Jenis iklan aktif: ' + (active.join(', ') || 'tidak ada menurut data terakhir') + '.' : 'Status jenis iklan aktif belum tersedia.');
      grid.appendChild(node);
    });
    const availableCount = visible.filter(shop => shop.metrics?.performance?.available).length;
    const staleCount = visible.filter(shop => shop.metrics?.performance?.available && shop.metrics.performance.stale).length;
    state.textContent = periodLabels[period.value] + ' · ' + visible.length + ' toko · ' + availableCount + ' laporan tersedia' + (staleCount ? ' (' + staleCount + ' belum diperbarui)' : '') + ' · ' + (visible.length - availableCount) + ' belum tersedia.';
  }

  async function loadTopups() {
    const current = ++topupRequestId;
    topupRows.replaceChildren();
    topupState.hidden = false;
    topupState.textContent = 'Memuat total topup bulanan…';
    const url = new URL(root.dataset.topupsEndpoint, window.location.href);
    if (initialTopup||topupShop.value) url.searchParams.set('shop_id', initialTopup||topupShop.value);
    url.searchParams.set('period', topupPeriod.value);
    if (topupPeriod.value === 'custom') {
      url.searchParams.set('start_date', topupStart.value);
      url.searchParams.set('end_date', topupEnd.value);
    }
    try {
      const response = await fetch(url, {headers: {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json'}, cache: 'no-store'});
      if (response.redirected) throw new Error('Sesi aplikasi berakhir. Muat ulang halaman untuk masuk kembali.');
      const payload = await response.json();
      if (!response.ok) throw new Error(payload.message || 'Laporan topup gagal dimuat (HTTP ' + response.status + ').');
      if (payload.status !== 'success' || !payload.report || !Array.isArray(payload.report.months)) throw new Error(payload.message || 'Respons laporan topup tidak lengkap.');
      if (current !== topupRequestId) return;
      const report = payload.report;
      const selectedShop = initialTopup||topupShop.value;initialTopup='';
      topupShop.replaceChildren(new Option('Semua toko', ''));
      const shopOptions = Array.isArray(report.shop_options) ? report.shop_options : report.shops;
      shopOptions.forEach(shop => topupShop.add(new Option(shop.shop_name || 'Toko #' + shop.shop_id, String(shop.shop_id))));
      topupShop.value = shopOptions.some(shop => String(shop.shop_id) === selectedShop) ? selectedShop : '';
      syncTopupPicker();
      topupPeriodNote.textContent = date(report.start_date) + ' sampai ' + date(report.end_date) + ' · WIB';
      const pending = report.shops.filter(shop => !shop.backfill_complete);
      const errors = report.shops.filter(shop => shop.error_message);
      if (!report.shops.length) {
        topupState.textContent = 'Belum ada toko. Tambahkan toko melalui halaman Toko untuk mulai mengumpulkan riwayat topup.';
      } else if (errors.length) {
        topupState.textContent = 'Sebagian data belum tersinkron. ' + errors.map(shop => shop.shop_name + ': ' + shop.error_message).join(' ');
      } else if (pending.length) {
        topupState.textContent = 'Riwayat sejak Agustus sedang disinkronkan. Total di bawah masih sementara.';
      } else if (!report.months.length) {
        topupState.textContent = 'Belum ada topup pada periode ini.';
      } else {
        topupState.textContent = '';
        topupState.hidden = true;
      }
      const lastSync = report.shops.map(shop => shop.synced_at).filter(Boolean).sort().pop();
      topupUpdated.textContent = lastSync ? 'Terakhir disinkron ' + timestamp(lastSync.replace(' ', 'T') + 'Z') : 'Menunggu sinkronisasi pertama';
      report.months.forEach(month => {
        const row = document.createElement('tr');
        const label = document.createElement('th');
        label.scope = 'row';
        label.className = 'px-4 py-3 font-semibold';
        label.textContent = month.label;
        const total = document.createElement('td');
        total.className = 'px-4 py-3 text-right font-bold tabular-nums';
        total.textContent = money(month.total);
        row.append(label, total);
        topupRows.appendChild(row);
      });
    } catch (error) {
      if (current !== topupRequestId) return;
      topupState.textContent = error instanceof TypeError ? 'Koneksi ke aplikasi gagal. Periksa koneksi lalu muat ulang halaman.' : error.message;
      topupUpdated.textContent = 'Status sinkronisasi belum tersedia';
    }
  }

  function updateTopupRange() {
    topupCustomRange.hidden = topupPeriod.value !== 'custom';
    loadTopups();
  }

  async function syncAdsReports() {
    if (!loaded || syncInProgress) return;
    const selectedShop = shopFilter.value;
    const targets = shops.filter(shop => !selectedShop || String(shop.shop_id) === selectedShop);
    if (!targets.length) {
      syncStatus.textContent = 'Tidak ada toko untuk disinkronkan.';
      return;
    }

    syncInProgress = true;
    syncButton.disabled = true;
    syncButton.textContent = 'Mengantrikan…';
    syncStatus.textContent = 'Meminta laporan terbaru untuk ' + targets.length + ' toko…';
    const jobs = [];
    const enqueueFailures = [];
    for (const shop of targets) {
      try {
        const form = new FormData();
        form.append('shop_id', String(shop.shop_id));
        form.append('sync_type', 'ads');
        form.append('sync_mode', 'diff');
        const response = await fetch(root.dataset.syncEndpoint, {
          method: 'POST',
          headers: {'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': root.dataset.csrf || '', 'Accept': 'application/json'},
          body: form,
          cache: 'no-store'
        });
        const payload = await response.json();
        if (!response.ok || payload.status !== 'accepted' || !payload.job_id) throw new Error(payload.message || 'Antrean ditolak.');
        jobs.push({shop_id: Number(payload.shop_id || shop.shop_id), job_id: Number(payload.job_id), shop_name: shop.shop_name || 'Toko'});
      } catch (error) {
        enqueueFailures.push({shop_name: shop.shop_name || 'Toko', message: error.message || 'Antrean gagal dibuat.'});
      }
    }

    if (!jobs.length) {
      syncStatus.textContent = 'Sinkronisasi tidak berhasil dimulai. ' + enqueueFailures.map(item => item.shop_name + ': ' + item.message).join(' ');
      finishAdsSync();
      return;
    }

    syncButton.textContent = 'Sinkronisasi…';
    syncStatus.textContent = 'Sinkronisasi laporan berjalan untuk ' + jobs.length + ' dari ' + targets.length + ' toko…';
    const jobResults = await pollAdsReports(jobs);
    const completed = jobResults.filter(job => job.status === 'completed');
    const failed = jobResults.filter(job => job.status === 'failed');
    const unfinished = jobResults.filter(job => !['completed', 'failed'].includes(job.status));
    if (completed.length) {
      syncStatus.textContent = 'Sinkronisasi selesai untuk ' + completed.length + ' dari ' + targets.length + ' toko. Memuat ulang ringkasan…';
      await loadAds();
      const issues = failed.length + enqueueFailures.length;
      syncStatus.textContent = unfinished.length
        ? 'Ringkasan diperbarui untuk ' + completed.length + ' toko. ' + unfinished.length + ' toko masih diproses; data tersimpan sebelumnya tetap ditampilkan.'
        : issues
          ? 'Ringkasan diperbarui untuk ' + completed.length + ' toko. ' + issues + ' toko gagal disinkronkan; data tersimpan sebelumnya tetap ditampilkan.'
        : 'Sinkronisasi selesai. Ringkasan iklan sudah diperbarui dari data terbaru.';
    } else if (failed.length) {
      syncStatus.textContent = unfinished.length
        ? 'Sinkronisasi gagal untuk ' + failed.length + ' toko; ' + unfinished.length + ' toko lainnya masih diproses. Rekap menampilkan laporan tersimpan terakhir.'
        : 'Sinkronisasi gagal. Rekap tetap menampilkan laporan tersimpan terakhir. Periksa jadwal sinkronisasi.';
    } else {
      syncStatus.textContent = 'Sinkronisasi masih berjalan. Periksa statusnya di halaman Jadwal sinkronisasi.';
    }
    finishAdsSync();
  }

  async function pollAdsReports(jobs) {
    const terminal = new Map();
    for (let attempt = 0; attempt < 360; attempt++) {
      try {
        const url = new URL(root.dataset.syncStatusEndpoint, window.location.href);
        url.searchParams.set('shop_id', '0');
        const response = await fetch(url, {headers: {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json'}, cache: 'no-store'});
        const payload = await response.json();
        if (response.ok && payload.status === 'success' && Array.isArray(payload.schedules)) {
          for (const job of jobs) {
            const key = job.shop_id + ':' + job.job_id;
            if (terminal.has(key)) continue;
            const row = payload.schedules.find(item => Number(item.shop_id) === job.shop_id && item.sync_type === 'ads' && Number(item.job_id) === job.job_id);
            if (row && ['completed', 'failed'].includes(row.job_status)) terminal.set(key, {...job, status: row.job_status});
          }
        }
      } catch (error) {
        // Keep polling through temporary status endpoint errors; the job itself may still be running.
      }
      const finished = [...terminal.values()];
      syncStatus.textContent = 'Sinkronisasi laporan: ' + finished.length + ' dari ' + jobs.length + ' toko selesai…';
      if (finished.length === jobs.length) return finished;
      await new Promise(resolve => window.setTimeout(resolve, 3000));
    }
    return jobs.map(job => terminal.get(job.shop_id + ':' + job.job_id) || {...job, status: 'timeout'});
  }

  function finishAdsSync() {
    syncInProgress = false;
    syncButton.disabled = !loaded;
    syncButton.textContent = 'Ambil data Shopee';
  }

  async function loadAds() {
    controller?.abort();
    controller = new AbortController();
    const current = ++requestId;
    refresh.disabled = true;
    shopFilter.disabled = true;
    syncShopPicker();
    grid.setAttribute('aria-busy', 'true');
    grid.hidden = true;
    warning.hidden = true;
    loadTopups();
    state.textContent = 'Memuat ' + periodLabels[period.value].toLowerCase() + ' untuk ' + channel.selectedOptions[0].text.toLowerCase() + '…';
    updateHelp();
    try {
      const url = new URL(root.dataset.endpoint, window.location.href);
      url.searchParams.set('period', period.value);
      url.searchParams.set('channel', channel.value);
      const response = await fetch(url, {headers: {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json'}, cache: 'no-store', signal: controller.signal});
      if (response.redirected) throw new Error('Sesi aplikasi berakhir. Muat ulang halaman untuk masuk kembali.');
      if (!response.ok) throw new Error('Laporan gagal dimuat (HTTP ' + response.status + '). Coba Muat ulang data.');
      const payload = await response.json();
      if (payload.status !== 'success' || !Array.isArray(payload.shops)) throw new Error(payload.message || 'Respons laporan tidak lengkap. Coba Muat ulang data.');
      if (current !== requestId) return;
      shops = payload.shops;
      const selection = initialShop||shopFilter.value;initialShop='';
      shopFilter.replaceChildren(new Option('Semua toko', ''));
      shops.forEach(shop => shopFilter.add(new Option(shop.shop_name || 'Toko #' + shop.shop_id, String(shop.shop_id))));
      shopFilter.value = shops.some(shop => String(shop.shop_id) === selection) ? selection : '';
      loaded = true;
      render();
      grid.hidden = false;
    } catch (error) {
      if (current !== requestId || error.name === 'AbortError') return;
      loaded = false;
      state.textContent = error instanceof SyntaxError ? 'Respons laporan tidak terbaca. Coba Muat ulang data.' : error instanceof TypeError ? 'Koneksi ke aplikasi gagal. Periksa koneksi lalu coba Muat ulang data.' : error.message;
      grid.replaceChildren();
    } finally {
      if (current === requestId) {
        refresh.disabled = false;
        shopFilter.disabled = !loaded;
        syncButton.disabled = !loaded || syncInProgress;
        syncShopPicker();
        grid.setAttribute('aria-busy', 'false');
      }
    }
  }

  period.addEventListener('change', loadAds);
  channel.addEventListener('change', loadAds);
  shopFilter.addEventListener('change', render);
  topupShop.addEventListener('change', loadTopups);
  topupPeriod.addEventListener('change', updateTopupRange);
  topupStart.addEventListener('change', loadTopups);
  topupEnd.addEventListener('change', loadTopups);
  syncButton.addEventListener('click', syncAdsReports);
  refresh.addEventListener('click', loadAds);
  loadAds();
  window.setInterval(() => { if (!document.hidden && !refresh.disabled) loadAds(); }, 300000);
})();
