<!-- Modal Konfirmasi Hapus Global -->
<dialog id="modal_delete" class="modal modal-bottom sm:modal-middle">
  <div class="modal-box bg-base-100 rounded-2xl shadow-xl p-0 overflow-hidden">
    <!-- Modal Header -->
    <div class="px-6 py-5 border-b border-base-200 bg-error/10 flex justify-between items-center">
      <h3 class="font-bold text-lg text-error flex items-center gap-2">
        <span class="material-symbols-outlined">warning</span>
        Konfirmasi Hapus Data
      </h3>
      <form method="dialog">
        <button class="tooltip tooltip-left btn btn-sm btn-circle btn-ghost text-base-content/50 hover:text-base-content hover:bg-base-200 transition-colors" data-tip="Tutup" aria-label="Tutup">✕</button>
      </form>
    </div>
    
    <!-- Modal Body -->
    <div class="p-6">
      <p class="text-base-content/80 text-[15px] mb-4 leading-relaxed">
        Apakah Anda yakin ingin menghapus data <strong id="delete_target_name" class="text-base-content font-bold"></strong>? 
      </p>
      <div class="text-xs text-error/90 bg-error/10 p-3 rounded-lg flex gap-2 items-start">
        <span class="material-symbols-outlined text-[18px] shrink-0">info</span>
        <span>Perhatian: Tindakan ini bersifat permanen dan tidak dapat dibatalkan. Semua data yang berkaitan akan hilang dari sistem.</span>
      </div>
    </div>
    
    <!-- Modal Footer -->
    <div class="px-6 py-4 border-t border-base-200 bg-base-200/30 flex justify-end gap-3">
      <form method="dialog">
        <button class="btn btn-ghost px-6 hover:bg-base-200">Batal</button>
      </form>
      <a href="#" id="btn_confirm_delete" class="btn btn-error px-6 gap-2 text-white shadow-sm hover:shadow-md transition-shadow">
        <span class="material-symbols-outlined text-[18px]">delete_forever</span>
        Ya, Hapus Data
      </a>
    </div>
  </div>
  <form method="dialog" class="modal-backdrop">
    <button>close</button>
  </form>
</dialog>

<!-- Script Trigger Modal Hapus Dinamis -->
<script>
  function confirmDelete(id, name, endpoint) {
    // Sisipkan nama target ke dalam modal
    document.getElementById('delete_target_name').innerText = name;
    
    // Setel URL aksi hapus pada tombol konfirmasi
    document.getElementById('btn_confirm_delete').href = '<?= burl; ?>' + endpoint + '/' + id;
    
    // Munculkan modal
    document.getElementById('modal_delete').showModal();
  }
</script>
