(() => {
  const root = document.getElementById('ads-monitor');
  if (!root) return;
  const grid = root.querySelector('#ads-grid');
  const state = root.querySelector('#ads-state');
  const refresh = root.querySelector('#ads-refresh');
  const period = root.querySelector('#ads-period');
  const channel = root.querySelector('#ads-channel');
  const shopFilter = root.querySelector('#ads-shop');
  const topupState = root.querySelector('#ads-topups-state');
  const topupRows = root.querySelector('#ads-topups-rows');
  const topupUpdated = root.querySelector('[data-topups-updated]');
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
  let requestId = 0;
  let topupRequestId = 0;

  function updateHelp() {
    const descriptions = {
      daily: 'Hari ini, mulai pukul 00.00 WIB. Data hari ini masih dapat berubah.',
      weekly: 'Senin sampai hari ini dalam WIB. Data minggu berjalan masih dapat berubah.',
      monthly: 'Tanggal 1 sampai hari ini dalam WIB. Data bulan berjalan masih dapat berubah.'
    };
    root.querySelector('#ads-period-help').textContent = descriptions[period.value];
  }

  function render() {
    const openShops = new Set(Array.from(grid.querySelectorAll('article')).filter(card => card.querySelector('details').open).map(card => card.dataset.shopId));
    grid.replaceChildren();
    const visible = shops.filter(shop => !shopFilter.value || String(shop.shop_id) === shopFilter.value);
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
      const range = report.start_date === report.end_date ? date(report.start_date) : date(report.start_date) + ' s.d. ' + date(report.end_date);
      set('performance-period', (report.channel_label || channel.selectedOptions[0].text) + ' · ' + range);
      set('shop-sync', report.fetched_at ? 'Laporan terakhir berhasil diambil ' + timestamp(report.fetched_at) : 'Laporan belum berhasil diambil' + (report.attempted_at ? ' · Percobaan terakhir ' + timestamp(report.attempted_at) : ''));
      const status = node.querySelector('[data-status]');
      status.textContent = available ? (report.stale || expired(shop) ? 'Data tersimpan · belum diperbarui' : 'Laporan tersedia') : (report.request_state === 'skipped' ? 'Request laporan dilewati' : report.error_code ? 'Laporan ditolak Shopee' : 'Laporan belum tersedia');
      status.dataset.state = available && !report.stale && !expired(shop) ? 'ready' : 'pending';
      fields.forEach((field, index) => set(selectors[index], available ? metric(field, report[field]) : '-'));
      const notes = [];
      if (report.collection_method === 'browser_capture') notes.push('Sumber: laporan Seller Centre melalui import browser. Pembaruan pilot dilakukan secara manual.');
      if (report.collection_method === 'cookie_campaign') notes.push('Sumber: laporan seluruh campaign yang diambil melalui koneksi toko.');
      if (report.mapping_note) notes.push(report.mapping_note);
      if (report.reconciliation?.status === 'mismatch') notes.push('Total rincian tanggal belum cocok dengan ringkasan Shopee. Angka ringkasan tetap memakai total dari Shopee.');
      if (report.request_state === 'reused') notes.push('Rentang ini sama dengan laporan lain dalam sinkronisasi; hasil request yang sama digunakan kembali.');
      if (report.error_message) notes.push(report.error_message);
      if (available && report.stale) notes.push('Angka yang ditampilkan adalah hasil sukses terakhir untuk rentang ini.');
      if (expired(shop)) notes.push('Sesi toko habis. Perbarui cookie melalui halaman Toko.');
      else if (shop.status === 'error' && shop.error_message) notes.push(shop.error_message);
      if (!available) notes.push('Periksa laporan di Seller Centre dan status sinkronisasi. Saldo yang tersedia tidak menandakan laporan berhasil diambil.');
      if (!notes.length) notes.push('Data periode berjalan masih dapat berubah. CTR dihitung dari klik ÷ tayangan; ROAS dari penjualan ÷ biaya iklan.');
      set('note', notes.join(' '));
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
    topupState.textContent = 'Memuat total topup bulanan…';
    const url = new URL(root.dataset.topupsEndpoint, window.location.href);
    if (shopFilter.value) url.searchParams.set('shop_id', shopFilter.value);
    try {
      const response = await fetch(url, {headers: {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json'}, cache: 'no-store'});
      if (response.redirected) throw new Error('Sesi aplikasi berakhir. Muat ulang halaman untuk masuk kembali.');
      if (!response.ok) throw new Error('Laporan topup gagal dimuat (HTTP ' + response.status + ').');
      const payload = await response.json();
      if (payload.status !== 'success' || !payload.report || !Array.isArray(payload.report.months)) throw new Error(payload.message || 'Respons laporan topup tidak lengkap.');
      if (current !== topupRequestId) return;
      const report = payload.report;
      const pending = report.shops.filter(shop => !shop.backfill_complete);
      const errors = report.shops.filter(shop => shop.error_message);
      if (!report.shops.length) {
        topupState.textContent = 'Belum ada toko. Tambahkan toko melalui halaman Toko untuk mulai mengumpulkan riwayat topup.';
      } else if (errors.length) {
        topupState.textContent = 'Sebagian data belum tersinkron. ' + errors.map(shop => shop.shop_name + ': ' + shop.error_message).join(' ');
      } else if (pending.length) {
        topupState.textContent = 'Riwayat sejak Agustus sedang disinkronkan. Total di bawah masih sementara.';
      } else {
        topupState.textContent = 'Semua riwayat sejak Agustus sudah tersinkron. Angka termasuk PPN.';
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

  async function loadAds() {
    controller?.abort();
    controller = new AbortController();
    const current = ++requestId;
    refresh.disabled = true;
    shopFilter.disabled = true;
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
      const selection = shopFilter.value;
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
        grid.setAttribute('aria-busy', 'false');
      }
    }
  }

  period.addEventListener('change', loadAds);
  channel.addEventListener('change', loadAds);
  shopFilter.addEventListener('change', () => { render(); loadTopups(); });
  refresh.addEventListener('click', loadAds);
  loadAds();
  window.setInterval(() => { if (!document.hidden && !refresh.disabled) loadAds(); }, 300000);
})();
