const assert=require('node:assert/strict');const fs=require('node:fs');const path=require('node:path');const {execFileSync}=require('node:child_process');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');const root=path.resolve(__dirname,'..');const base=process.env.NOTIFICATION_TEST_URL||'http://127.0.0.1:8131';
const php=code=>execFileSync('php',['-r',code],{cwd:root,encoding:'utf8'}).trim();
const testSession=require('./panel-test-session.cjs')(root);const sid=testSession.sid;
(async()=>{let browser;try{
 browser=await chromium.launch();const ctx=await browser.newContext({viewport:{width:1600,height:1008}});await ctx.addCookies([{name:'PHPSESSID',value:sid,url:base}]);await ctx.route('**/procWorkspace/**',r=>r.fulfill({json:{status:'success'}}));const page=await ctx.newPage();const errors=[];page.on('pageerror',e=>errors.push(e.message));
 let readFail=false,loadFail=false,evaluation=true,writes=0;
 let rows=Array.from({length:25},(_,i)=>({id:i+1,revision:1,shop_id:1,shop_name:'Toko dengan nama panjang untuk menguji pembungkusan tanpa terpotong',shop_logo:i===0?base+'/missing-notification-logo.png':'',type:i%2?'shipping_deadline':'low_stock',severity:i%2?'urgent':'warning',unread:true,title:i%2?'Batas kirim <script>':'Stok kritis',message:'Pesanan atau produk fixture untuk pengujian. Tidak ada transaksi sungguhan.',action_label:i%2?'Buka pesanan':'Periksa produk',path:i===2?'https://example.com/unsafe':'/panel/orders?shop_id=1&order_id='+i,icon:i%2?'local_shipping':'inventory_2',source_at:'2026-09-29 10:00:00',deadline:i%2?1790787599:null,stale:i===3}));
 await page.route('**/procnotifications/**',async route=>{
  const url=new URL(route.request().url());
  if(route.request().method()==='POST'){
   writes++;assert.ok(route.request().headers()['x-csrf-token']);
   if(readFail)return route.fulfill({status:500,json:{status:'error',message:'Status dibaca gagal disimpan.'}});
   for(const item of route.request().postDataJSON().items){const row=rows.find(r=>r.id===item.id);if(row&&row.revision===item.revision)row.unread=false;}
   return route.fulfill({json:{status:'success'}});
  }
  if(loadFail)return route.fulfill({status:500,json:{status:'error',message:'Notifikasi belum dapat dimuat.'}});
  const offset=Number(url.searchParams.get('offset')||0),unreadOnly=url.searchParams.get('unread_only')!=='0',selected=rows.filter(r=>!unreadOnly||r.unread);
  return route.fulfill({json:{status:'success',unread_count:rows.filter(r=>r.unread).length,summary:{total:rows.length,urgent:rows.filter(r=>r.severity==='urgent').length,unread:rows.filter(r=>r.unread).length,unread_urgent:rows.filter(r=>r.unread&&r.severity==='urgent').length},notifications:selected.slice(offset,offset+10),has_more:selected.length>offset+10,evaluation_available:evaluation}});
 });
 await page.goto(base+'/panel/automation');await page.addStyleTag({content:'*,*::before,*::after{transition:none!important;animation:none!important}'});
 await page.waitForFunction(()=>document.querySelector('#notification-badge').textContent==='25');
 await page.locator('#notification-button').focus();await page.keyboard.press('Enter');await page.locator('.notification-item').first().waitFor();
 assert.equal(await page.getAttribute('#notification-button','aria-expanded'),'true');
 assert.equal(await page.locator('.notification-item').count(),10);
 assert.ok(await page.getByText('Batas kirim <script>',{exact:true}).count());assert.equal(await page.locator('#notification-list script').count(),0);
 assert.equal(await page.locator('[data-alert-id="3"] a').getAttribute('href'),base+'/panel');
 await page.locator('[data-alert-id="1"] .notification-shop img').waitFor({state:'detached'});
 assert.ok((await page.locator('[data-alert-id="1"] .notification-shop').textContent()).includes('storefront'));
 await page.click('#notification-next');await page.waitForFunction(()=>document.querySelector('.notification-item').dataset.alertId==='11');
 await page.click('#notification-prev');await page.waitForFunction(()=>document.querySelector('.notification-item').dataset.alertId==='1');
 readFail=true;await page.locator('.notification-read').first().click();await page.locator('#notification-error').waitFor({state:'visible'});assert.equal(rows.filter(r=>r.unread).length,25);
 readFail=false;await page.locator('.notification-read').first().click();await page.waitForFunction(()=>document.querySelector('#notification-badge').textContent==='24');
 assert.equal(rows.length,25,'Read does not resolve incidents');
 await page.click('[data-notification-filter=active]');await page.waitForFunction(()=>document.querySelector('.notification-read-label')!==null);
 assert.equal(await page.locator('[data-alert-id="1"] .notification-read').count(),0);
 rows.forEach(r=>r.unread=false);await page.click('[data-notification-filter=new]');await page.getByText('Tidak ada notifikasi baru. 25 masalah masih aktif.',{exact:true}).waitFor();
 assert.ok(await page.locator('#notification-badge').isHidden());assert.ok(!(await page.locator('#notification-list').innerText()).includes('Semua stok aman'));
 rows[0].unread=true;rows[0].revision=2;rows[0].severity='urgent';await page.click('#notification-reload');await page.waitForFunction(()=>document.querySelector('#notification-badge').textContent==='1');
 await page.click('#notification-mark-all');await page.waitForFunction(()=>document.querySelector('#notification-badge').hidden);assert.equal(rows[0].unread,false);
 loadFail=true;await page.click('#notification-reload');await page.locator('#notification-error').waitFor({state:'visible'});
 loadFail=false;evaluation=false;await page.click('#notification-reload');await page.locator('#notification-source-warning').waitFor({state:'visible'});evaluation=true;
 await page.click('[data-notification-filter=active]');await page.locator('.notification-item').first().waitFor();
 let minimumContrast=Infinity;
 const out=path.join(root,'tmp/notifications-ui');fs.mkdirSync(out,{recursive:true});
 for(const width of [320,500,999,1600])for(const theme of ['light','dark']){
  await page.setViewportSize({width,height:1008});await page.evaluate(t=>window.shopdashTheme.setMode(t),theme);
  const box=await page.locator('#notification-panel').boundingBox();assert.ok(box.x>=0&&box.x+box.width<=width+1&&box.y>=0&&box.y+box.height<=1008);
  assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));
  assert.ok(await page.locator('#notification-list').evaluate(el=>el.scrollWidth<=el.clientWidth));
  const sizes=await page.locator('#notification-panel button:visible').evaluateAll(nodes=>nodes.map(n=>n.getBoundingClientRect().height));assert.ok(sizes.every(h=>h>=44));
  const contrasts=await page.locator('#notification-panel').evaluate(panel=>{
   const canvas=document.createElement('canvas');canvas.width=canvas.height=1;const c=canvas.getContext('2d',{willReadFrequently:true});
   const rgba=value=>{c.clearRect(0,0,1,1);c.fillStyle=value;c.fillRect(0,0,1,1);return Array.from(c.getImageData(0,0,1,1).data);};
   const blend=(fg,bg)=>fg.slice(0,3).map((v,i)=>v*fg[3]/255+bg[i]*(1-fg[3]/255));
   const lum=rgb=>rgb.map(v=>{v/=255;return v<=.04045?v/12.92:((v+.055)/1.055)**2.4;}).reduce((a,v,i)=>a+v*[.2126,.7152,.0722][i],0);
   return [...panel.querySelectorAll('h2,h3,p,a,button,span')].filter(el=>el.getClientRects().length&&el.textContent.trim()&&!el.disabled&&!el.classList.contains('material-symbols-outlined')&&[...el.childNodes].some(n=>n.nodeType===3&&n.textContent.trim())).map(el=>{
    const chain=[];for(let n=el;n;n=n.parentElement)chain.unshift(n);let bg=[255,255,255];for(const n of chain)bg=blend(rgba(getComputedStyle(n).backgroundColor),bg);
    const fg=blend(rgba(getComputedStyle(el).color),bg),a=lum(fg),b=lum(bg);return (Math.max(a,b)+.05)/(Math.min(a,b)+.05);
   });
  });
  minimumContrast=Math.min(minimumContrast,...contrasts);assert.ok(contrasts.every(v=>v>=4.5),'Notification text contrast');
  await page.screenshot({path:path.join(out,`${width}-${theme}.png`)});
 }
 await page.setViewportSize({width:320,height:480});
 const compact=await page.locator('#notification-panel').boundingBox();assert.ok(compact.y+compact.height<=480);
 assert.ok(await page.locator('#notification-list').evaluate(el=>el.clientHeight>=48));
 await page.setViewportSize({width:1600,height:1008});
 await page.keyboard.press('Escape');assert.ok(await page.locator('#notification-panel').isHidden());assert.equal(await page.evaluate(()=>document.activeElement.id),'notification-button');
 await page.click('#notification-button');await page.click('h1');assert.ok(await page.locator('#notification-panel').isHidden());
 const csrf=await page.locator('#notification-menu').getAttribute('data-csrf');
 assert.equal((await ctx.request.post(base+'/procnotifications/acknowledge',{data:{items:[]}})).status(),403);
 assert.equal((await ctx.request.post(base+'/procnotifications/acknowledge',{headers:{'X-CSRF-Token':csrf},data:{items:[{id:1}]}})).status(),422);
 assert.equal((await ctx.request.get(base+'/procnotifications/acknowledge')).status(),405);
 const anon=await browser.newContext();assert.equal((await anon.request.get(base+'/procnotifications/summary',{maxRedirects:0})).status(),302);await anon.close();
 assert.equal(writes,3);assert.deepEqual(errors,[]);
 const order=JSON.parse(php("chdir('public');require '../app/init.php';$d=new Database();$d->query('SELECT id,shop_id FROM orders ORDER BY id DESC LIMIT 1');echo json_encode($d->single());"));
 await page.route('**/procorders/**',r=>r.fulfill({json:{status:'success',orders:[],queue:[]}}));
 await page.route('**/procsync/**',r=>r.fulfill({json:{status:'success',jobs:[],schedules:[]}}));
 if(order){await page.goto(base+'/panel/orders?shop_id='+order.shop_id+'&order_id='+order.id);await page.locator('#notification-order-focus').waitFor();assert.equal(await page.locator('#order-row-'+order.id).count(),1);assert.equal(await page.locator('tr[id^="order-row-"]').count(),1);}
 console.log('Minimum sampled notification text contrast: '+minimumContrast.toFixed(2)+':1');
 console.log('PASS: notification filters, receipts, escalation, failures, paging, safe links/copy, logo fallback, keyboard, auth/CSRF and four responsive widths in both themes');
}finally{await browser?.close();testSession.cleanup();}})().catch(e=>{console.error(e);process.exitCode=1;});
