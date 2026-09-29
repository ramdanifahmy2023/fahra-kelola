<div class="navbar panel-navbar sticky top-0 z-30 min-h-14 border-b border-base-content/10 bg-base-100/75 px-3 backdrop-blur-xl sm:px-5">
  <div class="flex-none lg:hidden">
    <button type="button" id="panel-menu-button" aria-label="Buka menu" aria-controls="panel-drawer" aria-expanded="false" class="btn btn-ghost btn-square">
      <span class="material-symbols-outlined" aria-hidden="true">menu</span>
    </button>
  </div>
  <div class="flex-1 min-w-0">
    <a href="<?= burl; ?>/panel" class="panel-mobile-brand btn btn-ghost px-2 text-lg font-black tracking-tight text-primary lg:hidden"><?= app_name; ?></a>
  </div>
  <div class="panel-navbar-actions flex-none flex items-center gap-1.5 sm:gap-2">
    <div class="dropdown dropdown-end" id="theme-menu">
      <button type="button" id="theme-menu-button" class="btn btn-ghost btn-square relative rounded-xl border border-transparent hover:border-primary/30" aria-label="Pilih tema" aria-expanded="false" aria-controls="theme-menu-list" tabindex="0">
        <span id="theme-menu-icon" class="material-symbols-outlined" aria-hidden="true">contrast</span>
      </button>
      <ul id="theme-menu-list" tabindex="-1" role="menu" aria-label="Pilihan tema" class="menu dropdown-content z-50 mt-2 w-48 rounded-2xl border border-base-content/10 bg-base-100 p-2 shadow-2xl">
        <li><button type="button" role="menuitemradio" data-theme-choice="system" aria-checked="false"><span class="material-symbols-outlined text-base">routine</span><span class="flex-1 text-left">Sistem</span><span class="theme-choice-check material-symbols-outlined hidden text-base text-primary">check</span></button></li>
        <li><button type="button" role="menuitemradio" data-theme-choice="light" aria-checked="false"><span class="material-symbols-outlined text-base">light_mode</span><span class="flex-1 text-left">Terang</span><span class="theme-choice-check material-symbols-outlined hidden text-base text-primary">check</span></button></li>
        <li><button type="button" role="menuitemradio" data-theme-choice="dark" aria-checked="false"><span class="material-symbols-outlined text-base">dark_mode</span><span class="flex-1 text-left">Gelap</span><span class="theme-choice-check material-symbols-outlined hidden text-base text-primary">check</span></button></li>
      </ul>
    </div>
    <div class="dropdown dropdown-end" id="notification-menu">
      <button type="button" id="notification-button" class="btn btn-ghost btn-square relative rounded-xl border border-transparent hover:border-primary/30" aria-label="Notifikasi" aria-expanded="false" aria-controls="notification-panel" tabindex="0">
        <span class="material-symbols-outlined notification-bell-icon" aria-hidden="true">notifications</span>
        <span id="notification-badge" class="badge badge-error absolute hidden text-error-content" aria-live="polite" aria-atomic="true">0</span>
      </button>
      <div id="notification-panel" tabindex="-1" aria-label="Daftar notifikasi" class="dropdown-content z-50 mt-2 overflow-hidden rounded-2xl border border-base-content/10 bg-base-100 p-0 shadow-2xl">
        <div class="flex items-center justify-between gap-3 border-b border-base-content/10 px-4 py-3"><div class="min-w-0"><div class="text-sm font-black">Notifikasi</div><div id="notification-summary" aria-live="polite" class="text-[10px] text-base-content/60">Memuat...</div></div><button type="button" id="notification-mark-all" class="btn btn-ghost btn-sm min-h-10 shrink-0 px-3">Tandai dibaca</button></div>
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
    <?php $authUser = authUser() ?? []; $authName = trim((string)($authUser['name'] ?? 'Pengguna')); $authEmail = (string)($authUser['email'] ?? ''); $authInitial = strtoupper(substr($authName !== '' ? $authName : 'P', 0, 1)); ?>
    <div class="dropdown dropdown-end">
      <div tabindex="0" role="button" aria-label="Menu akun" class="panel-user-trigger btn btn-ghost h-9 min-h-9 gap-2 rounded-lg border border-base-content/10 px-1.5 pr-2 hover:border-primary/30">
        <div class="grid h-7 w-7 place-items-center rounded-md bg-gradient-to-br from-primary to-secondary text-xs font-black text-primary-content"><?= htmlspecialchars($authInitial, ENT_QUOTES); ?></div>
        <span class="hidden max-w-28 truncate text-xs font-semibold sm:block"><?= htmlspecialchars($authName, ENT_QUOTES); ?></span>
        <span class="panel-user-chevron material-symbols-outlined hidden text-base-content/50 sm:block" aria-hidden="true">expand_more</span>
      </div>
      <ul tabindex="0" class="menu dropdown-content z-[1] mt-2 w-52 rounded-xl border border-base-content/10 bg-base-100 p-2 shadow-xl">
        <li class="menu-title px-3 py-2"><span class="text-xs font-bold text-base-content"><?= htmlspecialchars($authName, ENT_QUOTES); ?></span><span class="text-[10px] font-normal normal-case text-base-content/50"><?= htmlspecialchars($authEmail, ENT_QUOTES); ?></span></li>
        <li class="mt-1 text-error"><a href="<?= burl; ?>/auth/logout"><span class="material-symbols-outlined text-base">logout</span>Keluar</a></li>
      </ul>
    </div>
  </div>
</div>
<style>
  #notification-button {
    width: 2.75rem;
    height: 2.75rem;
    min-height: 2.75rem;
    overflow: visible;
  }
  #notification-button:focus-visible,
  #notification-mark-all:focus-visible,
  #notification-panel a:focus-visible {
    outline: 2px solid var(--color-primary);
    outline-offset: 2px;
  }
  .notification-bell-icon { font-size: 1.45rem; line-height: 1; }
  #notification-badge {
    top: -0.2rem;
    right: -0.25rem;
    z-index: 2;
    min-width: 1.2rem;
    height: 1.2rem;
    padding: 0 0.25rem;
    border: 2px solid var(--color-base-100);
    border-radius: 999px;
    font-size: 0.625rem;
    font-weight: 800;
    line-height: 1;
    white-space: nowrap;
    box-shadow: 0 1px 3px rgb(0 0 0 / 0.18);
  }
  #notification-panel {
    width: min(23rem, calc(100vw - 1rem));
    max-height: min(32rem, calc(100vh - 5rem));
    max-height: min(32rem, calc(100dvh - 5rem - env(safe-area-inset-top)));
  }
  #notification-list { overscroll-behavior: contain; -webkit-overflow-scrolling: touch; }
  @media (max-width: 639px) {
    .panel-navbar-actions { gap: 0.25rem; }
    #notification-button { width: 2.75rem; height: 2.75rem; }
    #notification-panel {
      position: fixed;
      inset: calc(3.75rem + env(safe-area-inset-top)) 0.5rem auto auto;
      left: 0.5rem;
      right: 0.5rem;
      width: auto;
      max-width: none;
      max-height: calc(100dvh - 4.5rem - env(safe-area-inset-top));
      margin: 0;
    }
    #notification-panel > div:first-child { padding: 0.75rem; }
    #notification-list { max-height: calc(100dvh - 11rem - env(safe-area-inset-top)); }
  }
  @media (prefers-reduced-motion: reduce) {
    #notification-panel, #notification-panel * { scroll-behavior: auto !important; transition-duration: 0.01ms !important; }
  }
</style>
<script>
(() => {
  const drawer = document.getElementById('panel-drawer');
  const menuButton = document.getElementById('panel-menu-button');
  const reflectMenu = () => {
    menuButton.setAttribute('aria-expanded', String(drawer.checked));
    menuButton.setAttribute('aria-label', drawer.checked ? 'Tutup menu' : 'Buka menu');
  };
  menuButton.addEventListener('click', () => { drawer.checked = !drawer.checked; reflectMenu(); });
  drawer.addEventListener('change', reflectMenu);
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && drawer.checked) {
      drawer.checked = false;
      reflectMenu();
      menuButton.focus();
    }
  });
  const themeButton = document.getElementById('theme-menu-button');
  const themeMenu = document.getElementById('theme-menu');
  const themeIcon = document.getElementById('theme-menu-icon');
  const themeChoices = Array.from(document.querySelectorAll('[data-theme-choice]'));
  const themeIcons = { system: 'routine', light: 'light_mode', dark: 'dark_mode' };
  const themeLabels = { system: 'Sistem', light: 'Terang', dark: 'Gelap' };
  function renderTheme(mode) {
    const selected = mode || window.shopdashTheme?.getMode?.() || 'system';
    if (themeIcon) themeIcon.textContent = themeIcons[selected] || 'contrast';
    if (themeButton) themeButton.setAttribute('aria-label', 'Pilih tema, ' + (themeLabels[selected] || 'Sistem'));
    themeChoices.forEach(choice => {
      const active = choice.dataset.themeChoice === selected;
      choice.setAttribute('aria-checked', active ? 'true' : 'false');
      choice.querySelector('.theme-choice-check')?.classList.toggle('hidden', !active);
    });
  }
  themeChoices.forEach(choice => choice.addEventListener('click', () => window.shopdashTheme?.setMode(choice.dataset.themeChoice)));
  window.addEventListener('shopdash:theme', event => renderTheme(event.detail?.mode));
  renderTheme();

  const badge = document.getElementById('notification-badge');
  const summary = document.getElementById('notification-summary');
  const list = document.getElementById('notification-list');
  const markAll = document.getElementById('notification-mark-all');
  const number = value => new Intl.NumberFormat('id-ID').format(Number(value || 0));
  const esc = value => String(value ?? '').replace(/[&<>'"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[c]));
  const date = value => value ? new Date(String(value).replace(' ', 'T') + 'Z').toLocaleString('id-ID', {day:'2-digit', month:'short', hour:'2-digit', minute:'2-digit'}) : '-';
  function render(payload) {
    const unread = Number(payload.unread_count || 0);
    if (badge) {
      badge.textContent = unread > 99 ? '99+' : String(unread);
      badge.classList.toggle('hidden', unread < 1);
      const accessibleCount = unread > 99 ? '99 lebih' : number(unread);
      document.getElementById('notification-button')?.setAttribute('aria-label', unread ? `Notifikasi, ${accessibleCount} belum dibaca` : 'Notifikasi');
    }
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
