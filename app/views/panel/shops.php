<section id="shops-page" aria-label="Toko terhubung">
<!-- Page Header -->
<div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
  <div class="min-w-0">
    <h2 class="text-2xl font-bold mb-1 text-base-content">Daftar Toko</h2>
  </div>
  <div class="w-full sm:w-auto">
    <button class="btn btn-primary w-full gap-2 shadow-sm sm:w-auto" onclick="add_shop_modal.showModal()">
      <span class="material-symbols-outlined">add</span>
      Tambah Toko
    </button>
  </div>
</div>

<!-- Table Section -->
<div class="card overflow-hidden border border-base-300 bg-base-100 shadow-sm">
  <div class="shop-table-scroll overflow-x-auto">
    <table role="table" class="shop-table table w-full" aria-label="Daftar toko terhubung">
      <!-- head -->
      <thead class="bg-base-200/50 text-base-content">
        <tr>
          <th scope="col">Nama Toko & Kontak</th>
          <th scope="col">ID Toko</th>
          <th scope="col" class="text-right">Saldo Toko</th>
          <th scope="col" class="text-right">Kredit Iklan</th>
          <th scope="col" class="text-center">Jumlah Produk</th>
          <th scope="col">Status</th>
          <th scope="col" class="text-center w-32">Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($data['shops'])): ?>
        <tr>
          <td colspan="7" class="text-center py-10">
            <div class="flex flex-col items-center justify-center text-base-content/50">
              <span class="material-symbols-outlined text-[48px] mb-2 opacity-50">add_business</span>
              <p>Belum ada toko yang ditambahkan.</p>
            </div>
          </td>
        </tr>
        <?php else: ?>
        <?php foreach ($data['shops'] as $shop): ?>
        <?php $shopActionName = htmlspecialchars(json_encode($shop['name'] ?: 'Toko Belum Sinkron', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8'); ?>
        <tr class="hover" id="shop-<?= (int)$shop['id']; ?>">
          <td class="shop-identity-cell">
            <div class="flex items-center gap-3">
              <div class="avatar">
                <div class="w-10 h-10 rounded-lg bg-base-200 text-base-content flex items-center justify-center overflow-hidden">
                  <?php if (!empty($shop['shop_logo'])): ?>
                    <img src="<?= htmlspecialchars($shop['shop_logo']); ?>" alt="Logo" class="w-full h-full object-cover" />
                  <?php else: ?>
                    <span class="material-symbols-outlined opacity-50">storefront</span>
                  <?php endif; ?>
                </div>
              </div>
              <div>
                <div class="font-bold text-sm"><?= htmlspecialchars($shop['name'] ?: 'Toko Belum Sinkron'); ?></div>
                <div class="text-[11px] opacity-60 font-medium">@<?= htmlspecialchars($shop['username'] ?? '-'); ?></div>
              </div>
            </div>
          </td>
          <td data-label="ID Toko" class="font-semibold text-xs opacity-70">
            <?= htmlspecialchars($shop['shop_id'] ?: '-'); ?>
          </td>
          <td data-label="Saldo Toko" class="text-right font-semibold text-sm">
            Rp <?= number_format($shop['balances'] ?? 0, 0, ',', '.'); ?>
          </td>
          <td data-label="Kredit Iklan" class="text-right font-semibold text-sm">
            Rp <?= number_format($shop['ads_credit'] ?? 0, 0, ',', '.'); ?>
          </td>
          <td data-label="Jumlah Produk" class="text-center font-semibold text-sm opacity-80">
            <?= $shop['total_products'] ?? 0; ?>
          </td>
          <td data-label="Status">
            <?php if ($shop['sync_status'] === 'connected'): ?>
              <span class="badge badge-sm shop-connection-state font-medium px-2 py-2.5" data-connection="connected"><span class="material-symbols-outlined" aria-hidden="true">check_circle</span>Terhubung</span>
            <?php elseif ($shop['sync_status'] === 'expired'): ?>
              <span class="badge badge-sm shop-connection-state font-medium px-2 py-2.5" data-connection="expired"><span class="material-symbols-outlined" aria-hidden="true">link_off</span>Koneksi Putus</span>
            <?php else: ?>
              <span class="badge badge-ghost badge-sm font-medium px-2 py-2.5"><?= htmlspecialchars(ucfirst($shop['sync_status'] ?: 'Unknown')); ?></span>
            <?php endif; ?>
          </td>
          <td class="shop-action-cell text-center">
            <div class="flex items-center justify-center gap-1">
              <button type="button" onclick="triggerEditCookie(<?= (int)$shop['id']; ?>, <?= $shopActionName; ?>)" class="btn btn-sm btn-ghost shop-connection-action" aria-label="Perbarui koneksi <?= htmlspecialchars($shop['name'] ?: 'Toko', ENT_QUOTES, 'UTF-8'); ?>">
                <span class="material-symbols-outlined" aria-hidden="true">key</span><span>Perbarui koneksi</span>
              </button>
              <button type="button" onclick="confirmDelete(<?= (int)$shop['id']; ?>, <?= $shopActionName; ?>, '/procshops/delete')" class="btn btn-sm btn-ghost btn-square" aria-label="Hapus toko <?= htmlspecialchars($shop['name'] ?: 'Toko', ENT_QUOTES, 'UTF-8'); ?>">
                <span class="material-symbols-outlined" aria-hidden="true">delete</span>
              </button>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  
  <!-- Pagination (Opsional) -->
  <div class="p-4 border-t border-base-200 flex items-center justify-between">
    <div class="text-sm opacity-70">
      Total Toko: <span class="font-bold text-base-content"><?= count($data['shops'] ?? []); ?></span>
    </div>
  </div>
</div>

<!-- Modal Tambah Toko -->
<dialog id="add_shop_modal" class="modal modal-bottom sm:modal-middle">
  <div class="modal-box bg-base-100 rounded-2xl shadow-xl p-0 overflow-hidden">
    <!-- Modal Header -->
    <div class="px-6 py-5 border-b border-base-200 flex justify-between items-center bg-base-100/50">
      <h3 class="font-bold text-lg text-base-content flex items-center gap-2">
        <span class="material-symbols-outlined text-primary">add_business</span>
        Tambah Toko Baru
      </h3>
      <form method="dialog">
        <button class="tooltip tooltip-left btn btn-sm btn-circle btn-ghost text-base-content/50 hover:text-base-content hover:bg-base-200 transition-colors" data-tip="Tutup" aria-label="Tutup">✕</button>
      </form>
    </div>
    
    <!-- Modal Body -->
    <form action="<?= burl; ?>/procshops/add" method="POST">
      <div class="p-6 space-y-5">
        <div class="form-control w-full">
          <label class="label pb-1.5 px-1">
            <span class="label-text font-semibold text-base-content/90">Email Login Toko</span>
          </label>
          <div class="relative">
            <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 text-base-content/40">
              <span class="material-symbols-outlined text-[20px]">mail</span>
            </span>
            <input type="email" name="email" placeholder="contoh@shopdash.com" class="input input-bordered w-full pl-11 focus:input-primary transition-colors bg-base-100/50 hover:bg-base-100 shadow-sm" required />
          </div>
        </div>
      
        <div class="form-control w-full">
          <label class="label pb-1.5 px-1 flex justify-between">
            <span class="label-text font-semibold text-base-content/90">Cookies Session</span>
            <span class="label-text-alt text-error font-medium">* Wajib</span>
          </label>
          <div class="relative">
            <textarea name="cookie" class="textarea textarea-bordered w-full h-32 focus:textarea-primary transition-colors text-sm leading-relaxed bg-base-100/50 hover:bg-base-100 shadow-sm resize-none" placeholder="Paste data cookie di sini...&#10;Contoh: session_id=xyz123; user_token=abc890;" required></textarea>
          </div>
          <label class="label pt-1.5 px-1">
            <span class="label-text-alt opacity-60 flex items-center gap-1">
              <span class="material-symbols-outlined text-[14px]">info</span>
              Pastikan cookie yang dimasukkan masih valid.
            </span>
          </label>
        </div>
      </div>
      
      <!-- Modal Footer -->
      <div class="px-6 py-4 border-t border-base-200 bg-base-200/30 flex justify-end gap-3">
        <button type="button" class="btn btn-ghost px-6 hover:bg-base-200" onclick="add_shop_modal.close()">Batal</button>
        <button type="submit" class="btn btn-primary px-6 gap-2 shadow-sm">
          <span class="material-symbols-outlined text-[18px]">save</span>
          Simpan Toko
        </button>
      </div>
    </form>
  </div>
  <form method="dialog" class="modal-backdrop">
    <button>close</button>
  </form>
</dialog>

<!-- Komponen Tambahan -->
<?php require_once __DIR__ . '/templates/modal_delete.php'; ?>
<?php require_once __DIR__ . '/templates/modal_edit_cookie.php'; ?>

<!-- Script Sync Data via AJAX -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Hindari infinite loop: cek apakah URL memiliki parameter synced=1
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('synced')) return;

    const queue = <?= json_encode(array_map(function($s) { return ['id' => $s['id'], 'name' => $s['name'] ?: 'Toko Belum Sinkron']; }, $data['shops'] ?? [])); ?>;
    if (queue.length === 0) return;

    let total = queue.length;
    let isReloading = false;
    
    function runQueue() {
        const card = document.getElementById('sync-detail-card');
        const badge = document.getElementById('sync-detail-badge');
        const progress = document.getElementById('sync-detail-progress');
        const textLabel = document.getElementById('sync-detail-text');
        const textId = document.getElementById('sync-detail-id');
        
        if (queue.length === 0) {
            if (card && card.classList.contains('flex')) {
                textLabel.innerText = 'Selesai!';
                textId.innerText = '';
                setTimeout(() => {
                    card.classList.remove('flex');
                    card.classList.add('hidden');
                    if (!isReloading) {
                        isReloading = true;
                        // Reload dengan parameter penanda agar tidak sync ulang
                        window.location.href = window.location.pathname + '?synced=1';
                    }
                }, 2500);
            }
            return;
        }

        if (card) {
            card.classList.remove('hidden');
            card.classList.add('flex');
        }
        if (progress) progress.max = total;
        
        const currentShop = queue.shift();
        let completed = total - queue.length; 
        
        if (textLabel) textLabel.innerText = 'Sinkronisasi toko';
        if (textId) textId.innerText = currentShop.name;
        
        fetch('<?= burl; ?>/procshops/update/' + currentShop.id, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(res => res.json())
        .then(data => {
            if (progress) progress.value = completed;
            if (badge) badge.innerText = Math.round((completed / total) * 100) + '%';
            setTimeout(runQueue, 200);
        })
        .catch(err => {
            setTimeout(runQueue, 200);
        });
    }

    runQueue();
});
</script>
</section>
