<?php
$active = $data['active_menu'] ?? '';
$activeClass = 'bg-primary text-primary-content shadow-md shadow-primary/20';
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
      <div class="text-[9px] font-bold uppercase tracking-[0.2em] text-primary">Operations</div>
    </div>
  </div>

  <nav class="flex-1 overflow-y-auto px-3 py-5">
    <p class="mb-2 px-3 text-[9px] font-extrabold uppercase tracking-[0.18em] text-neutral-content/35">Menu utama</p>
    <div class="space-y-1">
      <a href="<?= burl; ?>/panel" class="<?= $navItemClass; ?> <?= ($active == 'dashboard') ? $activeClass : $inactiveClass; ?>"><span class="material-symbols-outlined text-[19px]">grid_view</span><span>Dashboard</span></a>
      <a href="<?= burl; ?>/panel/products" class="<?= $navItemClass; ?> <?= ($active == 'products') ? $activeClass : $inactiveClass; ?>"><span class="material-symbols-outlined text-[19px]">inventory_2</span><span>Produk</span></a>
      <a href="<?= burl; ?>/panel/boost" class="<?= $navItemClass; ?> <?= ($active == 'boost') ? $activeClass : $inactiveClass; ?>"><span class="material-symbols-outlined text-[19px]">north</span><span>Naikkan Produk</span></a>
      <a href="<?= burl; ?>/panel/orders" class="<?= $navItemClass; ?> <?= ($active == 'orders') ? $activeClass : $inactiveClass; ?>"><span class="material-symbols-outlined text-[19px]">receipt_long</span><span>Pesanan</span></a>
      <a href="<?= burl; ?>/panel/customers" class="<?= $navItemClass; ?> <?= ($active == 'customers') ? $activeClass : $inactiveClass; ?>"><span class="material-symbols-outlined text-[19px]">group</span><span>Pelanggan</span></a>
      <a href="<?= burl; ?>/panel/ads" class="<?= $navItemClass; ?> <?= ($active == 'ads') ? $activeClass : $inactiveClass; ?>"><span class="material-symbols-outlined text-[19px]">campaign</span><span>Iklan</span></a>
      <a href="<?= burl; ?>/panel/promotions" class="<?= $navItemClass; ?> <?= ($active == 'promotions') ? $activeClass : $inactiveClass; ?>"><span class="material-symbols-outlined text-[19px]">local_activity</span><span>Voucher & Flash Sale</span></a>
      <a href="<?= burl; ?>/panel/chat" class="<?= $navItemClass; ?> <?= ($active == 'chat') ? $activeClass : $inactiveClass; ?>"><span class="material-symbols-outlined text-[19px]">chat</span><span>Live Chat</span></a>
      <a href="javascript:void(0)" class="<?= $navItemClass; ?> <?= ($active == 'reports') ? $activeClass : $inactiveClass; ?>"><span class="material-symbols-outlined text-[19px]">monitoring</span><span>Laporan</span></a>
    </div>

    <div class="my-5 h-px bg-neutral-content/10"></div>

    <p class="mb-2 px-3 text-[9px] font-extrabold uppercase tracking-[0.18em] text-neutral-content/35">Manajemen</p>
    <div class="space-y-1">
      <a href="<?= burl; ?>/panel/shops" class="<?= $navItemClass; ?> <?= ($active == 'shops') ? $activeClass : $inactiveClass; ?>"><span class="material-symbols-outlined text-[19px]">storefront</span><span>Toko</span></a>
      <a href="javascript:void(0)" class="<?= $navItemClass; ?> <?= ($active == 'settings') ? $activeClass : $inactiveClass; ?>"><span class="material-symbols-outlined text-[19px]">tune</span><span>Sistem</span></a>
    </div>
  </nav>

  <div class="m-3 shrink-0 rounded-xl border border-neutral-content/10 bg-neutral-content/5 p-3">
    <div class="flex items-center gap-2 text-[11px] font-bold"><span class="h-2 w-2 rounded-full bg-success shadow-[0_0_10px_currentColor]"></span>System online</div>
    <p class="mt-1 text-[10px] leading-relaxed text-neutral-content/45">Workspace siap digunakan.</p>
  </div>
</aside>
