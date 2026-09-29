(() => {
  const dialog=document.getElementById('order-detail-dialog');if(!dialog)return;
  let opener;
  document.addEventListener('click',async event=>{
    const button=event.target.closest('[data-order-details]');
    if(button){const card=document.getElementById('order-card-'+button.dataset.orderDetails);if(!card)return;opener=button;const copy=card.cloneNode(true);copy.removeAttribute('id');copy.querySelectorAll('[id]').forEach(el=>el.removeAttribute('id'));copy.querySelector('details').open=true;document.getElementById('order-detail-content').replaceChildren(copy);dialog.showModal();document.getElementById('order-detail-close').focus();}
    const tracking=event.target.closest('.order-copy-tracking');if(tracking){try{await navigator.clipboard.writeText(tracking.dataset.tracking);tracking.textContent='Resi tersalin';}catch{tracking.textContent='Gagal menyalin. Pilih teks resi.';}}
  });
  document.getElementById('order-detail-close').addEventListener('click',()=>dialog.close());dialog.addEventListener('close',()=>opener?.focus());
  dialog.addEventListener('click',e=>{if(e.target===dialog){const box=dialog.getBoundingClientRect();if(e.clientX<box.left||e.clientX>box.right||e.clientY<box.top||e.clientY>box.bottom)dialog.close();}});
  const id=new URLSearchParams(location.search).get('order_id');if(id&&matchMedia('(max-width: 639px)').matches)document.getElementById('order-card-'+id)?.scrollIntoView({block:'start'});
  document.querySelectorAll('.order-card-product img').forEach(img=>img.addEventListener('error',()=>{const fallback=document.createElement('span');fallback.className='order-card-image material-symbols-outlined';fallback.textContent='inventory_2';fallback.setAttribute('aria-hidden','true');img.replaceWith(fallback);},{once:true}));
})();
