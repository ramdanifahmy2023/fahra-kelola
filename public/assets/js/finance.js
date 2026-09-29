(() => {
  const root = document.getElementById('finance-page');
  if (!root || !document.getElementById('finance-shops')) return;
  const $ = id => document.getElementById('finance-' + id);
  const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const money = value => value === null || value === undefined ? 'Belum tersedia' : new Intl.NumberFormat('id-ID', {style:'currency',currency:'IDR',maximumFractionDigits:2}).format(value);
  const stamp = value => value ? new Date(value.replace(' ', 'T') + 'Z').toLocaleString('id-ID',{timeZone:'Asia/Jakarta',dateStyle:'medium',timeStyle:'short'}) + ' WIB' : 'belum pernah diperbarui';
  const day = value => value ? new Date(value + 'T00:00:00+07:00').toLocaleDateString('id-ID',{timeZone:'Asia/Jakarta',dateStyle:'medium'}) : '';
  const params = new URLSearchParams(location.search);
  const requested = (params.get('shops') || '').split(',');
  if (requested.some(Boolean)) Array.from($('shops').options).forEach(o => { o.selected = requested.includes(o.value); });
  const syncPicker = window.enhanceShopSelect($('shops'));
  for (const field of ['start','end']) if (/^\d{4}-\d{2}-\d{2}$/.test(params.get(field) || '')) $(field).value = params.get(field);
  let applied, tab = ['summary','details','cost'].includes(params.get('tab')) ? params.get('tab') : 'summary';
  let summarySequence = 0, listSequence = 0, timer, detailPage = 1, costPage = 1, editing, proof, dialogSequence = 0;
  let detailSearch = '', costSearch = '';
  const query = extra => new URLSearchParams({...applied,...extra}).toString();
  const readFilters = () => ({shops:Array.from($('shops').selectedOptions, o => o.value).join(','),start:$('start').value,end:$('end').value});
  function url() { const p = new URLSearchParams({...applied,tab}); history.replaceState(null,'',location.pathname + '?' + p); }
  async function api(action, data) {
    const response = await fetch(root.dataset.endpoint + '/' + action, data ? {method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':root.dataset.csrf},body:JSON.stringify(data)} : {cache:'no-store'});
    let result;
    try { result = await response.json(); } catch (_) { throw new Error('Respons belum dapat dibaca. Muat ulang jika sesi sudah berakhir.'); }
    if (!response.ok || result.status !== 'success') throw new Error(result.message || 'Data belum dapat dimuat. Coba lagi.');
    return result;
  }
  function error(message) { $('error').hidden = !message; $('error').querySelector('p').textContent = message || ''; }
  const coverage = (days, total) => `${days}/${total} hari tersedia`;
  function renderSummary(data) {
    const stores = data.stores, count = stores.length, days = data.range.days;
    for (const key of ['pending','released']) {
      const available = stores.filter(s => s[key] !== null);
      $(key).textContent = available.length ? money(available.reduce((sum,s) => sum + s[key],0)) : 'Belum tersedia';
      const times = available.map(s => s[key + '_updated']).filter(Boolean).sort();
      const partial = key === 'released' ? ` · periode lengkap ${stores.filter(s=>s.released_days===days).length}/${count} toko` : '';
      const source = key==='released' ? (available.every(s=>s.released_basis!=='detail') ? ' Sumber: ringkasan Shopee.' : ' Sumber: rincian periode; lihat keterangan setiap toko.') : '';
      $(key + '-note').textContent = `${available.length}/${count} toko${partial}. ${key === 'pending' ? 'Posisi terbaru' : day(data.range.start) + ' sampai ' + day(data.range.end)}.${source} Pembaruan paling lama: ${stamp(times[0])}.`;
    }
    function metric(title, amount, note) { return `<div><h3>${esc(title)}</h3><strong>${esc(money(amount))}</strong><p>${esc(note)}</p></div>`; }
    const gmv = stores.filter(s => s.gmv !== null), ads = stores.filter(s => s.ads.amount !== null);
    $('secondary').innerHTML = metric('Omset dibayar',gmv.length ? gmv.reduce((n,s)=>n+s.gmv,0) : null, `Periode lengkap ${stores.filter(s=>s.gmv_days===days).length}/${count} toko. Rincian hari tersedia di bawah.`) + metric('Biaya iklan',ads.length ? ads.reduce((n,s)=>n+s.ads.amount,0) : null, `Periode lengkap ${stores.filter(s=>s.ads.days===days).length}/${count} toko. Iklan produk, toko, dan live.`);
    $('store-list').innerHTML = stores.map(s => `<article class="finance-store">
      <header><span class="shop-picker-logo" data-logo="${s.id}"></span><h3>${esc(s.name)}</h3></header>
      <dl>
        <div><dt>Pending</dt><dd>${esc(money(s.pending))}</dd><small>${esc(stamp(s.pending_updated))}</small></div>
        <div><dt>Sudah dilepas</dt><dd>${esc(money(s.released))}</dd><small>${s.released_basis==='detail' ? 'Jumlah rincian periode' : 'Ringkasan Shopee'} · ${esc(coverage(s.released_days,days))}</small></div>
        <div><dt>Omset dibayar</dt><dd>${esc(money(s.gmv))}</dd><small>${esc(coverage(s.gmv_days,days))}</small></div>
        <div><dt>Biaya iklan</dt><dd>${esc(money(s.ads.amount))}</dd><small>${esc(coverage(s.ads.days,days))}</small></div>
      </dl>
      ${s.released_difference ? `<p class="finance-notice">Ringkasan Shopee berbeda ${esc(money(s.released_difference))} dari jumlah rincian. Angka utama mengikuti ringkasan Shopee. Buka pembanding di bawah untuk melihat keduanya.</p>` : ''}
      <details><summary>Bagian pending &amp; pembanding rincian</summary><dl class="finance-breakdown">
        ${[['shipping','Dalam pengiriman'],['return','Retur proses'],['delivered','Tiba, menunggu pelepasan'],['unknown','Status belum dipastikan']].map(([key,label])=>`<div><dt>${label}</dt><dd>${esc(money(s.pending_states?.[key]))}</dd></div>`).join('')}
        <div><dt>Jumlah rincian pending</dt><dd>${esc(money(s.pending_detail))}</dd></div>
        <div><dt>Jumlah rincian dilepas</dt><dd>${esc(money(s.released_detail))}</dd><small>${esc(coverage(s.released_detail_days,days))}</small></div>
        <div><dt>Penyesuaian dana dilepas</dt><dd>${esc(money(s.adjustment))}</dd></div>
      </dl><p class="finance-help">Bagian pending tidak ditambahkan lagi ke total. ${s.pending !== null && s.pending !== s.pending_detail ? 'Ringkasan Shopee dan rincian pending saat ini berbeda.' : ''}</p></details>
      ${s.error ? `<p class="finance-notice">${esc(s.error)}</p>` : ''}
      ${s.active_imports ? `<p class="finance-help">${s.active_imports} bagian data masih dalam antrean atau sedang diambil.</p>` : ''}
    </article>`).join('');
    $('store-list').querySelectorAll('[data-logo]').forEach(el=>window.renderShopLogo(el,el.dataset.logo));
    const active = stores.reduce((n,s)=>n+s.active_imports,0);
    $('status').textContent = active ? 'Pembaruan berjalan atau menunggu pekerja sinkronisasi. Data lengkap terakhir tetap tampil.' : 'Menampilkan data tersimpan. Gunakan Perbarui data untuk mengambil saldo dan periode ini dari Shopee.';
    return active;
  }
  async function loadSummary(reset = false) {
    const sequence = ++summarySequence; clearTimeout(timer); error('');
    if (reset) { for (const id of ['pending','released']) { $(id).textContent='Memuat…'; $(id+'-note').textContent='Mengambil data tersimpan untuk filter ini.'; } $('secondary').replaceChildren(); $('store-list').replaceChildren(); }
    try {
      const data = await api('summary?' + query()); if (sequence !== summarySequence) return;
      const active = renderSummary(data);
      if (active) timer=setTimeout(()=>{ loadSummary(); if(tab==='details') loadList(); },5000);
    } catch (e) { if(sequence!==summarySequence)return; error(e.message); $('status').textContent='Pembaruan tampilan gagal. Coba lagi untuk memuat data.'; if(reset)for(const id of ['pending','released'])$(id).textContent='Belum dapat dimuat'; }
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
    const sequence=++listSequence; if(tab==='summary')return;
    const kind=tab==='details' ? 'detail' : 'cost', target=$(kind+'-list');
    target.textContent=kind==='detail' ? 'Memuat rincian penghasilan…' : 'Memuat produk dan HPP…'; $(kind+'-pagination').replaceChildren();
    $('detail-scope').textContent=$('category').value==='1' ? 'Seluruh pending pada pembaruan terbaru, tanpa filter tanggal.' : day(applied.start)+' sampai '+day(applied.end)+', berdasarkan tanggal dana dilepas.';
    try {
      const result=await api((kind==='detail' ? 'details?' : 'catalog?')+query(kind==='detail' ? {category:$('category').value,page:detailPage,search:detailSearch} : {page:costPage,search:costSearch}));
      if(sequence!==listSequence)return;
      if(!result.rows.length) target.innerHTML=`<p class="finance-empty">${kind==='detail' ? 'Belum ada rincian untuk pilihan ini. Jika saldo belum tersedia, klik Perbarui data. Jika memakai pencarian, coba nomor pesanan lain.' : 'Tidak ada produk yang cocok. Coba SKU lain atau sinkronkan produk di halaman Produk.'}</p>`;
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
  $('all-shops').onclick=()=>{ Array.from($('shops').options).forEach(o=>{o.selected=true;}); syncPicker(); };
  $('filters').addEventListener('submit',event=>{
    event.preventDefault(); if($('start').value>$('end').value){error('Tanggal mulai harus sebelum atau sama dengan tanggal akhir.');return;}
    applied=readFilters(); detailPage=costPage=1; url(); loadSummary(true); loadList();
  });
  root.querySelectorAll('[data-finance-tab]').forEach(b=>b.addEventListener('click',()=>setTab(b.dataset.financeTab)));
  $('category').onchange=()=>{detailPage=1;loadList();};
  for(const kind of ['order','cost']) $(kind+'-search-form').addEventListener('submit',event=>{event.preventDefault(); if(kind==='order'){detailSearch=$('order-search').value.trim();detailPage=1;}else{costSearch=$('cost-search').value.trim();costPage=1;}loadList();});
  $('sync').onclick=async()=>{
    $('sync').disabled=true; error('');
    try { const result=await api('sync',applied); $('status').textContent=result.message; await loadSummary(); }
    catch(e){error(e.message);} finally{$('sync').disabled=false;}
  };
  $('retry').onclick=()=>loadSummary(true);
  applied=readFilters(); setTab(tab); loadSummary(true);
})();
