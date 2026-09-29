<!-- Loading Overlay -->
<div id="page-loader" class="absolute inset-0 z-[50] bg-base-100/60 backdrop-blur-md flex flex-col items-center justify-center transition-opacity duration-500">
  <span class="loading loading-spinner loading-lg text-primary mb-4"></span>
  <p class="text-lg font-medium text-base-content">Sedang Memuat Data...</p>
</div>

<!-- Page Header -->
<?php
$activeShop = null;
foreach (($data['shops'] ?? []) as $s) {
  if ((int)$s['id'] === (int)($data['active_shop_id'] ?? 0)) { $activeShop = $s; break; }
}
if (!$activeShop && !empty($data['shops'])) $activeShop = $data['shops'][0];
$isCriticalFilter = ($data['stock_filter'] ?? '') === 'critical';
?>
<style>
  #product-toolbar { width: 100%; }
  #product-toolbar .product-sync-action { width: 100%; }
  #product-toolbar .dropdown { display: block; }
  @media (min-width: 640px) {
    #product-toolbar { width: 24rem; max-width: 24rem; flex: 0 0 24rem; flex-direction: row; }
    #product-toolbar .product-sync-action { width: auto; white-space: nowrap; }
  }
</style>
<div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
  <div class="min-w-0">
    <div class="flex flex-wrap items-center gap-2">
      <h2 class="text-2xl font-bold mb-1 text-base-content"><?= $isCriticalFilter ? 'Stok Kritis' : 'Daftar Produk'; ?></h2>
      <?php if ($isCriticalFilter): ?><span class="badge badge-error badge-sm gap-1"><span class="material-symbols-outlined text-[14px]">warning</span>Di bawah 15</span><?php endif; ?>
    </div>
    <?php if ($isCriticalFilter): ?><p class="mt-1 text-sm">Produk aktif dengan stok di bawah 15.</p><?php endif; ?>
  </div>
  <div id="product-toolbar" class="flex w-full min-w-0 flex-col gap-2 sm:flex-row sm:items-center">
  <?php if ($isCriticalFilter): ?><a href="<?= burl; ?>/panel/products?shop_id=<?= (int)($data['active_shop_id'] ?? 0); ?>" class="btn btn-ghost btn-sm w-full gap-1 sm:w-auto" title="Tampilkan semua produk"><span class="material-symbols-outlined text-[17px]">close</span>Semua produk</a><?php endif; ?>
  <button type="button" class="product-sync-action btn btn-primary btn-sm w-full gap-1" onclick="queueBackgroundSync('products')" id="product-sync-button">
    <span class="material-symbols-outlined text-[17px]">sync</span>Sync sekarang
  </button>
  <div class="form-control relative min-w-0 flex-1">
    <?php require __DIR__ . '/templates/shop-picker.php'; ?>
  </div>
  </div>
</div>
<div id="background-sync-status" class="mb-4 text-[11px] text-base-content/55">Sinkronisasi berjalan di belakang. Halaman ini membaca data lokal.</div>

<!-- Table Section -->
<div class="card overflow-hidden border border-base-300 bg-base-100 shadow-sm">
  <div class="flex items-center gap-2 border-b border-base-300 bg-base-100 px-4 py-2 text-[11px] text-base-content/50 sm:hidden"><span class="material-symbols-outlined text-sm">swipe</span><span>Geser ke samping untuk melihat kolom lainnya</span></div>
  <div class="overflow-x-auto">
    <table class="table w-full">
      <!-- head -->
      <thead class="bg-base-200/50 text-base-content">
        <tr>
          <th>Nama Produk</th>
          <th>Toko</th>
          <th class="text-right">Harga</th>
          <th class="text-center">Stok</th>
          <th>Status</th>
          <th class="text-center w-32">Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($data['products'])): ?>
        <tr>
          <td colspan="6" class="text-center py-10">
            <div class="flex flex-col items-center justify-center text-base-content/50">
              <span class="material-symbols-outlined text-[48px] mb-2 opacity-50">inventory_2</span>
              <p><?= $isCriticalFilter ? 'Tidak ada produk aktif dengan stok di bawah 15.' : 'Belum ada produk yang ditarik dari Shopee.'; ?></p>
            </div>
          </td>
        </tr>
        <?php else: ?>
        <?php foreach ($data['products'] as $p): ?>
        <?php $productName = trim(str_replace(['<', '>'], '', (string)$p['name'])); ?>
        <tr id="product-<?= (int)$p['id']; ?>" class="hover">
          <td>
            <div class="flex items-center gap-3">
              <div class="avatar">
                <div class="w-12 h-12 rounded bg-base-200 flex items-center justify-center overflow-hidden border border-base-300">
                  <?php if (!empty($p['cover_image'])): ?>
                    <img src="https://cf.shopee.co.id/file/<?= htmlspecialchars($p['cover_image']); ?>" alt="Product" class="w-full h-full object-cover" />
                  <?php else: ?>
                    <span class="material-symbols-outlined opacity-50">inventory_2</span>
                  <?php endif; ?>
                </div>
              </div>
              <div>
                <div class="js-floating-tooltip w-full max-w-[30rem] truncate font-bold text-sm" data-tip="<?= htmlspecialchars($productName); ?>">
                  <?= htmlspecialchars($productName); ?>
                </div>
                <div class="text-[11px] opacity-60 font-medium">SKU: <?= htmlspecialchars($p['parent_sku'] ?: '-'); ?></div>
              </div>
            </div>
          </td>
          <td>
            <div class="flex items-center gap-2">
               <img src="<?= htmlspecialchars($activeShop['shop_logo'] ?: burl . '/public/assets/images/app_brands/shopee.png'); ?>" class="w-5 h-5 rounded object-cover shadow-sm" />
               <span class="tooltip tooltip-top text-xs font-medium max-w-[100px] truncate" data-tip="<?= htmlspecialchars($activeShop['name']); ?>"><?= htmlspecialchars($activeShop['name']); ?></span>
            </div>
          </td>
          <td class="text-right">
            <div class="font-semibold text-sm">Rp <?= number_format($p['price_min'], 0, ',', '.'); ?></div>
            <?php if ($p['has_discount']): ?>
              <div class="text-[11px] text-base-content/50 line-through">Rp <?= number_format($p['price_max'], 0, ',', '.'); ?></div>
            <?php endif; ?>
          </td>
          <td class="text-center">
            <span class="font-semibold text-sm <?= $p['total_stock'] == 0 ? 'text-error' : '' ?>"><?= number_format($p['total_stock'], 0, ',', '.'); ?></span>
          </td>
          <td>
            <?php if ($p['status'] == 1): ?>
              <span class="badge badge-success badge-sm text-white gap-1"><span class="w-1.5 h-1.5 rounded-full bg-white opacity-80"></span>Aktif</span>
            <?php elseif ($p['status'] == 2): ?>
              <span class="badge badge-error badge-sm text-white gap-1"><span class="w-1.5 h-1.5 rounded-full bg-white opacity-80"></span>Habis</span>
            <?php else: ?>
              <span class="badge badge-ghost badge-sm gap-1"><span class="w-1.5 h-1.5 rounded-full bg-base-content opacity-50"></span>Diarsipkan</span>
            <?php endif; ?>
          </td>
          <td class="text-center">
            <button class="tooltip tooltip-top btn btn-sm btn-ghost btn-square" data-tip="Detail">
              <span class="material-symbols-outlined text-[18px]">visibility</span>
            </button>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  
  <!-- Pagination Controls -->
  <div class="p-4 border-t border-base-200 flex items-center justify-between bg-base-100 flex-wrap gap-4">
    <div class="text-sm opacity-70 flex items-center gap-2">
      Menampilkan 
      <select class="select select-bordered select-sm w-20 px-2 py-0 h-8 text-base-content bg-base-200 focus:outline-none" onchange="changeLimit(this.value, <?= $data['active_shop_id'] ?>)">
        <option value="10" <?= $data['limit'] == 10 ? 'selected' : '' ?>>10</option>
        <option value="20" <?= $data['limit'] == 20 ? 'selected' : '' ?>>20</option>
        <option value="50" <?= $data['limit'] == 50 ? 'selected' : '' ?>>50</option>
        <option value="100" <?= $data['limit'] == 100 ? 'selected' : '' ?>>100</option>
      </select>
      dari <span class="font-bold text-base-content"><?= $data['total_products'] ?></span> produk
    </div>
    
    <?php if ($data['total_pages'] > 1): ?>
    <div class="join">
      <?php 
      $current = $data['current_page'];
      $total = $data['total_pages'];
      $shopId = $data['active_shop_id'];
      $limit = $data['limit'];
      $stockQuery = $isCriticalFilter ? '&stock=critical' : '';
      
      // Previous Button
      if ($current > 1): 
      ?>
        <a href="?shop_id=<?= $shopId ?>&limit=<?= $limit ?>&page=<?= $current - 1 ?><?= $stockQuery; ?>" class="join-item btn btn-sm bg-base-200 hover:bg-base-300 border-base-300">«</a>
      <?php else: ?>
        <button class="join-item btn btn-sm btn-disabled border-base-300">«</button>
      <?php endif; ?>
      
      <!-- Page Numbers -->
      <button class="join-item btn btn-sm bg-base-100 border-base-300 no-animation cursor-default">Hal <?= $current ?> / <?= $total ?></button>
      
      <?php 
      // Next Button
      if ($current < $total): 
      ?>
        <a href="?shop_id=<?= $shopId ?>&limit=<?= $limit ?>&page=<?= $current + 1 ?><?= $stockQuery; ?>" class="join-item btn btn-sm bg-base-200 hover:bg-base-300 border-base-300">»</a>
      <?php else: ?>
        <button class="join-item btn btn-sm btn-disabled border-base-300">»</button>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<script>
let productSyncController = null;
let productSyncToken = 0;
let activeProductSyncShopId = null;

function getProductSyncState(shopId) {
    try {
        return JSON.parse(localStorage.getItem('product_sync_state_' + shopId) || 'null');
    } catch (error) {
        return null;
    }
}

function saveProductSyncState(shopId, state) {
    localStorage.setItem('product_sync_state_' + shopId, JSON.stringify(state));
}

function pauseProductSync() {
    if (!activeProductSyncShopId) return;

    const state = getProductSyncState(activeProductSyncShopId);
    if (state) {
        state.status = 'paused';
        saveProductSyncState(activeProductSyncShopId, state);
    }
    productSyncToken++;
    if (productSyncController) productSyncController.abort();
    productSyncController = null;
    activeProductSyncShopId = null;
}

function changeLimit(limit, shopId) {
    const url = new URL('<?= burl; ?>/panel/products', window.location.origin);
    url.searchParams.set('shop_id', shopId);
    url.searchParams.set('limit', limit);
    <?php if ($isCriticalFilter): ?>url.searchParams.set('stock', 'critical');<?php endif; ?>
    window.location.href = url.toString();
}

function queueBackgroundSync(type) {
    const shopId = document.getElementById('selectedShopId')?.value;
    const button = document.getElementById('product-sync-button');
    if (!shopId) return;
    if (button) { button.disabled = true; button.innerText = 'Mengantrikan...'; }
    const fd = new FormData();
    fd.append('shop_id', shopId);
    fd.append('sync_type', type);
    fd.append('sync_mode', 'diff');
    fetch('<?= burl; ?>/procsync/enqueue', { method: 'POST', headers: {'X-Requested-With': 'XMLHttpRequest'}, body: fd })
      .then(r => r.json())
      .then(data => {
        if (data.status !== 'accepted') throw new Error(data.message || 'Antrean gagal dibuat');
        const status = document.getElementById('background-sync-status');
        if (status) status.innerText = 'Sinkronisasi produk sudah masuk antrean background (job #' + data.job_id + ').';
      })
      .catch(error => { const status = document.getElementById('background-sync-status'); if (status) status.innerText = error.message; })
      .finally(() => { if (button) { button.disabled = false; button.innerHTML = '<span class="material-symbols-outlined text-[17px]">sync</span>Sync sekarang'; } });
}

function refreshBackgroundStatus(shopId) {
    fetch('<?= burl; ?>/procsync/status?shop_id=' + encodeURIComponent(shopId), { headers: {'X-Requested-With': 'XMLHttpRequest'} })
      .then(r => r.json()).then(data => {
        const row = (data.schedules || []).find(item => item.sync_type === 'products');
        const status = document.getElementById('background-sync-status');
        if (!status || !row) return;
        const when = row.last_success_at ? new Date(row.last_success_at.replace(' ', 'T') + 'Z').toLocaleString('id-ID') : 'belum pernah';
        status.innerText = 'Update terakhir: ' + when + '. Sinkronisasi berikutnya dijalankan otomatis di background.';
      }).catch(() => {});
}

function updateSyncIndicator(label, detail, percentage) {
    const card = document.getElementById('sync-detail-card');
    const badge = document.getElementById('sync-detail-badge');
    const progress = document.getElementById('sync-detail-progress');
    const textLabel = document.getElementById('sync-detail-text');
    const textId = document.getElementById('sync-detail-id');

    if (card) {
        card.classList.remove('hidden');
        card.classList.add('flex');
    }
    if (textLabel) textLabel.innerText = label;
    if (textId) textId.innerText = detail;
    if (badge) badge.innerText = percentage + '%';
    if (progress) {
        progress.max = 100;
        progress.value = percentage;
    }
}

function syncProducts(id, sentinel = '', pageNumber = 1, syncMode = '') {
    if (!sentinel) {
        const savedState = getProductSyncState(id);
        if (savedState && (savedState.status === 'paused' || savedState.status === 'running')) {
            sentinel = savedState.next_page_sentinel || '';
            pageNumber = savedState.page_number || 1;
            syncMode = savedState.sync_mode || '';
        }
    }

    const syncToken = ++productSyncToken;
    const controller = new AbortController();
    productSyncController = controller;
    activeProductSyncShopId = id;
    const loader = document.getElementById('page-loader');
    if (loader && !sentinel) {
        loader.style.display = 'flex';
        loader.style.opacity = '1';
        loader.querySelector('p').innerText = 'Sedang mengecek database...';
    }

    const formData = new FormData();
    formData.append('shop_id', id);
    formData.append('page_number', pageNumber);
    if (sentinel) formData.append('next_page_sentinel', sentinel);
    if (syncMode) formData.append('sync_mode', syncMode);

    fetch('<?= burl; ?>/procproducts/add', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData,
        signal: controller.signal
    })
    .then(res => res.json())
    .then(data => {
        if (syncToken !== productSyncToken) return;
        if (data.status === 'success') {
            const currentMode = syncMode || (data.is_empty_mode ? 'full' : 'diff');
            const percentage = data.progress_percent || 0;
            const label = data.is_empty_mode ? 'Sinkronisasi produk baru' : 'Sinkronisasi produk';

            if (data.has_next && data.next_page_sentinel) {
                saveProductSyncState(id, {
                    status: 'running',
                    next_page_sentinel: data.next_page_sentinel,
                    page_number: pageNumber + 1,
                    sync_mode: currentMode,
                    progress_percent: percentage
                });
                if (loader) {
                    if (!sentinel) {
                        loader.querySelector('p').innerText = data.is_empty_mode
                            ? 'Database kosong...'
                            : 'Database ditemukan';
                    } else if (data.is_empty_mode) {
                        loader.querySelector('p').innerText = 'Penarikan data pertama kali membutuhkan lebih banyak waktu mohon tunggu.. (' + percentage + '%)';
                    } else {
                        loader.querySelector('p').innerText = 'Sedang sinkronisasi data produk.. (' + percentage + '%)';
                    }
                }
                updateSyncIndicator(label, !sentinel
                    ? (data.is_empty_mode ? 'Database kosong' : 'Database ditemukan')
                    : (data.is_empty_mode ? 'Penarikan data pertama kali' : 'Memperbarui data'), percentage);
                const delay = !sentinel ? (data.is_empty_mode ? 2500 : 1500) : 150;
                setTimeout(() => {
                    if (syncToken !== productSyncToken) return;
                    if (!sentinel && !data.is_empty_mode && loader) {
                        loader.style.opacity = '0';
                        setTimeout(() => loader.style.display = 'none', 300);
                    }
                    syncProducts(id, data.next_page_sentinel, pageNumber + 1, currentMode);
                }, delay);
                return;
            }

            localStorage.removeItem('product_sync_state_' + id);
            activeProductSyncShopId = null;
            updateSyncIndicator('Sinkronisasi produk', 'Selesai!', 100);
            setTimeout(() => {
                if (syncToken !== productSyncToken) return;
                const card = document.getElementById('sync-detail-card');
                if (card) {
                    card.classList.remove('flex');
                    card.classList.add('hidden');
                }
                const currentUrl = new URL(window.location.href);
                currentUrl.searchParams.set('shop_id', id);
                window.location.href = currentUrl;
            }, 2500);
        } else {
            saveProductSyncState(id, {
                status: 'failed',
                next_page_sentinel: sentinel,
                page_number: pageNumber,
                sync_mode: syncMode
            });
            activeProductSyncShopId = null;
            sessionStorage.removeItem('synced_' + id);
            if (loader) {
                loader.style.opacity = '0';
                setTimeout(() => loader.style.display = 'none', 500);
            }
            if (data.debug) {
                alert(data.message + '\n\nDEBUG INFO:\n' + JSON.stringify(data.debug, null, 2));
            } else {
                alert(data.message);
            }
        }
    })
    .catch(err => {
        if (err.name === 'AbortError' || syncToken !== productSyncToken) return;
        saveProductSyncState(id, {
            status: 'failed',
            next_page_sentinel: sentinel,
            page_number: pageNumber,
            sync_mode: syncMode
        });
        activeProductSyncShopId = null;
        sessionStorage.removeItem('synced_' + id);
        if (loader) {
            loader.style.opacity = '0';
            setTimeout(() => loader.style.display = 'none', 500);
        }
        alert('Terjadi kesalahan saat menarik data produk.');
    });
}

// Logic untuk menyembunyikan preload atau auto-sync
document.addEventListener('DOMContentLoaded', () => {
    const activeShopId = '<?= $data['active_shop_id'] ?? '' ?>';

    const currentUrl = new URL(window.location.href);
    const syncFromSidebar = currentUrl.searchParams.get('sync') === '1';
    if (syncFromSidebar) {
        sessionStorage.removeItem('synced_' + activeShopId);
        currentUrl.searchParams.delete('sync');
    }
    if (activeShopId && !currentUrl.searchParams.has('shop_id')) {
        currentUrl.searchParams.set('shop_id', activeShopId);
        window.history.replaceState({}, '', currentUrl);
    } else if (syncFromSidebar) {
        window.history.replaceState({}, '', currentUrl);
    }
    
    const loader = document.getElementById('page-loader');
    if (loader) { loader.style.opacity = '0'; setTimeout(() => loader.style.display = 'none', 300); }
    if (activeShopId) refreshBackgroundStatus(activeShopId);
    const highlight = currentUrl.searchParams.get('highlight');
    if (highlight) {
        const row = document.getElementById('product-' + highlight);
        if (row) {
            row.classList.add('bg-warning/20');
            row.scrollIntoView({behavior: 'smooth', block: 'center'});
            setTimeout(() => row.classList.remove('bg-warning/20'), 3500);
        }
    }
});
</script>
