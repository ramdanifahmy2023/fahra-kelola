<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
  <div>
    <div class="mb-1 flex items-center gap-2 text-[10px] font-bold uppercase tracking-[0.16em] text-primary"><span class="material-symbols-outlined text-sm">local_activity</span>Promotion operations</div>
    <h2 class="text-2xl font-black tracking-tight text-base-content">Voucher & flash sale toko</h2>
  </div>
  <button id="promotion-refresh" type="button" class="btn btn-sm min-h-11 gap-2 rounded-lg border-base-content/10 bg-base-100"><span class="material-symbols-outlined text-base">refresh</span>Perbarui</button>
</div>

<div id="promotion-state" class="mb-5 rounded-xl border border-base-content/10 bg-base-100 p-4 text-sm text-base-content/60">Memuat data promosi setiap toko…</div>
<div id="promotion-session-warning" class="mb-5 hidden rounded-xl border border-warning/30 bg-warning/10 p-4 text-sm text-warning-content" role="alert"></div>
<div id="promotion-grid" class="grid grid-cols-1 gap-5 xl:grid-cols-2"></div>

<template id="promotion-card-template">
  <article class="overflow-hidden rounded-2xl border border-base-content/10 bg-base-100 shadow-sm">
    <div class="flex flex-wrap items-start justify-between gap-3 border-b border-base-content/10 p-5">
      <div class="shop-identity"><span class="shop-logo" data-shop-logo></span><div class="min-w-0"><div class="break-words text-base font-black" data-shop-name></div><div class="mt-1 text-xs text-base-content/50" data-shop-sync></div></div></div>
      <span class="badge badge-ghost badge-sm" data-status></span>
    </div>
    <div class="grid grid-cols-2 gap-4 p-5 sm:grid-cols-4">
      <div><div class="text-[10px] font-bold uppercase tracking-wide text-base-content/45">Voucher aktif</div><div class="mt-1 text-2xl font-black" data-voucher-count>-</div></div>
      <div><div class="text-[10px] font-bold uppercase tracking-wide text-base-content/45">Total voucher</div><div class="mt-1 text-2xl font-black" data-voucher-total>-</div></div>
      <div><div class="text-[10px] font-bold uppercase tracking-wide text-base-content/45">Flash sale aktif</div><div class="mt-1 text-2xl font-black" data-flash-count>-</div></div>
      <div><div class="text-[10px] font-bold uppercase tracking-wide text-base-content/45">Total slot</div><div class="mt-1 text-2xl font-black" data-flash-total>-</div></div>
    </div>
    <div class="grid grid-cols-1 gap-5 border-t border-base-content/10 p-5 lg:grid-cols-2">
      <section>
        <div class="mb-3 flex items-center justify-between"><h3 class="text-sm font-black">Voucher aktif</h3><span class="text-[11px] text-base-content/45" data-voucher-caption></span></div>
        <div class="space-y-2" data-vouchers></div>
      </section>
      <section>
        <div class="mb-3 flex items-center justify-between"><h3 class="text-sm font-black">Flash sale aktif</h3><span class="text-[11px] text-base-content/45">Berdasarkan waktu & produk aktif</span></div>
        <div class="space-y-2" data-flash-sales></div>
      </section>
    </div>
    <div class="border-t border-base-content/10 p-5" data-warning hidden><div class="rounded-lg bg-base-200/60 p-3 text-sm" data-note></div></div>
  </article>
</template>

<?php require __DIR__ . '/templates/shop-logos.php'; ?>
<script>
(() => {
  const grid = document.getElementById('promotion-grid');
  const state = document.getElementById('promotion-state');
  const refresh = document.getElementById('promotion-refresh');
  const template = document.getElementById('promotion-card-template');
  if (!grid || !state || !refresh || !template) return;
  const number = value => new Intl.NumberFormat('id-ID').format(Number(value || 0));
  const money = value => 'Rp ' + number(value);
  const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
  const date = unix => unix ? new Date(Number(unix) * 1000).toLocaleString('id-ID', {dateStyle:'short', timeStyle:'short'}) : '-';
  let inFlight = false;
  function voucherLabel(v) {
    if (v.discount_type === 'persen') return Number(v.discount).toLocaleString('id-ID', {maximumFractionDigits:2}) + '%';
    if (v.discount_type === 'koin_persen') return Number(v.discount).toLocaleString('id-ID', {maximumFractionDigits:2}) + '% koin';
    if (v.discount_type === 'koin') return number(v.discount) + ' koin';
    return money(v.discount);
  }
  async function load() {
    if (inFlight) return;
    inFlight = true; refresh.disabled = true; state.textContent = 'Memuat data promosi setiap toko…';
    try {
      const response = await fetch('<?= burl; ?>/procPromotions/summary', {headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}, cache:'no-store'});
      const payload = await response.json();
      if (!response.ok || payload.status !== 'success') throw new Error(payload.message || 'Data promosi gagal dimuat.');
      grid.innerHTML = '';
      const shops = payload.shops || [];
      if (!shops.length) { state.textContent = 'Belum ada toko terhubung untuk dipantau.'; return; }
      const expired = shops.filter(s => s.session_expired || s.session_status === 'expired');
      const warning = document.getElementById('promotion-session-warning');
      if (expired.length) { warning.className = 'mb-5 rounded-xl border border-warning/30 bg-warning/10 p-4 text-sm text-warning-content'; warning.innerHTML = '<strong>Sesi toko perlu diperbarui:</strong> ' + expired.map(s => esc(s.shop_name || ('Toko #' + s.shop_id))).join(', ') + '. <a class="font-bold underline" href="<?= burl; ?>/panel/shops">Perbarui cookie</a>'; }
      else { warning.className = 'mb-5 hidden rounded-xl border border-warning/30 bg-warning/10 p-4 text-sm text-warning-content'; warning.textContent = ''; }
      shops.forEach(shop => {
        const node = template.content.cloneNode(true), data = shop.summary || {};
        node.querySelector('[data-shop-name]').textContent = shop.shop_name || 'Toko tanpa nama';
        window.renderShopLogo(node.querySelector('[data-shop-logo]'), shop.shop_id);
        node.querySelector('[data-shop-sync]').textContent = shop.synced_at ? 'Diperbarui ' + new Date(shop.synced_at.replace(' ', 'T') + 'Z').toLocaleString('id-ID') : 'Belum pernah disinkronkan';
        const status = node.querySelector('[data-status]');
        if (shop.session_expired) { status.textContent = 'Sesi habis'; status.className = 'badge badge-warning badge-sm'; }
        else if (shop.status === 'ok' && !shop.stale) { status.textContent = 'Tersedia'; status.className = 'badge badge-success badge-sm text-white'; }
        else if (shop.status === 'ok') { status.textContent = 'Stale'; status.className = 'badge badge-warning badge-sm'; }
        else { status.textContent = shop.status === 'error' ? 'Gagal' : 'Menunggu'; status.className = 'badge badge-error badge-sm text-white'; }
        node.querySelector('[data-voucher-count]').textContent = number(data.active_voucher_count);
        node.querySelector('[data-voucher-total]').textContent = number(data.voucher_total);
        node.querySelector('[data-flash-count]').textContent = number(data.active_flash_sale_count);
        node.querySelector('[data-flash-total]').textContent = number(data.flash_sale_total);
        node.querySelector('[data-voucher-caption]').textContent = data.vouchers && data.vouchers.length ? 'Nama & nilai' : 'Tidak ada yang aktif';
        const vouchers = node.querySelector('[data-vouchers]');
        vouchers.innerHTML = (data.vouchers || []).slice(0, 8).map(v => '<div class="rounded-lg border border-base-content/10 p-3"><div class="flex items-start justify-between gap-3"><div class="min-w-0"><div class="truncate text-xs font-bold" title="' + esc(v.name) + '">' + esc(v.name) + '</div><div class="mt-1 text-[10px] text-base-content/50">' + date(v.start_time) + ' – ' + date(v.end_time) + '</div></div><span class="badge badge-primary badge-sm shrink-0">' + esc(voucherLabel(v)) + '</span></div><div class="mt-2 text-[10px] text-base-content/50">Min. belanja ' + money(v.min_price) + (v.usage_limit ? ' · Pemakaian ' + number(v.current_usage) + '/' + number(v.usage_limit) : '') + '</div></div>').join('') || '<div class="rounded-lg bg-base-200/60 p-3 text-xs text-base-content/50">Tidak ada voucher aktif.</div>';
        const flash = node.querySelector('[data-flash-sales]');
        flash.innerHTML = (data.active_flash_sales || []).map(s => '<div class="rounded-lg border border-base-content/10 p-3"><div class="flex items-center justify-between gap-3"><span class="text-xs font-bold">Flash sale #' + esc(s.id) + '</span><span class="badge badge-secondary badge-sm">' + number(s.enabled_item_count) + ' produk</span></div><div class="mt-1 text-[10px] text-base-content/50">' + date(s.start_time) + ' – ' + date(s.end_time) + '</div></div>').join('') || '<div class="rounded-lg bg-base-200/60 p-3 text-xs text-base-content/50">Tidak ada flash sale aktif.</div>';
        node.querySelector('[data-warning]').hidden = !shop.session_expired && shop.status !== 'error';
        if (shop.session_expired) node.querySelector('[data-note]').textContent = 'Sesi habis. Perbarui koneksi di halaman Toko.';
        else if (shop.status === 'error') node.querySelector('[data-note]').textContent = shop.error_message || 'Data promosi tidak tersedia.';
        grid.appendChild(node);
      });
      state.textContent = 'Data promosi per toko diperbarui ' + new Date().toLocaleTimeString('id-ID');
    } catch (error) { state.textContent = error.message || 'Data promosi gagal dimuat.'; state.className = 'mb-5 rounded-xl border border-error/20 bg-error/5 p-4 text-sm text-error'; }
    finally { inFlight = false; refresh.disabled = false; }
  }
  refresh.addEventListener('click', load); load(); window.setInterval(load, 300000);
})();
</script>
