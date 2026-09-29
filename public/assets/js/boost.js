(() => {
  const root = document.getElementById('boost-monitor');
  if (!root) return;
  const grid = root.querySelector('#boost-grid');
  const state = root.querySelector('#boost-state');
  const template = document.getElementById('boost-card-template');
  const shops = JSON.parse(document.getElementById('boost-shops').textContent);
  const states = new Map();
  const number = value => new Intl.NumberFormat('id-ID').format(Number(value || 0));
  const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'}[c]));
  const timestamp = value => value ? Date.parse(String(value).replace(' ', 'T') + 'Z') : 0;
  const date = value => value ? new Date(timestamp(value)).toLocaleString('id-ID', {dateStyle: 'short', timeStyle: 'short'}) : '-';
  const headers = {'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json'};
  const quota = local => Math.max(0, Math.min(5, Number(local.payload?.summary?.remaining_count) || 0));
  const connected = local => local.payload?.shop?.session_status === 'connected';
  const blocked = local => local.loading || local.busy || local.failed || !connected(local) || local.payload?.summary?.batch_active || quota(local) === 0;

  function eligibility(product) {
    if (!(Number(product.total_stock) > 0)) return 'Stok kosong';
    if (product.disabled_boost_button || product.show_boost_button === false) return 'Belum tersedia';
    const cooldown = product.boost_cooldown || {};
    const until = timestamp(cooldown.next_boost_at);
    if (until > Date.now()) return 'Tersedia ' + date(cooldown.next_boost_at);
    if (cooldown.cooldown_active && !until) return 'Masih menunggu';
    return '';
  }

  function controls(local) {
    const card = local.card;
    const locked = blocked(local);
    const products = local.payload?.products || [];
    card.querySelectorAll('[data-product-id]').forEach(input => {
      const product = products.find(item => String(item.id) === input.dataset.productId);
      const reason = product ? eligibility(product) : 'Belum tersedia';
      input.disabled = !!locked || !!reason;
      if (reason) input.checked = false;
      const row = input.closest('label');
      row.querySelector('[data-product-status]').textContent = reason || 'Stok tersedia · Siap dinaikkan';
      row.classList.toggle('cursor-not-allowed', input.disabled);
    });
    const selected = [...card.querySelectorAll('[data-product-id]:checked')];
    selected.slice(quota(local)).forEach(input => { input.checked = false; });
    const count = Math.min(selected.length, quota(local));
    const countText = count + '/' + quota(local) + ' dipilih';
    const counter = card.querySelector('[data-selection-count]');
    if (counter.textContent !== countText) counter.textContent = countText;
    const collapsed = card.querySelector('[data-collapsed-count]');
    const collapsedText = count ? '· ' + count + ' dipilih' : '';
    if (collapsed.textContent !== collapsedText) collapsed.textContent = collapsedText;
    const button = card.querySelector('[data-boost]');
    button.disabled = !!locked || !count;
    button.textContent = local.busy ? 'Memproses…' : count ? 'Naikkan ' + count + ' produk' : 'Naikkan produk';
    card.querySelector('[data-clear]').disabled = local.busy || local.loading || !count;
    card.querySelector('[data-recommend]').disabled = !!locked || !products.some(product => !eligibility(product));
    card.querySelector('[data-refresh]').disabled = local.loading || local.busy;
  }

  function render(local, selected) {
    const {card, payload} = local;
    const summary = payload.summary || {};
    card.querySelector('[data-shop-status]').textContent = payload.products.length + ' dari ' + number(payload.total) + ' produk aktif';
    card.querySelector('[data-session-status]').textContent = connected(local) ? 'Terhubung' : 'Koneksi perlu diperbarui';
    card.querySelector('[data-remaining]').textContent = quota(local);
    card.querySelector('[data-cooldown]').textContent = summary.batch_active ? 'Produk sedang diproses' : quota(local) ? 'Kuota tersedia' : 'Kuota kembali ' + date(summary.quota_reset_at);
    const help = card.querySelector('[data-selection-help]');
    help.textContent = !connected(local) ? 'Perbarui koneksi toko untuk menaikkan produk.' : summary.batch_active ? 'Tunggu proses toko ini selesai.' : '';
    help.hidden = !help.textContent;
    card.querySelector('[data-reconnect]').hidden = connected(local);
    card.querySelector('[data-recommendation-note]').hidden = true;
    const container = card.querySelector('[data-products]');
    container.replaceChildren();
    payload.products.forEach((product, index) => {
      const row = document.createElement('label');
      row.className = 'boost-product-row';
      row.innerHTML = '<input type="checkbox" class="checkbox checkbox-primary" data-product-id="' + esc(product.id) + '" /><span class="boost-product-image" aria-hidden="true"></span><span class="boost-product-title">' + esc(product.name) + '</span><span class="boost-product-meta"><span>Terlaris #' + (index + 1) + ' · Terjual ' + number(product.sold_count) + '</span><span>Rp ' + number(product.selling_price_min ?? product.price_min) + ' · Stok ' + number(product.total_stock) + '</span><span data-product-status></span></span>';
      if (product.cover_image) {
        const image = document.createElement('img');
        image.src = 'https://cf.shopee.co.id/file/' + encodeURIComponent(product.cover_image);
        image.alt = '';
        image.loading = 'lazy';
        image.width = image.height = 48;
        image.addEventListener('error', () => image.remove(), {once: true});
        row.querySelector('.boost-product-image').append(image);
      }
      const input = row.querySelector('input');
      input.checked = selected.has(String(product.id));
      input.addEventListener('change', () => {
        const note = card.querySelector('[data-recommendation-note]');
        if (card.querySelectorAll('[data-product-id]:checked').length > quota(local)) {
          input.checked = false;
          note.textContent = 'Sisa kuota ' + quota(local) + ' produk. Hapus satu pilihan untuk menggantinya.';
          note.hidden = false;
        } else note.hidden = true;
        controls(local);
      });
      container.appendChild(row);
    });
    if (!payload.products.length) container.textContent = 'Belum ada produk aktif. Periksa produk dan sinkronisasi di halaman Produk.';
    const labels = {completed: 'Selesai', failed: 'Gagal', running: 'Diproses', success: 'Berhasil', unknown: 'Belum pasti', pending: 'Menunggu'};
    card.querySelector('[data-history]').innerHTML = (payload.history || []).map(run => '<div class="rounded-lg border border-base-content/20 p-3"><p>' + date(run.started_at) + ' · ' + esc(labels[run.status] || run.status) + '</p><p class="mt-1">' + number(run.success_count) + ' berhasil · ' + number(run.failed_count) + ' gagal · ' + number(run.unknown_count) + ' belum pasti</p>' + (run.items || []).map(item => '<p class="mt-2 break-words">' + esc(item.product_name || 'Produk #' + item.product_id) + ' · ' + esc(labels[item.status] || item.status) + '</p>').join('') + '</div>').join('') || 'Belum ada riwayat.';
  }

  async function loadShop(local, preserve = true) {
    if (local.loading) return;
    const {card} = local;
    const selected = new Set(preserve ? [...card.querySelectorAll('[data-product-id]:checked')].map(input => input.dataset.productId) : []);
    local.loading = true;
    const status = card.querySelector('[data-load-state]');
    status.hidden = false;
    status.textContent = 'Memuat status dan produk…';
    card.setAttribute('aria-busy', 'true');
    controls(local);
    try {
      const response = await fetch(root.dataset.endpoint + '/boost_products?shop_id=' + local.id + '&limit=10&page=1', {headers, cache: 'no-store'});
      const payload = await response.json();
      if (!response.ok || payload.status !== 'success' || !Array.isArray(payload.products)) throw new Error('Data toko gagal dimuat. Coba muat ulang status.');
      local.payload = {...payload, products: payload.products.slice(0, 10)};
      local.failed = false;
      render(local, selected);
      status.hidden = true;
    } catch (error) {
      local.failed = true;
      status.textContent = 'Data toko gagal dimuat. Buka daftar produk lalu pilih Muat ulang status.';
      card.querySelector('[data-shop-status]').textContent = 'Status belum diperbarui';
    } finally {
      local.loading = false;
      card.setAttribute('aria-busy', 'false');
      controls(local);
    }
  }

  async function runBoost(local) {
    if (blocked(local)) return;
    const selected = [...local.card.querySelectorAll('[data-product-id]:checked:not(:disabled)')].map(input => input.dataset.productId);
    if (!selected.length || selected.length > quota(local)) return;
    local.busy = true;
    controls(local);
    const result = local.card.querySelector('[data-action-result]');
    result.hidden = false;
    result.textContent = 'Memproses ' + selected.length + ' produk…';
    const form = new FormData();
    form.append('shop_id', local.id);
    form.append('product_ids', JSON.stringify(selected));
    try {
      const response = await fetch(root.dataset.endpoint + '/boost', {method: 'POST', headers, body: form});
      const payload = await response.json();
      if (!response.ok || payload.status !== 'success') throw new Error(payload.message || 'Produk gagal dinaikkan.');
      result.textContent = 'Selesai: ' + number(payload.result.success_count) + ' berhasil, ' + number(payload.result.failed_count) + ' gagal, ' + number(payload.result.unknown_count) + ' belum pasti.';
    } catch (error) {
      result.textContent = error.message === 'Failed to fetch' ? 'Koneksi terputus. Periksa riwayat sebelum mencoba lagi.' : error.message || 'Produk gagal dinaikkan. Periksa riwayat sebelum mencoba lagi.';
    } finally {
      await loadShop(local, false);
      local.busy = false;
      controls(local);
    }
  }

  shops.forEach(shop => {
    const node = template.content.cloneNode(true);
    const card = node.querySelector('[data-card]');
    const local = {id: shop.id, card, payload: null, loading: false, busy: false, failed: false};
    states.set(shop.id, local);
    card.dataset.shopId = shop.id;
    card.querySelector('[data-shop-name]').textContent = shop.name;
    window.renderShopLogo(card.querySelector('[data-shop-logo]'), shop.id);
    card.querySelector('[data-reconnect]').href = root.dataset.shopsUrl;
    card.querySelector('[data-refresh]').addEventListener('click', () => loadShop(local));
    card.querySelector('[data-boost]').addEventListener('click', () => runBoost(local));
    card.querySelector('[data-clear]').addEventListener('click', () => {
      card.querySelectorAll('[data-product-id]').forEach(input => { input.checked = false; });
      card.querySelector('[data-recommendation-note]').hidden = true;
      controls(local);
    });
    card.querySelector('[data-recommend]').addEventListener('click', () => {
      if (blocked(local)) return;
      let remaining = quota(local);
      card.querySelectorAll('[data-product-id]').forEach(input => {
        input.checked = !input.disabled && remaining > 0;
        if (input.checked) remaining--;
      });
      const count = quota(local) - remaining;
      const note = card.querySelector('[data-recommendation-note]');
      note.textContent = count + ' produk terlaris yang tersedia dipilih. Pilihan bisa diganti sebelum dinaikkan.';
      note.hidden = false;
      controls(local);
    });
    grid.appendChild(node);
    controls(local);
  });
  if (!shops.length) { state.textContent = 'Belum ada toko. Tambahkan toko melalui halaman Toko.'; return; }
  Promise.all([...states.values()].map(local => loadShop(local))).then(() => { state.hidden = true; });
  setInterval(() => states.forEach(local => { if (local.payload) controls(local); }), 1000);
})();
