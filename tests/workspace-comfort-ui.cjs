const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),{execFileSync}=require('node:child_process');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');const root=path.resolve(__dirname,'..'),base=process.env.PANEL_TEST_URL||'http://127.0.0.1:8131';
const php=code=>execFileSync('php',['-r',code],{cwd:root,encoding:'utf8'}).trim();
const uid=1000000000+Math.floor(Math.random()*900000000),sid=php(`chdir('public');require '../app/init.php';$d=new Database();$d->query('SELECT id FROM accounts WHERE id=${uid}');if($d->single())exit(1);session_id(bin2hex(random_bytes(24)));session_start();$_SESSION['auth_user']=['id'=>${uid},'name'=>'Test workspace','email'=>'fixture@example.invalid'];echo session_id();session_write_close();`);
const fixture=JSON.parse(php("chdir('public');require '../app/init.php';$d=new Database();$d->query('SELECT id FROM shops ORDER BY id LIMIT 2');$s=$d->getAll();$d->query('SELECT id,shop_id FROM orders ORDER BY id DESC LIMIT 1');$o=$d->single();$d->query('SELECT id,shop_id FROM products ORDER BY id DESC LIMIT 1');$p=$d->single();$d->query('SELECT c.id,cs.shop_id FROM customers c JOIN customer_shops cs ON cs.customer_id=c.id LIMIT 1');$c=$d->single();echo json_encode(['shops'=>$s,'order'=>$o,'product'=>$p,'customer'=>$c]);"));
const first=Number(fixture.shops[0].id),second=Number(fixture.shops[1].id);let browser;
(async()=>{try{
 browser=await chromium.launch();const ctx=await browser.newContext({viewport:{width:1600,height:1008}});await ctx.addCookies([{name:'PHPSESSID',value:sid,url:base}]);
 let saveFail=false,saveCount=0;await ctx.route('**/procWorkspace/**',r=>{saveCount++;return r.fulfill({status:saveFail?500:200,json:{status:saveFail?'error':'success'}});});
 let syncFail=false,syncWriteFail=false,syncWrites=0;
 const syncRows=[
 {shop_id:first,shop_name:'Toko fixture dengan nama panjang',sync_type:'orders',enabled:1,interval_seconds:180,job_status:'running',last_success_at:'2026-09-29 10:00:00',next_run_at:'2026-09-30 01:00:00',presentation:{stage:'Memperbarui detail pesanan',tone:'active',message:'Detail diperbarui dari antrean.',detail_total:100,detail_done:20,detail_remaining:80,detail_failed:0,percent:20,eta_seconds:960}},
 {shop_id:second,shop_name:'Toko kedua',sync_type:'products',enabled:1,interval_seconds:900,last_success_at:null,presentation:{stage:'Perlu diperiksa',tone:'error',message:'Pembaruan belum berhasil.',percent:null,can_retry:true,action_label:'Coba sinkronkan lagi'}},
 {shop_id:first,shop_name:'Toko fixture dengan nama panjang',sync_type:'chat',enabled:0,interval_seconds:300,presentation:{stage:'Jadwal dijeda',tone:'neutral',message:'Jadwal otomatis dijeda.',percent:null}}
 ];
 await ctx.route('**/procsync/**',async r=>{if(r.request().method()==='POST'){syncWrites++;if(syncWriteFail)return r.fulfill({status:500,json:{status:'error',message:'Jadwal fixture gagal disimpan.'}});assert.ok(r.request().headers()['x-csrf-token']);return r.fulfill({json:{status:r.request().url().endsWith('retry')?'accepted':'success'}});}return r.fulfill({status:syncFail?500:200,json:syncFail?{status:'error',message:'Status fixture gagal dimuat.'}:{status:'success',schedules:syncRows}});});
 await ctx.route('**/procorders/**',r=>r.fulfill({json:{status:'success',orders:[],queue:[]}}));await ctx.route('**/procproducts/**',r=>r.fulfill({json:{status:'success',is_empty:false,products:[]}}));
 let notices=Array.from({length:24},(_,i)=>({id:i+1,revision:1,shop_id:first,shop_name:'Toko fixture panjang untuk pengujian',shop_logo:i===0?base+'/missing-fixture-logo':'' ,type:i<18?'low_stock':'shipping_deadline',severity:i%2?'urgent':'warning',unread:true,title:i<18?'Stok kritis':'Batas kirim',message:'Data fixture untuk pengujian',path:'/panel/products?shop_id='+first,action_label:'Periksa produk',icon:i<18?'inventory_2':'local_shipping',source_at:'2026-09-29 10:00:00'}));let reminderFail=false;
 await ctx.route('**/procnotifications/**',async r=>{
  const url=new URL(r.request().url());if(r.request().method()==='POST'){
   assert.ok(r.request().headers()['x-csrf-token']);if(reminderFail)return r.fulfill({status:500,json:{status:'error',message:'Pengingat fixture gagal.'}});
   for(const item of r.request().postDataJSON().items){const n=notices.find(n=>n.id===item.id&&n.revision===item.revision);if(n){if(url.pathname.endsWith('snooze'))n.snoozed=true;else n.unread=false;}}return r.fulfill({json:{status:'success'}});
  }
  const visible=notices.filter(n=>!n.snoozed),summary={total:visible.length,unread:visible.filter(n=>n.unread).length,unread_urgent:visible.filter(n=>n.unread&&n.severity==='urgent').length};
  let selected=visible.filter(n=>(url.searchParams.get('unread_only')==='0'||n.unread)&&(url.searchParams.get('urgent_only')!=='1'||n.severity==='urgent'));const offset=Number(url.searchParams.get('offset')||0);
  const data={status:'success',summary,unread_count:summary.unread,evaluation_available:true};
  if(url.searchParams.get('grouped')==='1'){
   const groups=[];for(const n of selected){let g=groups.find(g=>g.type===n.type);if(!g){g={shop_id:n.shop_id,type:n.type,shop_name:n.shop_name,shop_logo:n.shop_logo,total:0,unread:0,urgent:0};groups.push(g);}g.total++;if(n.unread)g.unread++;if(n.severity==='urgent')g.urgent++;}
   Object.assign(data,{groups:groups.slice(offset,offset+10),group_count:groups.length,has_more:groups.length>offset+10});
  }else{if(url.searchParams.has('type'))selected=selected.filter(n=>n.type===url.searchParams.get('type'));Object.assign(data,{notifications:selected.slice(offset,offset+10),filtered_count:selected.length,has_more:selected.length>offset+10});}
  return r.fulfill({json:data});
 });
 await ctx.route('**/procSearch?**',async r=>{
  const q=new URL(r.request().url()).searchParams.get('q');if(q==='fail')return r.fulfill({status:500,json:{status:'error',message:'Pencarian fixture gagal. Coba lagi.'}});
  if(q==='none')return r.fulfill({json:{status:'success',results:[]}});
  return r.fulfill({json:{status:'success',results:[{type:'order',title:'Pesanan <script> fixture',detail:q,shop_name:'Toko fixture panjang untuk pengujian',shop_logo:base+'/missing-search-logo',path:'/panel/orders?shop_id='+fixture.order.shop_id+'&order_id='+fixture.order.id},{type:'product',title:'Produk fixture dengan nama sangat panjang agar pengujian responsif bermakna',detail:'SKU-fixture',shop_name:'Toko fixture',path:'/panel/products?shop_id='+fixture.product.shop_id+'&highlight='+fixture.product.id},{type:'customer',title:'Pelanggan fixture',detail:'Riwayat pesanan',shop_name:'Toko fixture',path:'/panel/customers?shop_id='+fixture.customer.shop_id+'&customer_id='+fixture.customer.id},{type:'order',title:'Unsafe',path:'https://example.com'}]}});
 });
 const page=await ctx.newPage(),errors=[];page.on('pageerror',e=>errors.push(e.message));
 await page.goto(base+'/panel/orders?shop_id='+first);await page.waitForFunction(()=>window.shopdashWorkspaceState);
 const csrf=await page.locator('#notification-menu').getAttribute('data-csrf');
 assert.equal((await ctx.request.post(base+'/procWorkspace/save',{data:{scope:'orders',values:{}}})).status(),403);
 assert.equal((await ctx.request.get(base+'/procWorkspace/save')).status(),405);
 assert.equal((await ctx.request.post(base+'/procWorkspace/save',{headers:{'X-CSRF-Token':csrf},data:{scope:'unsafe',values:{}}})).status(),422);
 const save=await ctx.request.post(base+'/procWorkspace/save',{headers:{'X-CSRF-Token':csrf},data:{scope:'orders',values:{shop_id:second,page:2,limit:20,startDate:'2026-08-01',endDate:'2026-08-31'}}});assert.equal(save.status(),200);
 await page.goto(base+'/panel/orders');assert.equal(await page.locator('#selectedShopId').inputValue(),String(second));assert.ok(page.url().includes('startDate=2026-08-01'));
 await page.goto(base+'/panel/products');assert.equal(await page.locator('#selectedShopId').inputValue(),String(second));
 await page.goto(base+'/panel/orders?shop_id='+first+'&order_id='+fixture.order.id); // Explicit scope must not be overwritten by preferences.
 assert.equal(await page.locator('#selectedShopId').inputValue(),String(first));
 await page.goto(base+'/panel/orders?shop_id='+fixture.order.shop_id+'&order_id='+fixture.order.id);
 assert.equal(await page.locator('[data-order-details]').count(),1);await page.locator('[data-order-details]').click();assert.ok(await page.locator('#order-detail-dialog').isVisible());assert.ok(await page.locator('#order-detail-content details').evaluate(el=>el.open));await page.keyboard.press('Escape');assert.equal(await page.locator('[data-order-details]').evaluate(el=>el===document.activeElement),true);
 saveFail=true;await page.evaluate(()=>window.shopdashWorkspaceState.save());await page.locator('#workspace-save-error').waitFor({state:'visible'});saveFail=false;await page.click('#workspace-save-retry');await page.locator('#workspace-save-error').waitFor({state:'hidden'});
 await page.keyboard.press('Control+k');await page.locator('#quick-search-input').fill('order');await page.locator('.quick-search-result').first().waitFor();assert.equal(await page.locator('.quick-search-result').count(),3);assert.equal(await page.locator('#quick-search-results script').count(),0);
 await page.locator('.quick-search-identity img').waitFor({state:'detached'});await page.locator('#quick-search-input').focus();await page.keyboard.press('ArrowDown');assert.ok(await page.locator('.quick-search-result').first().evaluate(el=>el===document.activeElement));
 let minimumContrast=Infinity;const out=path.join(root,'tmp/workspace-comfort-ui');fs.mkdirSync(out,{recursive:true});
 async function shots(name,selector){await page.addStyleTag({content:'*,*::before,*::after{transition:none!important;animation:none!important}'});for(const width of [320,500,999,1600])for(const theme of ['light','dark']){await page.setViewportSize({width,height:1008});await page.evaluate(t=>window.shopdashTheme.setMode(t),theme);assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'Page horizontal overflow: '+name+' '+width);const box=await page.locator(selector.split(',').map(s=>s.trim()+':visible').join(',')).first().boundingBox();assert.ok(box&&box.x>=-1&&box.x+box.width<=width+1);const colors=await page.locator(name==='orders'?'#order-mobile-list .order-mobile-card:visible':selector).evaluateAll(roots=>{
 const canvas=document.createElement('canvas');canvas.width=canvas.height=1;const ctx=canvas.getContext('2d',{willReadFrequently:true});
 const rgb=value=>{ctx.clearRect(0,0,1,1);ctx.fillStyle=value;ctx.fillRect(0,0,1,1);return [...ctx.getImageData(0,0,1,1).data];};
 const blend=(a,b)=>a.slice(0,3).map((v,i)=>v*a[3]/255+b[i]*(1-a[3]/255));
 const lum=a=>a.map(v=>{v/=255;return v<=.04045?v/12.92:((v+.055)/1.055)**2.4;}).reduce((sum,v,i)=>sum+v*[.2126,.7152,.0722][i],0);
 return roots.flatMap(root=>[...root.querySelectorAll('h2,h3,p,strong,span,a,button,label,summary,dt,dd')].filter(el=>el.getClientRects().length&&!el.disabled&&!el.classList.contains('material-symbols-outlined')&&[...el.childNodes].some(n=>n.nodeType===3&&n.textContent.trim())).map(el=>{
  let bg=[255,255,255];const parents=[];for(let n=el;n;n=n.parentElement)parents.unshift(n);for(const n of parents)bg=blend(rgb(getComputedStyle(n).backgroundColor),bg);const a=lum(blend(rgb(getComputedStyle(el).color),bg)),b=lum(bg);return (Math.max(a,b)+.05)/(Math.min(a,b)+.05);
 }));
});minimumContrast=Math.min(minimumContrast,...colors);assert.ok(colors.every(v=>v>=4.5),'Changed text contrast: '+name);
 await page.screenshot({path:path.join(out,name+'-'+width+'-'+theme+'.png')});}}
 await shots('search','#quick-search');await page.locator('#quick-search-input').fill('none');await page.getByText(/Belum ditemukan/).waitFor();await page.locator('#quick-search-input').fill('fail');await page.getByText('Pencarian fixture gagal. Coba lagi.',{exact:true}).waitFor();await page.locator('#quick-search-input').fill('product');await page.locator('.quick-search-result').first().waitFor();await page.keyboard.press('Escape');
 await shots('orders','#order-mobile-list, .order-desktop-table'); // Pick whichever presentation is visible below.
 await page.setViewportSize({width:320,height:1008});assert.ok(await page.locator('.order-desktop-table').isHidden());await page.locator('#order-mobile-list .order-mobile-card summary').click();assert.ok(await page.locator('#order-mobile-list .order-mobile-card details').evaluate(el=>el.open));
 await page.setViewportSize({width:1600,height:1008});await page.click('#notification-button');await page.locator('.notification-group').first().waitFor();assert.equal(await page.locator('.notification-group').count(),2);assert.ok(await page.locator('#notification-mark-all').isDisabled());await page.locator('.notification-group button').first().click();await page.locator('.notification-later').first().waitFor();assert.equal(await page.locator('.notification-item').count(),10);
 reminderFail=true;await page.locator('.notification-later').first().click();await page.getByText('Pengingat fixture gagal.',{exact:true}).waitFor();reminderFail=false;await page.locator('.notification-later').first().click();await page.waitForFunction(()=>document.querySelector('#notification-badge').textContent==='23');
 await page.click('#notification-back');await page.locator('.notification-group').first().waitFor();await page.locator('#notification-urgent').check();await page.waitForFunction(()=>document.querySelector('.notification-group h3').textContent.startsWith('9 '));
 await shots('notifications','#notification-panel');await page.keyboard.press('Escape');
 await page.goto(base+'/panel/sync?shop_id='+first);await page.locator('.sync-status-card').first().waitFor();assert.equal(await page.locator('#sync-page progress').count(),1);assert.ok((await page.locator('#sync-dashboard').innerText()).includes('20 dari 100'));
 await page.getByRole('button',{name:'Coba sinkronkan lagi'}).click();assert.equal(syncWrites,1);
 await page.locator('.sync-status-card summary').first().click();await page.locator('.sync-schedule-form').first().getByRole('button',{name:'Simpan jadwal'}).click();assert.equal(syncWrites,2);
 syncWriteFail=true;await page.locator('.sync-schedule-form').first().getByRole('button',{name:'Simpan jadwal'}).click();await page.locator('#sync-error').waitFor({state:'visible'});assert.ok((await page.locator('#sync-error').innerText()).includes('gagal disimpan'));assert.equal(await page.locator('.sync-status-card').count(),3);syncWriteFail=false;await page.click('#sync-reload');await page.locator('#sync-error').waitFor({state:'hidden'});
 syncFail=true;await page.click('#sync-reload');await page.locator('#sync-error').waitFor({state:'visible'});assert.equal(await page.locator('.sync-status-card').count(),3);syncFail=false;await page.click('#sync-reload');await page.locator('#sync-error').waitFor({state:'hidden'});
 await shots('sync','#sync-page');
 const targets=await page.locator('#sync-page button:visible,#sync-page select:visible,#sync-page summary:visible').evaluateAll(nodes=>nodes.map(n=>n.getBoundingClientRect().height));assert.ok(targets.every(h=>h>=44));
 assert.equal((await ctx.request.post(base+'/procsync/retry',{headers:{'X-Requested-With':'XMLHttpRequest'},form:{shop_id:first,sync_type:'orders'}})).status(),403);
 assert.equal((await ctx.request.post(base+'/procnotifications/snooze',{headers:{'X-CSRF-Token':csrf},data:{items:[{id:1}]}})).status(),422);
 const anon=await browser.newContext();for(const endpoint of ['/procSearch?q=aa','/procWorkspace/save'])assert.equal((await anon.request.get(base+endpoint,{maxRedirects:0})).status(),302);await anon.close();
 assert.deepEqual(errors,[]);assert.ok(saveCount>0);console.log('Minimum sampled changed-surface text contrast: '+minimumContrast.toFixed(2)+':1');console.log('PASS: per-account preferences, search recovery/keyboard/scoped destinations, responsive orders/details, grouped reminders/urgent filter, sync actions/recovery, auth/CSRF and four widths in both themes');
}finally{await browser?.close();php(`chdir('public');require '../app/init.php';$d=new Database();$d->query('DELETE FROM account_workspace WHERE user_id=${uid}');$d->exe();session_id('${sid}');session_start();session_destroy();`);}})().catch(e=>{console.error(e);process.exitCode=1;});
