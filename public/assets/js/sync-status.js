(() => {
  const root=document.getElementById('sync-page');if(!root)return;
  const $=id=>document.getElementById('sync-'+id),target=$('dashboard'),shop=$('shop');window.enhanceShopSelect?.(shop);
  const labels={orders:'Pesanan',chat:'Live chat',products:'Produk',promotions:'Voucher & Flash Sale',ads:'Iklan',ads_topups:'Topup iklan',customers:'Pelanggan',shops:'Koneksi toko',packages:'Paket',finance:'Keuangan'};
  const intervals=[30,60,180,300,600,900,1800,3600,43200,86400];let controller,busy=false,sequence=0;
  const text=(tag,value,cls='')=>{const el=document.createElement(tag);el.textContent=value;el.className=cls;return el;};
  const stamp=value=>value?new Date(value.replace(' ','T')+'Z').toLocaleString('id-ID',{timeZone:'Asia/Jakarta',day:'numeric',month:'short',hour:'2-digit',minute:'2-digit'})+' WIB':'Belum tersedia';
  const duration=n=>n>=86400?Math.ceil(n/86400)+' hari':n>=3600?Math.ceil(n/3600)+' jam':n>=60?Math.ceil(n/60)+' menit':n+' detik';
  function render(rows){
    const open=new Set([...target.querySelectorAll('details[open]')].map(el=>el.dataset.key));target.replaceChildren();
    if(!rows.length){target.append(text('p','Belum ada jadwal untuk toko pilihan.'));return;}
    for(const row of rows){
      const key=row.shop_id+':'+row.sync_type,p=row.presentation||{stage:row.job_status||'Belum mulai',message:'Status kemajuan belum tersedia.'};
      const card=text('article','','sync-status-card');card.dataset.tone=p.tone||'neutral';card.dataset.syncKey=key;
      const heading=text('div','','sync-status-heading'),logo=text('span','','sync-shop-logo');window.renderShopLogo?.(logo,row.shop_id);const title=text('div');title.append(text('p',row.shop_name||'Toko #'+row.shop_id),text('h2',labels[row.sync_type]||row.sync_type));heading.append(logo,title);card.append(heading,text('strong',p.stage,'sync-stage'),text('p',p.message));
      if(p.detail_total>0)card.append(text('p',`${p.detail_done} dari ${p.detail_total} detail selesai · ${p.detail_remaining} menunggu${p.detail_failed?' · '+p.detail_failed+' gagal':''}`));
      if(p.percent!==null&&p.percent!==undefined){const progress=document.createElement('progress');progress.max=100;progress.value=p.percent;progress.setAttribute('aria-label','Detail pesanan selesai '+p.percent+' persen');card.append(progress,text('p',p.percent+'% detail pesanan selesai'));}
      else if(row.sync_type==='orders'&&p.pages_done)card.append(text('p',p.pages_done+' halaman ditemukan. Total detail masih dihitung.'));
      if(p.eta_seconds)card.append(text('p','Perkiraan '+duration(p.eta_seconds)+' lagi · berdasarkan laju 5 menit terakhir','sync-estimate'));
      if(p.retry_at)card.append(text('p','Percobaan berikutnya: '+stamp(p.retry_at)));
      const dates=text('dl','','sync-status-dates');for(const [label,value]of [['Terakhir berhasil',row.last_success_at],['Jadwal berikutnya',row.enabled?row.next_run_at:null]]){const group=text('div');group.append(text('dt',label),text('dd',stamp(value)));dates.append(group);}card.append(dates);
      if(p.action_path){const a=text('a',p.action_label,'btn');a.href=root.dataset.base+p.action_path;card.append(a);}
      else if(p.can_retry){const retry=text('button',p.action_label,'btn');retry.type='button';retry.addEventListener('click',()=>mutate('retry',row));card.append(retry);}
      const details=document.createElement('details');details.dataset.key=key;details.open=open.has(key);details.append(text('summary','Atur jadwal'));const form=document.createElement('form');form.className='sync-schedule-form';
      const label=text('label','Interval pembaruan'),select=document.createElement('select');select.className='select';select.setAttribute('aria-label','Interval '+(labels[row.sync_type]||row.sync_type)+' '+row.shop_name);for(const n of intervals){const o=new Option(duration(n),n);o.selected=n===Number(row.interval_seconds);select.add(o);}label.append(select);
      const enabledLabel=text('label','','sync-enable'),enabled=document.createElement('input');enabled.type='checkbox';enabled.className='checkbox';enabled.checked=!!Number(row.enabled);enabledLabel.append(enabled,text('span','Jadwal otomatis aktif'));const save=text('button','Simpan jadwal','btn');save.type='submit';form.append(label,enabledLabel,save);form.addEventListener('submit',e=>{e.preventDefault();mutate('configure',{...row,interval_seconds:select.value,enabled:enabled.checked?1:0});});details.append(form);card.append(details);target.append(card);
    }
  }
  async function load(automatic=false){
    if(busy||(automatic&&target.contains(document.activeElement)))return;
    controller?.abort();controller=new AbortController();const n=++sequence;$('reload').disabled=true;target.setAttribute('aria-busy','true');
    try{const response=await fetch(root.dataset.base+'/procsync/status?shop_id='+shop.value,{cache:'no-store',headers:{'X-Requested-With':'XMLHttpRequest'},signal:controller.signal});if(response.redirected)throw new Error('Sesi berakhir. Muat ulang halaman.');const data=await response.json();if(!response.ok||data.status!=='success')throw new Error(data.message||'Status gagal dimuat.');if(n!==sequence)return;render(data.schedules||[]);$('error').hidden=true;$('refreshed').textContent='Diperiksa '+new Date().toLocaleTimeString('id-ID',{timeZone:'Asia/Jakarta'})+' WIB';}
    catch(e){if(e.name!=='AbortError'&&n===sequence){$('error').textContent=e.message+' Data sebelumnya tetap ditampilkan.';$('error').hidden=false;}}
    finally{if(n===sequence){target.removeAttribute('aria-busy');$('reload').disabled=false;}}
  }
  async function mutate(action,row){
    if(busy)return;busy=true;controller?.abort();sequence++;target.querySelectorAll('button,select,input').forEach(el=>el.disabled=true);$('reload').disabled=true;shop.disabled=true;let succeeded=false;
    try{const form=new FormData();for(const key of ['shop_id','sync_type','interval_seconds','enabled'])if(row[key]!==undefined)form.append(key,row[key]);const response=await fetch(root.dataset.base+'/procsync/'+action,{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest','X-CSRF-Token':root.dataset.csrf},body:form});const data=await response.json();if(!response.ok||!['accepted','success'].includes(data.status))throw new Error(data.message||'Perubahan gagal disimpan.');succeeded=true;$('refreshed').textContent=action==='retry'?'Sinkronisasi masuk antrean.':'Jadwal tersimpan.';}
    catch(e){$('error').textContent=e.message;$('error').hidden=false;}
    finally{busy=false;target.querySelectorAll('button,select,input').forEach(el=>el.disabled=false);$('reload').disabled=false;shop.disabled=false;}if(succeeded)await load();
  }
  $('reload').addEventListener('click',()=>load());shop.addEventListener('change',()=>{const url=new URL(location.href);url.searchParams.set('shop_id',shop.value);history.replaceState(null,'',url);window.shopdashWorkspaceState?.save();load();});
  load();setInterval(()=>{if(!document.hidden)load(true);},15000);
})();
