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
    const p = new URLSearchParams({...applied,...(dashboard ? {} : {tab,...(tab==='details' ? {category:$('category').value,state:$('category').value==='1' ? $('state').value : ''} : {})})}); history.replaceState(null,'',location.pathname + '?' + p + location.hash);
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
    function metric(title, symbol, amount, available, complete, missing, note) { return `<div><h2>${icon(symbol)}${esc(title)}</h2><strong>${esc(money(amount))}</strong>${quality(available,complete,count,missing)}<p>${esc(note)}</p><p>${esc(day(data.range.start)+' sampai '+day(data.range.end))} WIB</p></div>`; }
    const gmv = stores.filter(s => s.gmv !== null), ads = stores.filter(s => s.ads.amount !== null), topups=stores.filter(s=>s.topups?.amount!==null && s.topups?.amount!==undefined);
    const gmvComplete=gmv.filter(s=>s.gmv_days===days).length, adsComplete=ads.filter(s=>s.ads.days===days).length;
    const topupComplete=topups.filter(s=>s.topups.complete).length, topupTimes=topups.map(s=>s.topups.updated_at).filter(Boolean).sort();
    const todayStores=gmv.filter(s=>s.gmv_coverage?.today_included).length;
    $('secondary').innerHTML = metric('Omset dibayar','receipt_long',gmv.length ? gmv.reduce((n,s)=>n+s.gmv,0) : null,gmv.length,gmvComplete,gmvGap(stores,days),`Pesanan sudah dibayar.${todayStores ? ' Termasuk omset hari ini'+(count>1 ? ` dari ${todayStores} toko` : '')+'. Angka hari ini masih bisa berubah.' : ''}`)
      + metric('Top up iklan berhasil','add_card',topups.length ? topups.reduce((n,s)=>n+s.topups.amount,0) : null,topups.length,topupComplete,topupGap(stores,data.range),`Uang untuk isi saldo, termasuk PPN. ${topups.reduce((n,s)=>n+s.topups.transactions,0)} transaksi. Pembaruan paling lama: ${stamp(topupTimes[0])}.`)
      + metric('Biaya iklan terpakai','campaign',ads.length ? ads.reduce((n,s)=>n+s.ads.amount,0) : null,ads.length,adsComplete,periodGap(stores,s=>s.ads.days,days),'Biaya pemakaian iklan selama periode pilihan.');
    const pending=stores.filter(s=>s.pending_states!==null);
    const totals=pending.length ? Object.fromEntries(states.map(([key])=>[key,pending.reduce((n,s)=>n+(s.pending_states[key] || 0),0)])) : null;
    const stateCounts=pending.length ? Object.fromEntries(states.map(([key])=>[key,pending.reduce((n,s)=>n+(s.pending_state_counts?.[key] || 0),0)])) : null;
    const statusTimes=pending.map(s=>s.pending_status_updated).filter(Boolean).sort();
    const hasUnknown=pending.some(unknownState), incomplete=pending.length<count;
    const detailTotal=pending.reduce((n,s)=>n+s.pending_detail,0), pendingTotal=pending.reduce((n,s)=>n+s.pending,0);
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
    if (reset) { for (const id of ['pending','released','wallet']) { $(id).textContent='Memuat…'; $(id+'-note').textContent='Memuat pilihan ini…'; $(id+'-quality').replaceChildren(); } $('wallet-restrictions').hidden=true; $('secondary').textContent='Memuat omset dan biaya iklan…'; $('pending-states').textContent='Memuat rincian Pending…'; $('store-list').replaceChildren(); lastStoresMarkup=''; }
    $('summary-panel').setAttribute('aria-busy','true');
    try {
      const data = await api('summary?' + query()); if (sequence !== summarySequence) return;
      const active = renderSummary(data);
      timer=setTimeout(refreshView,active ? 5000 : 30000);
    } catch (e) { if(sequence!==summarySequence)return; error(e.message); $('status').textContent='Pembaruan gagal. Angka yang masih tampil adalah data sebelumnya.'; if(reset) { for(const id of ['pending','released','wallet']) { $(id).textContent='Belum dapat dimuat'; $(id+'-note').textContent=''; } $('secondary').textContent='Omset dan biaya iklan belum dapat dimuat.'; $('pending-states').textContent='Rincian Pending belum dapat dimuat.'; } timer=setTimeout(refreshView,30000); }
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
      const result=await api((kind==='detail' ? 'details?' : 'catalog?')+query(kind==='detail' ? {category:$('category').value,state:$('category').value==='1' ? $('state').value : '',page:detailPage,search:detailSearch} : {page:costPage,search:costSearch}));
      if(sequence!==listSequence)return;
      if(!result.rows.length) target.innerHTML=`<p class="finance-empty">${kind==='detail' ? 'Belum ada rincian untuk pilihan ini. Jika saldo belum tersedia, klik Perbarui saldo Shopee. Jika memakai pencarian, coba nomor pesanan lain.' : 'Tidak ada produk yang cocok. Coba SKU lain atau sinkronkan produk di halaman Produk.'}</p>`;
      else if(kind==='detail') {
        const labels={...Object.fromEntries(states.map(([key,label])=>[key,label])),released:'Sudah dilepas'};
        target.innerHTML='<div class="finance-table-wrap"><table class="finance-table"><thead><tr><th>Pesanan / toko</th><th>Status / tanggal</th><th>Penghasilan / HPP</th><th>Penyesuaian</th></tr></thead><tbody>'+result.rows.map(r=>`<tr><td data-label="Pesanan / toko"><strong>${esc(r.order_sn)}</strong><span>${esc(r.shop_name)}</span><small>${esc(r.product_name)}</small></td><td data-label="Status / tanggal">${esc(labels[r.state])}${r.state_note ? '<small>'+esc(r.state_note)+'</small>' : ''}<span>${esc(r.released_at ? stamp(r.released_at) : r.estimated_at ? 'Perkiraan: '+stamp(r.estimated_at) : 'Tanggal pelepasan belum tersedia')}</span></td><td data-label="Penghasilan / HPP">${esc(money(r.income_amount))}<small>HPP: ${esc(r.cost?.amount === null ? (r.cost.items ? 'belum lengkap' : 'rincian produk belum tersedia') : money(r.cost?.amount))}</small></td><td data-label="Penyesuaian">${esc(money(r.adjustment_amount))}</td></tr>`).join('')+'</tbody></table></div>';
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
  if (params.get('category')==='2') $('category').value='2';
  if (states.some(([key])=>key===params.get('state'))) $('state').value=params.get('state');
  $('state-field').hidden=$('category').value!=='1';
  $('category').onchange=()=>{$('state-field').hidden=$('category').value!=='1';detailPage=1;url();loadList();};
  $('state').onchange=()=>{detailPage=1;url();loadList();};
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
