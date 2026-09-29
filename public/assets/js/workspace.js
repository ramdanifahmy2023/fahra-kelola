(() => {
  const config=window.shopdashWorkspace;if(!config)return;
  const scope=config.scope,allowed=config.keys||[],initial=config.query||{};
  let currentShop=Number(initial.shop_id??config.saved?.global?.shop_id??0),timer,changedAt=config.timestamp;
  const map={shop_id:'ads-shop',period:'ads-period',channel:'ads-channel',topup_shop:'ads-topup-shop',topup_period:'ads-topup-period',topup_start:'ads-topup-start',topup_end:'ads-topup-end'};
  const reportsMap={end_date:'report-end-date',sort:'report-sort',metric:'compare-metric'};
  if(scope==='chat'&&initial.status!==undefined){const el=document.getElementById('chat-status');if(el)el.value=initial.status;}
  const snapshot=()=>{
    const params=new URLSearchParams(location.search),values={};
    allowed.forEach(key=>{if(params.has(key))values[key]=params.get(key);});
    if(scope==='ads')for(const [key,id] of Object.entries(map)){const el=document.getElementById(id);if(el)values[key]=el.tagName==='SELECT'&&el.options.length<2&&initial[key]!==undefined?initial[key]:(el.value||((key==='shop_id'||key==='topup_shop')?'0':''));}
    const selected=document.getElementById('selectedShopId')||document.getElementById('report-shop')||document.getElementById('sync-shop');if(selected)values.shop_id=selected.value;
    if(config.saved?.[scope]?.shop_id!==undefined && values.shop_id===undefined && config.query?.shop_id!==undefined)values.shop_id=config.query.shop_id;
    if(scope==='reports')for(const [key,id]of Object.entries(reportsMap)){const el=document.getElementById(id);if(el)values[key]=el.value;}
    if(scope==='chat'&&document.getElementById('chat-status'))values.status=document.getElementById('chat-status').value;
    values.scroll_y=Math.max(0,Math.round(scrollY));return values;
  };
  async function save(){
    if(!allowed.length)return;
    const values=snapshot();if(values.shop_id!==undefined)currentShop=Number(values.shop_id);
    try{
      const response=await fetch(config.base+'/procWorkspace/save',{method:'POST',keepalive:true,headers:{'Content-Type':'application/json','X-CSRF-Token':config.csrf},body:JSON.stringify({scope,values,changed_at:changedAt})});
      if(!response.ok||response.redirected)throw new Error();
      document.getElementById('workspace-save-error').hidden=true;
    }catch{document.getElementById('workspace-save-error').hidden=false;}
  }
  const schedule=()=>{clearTimeout(timer);timer=setTimeout(save,500);};
  window.shopdashWorkspaceState={save,shop:()=>currentShop};
  document.addEventListener('change',event=>{
    const el=event.target;changedAt=config.timestamp+Math.floor(performance.now());if(el.id==='selectedShopId'||el.id==='report-shop'||el.id==='ads-shop'||el.id==='sync-shop')currentShop=Number(el.value);
    if(scope==='ads'&&Object.values(map).includes(el.id)){
      const url=new URL(location.href);for(const [key,id]of Object.entries(map)){const control=document.getElementById(id);if(control)url.searchParams.set(key,control.value||((key==='shop_id'||key==='topup_shop')?'0':''));}history.replaceState(null,'',url);schedule();
    }else if(scope==='reports'&&Object.values(reportsMap).includes(el.id)){const url=new URL(location.href);for(const [key,id]of Object.entries(reportsMap))url.searchParams.set(key,document.getElementById(id).value);history.replaceState(null,'',url);schedule();}
    else if(el.id==='chat-status'){const url=new URL(location.href);url.searchParams.set('status',el.value);history.replaceState(null,'',url);schedule();}
    else if(el.id==='report-shop') {const url=new URL(location.href);url.searchParams.set('shop_id',el.value);history.replaceState(null,'',url);schedule();}
  });
  document.addEventListener('finance:filters',()=>{changedAt=config.timestamp+Math.floor(performance.now());schedule();},true);
  document.addEventListener('click',event=>{
    const chatShop=event.target.closest('#chat-shops [data-shop-id]');if(chatShop){currentShop=Number(chatShop.dataset.shopId);changedAt=config.timestamp+Math.floor(performance.now());const url=new URL(location.href);url.searchParams.set('shop_id',String(currentShop));history.replaceState(null,'',url);schedule();}
    const link=event.target.closest('a');if(!link)return;
    const target=new URL(link.href,location.href);if(target.origin!==location.origin)return;
    const explicit=link.dataset.productShop;if(explicit!==undefined)currentShop=Number(explicit);
    if(link.closest('aside nav')&&config.singleShop.includes(target.pathname.split('/').pop())&&!target.searchParams.has('shop_id'))target.searchParams.set('shop_id',String(currentShop));
    if(link.closest('aside nav'))link.href=target.href;
    clearTimeout(timer);save();
  },true);
  document.getElementById('workspace-save-retry')?.addEventListener('click',save);
  window.addEventListener('scroll',schedule,{passive:true});
  window.addEventListener('pagehide',()=>{clearTimeout(timer);save();});
  const saved=config.saved?.[scope];
  if(!config.explicitNavigation&&!location.hash&&saved?.scroll_y>0&&Number(saved.shop_id||0)===Number(initial.shop_id||0)&&performance.getEntriesByType('navigation')[0]?.type!=='back_forward')setTimeout(()=>scrollTo({top:saved.scroll_y,behavior:'instant'}),100);
  setTimeout(save,800);
})();
