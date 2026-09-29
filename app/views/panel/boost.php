<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
  <div>
    <h2 class="text-2xl font-black tracking-tight text-base-content">Naikkan produk</h2>
  </div>
  <a href="<?= burl; ?>/panel/products" class="btn btn-sm min-h-11 gap-2 rounded-lg border-base-content/10 bg-base-100"><span class="material-symbols-outlined text-base">inventory_2</span>Sinkronkan produk</a>
</div>

<div id="boost-state" class="mb-5 rounded-xl border border-base-content/10 bg-base-100 p-4 text-sm text-base-content/60">Memuat status naikkan produk setiap toko…</div>
<div id="boost-grid" class="grid grid-cols-1 gap-5 2xl:grid-cols-2"></div>

<template id="boost-card-template">
  <article class="overflow-hidden rounded-2xl border border-base-content/10 bg-base-100 shadow-sm" data-card>
    <div class="flex flex-wrap items-start justify-between gap-3 border-b border-base-content/10 p-5">
      <div class="min-w-0"><div class="truncate text-base font-black" data-shop-name></div><div class="mt-1 text-xs text-base-content/50" data-shop-status></div></div>
      <span class="badge badge-ghost badge-sm" data-session-status></span>
    </div>
    <div class="grid grid-cols-2 gap-4 p-5 sm:grid-cols-4">
      <div><div class="text-[10px] font-bold uppercase tracking-wide text-base-content/45">Terpakai</div><div class="mt-1 text-2xl font-black" data-used>0/5</div></div>
      <div><div class="text-[10px] font-bold uppercase tracking-wide text-base-content/45">Sisa kuota</div><div class="mt-1 text-2xl font-black" data-remaining>5</div></div>
      <div class="col-span-2"><div class="text-[10px] font-bold uppercase tracking-wide text-base-content/45">Status berikutnya</div><div class="mt-1 text-sm font-bold" data-cooldown>Siap digunakan</div></div>
    </div>
    <div class="border-t border-base-content/10 p-5">
      <div class="mb-3 flex flex-wrap items-center justify-between gap-3"><div><h3 class="text-sm font-black">Pilih produk</h3><p class="mt-1 text-sm" data-selection-count role="status">0/5 dipilih</p><p class="mt-1 text-sm" data-selection-help hidden></p></div><div class="join"><input data-search aria-label="Cari produk" class="input input-sm join-item w-40 border-base-content/10 bg-base-200" placeholder="Cari produk" /><button data-search-button class="btn btn-sm join-item">Cari</button></div></div>
      <div data-products class="space-y-2"></div>
      <div class="mt-4 flex flex-wrap items-center justify-between gap-3"><button data-more class="btn btn-ghost btn-sm min-h-10">Muat produk lain</button><button data-boost class="btn btn-primary btn-sm min-h-10 gap-2"><span class="material-symbols-outlined text-base">north</span>Naikkan terpilih</button></div>
      <div class="mt-3 hidden rounded-lg border border-base-content/10 p-3 text-xs" data-action-result role="status"></div>
    </div>
    <details class="border-t border-base-content/10 p-5"><summary class="cursor-pointer text-sm font-black">Riwayat toko</summary><div class="mt-3 space-y-2" data-history></div></details>
  </article>
</template>

<script>
(() => {
  const grid = document.getElementById('boost-grid');
  const state = document.getElementById('boost-state');
  const template = document.getElementById('boost-card-template');
  const shops = <?= json_encode(array_map(static function ($shop) { return ['id' => (int)$shop['id'], 'name' => $shop['name'] ?? 'Toko tanpa nama']; }, $data['shops'] ?? []), JSON_UNESCAPED_UNICODE); ?>;
  if (!grid || !state || !template) return;
  const number = value => new Intl.NumberFormat('id-ID').format(Number(value || 0));
  const money = value => 'Rp ' + number(value);
  const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
  const localDate = value => value ? new Date(String(value).replace(' ', 'T') + 'Z').toLocaleString('id-ID', {dateStyle:'short', timeStyle:'short'}) : '-';
  const utcTimestamp = value => value ? new Date(String(value).replace(' ', 'T') + 'Z').getTime() : 0;
  const countdown = seconds => { const total = Math.max(0, Math.floor(seconds)); const hours = Math.floor(total / 3600); const minutes = Math.floor((total % 3600) / 60); const secs = total % 60; return (hours ? hours + 'j ' : '') + String(minutes).padStart(2, '0') + 'm ' + String(secs).padStart(2, '0') + 'd'; };
  const stateByShop = new Map();

  function createCard(shop) {
    const node = template.content.cloneNode(true);
    const card = node.querySelector('[data-card]');
    card.dataset.shopId = shop.id;
    card.querySelector('[data-shop-name]').textContent = shop.name || 'Toko tanpa nama';
    card.querySelector('[data-shop-status]').textContent = 'Memuat data produk…';
    card.querySelector('[data-search-button]').addEventListener('click', () => loadShop(shop.id, 1, false));
    card.querySelector('[data-search]').addEventListener('keydown', event => { if (event.key === 'Enter') loadShop(shop.id, 1, false); });
    card.querySelector('[data-more]').addEventListener('click', () => {
      const local = stateByShop.get(shop.id) || {};
      loadShop(shop.id, (local.page || 1) + 1, true);
    });
    card.querySelector('[data-boost]').addEventListener('click', () => runBoost(shop.id));
    grid.appendChild(node);
  }

  function cardFor(shopId) { return grid.querySelector('[data-shop-id="' + shopId + '"]'); }

  function renderSummary(card, payload) {
    const summary = payload.summary || {};
    card.querySelector('[data-used]').textContent = number(summary.used_count) + '/5';
    card.querySelector('[data-remaining]').textContent = number(summary.remaining_count);
    const cooldown = card.querySelector('[data-cooldown]');
    const quotaEmpty = Number(summary.remaining_count || 0) < 1;
    cooldown.textContent = summary.batch_active ? 'Batch sedang diproses…' : (quotaEmpty ? 'Kuota kembali ' + localDate(summary.quota_reset_at) : 'Siap digunakan');
    cooldown.className = (summary.batch_active || quotaEmpty) ? 'mt-1 text-sm font-bold text-warning' : 'mt-1 text-sm font-bold text-success';
    const session = card.querySelector('[data-session-status]');
    const sessionExpired = !payload.shop || ['expired', 'disconnected', 'unknown'].includes(payload.shop.session_status);
    if (sessionExpired) { session.textContent = payload.shop && payload.shop.session_status === 'expired' ? 'Sesi habis' : 'Sesi perlu dicek'; session.className = 'badge badge-warning badge-sm'; }
    else { session.textContent = 'Sesi tersedia'; session.className = 'badge badge-success badge-sm text-white'; }
    const button = card.querySelector('[data-boost]');
    button.disabled = sessionExpired || !!summary.batch_active || quotaEmpty;
    const help = card.querySelector('[data-selection-help]');
    help.textContent = sessionExpired ? 'Perbarui koneksi toko untuk menaikkan produk.' : (summary.batch_active ? 'Produk sedang diproses. Tunggu sampai selesai.' : (quotaEmpty ? 'Kuota habis. Tunggu sampai kuota kembali.' : (Number(summary.remaining_count) < 5 ? 'Sisa kuota: ' + number(summary.remaining_count) + ' produk.' : '')));
    help.hidden = !help.textContent;
  }

  function renderProducts(card, products, append) {
    const container = card.querySelector('[data-products]');
    if (!append) container.innerHTML = '';
    if (!products.length && !append) { container.innerHTML = '<div class="rounded-lg bg-base-200/60 p-3 text-xs text-base-content/50">Belum ada produk lokal. Jalankan sinkronisasi produk terlebih dahulu.</div>'; return; }
    products.forEach(product => {
      const boostCooldown = product.boost_cooldown || {};
      const localCooldown = !!boostCooldown.cooldown_active;
      const shopeeDisabled = !!product.disabled_boost_button;
      const disabled = localCooldown || shopeeDisabled ? ' disabled' : '';
      const note = localCooldown ? 'Cooldown' : (shopeeDisabled ? 'Tidak tersedia' : (product.show_boost_button ? 'Siap dinaikkan' : 'Belum tersedia'));
      const statusClass = localCooldown ? 'badge-warning text-warning-content' : (shopeeDisabled ? 'badge-ghost' : 'badge-success text-white');
      const statusText = localCooldown ? 'Cooldown ' + countdown(boostCooldown.cooldown_seconds || 0) : (shopeeDisabled ? 'Belum tersedia' : (boostCooldown.last_boost_at ? 'Siap dinaikkan lagi' : 'Siap dinaikkan'));
      const nextText = localCooldown ? 'Naik lagi ' + localDate(boostCooldown.next_boost_at) : (boostCooldown.last_boost_at ? 'Terakhir ' + localDate(boostCooldown.last_boost_at) : '');
      const row = document.createElement('label');
      row.className = 'flex cursor-pointer items-center gap-3 rounded-xl border border-base-content/10 p-3 transition hover:border-primary/30 hover:bg-base-200/60' + (disabled ? ' cursor-not-allowed opacity-70' : '');
      row.innerHTML = '<input type="checkbox" class="checkbox checkbox-primary checkbox-sm" data-product-id="' + esc(product.id) + '" data-local-cooldown="' + (localCooldown ? '1' : '0') + '" data-shopee-disabled="' + (shopeeDisabled ? '1' : '0') + '"' + disabled + ' /><span class="grid h-10 w-10 shrink-0 place-items-center overflow-hidden rounded-lg bg-base-200">' + (product.cover_image ? '<img src="https://cf.shopee.co.id/file/' + esc(product.cover_image) + '" class="h-full w-full object-cover" alt="" />' : '<span class="material-symbols-outlined text-base-content/40">inventory_2</span>') + '</span><span class="min-w-0 flex-1"><span class="block truncate text-xs font-bold" title="' + esc(product.name) + '">' + esc(product.name) + '</span><span class="mt-1 block text-[10px] text-base-content/50">' + money(product.selling_price_min || product.price_min) + ' · stok ' + number(product.total_stock) + ' · terjual ' + number(product.sold_count) + '</span></span><span class="flex shrink-0 flex-col items-end gap-1 text-right"><span data-product-status class="badge ' + statusClass + ' badge-xs whitespace-nowrap">' + esc(statusText) + '</span><span data-product-next class="text-[9px] font-semibold text-base-content/50">' + esc(nextText) + '</span></span>';
      row.dataset.nextBoost = boostCooldown.next_boost_at || '';
      container.appendChild(row);
    });
    container.querySelectorAll('input[data-product-id]').forEach(input => input.onchange = () => enforceSelection(card, input));
    updateProductCooldowns(card);
  }

  function updateProductCooldowns(card) {
    card.querySelectorAll('[data-next-boost], label[data-next-boost], [data-product-status]').forEach(status => {
      const row = status.closest('label');
      if (!row || !row.dataset.nextBoost) return;
      const nextTimestamp = utcTimestamp(row.dataset.nextBoost);
      const remaining = Math.max(0, Math.ceil((nextTimestamp - Date.now()) / 1000));
      const badge = row.querySelector('[data-product-status]');
      const next = row.querySelector('[data-product-next]');
      const input = row.querySelector('input[data-product-id]');
      if (remaining > 0) {
        badge.textContent = 'Cooldown ' + countdown(remaining);
        badge.className = 'badge badge-warning text-warning-content badge-xs whitespace-nowrap';
        if (next) next.textContent = 'Naik lagi ' + localDate(row.dataset.nextBoost);
        if (input) input.disabled = true;
        row.classList.add('cursor-not-allowed', 'opacity-70');
      } else if (input && input.dataset.localCooldown === '1') {
        badge.textContent = 'Siap dinaikkan lagi';
        badge.className = 'badge badge-success text-white badge-xs whitespace-nowrap';
        if (next) next.textContent = 'Cooldown selesai';
        input.dataset.localCooldown = '0';
        input.disabled = input.dataset.shopeeDisabled === '1';
        row.classList.toggle('cursor-not-allowed', input.disabled);
        row.classList.toggle('opacity-70', input.disabled);
      }
    });
  }

  function enforceSelection(card, changedInput) {
    const checked = [...card.querySelectorAll('input[data-product-id]:checked')];
    const max = 5;
    if (checked.length > max) changedInput.checked = false;
    card.querySelector('[data-selection-count]').textContent = card.querySelectorAll('input[data-product-id]:checked').length + '/5 dipilih';
  }

  function renderHistory(card, history) {
    const container = card.querySelector('[data-history]');
    container.innerHTML = (history || []).map(run => '<div class="rounded-lg border border-base-content/10 p-3"><div class="flex flex-wrap items-center justify-between gap-2"><span class="text-xs font-bold">' + localDate(run.started_at) + '</span><span class="badge badge-' + (run.status === 'completed' ? 'success' : (run.status === 'failed' ? 'error' : 'warning')) + ' badge-xs">' + esc(run.status) + '</span></div><div class="mt-1 text-[10px] text-base-content/55">' + number(run.success_count) + ' berhasil · ' + number(run.failed_count) + ' gagal · ' + number(run.unknown_count) + ' tidak diketahui</div>' + (run.items || []).map(item => '<div class="mt-2 flex justify-between gap-2 text-[10px]"><span class="truncate">' + esc(item.product_name || ('Produk #' + item.product_id)) + '</span><span class="shrink-0">' + esc(item.status) + '</span></div>').join('') + '</div>').join('') || '<div class="text-xs text-base-content/50">Belum ada riwayat.</div>';
  }

  async function loadShop(shopId, page = 1, append = false) {
    const card = cardFor(shopId);
    if (!card) return;
    const search = card.querySelector('[data-search]').value.trim();
    const local = stateByShop.get(shopId) || {};
    const response = await fetch('<?= burl; ?>/procproducts/boost_products?shop_id=' + encodeURIComponent(shopId) + '&page=' + page + '&limit=12&search=' + encodeURIComponent(search), {headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}, cache:'no-store'});
    const payload = await response.json();
    if (!response.ok || payload.status !== 'success') throw new Error(payload.message || 'Data produk gagal dimuat.');
    stateByShop.set(shopId, {page, products: append ? (local.products || []).concat(payload.products || []) : (payload.products || [])});
    renderSummary(card, payload); renderProducts(card, payload.products || [], append); renderHistory(card, payload.history || []);
    card.querySelector('[data-selection-count]').textContent = card.querySelectorAll('input[data-product-id]:checked').length + '/5 dipilih';
    card.querySelector('[data-shop-status]').textContent = number(payload.total) + ' produk aktif';
    card.querySelector('[data-more]').classList.toggle('hidden', (page * 12) >= Number(payload.total || 0));
  }

  async function runBoost(shopId) {
    const card = cardFor(shopId);
    const selected = [...card.querySelectorAll('input[data-product-id]:checked')].map(input => input.dataset.productId);
    const result = card.querySelector('[data-action-result]');
    if (!selected.length) { result.className = 'mt-3 rounded-lg border border-warning/30 bg-warning/10 p-3 text-xs text-warning'; result.textContent = 'Pilih minimal satu produk.'; return; }
    if (selected.length > 5) return;
    const button = card.querySelector('[data-boost]'); button.disabled = true; result.className = 'mt-3 rounded-lg border border-base-content/10 bg-base-200/60 p-3 text-xs'; result.textContent = 'Memproses ' + selected.length + ' produk satu per satu…';
    const form = new FormData(); form.append('shop_id', shopId); form.append('product_ids', JSON.stringify(selected));
    try {
      const response = await fetch('<?= burl; ?>/procproducts/boost', {method:'POST', headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}, body:form});
      const payload = await response.json();
      if (!response.ok || payload.status !== 'success') throw new Error(payload.message || 'Naikkan produk gagal.');
      result.className = 'mt-3 rounded-lg border border-success/30 bg-success/10 p-3 text-xs text-success'; result.textContent = 'Selesai: ' + number(payload.result.success_count) + ' berhasil, ' + number(payload.result.failed_count) + ' gagal, ' + number(payload.result.unknown_count) + ' tidak diketahui.';
      await loadShop(shopId, 1, false);
    } catch (error) { result.className = 'mt-3 rounded-lg border border-error/30 bg-error/10 p-3 text-xs text-error'; result.textContent = error.message || 'Naikkan produk gagal.'; button.disabled = false; }
  }

  shops.forEach(createCard);
  if (!shops.length) { state.textContent = 'Belum ada toko terhubung.'; return; }
  setInterval(() => grid.querySelectorAll('[data-card]').forEach(updateProductCooldowns), 1000);
  Promise.all(shops.map(shop => loadShop(shop.id))).then(() => { state.hidden = true; }).catch(error => { state.className = 'mb-5 rounded-xl border border-error/20 bg-error/5 p-4 text-sm text-error'; state.textContent = error.message || 'Data naikkan produk gagal dimuat.'; });
})();
</script>
