(() => {
  const root=document.getElementById('finance-page'), ui=window.shopdashFinance;
  if(!ui || root.dataset.dashboard!=='true')return;
  const {esc,money,stamp,day,icon}=ui, $=id=>document.getElementById('dashboard-'+id);
  const number=value=>value===null || value===undefined ? 'Belum tersedia' : new Intl.NumberFormat('id-ID').format(value);
  let filters=ui.filters, operationSequence=0, liveSequence=0, liveTimer, operationTimer, liveRequest, operationRequest, lastOperationsMarkup='', lastLiveStamp='';
  const shopLabel=()=>!filters.shops ? 'Semua toko' : filters.shops.split(',').map(id=>Array.from(document.getElementById('finance-shops').options).find(o=>o.value===id)?.text || 'Toko tidak ditemukan').join(', ');
  function logos(container) {container.querySelectorAll('[data-logo]').forEach(el=>window.renderShopLogo(el,el.dataset.logo));}
  const shopLogo=id=>`<span class="shop-picker-logo" data-logo="${Number(id)}"></span>`;
  const productLink=p=>root.dataset.base+'/panel/products?'+new URLSearchParams({shop_id:p.shop_id,stock:'critical',highlight:p.id});
  async function request(path,signal) {
    const response=await fetch(root.dataset.base+path,{headers:{'X-Requested-With':'XMLHttpRequest'},cache:'no-store',signal});
    let data; try{data=await response.json();}catch(_){throw new Error('Respons belum dapat dibaca. Muat ulang jika sesi berakhir.');}
    if(!response.ok || data.status!=='success')throw new Error(data.message || 'Data belum dapat dimuat.');
    return data;
  }
  async function loadOperations(reset=false) {
    clearTimeout(operationTimer); operationRequest?.abort(); operationRequest=new AbortController(); const sequence=++operationSequence;
    $('operations-error').hidden=true; $('operations').setAttribute('aria-busy','true');
    if(reset){$('operations-content').replaceChildren();lastOperationsMarkup='';$('operations-status').textContent='Memuat kondisi toko pilihan…';}
    try {
      const data=await request('/procDashboard/overview?'+new URLSearchParams(filters),operationRequest.signal); if(sequence!==operationSequence)return;
      const s=data.summary, selected=shopLabel(), period=day(data.range.start)+' sampai '+day(data.range.end)+' WIB';
      $('operations-status').textContent=selected+' · pesanan dibuat '+period;
      const stat=(label,value,note,symbol)=>`<div><dt>${icon(symbol)}${esc(label)}</dt><dd>${esc(value)}</dd><small>${esc(note)}</small></div>`;
      const notice=Number(s.undated_orders)>0 ? `<p class="finance-notice">${number(s.undated_orders)} pesanan belum punya tanggal yang bisa dipastikan, sehingga belum masuk hitungan periode ini.</p>` : '';
      const missing=Number(s.pending_order_details)>0 ? `<p class="finance-notice">${number(s.pending_order_details)} pesanan dalam periode ini masih menunggu rincian lengkap.</p>` : '';
      const connections=data.shops.map(shop=>`<div class="dashboard-shop-row">${shopLogo(shop.id)}<div><strong>${esc(shop.name)}</strong><small>${shop.sync_status==='connected' ? 'Terhubung' : 'Perlu periksa koneksi'} · ${esc(stamp(shop.last_successful_sync_at))}</small></div></div>`).join('');
      const products=data.low_stock.map(p=>`<a class="dashboard-stock-row" href="${esc(productLink(p))}">${shopLogo(p.shop_id)}<div><strong>${esc(p.name)}</strong><small>${esc(p.shop_name)}</small></div><span class="finance-quality is-partial">${Number(p.total_stock)===0 ? 'Habis' : esc(number(p.total_stock))+' tersisa'}</span></a>`).join('') || '<p class="finance-empty">Tidak ada produk aktif dengan stok di bawah 15 pada toko pilihan.</p>';
      const recent=data.recent_orders.map(o=>`<tr><td data-label="Pesanan">${esc(o.order_sn || o.id)}<small>${esc(o.shop_name)}</small></td><td data-label="Status">${esc(o.status_type || 'Belum tersedia')}</td><td data-label="Dibuat (WIB)">${esc(stamp(o.created_at))}</td><td data-label="Nilai pesanan">${esc(money(o.total_price))}</td></tr>`).join('');
      const markup=`<dl class="dashboard-operation-stats">${stat('Toko terhubung',data.shops.filter(s=>s.sync_status==='connected').length+'/'+data.shops.length,'Posisi terbaru','storefront')}${stat('Produk tersimpan',number(s.total_products),'Posisi terbaru','inventory_2')}${stat('Pelanggan tersimpan',number(s.total_customers),'Dari toko pilihan, seluruh waktu','group')}${stat('Pesanan dibuat',number(s.total_orders),number(s.completed_orders)+' kini selesai · periode pilihan','receipt_long')}</dl>${notice}${missing}<details class="finance-explanation"><summary>Nilai pesanan yang kini selesai</summary><p>${esc(money(s.completed_order_value))}${Number(s.completed_missing_value)>0 ? ' · '+number(s.completed_missing_value)+' pesanan belum punya nominal' : ''}</p><p>Nilai pesanan dibuat selama periode pilihan yang statusnya sekarang selesai. Terpisah dari omset dibayar dan saldo dilepas Shopee.</p></details><div class="dashboard-operations-columns"><section><h3>${icon('inventory_2')}Stok perlu diperiksa</h3><p>${number(s.stock_out_count)} produk habis · ${number(s.stock_low_count)} produk tersisa 1 sampai 14</p>${products}</section><section><h3>${icon('storefront')}Koneksi toko</h3>${connections || '<p>Belum ada toko.</p>'}</section></div><section class="dashboard-recent"><h3>Pesanan terbaru dalam periode</h3>${recent ? `<table class="finance-table"><thead><tr><th>Pesanan / toko</th><th>Status</th><th>Dibuat (WIB)</th><th>Nilai pesanan</th></tr></thead><tbody>${recent}</tbody></table>` : '<p class="finance-empty">Belum ada pesanan tersimpan untuk periode dan toko pilihan.</p>'}</section>`;
      if(markup!==lastOperationsMarkup) {
        const container=$('operations-content'), open=container.querySelector('details')?.open;
        const focused=container.contains(document.activeElement) ? document.activeElement : null, href=focused?.getAttribute('href');
        container.innerHTML=markup;lastOperationsMarkup=markup;logos(container);
        if(open)container.querySelector('details').open=true;
        if(focused?.tagName==='SUMMARY')container.querySelector('summary')?.focus({preventScroll:true});
        if(href)Array.from(container.querySelectorAll('a')).find(a=>a.getAttribute('href')===href)?.focus({preventScroll:true});
      }
    }catch(e){if(sequence!==operationSequence || e.name==='AbortError')return;$('operations-error').hidden=false;$('operations-error').querySelector('p').textContent=e.message;$('operations-status').textContent='Pembaruan gagal. Data sebelumnya, jika ada, tetap tampil.';}
    finally{if(sequence===operationSequence){$('operations').setAttribute('aria-busy','false');operationTimer=setTimeout(()=>{if(!document.hidden)loadOperations();},30000);}}
  }
  function renderHours(metrics,count) {
    const values=metrics.sales_hourly || [], counts=metrics.hourly_coverage || [];
    if(!values.length){$('hourly').textContent='Data per jam belum tersedia.';$('hour-table').replaceChildren();$('hour-value').textContent='';return;}
    const max=Math.max(1,...values.filter(v=>v!==null).map(Number));
    const focused=$('hourly').contains(document.activeElement), previous=$('hourly').querySelector('select')?.value || String(values.length-1);
    const description=(value,hour)=>`${String(hour).padStart(2,'0')}.00 WIB: ${money(value)} · ${counts[hour] || 0}/${count} toko`;
    const width=640, step=width/24;
    const peak=Math.max(0,...values.filter(v=>v!==null).map(Number));
    $('hourly').innerHTML=`<p class="finance-meta">${peak ? 'Nilai tertinggi: '+esc(money(peak)) : 'Belum ada nilai penjualan positif pada jam yang tersedia.'}</p><svg class="dashboard-chart" viewBox="0 0 660 140" role="img" aria-label="Penjualan terkonfirmasi per jam. Nominal lengkap tersedia pada pilihan jam dan tabel."><line x1="10" y1="132" x2="650" y2="132" class="dashboard-chart-axis"/>${values.map((v,h)=>v===null ? '' : `<rect x="${10+h*step+2}" y="${132-Math.max(0,Number(v))/max*116}" width="${step-4}" height="${Math.max(0,Number(v))/max*116}" rx="2" class="dashboard-chart-bar"/>`).join('')}</svg><div class="dashboard-chart-ticks" aria-hidden="true">${[0,6,12,18,23].map(h=>`<span>${String(h).padStart(2,'0')}</span>`).join('')}</div><label for="dashboard-hour-select">Lihat nominal jam (WIB)</label><select id="dashboard-hour-select" class="select">${values.map((v,h)=>`<option value="${h}">${String(h).padStart(2,'0')}.00 WIB</option>`).join('')}</select>`;
    const select=$('hourly').querySelector('select');select.value=Number(previous)<values.length ? previous : String(values.length-1);
    const show=()=>{$('hour-value').textContent=description(values[Number(select.value)],Number(select.value));};
    select.addEventListener('change',show);show();if(focused)select.focus({preventScroll:true});
    $('hour-table').innerHTML='<table class="dashboard-hours-table"><thead><tr><th>Jam WIB</th><th>Penjualan terkonfirmasi</th><th>Data toko</th></tr></thead><tbody>'+values.map((v,h)=>`<tr><td>${String(h).padStart(2,'0')}.00</td><td>${esc(money(v))}</td><td>${counts[h] || 0}/${count}</td></tr>`).join('')+'</tbody></table>';
  }
  async function loadLive(reset=false) {
    clearTimeout(liveTimer); liveRequest?.abort(); liveRequest=new AbortController(); const sequence=++liveSequence;
    $('live-refresh').disabled=true; $('live-error').hidden=true; $('activity').setAttribute('aria-busy','true');
    $('live-scope').textContent='Hari ini · '+new Date().toLocaleDateString('id-ID',{timeZone:'Asia/Jakarta',dateStyle:'medium'})+' WIB · '+shopLabel()+'. Tidak mengikuti periode laporan.';
    if(reset){lastLiveStamp='';$('live-metrics').replaceChildren();$('top-products').replaceChildren();$('hourly').replaceChildren();$('hour-table').replaceChildren();$('hour-value').textContent='';$('live-status').textContent='Memuat aktivitas toko pilihan…';}
    try {
      const query=new URLSearchParams(); if(filters.shops)filters.shops.split(',').forEach(id=>query.append('shop_ids[]',id));
      const data=await request('/procRealtime/metrics?'+query,liveRequest.signal); if(sequence!==liveSequence)return;
      const m=data.metrics,count=data.selected_shop_count;
      const fields=[['uv','Pengunjung','person'],['pv','Tampilan','visibility'],['product_clicks','Klik produk','ads_click'],['orders','Pesanan terkonfirmasi','receipt_long'],['buyers','Pembeli','group'],['sales','Penjualan terkonfirmasi','payments']];
      $('live-metrics').innerHTML=fields.map(([key,title,symbol])=>`<div><dt>${icon(symbol)}${title}</dt><dd>${esc(key==='sales' ? money(m.key_metrics[key]) : number(m.key_metrics[key]))}</dd><small class="${m.metric_coverage[key]<count ? 'finance-incomplete' : ''}">${m.metric_coverage[key]<count ? 'Sementara · ' : ''}${m.metric_coverage[key]}/${count} toko</small></div>`).join('');
      lastLiveStamp=m.time ? 'Pembaruan paling lama: '+new Date(m.time*1000).toLocaleString('id-ID',{timeZone:'Asia/Jakarta',dateStyle:'medium',timeStyle:'short'})+' WIB' : 'Waktu data dari Shopee belum tersedia.';
      $('live-status').textContent=lastLiveStamp;
      if(data.failures.length){$('live-error').hidden=false;$('live-error').textContent='Sebagian toko belum tersedia: '+data.failures.map(s=>s.shop_name).join(', ')+'. Total aktivitas masih sementara.';}
      $('top-products').innerHTML=`<p class="finance-meta">Daftar dari ${m.product_shop_count}/${count} toko.</p>`+(m.top_sales_items.length ? m.top_sales_items.map(p=>`<div class="dashboard-top-product">${shopLogo(p.shop_id)}<div><strong>${esc(p.item_name)}</strong><small>${esc(p.shop_name)}</small></div><span>${esc(money(p.sales))}</span></div>`).join('') : `<p class="finance-empty">${m.product_shop_count===count ? 'Belum ada produk terjual pada laporan ini.' : 'Daftar produk belum lengkap.'}</p>`);
      logos($('top-products'));renderHours(m,count);
    }catch(e){if(sequence!==liveSequence || e.name==='AbortError')return;$('live-error').hidden=false;$('live-error').textContent=e.message;$('live-status').textContent=lastLiveStamp ? lastLiveStamp+'. Pembaruan gagal, menampilkan data sebelumnya.' : 'Aktivitas belum tersedia untuk pilihan ini.';}
    finally{if(sequence===liveSequence){$('activity').setAttribute('aria-busy','false');$('live-refresh').disabled=false;liveTimer=setTimeout(()=>{if(!document.hidden)loadLive();},30000);}}
  }
  root.addEventListener('finance:filters',event=>{const changed=filters.shops!==event.detail.shops;filters={...event.detail};delete filters.label;loadOperations(true);if(changed)loadLive(true);});
  $('operations-retry').onclick=()=>loadOperations(true);$('live-refresh').onclick=()=>loadLive();
  document.addEventListener('visibilitychange',()=>{if(!document.hidden){loadOperations();loadLive();}});
  loadOperations(true);loadLive(true);
})();
