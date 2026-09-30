<?php
$active = $data['active_menu'] ?? '';
$activeClass = 'bg-primary text-primary-content';
$inactiveClass = 'text-neutral-content/65 hover:bg-neutral-content/10 hover:text-neutral-content';
$navItemClass = 'flex h-10 items-center gap-3 rounded-lg px-3 text-[12px] font-semibold transition-colors';
?>
<aside class="flex min-h-screen w-64 flex-col overflow-hidden border-r border-neutral-content/10 bg-neutral text-neutral-content">
  <div class="flex h-16 shrink-0 items-center gap-3 border-b border-neutral-content/10 px-4">
    <div class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-primary text-primary-content shadow-lg shadow-primary/20">
      <span class="material-symbols-outlined text-lg">bolt</span>
    </div>
    <div class="min-w-0">
      <div class="tooltip tooltip-right truncate text-base font-black tracking-tight" data-tip="<?= app_name; ?>"><?= app_name; ?></div>
    </div>
  </div>

  <nav class="flex-1 overflow-y-auto px-3 py-5">
    <p class="mb-2 px-3 text-[9px] font-extrabold uppercase tracking-[0.18em] text-neutral-content/35">Menu utama</p>
    <div class="space-y-1">
      <a href="<?= burl; ?>/panel" class="<?= $navItemClass; ?> <?= ($active == 'dashboard') ? $activeClass : $inactiveClass; ?>" <?= $active == 'dashboard' ? 'aria-current="page"' : ''; ?>><span class="material-symbols-outlined text-[19px]">grid_view</span><span>Dashboard</span></a>
      <a href="<?= burl; ?>/panel/products" class="<?= $navItemClass; ?> <?= ($active == 'products') ? $activeClass : $inactiveClass; ?>" <?= $active == 'products' ? 'aria-current="page"' : ''; ?>><span class="material-symbols-outlined text-[19px]">inventory_2</span><span>Produk</span></a>
      <a href="<?= burl; ?>/panel/boost" class="<?= $navItemClass; ?> <?= ($active == 'boost') ? $activeClass : $inactiveClass; ?>" <?= $active == 'boost' ? 'aria-current="page"' : ''; ?>><span class="material-symbols-outlined text-[19px]">north</span><span>Naikkan Produk</span></a>
      <a href="<?= burl; ?>/panel/orders" class="<?= $navItemClass; ?> <?= ($active == 'orders') ? $activeClass : $inactiveClass; ?>"><span class="material-symbols-outlined text-[19px]">receipt_long</span><span>Pesanan</span></a>
      <a href="<?= burl; ?>/panel/customers" class="<?= $navItemClass; ?> <?= ($active == 'customers') ? $activeClass : $inactiveClass; ?>"><span class="material-symbols-outlined text-[19px]">group</span><span>Pelanggan</span></a>
      <a href="<?= burl; ?>/panel/ads" class="<?= $navItemClass; ?> <?= ($active == 'ads') ? $activeClass : $inactiveClass; ?>"><span class="material-symbols-outlined text-[19px]">campaign</span><span>Iklan</span></a>
      <a href="<?= burl; ?>/panel/promotions" class="<?= $navItemClass; ?> <?= ($active == 'promotions') ? $activeClass : $inactiveClass; ?>"><span class="material-symbols-outlined text-[19px]">local_activity</span><span>Voucher & Flash Sale</span></a>
      <a href="<?= burl; ?>/panel/chat" class="<?= $navItemClass; ?> <?= ($active == 'chat') ? $activeClass : $inactiveClass; ?>"><span class="material-symbols-outlined text-[19px]">chat</span><span>Chat Shopee</span></a>
      <a href="<?= burl; ?>/panel/sync" class="<?= $navItemClass; ?> <?= ($active == 'sync') ? $activeClass : $inactiveClass; ?>"><span class="material-symbols-outlined text-[19px]">sync</span><span>Sinkronisasi</span></a>
      <a href="<?= burl; ?>/panel/reports" class="<?= $navItemClass; ?> <?= ($active == 'reports') ? $activeClass : $inactiveClass; ?>"><span class="material-symbols-outlined text-[19px]">monitoring</span><span>Laporan</span></a>
      <a href="<?= burl; ?>/panel/finance" class="<?= $navItemClass; ?> min-h-11 <?= $active === 'finance' ? $activeClass : $inactiveClass; ?>" <?= $active === 'finance' ? 'aria-current="page"' : ''; ?>><span class="material-symbols-outlined text-[19px]" aria-hidden="true">account_balance_wallet</span><span>Keuangan</span></a>
    </div>

    <div class="my-5 h-px bg-neutral-content/10"></div>

    <p class="mb-2 px-3 text-[9px] font-extrabold uppercase tracking-[0.18em] text-neutral-content/35">AI Agent</p>
    <div class="space-y-1">
      <a href="<?= burl; ?>/panel/automation" class="<?= $navItemClass; ?> min-h-11 <?= $active === 'automation' ? $activeClass : $inactiveClass; ?>" <?= $active === 'automation' ? 'aria-current="page"' : ''; ?>><span class="material-symbols-outlined text-[19px]" aria-hidden="true">rule</span><span>Automation</span></a>
    </div>
    <div class="my-5 h-px bg-neutral-content/10"></div>
    <p class="mb-2 px-3 text-[9px] font-extrabold uppercase tracking-[0.18em] text-neutral-content/35">Manajemen</p>
    <div class="space-y-1">
      <a href="<?= burl; ?>/panel/shops" class="<?= $navItemClass; ?> <?= ($active == 'shops') ? $activeClass : $inactiveClass; ?>"><span class="material-symbols-outlined text-[19px]">storefront</span><span>Toko</span></a>
      <a href="<?= burl; ?>/panel/extensions" class="<?= $navItemClass; ?> min-h-11 <?= ($active == 'extensions') ? $activeClass : $inactiveClass; ?>" <?= ($active == 'extensions') ? 'aria-current="page"' : ''; ?>><span class="material-symbols-outlined text-[19px]" aria-hidden="true">extension</span><span>Ekstensi</span></a>
    </div>
  </nav>

</aside>
