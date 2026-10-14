(() => {
  const root = document.getElementById('finance-page');
  if (!root || !document.getElementById('finance-shops')) return;
  const $ = id => document.getElementById('finance-' + id);
  const dashboard = root.dataset.dashboard === 'true';
  const icon = name => `<span class="material-symbols-outlined" aria-hidden="true">${name}</span>`;
  const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const imageUrl = value => { const source=String(value || '').trim(); return !source ? '' : /^https?:\/\//i.test(source) ? source : 'https://cf.shopee.co.id/file/'+encodeURIComponent(source); };
  const imageSource = row => row?.variant_image ? 'Foto varian' : row?.product_image ? 'Foto produk utama' : 'Foto tidak tersedia';
  const costVisualMarkup = (row,large=false) => { const variant=imageUrl(row?.variant_image), product=imageUrl(row?.product_image), source=variant || product; const label=imageSource(row); const alt=row?.variation_name ? `Foto ${row.variation_name}` : `Foto ${row?.product_name || 'produk'}`; return `<span class="finance-cost-thumb${large ? ' finance-cost-thumb-large' : ''}" data-cost-visual><span class="finance-cost-image-source">${esc(label)}</span>${source ? `<img data-cost-image src="${esc(source)}"${variant && product ? ` data-cost-image-fallback-src="${esc(product)}"` : ''} alt="${esc(alt)}" loading="lazy"><span data-cost-image-fallback hidden>Foto tidak tersedia</span>` : ''}</span>`; };
  function bindCostImages(container) { container.querySelectorAll('[data-cost-image]').forEach(image=>image.addEventListener('error',()=>{ const fallbackSrc=image.dataset.costImageFallbackSrc; if(fallbackSrc && !image.dataset.costImageFallbackTried){ image.dataset.costImageFallbackTried='1'; image.src=fallbackSrc; const source=image.parentElement.querySelector('.finance-cost-image-source'); if(source)source.textContent='Foto produk utama'; return; } image.hidden=true; const fallback=image.parentElement.querySelector('[data-cost-image-fallback]'); if(fallback){fallback.textContent='Foto tidak tersedia';fallback.hidden=false;} })); }
  const money = value => value === null || value === undefined ? 'Belum tersedia' : new Intl.NumberFormat('id-ID', {style:'currency',currency:'IDR',maximumFractionDigits:2}).format(value);
  const stamp = value => value ? new Date(value.replace(' ', 'T') + 'Z').toLocaleString('id-ID',{timeZone:'Asia/Jakarta',dateStyle:'medium',timeStyle:'short'}) + ' WIB' : 'belum pernah diperbarui';
  const day = value => {
    const date=new Date(value+'T00:00:00+07:00');
    return value && Number.isFinite(date.getTime()) ? date.toLocaleDateString('id-ID',{timeZone:'Asia/Jakarta',dateStyle:'medium'}) : 'Tanggal belum valid';
  };
  const params = new URLSearchParams(location.search);
  const requested = (params.get('shops') || '').split(',');
  if (requested.some(Boolean) && Array.from($('shops').options).some(o => requested.includes(o.value))) Array.from($('shops').options).forEach(o => { o.selected = requested.includes(o.value); });
  const syncPicker = window.enhanceShopSelect($('shops'));
  const clearShops = $('clear-shops');
  const toggleAll = () => { const selected=$('shops').selectedOptions.length; $('all-shops').hidden=selected===$('shops').options.length; if(clearShops) clearShops.hidden=selected===0; };
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
  const costPreferenceKey='shopdash:hpp-filters:'+root.dataset.user;
  let costPreferences={};
  try { costPreferences=JSON.parse(localStorage.getItem(costPreferenceKey)) || {}; } catch (_) {}
  const preferredCostStatus=params.get('cost_status') ?? costPreferences.status;
  let applied, tab = ['summary','details','cost'].includes(params.get('tab')) ? params.get('tab') : 'summary';
  let summarySequence = 0, listSequence = 0, timer, detailPage = 1, costPage = 1, editing, proof, dialogSequence = 0, batchProof, batchRows, batchSequence = 0;
  let detailSearch = '', costSearch = String(params.get('cost_search') ?? costPreferences.search ?? '').slice(0,100), costStatus = ['all','filled','missing','archived'].includes(preferredCostStatus) ? preferredCostStatus : 'missing', costShop = String(params.get('cost_shop') ?? costPreferences.shop ?? ''), costRows = [], costSelection = new Set(), lastStoresMarkup = '';
  let syncCostPicker, costAnchor, changedCostKeys=new Set();
  const costKey=row=>`${row.shop_id}:${row.product_id}:${row.model_id}`;
  const costGroupKey=row=>`${row.shop_id}:${row.product_id}`;
  const selectedCosts=()=>costRows.filter(row=>costSelection.has(costKey(row)));
  function rememberCostFilters() {
    try { localStorage.setItem(costPreferenceKey,JSON.stringify({status:costStatus,shop:costShop,search:costSearch})); } catch (_) {}
  }
  const query = extra => new URLSearchParams({...applied,...extra}).toString();
  const readFilters = () => ({shops:$('shops').selectedOptions.length===0 ? '__none__' : $('shops').selectedOptions.length===$('shops').options.length ? '' : Array.from($('shops').selectedOptions, o => o.value).join(','),period:$('period').value,start:$('start').value,end:$('end').value});
  const costQuery = extra => new URLSearchParams({...extra,shops:costShop}).toString();
  const costScopeIds = () => costShop ? new Set([costShop]) : new Set(Array.from($('shops').options,o=>o.value));
  const costScopePayload = () => ({scope_shops:[...costScopeIds()]});
  function syncCostShopOptions() {
    if (dashboard || !$('cost-shop')) return;
    const selected=new Set(Array.from($('shops').options,o=>o.value));
    if (costShop && !selected.has(costShop)) costShop='';
    $('cost-shop').value=costShop;
    const selectedName=costShop ? $('cost-shop').selectedOptions[0]?.textContent : '';
    const scope=costShop ? `Menampilkan HPP untuk ${selectedName}.` : 'Menampilkan HPP untuk semua toko.';
    $('cost-scope').textContent=scope+' Filter toko HPP terpisah dari filter laporan.';
    $('cost-batch-scope').textContent=costShop ? `Toko target: ${selectedName}. File hanya boleh berisi toko ini.` : 'Toko target: semua toko. Periksa nama toko pada setiap baris sebelum menyimpan.';
    syncCostPicker?.();
  }
  function url() {
    const p = new URLSearchParams({...applied,...(dashboard ? {} : {tab,...(tab==='details' ? {category:$('category').value,state:$('category').value==='1' ? $('state').value : ''} : tab==='cost' ? {cost_status:costStatus,cost_shop:costShop,cost_search:costSearch} : {})})}); history.replaceState(null,'',location.pathname + '?' + p + location.hash);
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
  const countedDays = (days,total) => `Baru ${days} dari ${total} hari terhitung`;
  const affectedShops = (affected,count) => count>1 ? ` di ${affected} dari ${count} toko` : '';
  const quality = (available,complete,count,missing='') => {
    const partial=!count || complete<count;
    const label=!count ? 'Belum ada toko' : !available ? 'Data belum tersedia'+(count>1 ? ` dari ${count} toko` : '')
      : available<count ? `Baru ${available} dari ${count} toko terhitung`
      : partial ? missing : count===1 ? 'Data toko tersedia' : 'Data semua toko tersedia';
    return `<span class="finance-quality ${partial ? 'is-partial' : ''}">${icon(partial ? 'info' : 'check_circle')}<span>${esc(label)}</span></span>`;
  };
  const periodGap = (stores,daysOf,total) => stores.length===1 ? countedDays(daysOf(stores[0]),total)
    : 'Ada tanggal belum masuk'+affectedShops(stores.filter(s=>daysOf(s)<total).length,stores.length);
  const gmvGap = (stores,total) => {
    const missing=stores.filter(s=>s.gmv_days<total);
    if(missing.length && missing.every(s=>s.gmv_coverage?.missing_dates?.length===1)) {
      const dates=new Set(missing.map(s=>s.gmv_coverage.missing_dates[0]));
      if(dates.size===1) {
        const date=[...dates][0];
        return `Data ${date===today() ? 'hari ini' : day(date)} belum masuk`+affectedShops(missing.length,stores.length);
      }
    }
    return periodGap(stores,s=>s.gmv_days,total);
  };
  const states = [['preparing','Perlu dikirim','inventory_2'],['pickup','Pickup / verifikasi kurir','package_2'],['shipping','Dalam pengiriman','local_shipping'],['delivered','Tiba, menunggu dilepas','task_alt'],['return','Retur proses','assignment_return'],['mixed','Paket berbeda tahap','splitscreen'],['unknown','Status belum dipastikan','help']];
  const detailLink = (state,shop) => root.dataset.base+'/panel/finance?'+query({tab:'details',category:'1',state,...(shop ? {shops:shop} : {})});
  const stateList = (values,counts,partial=false,shop=null) => `<dl class="finance-state-list">${states.filter(([key])=>key!=='mixed' || counts?.mixed>0).map(([key,label,symbol])=>{
    const unresolved=!['unknown','mixed'].includes(key) && partial, empty=values && unresolved && !counts?.[key];
    return `<div data-pending-state="${key}"><dt>${icon(symbol)}<span>${label}</span></dt><dd>${empty ? 'Belum teridentifikasi' : esc(money(values ? values[key] ?? 0 : null))}</dd><small>${counts ? (counts[key] || 0)+' pesanan'+(unresolved ? ' teridentifikasi' : '') : 'Jumlah pesanan belum tersedia'}</small>${unresolved ? '<small class="finance-incomplete">Belum lengkap</small>' : ''}<a class="finance-text-button" href="${esc(detailLink(key,shop))}" aria-label="Lihat rincian ${esc(label)}">Lihat rincian</a></div>`;
  }).join('')}</dl>`;
  const unknownState = store => (store.pending_state_counts?.unknown ?? (store.pending_states?.unknown ? 1 : 0))>0;
  function replaceKeepingLink(container,markup) {
    const href=container.contains(document.activeElement) && document.activeElement.tagName==='A' ? document.activeElement.getAttribute('href') : null;
    container.innerHTML=markup;
    const restore=()=>{ if(href)Array.from(container.querySelectorAll('a')).find(a=>a.getAttribute('href')===href)?.focus({preventScroll:true}); };
    restore();return restore;
  }
  const interval = seconds => seconds%3600===0 ? seconds/3600+' jam' : seconds%60===0 ? seconds/60+' menit' : seconds+' detik';
  const topupNote = t => !t ? 'Belum pernah diperbarui.' : `${t.transactions} transaksi berhasil · termasuk PPN. ${t.error ? 'Pembaruan terakhir gagal. ' : ''}${t.stale ? 'Jadwal pembaruan terlewat. ' : ''}${!t.complete ? 'Riwayat belum lengkap untuk periode ini. ' : ''}Pembaruan terakhir: ${stamp(t.updated_at)}. ${t.enabled ? 'Jadwal setiap '+interval(t.interval_seconds)+'.' : 'Jadwal otomatis belum aktif.'}`;
  const topupIssue = (t,range) => {
    if(t?.error)return 'Pembaruan terakhir gagal';
    if(!t?.updated_at)return 'Riwayat belum pernah diperbarui';
    if(t.history_start && range.start<t.history_start)return 'Riwayat tersedia mulai '+day(t.history_start);
    const through=new Intl.DateTimeFormat('en-CA',{timeZone:'Asia/Jakarta',year:'numeric',month:'2-digit',day:'2-digit'}).format(new Date(t.updated_at.replace(' ','T')+'Z'));
    if(range.end>through)return 'Belum diperbarui sejak '+day(through);
    if(!t.complete)return 'Sebagian riwayat belum masuk';
    return t.stale ? 'Pembaruan terlewat' : '';
  };
  const topupGap = (stores,range) => {
    const missing=stores.filter(s=>!s.topups?.complete);
    const reasons=new Set(missing.map(s=>topupIssue(s.topups,range)));
    return (reasons.size===1 ? [...reasons][0] : 'Riwayat top up belum lengkap')+affectedShops(missing.length,stores.length);
  };
  const gmvNote = (s,range) => {
    const c=s.gmv_coverage;
    if(s.gmv===null)return 'Belum tersedia';
    if(c?.missing_dates?.length===1 && c.missing_dates[0]===today())return 'Hari ini belum masuk';
    if(c?.missing_dates?.length===1)return 'Belum masuk: '+day(c.missing_dates[0]);
    if(s.gmv_days<range.days)return countedDays(s.gmv_days,range.days);
    if(c?.today_failed)return 'Pembaruan hari ini gagal';
    return c?.today_included ? 'Hari ini masih berjalan' : '';
  };
  const issueNote = (text,warning=false) => text ? `<small class="finance-store-note${warning ? ' finance-incomplete' : ''}">${esc(text)}</small>` : '';
  const walletKnown = s => s.wallet?.amount!==null && s.wallet?.amount!==undefined;
  const walletIssue = s => s.wallet?.failed ? 'Pembaruan gagal' : s.wallet?.stale ? 'Pembaruan tertunda' : '';
  const walletDetail = s => `${walletKnown(s) ? 'Saldo Saya Shopee · '+stamp(s.wallet.updated_at)+'.' : 'Saldo belum tersedia.'} ${s.wallet?.failed ? (walletKnown(s) ? 'Saldo terakhir tetap ditampilkan. ' : '')+(s.wallet.error || 'Pembaruan terakhir gagal.') : s.wallet?.stale ? 'Menunggu pembaruan saldo.' : ''}`;
  const adsNote = (s,days) => s.ads.amount===null ? 'Belum tersedia' : s.ads.days<days ? countedDays(s.ads.days,days) : s.ads.stale ? 'Pembaruan tertunda' : '';
  const gmvDetail = (s,range) => {
    const c=s.gmv_coverage;
    if(!c)return coverage(s.gmv_days,range.days);
    return `${coverage(s.gmv_days,range.days)}.${c.first_date ? ' Data '+day(c.first_date)+' sampai '+day(c.last_date)+'.' : ''}${c.missing_dates.length ? ' Belum tersedia: '+c.missing_dates.map(day).join(', ')+'.' : ''}${c.today_included ? ' Hari ini masih sementara'+(c.today_through ? ', sampai '+stamp(c.today_through) : '')+'.' : ''}${c.today_failed ? ' Pembaruan omset hari ini gagal; data sebelumnya dipertahankan.' : ''}`;
  };
  function renderSummary(data) {
    const stores = data.stores, count = stores.length, days = data.range.days;
    for (const key of ['pending','released']) {
      const available = stores.filter(s => s[key] !== null);
      $(key).textContent = available.length ? money(available.reduce((sum,s) => sum + s[key],0)) : 'Belum tersedia';
      const times = available.map(s => s[key + '_updated']).filter(Boolean).sort();
      const complete=key==='pending' ? available.length : stores.filter(s=>s.released_days===days).length;
      $(key+'-quality').innerHTML=quality(available.length,complete,count,key==='released' ? periodGap(stores,s=>s.released_days,days) : '');
      const source=key==='pending' ? 'Posisi terbaru, tanpa filter tanggal.' : available.length && available.every(s=>s.released_basis!=='detail') ? 'Ringkasan Shopee.' : 'Jumlah rincian berdasarkan tanggal pelepasan.';
      $(key + '-note').textContent = `${source} Pembaruan paling lama: ${stamp(times[0])}.`;
    }
    const wallets=stores.filter(walletKnown), walletIssues=wallets.filter(s=>walletIssue(s));
    const walletTimes=wallets.map(s=>s.wallet.updated_at).filter(Boolean).sort();
    $('wallet').textContent=money(wallets.length ? wallets.reduce((sum,s)=>sum+s.wallet.amount,0) : null);
    $('wallet-quality').innerHTML=quality(wallets.length,wallets.length-walletIssues.length,count,'Memakai saldo terakhir'+affectedShops(walletIssues.length,count));
    $('wallet-note').textContent=`Tanpa filter tanggal. Pembaruan paling lama: ${stamp(walletTimes[0])}.${walletIssues.length ? ' Sebagian pembaruan tertunda atau gagal; lihat rincian toko.' : ''}`;
    const restricted=wallets.filter(s=>s.wallet.withdrawal_restricted===true);
    $('wallet-restrictions').hidden=!restricted.length;
    $('wallet-restrictions').textContent=restricted.length ? `Penarikan dibatasi di ${restricted.length} toko · lihat alasan` : '';
    function metric(title, symbol, amount, available, complete, missing, note) {
      const description=dashboard ? `<details class="dashboard-metric-source" data-source="${esc(symbol)}"><summary>Detail ${esc(title.toLowerCase())}</summary><p>${esc(note)}</p></details>` : `<p>${esc(note)}</p>`;
      return `<div><h2>${icon(symbol)}${esc(title)}</h2><strong>${esc(money(amount))}</strong>${quality(available,complete,count,missing)}${description}</div>`;
    }
    const gmv = stores.filter(s => s.gmv !== null), ads = stores.filter(s => s.ads.amount !== null), topups=stores.filter(s=>s.topups?.amount!==null && s.topups?.amount!==undefined);
    const gmvComplete=gmv.filter(s=>s.gmv_days===days).length, adsComplete=ads.filter(s=>s.ads.days===days).length;
    const topupComplete=topups.filter(s=>s.topups.complete).length, topupTimes=topups.map(s=>s.topups.updated_at).filter(Boolean).sort();
    const todayStores=gmv.filter(s=>s.gmv_coverage?.today_included).length;
    const secondaryOpen=new Set(Array.from($('secondary').querySelectorAll('details[open]'),el=>el.dataset.source));
    const secondaryFocus=$('secondary').contains(document.activeElement) ? document.activeElement.closest('[data-source]')?.dataset.source : null;
    $('secondary').innerHTML = metric('Omset dibayar','receipt_long',gmv.length ? gmv.reduce((n,s)=>n+s.gmv,0) : null,gmv.length,gmvComplete,gmvGap(stores,days),`Pesanan sudah dibayar.${todayStores ? ' Termasuk omset hari ini'+(count>1 ? ` dari ${todayStores} toko` : '')+'. Angka hari ini masih bisa berubah.' : ''}`)
      + metric('Top up iklan berhasil','add_card',topups.length ? topups.reduce((n,s)=>n+s.topups.amount,0) : null,topups.length,topupComplete,topupGap(stores,data.range),`Uang untuk isi saldo, termasuk PPN. ${topups.reduce((n,s)=>n+s.topups.transactions,0)} transaksi. Pembaruan paling lama: ${stamp(topupTimes[0])}.`)
      + metric('Biaya iklan terpakai','campaign',ads.length ? ads.reduce((n,s)=>n+s.ads.amount,0) : null,ads.length,adsComplete,periodGap(stores,s=>s.ads.days,days),'Biaya pemakaian iklan selama periode pilihan.');
    if(dashboard) {
      $('secondary').querySelectorAll('details').forEach(el=>{el.open=secondaryOpen.has(el.dataset.source);if(el.dataset.source===secondaryFocus)el.querySelector('summary').focus({preventScroll:true});});
      if(todayStores) { const note=document.createElement('p'); note.textContent='Termasuk hari ini, masih berjalan.'; $('secondary').firstElementChild.append(note); }
    }
    const pending=stores.filter(s=>s.pending_states!==null);
    const totals=pending.length ? Object.fromEntries(states.map(([key])=>[key,pending.reduce((n,s)=>n+(s.pending_states[key] || 0),0)])) : null;
    const stateCounts=pending.length ? Object.fromEntries(states.map(([key])=>[key,pending.reduce((n,s)=>n+(s.pending_state_counts?.[key] || 0),0)])) : null;
    const statusTimes=pending.map(s=>s.pending_status_updated).filter(Boolean).sort();
    const hasUnknown=pending.some(unknownState), incomplete=pending.length<count;
    const detailTotal=pending.reduce((n,s)=>n+s.pending_detail,0), pendingTotal=pending.reduce((n,s)=>n+s.pending,0);
    if(dashboard && $('pending-summary')) {
      const difference=Math.abs(pendingTotal-detailTotal);
      $('pending-summary').textContent=(hasUnknown || incomplete ? 'Sebagian status belum dipastikan.' : 'Bagian dari total Pending.')+(pending.length && difference>.005 ? ' Rincian berbeda '+money(difference)+' dari ringkasan Shopee.' : '');
    }
    replaceKeepingLink($('pending-states'),stateList(totals,stateCounts,hasUnknown || incomplete)+`<p class="finance-help">${pending.length}/${count} toko · status diperiksa paling lama ${esc(stamp(statusTimes[0]))}.${hasUnknown || incomplete ? ' Sebagian status belum tersedia. Penyebabnya ada pada rincian toko.' : ''}</p>`+(pending.length && Math.abs(pendingTotal-detailTotal)>.005 ? `<p class="finance-notice">Rincian berbeda ${esc(money(Math.abs(pendingTotal-detailTotal)))} dari ringkasan Shopee. Total Pending mengikuti ringkasan Shopee.</p>` : ''));
    $('store-period').textContent=`Pending dan Saldo Penjual: posisi terbaru. Angka lainnya: ${day(data.range.start)} sampai ${day(data.range.end)}.`;
    const markup = stores.map(s => `<article class="finance-store" data-store-id="${s.id}">
      <header><span class="shop-picker-logo" data-logo="${s.id}"></span><h3>${esc(s.name)}</h3></header>
      <dl class="finance-store-values">
        <div><dt>Pending</dt><dd>${esc(money(s.pending))}</dd></div>
        <div><dt>Sudah dilepas</dt><dd>${esc(money(s.released))}</dd>${issueNote(s.released_days<days ? countedDays(s.released_days,days) : '',true)}</div>
        <div data-wallet-shop="${s.id}"><dt>Saldo Penjual</dt><dd>${esc(money(s.wallet?.amount))}</dd>${issueNote(walletIssue(s),true)}${issueNote(s.wallet?.withdrawal_restricted===true ? 'Penarikan dibatasi' : '',true)}</div>
        <div><dt>Omset dibayar</dt><dd>${esc(money(s.gmv))}</dd>${issueNote(gmvNote(s,data.range),s.gmv_days<days || s.gmv_coverage?.today_failed)}</div>
        <div data-topup-shop="${s.id}"><dt>Top up iklan</dt><dd>${esc(money(s.topups?.amount))}</dd>${issueNote(topupIssue(s.topups,data.range),true)}</div>
        <div><dt>Biaya iklan</dt><dd>${esc(money(s.ads.amount))}</dd>${issueNote(adsNote(s,days),true)}</div>
      </dl>
      ${s.released_difference ? `<p class="finance-store-alert">${icon('info')}Rincian pelepasan berbeda ${esc(money(Math.abs(s.released_difference)))}. Angka utama mengikuti ringkasan Shopee.</p>` : ''}
      ${unknownState(s) ? `<p class="finance-store-alert">${icon('help')}${s.pending_state_counts?.unknown || 0} pesanan masih menunggu kepastian status.</p>` : ''}
      <details><summary>Rincian &amp; pembaruan<span class="finance-disclosure-icon material-symbols-outlined" aria-hidden="true">expand_more</span></summary>
      <div class="finance-store-details">${s.wallet?.withdrawal_restricted===true ? `<div class="finance-wallet-notice"><h4>Penarikan Saldo Penjual dibatasi</h4><p>${esc(s.wallet.notice || 'Shopee belum memberikan alasan pembatasan.')}</p><p class="finance-help">Keterangan Shopee pada pembaruan terakhir. Saldo tetap masuk total.</p></div>` : ''}<h4>Bagian Pending</h4>${stateList(s.pending_states,s.pending_state_counts,unknownState(s),s.id)}
      ${(s.pending_issues || []).map(issue=>`<p class="finance-help">${Number(issue.orders)} pesanan: ${esc(issue.message)}</p>`).join('')}
      <p class="finance-help">Status diperiksa paling lama: ${esc(stamp(s.pending_status_updated))}. Semua bagian sudah termasuk Pending.</p><h4>Pembanding saldo</h4><dl class="finance-breakdown">
        <div><dt>Jumlah rincian pending</dt><dd>${esc(money(s.pending_detail))}</dd></div>
        <div><dt>Jumlah rincian dilepas</dt><dd>${esc(money(s.released_detail))}</dd><small>${esc(coverage(s.released_detail_days,days))}</small></div>
        <div><dt>Penyesuaian dana dilepas</dt><dd>${esc(money(s.adjustment))}</dd></div>
      </dl>${s.pending !== null && s.pending !== s.pending_detail ? '<p class="finance-help">Ringkasan Shopee dan rincian Pending saat ini berbeda.</p>' : ''}
      <h4>Sumber &amp; waktu data</h4><dl class="finance-source-list">
        <div><dt>Saldo Penjual</dt><dd>${esc(walletDetail(s))}</dd></div>
        <div><dt>Pending</dt><dd>${esc(stamp(s.pending_updated))}</dd></div>
        <div><dt>Sudah dilepas</dt><dd>${s.released_basis==='detail' ? 'Rincian berdasarkan tanggal pelepasan' : 'Ringkasan Shopee'} · ${esc(stamp(s.released_updated))}</dd></div>
        <div><dt>Omset dibayar</dt><dd>${esc(gmvDetail(s,data.range))} Pembaruan paling lama: ${esc(stamp(s.gmv_updated))}.</dd></div>
        <div><dt>Top up iklan</dt><dd>${esc(topupNote(s.topups))}</dd></div>
        <div><dt>Biaya iklan</dt><dd>${s.ads.basis==='period' ? 'Total laporan sesuai periode pilihan.' : coverage(s.ads.days,days)+'.'} Produk, toko, dan live. Pembaruan paling lama: ${esc(stamp(s.ads.updated_at))}.</dd></div>
      </dl></div></details>
      ${s.error ? `<p class="finance-notice">${esc(s.error)}</p>` : ''}
      ${s.active_imports || s.wallet?.refresh_pending ? `<p class="finance-help">${icon('sync')}Saldo sedang diperbarui. Angka sebelumnya tetap tampil.</p>` : ''}
    </article>`).join('') || '<p class="finance-empty">Belum ada toko yang tersedia untuk ditampilkan.</p>';
    if(markup!==lastStoresMarkup) {
      const open=Array.from($('store-list').querySelectorAll('details[open]'),el=>el.closest('[data-store-id]').dataset.storeId);
      const focused=document.activeElement?.tagName==='SUMMARY' ? document.activeElement.closest('[data-store-id]')?.dataset.storeId : null;
      const restoreLink=replaceKeepingLink($('store-list'),markup); lastStoresMarkup=markup;
      $('store-list').querySelectorAll('[data-store-id]').forEach(el=>{ if(open.includes(el.dataset.storeId))el.querySelector('details').open=true; if(focused===el.dataset.storeId)el.querySelector('summary').focus({preventScroll:true}); });
      restoreLink();
      $('store-list').querySelectorAll('[data-logo]').forEach(el=>window.renderShopLogo(el,el.dataset.logo));
    }
    const active = stores.filter(s=>s.active_imports || s.wallet?.refresh_pending).length;
    $('status').textContent = active ? `Saldo ${active}/${count} toko sedang diperbarui. Angka sebelumnya tetap tampil.` : 'Tampilan diperiksa otomatis. Waktu data tercantum pada saldo.';
    return active;
  }
  async function loadSummary(reset = false) {
    const sequence = ++summarySequence; clearTimeout(timer); error('');
    if (reset) { for (const id of ['pending','released','wallet']) { $(id).textContent='Memuat…'; $(id+'-note').textContent='Memuat pilihan ini…'; $(id+'-quality').replaceChildren(); } $('wallet-restrictions').hidden=true; $('secondary').textContent='Memuat omset dan biaya iklan…'; $('pending-states').textContent='Memuat rincian Pending…'; if($('pending-summary'))$('pending-summary').textContent='Memuat rincian…'; $('store-list').replaceChildren(); lastStoresMarkup=''; }
    $('summary-panel').setAttribute('aria-busy','true');
    try {
      const data = await api('summary?' + query()); if (sequence !== summarySequence) return;
      const active = renderSummary(data);
      timer=setTimeout(refreshView,active ? 5000 : 30000);
    } catch (e) { if(sequence!==summarySequence)return; error(e.message); $('status').textContent='Pembaruan gagal. Angka yang masih tampil adalah data sebelumnya.'; if($('pending-summary'))$('pending-summary').textContent='Pembaruan gagal. Periksa data terakhir.'; if(reset) { for(const id of ['pending','released','wallet']) { $(id).textContent='Belum dapat dimuat'; $(id+'-note').textContent=''; } $('secondary').textContent='Omset dan biaya iklan belum dapat dimuat.'; $('pending-states').textContent='Rincian Pending belum dapat dimuat.'; } timer=setTimeout(refreshView,30000); }
    finally { if(sequence===summarySequence)$('summary-panel').setAttribute('aria-busy','false'); }
  }
  function pagination(kind,result) {
    const container=$(kind+'-pagination'); container.replaceChildren();
    const pages=Math.max(1,Math.ceil(result.total/25));
    const label=document.createElement('span'); label.textContent=`${result.total} baris · halaman ${result.page}/${pages}`;
    for(const [text,delta] of [['Sebelumnya',-1],['Berikutnya',1]]) {
      const b=document.createElement('button'); b.type='button'; b.className='btn'; b.textContent=text;
      b.disabled=delta<0 ? result.page<=1 : result.page>=pages;
      b.addEventListener('click',()=>{ if(kind==='detail') detailPage=result.page+delta; else { costPage=result.page+delta; costSelection.clear(); } loadList(); });
      container.append(b); if(delta<0)container.append(label);
    }
  }
  function renderCostSummary(summary) {
    const value=key => Number(summary?.[key] || 0);
    $('cost-summary').innerHTML=[
      ['total','Katalog',value('total')+' baris'],
      ['filled','HPP terisi',value('filled')+' baris'],
      ['missing','Belum diisi',value('missing')+' baris'],
      ['archived','Produk arsip',value('archived')+' baris'],
      ['products','Produk',value('products')],
      ['variants','Varian',value('variants')]
    ].map(([,label,amount])=>`<div><span>${esc(label)}</span><strong>${esc(amount)}</strong></div>`).join('');
  }
  function renderCostToolbar() {
    const count=costSelection.size;
    const selectedRows=selectedCosts();
    const shops=new Set(selectedRows.map(row=>String(row.shop_id)));
    const mixed=shops.size>1;
    $('cost-list').querySelectorAll('[data-cost-select]').forEach(box=>{ box.checked=costSelection.has(box.dataset.costSelect); });
    $('cost-bulk-form').hidden=!count;
    $('cost-selection-hint').hidden=!!count;
    $('cost-selection').textContent=!count ? '' : mixed ? `${count} baris dari ${shops.size} toko dipilih. Bulk HPP hanya dapat dilakukan untuk satu toko.` : `${count} baris dari satu toko dipilih. Isi HPP yang sama untuk semua baris terpilih.`;
    $('cost-bulk-apply').disabled=!count || mixed;
    $('cost-list').querySelectorAll('[data-cost-group]').forEach(button=>{
      const rows=costRows.filter(row=>costGroupKey(row)===button.dataset.costGroup && !Number(row.archived));
      const selected=rows.length>0 && rows.every(row=>costSelection.has(costKey(row)));
      button.setAttribute('aria-pressed',String(selected));
      button.textContent=selected ? 'Batalkan pilihan varian' : `Pilih semua ${rows.length} varian`;
    });
  }
  function costIdentityMarkup(row,copy=false) {
    const fields=[['ID produk',row.product_id],['ID varian',String(row.model_id)==='0' ? null : row.model_id],['SKU utama',row.parent_sku],['SKU varian',row.variant_sku]];
    return `<details class="finance-cost-identity"><summary>Lihat identitas</summary><dl>${fields.map(([label,value])=>`<div><dt>${label}</dt><dd><span>${esc(value || (label==='ID varian' ? 'Produk tanpa varian' : 'Belum diisi'))}</span>${copy && value ? `<button class="finance-copy-button" type="button" data-copy-cost="${esc(value)}" data-copy-label="${label}" aria-label="Salin ${label}">Salin</button>` : ''}</dd></div>`).join('')}</dl></details>`;
  }
  function costFeedback(message) {
    $('cost-feedback').textContent=message;
    $('cost-feedback').hidden=!message;
  }
  async function showCostSaved(keys,message,anchor) {
    changedCostKeys=new Set(keys);
    keys.forEach(key=>costSelection.delete(key));
    await loadList();
    if(tab!=='cost')return;
    costFeedback(message);
    const row=Array.from($('cost-list').querySelectorAll('[data-cost-key]')).find(el=>el.dataset.costKey===anchor?.key);
    if(row) {
      window.scrollBy(0,row.getBoundingClientRect().top-anchor.top);
      row.querySelector('[data-edit-cost]')?.focus({preventScroll:true});
    } else {
      $('cost-feedback').focus({preventScroll:true});
      $('cost-feedback').scrollIntoView({block:'nearest'});
    }
  }
  function csvCell(value) { return `"${String(value ?? '').replace(/"/g,'""')}"`; }
  function downloadCsv(filename,headers,rows) {
    const csv=[headers,...rows].map(row=>row.map(csvCell).join(',')).join('\r\n');
    const link=document.createElement('a'); link.href=URL.createObjectURL(new Blob(['\uFEFF'+csv],{type:'text/csv;charset=utf-8'})); link.download=filename; link.click(); setTimeout(()=>URL.revokeObjectURL(link.href),0);
  }
  function downloadCatalog() {
    api('catalogExport?'+costQuery({search:costSearch,status:costStatus})).then(result=>downloadCsv('katalog-hpp.csv',
      ['shop_id','shop_name','product_id','product_name','model_id','variation_name','parent_sku','variant_sku','sku','status','unit_cost','valid_from'],
      (result.rows||[]).map(row=>[row.shop_id,row.shop_name,row.product_id,row.product_name,row.model_id,row.variation_name,row.parent_sku,row.variant_sku,row.sku,row.status,row.unit_cost===null?'':row.unit_cost,row.valid_from||'']))
    ).catch(e=>{costFeedback(e.message);$('cost-feedback').scrollIntoView({block:'nearest'});});
  }
  function openBatchRows(rows) {
    invalidateBatch(); batchRows=null; $('cost-batch-title').textContent=`Atur HPP untuk ${rows.length} varian`; $('cost-batch-intro').textContent='Baris di bawah berasal dari pilihan Anda. Periksa lalu simpan HPP yang sama untuk semua varian.'; $('cost-batch-file').value=''; $('cost-batch-input').value=['shop_id,product_id,model_id,unit_cost,valid_from',...rows.map(row=>[row.shop_id,row.product_id,row.model_id,row.unit_cost,$('cost-bulk-date').value || root.dataset.today].join(','))].join('\n'); $('cost-batch-error').hidden=true; $('cost-batch-dialog').showModal(); $('cost-batch-input').focus();
  }
  function renderHealth(result) {
    $('cost-health-content').innerHTML=(result.shops||[]).map(shop=>{
      const h=shop.hpp||{}, o=shop.order_items||{};
      const attention=shop.status==='attention';
      return `<article class="finance-health-row"><header><strong>${esc(shop.shop_name)}</strong><span class="finance-health-status ${attention?'is-attention':'is-ready'}">${attention?'Perlu ditinjau':'Siap'}</span></header><dl><div><dt>HPP belum diisi</dt><dd>${esc(h.missing||0)}</dd></div><div><dt>Varian tanpa ID</dt><dd>${esc(o.variant_without_model||0)}</dd></div><div><dt>ID varian tidak cocok</dt><dd>${esc(o.invalid_model||0)}</dd></div><div><dt>Item order ganda</dt><dd>${esc(o.duplicate_rows||0)}</dd></div></dl></article>`;
    }).join('') || '<p class="finance-empty">Tidak ada toko untuk diaudit.</p>';
  }
  async function loadList() {
    const sequence=++listSequence; if(dashboard || tab==='summary')return;
    const kind=tab==='details' ? 'detail' : 'cost', target=$(kind+'-list');
    target.textContent=kind==='detail' ? 'Memuat rincian penghasilan…' : 'Memuat produk dan HPP…'; $(kind+'-pagination').replaceChildren(); if(kind==='cost') $('cost-summary').textContent='Memuat ringkasan HPP…';
    $('detail-scope').textContent=$('category').value==='1' ? 'Seluruh pending pada pembaruan terbaru, tanpa filter tanggal.' : day(applied.start)+' sampai '+day(applied.end)+', berdasarkan tanggal dana dilepas.';
    try {
      const result=await api((kind==='detail' ? 'details?' : 'catalog?')+(kind==='detail' ? query({category:$('category').value,state:$('category').value==='1' ? $('state').value : '',page:detailPage,search:detailSearch}) : costQuery({page:costPage,search:costSearch,status:costStatus})));
      if(sequence!==listSequence)return;
      if(kind==='cost' && !result.rows.length && costPage>1) { costPage=Math.max(1,Math.ceil(result.total/25)); return loadList(); }
      if(kind==='cost') { costRows=result.rows||[]; const available=new Set(costRows.filter(row=>!Number(row.archived)).map(costKey)); costSelection=new Set([...costSelection].filter(key=>available.has(key))); renderCostSummary(result.summary||{}); renderCostToolbar(); }
      if(!result.rows.length) target.innerHTML=`<p class="finance-empty">${kind==='detail' ? 'Belum ada rincian untuk pilihan ini. Jika saldo belum tersedia, klik Perbarui saldo Shopee. Jika memakai pencarian, coba nomor pesanan lain.' : costStatus==='missing' && !costSearch ? 'Tidak ada HPP yang belum diisi pada pilihan toko ini. Pilih Semua produk untuk melihat katalog dan HPP yang sudah terisi.' : 'Tidak ada produk yang cocok. Ubah pencarian atau status HPP. Jika katalog belum tersedia, sinkronkan produk di halaman Produk.'}</p>`;
      else if(kind==='detail') {
        const labels={...Object.fromEntries(states.map(([key,label])=>[key,label])),released:'Sudah dilepas'};
        target.innerHTML='<div class="finance-table-wrap"><table class="finance-table"><thead><tr><th>Pesanan / toko</th><th>Status / tanggal</th><th>Penghasilan / HPP</th><th>Penyesuaian</th></tr></thead><tbody>'+result.rows.map(r=>`<tr><td data-label="Pesanan / toko"><strong>${esc(r.order_sn)}</strong><span>${esc(r.shop_name)}</span><small>${esc(r.product_name)}</small></td><td data-label="Status / tanggal">${esc(labels[r.state])}${r.state_note ? '<small>'+esc(r.state_note)+'</small>' : ''}<span>${esc(r.released_at ? stamp(r.released_at) : r.estimated_at ? 'Perkiraan: '+stamp(r.estimated_at) : 'Tanggal pelepasan belum tersedia')}</span></td><td data-label="Penghasilan / HPP">${esc(money(r.income_amount))}<small>HPP: ${esc(r.cost?.amount === null ? (r.cost.items ? 'belum lengkap' : 'rincian produk belum tersedia') : money(r.cost?.amount))}</small></td><td data-label="Penyesuaian">${esc(money(r.adjustment_amount))}</td></tr>`).join('')+'</tbody></table></div>';
      } else {
        const groups=new Map(); costRows.forEach(row=>{const key=costGroupKey(row);if(!groups.has(key))groups.set(key,[]);groups.get(key).push(row);});
        const renderedGroups=new Set();
        target.innerHTML='<div class="finance-table-wrap"><table class="finance-table finance-cost-table"><thead><tr><th>Pilih</th><th>Produk / varian</th><th>Toko / identitas</th><th>HPP per unit</th><th>Aksi</th></tr></thead><tbody>'+costRows.map((r,i)=>{
          const groupKey=costGroupKey(r), group=groups.get(groupKey)||[], activeGroup=group.filter(row=>!Number(row.archived));
          const groupAction=activeGroup.length>1 && !renderedGroups.has(groupKey) ? `<button class="finance-text-button finance-cost-group-action" type="button" data-cost-group="${esc(groupKey)}" aria-pressed="false">Pilih semua ${activeGroup.length} varian</button>` : '';
          renderedGroups.add(groupKey);
          return `<tr data-cost-key="${esc(costKey(r))}" class="${changedCostKeys.has(costKey(r))?'finance-cost-saved':''}"><td data-label="Pilih"><label class="finance-cost-check"><input type="checkbox" data-cost-select="${esc(costKey(r))}" ${costSelection.has(costKey(r))?'checked':''} ${Number(r.archived)?'disabled':''} aria-label="Pilih HPP ${esc(r.product_name)}, ${esc(r.variation_name || 'tanpa varian')}, ${esc(r.shop_name)}"></label></td><td data-label="Produk / varian"><div class="finance-cost-product"><div>${costVisualMarkup(r)}</div><div><strong class="finance-cost-product-name">${esc(r.product_name)}</strong><span class="finance-cost-variant-name">Varian: ${esc(r.variation_name || 'Produk tanpa varian')}</span>${Number(r.archived) ? '<small>Produk arsip</small>' : ''}${groupAction}</div></div></td><td data-label="Toko / identitas"><span class="finance-cost-shop-name">${esc(r.shop_name)}</span>${costIdentityMarkup(r)}</td><td data-label="HPP per unit"><strong>${r.unit_cost===null ? 'Belum diisi' : esc(money(r.unit_cost))}</strong>${r.valid_from ? '<span>Sejak '+esc(day(r.valid_from))+'</span>' : ''}${changedCostKeys.has(costKey(r))?'<small>HPP diperbarui</small>':''}</td><td data-label="Aksi"><button class="btn" type="button" data-edit-cost="${i}">Atur HPP</button></td></tr>`;
        }).join('')+'</tbody></table></div>';
        bindCostImages(target);
        target.querySelectorAll('[data-edit-cost]').forEach(b=>b.addEventListener('click',()=>{const row=b.closest('[data-cost-key]');costAnchor={key:row.dataset.costKey,top:row.getBoundingClientRect().top};openCost(costRows[Number(b.dataset.editCost)]);}));
        target.querySelectorAll('[data-cost-select]').forEach(box=>box.addEventListener('change',()=>{const key=box.dataset.costSelect; if(box.checked)costSelection.add(key);else costSelection.delete(key); renderCostToolbar();}));
        target.querySelectorAll('[data-cost-group]').forEach(button=>button.addEventListener('click',()=>{
          const rows=costRows.filter(row=>costGroupKey(row)===button.dataset.costGroup && !Number(row.archived));
          const select=rows.some(row=>!costSelection.has(costKey(row)));
          rows.forEach(row=>{const key=costKey(row);if(select)costSelection.add(key);else costSelection.delete(key);});
          renderCostToolbar();
        }));
        renderCostToolbar();
      }
      pagination(kind,result);
    } catch(e) { if(sequence!==listSequence)return; if(kind==='cost')$('cost-summary').textContent='Ringkasan HPP belum dapat dimuat.'; target.textContent=e.message; const b=document.createElement('button'); b.className='btn'; b.textContent='Coba lagi'; b.onclick=loadList; target.append(b); }
  }
  function setTab(value) {
    tab=value; root.querySelectorAll('[data-finance-tab]').forEach(b=>b.setAttribute('aria-pressed',String(b.dataset.financeTab===tab)));
    root.querySelector(`[data-finance-tab="${tab}"]`)?.scrollIntoView({block:'nearest',inline:'nearest'});
    for(const name of ['summary','details','cost']) $(''+name+'-panel').hidden=name!==tab;
    $('report-overview').hidden=tab==='cost';
    $('sync').parentElement.hidden=tab==='cost';
    if(tab==='cost' && !syncCostPicker)syncCostPicker=window.enhanceShopSelect($('cost-shop'));
    url(); loadList();
  }
  $('wallet-restrictions').onclick=event=>{
    event.preventDefault(); if(!dashboard)setTab('summary');
    const notices=Array.from($('store-list').querySelectorAll('.finance-wallet-notice'));
    notices.forEach(el=>{el.closest('details').open=true;});
    const summary=notices[0]?.closest('details').querySelector('summary');
    if(summary) { summary.focus({preventScroll:true}); summary.scrollIntoView({block:'start'}); }
  };
  function invalidatePreview() { proof=null; $('save-cost').disabled=true; $('cost-preview').hidden=true; }
  async function openCost(item) {
    editing=item; invalidatePreview(); const sequence=++dialogSequence;
    $('cost-error').hidden=true; $('cost-value').value=item.unit_cost ?? ''; $('cost-date').value=root.dataset.today;
    $('cost-shop-target').textContent=item.shop_name || 'Toko tidak diketahui';
    $('cost-image').innerHTML=costVisualMarkup(item,true); bindCostImages($('cost-image'));
    $('cost-item').textContent=item.product_name;
    $('cost-variant').textContent='Varian: '+(item.variation_name || 'Produk tanpa varian');
    $('cost-identities').innerHTML=costIdentityMarkup(item,true);
    $('cost-copy-status').textContent='';
    $('cost-history').textContent='Memuat riwayat…'; $('cost-dialog').showModal(); $('cost-value').focus();
    try {
      const result=await api('history?'+new URLSearchParams({shop_id:item.shop_id,product_id:item.product_id,model_id:item.model_id}));
      if(sequence!==dialogSequence)return;
      $('cost-history').innerHTML=result.rows.length ? '<ul>'+result.rows.map(r=>`<li><span>Mulai ${esc(day(r.valid_from))}: ${esc(money(Number(r.unit_cost)))} per unit</span>${r.previous_cost===null ? '' : `<button type="button" class="finance-history-action" data-revert-cost="${esc(r.valid_from)}">Pulihkan ${esc(money(Number(r.previous_cost)))}</button>`}</li>`).join('')+'</ul>' : '<p>HPP produk ini belum pernah diisi.</p>';
      $('cost-history').querySelectorAll('[data-revert-cost]').forEach(button=>button.addEventListener('click',async()=>{
        if(button.dataset.confirmRevert !== 'true'){
          button.dataset.confirmRevert='true';
          button.dataset.revertLabel=button.textContent;
          button.textContent='Klik lagi untuk memulihkan';
          $('cost-copy-status').textContent='Klik lagi untuk memulihkan HPP sebelumnya, atau pilih tindakan lain.';
          window.setTimeout(()=>{if(button.dataset.confirmRevert==='true'){delete button.dataset.confirmRevert;button.textContent=button.dataset.revertLabel||'Pulihkan HPP sebelumnya';delete button.dataset.revertLabel;}},5000);
          return;
        }
        delete button.dataset.confirmRevert; delete button.dataset.revertLabel;
        button.disabled=true;
        try { const result=await api('revert',{shop_id:editing.shop_id,product_id:editing.product_id,model_id:editing.model_id,version:editing.version,valid_from:button.dataset.revertCost}); editing={...editing,version:result.version,unit_cost:result.unit_cost}; await openCost(editing); $('cost-copy-status').textContent='HPP sebelumnya dipulihkan.'; costFeedback('HPP sebelumnya dipulihkan.'); loadList(); }
        catch(error) { $('cost-error').textContent=error.message; $('cost-error').hidden=false; button.disabled=false; }
      }));
    } catch(e) { if(sequence===dialogSequence)$('cost-history').textContent=e.message; }
  }
  function batchDelimiter(source) {
    const candidates=[',',';','\t'], counts=new Map(candidates.map(value=>[value,0])), firstLine=source.split(/\r?\n/).find(line=>line.trim()!=='') || ''; let quoted=false;
    for(let i=0;i<firstLine.length;i++) {
      const char=firstLine[i], next=firstLine[i+1];
      if(char==='"' && quoted && next==='"') { i++; continue; }
      if(char==='"') { quoted=!quoted; continue; }
      if(!quoted && counts.has(char)) counts.set(char,counts.get(char)+1);
    }
    return candidates.reduce((best,value)=>counts.get(value)>counts.get(best) ? value : best,',');
  }
  function validateBatchRows(rows) {
    const ids=[['shop_id','ID toko',true],['product_id','ID produk',true],['model_id','ID varian',false]], errors=[];
    const scientific=/^\d+[,.]\d+e[+-]\d+$/i;
    const positive=value=>/^0+$/.test(String(value));
    const validDate=value=>{ if(!/^\d{4}-\d{2}-\d{2}$/.test(value)) return false; const parsed=new Date(value+'T00:00:00Z'); return Number.isFinite(parsed.getTime()) && parsed.toISOString().slice(0,10)===value; };
    rows.forEach(row=>{
      const line=`Baris ${row.row_no}`;
      ids.forEach(([field,label,required])=>{
        const value=String(row[field] ?? '').trim();
        if(!value) { errors.push(`${line}: ${label} wajib diisi. Produk tanpa varian tetap memakai model_id 0.`); return; }
        if(scientific.test(value)) {
          const sku=String(row.variant_sku || row.sku || '').trim();
          if(field==='model_id' && sku) return;
          errors.push(`${line}: ${label} terbaca sebagai notasi ilmiah (${value}). Unduh ulang katalog dan impor kolom ID sebagai teks${field==='model_id' ? ' atau isi SKU varian yang masih utuh' : ''}; angka yang sudah dibulatkan tidak dapat dipulihkan.`); return;
        }
        if(!/^\d+$/.test(value) || (required && positive(value))) errors.push(`${line}: ${label} harus berupa angka bulat tanpa koma, titik, atau notasi ilmiah.`);
      });
      const unit=String(row.unit_cost ?? '').trim();
      if(!unit) errors.push(`${line}: HPP per unit wajib diisi.`);
      else if(!/^\d+$/.test(unit) || Number(unit)>1000000000) errors.push(`${line}: HPP per unit harus berupa angka bulat dari 0 sampai 1 miliar.`);
      const date=String(row.valid_from ?? '').trim();
      if(!date) errors.push(`${line}: tanggal mulai HPP wajib diisi.`);
      else if(!validDate(date)) errors.push(`${line}: tanggal mulai HPP harus berformat YYYY-MM-DD.`);
    });
    if(errors.length) {
      const shown=errors.slice(0,3).join(' ');
      throw new Error(`${errors.length} masalah ditemukan sebelum dikirim. ${shown}${errors.length>3 ? ' Perbaiki baris lain yang ditandai pada file.' : ''}`);
    }
  }
  function parseBatchText(text) {
    const rows=[], source=String(text||'').replace(/^\uFEFF/,''), delimiter=batchDelimiter(source); let fields=[], field='', quoted=false;
    for(let i=0;i<source.length;i++) {
      const char=source[i], next=source[i+1];
      if(char==='"' && quoted && next==='"') { field+='"'; i++; continue; }
      if(char==='"') { quoted=!quoted; continue; }
      if(!quoted && char===delimiter) { fields.push(field.trim()); field=''; continue; }
      if(!quoted && (char==='\n' || char==='\r')) {
        if(char==='\r' && next==='\n') i++;
        fields.push(field.trim()); field='';
        if(fields.some(Boolean)) rows.push(fields);
        fields=[]; continue;
      }
      field+=char;
    }
    if(quoted) throw new Error('Tanda kutip CSV belum ditutup.');
    fields.push(field.trim()); if(fields.some(Boolean)) rows.push(fields);
    if(!rows.length) throw new Error('Isi data batch terlebih dahulu.');
    const header=rows[0].map(value=>value.toLowerCase().replace(/\s+/g,'_'));
    const columns=['shop_id','product_id','model_id','unit_cost','valid_from'];
    const hasHeader=columns.every(column=>header.includes(column));
    const headerLike=header.some(value=>['shop_id','product_id','model_id','unit_cost','valid_from','shop_name','product_name'].includes(value));
    if(headerLike && !hasHeader) throw new Error('Header file belum lengkap. Gunakan kolom shop_id, product_id, model_id, unit_cost, valid_from.');
    const optional=['shop_name','product_name','variation_name','parent_sku','variant_sku','sku'];
    const start=hasHeader?1:0, indexes=hasHeader?Object.fromEntries([...columns,...optional].map(column=>[column,header.indexOf(column)])):Object.fromEntries(columns.map((column,index)=>[column,index]));
    const parsed=[];
    for(let index=start;index<rows.length;index++) {
      const values=rows[index];
      const row={row_no:index+1,...Object.fromEntries(columns.map(column=>[column,values[indexes[column]]??'']))};
      optional.forEach(column=>{if(indexes[column]>=0)row[column]=values[indexes[column]]??'';});
      parsed.push(row);
    }
    if(!parsed.length) throw new Error('Data batch hanya berisi header.');
    const rowsWithCost=parsed.filter(row=>String(row.unit_cost ?? '').trim()!=='' || String(row.valid_from ?? '').trim()!=='');
    const skipped=parsed.length-rowsWithCost.length;
    if(!rowsWithCost.length) throw new Error('Belum ada baris dengan HPP dan tanggal mulai. Isi minimal satu baris terlebih dahulu.');
    validateBatchRows(rowsWithCost);
    return {rows:rowsWithCost,skipped};
  }
  function validateBatchScope(rows) {
    const allowed=costScopeIds();
    const outside=rows.filter(row=>!allowed.has(String(row.shop_id)));
    if (outside.length) throw new Error(costShop ? 'Ada baris batch dari toko lain. Pilih Toko HPP yang sesuai atau periksa shop_id pada file.' : 'Ada baris batch dari toko yang tidak tersedia. Periksa shop_id pada file.');
  }
  function invalidateBatch() {
    batchSequence++; batchProof=null; $('save-cost-batch').disabled=true; $('cost-batch-preview').hidden=true; $('cost-batch-note').hidden=true;
  }
  function batchPreviewMarkup(result) {
    const preview=result.preview || result;
    const errorRows=new Map((preview.errors||[]).map(error=>[Number(error.row_no),error.message]));
    const summary=preview.summary||{};
    const rows=(preview.rows||[]).map(row=>{
      const message=errorRows.get(Number(row.row_no));
      const impact=row.impact ? `${esc(row.impact.orders)} pesanan · ${esc(row.impact.units)} unit` : 'Belum dihitung';
      return `<tr><td data-label="Baris">${esc(row.row_no)}</td><td data-label="Produk / varian"><div class="finance-cost-product"><div>${costVisualMarkup(row)}</div><div><strong>${esc(row.product_name||row.product_id)}</strong><span>Varian: ${esc(row.variation_name||'Produk tanpa varian')}</span><small>SKU: ${esc(row.variant_sku || row.sku || 'Belum diisi')}</small></div></div>${costIdentityMarkup(row)}</td><td data-label="Toko">${esc(row.shop_name||row.shop_id)}</td><td data-label="HPP per unit"><span>Lama: ${esc(row.previous_cost===null?'Belum diisi':money(row.previous_cost))}</span><strong>Baru: ${esc(money(row.unit_cost))}</strong><small>Mulai ${esc(day(row.valid_from))}</small></td><td data-label="Dampak order">${impact}</td><td data-label="Status">${message?`<span class="finance-batch-invalid">${esc(message)}</span>`:'Siap'}</td></tr>`;
    }).join('');
    const errors=(preview.errors||[]).map(error=>`<li>Baris ${esc(error.row_no)}: ${esc(error.message)}</li>`).join('');
    return `<strong>${esc(summary.valid||0)} dari ${esc(summary.total||0)} baris siap.</strong>${summary.changed?` <span>${esc(summary.changed)} HPP akan berubah.</span>`:''}${summary.unchanged?` <span>${esc(summary.unchanged)} baris tidak berubah.</span>`:''}${summary.errors?`<ul class="finance-batch-errors">${errors}</ul>`:''}${rows?`<div class="finance-batch-table-wrap"><table class="finance-batch-table"><thead><tr><th>Baris</th><th>Produk / varian</th><th>Toko</th><th>HPP per unit</th><th>Dampak order</th><th>Status</th></tr></thead><tbody>${rows}</tbody></table></div>`:''}`;
  }
  async function previewBatch() {
    invalidateBatch(); $('cost-batch-error').hidden=true; $('preview-cost-batch').disabled=true;
    const sequence=++batchSequence;
    try {
      const parsed=parseBatchText($('cost-batch-input').value), rows=parsed.rows; validateBatchScope(rows); batchRows=rows; const scope=costScopePayload();
      if(parsed.skipped) { $('cost-batch-note').textContent=`${parsed.skipped} baris tanpa HPP dan tanggal dilewati. Baris tersebut tidak mengubah data yang sudah tersimpan.`; $('cost-batch-note').hidden=false; }
      const result=await api('batchPreview',{rows,...scope});
      if(sequence!==batchSequence)return;
      $('cost-batch-preview').innerHTML=batchPreviewMarkup(result); bindCostImages($('cost-batch-preview')); $('cost-batch-preview').hidden=false;
      const preview=result.preview || result;
      if(result.token && !(preview.errors||[]).length) { batchProof={rows,...scope,expires:result.expires,token:result.token}; $('save-cost-batch').disabled=false; }
      else $('cost-batch-error').textContent='Perbaiki baris yang ditandai sebelum menyimpan.';
      $('cost-batch-error').hidden=!(preview.errors||[]).length;
    } catch(e) { $('cost-batch-error').textContent=e.message; $('cost-batch-error').hidden=false; }
    finally { $('preview-cost-batch').disabled=false; }
  }
  function openBatch() { invalidateBatch(); batchRows=null; $('cost-batch-title').textContent='Impor HPP batch'; $('cost-batch-intro').textContent='Unggah CSV atau tempel data dari spreadsheet. Gunakan katalog HPP untuk mendapatkan ID produk dan varian yang tepat.'; $('cost-batch-input').value=''; $('cost-batch-file').value=''; $('cost-batch-error').hidden=true; $('cost-batch-dialog').showModal(); $('cost-batch-input').focus(); }
  function closeBatch() { $('cost-batch-dialog').close(); }
  const costInput=()=>({shop_id:editing.shop_id,product_id:editing.product_id,model_id:editing.model_id,version:editing.version,unit_cost:$('cost-value').value,valid_from:$('cost-date').value,...costScopePayload()});
  if(!dashboard) {
  $('cost-search').value=costSearch;
  $('cost-identities').addEventListener('click',async event=>{
    const button=event.target.closest('[data-copy-cost]'); if(!button)return;
    try { await navigator.clipboard.writeText(button.dataset.copyCost); $('cost-copy-status').textContent=button.dataset.copyLabel+' disalin.'; }
    catch (_) { $('cost-copy-status').textContent='Tidak dapat menyalin otomatis. Pilih teks identitas lalu salin secara manual.'; }
  });
  $('cost-clear-selection').onclick=()=>{costSelection.clear();$('cost-list').querySelectorAll('[data-cost-select]').forEach(box=>box.checked=false);renderCostToolbar();$('cost-selection-hint').scrollIntoView({block:'nearest'});};
  $('open-cost-batch').onclick=openBatch;
  $('close-cost-batch').onclick=closeBatch;
  $('cost-batch-dialog').addEventListener('close',()=>{batchSequence++;invalidateBatch();});
  $('cost-batch-input').addEventListener('input',invalidateBatch);
  $('cost-batch-form').addEventListener('submit',event=>{event.preventDefault();previewBatch();});
  $('cost-batch-file').addEventListener('change',async event=>{const file=event.target.files?.[0]; if(!file)return; $('cost-batch-input').value=await file.text(); invalidateBatch();});
  $('cost-batch-template').onclick=()=>{const blob=new Blob(['shop_id,product_id,model_id,unit_cost,valid_from\n'],{type:'text/csv;charset=utf-8'});const link=document.createElement('a');link.href=URL.createObjectURL(blob);link.download='template-hpp.csv';link.click();URL.revokeObjectURL(link.href);};
  $('cost-export').onclick=downloadCatalog;
  $('cost-health').onclick=async()=>{ $('cost-health-content').textContent='Memuat audit…'; $('cost-health-dialog').showModal(); try { renderHealth(await api('health?'+costQuery({}))); } catch(e) { $('cost-health-content').innerHTML=`<p class="finance-notice">${esc(e.message)}</p>`; } };
  $('close-cost-health').onclick=()=>$('cost-health-dialog').close();
  $('cost-shop').addEventListener('change',()=>{costShop=$('cost-shop').value; costPage=1; costSelection.clear(); costFeedback(''); syncCostShopOptions(); rememberCostFilters(); url(); loadList();});
  $('cost-status').value=costStatus;
  $('cost-status').addEventListener('change',()=>{costStatus=$('cost-status').value; costPage=1; costSelection.clear(); costFeedback(''); rememberCostFilters(); url(); loadList();});
  $('cost-bulk-date').value=root.dataset.today;
  $('cost-bulk-form').onsubmit=event=>{
    event.preventDefault();
    const value=$('cost-bulk-value').value, date=$('cost-bulk-date').value;
    if(!/^\d+$/.test(value) || Number(value)>1000000000 || !/^\d{4}-\d{2}-\d{2}$/.test(date)) { costFeedback('Isi HPP dan tanggal berlaku yang valid terlebih dahulu.'); return; }
    const selected=selectedCosts().filter(row=>!Number(row.archived));
    if(new Set(selected.map(row=>String(row.shop_id))).size>1)return;
    const rows=selected.map(row=>({shop_id:row.shop_id,product_id:row.product_id,model_id:row.model_id,unit_cost:value,valid_from:date}));
    if(!rows.length) { costFeedback('Pilih baris produk aktif terlebih dahulu.'); return; }
    openBatchRows(rows);
  };
  $('save-cost-batch').onclick=async()=>{
    if(!batchProof)return; $('save-cost-batch').disabled=true; $('preview-cost-batch').disabled=true; $('cost-batch-error').hidden=true;
    const savedKeys=batchProof.rows.map(costKey);
    try { const result=await api('batchSave',{rows:batchProof.rows,scope_shops:batchProof.scope_shops,expires:batchProof.expires,token:batchProof.token}); closeBatch(); costSelection.clear(); await showCostSaved(savedKeys,`${result.saved} HPP tersimpan. Riwayat modal diperbarui.`,null); }
    catch(e) { $('cost-batch-error').textContent=e.message; $('cost-batch-error').hidden=false; invalidateBatch(); }
    finally { $('preview-cost-batch').disabled=false; }
  };
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
    const item=editing, anchor=costAnchor;
    try { await api('save',proof); $('cost-dialog').close(); await showCostSaved([costKey(item)],`HPP ${item.variation_name || item.product_name} di ${item.shop_name} tersimpan. Riwayat modal diperbarui.`,anchor); }
    catch(e) { $('cost-error').textContent=e.message; $('cost-error').hidden=false; invalidatePreview(); }
    finally { $('preview-cost').disabled=false; }
  });
  $('close-cost').onclick=()=>$('cost-dialog').close();
  $('cost-dialog').addEventListener('close',()=>{ dialogSequence++; invalidatePreview(); });
  }
  const draft=()=>{ toggleAll(); $('filter-draft').hidden=JSON.stringify(readFilters())===JSON.stringify(applied); };
  $('all-shops').onclick=()=>{ Array.from($('shops').options).forEach(o=>{o.selected=true;}); syncPicker(); draft(); };
  if(clearShops) clearShops.onclick=()=>{ Array.from($('shops').options).forEach(o=>{o.selected=false;}); syncPicker(); draft(); };
  $('period').addEventListener('change',()=>{preparePeriod();draft();});
  $('shops').addEventListener('change',draft);
  for(const field of ['start','end']) $(field).addEventListener('input',draft);
  $('filters').addEventListener('submit',event=>{
    event.preventDefault(); if(!$('shops').selectedOptions.length){error('Pilih setidaknya satu toko sebelum menerapkan filter.');return;} if($('start').value>$('end').value){error('Tanggal mulai harus sebelum atau sama dengan tanggal akhir.');return;}
    applied=readFilters(); $('filter-draft').hidden=true; detailPage=costPage=1; costSelection.clear(); syncCostShopOptions(); url(); publishFilters(); loadSummary(true); loadList();
  });
  if(!dashboard) {
  root.querySelectorAll('[data-finance-tab]').forEach(b=>b.addEventListener('click',()=>setTab(b.dataset.financeTab)));
  if (params.get('category')==='2') $('category').value='2';
  if (states.some(([key])=>key===params.get('state'))) $('state').value=params.get('state');
  $('state-field').hidden=$('category').value!=='1';
  $('category').onchange=()=>{$('state-field').hidden=$('category').value!=='1';detailPage=1;url();loadList();};
  $('state').onchange=()=>{detailPage=1;url();loadList();};
  for(const kind of ['order','cost']) $(kind+'-search-form').addEventListener('submit',event=>{event.preventDefault(); if(kind==='order'){detailSearch=$('order-search').value.trim();detailPage=1;}else{costSearch=$('cost-search').value.trim();costPage=1;costSelection.clear();costFeedback('');rememberCostFilters();url();}loadList();});
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
  syncCostShopOptions();
  window.shopdashFinance={get filters(){return {...applied};},esc,money,stamp,day,icon};
  if(dashboard){tab='summary';url();}else setTab(tab);
  publishFilters(); loadSummary(true);
})();
