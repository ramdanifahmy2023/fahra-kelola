<section id="orders-page">
<!-- Loading Overlay -->
<div id="page-loader" class="absolute inset-0 z-[50] bg-base-100/60 backdrop-blur-md flex flex-col items-center justify-center transition-opacity duration-500">
  <span class="loading loading-spinner loading-lg text-primary mb-4"></span>
  <p class="text-lg font-medium text-base-content">Sedang Memuat Data...</p>
</div>

<!-- Page Header -->
<?php
$selectedDate = new DateTimeImmutable($data['start_date'], new DateTimeZone('Asia/Jakarta'));
$currentDate = new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta'));
$selectedMonth = (int)$selectedDate->format('n');
$selectedYear = (int)$selectedDate->format('Y');
$currentMonth = (int)$currentDate->format('n');
$currentYear = (int)$currentDate->format('Y');
$monthNames = [1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
$activeShop = null;
foreach (($data['shops'] ?? []) as $s) {
  if ((int)$s['id'] === (int)($data['active_shop_id'] ?? 0)) { $activeShop = $s; break; }
}
if (!$activeShop && !empty($data['shops'])) $activeShop = $data['shops'][0];
?>
<div class="mb-5 flex flex-wrap items-center justify-between gap-3">
  <div>
    <h2 class="text-2xl font-bold mb-1 text-base-content">Daftar Pesanan</h2>
  </div>
  <div class="flex flex-wrap items-center gap-3">
    <button type="button" class="btn btn-primary btn-sm gap-1" onclick="queueOrderBackgroundSync()" id="order-sync-button">
      <span class="material-symbols-outlined text-[17px]">sync</span>Sync sekarang
    </button>
    <div class="join h-9" aria-label="Filter bulan pesanan">
      <button type="button" class="join-item btn btn-sm h-8 min-h-8 w-10 p-0 border-base-300 <?= $selectedYear === $currentYear - 5 && $selectedMonth === 1 ? 'btn-disabled bg-base-200 text-base-content/40' : 'bg-base-200 hover:bg-base-300'; ?>" onclick="changeOrderMonth(-1)" title="Bulan sebelumnya" <?= $selectedYear === $currentYear - 5 && $selectedMonth === 1 ? 'disabled' : ''; ?>>‹</button>
      <select id="orderMonth" class="join-item select select-bordered select-sm w-20 px-2 py-0 h-8 text-base-content bg-base-200 focus:outline-none" onchange="applyOrderMonth()" aria-label="Bulan">
        <?php foreach ($monthNames as $monthNumber => $monthName): ?>
          <?php if ($selectedYear === $currentYear && $monthNumber > $currentMonth) continue; ?>
          <option value="<?= $monthNumber; ?>" <?= $monthNumber === $selectedMonth ? 'selected' : ''; ?>><?= $monthName; ?></option>
        <?php endforeach; ?>
      </select>
      <select id="orderYear" class="join-item select select-bordered select-sm w-24 px-2 py-0 h-8 text-base-content bg-base-200 focus:outline-none" onchange="applyOrderMonth()" aria-label="Tahun">
        <?php for ($year = $currentYear - 5; $year <= $currentYear; $year++): ?>
          <option value="<?= $year; ?>" <?= $year === $selectedYear ? 'selected' : ''; ?>><?= $year; ?></option>
        <?php endfor; ?>
      </select>
      <button type="button" class="join-item btn btn-sm h-8 min-h-8 w-10 p-0 border-base-300 <?= $selectedYear === $currentYear && $selectedMonth === $currentMonth ? 'btn-disabled bg-base-200 text-base-content/40' : 'bg-base-200 hover:bg-base-300'; ?>" onclick="changeOrderMonth(1)" title="Bulan berikutnya" <?= $selectedYear === $currentYear && $selectedMonth === $currentMonth ? 'disabled' : ''; ?>>›</button>
    </div>
  <div class="form-control relative w-full min-w-0 sm:w-64">
    <?php require __DIR__ . '/templates/shop-picker.php'; ?>
  </div>
</div>
</div>
<div id="order-background-sync-status" class="mb-4 text-[11px] text-base-content/55">Sinkronisasi pesanan berjalan di belakang. Halaman ini membaca data lokal.</div>

<?php if (!empty($data['focused_order_id'])): ?><div id="notification-order-focus">Menampilkan pesanan pilihan.<a href="<?= burl; ?>/panel/orders?shop_id=<?= (int)$data['active_shop_id']; ?>">Lihat semua pesanan toko</a></div><?php endif; ?>
<!-- Table Section -->
<div class="card overflow-hidden border border-base-300 bg-base-100 shadow-sm">

  <div class="overflow-x-auto order-desktop-table">
    <table class="table w-full">
      <!-- head -->
      <thead class="bg-base-200/50 text-base-content">
        <tr>
          <th>Tanggal</th>
          <th>No. Pesanan</th>
          <th>Produk</th>
          <th>Jenis Pesanan</th>
          <th class="text-right">Total Pembayaran</th>
          <th>Status</th>
          <th>Kurir & Resi</th>
          <th class="text-center w-32">Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($data['orders'])): ?>
        <tr>
          <td colspan="8" class="text-center py-10">
            <div class="flex flex-col items-center justify-center text-base-content/50">
              <span class="material-symbols-outlined text-[48px] mb-2 opacity-50">receipt_long</span>
              <p>Belum ada pesanan yang ditarik dari Shopee.</p>
            </div>
          </td>
        </tr>
        <?php else: ?>
        <?php foreach ($data['orders'] as $ord): ?>
        <?php require __DIR__ . '/order_row.php'; ?>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  
  <div id="order-mobile-list" aria-label="Daftar pesanan">
    <?php if (empty($data['orders'])): ?><p class="order-mobile-empty">Tidak ada pesanan untuk toko dan periode pilihan.</p><?php endif; ?>
    <?php foreach ($data['orders'] as $ord): require __DIR__.'/order_card.php'; endforeach; ?>
  </div>
  <!-- Pagination Controls -->
  <div class="p-4 border-t border-base-200 flex items-center justify-between bg-base-100 flex-wrap gap-4">
    <div class="text-sm opacity-70 flex items-center gap-2">
      Menampilkan 
      <select class="select select-bordered select-sm w-20 px-2 py-0 h-8 text-base-content bg-base-200 focus:outline-none" aria-label="Jumlah pesanan per halaman" onchange="changeLimit(this.value, <?= $data['active_shop_id'] ?>)">
        <option value="10" <?= $data['limit'] == 10 ? 'selected' : '' ?>>10</option>
        <option value="20" <?= $data['limit'] == 20 ? 'selected' : '' ?>>20</option>
        <option value="50" <?= $data['limit'] == 50 ? 'selected' : '' ?>>50</option>
        <option value="100" <?= $data['limit'] == 100 ? 'selected' : '' ?>>100</option>
      </select>
      dari <span class="font-bold text-base-content"><?= $data['total_orders'] ?></span> pesanan
    </div>
    
    <?php if ($data['total_pages'] > 1): ?>
    <div class="join">
      <?php 
      $current = $data['current_page'];
      $total = $data['total_pages'];
      $shopId = $data['active_shop_id'];
      $limit = $data['limit'];
      $dateQuery = '&startDate=' . urlencode($data['start_date']) . '&endDate=' . urlencode($data['end_date']);
      
      // Previous Button
      if ($current > 1): 
      ?>
        <a href="?shop_id=<?= $shopId ?>&limit=<?= $limit ?><?= $dateQuery ?>&page=<?= $current - 1 ?>" class="join-item btn btn-sm bg-base-200 hover:bg-base-300 border-base-300">«</a>
      <?php else: ?>
        <button class="join-item btn btn-sm btn-disabled border-base-300">«</button>
      <?php endif; ?>
      
      <!-- Page Numbers -->
      <button class="join-item btn btn-sm bg-base-100 border-base-300 no-animation cursor-default">Hal <?= $current ?> / <?= $total ?></button>
      
      <?php 
      // Next Button
      if ($current < $total): 
      ?>
        <a href="?shop_id=<?= $shopId ?>&limit=<?= $limit ?><?= $dateQuery ?>&page=<?= $current + 1 ?>" class="join-item btn btn-sm bg-base-200 hover:bg-base-300 border-base-300">»</a>
      <?php else: ?>
        <button class="join-item btn btn-sm btn-disabled border-base-300">»</button>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
</div>



<script>
let orderSyncController = null;
let orderSyncToken = 0;
let activeOrderSyncShopId = null;
let orderDetailController = null;
let orderDetailToken = 0;

function applyOrderMonth() {
    const url = new URL(window.location.href);
    const year = Number(document.getElementById('orderYear').value);
    const month = Number(document.getElementById('orderMonth').value);
    const start = new Date(year, month - 1, 1);
    const end = new Date(year, month, 0);
    url.searchParams.set('startDate', formatOrderDate(start));
    url.searchParams.set('endDate', formatOrderDate(end));
    url.searchParams.delete('page');
    window.location.href = url;
}

function changeOrderMonth(offset) {
    const month = document.getElementById('orderMonth');
    const year = document.getElementById('orderYear');
    let nextMonth = Number(month.value) + offset;
    let nextYear = Number(year.value);
    if (nextMonth < 1) { nextMonth = 12; nextYear--; }
    if (nextMonth > 12) { nextMonth = 1; nextYear++; }
    if (![...year.options].some(option => Number(option.value) === nextYear)) return;
    const url = new URL(window.location.href);
    const start = new Date(nextYear, nextMonth - 1, 1);
    const end = new Date(nextYear, nextMonth, 0);
    url.searchParams.set('startDate', formatOrderDate(start));
    url.searchParams.set('endDate', formatOrderDate(end));
    url.searchParams.delete('page');
    window.location.href = url;
}

function formatOrderDate(date) {
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}

function queueOrderBackgroundSync() {
    const shopId = '<?= $data['active_shop_id'] ?? '' ?>';
    const button = document.getElementById('order-sync-button');
    if (!shopId) return;
    if (button) { button.disabled = true; button.innerText = 'Mengantrikan...'; }
    const fd = new FormData();
    fd.append('shop_id', shopId);
    fd.append('sync_type', 'orders');
    fd.append('sync_mode', 'diff');
    fetch('<?= burl; ?>/procsync/enqueue', { method: 'POST', headers: {'X-Requested-With': 'XMLHttpRequest'}, body: fd })
      .then(r => r.json()).then(data => {
        if (data.status !== 'accepted') throw new Error(data.message || 'Antrean gagal dibuat');
        const status = document.getElementById('order-background-sync-status');
        if (status) status.innerText = 'Sinkronisasi pesanan sudah masuk antrean background (job #' + data.job_id + ').';
      }).catch(error => { const status = document.getElementById('order-background-sync-status'); if (status) status.innerText = error.message; })
      .finally(() => { if (button) { button.disabled = false; button.innerHTML = '<span class="material-symbols-outlined text-[17px]">sync</span>Sync sekarang'; } });
}

function refreshOrderBackgroundStatus(shopId) {
    fetch('<?= burl; ?>/procsync/status?shop_id=' + encodeURIComponent(shopId), { headers: {'X-Requested-With': 'XMLHttpRequest'} })
      .then(r => r.json()).then(data => {
        const row = (data.schedules || []).find(item => item.sync_type === 'orders');
        const status = document.getElementById('order-background-sync-status');
        if (!status || !row) return;
        const when = row.last_success_at ? new Date(row.last_success_at.replace(' ', 'T') + 'Z').toLocaleString('id-ID') : 'belum pernah';
        status.innerText = 'Update terakhir: ' + when + '. Sinkronisasi berikutnya dijalankan otomatis di background.';
      }).catch(() => {});
}

function getOrderSyncState(shopId) {
    try {
        return JSON.parse(localStorage.getItem('order_sync_state_' + shopId) || 'null');
    } catch (error) {
        return null;
    }
}

function saveOrderSyncState(shopId, state) {
    localStorage.setItem('order_sync_state_' + shopId, JSON.stringify(state));
}

function pauseOrderSync() {
    if (activeOrderSyncShopId) {
        const state = getOrderSyncState(activeOrderSyncShopId);
        if (state) {
            state.status = 'paused';
            saveOrderSyncState(activeOrderSyncShopId, state);
        }
    }
    orderSyncToken++;
    orderDetailToken++;
    if (orderSyncController) orderSyncController.abort();
    if (orderDetailController) orderDetailController.abort();
    orderSyncController = null;
    orderDetailController = null;
    activeOrderSyncShopId = null;
}

function changeLimit(limit, shopId) {
    const currentUrl = new URL(window.location.href);
    currentUrl.searchParams.set('shop_id', shopId);
    currentUrl.searchParams.set('limit', limit);
    currentUrl.searchParams.delete('page');
    window.location.href = currentUrl;
}


function syncOrders(id, sentinel = '', pageNumber = 1, syncMode = '') {
    if (!sentinel) {
        const savedState = getOrderSyncState(id);
        if (savedState && (savedState.status === 'paused' || savedState.status === 'running')) {
            sentinel = savedState.next_page_sentinel || '';
            pageNumber = savedState.page_number || 1;
            syncMode = savedState.sync_mode || '';
        }
    }

    const syncToken = ++orderSyncToken;
    const controller = new AbortController();
    orderSyncController = controller;
    activeOrderSyncShopId = id;
    const loader = document.getElementById('page-loader');
    if (loader && !sentinel) {
        loader.style.display = 'flex';
        loader.style.opacity = '1';
        loader.querySelector('p').innerText = 'Sedang mengecek database...';
    }

    const formData = new FormData();
    formData.append('shop_id', id);
    formData.append('page_number', pageNumber);
    if (sentinel) {
        formData.append('next_page_sentinel', sentinel);
    }
    if (syncMode) {
        formData.append('sync_mode', syncMode);
    }

    fetch('<?= burl; ?>/procorders/sync_index', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: formData,
        signal: controller.signal
    })
    .then(res => res.json())
    .then(data => {
        if (syncToken !== orderSyncToken) return;
        if (data.status === 'success') {
            if (data.has_next && data.next_page_sentinel) {
                let currentMode = syncMode || (data.is_empty_mode ? 'full' : 'diff');
                saveOrderSyncState(id, {
                    status: 'running',
                    next_page_sentinel: data.next_page_sentinel,
                    page_number: pageNumber + 1,
                    sync_mode: currentMode,
                    progress_percent: data.progress_percent || 0
                });
                if (!sentinel && loader) {
                    // Ini adalah tarikan pertama, tampilkan status database
                    if (data.is_empty_mode) {
                        loader.querySelector('p').innerText = 'Database kosong...';
                        setTimeout(() => {
                            if (loader) loader.querySelector('p').innerText = 'Penarikan data pertama kali membutuhkan lebih banyak waktu mohon tunggu.. (' + (data.progress_percent || 0) + '%)';
                            setTimeout(() => {
                                if (syncToken !== orderSyncToken) return;
                                syncOrders(id, data.next_page_sentinel, pageNumber + 1, currentMode);
                            }, 1000);
                        }, 1500);
                    } else {
                        loader.querySelector('p').innerText = 'Database ditemukan';
                        setTimeout(() => {
                            if (loader) loader.querySelector('p').innerText = 'Sedang sinkronisasi data pesanan..';
                            setTimeout(() => {
                                if (syncToken !== orderSyncToken) return;
                                syncOrders(id, data.next_page_sentinel, pageNumber + 1, currentMode);
                            }, 1000);
                        }, 1500);
                    }
                } else {
                    // Tarikan selanjutnya (halaman 2 dst), update persentase
                    if (currentMode === 'full' && loader) {
                        loader.querySelector('p').innerText = 'Penarikan data pertama kali membutuhkan lebih banyak waktu mohon tunggu.. (' + (data.progress_percent || 0) + '%)';
                    }
                    setTimeout(() => {
                        if (syncToken !== orderSyncToken) return;
                        syncOrders(id, data.next_page_sentinel, pageNumber + 1, currentMode);
                    }, 1000);
                }
            } else {
                localStorage.removeItem('order_sync_state_' + id);
                activeOrderSyncShopId = null;
                const currentUrl = new URL(window.location.href);
                currentUrl.searchParams.set('shop_id', id);
                currentUrl.searchParams.delete('page');
                window.location.href = currentUrl;
            }
        } else {
            saveOrderSyncState(id, {
                status: 'failed',
                next_page_sentinel: sentinel,
                page_number: pageNumber,
                sync_mode: syncMode
            });
            activeOrderSyncShopId = null;
            sessionStorage.removeItem('synced_orders_' + id);
            if (loader) {
                loader.style.opacity = '0';
                setTimeout(() => loader.style.display = 'none', 300);
            }
            if (data.debug) {
                alert(data.message + '\n\nDEBUG INFO:\n' + JSON.stringify(data.debug, null, 2));
            } else {
                alert(data.message);
            }
        }
    })
    .catch(err => {
        if (err.name === 'AbortError' || syncToken !== orderSyncToken) return;
        saveOrderSyncState(id, {
            status: 'failed',
            next_page_sentinel: sentinel,
            page_number: pageNumber,
            sync_mode: syncMode
        });
        activeOrderSyncShopId = null;
        sessionStorage.removeItem('synced_orders_' + id);
        if (loader) {
            loader.style.opacity = '0';
            setTimeout(() => loader.style.display = 'none', 300);
        }
        alert('Terjadi kesalahan saat menarik data pesanan.');
    });
}

function startDetailSync(shopId) {
    const queueKey = 'sync_queue_' + shopId;
    const totalKey = 'sync_queue_total_' + shopId;
    const detailRunToken = ++orderDetailToken;
    let hasProcessedDetail = false;
    
    const runQueue = () => {
        let queue = JSON.parse(localStorage.getItem(queueKey) || '[]');
        let total = parseInt(localStorage.getItem(totalKey) || '0');
        
        const card = document.getElementById('sync-detail-card');
        const badge = document.getElementById('sync-detail-badge');
        const progress = document.getElementById('sync-detail-progress');
        const textLabel = document.getElementById('sync-detail-text');
        const textId = document.getElementById('sync-detail-id');
        
        if (queue.length === 0) {
            // Selesai
            sessionStorage.setItem('synced_order_details_' + shopId, '1');
            if (card && card.classList.contains('flex')) {
                textLabel.innerText = 'Selesai!';
                textId.innerText = '';

                setTimeout(() => {
                    card.classList.remove('flex');
                    card.classList.add('hidden');
                }, 2500);
            }
            if (hasProcessedDetail) {
                setTimeout(() => window.location.reload(), 600);
            }
            return;
        }
        
        if (total === 0) {
            total = queue.length;
            localStorage.setItem(totalKey, total);
        }
        
        // Munculkan indicator jika belum
        card.classList.remove('hidden');
        card.classList.add('flex');
        progress.max = total;
        
        const currentOrderId = queue.shift();
        hasProcessedDetail = true;
        localStorage.setItem(queueKey, JSON.stringify(queue)); // Simpan sisa queue
        
        let completed = total - queue.length; 
        
        textLabel.innerText = 'Sinkronisasi data';
        textId.innerText = currentOrderId;
        
        const formData = new FormData();
        formData.append('shop_id', shopId);
        formData.append('order_id', currentOrderId);
        const controller = new AbortController();
        orderDetailController = controller;
        
        fetch('<?= burl; ?>/procorders/sync_detail', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData,
            signal: controller.signal
        })
        .then(res => res.json())
        .then(data => {
            if (detailRunToken !== orderDetailToken) {
                const pendingQueue = JSON.parse(localStorage.getItem(queueKey) || '[]');
                localStorage.setItem(queueKey, JSON.stringify([currentOrderId, ...pendingQueue]));
                return;
            }
            if (data.status !== 'success') {
                const pendingQueue = JSON.parse(localStorage.getItem(queueKey) || '[]');
                localStorage.setItem(queueKey, JSON.stringify([currentOrderId, ...pendingQueue]));
                textLabel.innerText = 'Sinkronisasi gagal';
                return;
            }
            if (data.status === 'success' && data.html) {
                // Temukan baris tabel lama jika kebetulan ada di layar
                const oldRow = document.getElementById('order-row-' + currentOrderId);
                if (oldRow) {
                    const temp = document.createElement('table');
                    temp.innerHTML = '<tbody>' + data.html + '</tbody>';
                    const newRow = temp.querySelector('tr');
                    
                    if (newRow) {
                        oldRow.style.opacity = '0.5';
                        setTimeout(() => {
                            oldRow.replaceWith(newRow);
                        }, 150);
                    }
                }
            }
            
            const pct = Math.round((completed / total) * 100);
            badge.innerText = pct + '%';
            progress.value = completed;
            
            runQueue();
        })
        .catch(err => {
            if (err.name === 'AbortError' || detailRunToken !== orderDetailToken) {
                const pendingQueue = JSON.parse(localStorage.getItem(queueKey) || '[]');
                localStorage.setItem(queueKey, JSON.stringify([currentOrderId, ...pendingQueue]));
                return;
            }
            console.error('Error detail sync for ' + currentOrderId, err);
            const pendingQueue = JSON.parse(localStorage.getItem(queueKey) || '[]');
            localStorage.setItem(queueKey, JSON.stringify([currentOrderId, ...pendingQueue]));
            textLabel.innerText = 'Sinkronisasi gagal';
        });
    };
    
    const fd = new FormData();
    fd.append('shop_id', shopId);
    fetch('<?= burl; ?>/procorders/get_sync_queue', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: fd
    })
    .then(r => r.json())
    .then(d => {
        const existingQueue = JSON.parse(localStorage.getItem(queueKey) || '[]').map(String);
        const databaseQueue = d.status === 'success' && Array.isArray(d.queue) ? d.queue.map(String) : [];
        const existingIds = new Set(existingQueue);
        const newQueue = databaseQueue.filter(orderId => !existingIds.has(orderId));
        const mergedQueue = [...existingQueue, ...newQueue];
        const currentTotal = parseInt(localStorage.getItem(totalKey) || '0');

        localStorage.setItem(queueKey, JSON.stringify(mergedQueue));
        localStorage.setItem(totalKey, String(currentTotal > 0 ? currentTotal + newQueue.length : mergedQueue.length));
        runQueue();
    })
    .catch(e => {
        console.error('Gagal mendapatkan antrean sinkronisasi', e);
        if (JSON.parse(localStorage.getItem(queueKey) || '[]').length > 0) runQueue();
    });
}

document.addEventListener('DOMContentLoaded', () => {
    const activeShopId = '<?= $data['active_shop_id'] ?? '' ?>';

    const currentUrl = new URL(window.location.href);
    const syncFromSidebar = currentUrl.searchParams.get('sync') === '1';
    if (syncFromSidebar) {
        sessionStorage.removeItem('synced_orders_' + activeShopId);
        sessionStorage.removeItem('synced_order_details_' + activeShopId);
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
    if (activeShopId) refreshOrderBackgroundStatus(activeShopId);
});
</script>
<script>
let durableSyncJobId = null;
let durableSyncTimer = null;

function getOrderSyncState() { return null; }
function saveOrderSyncState() {}
function pauseOrderSync() {
    if (durableSyncTimer) clearTimeout(durableSyncTimer);
    durableSyncTimer = null;
    orderSyncToken++;
    orderDetailToken++;
    activeOrderSyncShopId = null;
}

function renderDurableSync(job) {
    const card = document.getElementById('sync-detail-card');
    const badge = document.getElementById('sync-detail-badge');
    const progress = document.getElementById('sync-detail-progress');
    const label = document.getElementById('sync-detail-text');
    const jobData = job || {};
    const pct = Number(jobData.progress_percent || 0);
    if (card) { card.classList.remove('hidden'); card.classList.add('flex'); }
    if (badge) badge.innerText = pct + '%';
    if (progress) { progress.classList.remove('hidden'); progress.value = pct; }
    if (label) label.innerText = jobData.status === 'completed' ? 'Sinkronisasi selesai' : (jobData.status === 'failed' ? 'Sinkronisasi gagal' : 'Sinkronisasi berjalan');
}

function pollDurableSync(shopId, jobId) {
    const fd = new FormData();
    fd.append('shop_id', shopId);
    fd.append('job_id', jobId);
    fetch('<?= burl; ?>/procorders/sync_status', { method: 'POST', headers: {'X-Requested-With': 'XMLHttpRequest'}, body: fd })
        .then(response => response.json())
        .then(data => {
            if (data.status !== 'success') return;
            renderDurableSync(data.job);
            if (data.job && ['queued', 'running'].includes(data.job.status)) {
                durableSyncTimer = setTimeout(() => pollDurableSync(shopId, jobId), 2000);
            } else {
                activeOrderSyncShopId = null;
                setTimeout(() => window.location.reload(), 500);
            }
        })
        .catch(() => { durableSyncTimer = setTimeout(() => pollDurableSync(shopId, jobId), 5000); });
}

function syncOrders(id) {
    pauseOrderSync();
    activeOrderSyncShopId = id;
    const loader = document.getElementById('page-loader');
    if (loader) { loader.style.display = 'flex'; loader.style.opacity = '1'; loader.querySelector('p').innerText = 'Antrean sinkronisasi dibuat...'; }
    const formData = new FormData();
    formData.append('shop_id', id);
    formData.append('sync_mode', 'diff');
    fetch('<?= burl; ?>/procorders/sync_index', { method: 'POST', headers: {'X-Requested-With': 'XMLHttpRequest'}, body: formData })
        .then(response => response.json())
        .then(data => {
            if (data.status !== 'accepted') throw new Error(data.message || 'Gagal membuat antrean');
            durableSyncJobId = data.job_id;
            renderDurableSync({status: 'queued', progress_percent: 0});
            if (loader) { loader.style.opacity = '0'; loader.style.display = 'none'; }
            pollDurableSync(id, durableSyncJobId);
        })
        .catch(error => {
            if (loader) { loader.style.opacity = '0'; loader.style.display = 'none'; }
            const label = document.getElementById('sync-detail-text');
            if (label) label.innerText = error.message;
        });
}

function startDetailSync(shopId) {
    const fd = new FormData();
    fd.append('shop_id', shopId);
    fetch('<?= burl; ?>/procorders/sync_status', { method: 'POST', headers: {'X-Requested-With': 'XMLHttpRequest'}, body: fd })
        .then(response => response.json())
        .then(data => {
            if (!data.job) return;
            durableSyncJobId = data.job.id;
            renderDurableSync(data.job);
            if (['queued', 'running'].includes(data.job.status)) {
                pollDurableSync(shopId, data.job.id);
            }
        });
}
</script>

<dialog id="order-detail-dialog" aria-labelledby="order-detail-title"><header><h2 id="order-detail-title">Rincian pesanan</h2><button type="button" id="order-detail-close" class="btn" aria-label="Tutup rincian pesanan"><span class="material-symbols-outlined" aria-hidden="true">close</span></button></header><div id="order-detail-content"></div></dialog>
<script src="<?= assets; ?>/js/order-cards.js?v=<?= filemtime(__DIR__.'/../../../public/assets/js/order-cards.js'); ?>" defer></script>

</section>
