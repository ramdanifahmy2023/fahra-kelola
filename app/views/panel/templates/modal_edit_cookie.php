<!-- Modal Edit Cookie -->
<dialog id="modal_edit_cookie" class="modal modal-bottom sm:modal-middle" aria-labelledby="edit-cookie-title" aria-describedby="edit-cookie-status">
  <div class="modal-box bg-base-100 rounded-2xl shadow-xl p-0 overflow-hidden">
    <!-- Modal Header -->
    <div class="px-6 py-5 border-b border-base-200 flex justify-between items-center bg-base-100/50">
      <h3 id="edit-cookie-title" class="font-bold text-lg text-base-content flex items-center gap-2">
        <span class="material-symbols-outlined text-warning" aria-hidden="true">key</span>
        Update Cookie Session
      </h3>
      <form method="dialog">
        <button class="tooltip tooltip-left btn btn-sm btn-circle btn-ghost text-base-content/50 hover:text-base-content hover:bg-base-200 transition-colors" data-tip="Tutup" aria-label="Tutup">✕</button>
      </form>
    </div>
    
    <!-- Modal Body -->
    <form id="form_edit_cookie" action="" method="POST">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(authCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
      <div class="p-6 space-y-5">
        
        <div class="bg-warning/10 border border-warning/20 rounded-lg p-3 flex gap-3 items-center">
            <span class="material-symbols-outlined text-warning" aria-hidden="true">storefront</span>
            <div>
                <p class="text-xs font-semibold text-warning-content opacity-70">Toko Target</p>
                <p class="text-sm font-bold text-base-content" id="edit_cookie_target_name">-</p>
            </div>
        </div>

        <div class="form-control w-full">
          <label for="edit-shop-cookie" class="label pb-1.5 px-1 flex justify-between">
            <span class="label-text font-semibold text-base-content/90">Cookies Session</span>
            <span class="label-text-alt text-error font-medium">* Wajib</span>
          </label>
          <div class="relative">
            <textarea id="edit-shop-cookie" name="cookie" aria-describedby="edit-shop-cookie-help" class="textarea textarea-bordered w-full h-32 focus:textarea-warning transition-colors text-sm leading-relaxed bg-base-100/50 hover:bg-base-100 shadow-sm resize-none" placeholder="Paste data cookie di sini...&#10;Contoh: session_id=xyz123; user_token=abc890;" required></textarea>
          </div>
          <p id="edit-shop-cookie-help" class="label pt-1.5 px-1">
            <span class="label-text-alt opacity-60 flex items-center gap-1">
              <span class="material-symbols-outlined text-[14px]" aria-hidden="true">info</span>
              Pastikan cookie yang dimasukkan adalah cookie terbaru.
            </span>
          </p>
        </div>
      </div>
      <p id="edit-cookie-status" class="px-6 pb-3 text-sm" role="status" aria-live="polite"></p>
      
      <!-- Modal Footer -->
      <div class="px-6 py-4 border-t border-base-200 bg-base-200/30 flex justify-end gap-3">
        <button type="button" class="btn btn-ghost px-6 hover:bg-base-200" onclick="document.getElementById('modal_edit_cookie').close()">Batal</button>
        <button type="submit" class="btn btn-warning px-6 gap-2 shadow-sm text-warning-content">
          <span class="material-symbols-outlined text-[18px]" aria-hidden="true">save</span>
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
  let editCookieDialogTrigger = null;
  function triggerEditCookie(id, name) {
    editCookieDialogTrigger = document.activeElement;
    document.getElementById('edit_cookie_target_name').innerText = name;
    // Ubah endpoint-nya langsung ke update agar melakukan siklus sync data penuh
    document.getElementById('form_edit_cookie').action = '<?= burl; ?>/procshops/update/' + id;
    document.getElementById('form_edit_cookie').reset();
    document.getElementById('modal_edit_cookie').showModal();
  }

  document.getElementById('modal_edit_cookie').addEventListener('close', function() {
    if (editCookieDialogTrigger && document.contains(editCookieDialogTrigger)) editCookieDialogTrigger.focus();
    editCookieDialogTrigger = null;
  });

  // Intersep form submission untuk di-handle via AJAX
  document.getElementById('form_edit_cookie').addEventListener('submit', function(e) {
      e.preventDefault();
      
      const form = this;
      const btn = form.querySelector('button[type="submit"]');
      const originalText = btn.innerHTML;
      
      // Tambahkan animasi spinner ke tombol
      btn.innerHTML = '<span class="material-symbols-outlined animate-spin [animation-direction:reverse] text-[18px]" aria-hidden="true">sync</span> Menyinkronkan...';
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
              document.getElementById('edit-cookie-status').textContent = data.message || 'Cookie belum dapat diperbarui.';
          }
      })
      .catch(err => {
          btn.innerHTML = originalText;
          btn.disabled = false;
          btn.classList.remove('opacity-80');
          document.getElementById('edit-cookie-status').textContent = 'Gagal menghubungi server Shopee.';
      });
  });
</script>
