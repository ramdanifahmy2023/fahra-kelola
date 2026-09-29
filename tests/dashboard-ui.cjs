const assert=require('node:assert/strict');
const {execFileSync}=require('node:child_process');
const fs=require('node:fs'),path=require('node:path');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const root=path.resolve(__dirname,'..'),base=process.env.FINANCE_TEST_URL || 'http://127.0.0.1:8133';
const php=code=>execFileSync('php',['-r',code],{cwd:root,encoding:'utf8'}).trim();
const sid=php("chdir('public');require '../app/init.php';$d=new Database;$d->query('SELECT id,name,email FROM accounts LIMIT 1');session_id(bin2hex(random_bytes(24)));session_start();$_SESSION['auth_user']=$d->single();echo session_id();session_write_close();");
const output=path.join(root,'tmp/dashboard-ui');fs.mkdirSync(output,{recursive:true});
const realtime=(ids=['1','2'])=>({status:'success',selected_shop_count:ids.length,shop_count:ids.length,failures:[],metrics:{key_metrics:{uv:0,pv:100,product_clicks:20,orders:4,buyers:3,sales:Number(ids[0])*100},metric_coverage:{uv:ids.length,pv:ids.length,product_clicks:ids.length,orders:ids.length,buyers:ids.length,sales:ids.length},top_sales_items:ids.map(id=>({shop_id:Number(id),shop_name:'Toko uji '+id,item_name:'Produk uji dengan nama panjang untuk pemeriksaan tata letak di layar kecil',sales:12345678})),product_shop_count:ids.length,sales_hourly:[0,null,100,200,300,0,30,40,500,200,10,20,40,600,50,60,400,200,500,100,200,150],hourly_coverage:Array.from({length:22},(_,i)=>i===1 ? 0 : ids.length),time:1790686800}});
(async()=>{let browser;try{
  browser=await chromium.launch({headless:true});
  const context=await browser.newContext({viewport:{width:1600,height:1100},locale:'id-ID',timezoneId:'Asia/Jakarta',reducedMotion:'reduce'});
  await context.addCookies([{name:'PHPSESSID',value:sid,url:base}]);
  const page=await context.newPage(),errors=[];page.on('pageerror',e=>errors.push(e.message));
  let hold=false,releaseOld,syncs=0,failLive=false;
  await page.route('**/procRealtime/metrics?*',async route=>{
    if(failLive)return route.fulfill({status:502,json:{status:'error',message:'Gagal uji aktivitas'}});
    const ids=new URL(route.request().url()).searchParams.getAll('shop_ids[]');
    if(hold && ids.join(',')==='1')await new Promise(resolve=>{releaseOld=resolve;});
    await route.fulfill({json:realtime(ids.length ? ids : ['1','2'])}).catch(()=>{});
  });
  await page.route('**/procFinance/sync',route=>{syncs++;return route.fulfill({json:{status:'success',queued:1,message:'Pembaruan masuk antrean.'}});});
  const loaded=()=>page.waitForFunction(()=>document.querySelectorAll('.finance-store').length>0 && document.querySelector('#finance-summary-panel').getAttribute('aria-busy')==='false');
  await page.goto(base+'/panel');await loaded();await page.locator('.dashboard-stock-row').first().waitFor();await page.locator('#dashboard-hour-select').waitFor();
  const today=await page.evaluate(()=>new Intl.DateTimeFormat('en-CA',{timeZone:'Asia/Jakarta',year:'numeric',month:'2-digit',day:'2-digit'}).format(new Date()));
  assert.equal(await page.inputValue('#finance-period'),'month');assert.equal(await page.inputValue('#finance-start'),today.slice(0,8)+'01');assert.equal(await page.inputValue('#finance-end'),today);
  assert.equal(await page.locator('#finance-custom-dates').isVisible(),false);assert.match(await page.locator('#finance-scope-label').innerText(),/Semua toko/);
  assert.match(await page.locator('#finance-secondary').innerText(),/Omset dibayar/);assert.equal(await page.locator('#finance-pending-states').isVisible(),true);
  assert.match(await page.locator('#dashboard-live-scope').innerText(),/Tidak mengikuti periode laporan/);
  await page.selectOption('#dashboard-hour-select','1');assert.match(await page.locator('#dashboard-hour-value').innerText(),/Belum tersedia/);
  const lastStamp=await page.locator('#dashboard-live-status').innerText();failLive=true;await page.locator('#dashboard-live-refresh').click();await page.locator('#dashboard-live-error').waitFor({state:'visible'});
  assert.ok((await page.locator('#dashboard-live-status').innerText()).includes(lastStamp));failLive=false;await page.locator('#dashboard-live-refresh').click();await page.waitForFunction(()=>!document.querySelector('#dashboard-live-refresh').disabled);assert.equal(await page.locator('#dashboard-live-error').isVisible(),false);
  await page.selectOption('#finance-period','custom');await page.fill('#finance-start','2026-09-01');await page.fill('#finance-end','2026-09-02');
  assert.equal(await page.locator('#finance-filter-draft').isVisible(),true);
  await page.locator('#finance-filters button[type=submit]').click();await loaded();
  assert.match(await page.locator('#dashboard-operations-status').innerText(),/1 Sep 2026 sampai 2 Sep 2026/);
  const choose=async ids=>{
    await page.locator('#finance-shops').evaluate((el,ids)=>{for(const o of el.options)o.selected=ids.includes(o.value);el.dispatchEvent(new Event('change',{bubbles:true}));},ids);
    await page.locator('#finance-filters button[type=submit]').click();
  };
  const oldRequest=page.waitForRequest(r=>r.url().includes('/procRealtime/metrics?') && r.url().includes('=1'));
  hold=true;await choose(['1']);await oldRequest;
  await choose(['2']);await loaded();await page.waitForFunction(()=>document.querySelector('#dashboard-live-metrics')?.textContent.replace(/\s/g,'').includes('Rp200'));
  releaseOld?.();hold=false;
  await page.waitForTimeout(200);
  assert.match(await page.locator('#dashboard-live-metrics').innerText(),/Rp\s*200/);assert.equal(await page.locator('.finance-store').count(),1);assert.equal(await page.locator('.finance-store').getAttribute('data-store-id'),'2');
  const op=await (await context.request.get(base+'/procDashboard/overview?shops=2&start=2026-09-01&end=2026-09-02')).json();
  assert.ok(op.shops.every(s=>s.id===2));assert.ok(op.low_stock.every(s=>s.shop_id===2));assert.ok(op.recent_orders.every(s=>s.shop_id===2));
  await page.reload();await loaded();assert.equal(await page.inputValue('#finance-period'),'custom');assert.equal(await page.inputValue('#finance-start'),'2026-09-01');assert.equal(await page.locator('#finance-shops option:checked').count(),1);
  await page.locator('#finance-all-shops').click();await page.selectOption('#finance-period','month');await page.locator('#finance-filters button[type=submit]').click();await loaded();
  for(const width of [320,500,999,1600]){
    await page.setViewportSize({width,height:1100});
    for(const theme of ['light','dark']){
      await page.evaluate(t=>window.shopdashTheme.setMode(t),theme);
      assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'Page overflow '+width+' '+theme);
      if(width===1600){
        const pairs=await page.evaluate(()=>{
          const canvas=document.createElement('canvas');canvas.width=canvas.height=1;const ctx=canvas.getContext('2d');
          const rgb=color=>{ctx.clearRect(0,0,1,1);ctx.fillStyle=color;ctx.fillRect(0,0,1,1);return Array.from(ctx.getImageData(0,0,1,1).data);};
          return ['#finance-pending','.finance-quality.is-partial','#finance-sync'].map(selector=>{
            const el=document.querySelector(selector),text=rgb(getComputedStyle(el).color);let node=el,bg;
            while(node){bg=rgb(getComputedStyle(node).backgroundColor);if(bg[3]===255)break;node=node.parentElement;}
            const hex=values=>'#'+values.slice(0,3).map(v=>v.toString(16).padStart(2,'0')).join('');return {selector,text:hex(text),background:hex(bg)};
          });
        });
        const luminance=hex=>{const c=hex.slice(1).match(/../g).map(v=>parseInt(v,16)/255).map(v=>v<=.04045?v/12.92:((v+.055)/1.055)**2.4);return .2126*c[0]+.7152*c[1]+.0722*c[2];};
        for(const p of pairs){const a=luminance(p.text),b=luminance(p.background),ratio=(Math.max(a,b)+.05)/(Math.min(a,b)+.05);assert.ok(ratio>=4.5,'Contrast '+theme+' '+p.selector+' '+ratio);}
        console.log(JSON.stringify({theme,contrast:pairs}));
      }
      assert.ok(await page.locator('.dashboard-chart').evaluate(el=>el.getBoundingClientRect().right<=innerWidth),'Chart fits mobile');
      if(width<640)assert.ok(await page.locator('.dashboard-top-product > div').first().evaluate(el=>el.getBoundingClientRect().width>180),'Product title has a readable mobile column');
      await page.locator('#finance-shops-trigger').focus();await page.keyboard.press('ArrowDown');
      const menu=page.locator('#finance-shops-options');assert.equal(await menu.isVisible(),true);const box=await menu.boundingBox();assert.ok(box.x>=0 && box.x+box.width<=width+1);
      await page.keyboard.press('Escape');assert.equal(await menu.isVisible(),false);
      await page.evaluate(()=>scrollTo(0,0));await page.screenshot({path:path.join(output,`${width}-${theme}.png`),animations:'disabled'});
    }
  }
  await page.setViewportSize({width:1600,height:1100});await page.evaluate(()=>window.shopdashTheme.setMode('light'));await page.locator('#dashboard-activity').scrollIntoViewIfNeeded();await page.screenshot({path:path.join(output,'activity-desktop.png')});
  await page.setViewportSize({width:320,height:1100});await page.waitForTimeout(400);await page.locator('#dashboard-hourly').scrollIntoViewIfNeeded();await page.screenshot({path:path.join(output,'activity-mobile.png')});
  assert.match(await page.locator('#finance-open').getAttribute('href'),/period=month/);
  await page.locator('#finance-sync').click();await page.waitForFunction(()=>!document.querySelector('#finance-sync').disabled);assert.equal(syncs,1);
  await page.route('**/procDashboard/overview?*',route=>route.fulfill({status:500,json:{status:'error',message:'Gagal uji operasi'}}));
  await choose(['1']);await page.locator('#dashboard-operations-error').waitFor({state:'visible'});assert.equal(await page.locator('#dashboard-operations-content').innerText(),'');
  await page.unroute('**/procDashboard/overview?*');await page.locator('#dashboard-operations-retry').click();await page.waitForFunction(()=>document.querySelector('#dashboard-operations').getAttribute('aria-busy')==='false');
  await page.locator('#dashboard-operations-content summary').click();await page.locator('#dashboard-operations-content summary').focus();
  const refreshed=page.waitForResponse(r=>r.url().includes('/procDashboard/overview?'));
  await page.evaluate(()=>document.dispatchEvent(new Event('visibilitychange')));await refreshed;await page.waitForFunction(()=>document.querySelector('#dashboard-operations').getAttribute('aria-busy')==='false');
  assert.equal(await page.locator('#dashboard-operations-content details').evaluate(el=>el.open),true);assert.equal(await page.locator('#dashboard-operations-content summary').evaluate(el=>el===document.activeElement),true);
  assert.deepEqual(errors,[]);
  await page.close();

  // The clock and balances below are isolated fixtures; no future-dated data reaches Shopee or the database.
  const rolling=await context.newPage();const rollingErrors=[];rolling.on('pageerror',e=>rollingErrors.push(e.message));
  await rolling.clock.install({time:new Date('2026-09-30T16:59:50Z')});
  await rolling.route('**/procFinance/summary?*',route=>{
    const p=new URL(route.request().url()).searchParams,start=p.get('start'),end=p.get('end');
    const days=Math.round((Date.parse(end)-Date.parse(start))/86400000)+1;
    return route.fulfill({json:{status:'success',range:{start,end,days},stores:[{id:1,name:'Toko fixture',pending:0,pending_detail:0,pending_states:{shipping:0,delivered:0,return:0,unknown:0},pending_updated:null,released:null,released_days:0,released_basis:'detail',released_detail:null,released_detail_days:0,released_difference:null,adjustment:null,gmv:100,gmv_days:1,ads:{amount:null,days:0,updated_at:null},active_imports:0,error:null}]}});
  });
  await rolling.goto(base+'/panel/finance');await rolling.locator('.finance-store').waitFor();
  assert.equal(await rolling.inputValue('#finance-start'),'2026-09-01');assert.equal(await rolling.inputValue('#finance-end'),'2026-09-30');
  assert.match(await rolling.locator('#finance-pending').innerText(),/Rp\s*0/);assert.equal(await rolling.locator('#finance-released').innerText(),'Belum tersedia');
  assert.match(await rolling.locator('#finance-secondary').innerText(),/Total sementara/);
  await rolling.clock.fastForward(31000);await rolling.waitForFunction(()=>document.querySelector('#finance-start').value==='2026-10-01');assert.equal(await rolling.inputValue('#finance-end'),'2026-10-01');
  await rolling.selectOption('#finance-period','custom');await rolling.fill('#finance-start','2026-09-01');await rolling.fill('#finance-end','2026-09-02');await rolling.locator('#finance-filters button[type=submit]').click();
  await rolling.clock.fastForward(86400000);assert.equal(await rolling.inputValue('#finance-start'),'2026-09-01');assert.equal(await rolling.inputValue('#finance-end'),'2026-09-02');
  await rolling.reload();await rolling.locator('.finance-store').waitFor();assert.equal(await rolling.inputValue('#finance-period'),'custom');assert.equal(await rolling.inputValue('#finance-end'),'2026-09-02');assert.deepEqual(rollingErrors,[]);
  console.log('PASS: dashboard/finance defaults, global scopes, partial/null/zero, race rejection, custom dates, WIB month rollover, sync scope, failure recovery, responsive themes, chart controls and keyboard');
}finally{await browser?.close();php("session_id('"+sid+"');session_start();$_SESSION=[];session_destroy();");}})().catch(e=>{console.error(e);process.exitCode=1;});
