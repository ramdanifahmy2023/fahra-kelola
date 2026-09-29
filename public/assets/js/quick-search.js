(() => {
  const dialog=document.getElementById('quick-search'),trigger=document.getElementById('quick-search-button');if(!dialog||!trigger)return;
  const input=document.getElementById('quick-search-input'),shop=document.getElementById('quick-search-shop'),status=document.getElementById('quick-search-status'),results=document.getElementById('quick-search-results');
  let timer,controller,sequence=0,returnFocus;
  const labels={order:['Pesanan','receipt_long'],product:['Produk','inventory_2'],customer:['Pelanggan','group']};
  const text=(tag,value,cls='')=>{const el=document.createElement(tag);el.textContent=value;el.className=cls;return el;};
  const icon=value=>{const el=text('span',value,'material-symbols-outlined');el.setAttribute('aria-hidden','true');return el;};
  function open(){returnFocus=document.activeElement;const chosen=String(window.shopdashWorkspaceState?.shop()||0);shop.value=[...shop.options].some(o=>o.value===chosen)?chosen:'0';dialog.showModal();trigger.setAttribute('aria-expanded','true');input.focus();if(input.value.trim().length>=2)search();}
  function close(){controller?.abort();clearTimeout(timer);sequence++;dialog.close();trigger.setAttribute('aria-expanded','false');returnFocus?.focus();}
  async function search(){
    controller?.abort();const token=++sequence,q=input.value.trim();results.replaceChildren();
    if(q.length<2){status.textContent='Ketik minimal 2 karakter. Hasil memakai data tersimpan.';return;}
    controller=new AbortController();status.textContent='Mencari…';results.setAttribute('aria-busy','true');
    try{
      const response=await fetch(dialog.dataset.base+'/procSearch?q='+encodeURIComponent(q)+'&shop_id='+shop.value,{cache:'no-store',signal:controller.signal});
      if(response.redirected)throw new Error('Sesi berakhir. Muat ulang halaman.');const data=await response.json();if(!response.ok||data.status!=='success')throw new Error(data.message||'Pencarian gagal. Coba lagi.');if(token!==sequence)return;
      const rows=(data.results||[]).filter(row=>labels[row.type]&&typeof row.path==='string'&&/^\/panel(?:\/|\?)/.test(row.path));status.textContent=rows.length?rows.length+' hasil teratas · data tersimpan':'Belum ditemukan. Coba nomor pesanan lengkap, SKU, atau pilih Semua toko.';
      for(const row of rows){
        if(!labels[row.type]||!/^\/panel(?:\/|\?)/.test(row.path))continue;
        const a=document.createElement('a');a.href=dialog.dataset.base+row.path;a.className='quick-search-result';a.append(icon(labels[row.type][1]));const body=text('span','','quick-search-result-body');body.append(text('strong',row.title||'Data tanpa nama'),text('span',labels[row.type][0]+' · '+(row.detail||'')));
        const identity=text('span','','quick-search-identity');if(row.shop_logo&&/^https?:\/\//.test(row.shop_logo)){const img=document.createElement('img');img.src=row.shop_logo;img.alt='';img.width=24;img.height=24;img.addEventListener('error',()=>img.replaceWith(icon('storefront')),{once:true});identity.append(img);}else identity.append(icon('storefront'));identity.append(text('span',row.shop_name));body.append(identity);a.append(body);results.append(a);
      }
    }catch(e){if(e.name!=='AbortError'&&token===sequence)status.textContent=e.message;}
    finally{if(token===sequence)results.removeAttribute('aria-busy');}
  }
  trigger.addEventListener('click',open);document.getElementById('quick-search-close').addEventListener('click',close);
  dialog.addEventListener('cancel',e=>{e.preventDefault();close();});dialog.addEventListener('click',e=>{if(e.target===dialog){const box=dialog.getBoundingClientRect();if(e.clientX<box.left||e.clientX>box.right||e.clientY<box.top||e.clientY>box.bottom)close();}});
  input.addEventListener('input',()=>{clearTimeout(timer);controller?.abort();sequence++;timer=setTimeout(search,250);});shop.addEventListener('change',search);
  document.getElementById('quick-search-form').addEventListener('submit',e=>{e.preventDefault();clearTimeout(timer);search();});
  dialog.addEventListener('keydown',e=>{
    if(e.key==='Escape'){e.preventDefault();e.stopPropagation();close();return;}
    if(!['ArrowDown','ArrowUp'].includes(e.key))return;const links=[...results.querySelectorAll('a')];if(!links.length)return;e.preventDefault();const n=links.indexOf(document.activeElement);if(e.key==='ArrowDown')links[Math.min(links.length-1,n+1)].focus();else if(n>0)links[n-1].focus();else input.focus();
  });
  document.addEventListener('keydown',e=>{if((e.ctrlKey||e.metaKey)&&e.key.toLowerCase()==='k'){e.preventDefault();dialog.open?close():open();}});
})();
