(() => {
  const root = document.getElementById('finance-page');
  if (!root || !document.getElementById('finance-shops')) return;
  const $ = id => document.getElementById('finance-' + id);
  const dashboard = root.dataset.dashboard === 'true';
  const icon = name => `<span class="material-symbols-outlined" aria-hidden="true">${name}</span>`;
  const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const money = value => value === null || value === undefined ? 'Belum tersedia' : new Intl.NumberFormat('id-ID', {style:'currency',currency:'IDR',maximumFractionDigits:2}).format(value);
  const stamp = value => value ? new Date(value.replace(' ', 'T') + 'Z').toLocaleString('id-ID',{timeZone:'Asia/Jakarta',dateStyle:'medium',timeStyle:'short'}) + ' WIB' : 'belum pernah diperbarui';
  const day = value => {
    const date=new Date(value+'T00:00:00+07:00');
    return value && Number.isFinite(date.getTime()) ? date.toLocaleDateString('id-ID',{timeZone:'Asia/Jakarta',dateStyle:'medium'}) : 'Tanggal belum valid';
  };
  const params = new URLSearchParams(location.search);
  const requested = (params.get('shops') || '').split(',');
  if (requested.some(Boolean)) Array.from($('shops').options).forEach(o => { o.selected = requested.includes(o.value); });
  const syncPicker = window.enhanceShopSelect($('shops'));
  const toggleAll = () => { $('all-shops').hidden=$('shops').selectedOptions.length===0 || $('shops').selectedOptions.length===$('shops').options.length; };
  toggleAll();
  for (const field of ['start','end']) if (/^\d{4}-\d{2}-\d{2}$/.test(params.get(field) || '')) $(field).value = params.get(field);
  const today = () => new Intl.DateTimeFormat('en-CA',{timeZone:'Asia/Jakarta',year:'numeric',month:'2-digit',day:'2-digit'}).format(new Date());
  const presetDates = period => { const end=today(); return {start:period==='today' ? end : end.slice(0,8)+'01',end}; };
  $('period').value = ['month','today','custom'].includes(params.get('period')) ? params.get('period') : (params.has('start') || params.has('end') ? 'custom' : 'month');
  function preparePeriod() {
    const custom=$('period').value==='custom'; $('custom-dates').hidden=!custom;
    if(!custom) { const dates=presetDates($('period').value); $('start').value=dates.start; $('end').value=dates.end; }
    for(const field of ['start','end']) $(field).max=today();
  }
  preparePeriod();
  let applied, tab = ['summary','details','cost'].includes(params.get('tab')) ? params.get('tab') : 'summary';
  let summarySequence = 0, listSequence = 0, timer, detailPage = 1, costPage = 1, editing, proof, dialogSequence = 0;
  let detailSearch = '', costSearch = '', lastStoresMarkup = '';
  const query = extra => new URLSearchParams({...applied,...extra}).toString();
  const readFilters = () => ({shops:$('shops').selectedOptions.length===$('shops').options.length ? '' : Array.from($('shops').selectedOptions, o => o.value).join(','),period:$('period').value,start:$('start').value,end:$('end').value});
  function url() {
    const p = new URLSearchParams({...applied,...(dashboard ? {} : {tab})}); history.replaceState(null,'',location.pathname + '?' + p + location.hash);
    if($('open')) $('open').href=root.dataset.base+'/panel/finance?'+p;
  }
  function publishFilters() {
    const selected=applied.shops ? applied.shops.split(',') : Array.from($('shops').options,o=>o.value);
    const names=Array.from($('shops').options).filter(o=>selected.includes(o.value)).map(o=>o.text);
    const label=!applied.shops ? `Semua toko · gabungan ${names.length} toko` : names.length===1 ? names[0] : `${names.length} toko dipilih`;
    $('scope-label').textContent=label+' · '+day(applied.start)+' sampai '+day(applied.end)+' WIB';
    $('released-period').textContent=day(applied.start)+' sampai '+day(applied.end)+' WIB';
    root.dispatchEvent(new CustomEvent('finance:filters',{detail:{...applied,label}}));
  }
  async function api(action, data) {
    const response = await fetch(root.dataset.endpoint + '/' + action, data ? {method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':root.dataset.csrf},body:JSON.stringify(data)} : {cache:'no-store'});
    let result;
    try { result = await response.json(); } catch (_) { throw new Error('Respons belum dapat dibaca. Muat ulang jika sesi sudah berakhir.'); }
    if (!response.ok || result.status !== 'success') throw new Error(result.message || 'Data belum dapat dimuat. Coba lagi.');
    return result;
  }
  function error(message) { $('error').hidden = !message; $('error').querySelector('p').textContent = message || ''; }
  const coverage = (days, total) => `${days}/${total} hari tersedia`;
  const quality = (available,complete,count) => `<span class="finance-quality ${complete<count ? 'is-partial' : ''}">${icon(complete<count ? 'info' : 'check_circle')}${available===0 ? 'Data belum tersedia' : complete<count ? 'Total sementara · data belum lengkap' : 'Data semua toko tersedia'}</span>`;
  const states = [['shipping','Dalam pengiriman','local_shipping'],['delivered','Tiba, menunggu dilepas','package_2'],['return','Retur proses','assignment_return'],['unknown','Status belum dipastikan','help']];
  const stateList = (values,partial=false) => `<dl class="finance-state-list">${states.map(([key,label,symbol])=>`<div><dt>${icon(symbol)}${label}</dt><dd>${esc(money(values?.[key]))}</dd>${key!=='unknown' && partial ? '<small>Yang sudah teridentifikasi</small>' : ''}</div>`).join('')}</dl>`;
  const unknownState = store => (store.pending_state_counts?.unknown ?? (store.pending_states?.unknown ? 1 : 0))>0;
  function renderSummary(data) {
    const stores = data.stores, count = stores.length, days = data.range.days;
    for (const key of ['pending','released']) {
      const available = stores.filter(s => s[key] !== null);
      $(key).textContent = available.length ? money(available.reduce((sum,s) => sum + s[key],0)) : 'Belum tersedia';
      const times = available.map(s => s[key + '_updated']).filter(Boolean).sort();
      const complete=key==='pending' ? available.length : stores.filter(s=>s.released_days===days).length;
      $(key+'-quality').innerHTML=quality(available.length,complete,count);
      const source=key==='pending' ? 'Posisi terbaru, tanpa filter tanggal.' : available.length && available.every(s=>s.released_basis!=='detail') ? 'Ringkasan Shopee.' : 'Jumlah rincian berdasarkan tanggal pelepasan.';
      $(key + '-note').textContent = `${available.length}/${count} toko${key==='released' ? ` · periode lengkap ${complete}/${count} toko` : ''}. ${source} Pembaruan paling lama: ${stamp(times[0])}.`;
    }
    function metric(title, symbol, amount, available, complete, note) { return `<div><h2>${icon(symbol)}${esc(title)}</h2><strong>${esc(money(amount))}</strong>${quality(available,complete,count)}<p>${esc(note)}</p><p>${esc(day(data.range.start)+' sampai '+day(data.range.end))} WIB</p></div>`; }
    const gmv = stores.filter(s => s.gmv !== null), ads = stores.filter(s => s.ads.amount !== null);
    const gmvComplete=gmv.filter(s=>s.gmv_days===days).length, adsComplete=ads.filter(s=>s.ads.days===days).length;
    $('secondary').innerHTML = metric('Omset dibayar','receipt_long',gmv.length ? gmv.reduce((n,s)=>n+s.gmv,0) : null,gmv.length,gmvComplete,`Pesanan sudah dibayar. Periode lengkap ${gmvComplete}/${count} toko.`) + metric('Biaya iklan','campaign',ads.length ? ads.reduce((n,s)=>n+s.ads.amount,0) : null,ads.length,adsComplete,`Biaya pemakaian, bukan top up. Periode lengkap ${adsComplete}/${count} toko.`);
    const pending=stores.filter(s=>s.pending_states!==null);
    const totals=pending.length ? Object.fromEntries(states.map(([key])=>[key,pending.reduce((n,s)=>n+s.pending_states[key],0)])) : null;
    const hasUnknown=pending.some(unknownState), incomplete=pending.length<count;
    const detailTotal=pending.reduce((n,s)=>n+s.pending_detail,0), pendingTotal=pending.reduce((n,s)=>n+s.pending,0);
    $('pending-states').innerHTML=stateList(totals,hasUnknown || incomplete)+`<p class="finance-help">Rincian tersedia untuk ${pending.length}/${count} toko. ${hasUnknown || incomplete ? 'Status yang belum diketahui bisa mencakup pesanan dalam pengiriman. Nilai teridentifikasi belum menggambarkan semuanya.' : 'Retur proses belum berarti refund sudah dipotong.'}</p>`+(pending.length && Math.abs(pendingTotal-detailTotal)>.005 ? `<p class="finance-notice">Jumlah rincian: ${esc(money(detailTotal))}. Ringkasan Shopee: ${esc(money(pendingTotal))}. Rincian lebih ${detailTotal>pendingTotal ? 'besar' : 'kecil'} ${esc(money(Math.abs(pendingTotal-detailTotal)))}. Angka utama mengikuti ringkasan Shopee.</p>` : '');
    const markup = stores.map(s => `<article class="finance-store" data-store-id="${s.id}">
      <header><span class="shop-picker-logo" data-logo="${s.id}"></span><h3>${esc(s.name)}</h3></header>
      <dl>
        <div><dt>Pending</dt><dd>${esc(money(s.pending))}</dd><small>${esc(stamp(s.pending_updated))}</small></div>
        <div><dt>Sudah dilepas Shopee</dt><dd>${esc(money(s.released))}</dd><small class="${s.released_days<days ? 'finance-incomplete' : ''}">${s.released_days<days ? 'Belum lengkap · ' : ''}${esc(coverage(s.released_days,days))}</small><small>${s.released_basis==='detail' ? 'Rincian periode' : 'Ringkasan Shopee'}</small></div>
        <div><dt>Omset dibayar</dt><dd>${esc(money(s.gmv))}</dd><small class="${s.gmv_days<days ? 'finance-incomplete' : ''}">${s.gmv_days<days ? 'Belum lengkap · ' : ''}${esc(coverage(s.gmv_days,days))}</small><small>Pembaruan terakhir: ${esc(stamp(s.gmv_updated))}</small></div>
        <div><dt>Biaya iklan</dt><dd>${esc(money(s.ads.amount))}</dd><small class="${s.ads.days<days ? 'finance-incomplete' : ''}">${s.ads.days<days ? 'Belum lengkap · ' : ''}${esc(coverage(s.ads.days,days))}</small><small>Pembaruan terakhir: ${esc(stamp(s.ads.updated_at))}</small></div>
      </dl>
      ${s.released_difference ? `<p class="finance-notice">Ringkasan Shopee berbeda ${esc(money(s.released_difference))} dari jumlah rincian. Angka utama mengikuti ringkasan Shopee. Buka pembanding di bawah untuk melihat keduanya.</p>` : ''}
      <details><summary>Pengiriman, retur, dan pembanding saldo</summary>${stateList(s.pending_states,unknownState(s))}<dl class="finance-breakdown">
        <div><dt>Jumlah rincian pending</dt><dd>${esc(money(s.pending_detail))}</dd></div>
        <div><dt>Jumlah rincian dilepas</dt><dd>${esc(money(s.released_detail))}</dd><small>${esc(coverage(s.released_detail_days,days))}</small></div>
        <div><dt>Penyesuaian dana dilepas</dt><dd>${esc(money(s.adjustment))}</dd></div>
      </dl><p class="finance-help">Bagian pending tidak ditambahkan lagi ke total. ${s.pending !== null && s.pending !== s.pending_detail ? 'Ringkasan Shopee dan rincian pending saat ini berbeda.' : ''}</p></details>
      ${s.error ? `<p class="finance-notice">${esc(s.error)}</p>` : ''}
      ${s.active_imports ? `<p class="finance-help">${icon('sync')}Saldo sedang diperbarui. Angka sebelumnya tetap tampil.</p>` : ''}
    </article>`).join('') || '<p class="finance-empty">Belum ada toko yang tersedia untuk ditampilkan.</p>';
    if(markup!==lastStoresMarkup) {
      const open=Array.from($('store-list').querySelectorAll('details[open]'),el=>el.closest('[data-store-id]').dataset.storeId);
      const focused=document.activeElement?.tagName==='SUMMARY' ? document.activeElement.closest('[data-store-id]')?.dataset.storeId : null;
      $('store-list').innerHTML=markup; lastStoresMarkup=markup;
      $('store-list').querySelectorAll('[data-store-id]').forEach(el=>{ if(open.includes(el.dataset.storeId))el.querySelector('details').open=true; if(focused===el.dataset.storeId)el.querySelector('summary').focus({preventScroll:true}); });
      $('store-list').querySelectorAll('[data-logo]').forEach(el=>window.renderShopLogo(el,el.dataset.logo));
    }
    const active = stores.reduce((n,s)=>n+s.active_imports,0);
    $('status').textContent = active ? `Saldo ${stores.filter(s=>s.active_imports).length}/${count} toko sedang diperbarui. Angka sebelumnya tetap tampil.` : 'Tampilan diperiksa otomatis. Waktu data tercantum pada saldo.';
    return active;
  }
  async function loadSummary(reset = false) {
    const sequence = ++summarySequence; clearTimeout(timer); error('');
    if (reset) { for (const id of ['pending','released']) { $(id).textContent='Memuat…'; $(id+'-note').textContent='Memuat pilihan ini…'; $(id+'-quality').replaceChildren(); } $('secondary').textContent='Memuat omset dan biaya iklan…'; $('pending-states').textContent='Memuat rincian Pending…'; $('store-list').replaceChildren(); lastStoresMarkup=''; }
    $('summary-panel').setAttribute('aria-busy','true');
    try {
      const data = await api('summary?' + query()); if (sequence !== summarySequence) return;
      const active = renderSummary(data);
      timer=setTimeout(refreshView,active ? 5000 : 30000);
    } catch (e) { if(sequence!==summarySequence)return; error(e.message); $('status').textContent='Pembaruan gagal. Angka yang masih tampil adalah data sebelumnya.'; if(reset) { for(const id of ['pending','released'])$(id).textContent='Belum dapat dimuat'; $('secondary').textContent='Omset dan biaya iklan belum dapat dimuat.'; $('pending-states').textContent='Rincian Pending belum dapat dimuat.'; } timer=setTimeout(refreshView,30000); }
    finally { if(sequence===summarySequence)$('summary-panel').setAttribute('aria-busy','false'); }
  }
  function pagination(kind,result) {
    const container=$(kind+'-pagination'); container.replaceChildren();
    const pages=Math.max(1,Math.ceil(result.total/25));
    const label=document.createElement('span'); label.textContent=`${result.total} baris · halaman ${result.page}/${pages}`;
    for(const [text,delta] of [['Sebelumnya',-1],['Berikutnya',1]]) {
      const b=document.createElement('button'); b.type='button'; b.className='btn'; b.textContent=text;
      b.disabled=delta<0 ? result.page<=1 : result.page>=pages;
      b.addEventListener('click',()=>{ if(kind==='detail') detailPage=result.page+delta; else costPage=result.page+delta; loadList(); });
      container.append(b); if(delta<0)container.append(label);
    }
  }
  async function loadList() {
    const sequence=++listSequence; if(dashboard || tab==='summary')return;
    const kind=tab==='details' ? 'detail' : 'cost', target=$(kind+'-list');
    target.textContent=kind==='detail' ? 'Memuat rincian penghasilan…' : 'Memuat produk dan HPP…'; $(kind+'-pagination').replaceChildren();
    $('detail-scope').textContent=$('category').value==='1' ? 'Seluruh pending pada pembaruan terbaru, tanpa filter tanggal.' : day(applied.start)+' sampai '+day(applied.end)+', berdasarkan tanggal dana dilepas.';
    try {
      const result=await api((kind==='detail' ? 'details?' : 'catalog?')+query(kind==='detail' ? {category:$('category').value,page:detailPage,search:detailSearch} : {page:costPage,search:costSearch}));
      if(sequence!==listSequence)return;
      if(!result.rows.length) target.innerHTML=`<p class="finance-empty">${kind==='detail' ? 'Belum ada rincian untuk pilihan ini. Jika saldo belum tersedia, klik Perbarui saldo Shopee. Jika memakai pencarian, coba nomor pesanan lain.' : 'Tidak ada produk yang cocok. Coba SKU lain atau sinkronkan produk di halaman Produk.'}</p>`;
      else if(kind==='detail') {
        const states={shipping:'Dalam pengiriman',return:'Retur proses',delivered:'Tiba, menunggu pelepasan',unknown:'Status belum dipastikan',released:'Sudah dilepas'};
        target.innerHTML='<div class="finance-table-wrap"><table class="finance-table"><thead><tr><th>Pesanan / toko</th><th>Status / tanggal</th><th>Penghasilan / HPP</th><th>Penyesuaian</th></tr></thead><tbody>'+result.rows.map(r=>`<tr><td data-label="Pesanan / toko"><strong>${esc(r.order_sn)}</strong><span>${esc(r.shop_name)}</span><small>${esc(r.product_name)}</small></td><td data-label="Status / tanggal">${esc(states[r.state])}<span>${esc(r.released_at ? stamp(r.released_at) : r.estimated_at ? 'Perkiraan: '+stamp(r.estimated_at) : 'Tanggal pelepasan belum tersedia')}</span></td><td data-label="Penghasilan / HPP">${esc(money(r.income_amount))}<small>HPP: ${esc(r.cost?.amount === null ? (r.cost.items ? 'belum lengkap' : 'rincian produk belum tersedia') : money(r.cost?.amount))}</small></td><td data-label="Penyesuaian">${esc(money(r.adjustment_amount))}</td></tr>`).join('')+'</tbody></table></div>';
      } else {
        target.innerHTML='<div class="finance-table-wrap"><table class="finance-table"><thead><tr><th>Produk / varian</th><th>Toko / SKU</th><th>HPP per unit</th><th>Aksi</th></tr></thead><tbody>'+result.rows.map((r,i)=>`<tr><td data-label="Produk / varian"><strong>${esc(r.product_name)}</strong><span>${esc(r.variation_name || 'Tanpa varian')}</span>${Number(r.archived) ? '<small>Produk arsip</small>' : ''}</td><td data-label="Toko / SKU">${esc(r.shop_name)}<span>${esc(r.sku || 'SKU belum diisi di Shopee')}</span><small>ID varian: ${esc(r.model_id)}</small></td><td data-label="HPP per unit">${r.unit_cost===null ? 'Belum diisi' : esc(money(r.unit_cost))}<span>${r.valid_from ? 'Sejak '+esc(day(r.valid_from)) : ''}</span></td><td data-label="Aksi"><button class="btn" type="button" data-edit-cost="${i}">Atur HPP</button></td></tr>`).join('')+'</tbody></table></div>';
        target.querySelectorAll('[data-edit-cost]').forEach(b=>b.addEventListener('click',()=>openCost(result.rows[Number(b.dataset.editCost)])));
      }
      pagination(kind,result);
    } catch(e) { if(sequence!==listSequence)return; target.textContent=e.message; const b=document.createElement('button'); b.className='btn'; b.textContent='Coba lagi'; b.onclick=loadList; target.append(b); }
  }
  function setTab(value) {
    tab=value; root.querySelectorAll('[data-finance-tab]').forEach(b=>b.setAttribute('aria-pressed',String(b.dataset.financeTab===tab)));
    for(const name of ['summary','details','cost']) $(''+name+'-panel').hidden=name!==tab;
    url(); loadList();
  }
  function invalidatePreview() { proof=null; $('save-cost').disabled=true; $('cost-preview').hidden=true; }
  async function openCost(item) {
    editing=item; invalidatePreview(); const sequence=++dialogSequence;
    $('cost-error').hidden=true; $('cost-value').value=item.unit_cost ?? ''; $('cost-date').value=root.dataset.today;
    $('cost-item').textContent=`${item.shop_name} · ${item.product_name} · ${item.variation_name || 'Tanpa varian'} · SKU: ${item.sku || 'belum diisi'}`;
    $('cost-history').textContent='Memuat riwayat…'; $('cost-dialog').showModal(); $('cost-value').focus();
    try {
      const result=await api('history?'+new URLSearchParams({shop_id:item.shop_id,product_id:item.product_id,model_id:item.model_id}));
      if(sequence!==dialogSequence)return;
      $('cost-history').innerHTML=result.rows.length ? '<ul>'+result.rows.map(r=>`<li>Mulai ${esc(day(r.valid_from))}: ${esc(money(Number(r.unit_cost)))} per unit</li>`).join('')+'</ul>' : '<p>HPP produk ini belum pernah diisi.</p>';
    } catch(e) { if(sequence===dialogSequence)$('cost-history').textContent=e.message; }
  }
  const costInput=()=>({shop_id:editing.shop_id,product_id:editing.product_id,model_id:editing.model_id,version:editing.version,unit_cost:$('cost-value').value,valid_from:$('cost-date').value});
  if(!dashboard) {
  $('cost-form').addEventListener('input',invalidatePreview);
  $('cost-form').addEventListener('submit',async event=>{
    event.preventDefault(); invalidatePreview(); $('cost-error').hidden=true; $('preview-cost').disabled=true;
    const input=costInput(), sequence=dialogSequence;
    try {
      const result=await api('preview',input); if(sequence!==dialogSequence || JSON.stringify(input)!==JSON.stringify(costInput()))return;
      proof={...input,token:result.token,expires:result.expires}; const p=result.preview, impact=p.impact;
      $('cost-preview').textContent=`Berlaku ${day(p.valid_from)}${p.until ? ' sampai '+day(p.until) : ' dan seterusnya'}. ${impact.orders} pesanan tersimpan, ${impact.units} unit terdampak. Modal lama yang sudah diketahui: ${money(impact.known_cost)}. ${impact.missing_units} unit belum punya HPP lama. Modal setelah perubahan: ${money(impact.new_cost)}. Pratinjau mencakup data pesanan yang sudah tersimpan, termasuk pesanan batal/retur; angka ini bukan laba.`;
      $('cost-preview').hidden=false; $('save-cost').disabled=false;
    } catch(e) { $('cost-error').textContent=e.message; $('cost-error').hidden=false; }
    finally { $('preview-cost').disabled=false; }
  });
  $('save-cost').addEventListener('click',async()=>{
    if(!proof)return; $('save-cost').disabled=true; $('preview-cost').disabled=true;
    try { await api('save',proof); $('cost-dialog').close(); $('status').textContent='HPP tersimpan. Riwayat modal diperbarui.'; loadList(); }
    catch(e) { $('cost-error').textContent=e.message; $('cost-error').hidden=false; invalidatePreview(); }
    finally { $('preview-cost').disabled=false; }
  });
  $('close-cost').onclick=()=>$('cost-dialog').close();
  $('cost-dialog').addEventListener('close',()=>{ dialogSequence++; invalidatePreview(); });
  }
  const draft=()=>{ toggleAll(); $('filter-draft').hidden=JSON.stringify(readFilters())===JSON.stringify(applied); };
  $('all-shops').onclick=()=>{ Array.from($('shops').options).forEach(o=>{o.selected=true;}); syncPicker(); draft(); };
  $('period').addEventListener('change',()=>{preparePeriod();draft();});
  $('shops').addEventListener('change',draft);
  for(const field of ['start','end']) $(field).addEventListener('input',draft);
  $('filters').addEventListener('submit',event=>{
    event.preventDefault(); if($('start').value>$('end').value){error('Tanggal mulai harus sebelum atau sama dengan tanggal akhir.');return;}
    applied=readFilters(); $('filter-draft').hidden=true; detailPage=costPage=1; url(); publishFilters(); loadSummary(true); loadList();
  });
  if(!dashboard) {
  root.querySelectorAll('[data-finance-tab]').forEach(b=>b.addEventListener('click',()=>setTab(b.dataset.financeTab)));
  $('category').onchange=()=>{detailPage=1;loadList();};
  for(const kind of ['order','cost']) $(kind+'-search-form').addEventListener('submit',event=>{event.preventDefault(); if(kind==='order'){detailSearch=$('order-search').value.trim();detailPage=1;}else{costSearch=$('cost-search').value.trim();costPage=1;}loadList();});
  }
  $('sync').onclick=async()=>{
    $('sync').disabled=true; error('');
    try { const result=await api('sync',applied); $('status').textContent=result.message; await loadSummary(); }
    catch(e){error(e.message);} finally{$('sync').disabled=false;}
  };
  $('retry').onclick=()=>loadSummary(true);
  function refreshView() {
    if(document.hidden) { timer=setTimeout(refreshView,30000); return; }
    root.dataset.today=today(); for(const field of ['start','end'])$(field).max=today();
    let changed=false;
    if(applied.period!=='custom') {
      const dates=presetDates(applied.period);
      if(applied.start!==dates.start || applied.end!==dates.end) {
        applied={...applied,...dates}; changed=true;
        if($('filter-draft').hidden) { $('start').value=dates.start; $('end').value=dates.end; }
        url(); publishFilters();
      }
    }
    loadSummary(changed); if(changed || tab==='details')loadList();
  }
  document.addEventListener('visibilitychange',()=>{if(!document.hidden){clearTimeout(timer);refreshView();}});
  applied=readFilters();
  if(requested.some(id=>id && !Array.from($('shops').options).some(o=>o.value===id)))applied.shops=params.get('shops');
  window.shopdashFinance={get filters(){return {...applied};},esc,money,stamp,day,icon};
  if(dashboard){tab='summary';url();}else setTab(tab);
  publishFilters(); loadSummary(true);
})();
