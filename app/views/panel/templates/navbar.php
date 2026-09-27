<div class="navbar sticky top-0 z-30 min-h-14 border-b border-base-content/10 bg-base-100/75 px-3 backdrop-blur-xl sm:px-5">
  <div class="flex-none lg:hidden">
    <label for="panel-drawer" aria-label="open sidebar" class="btn btn-ghost btn-square btn-sm">
      <span class="material-symbols-outlined">menu</span>
    </label>
  </div>
  <div class="flex-1 min-w-0">
    <div class="hidden lg:flex items-center gap-2 text-xs font-medium text-base-content/55">
      <span class="material-symbols-outlined text-sm text-primary">space_dashboard</span>
      <span>Operations workspace</span>
    </div>
    <a class="btn btn-ghost px-2 text-lg font-black tracking-tight text-primary lg:hidden"><?= app_name; ?></a>
  </div>
  <div class="flex-none flex items-center gap-1.5 sm:gap-2">
    <div class="dropdown dropdown-end" id="notification-menu">
      <button type="button" id="notification-button" class="btn btn-ghost btn-square btn-sm relative rounded-lg border border-transparent hover:border-primary/30" aria-label="Notifikasi" aria-expanded="false" tabindex="0">
        <span class="material-symbols-outlined text-[21px]">notifications</span>
        <span id="notification-badge" class="badge badge-error badge-xs absolute -right-0.5 -top-0.5 hidden min-w-4 px-1 text-[9px] text-error-content">0</span>
      </button>
      <div id="notification-panel" tabindex="0" class="dropdown-content z-50 mt-2 w-[min(22rem,calc(100vw-1.5rem))] overflow-hidden rounded-2xl border border-base-content/10 bg-base-100 p-0 shadow-2xl">
        <div class="flex items-center justify-between border-b border-base-content/10 px-4 py-3"><div><div class="text-sm font-black">Notifikasi</div><div id="notification-summary" class="text-[10px] text-base-content/50">Memuat...</div></div><button type="button" id="notification-mark-all" class="btn btn-ghost btn-xs">Tandai dibaca</button></div>
        <div id="notification-list" class="max-h-[min(24rem,70vh)] overflow-y-auto p-2"><div class="px-3 py-8 text-center text-xs text-base-content/50">Memuat notifikasi...</div></div>
        <div class="border-t border-base-content/10 px-4 py-2.5 text-center"><a href="<?= burl; ?>/panel" class="text-[11px] font-bold text-primary">Buka dashboard</a></div>
      </div>
    </div>
    <div id="sync-detail-card" class="hidden h-9 max-w-[205px] items-center gap-2 rounded-lg border border-primary/15 bg-primary/8 px-2.5 transition-all duration-300">
      <span class="loading loading-spinner loading-xs text-primary"></span>
      <div class="flex min-w-0 flex-col">
        <span id="sync-detail-text" class="truncate text-[10px] font-bold leading-tight text-base-content">Sinkronisasi</span>
        <span id="sync-detail-id" class="truncate font-mono text-[9px] leading-tight text-base-content/50"></span>
      </div>
      <div id="sync-detail-badge" class="badge badge-primary badge-xs shrink-0 font-bold">0%</div>
    </div>
    <progress id="sync-detail-progress" class="hidden" value="0" max="100"></progress>
    <div class="dropdown dropdown-end">
      <div tabindex="0" role="button" class="btn btn-ghost h-9 min-h-9 gap-2 rounded-lg border border-base-content/10 px-1.5 pr-2 hover:border-primary/30">
        <div class="grid h-7 w-7 place-items-center rounded-md bg-gradient-to-br from-primary to-secondary text-xs font-black text-primary-content">A</div>
        <span class="hidden text-xs font-semibold sm:block">Admin</span>
        <span class="material-symbols-outlined hidden text-base-content/50 sm:block">expand_more</span>
      </div>
      <ul tabindex="0" class="menu dropdown-content z-[1] mt-2 w-52 rounded-xl border border-base-content/10 bg-base-100 p-2 shadow-xl">
        <li class="menu-title px-3 py-2"><span class="text-xs font-bold text-base-content">Admin User</span><span class="text-[10px] font-normal normal-case text-base-content/50">admin@shopdash.com</span></li>
        <li><a><span class="material-symbols-outlined text-base">person</span>Profil</a></li>
        <li><a><span class="material-symbols-outlined text-base">settings</span>Pengaturan</a></li>
        <li class="mt-1 text-error"><a><span class="material-symbols-outlined text-base">logout</span>Keluar</a></li>
      </ul>
    </div>
  </div>
</div>
<script>
(() => {
  const badge = document.getElementById('notification-badge');
  const summary = document.getElementById('notification-summary');
  const list = document.getElementById('notification-list');
  const markAll = document.getElementById('notification-mark-all');
  const number = value => new Intl.NumberFormat('id-ID').format(Number(value || 0));
  const esc = value => String(value ?? '').replace(/[&<>'"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[c]));
  const date = value => value ? new Date(String(value).replace(' ', 'T') + 'Z').toLocaleString('id-ID', {day:'2-digit', month:'short', hour:'2-digit', minute:'2-digit'}) : '-';
  function render(payload) {
    const unread = Number(payload.unread_count || 0);
    if (badge) { badge.textContent = unread > 99 ? '99+' : String(unread); badge.classList.toggle('hidden', unread < 1); }
    const s = payload.summary || {};
    if (summary) summary.textContent = unread ? `${number(unread)} belum dibaca · ${number(s.urgent)} urgent` : 'Tidak ada notifikasi baru';
    const rows = payload.notifications || [];
    if (list) list.innerHTML = rows.length ? rows.map(item => {
      const urgent = item.severity === 'urgent';
      const href = '<?= burl; ?>/panel/products?shop_id=' + encodeURIComponent(item.shop_id) + '&stock=critical&highlight=' + encodeURIComponent(item.entity_id || '');
      return `<a href="${href}" data-alert-id="${item.id}" class="block rounded-xl px-3 py-3 transition-colors hover:bg-base-200"><div class="flex items-start gap-2.5"><span class="material-symbols-outlined mt-0.5 text-[19px] ${urgent ? 'text-error' : 'text-warning'}">${urgent ? 'error' : 'warning'}</span><div class="min-w-0 flex-1"><div class="flex items-start justify-between gap-2"><strong class="truncate text-xs">${esc(item.product_name || 'Produk')}</strong><span class="badge ${urgent ? 'badge-error' : 'badge-warning'} badge-xs shrink-0">${urgent ? 'Habis' : 'Kritis'}</span></div><div class="mt-1 text-[10px] text-base-content/55">${esc(item.shop_name || ('Toko #' + item.shop_id))} · stok <strong class="text-base-content">${number(item.total_stock)}</strong></div><div class="mt-1 text-[10px] text-base-content/45">${date(item.last_seen_at)}</div></div></div></a>`;
    }).join('') : '<div class="px-3 py-8 text-center text-xs text-base-content/50"><span class="material-symbols-outlined mb-1 block text-3xl text-success">check_circle</span>Semua stok aman.</div>';
  }
  function load() { fetch('<?= burl; ?>/procnotifications/summary', {headers:{'X-Requested-With':'XMLHttpRequest'}, cache:'no-store'}).then(r => r.json()).then(data => { if (data.status === 'success') render(data); }).catch(() => { if (summary) summary.textContent = 'Notifikasi tidak tersedia'; }); }
  document.addEventListener('click', event => { const item = event.target.closest('[data-alert-id]'); if (!item) return; const fd = new FormData(); fd.append('id', item.dataset.alertId); fetch('<?= burl; ?>/procnotifications/acknowledge', {method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'}, body:fd}).catch(() => {}); });
  if (markAll) markAll.addEventListener('click', event => { event.preventDefault(); event.stopPropagation(); fetch('<?= burl; ?>/procnotifications/acknowledge_all', {method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'}}).then(() => load()).catch(() => {}); });
  load();
  window.setInterval(load, 30000);
})();
</script>
