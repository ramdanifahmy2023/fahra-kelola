<div class="navbar panel-navbar sticky top-0 z-30 min-h-14 border-b border-base-content/10 bg-base-100 px-3 sm:px-5">
  <div class="flex-none lg:hidden">
    <button type="button" id="panel-menu-button" aria-label="Buka menu" aria-controls="panel-drawer" aria-expanded="false" class="btn btn-ghost btn-square">
      <span class="material-symbols-outlined" aria-hidden="true">menu</span>
    </button>
  </div>
  <div class="flex-1 min-w-0">
    <a href="<?= burl; ?>/panel" class="panel-mobile-brand btn btn-ghost px-2 text-lg font-black tracking-tight text-primary lg:hidden"><?= app_name; ?></a>
  </div>
  <div class="panel-navbar-actions flex-none flex items-center gap-1.5 sm:gap-2">
    <button type="button" id="quick-search-button" class="btn btn-ghost btn-square" aria-label="Cari pesanan, produk, atau pelanggan" aria-haspopup="dialog" aria-controls="quick-search" aria-expanded="false"><span class="material-symbols-outlined" aria-hidden="true">search</span></button>
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
    <div id="notification-menu" data-endpoint="<?= htmlspecialchars(burl.'/procnotifications',ENT_QUOTES,'UTF-8'); ?>" data-base="<?= htmlspecialchars(burl,ENT_QUOTES,'UTF-8'); ?>" data-csrf="<?= htmlspecialchars(authCsrfToken(),ENT_QUOTES,'UTF-8'); ?>" data-user="<?= (int)(authUser()['id'] ?? 0); ?>">
      <button type="button" id="notification-button" class="btn btn-ghost btn-square" aria-label="Notifikasi" aria-expanded="false" aria-controls="notification-panel">
        <span class="material-symbols-outlined notification-bell-icon" aria-hidden="true">notifications</span>
        <span id="notification-badge" hidden>0</span>
      </button>
      <span id="notification-live" class="sr-only" role="status" aria-atomic="true"></span>
      <section id="notification-panel" aria-label="Notifikasi" hidden>
        <header class="notification-heading"><div><h2>Notifikasi</h2><p id="notification-summary">Memuat notifikasi…</p></div><button type="button" id="notification-close" class="btn" aria-label="Tutup notifikasi"><span class="material-symbols-outlined" aria-hidden="true">close</span></button></header>
        <div class="notification-toolbar">
          <div class="notification-filters" role="group" aria-label="Tampilkan notifikasi"><button type="button" data-notification-filter="new" aria-pressed="true">Baru</button><button type="button" data-notification-filter="active" aria-pressed="false">Semua aktif</button></div>
          <button type="button" id="notification-reload" class="btn" aria-label="Muat ulang notifikasi"><span class="material-symbols-outlined" aria-hidden="true">refresh</span></button>
        </div>
        <div class="notification-sound"><button type="button" id="notification-sound-toggle" class="btn" aria-pressed="false" aria-describedby="notification-sound-status">Aktifkan bunyi chat</button><p id="notification-sound-status">Bunyi hanya untuk chat baru saat Shopdash terbuka.</p></div>
        <div class="notification-view-options"><button type="button" id="notification-back" class="btn" hidden>Semua kelompok</button><label><input type="checkbox" id="notification-urgent" class="checkbox">Mendesak saja</label></div>
        <p id="notification-error" role="alert" tabindex="-1" hidden></p>
        <p id="notification-source-warning" hidden>Pemeriksaan terbaru belum berhasil. Menampilkan notifikasi tersimpan.</p>
        <div id="notification-list"><p class="notification-empty">Memuat notifikasi…</p></div>
        <footer class="notification-footer"><button type="button" id="notification-mark-all" class="btn">Tandai daftar dibaca</button><p id="notification-read-help">Dibaca dan pengingat nanti hanya untuk akun Anda. Masalah tetap aktif sampai selesai.</p><div class="notification-pagination"><button type="button" id="notification-prev" class="btn" aria-label="Halaman notifikasi sebelumnya" disabled><span class="material-symbols-outlined" aria-hidden="true">chevron_left</span></button><span id="notification-page-status"></span><button type="button" id="notification-next" class="btn" aria-label="Halaman notifikasi berikutnya" disabled><span class="material-symbols-outlined" aria-hidden="true">chevron_right</span></button></div></footer>
      </section>
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
        <div class="panel-user-avatar grid h-7 w-7 place-items-center rounded-md text-xs font-black"><?= htmlspecialchars($authInitial, ENT_QUOTES); ?></div>
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

})();
</script>
<script src="<?= assets; ?>/js/notifications.js?v=<?= filemtime(__DIR__.'/../../../../public/assets/js/notifications.js'); ?>" defer></script>

<?php require __DIR__.'/quick-search.php'; ?>
