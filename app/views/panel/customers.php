<div id="customer-page-loader" class="absolute inset-0 z-40 hidden flex-col items-center justify-center bg-base-100/65 backdrop-blur-md">
  <span class="loading loading-spinner loading-lg text-primary"></span>
  <p class="mt-3 text-sm font-semibold text-base-content">Sedang mengecek data pelanggan...</p>
</div>

<div class="mb-5 flex flex-wrap items-end justify-between gap-3">
  <div>
    <div class="mb-1 flex items-center gap-2 text-[10px] font-bold uppercase tracking-[0.16em] text-primary"><span class="material-symbols-outlined text-sm">group</span>Customer directory</div>
    <h2 class="text-xl font-black tracking-tight text-base-content">Daftar Pelanggan</h2>
    <p class="mt-1 text-xs text-base-content/60">Data pelanggan yang tersimpan pada workspace Anda.</p>
  </div>
  <div class="flex items-center gap-2">
    <button type="button" class="btn btn-primary btn-sm gap-1" onclick="queueCustomerBackgroundSync()" id="customer-sync-button"><span class="material-symbols-outlined text-[17px]">sync</span>Sync sekarang</button>
    <div class="badge badge-outline h-9 gap-2 px-3 text-xs font-bold"><span class="material-symbols-outlined text-sm text-primary">database</span><?= number_format($data['total_customers']) ?> pelanggan</div>
  </div>
</div>

<script>
let customerSyncController = null;
let customerSyncToken = 0;

function updateCustomerSyncIndicator(label, detail, percentage) {
  const card = document.getElementById('sync-detail-card');
  const badge = document.getElementById('sync-detail-badge');
  const text = document.getElementById('sync-detail-text');
  const id = document.getElementById('sync-detail-id');
  const progress = document.getElementById('sync-detail-progress');
  if (card) { card.classList.remove('hidden'); card.classList.add('flex'); }
  if (text) text.innerText = label;
  if (id) id.innerText = detail;
  if (badge) badge.innerText = percentage + '%';
  if (progress) { progress.max = 100; progress.value = percentage; }
}

function syncCustomers(force = false) {
  if (customerSyncController) customerSyncController.abort();
  const token = ++customerSyncToken;
  customerSyncController = new AbortController();
  const loader = document.getElementById('customer-page-loader');
  if (loader) { loader.classList.remove('hidden'); loader.classList.add('flex'); }
  updateCustomerSyncIndicator('Sinkronisasi pelanggan', 'Database ditemukan', 0);

  fetch('<?= burl; ?>/proccustomers/sync', {
    method: 'POST',
    headers: { 'X-Requested-With': 'XMLHttpRequest' },
    signal: customerSyncController.signal
  })
  .then(response => response.json())
  .then(data => {
    if (token !== customerSyncToken) return;
    if (data.status !== 'success') throw new Error(data.message || 'Sync failed');
    sessionStorage.setItem('synced_customers', '1');
    updateCustomerSyncIndicator('Sinkronisasi pelanggan', 'Selesai!', 100);
    if (loader) { loader.classList.remove('flex'); loader.classList.add('hidden'); }
    setTimeout(() => window.location.reload(), 1200);
  })
  .catch(error => {
    if (error.name === 'AbortError' || token !== customerSyncToken) return;
    sessionStorage.removeItem('synced_customers');
    updateCustomerSyncIndicator('Sinkronisasi pelanggan', 'Gagal', 0);
    if (loader) { loader.classList.remove('flex'); loader.classList.add('hidden'); }
    alert(error.message || 'Gagal menyinkronkan data pelanggan.');
  });
}

function queueCustomerBackgroundSync() {
  const button = document.getElementById('customer-sync-button');
  if (button) { button.disabled = true; button.innerText = 'Mengantrikan...'; }
  const fd = new FormData(); fd.append('shop_id', '0'); fd.append('sync_type', 'customers');
  fetch('<?= burl; ?>/procsync/enqueue', { method: 'POST', headers: {'X-Requested-With': 'XMLHttpRequest'}, body: fd })
    .then(r => r.json()).then(data => { if (data.status !== 'accepted') throw new Error(data.message || 'Antrean gagal dibuat'); alert('Sinkronisasi pelanggan masuk antrean background.'); })
    .catch(error => alert(error.message)).finally(() => { if (button) { button.disabled = false; button.innerHTML = '<span class="material-symbols-outlined text-[17px]">sync</span>Sync sekarang'; } });
}

document.addEventListener('DOMContentLoaded', () => {
  const url = new URL(window.location.href);
  const syncFromSidebar = url.searchParams.get('sync') === '1';
  if (syncFromSidebar) {
    sessionStorage.removeItem('synced_customers');
    url.searchParams.delete('sync');
    window.history.replaceState({}, '', url);
  }
  // Halaman hanya membaca database lokal; scheduler background memperbarui pelanggan.
});
</script>

<div class="card overflow-hidden border border-base-300 bg-base-100 shadow-sm">
  <div class="flex items-center gap-2 border-b border-base-300 bg-base-100 px-4 py-2 text-[11px] text-base-content/50 sm:hidden"><span class="material-symbols-outlined text-sm">swipe</span><span>Geser ke samping untuk melihat kolom lainnya</span></div>
  <div class="overflow-x-auto">
    <table class="table w-full">
      <thead>
        <tr>
          <th>Ditambahkan</th>
          <th>Nama Pengguna</th>
          <th>Alamat</th>
          <th class="text-center">Jumlah Pesanan</th>
          <th class="text-center">Riwayat</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($data['customers'])): ?>
        <tr>
          <td colspan="5" class="py-14 text-center">
            <div class="flex flex-col items-center text-base-content/45">
              <span class="material-symbols-outlined mb-2 text-4xl">group_off</span>
              <span class="text-sm font-semibold">Belum ada data pelanggan.</span>
            </div>
          </td>
        </tr>
        <?php else: ?>
        <?php foreach ($data['customers'] as $customer): ?>
        <tr class="hover">
          <td class="whitespace-nowrap">
            <?php if (empty($customer['created_at']) || $customer['created_at'] === '0000-00-00 00:00:00'): ?>
              <div class="skeleton h-4 w-20 rounded-md"></div>
            <?php else: ?>
              <div class="text-xs font-medium"><?= date('d M Y', strtotime($customer['created_at'])); ?></div>
              <div class="text-[11px] opacity-70"><?= date('H:i', strtotime($customer['created_at'])); ?></div>
            <?php endif; ?>
          </td>
          <td><div class="font-bold text-sm">@<?= htmlspecialchars($customer['username'] ?: '-'); ?></div></td>
          <td><span class="tooltip tooltip-top block max-w-[320px] whitespace-normal text-xs font-medium leading-snug text-base-content line-clamp-2" data-tip="<?= htmlspecialchars($customer['address'] ?: '-'); ?>"><?= htmlspecialchars($customer['address'] ?: '-'); ?></span></td>
          <td class="text-center"><span class="text-sm font-black text-base-content"><?= number_format($customer['total_orders'] ?? 0) ?></span></td>
          <td class="text-center"><button type="button" class="tooltip tooltip-top btn btn-ghost btn-sm btn-square" data-tip="Lihat riwayat pesanan" onclick="openCustomerHistory(<?= htmlspecialchars(json_encode($customer['username'] ?: ''), ENT_QUOTES, 'UTF-8'); ?>)"><span class="material-symbols-outlined text-lg">list</span></button></td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <div class="flex flex-wrap items-center justify-between gap-3 border-t border-base-content/8 bg-base-200/35 px-4 py-3">
    <div class="flex items-center gap-2 text-xs text-base-content/60">
      Tampilkan
      <select class="select select-sm h-8 min-h-8 w-18" onchange="window.location.href = '?limit=' + this.value">
        <?php foreach ([10, 20, 50, 100] as $option): ?>
        <option value="<?= $option ?>" <?= $data['limit'] == $option ? 'selected' : '' ?>><?= $option ?></option>
        <?php endforeach; ?>
      </select>
      dari <span class="font-bold text-base-content"><?= number_format($data['total_customers']) ?></span> data
    </div>
    <?php if ($data['total_pages'] > 1): ?>
    <div class="join">
      <?php if ($data['current_page'] > 1): ?><a href="?limit=<?= $data['limit'] ?>&page=<?= $data['current_page'] - 1 ?>" class="join-item btn btn-sm">«</a><?php else: ?><button class="join-item btn btn-sm btn-disabled">«</button><?php endif; ?>
      <button class="join-item btn btn-sm no-animation">Hal <?= $data['current_page'] ?> / <?= $data['total_pages'] ?></button>
      <?php if ($data['current_page'] < $data['total_pages']): ?><a href="?limit=<?= $data['limit'] ?>&page=<?= $data['current_page'] + 1 ?>" class="join-item btn btn-sm">»</a><?php else: ?><button class="join-item btn btn-sm btn-disabled">»</button><?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<dialog id="customer-history-modal" class="modal">
  <div class="modal-box flex max-h-[80vh] max-w-3xl flex-col overflow-hidden p-0">
    <div class="flex shrink-0 items-center justify-between border-b border-base-content/10 px-5 py-4">
      <div><p class="text-[10px] font-bold uppercase tracking-[0.15em] text-primary">Riwayat Pesanan</p><h3 class="mt-1 text-base font-black" id="customer-history-title">Pesanan</h3></div>
      <form method="dialog"><button class="btn btn-ghost btn-sm btn-square" aria-label="Tutup"><span class="material-symbols-outlined">close</span></button></form>
    </div>
    <div class="min-h-0 flex-1 overflow-auto" id="customer-history-content"></div>
    <div class="flex shrink-0 items-center border-t border-base-content/10 bg-base-200/40 px-5 py-3">
      <div class="flex items-center gap-2">
        <div class="grid h-7 w-7 place-items-center rounded-lg bg-primary text-primary-content"><span class="material-symbols-outlined text-base">bolt</span></div>
        <span class="text-xs font-black tracking-tight text-base-content"><?= app_name; ?></span>
      </div>
    </div>
  </div>
  <form method="dialog" class="modal-backdrop"><button>Tutup</button></form>
</dialog>

<script>
function escapeCustomerHistory(value) {
  return String(value ?? '').replace(/[&<>'"]/g, character => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' })[character]);
}

function customerOrderStatusBadge(status) {
  const statusType = String(status || '');
  const normalizedStatus = statusType.toLowerCase();
  let displayStatus = statusType || '-';
  let statusBadge = 'badge-ghost';
  let statusDot = 'bg-base-content opacity-50';

  if (normalizedStatus === 'cancelled') displayStatus = 'Dibatalkan';
  else if (normalizedStatus === 'completed') displayStatus = 'Selesai';
  else if (normalizedStatus === 'unpaid') displayStatus = 'Belum Dibayar';
  else if (normalizedStatus === 'to ship') displayStatus = 'Perlu Dikirim';
  else if (normalizedStatus === 'shipping' || normalizedStatus === 'shipped') displayStatus = 'Dikirim';
  else if (normalizedStatus === 'order received') displayStatus = 'Diterima';
  else if (normalizedStatus === 'delivered') displayStatus = 'Terkirim';

  if (normalizedStatus.includes('completed') || normalizedStatus.includes('selesai') || normalizedStatus.includes('shipped') || normalizedStatus.includes('kirim') || normalizedStatus.includes('terkirim') || normalizedStatus.includes('delivered')) {
    statusBadge = 'badge-success text-white';
    statusDot = 'bg-white opacity-80';
  } else if (normalizedStatus.includes('order received') || normalizedStatus.includes('diterima')) {
    statusBadge = 'badge-info text-white';
    statusDot = 'bg-white opacity-80';
  } else if (normalizedStatus.includes('cancel') || normalizedStatus.includes('batal')) {
    statusBadge = 'badge-error text-white';
    statusDot = 'bg-white opacity-80';
  } else if (normalizedStatus.includes('unpaid') || normalizedStatus.includes('bayar')) {
    statusBadge = 'badge-warning text-white';
    statusDot = 'bg-white opacity-80';
  } else if (normalizedStatus.includes('to ship') || normalizedStatus.includes('perlu')) {
    statusBadge = 'badge-primary text-white';
    statusDot = 'bg-white opacity-80';
  }

  return '<span class="badge ' + statusBadge + ' badge-sm gap-1"><span class="w-1.5 h-1.5 rounded-full ' + statusDot + '"></span>' + escapeCustomerHistory(displayStatus) + '</span>';
}

function openCustomerHistory(username) {
  const modal = document.getElementById('customer-history-modal');
  const title = document.getElementById('customer-history-title');
  const content = document.getElementById('customer-history-content');
  title.textContent = '@' + username;
  content.innerHTML = '<div class="flex items-center justify-center gap-3 p-12 text-sm text-base-content/60"><span class="loading loading-spinner loading-sm text-primary"></span>Memuat riwayat pesanan...</div>';
  modal.showModal();

  const formData = new FormData();
  formData.append('username', username);
  fetch('<?= burl; ?>/proccustomers/history', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: formData })
    .then(response => response.json())
    .then(data => {
      if (data.status !== 'success') throw new Error(data.message || 'Gagal memuat riwayat.');
      if (!data.orders.length) {
        content.innerHTML = '<div class="p-12 text-center text-sm text-base-content/55">Belum ada pesanan untuk pelanggan ini.</div>';
        return;
      }
      content.innerHTML = '<table class="table w-full"><thead class="sticky top-0 z-10"><tr><th>Tanggal</th><th>No. Pesanan</th><th class="w-[220px]">Produk</th><th class="text-right">Total</th><th class="whitespace-nowrap">Status</th></tr></thead><tbody>' + data.orders.map(order => {
        const date = order.created_at ? new Date(order.created_at.replace(' ', 'T')) : null;
        const dateCell = date ? '<div class="text-xs font-medium">' + escapeCustomerHistory(date.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })) + '</div><div class="text-[11px] opacity-70">' + escapeCustomerHistory(date.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', hour12: false })) + '</div>' : '<span class="text-xs">-</span>';
        const total = new Intl.NumberFormat('id-ID').format(order.total_price || 0);
        const itemName = order.item_name || '-';
        const itemImage = order.item_image ? 'https://cf.shopee.co.id/file/' + order.item_image : '';
        const variation = order.variation_name ? 'Var: ' + order.variation_name + ' · ' : '';
        const quantity = order.quantity ? 'Qty: ' + order.quantity : '';
        const itemVisual = itemImage ? '<img src="' + escapeCustomerHistory(itemImage) + '" class="h-8 w-8 shrink-0 rounded object-cover border border-base-200" alt="">' : '<div class="grid h-8 w-8 shrink-0 place-items-center rounded bg-base-200 border border-base-300"><span class="material-symbols-outlined text-sm opacity-50">image</span></div>';
        return '<tr class="hover"><td class="whitespace-nowrap">' + dateCell + '</td><td><div class="text-xs font-bold">' + escapeCustomerHistory(order.order_sn || '-') + '</div><div class="mt-0.5 font-mono text-[10px] text-base-content/50">' + escapeCustomerHistory(order.id) + '</div></td><td class="w-[220px] max-w-[220px]"><div class="flex items-start gap-2"><div>' + itemVisual + '</div><div class="min-w-0"><div class="tooltip tooltip-top block max-w-[165px] truncate text-xs font-medium" data-tip="' + escapeCustomerHistory(itemName) + '">' + escapeCustomerHistory(itemName) + '</div><div class="tooltip tooltip-top max-w-[165px] truncate mt-0.5 text-[10px] text-base-content/60" data-tip="' + escapeCustomerHistory(variation + quantity) + '">' + escapeCustomerHistory(variation + quantity) + '</div></div></div></td><td class="whitespace-nowrap text-right text-xs font-bold">Rp ' + total + '</td><td class="whitespace-nowrap">' + customerOrderStatusBadge(order.status_type) + '</td></tr>';
      }).join('') + '</tbody></table>';
    })
    .catch(error => { content.innerHTML = '<div class="p-12 text-center text-sm text-error">' + escapeCustomerHistory(error.message) + '</div>'; });
}
</script>
