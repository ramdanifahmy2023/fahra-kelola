(() => {
  const root = document.getElementById('boost-monitor');
  if (!root) return;
  const $ = (sel, parent = root) => parent.querySelector(sel);
  const shops = JSON.parse(document.getElementById('boost-shops').textContent);
  const states = new Map();
  const editor = $('#boost-editor'), confirmDialog = $('#boost-confirm'), resolution = $('#boost-resolution');
  const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
  const date = value => value ? new Date(value.replace(' ', 'T') + 'Z').toLocaleString('id-ID', {timeZone:'Asia/Jakarta', day:'numeric', month:'short', hour:'2-digit', minute:'2-digit'}) + ' WIB' : 'Belum tersedia';
  const number = value => new Intl.NumberFormat('id-ID').format(Number(value || 0));
  const labels = {completed:'Selesai', partial:'Sebagian selesai', failed:'Gagal', running:'Diproses', reserved:'Belum dikirim', pending:'Perlu diperiksa', sending:'Hasil sedang diperiksa', unknown:'Belum pasti', success:'Diterima Shopee', not_sent:'Tidak dikirim'};
  let draft = null, confirmation = null, resolving = null;

  async function api(path, data) {
    const controller = new AbortController(), timeout = setTimeout(() => controller.abort(), path === 'run' ? 180000 : path === 'inspect' ? 90000 : 20000);
    let response;
    try {
      response = await fetch(root.dataset.endpoint + '/' + path, {method:data ? 'POST' : 'GET', cache:'no-store', signal:controller.signal, headers:{Accept:'application/json', ...(data ? {'Content-Type':'application/json','X-CSRF-Token':root.dataset.csrf} : {})}, ...(data ? {body:JSON.stringify(data)} : {})});
    } catch (_) { throw new Error('Koneksi terputus atau terlalu lama. Muat ulang status dan periksa riwayat.'); }
    finally { clearTimeout(timeout); }
    let payload;
    try { payload = await response.json(); } catch (_) { throw new Error('Sesi atau koneksi terputus. Muat ulang halaman dan periksa riwayat.'); }
    if (!response.ok || payload.status !== 'success') { const error = new Error(payload.message || 'Data gagal dimuat. Coba lagi.'); error.code = response.status; throw error; }
    return payload;
  }
  const notice = (node, message) => { node.textContent = message || ''; node.hidden = !message; };
  const idsEqual = (a, b) => JSON.stringify(a) === JSON.stringify(b);
  const dirty = () => draft && !idsEqual([...draft.selected.keys()], draft.original);
  const icon = name => '<svg class="boost-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><use href="#boost-icon-'+name+'"></use></svg>';
  const visualLabel = (node, name, text) => { node.innerHTML=icon(name)+'<span>'+esc(text)+'</span>'; };
  const slots = (node, count) => { node.innerHTML=Array.from({length:5},(_,i)=>'<i data-filled="'+(i<count)+'"></i>').join(''); };
  function updateSummary() {
    const loaded=[...states.values()].filter(local=>local.payload), complete=loaded.length===shops.length;
    $('[data-total-active]').textContent=complete ? loaded.filter(local=>local.payload.profile.enabled).length+' toko' : 'Belum dimuat';
    $('[data-total-products]').textContent=complete ? number(loaded.reduce((n,local)=>n+local.payload.profile.product_ids.length,0))+' produk' : 'Belum dimuat';
    $('[data-total-attention]').textContent=complete ? loaded.filter(local=>local.payload.summary.unresolved_count>0 || local.readError || local.payload.shop.session_status!=='connected' || (local.payload.profile.enabled && (!local.payload.sender_enabled || !Number(local.payload.worker?.alive)))).length+' toko' : 'Belum dimuat';
    const worker=$('[data-worker-state]');
    const failed=loaded.some(local=>local.readError), ready=loaded.length>0 && complete && loaded.every(local=>Number(local.payload.worker?.alive)), sending=loaded.length>0 && complete && loaded.every(local=>local.payload.sender_enabled);
    worker.dataset.tone=failed || (shops.length>0 && complete && !ready) ? 'warning' : ready && sending ? 'success' : 'neutral';
    visualLabel(worker,failed?'alert':'worker',!shops.length?'Belum ada toko':failed?'Status belum diperbarui':!complete?'Memuat '+loaded.length+' / '+shops.length+' toko':!sending?'Pengiriman server nonaktif':ready?'Worker terpantau':'Worker belum terpantau');
  }
  function buttonState(local) {
    const p = local.payload, busy = local.busy || local.loading, saved = p?.profile.product_ids.length || 0;
    $('[data-edit]',local.card).disabled = busy || !p;
    $('[data-refresh]',local.card).disabled = busy;
    $('[data-inspect]',local.card).disabled = busy || !p || (!saved && !p.unresolved.length);
    $('[data-toggle]',local.card).disabled = busy || !p || (!p.profile.enabled && (!saved || !p.sender_enabled));
    $('[data-manual]',local.card).disabled = busy || !p?.sender_enabled || p.summary.batch_active || p.summary.unresolved_count > 0 || p.summary.remaining_count === 0;
    local.card.setAttribute('aria-busy', String(!!busy));
  }
  function image(p) { return '<span class="boost-product-image" aria-hidden="true">'+icon('box') + (p.cover_image ? '<img width="48" height="48" loading="lazy" alt="" src="https://cf.shopee.co.id/file/' + encodeURIComponent(p.cover_image) + '">' : '') + '</span>'; }
  function bindImages(parent) { parent.querySelectorAll('img').forEach(img => img.addEventListener('error', () => img.remove(), {once:true})); }
  function render(local) {
    const p = local.payload, card = local.card, profile = p.profile;
    const blocked=profile.enabled && (!p.sender_enabled || !Number(p.worker?.alive));
    const state=p.summary.unresolved_count?'attention':p.summary.batch_active?'processing':blocked?'attention':profile.enabled?'active':profile.version?'paused':'empty';
    card.dataset.state=state;
    $('[data-mode]',card).dataset.tone=state==='attention'?'warning':state==='active'?'success':state==='processing'?'processing':'neutral';
    visualLabel($('[data-mode]',card),state==='attention'?'alert':state==='processing'?'refresh':state==='active'?'repeat':state==='paused'?'pause':'box',p.summary.unresolved_count ? 'Hasil perlu diperiksa' : p.summary.batch_active ? 'Memproses' : blocked ? 'Pengulangan tertahan' : profile.enabled ? 'Pengulangan aktif' : profile.version ? 'Pengulangan dijeda' : 'Belum diatur');
    $('[data-mode]',card).dataset.active = String(profile.enabled && !p.summary.unresolved_count);
    visualLabel($('[data-session-status]',card),p.shop.session_status==='connected'?'check':'alert',p.shop.session_status === 'connected' ? 'Koneksi toko tersimpan' : 'Periksa koneksi toko');
    $('[data-saved-count]',card).textContent = profile.product_ids.length + ' / 5 produk';
    $('[data-next-check]',card).textContent = profile.enabled ? date(profile.next_check_at) : 'Pengulangan nonaktif';
    $('[data-remaining]',card).textContent = p.summary.unresolved_count ? 'Menunggu kepastian hasil' : p.summary.remaining_count + ' / 5';
    slots($('[data-saved-slots]',card),profile.product_ids.length);slots($('[data-capacity-slots]',card),p.summary.unresolved_count?0:p.summary.remaining_count);
    visualLabel($('[data-toggle]',card),profile.enabled?'pause':'play',profile.enabled ? 'Jeda pengulangan' : 'Aktifkan pengulangan');
    $('[data-toggle]',card).classList.toggle('btn-primary', !profile.enabled);
    $('[data-toggle]',card).classList.toggle('boost-secondary', profile.enabled);
    visualLabel($('[data-edit]',card),'edit',profile.product_ids.length ? 'Ubah pilihan' : 'Pilih produk');
    let message = profile.last_message || (profile.enabled ? 'Produk pilihan diproses saat jeda dan slot tersedia.' : profile.product_ids.length ? 'Pilihan tersimpan. Aktifkan pengulangan saat siap.' : 'Pilih produk yang ingin dinaikkan berulang untuk toko ini.');
    if (!p.sender_enabled) message = 'Pilihan dapat disimpan. Pengiriman belum diaktifkan oleh pengelola server.';
    else if (profile.enabled && !Number(p.worker?.alive)) message = 'Pengulangan aktif, tetapi worker belum terpantau. Hubungi pengelola untuk memeriksa layanan.';
    if (p.summary.unresolved_count) message = 'Ada hasil yang belum pasti. Pengiriman toko ini ditahan sampai hasilnya diperiksa.';
    const note=$('[data-operation-note]',card);note.dataset.tone=p.summary.unresolved_count || (profile.enabled && !Number(p.worker?.alive))?'warning':'neutral';
    visualLabel(note,note.dataset.tone==='warning'?'alert':profile.enabled?'clock':'info',message);
    $('[data-freshness]',card).textContent = 'Pemeriksaan worker: ' + date(profile.last_checked_at) + '. Slot lokal berupa perkiraan; status Shopee diperiksa sebelum setiap pengiriman.';
    const reconnect = $('[data-reconnect]',card); reconnect.href = root.dataset.shopsUrl; reconnect.hidden = p.shop.session_status === 'connected';
    const savedKey = JSON.stringify(p.products);
    if (local.savedKey !== savedKey) {
      local.savedKey = savedKey;
      $('[data-saved-products]',card).innerHTML = p.products.length ? p.products.map(product => '<li class="boost-saved-row">' + image(product) + '<div><strong>' + esc(product.name) + '</strong><span class="boost-product-state" data-tone="'+(product.reason?'warning':'success')+'">'+icon(product.reason==='Menunggu jeda produk'?'clock':product.reason?'alert':'check') + esc(product.reason || 'Siap diperiksa') + '</span>'+(product.reason === 'Menunggu jeda produk' && product.next_boost_at ? '<small class="boost-product-time">'+date(product.next_boost_at)+'</small>' : '')+'</div></li>').join('') : '<li class="boost-empty">'+icon('box')+'<span>Belum ada produk pilihan. Mulai dengan memilih produk dari katalog toko.</span></li>';
      bindImages(card);
    }
    const historyKey = JSON.stringify([p.history,p.unresolved]);
    if (local.historyKey !== historyKey) {
      local.historyKey = historyKey;
      $('[data-history]',card).innerHTML = p.history.length ? p.history.map(run => '<details class="boost-run"><summary>' + date(run.started_at) + ' · ' + (run.mode === 'auto' ? 'Otomatis' : 'Manual') + ' · ' + esc(labels[run.status] || run.status) + '</summary><p>' + number(run.success_count) + ' diterima · ' + number(run.failed_count) + ' gagal/tidak dikirim · ' + number(run.unknown_count) + ' belum pasti</p><ul>' + run.items.map(item => '<li><strong>' + esc(item.product_name) + '</strong><span>' + esc(labels[item.status] || item.status) + (item.error_message ? ' · ' + esc(item.error_message) : '') + '</span></li>').join('') + '</ul></details>').join('') : '<p class="boost-empty">Belum ada pengiriman dari Shopdash.</p>';
      const unknown = $('[data-unresolved]',card); unknown.replaceChildren();
      p.unresolved.forEach(item => {
        const row = document.createElement('div'); row.className = 'boost-unresolved';
        const text = document.createElement('p'); text.textContent = item.product_name + ' · ' + date(item.attempted_at);
        const button = document.createElement('button'); button.type = 'button'; button.className = 'btn boost-secondary'; button.textContent = 'Tentukan hasil';
        button.addEventListener('click', () => openResolution(local,item)); row.append(text,button); unknown.append(row);
      });
    }
    buttonState(local);updateSummary();
  }
  async function load(local, quiet = false) {
    if (local.busy && quiet) return;
    const seq = ++local.seq; if (!quiet) local.loading = true;
    buttonState(local); if (!quiet) notice($('[data-load-state]',local.card),'Memuat status toko…');
    try {
      const p = await api('status?shop_id=' + local.id);
      if (seq !== local.seq) return;
      local.payload = p; local.readError=false;render(local); notice($('[data-load-state]',local.card),'');
    } catch(error) { if(seq === local.seq) {local.readError=true;updateSummary();notice($('[data-load-state]',local.card),error.message + ' Gunakan Muat ulang status.');} }
    finally { if(seq === local.seq) { local.loading = false; buttonState(local); } }
  }
  async function action(local, path, data) {
    local.busy = true; ++local.seq; buttonState(local);
    try { return await api(path,{shop_id:local.id,...data}); }
    finally { local.busy = false; await load(local); }
  }
  function openEditor(local, mode = 'auto') {
    draft = {local, mode, version:local.payload.profile.version, original:[...local.payload.profile.product_ids], selected:new Map(local.payload.products.map(p => [String(p.id),p])), page:1, pages:1, search:'', seq:0, busy:false, conflict:false};
    $('#boost-editor-title').textContent = mode === 'manual' ? 'Pilih produk untuk sekali pengiriman' : 'Pilih produk untuk diulang';
    $('[data-editor-intro]',editor).textContent = mode === 'manual' ? 'Pilih hingga 5 produk dari katalog untuk dinaikkan sekarang. Kelayakan diperiksa lagi sebelum pengiriman.' : 'Pilih hingga 5 produk. Produk yang belum tersedia akan menunggu tanpa diganti produk lain.';
    $('[data-editor-note]',editor).textContent = mode === 'manual' ? 'Aksi sekali jalan tidak mengubah pilihan atau jadwal pengulangan.' : 'Menyimpan pilihan tidak mengaktifkan pengulangan baru.';
    visualLabel($('[data-save]',editor),mode==='manual'?'up':'check',mode === 'manual' ? 'Tinjau pengiriman' : 'Simpan pilihan');
    $('[data-editor-shop]',editor).textContent = local.name;
    window.renderShopLogo($('[data-editor-logo]',editor),local.id);
    $('#boost-search').value = ''; notice($('[data-editor-error]',editor),''); notice($('[data-recommendation-status]',editor),''); $('[data-reload-version]',editor).hidden = true;
    renderDraft(); editor.showModal(); $('[data-recommend]',editor).focus({preventScroll:true}); $('.boost-editor-content',editor).scrollTop=0; loadCatalog();
  }
  function renderDraft() {
    if (!draft) return;
    $('[data-draft-count]',editor).textContent = draft.selected.size + ' / 5 dipilih';
    $('[data-dirty]',editor).textContent = draft.mode === 'manual' ? 'Untuk sekali pengiriman' : dirty() ? 'Perubahan belum disimpan' : 'Sesuai pilihan tersimpan';
    const list = $('[data-chosen]',editor); list.replaceChildren();
    draft.selected.forEach(p => {
      const li = document.createElement('li'), name = document.createElement('span'), remove = document.createElement('button');
      name.textContent = p.name;
      if(p.sold_count != null){const sales=document.createElement('small');sales.textContent='Terjual '+number(p.sold_count);name.append(sales);}
      remove.type = 'button'; remove.className = 'boost-text-button'; remove.textContent = 'Hapus'; remove.setAttribute('aria-label','Hapus '+p.name); remove.disabled = draft.busy;
      remove.addEventListener('click',()=>{ const index=[...draft.selected.keys()].indexOf(String(p.id));draft.selected.delete(String(p.id));clearRecommendationUndo();renderDraft();const buttons=$('[data-chosen]',editor).querySelectorAll('button');(buttons[Math.min(index,buttons.length-1)] || $('#boost-search')).focus(); });li.innerHTML=image(p); li.append(name,remove); list.append(li);
    });
    $('[data-save]',editor).disabled = draft.busy || draft.conflict || (draft.mode === 'manual' ? !draft.selected.size : !dirty());
    $('[data-recommend]',editor).disabled = draft.busy;
    bindImages(list);
    visualLabel($('[data-recommend]',editor),draft.recommending?'refresh':'trend',draft.recommending ? 'Memuat rekomendasi…' : 'Pilih rekomendasi');
    $('[data-recommendation]',editor).setAttribute('aria-busy',String(!!draft.recommending));
    $('[data-undo-recommendation]',editor).hidden = !draft.beforeRecommendation;
    $('[data-undo-recommendation]',editor).disabled = draft.busy;
    $('[data-reload-version]',editor).disabled = draft.busy;
    editor.querySelectorAll('[data-product-id]').forEach(input => { input.checked = draft.selected.has(input.dataset.productId); input.disabled = draft.busy || (!input.checked && draft.selected.size >= 5); });
  }
  async function loadCatalog() {
    const current = draft, seq = ++current.seq;
    notice($('[data-catalog-status]',editor),'Memuat katalog…');
    $('[data-prev]',editor).disabled = $('[data-next]',editor).disabled = true;
    $('[data-catalog]',editor).replaceChildren();
    try {
      const p = await api('catalog?shop_id='+current.local.id+'&page='+current.page+'&search='+encodeURIComponent(current.search));
      if(draft !== current || current.seq !== seq) return;
      current.page=p.page; current.pages=p.pages;
      const container=$('[data-catalog]',editor);
      p.products.forEach(product=>{
        product.id=String(product.id);
        const row=document.createElement('label');row.className='boost-product-row';
        row.innerHTML='<input type="checkbox" data-product-id="'+esc(product.id)+'">'+image(product)+'<span class="boost-product-title">'+esc(product.name)+'</span><span class="boost-product-meta">Terjual '+number(product.sold_count)+' · Rp '+number(product.price_min)+' · Stok '+number(product.total_stock)+'<span>'+((Number(product.total_stock)>0)?'Diperiksa lagi sebelum pengiriman':'Stok kosong; pengulangan akan menunggu')+'</span></span>';
        const input=row.querySelector('input');input.addEventListener('change',()=>{if(input.checked)current.selected.set(product.id,product);else current.selected.delete(product.id);clearRecommendationUndo();renderDraft();});container.append(row);
      });
      bindImages(container); notice($('[data-catalog-status]',editor),p.total ? number(p.total)+' produk aktif di katalog' : 'Tidak ada produk yang cocok. Ubah pencarian atau periksa sinkronisasi produk.');
      $('[data-page]',editor).textContent='Halaman '+p.page+' / '+p.pages;
      $('[data-prev]',editor).disabled=p.page<=1;$('[data-next]',editor).disabled=p.page>=p.pages;renderDraft();
    }catch(error){if(draft===current && seq===current.seq)notice($('[data-catalog-status]',editor),error.message+' Gunakan Cari untuk mencoba lagi.');}
  }
  function clearRecommendationUndo() {
    draft.beforeRecommendation=null;
    notice($('[data-recommendation-status]',editor),'');
  }
  $('[data-recommend]',editor).addEventListener('click',async()=>{
    const current=draft;if(!current || current.busy)return;
    current.busy=true;current.recommending=true;renderDraft();
    notice($('[data-recommendation-status]',editor),'Mengurutkan produk toko berdasarkan penjualan…');
    try {
      const result=await api('recommendations?shop_id='+current.local.id);
      if(draft!==current)return;
      if(!result.products.length){notice($('[data-recommendation-status]',editor),'Belum ada produk aktif dan berstok dengan data penjualan. Pilihan Anda tetap.');return;}
      current.beforeRecommendation=new Map(current.selected);
      current.selected=new Map(result.products.map(p=>[String(p.id),{...p,id:String(p.id)}]));
      notice($('[data-recommendation-status]',editor),current.selected.size+' produk terlaris dipilih. '+(current.mode==='manual'?'Tinjau pengiriman untuk melanjutkan.':'Periksa pilihan, lalu klik Simpan pilihan.'));
    }catch(error){if(draft===current)notice($('[data-recommendation-status]',editor),error.message+' Pilihan Anda tetap. Klik Pilih rekomendasi untuk mencoba lagi.');}
    finally{current.busy=false;current.recommending=false;if(draft===current)renderDraft();}
  });
  $('[data-undo-recommendation]',editor).addEventListener('click',()=>{
    if(!draft?.beforeRecommendation || draft.busy)return;
    draft.selected=draft.beforeRecommendation;clearRecommendationUndo();renderDraft();
    notice($('[data-recommendation-status]',editor),'Pilihan sebelumnya dikembalikan.');$('[data-recommend]',editor).focus();
  });
  function closeEditor() { if(draft?.busy) return; if(dirty() && !window.confirm('Batalkan perubahan pilihan yang belum disimpan?')) return; editor.close(); draft=null; }
  $('[data-editor-close]',editor).addEventListener('click',closeEditor);$('[data-editor-cancel]',editor).addEventListener('click',closeEditor);
  editor.addEventListener('cancel',event=>{event.preventDefault();closeEditor();});
  $('[data-search-form]',editor).addEventListener('submit',event=>{event.preventDefault();draft.search=$('#boost-search').value.trim();draft.page=1;loadCatalog();});
  $('[data-prev]',editor).addEventListener('click',()=>{draft.page--;loadCatalog();});$('[data-next]',editor).addEventListener('click',()=>{draft.page++;loadCatalog();});
  $('[data-save]',editor).addEventListener('click',async()=>{
    const current=draft;current.busy=true;renderDraft();notice($('[data-editor-error]',editor),'');
    if(current.mode==='manual'){
      try {const p=await api('preview',{shop_id:current.local.id,product_ids:[...current.selected.keys()]});editor.close();draft=null;openConfirm(current.local,'manual',p.products);}
      catch(error){notice($('[data-editor-error]',editor),error.message);}finally{current.busy=false;if(draft===current)renderDraft();}return;
    }
    try { await action(current.local,'save',{version:current.version,product_ids:[...current.selected.keys()]});editor.close();draft=null;notice($('[data-action-result]',current.local.card),'Pilihan produk tersimpan.'); }
    catch(error){notice($('[data-editor-error]',editor),error.message);current.conflict=error.code===409;$('[data-reload-version]',editor).hidden=!current.conflict;}
    finally {current.busy=false;if(draft===current)renderDraft();}
  });
  $('[data-reload-version]',editor).addEventListener('click',async()=>{
    const current=draft;current.busy=true;renderDraft();
    try {const p=await api('status?shop_id='+current.local.id);current.version=p.profile.version;current.original=p.profile.product_ids;current.conflict=false;$('[data-reload-version]',editor).hidden=true;notice($('[data-editor-error]',editor),'Versi terbaru dimuat. Pilihan Anda dipertahankan; periksa sebelum menyimpan.');}
    catch(error){notice($('[data-editor-error]',editor),error.message);}finally{current.busy=false;renderDraft();}
  });
  function openConfirm(local,kind,products=local.payload.products) {
    const p=local.payload;
    confirmation={local,kind,version:p.profile.version,request_key:crypto.randomUUID(),busy:false};
    $('#boost-confirm-title').textContent=kind==='manual'?'Naikkan sekali sekarang':'Aktifkan pengulangan';
    $('[data-confirm-copy]',confirmDialog).textContent=local.name+(kind==='manual'?' · Konfirmasi produk untuk satu pengiriman. Pilihan dan jadwal pengulangan tidak diubah.':' · Produk berikut akan diulang saat tersedia, termasuk ketika halaman ini ditutup.');
    $('[data-confirm-products]',confirmDialog).innerHTML=products.map(product=>kind==='manual'?'<label class="boost-check"><input type="checkbox" value="'+esc(product.id)+'" '+(!product.reason?'checked':'disabled')+'><span>'+esc(product.name)+(product.reason?' · '+esc(product.reason):'')+'</span></label>':'<p>'+esc(product.name)+'</p>').join('');
    $('[data-confirm-action]',confirmDialog).textContent=kind==='manual'?'Naikkan pilihan sekali':'Aktifkan pengulangan';
    $('[data-confirm-action]',confirmDialog).disabled=false;notice($('[data-confirm-error]',confirmDialog),'');confirmDialog.showModal();
  }
  $('[data-confirm-cancel]',confirmDialog).addEventListener('click',()=>{if(!confirmation.busy)confirmDialog.close();});
  confirmDialog.addEventListener('cancel',event=>{if(confirmation?.busy)event.preventDefault();});
  $('[data-confirm-action]',confirmDialog).addEventListener('click',async()=>{
    const c=confirmation, ids=[...confirmDialog.querySelectorAll('input:checked:not(:disabled)')].map(el=>el.value);
    if(c.kind==='manual' && !ids.length){notice($('[data-confirm-error]',confirmDialog),'Pilih setidaknya satu produk yang tersedia.');return;}
    c.busy=true;$('[data-confirm-action]',confirmDialog).disabled=true;
    try {
      const p=await action(c.local,c.kind==='manual'?'run':'toggle',c.kind==='manual'?{product_ids:ids,request_key:c.request_key}:{version:c.version,enabled:true});
      confirmDialog.close();notice($('[data-action-result]',c.local.card),p.result?'Hasil: '+number(p.result.success_count)+' diterima, '+number(p.result.failed_count)+' gagal/tidak dikirim, '+number(p.result.unknown_count)+' belum pasti.':'Pengulangan aktif untuk pilihan tersimpan.');
    }catch(error){notice($('[data-confirm-error]',confirmDialog),error.message+' Periksa status sebelum mencoba lagi.');}
    finally{c.busy=false;$('[data-confirm-action]',confirmDialog).disabled=false;}
  });
  function openResolution(local,item){resolving={local,item,busy:false};$('[data-resolution-name]',resolution).textContent=item.product_name+' · '+date(item.attempted_at);$('[data-resolution-confirm]',resolution).checked=false;$('#boost-resolution-outcome').value='';$('[data-resolution-save]',resolution).disabled=true;notice($('[data-resolution-error]',resolution),'');resolution.showModal();}
  resolution.addEventListener('change',()=>{$('[data-resolution-save]',resolution).disabled=resolving.busy || !$('[data-resolution-confirm]',resolution).checked || !$('#boost-resolution-outcome').value;});
  $('[data-resolution-cancel]',resolution).addEventListener('click',()=>{if(!resolving.busy)resolution.close();});resolution.addEventListener('cancel',event=>{if(resolving?.busy)event.preventDefault();});
  $('[data-resolution-save]',resolution).addEventListener('click',async()=>{const r=resolving;r.busy=true;$('[data-resolution-save]',resolution).disabled=true;try{await action(r.local,'resolve',{run_id:Number(r.item.run_id),product_id:String(r.item.product_id),outcome:$('#boost-resolution-outcome').value,confirmed:$('[data-resolution-confirm]',resolution).checked});resolution.close();}catch(error){notice($('[data-resolution-error]',resolution),error.message);}finally{r.busy=false;$('[data-resolution-save]',resolution).disabled=false;}});
  shops.forEach(shop=>{
    const fragment=document.getElementById('boost-card-template').content.cloneNode(true),card=fragment.querySelector('[data-card]');
    const local={id:shop.id,name:shop.name,card,payload:null,seq:0,busy:false,loading:false};states.set(shop.id,local);card.dataset.shopId=shop.id;
    $('[data-shop-name]',card).textContent=shop.name;window.renderShopLogo($('[data-shop-logo]',card),shop.id);
    $('[data-edit]',card).addEventListener('click',()=>openEditor(local));$('[data-refresh]',card).addEventListener('click',()=>load(local));
    $('[data-toggle]',card).addEventListener('click',async()=>{if(!local.payload.profile.enabled)return openConfirm(local,'auto');try{await action(local,'toggle',{version:local.payload.profile.version,enabled:false});notice($('[data-action-result]',card),'Pengulangan dijeda. Pengiriman yang sudah berlangsung tetap dicatat.');}catch(error){notice($('[data-action-result]',card),error.message);}});
    $('[data-manual]',card).addEventListener('click',()=>openEditor(local,'manual'));
    $('[data-inspect]',card).addEventListener('click',async()=>{try{const p=await action(local,'inspect',{});const rows=Object.entries(p.inspection.products).map(([id,info])=>'Produk #'+id+': '+(info.eligible?'tersedia saat diperiksa':info.reason));notice($('[data-inspection]',card),rows.join(' · ')+'. '+p.inspection.message);}catch(error){notice($('[data-inspection]',card),error.message);}});
    $('#boost-grid').append(fragment);load(local);
  });
  notice($('#boost-state'),shops.length?'':'Belum ada toko. Tambahkan toko melalui halaman Toko.');
  if(!shops.length)updateSummary();
  window.addEventListener('beforeunload',event=>{if(dirty()){event.preventDefault();event.returnValue='';}});
  setInterval(()=>{if(!document.hidden)states.forEach(local=>load(local,true));},30000);
})();
