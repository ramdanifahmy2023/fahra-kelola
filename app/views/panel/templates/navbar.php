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
