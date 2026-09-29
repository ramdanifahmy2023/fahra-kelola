<div class="mb-5 flex flex-wrap items-end justify-between gap-3">
  <div>
    <h2 class="text-xl font-black tracking-tight text-base-content">Status Sinkronisasi</h2>
  </div>
  <button type="button" class="btn btn-primary btn-sm gap-2" onclick="loadSyncStatus()"><span class="material-symbols-outlined text-base">refresh</span>Muat ulang status</button>
</div>

<div id="sync-dashboard" class="grid gap-3 md:grid-cols-2 xl:grid-cols-3"></div>

<script>
const escapeSyncText = value => String(value || '').replace(/[&<>"']/g, char => ({'&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;'}[char]));
const syncIntervals = [30, 60, 180, 300, 600, 900, 1800, 3600, 43200, 86400];
const syncLabels = {orders:'Pesanan', chat:'Live chat', products:'Produk', promotions:'Voucher & flash sale', ads:'Iklan', ads_topups:'Topup saldo iklan', customers:'Pelanggan', shops:'Status toko', packages:'Paket'};
function formatInterval(seconds) { seconds = Number(seconds || 0); if (seconds >= 86400) return Math.round(seconds / 86400) + ' hari'; if (seconds >= 3600) return Math.round(seconds / 3600) + ' jam'; if (seconds >= 60) return Math.round(seconds / 60) + ' menit'; return seconds + ' detik'; }
function formatDate(value) { if (!value) return 'Belum pernah'; return new Date(String(value).replace(' ', 'T') + 'Z').toLocaleString('id-ID'); }
function renderSyncCards(rows) {
  const target = document.getElementById('sync-dashboard'); if (!target) return;
  target.innerHTML = rows.map(row => {
    const running = ['queued','running'].includes(row.job_status); const failed = row.job_status === 'failed' || row.last_error || row.job_error;
    const tone = failed ? 'border-error/30 bg-error/5' : (running ? 'border-primary/30 bg-primary/5' : 'border-base-300 bg-base-100');
    const status = failed ? 'Error' : (running ? 'Berjalan' : 'Selesai');
    const options = syncIntervals.map(value => `<option value="${value}" ${Number(row.interval_seconds) === value ? 'selected' : ''}>${formatInterval(value)}</option>`).join('');
    return `<article class="card border ${tone} shadow-sm"><div class="card-body gap-3 p-4"><div class="flex items-start justify-between gap-3"><div><div class="text-[10px] font-bold uppercase tracking-[.15em] text-base-content/45">${row.shop_name || 'Toko #' + row.shop_id}</div><h3 class="mt-1 font-black">${syncLabels[row.sync_type] || row.sync_type}</h3></div><span class="badge ${failed ? 'badge-error' : (running ? 'badge-primary' : 'badge-success')} badge-sm">${status}</span></div><div class="grid grid-cols-2 gap-2 text-[11px] text-base-content/60"><div>Terakhir sukses<br><strong class="text-base-content">${formatDate(row.last_success_at)}</strong></div><div>Berikutnya<br><strong class="text-base-content">${formatDate(row.next_run_at)}</strong></div></div>${failed ? `<div class="rounded-lg bg-error/10 px-2.5 py-2 text-[11px] text-error">Pembaruan gagal. Periksa koneksi toko.<details class="mt-2"><summary class="cursor-pointer py-2">Detail kesalahan</summary>${escapeSyncText(row.last_error || row.job_error)}</details></div>` : ''}<div class="flex items-center gap-2 border-t border-base-content/10 pt-3"><label class="text-[11px] text-base-content/60">Interval</label><select class="select select-bordered select-xs flex-1" onchange="configureSync(${row.shop_id}, '${row.sync_type}', this.value, ${row.enabled ? 1 : 0})">${options}</select><label class="flex items-center gap-1 text-[11px] text-base-content/60"><input type="checkbox" class="toggle toggle-primary toggle-xs" ${row.enabled ? 'checked' : ''} onchange="configureSync(${row.shop_id}, '${row.sync_type}', ${row.interval_seconds}, this.checked ? 1 : 0)">Aktif</label></div></div></article>`;
  }).join('');
}
function loadSyncStatus() { const target = document.getElementById('sync-dashboard'); if (target && !target.children.length) target.innerHTML = '<div class="col-span-full py-10 text-center text-sm text-base-content/50">Memuat status...</div>'; fetch('<?= burl; ?>/procsync/status', {headers:{'X-Requested-With':'XMLHttpRequest'}}).then(r => r.json()).then(data => renderSyncCards(data.schedules || [])).catch(() => { if (target) target.innerHTML = '<div class="col-span-full py-10 text-center text-sm text-error">Status gagal dimuat.</div>'; }); }
function configureSync(shopId, type, interval, enabled) { const fd = new FormData(); fd.append('shop_id', shopId); fd.append('sync_type', type); fd.append('interval_seconds', interval); fd.append('enabled', enabled); fetch('<?= burl; ?>/procsync/configure', {method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'}, body:fd}).then(r => r.json()).then(data => { if (data.status !== 'success') throw new Error(data.message || 'Gagal memperbarui jadwal'); loadSyncStatus(); }).catch(error => alert(error.message)); }
document.addEventListener('DOMContentLoaded', loadSyncStatus);
</script>
