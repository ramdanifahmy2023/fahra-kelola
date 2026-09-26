<!-- Modal Edit Cookie -->
<dialog id="modal_edit_cookie" class="modal modal-bottom sm:modal-middle">
  <div class="modal-box bg-base-100 rounded-2xl shadow-xl p-0 overflow-hidden">
    <!-- Modal Header -->
    <div class="px-6 py-5 border-b border-base-200 flex justify-between items-center bg-base-100/50">
      <h3 class="font-bold text-lg text-base-content flex items-center gap-2">
        <span class="material-symbols-outlined text-warning">key</span>
        Update Cookie Session
      </h3>
      <form method="dialog">
        <button class="tooltip tooltip-left btn btn-sm btn-circle btn-ghost text-base-content/50 hover:text-base-content hover:bg-base-200 transition-colors" data-tip="Tutup" aria-label="Tutup">✕</button>
      </form>
    </div>
    
    <!-- Modal Body -->
    <form id="form_edit_cookie" action="" method="POST">
      <div class="p-6 space-y-5">
        
        <div class="bg-warning/10 border border-warning/20 rounded-lg p-3 flex gap-3 items-center">
            <span class="material-symbols-outlined text-warning">storefront</span>
            <div>
                <p class="text-xs font-semibold text-warning-content opacity-70">Toko Target</p>
                <p class="text-sm font-bold text-base-content" id="edit_cookie_target_name">-</p>
            </div>
        </div>

        <div class="form-control w-full">
          <label class="label pb-1.5 px-1 flex justify-between">
            <span class="label-text font-semibold text-base-content/90">Cookies Session</span>
            <span class="label-text-alt text-error font-medium">* Wajib</span>
          </label>
          <div class="relative">
            <textarea name="cookie" class="textarea textarea-bordered w-full h-32 focus:textarea-warning transition-colors text-sm leading-relaxed bg-base-100/50 hover:bg-base-100 shadow-sm resize-none" placeholder="Paste data cookie di sini...&#10;Contoh: session_id=xyz123; user_token=abc890;" required></textarea>
          </div>
          <label class="label pt-1.5 px-1">
            <span class="label-text-alt opacity-60 flex items-center gap-1">
              <span class="material-symbols-outlined text-[14px]">info</span>
              Pastikan cookie yang dimasukkan adalah cookie terbaru.
            </span>
          </label>
        </div>
      </div>
      
      <!-- Modal Footer -->
      <div class="px-6 py-4 border-t border-base-200 bg-base-200/30 flex justify-end gap-3">
        <button type="button" class="btn btn-ghost px-6 hover:bg-base-200" onclick="document.getElementById('modal_edit_cookie').close()">Batal</button>
        <button type="submit" class="btn btn-warning px-6 gap-2 shadow-sm text-warning-content">
          <span class="material-symbols-outlined text-[18px]">save</span>
          Simpan Cookie
        </button>
      </div>
    </form>
  </div>
  <form method="dialog" class="modal-backdrop">
    <button>close</button>
  </form>
</dialog>

<!-- Script Trigger Modal Edit Cookie -->
<script>
  function triggerEditCookie(id, name) {
    document.getElementById('edit_cookie_target_name').innerText = name;
    // Ubah endpoint-nya langsung ke update agar melakukan siklus sync data penuh
    document.getElementById('form_edit_cookie').action = '<?= burl; ?>/procshops/update/' + id;
    document.getElementById('form_edit_cookie').reset();
    document.getElementById('modal_edit_cookie').showModal();
  }

  // Intersep form submission untuk di-handle via AJAX
  document.getElementById('form_edit_cookie').addEventListener('submit', function(e) {
      e.preventDefault();
      
      const form = this;
      const btn = form.querySelector('button[type="submit"]');
      const originalText = btn.innerHTML;
      
      // Tambahkan animasi spinner ke tombol
      btn.innerHTML = '<span class="material-symbols-outlined animate-spin [animation-direction:reverse] text-[18px]">sync</span> Menyinkronkan...';
      btn.disabled = true;
      btn.classList.add('opacity-80');
      
      const formData = new FormData(form);
      
      fetch(form.action, {
          method: 'POST',
          headers: {
              'X-Requested-With': 'XMLHttpRequest'
          },
          body: formData
      })
      .then(res => res.json())
      .then(data => {
          btn.innerHTML = originalText;
          btn.disabled = false;
          btn.classList.remove('opacity-80');
          
          if (data.status === 'success') {
              window.location.reload();
          } else {
              alert(data.message);
          }
      })
      .catch(err => {
          btn.innerHTML = originalText;
          btn.disabled = false;
          btn.classList.remove('opacity-80');
          alert('Gagal menghubungi server Shopee.');
      });
  });
</script>
