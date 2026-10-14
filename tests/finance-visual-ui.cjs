const assert=require('node:assert/strict');
const fs=require('node:fs'),path=require('node:path'),{execFileSync}=require('node:child_process');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const root=path.resolve(__dirname,'..'),base='https://shopdash-fixture.test',output=path.join(root,'tmp/finance-visual-ui');
fs.mkdirSync(output,{recursive:true});
const names=['Toko Uji Utama','Toko Uji Perlengkapan Perjalanan dengan Nama Panjang'];
const timestamp='2026-10-14 02:00:00';
const store=id=>({id,name:names[id-1],pending:12000000*id,released:18500000*id,pending_updated:timestamp,released_updated:timestamp,released_basis:'overview',released_days:14,
  wallet:{amount:id===1?3450000:0,updated_at:timestamp,failed:false,stale:false,withdrawal_restricted:false},
  gmv:24000000*id,gmv_days:14,gmv_updated:timestamp,ads:{amount:350000,days:14,updated_at:timestamp},topups:{amount:500000,transactions:2,complete:true,updated_at:timestamp},
  pending_states:{preparing:1000000,pickup:0,shipping:8000000,delivered:2000000,return:0,unknown:1000000},pending_state_counts:{preparing:4,pickup:0,shipping:20,delivered:5,return:0,unknown:2},
  pending_status_updated:timestamp,pending_detail:12000000*id,released_detail:18500000*id,released_detail_days:14,adjustment:0});
const realtime=ids=>({status:'success',selected_shop_count:ids.length,failures:[],metrics:{key_metrics:{uv:240,pv:580,product_clicks:120,orders:18,buyers:15,sales:1350000},metric_coverage:{uv:ids.length,pv:ids.length,product_clicks:ids.length,orders:ids.length,buyers:ids.length,sales:ids.length},top_sales_items:[{shop_id:1,shop_name:names[0],item_name:'Produk uji perlengkapan perjalanan dengan judul panjang yang tetap terbaca',sales:850000}],product_shop_count:ids.length,sales_hourly:[0,null,120000,40000,90000,200000,350000,80000,400000],hourly_coverage:[2,0,2,2,2,2,2,2,2],time:1791943200}});
(async()=>{let browser;try{
  browser=await chromium.launch({headless:true});
  const context=await browser.newContext({viewport:{width:1600,height:1100},locale:'id-ID',timezoneId:'Asia/Jakarta',reducedMotion:'reduce'});
  let fail=false,stores=[store(1),store(2)],writes=0;const requests=[],unexpected=[],errors=[];
  await context.route('**/*',async route=>{
    const url=new URL(route.request().url()),p=url.pathname;
    if(url.origin!==base)return route.abort();
    if(p.endsWith('/broken-fixture'))return route.fulfill({status:404,body:''});
    if(p.startsWith('/assets/')){
      const file=path.join(root,'public',p);
      if(!fs.existsSync(file))return route.fulfill({status:404,body:''});
      return route.fulfill({path:file,contentType:p.endsWith('.css')?'text/css':p.endsWith('.js')?'application/javascript':p.endsWith('.woff2')?'font/woff2':'application/octet-stream'});
    }
    if(p==='/panel' || p==='/panel/finance')return route.fulfill({contentType:'text/html',body:execFileSync('php',['tests/fixtures/finance-visual.php',p==='/panel'?'dashboard':'finance'],{cwd:root,encoding:'utf8'})});
    if(p==='/procFinance/summary'){
      const selected=url.searchParams.get('shops')?.split(',').filter(Boolean)||[];requests.push(selected);
      if(fail)return route.fulfill({status:502,json:{status:'error',message:'Pembaruan data uji gagal.'}});
      const start=url.searchParams.get('start'),end=url.searchParams.get('end'),days=Math.round((Date.parse(end)-Date.parse(start))/86400000)+1;
      return route.fulfill({json:{status:'success',range:{start,end,days},stores:stores.filter(s=>!selected.length||selected.includes(String(s.id))).map(s=>({...s,released_days:days,gmv_days:days,ads:{...s.ads,days}}))}});
    }
    if(p==='/procRealtime/metrics')return route.fulfill({json:realtime(url.searchParams.getAll('shop_ids[]').length?url.searchParams.getAll('shop_ids[]'):['1','2'])});
    if(p==='/procDashboard/overview')return route.fulfill({json:{status:'success',range:{start:'2026-10-01',end:'2026-10-14'},summary:{total_products:35,total_customers:240,total_orders:80,completed_orders:70,completed_order_value:5600000,stock_out_count:1,stock_low_count:2},shops:[{id:1,name:names[0],sync_status:'connected',last_successful_sync_at:timestamp}],low_stock:[{id:11,shop_id:1,shop_name:names[0],name:'Produk uji dengan stok yang perlu diperiksa',total_stock:3}],recent_orders:[]}});
    if(p==='/procFinance/sync'){writes++;return route.fulfill({json:{status:'success',message:'Pembaruan uji masuk antrean.'}});}
    if(['/procFinance/catalog','/procFinance/details'].includes(p))return route.fulfill({json:{status:'success',rows:[],total:0,page:1,limit:20,summary:{}}});
    unexpected.push(p);return route.fulfill({status:404,json:{status:'error',message:'Unexpected fixture request'}});
  });
  const page=await context.newPage();page.on('pageerror',e=>errors.push(e.message));
  const loaded=()=>page.waitForFunction(()=>document.querySelector('#finance-summary-panel')?.getAttribute('aria-busy')==='false' && document.querySelector('.finance-store'));
  const refresh=async()=>{await page.evaluate(()=>document.dispatchEvent(new Event('visibilitychange')));await loaded();};
  for(const route of ['/panel','/panel/finance']){
    await page.goto(base+route);await loaded();
    if(route==='/panel'){
      await page.locator('#dashboard-hour-select').waitFor();
      assert.ok(await page.evaluate(()=>!!(document.getElementById('dashboard-activity').compareDocumentPosition(document.getElementById('finance-summary-panel'))&Node.DOCUMENT_POSITION_FOLLOWING)),'Activity precedes store ledger in reading order');
      for(const anchor of await page.locator('.dashboard-jump-nav a').all()){
        const href=await anchor.getAttribute('href');assert.equal(await page.locator(href).count(),1);await anchor.focus();await page.keyboard.press('Enter');assert.equal(new URL(page.url()).hash,href);
      }
      await page.selectOption('#dashboard-hour-select','1');assert.match(await page.locator('#dashboard-hour-value').innerText(),/Belum tersedia/);
    }
    const detail=page.locator('.finance-balances .finance-metric-detail').first();
    assert.equal(await detail.getAttribute('open'),null);await detail.locator('summary').focus();await page.keyboard.press('Enter');assert.equal(await detail.evaluate(el=>el.open),true);
    await refresh();assert.equal(await detail.evaluate(el=>el.open),true,'Polling keeps source detail open');assert.equal(await detail.locator('summary').evaluate(el=>el===document.activeElement),true,'Polling preserves keyboard focus');
    await detail.locator('summary').click();
    for(const width of [320,500,999,1600])for(const theme of ['light','dark']){
      await page.setViewportSize({width,height:1100});await page.evaluate(t=>document.documentElement.dataset.theme='shopdash-'+t,theme);
      assert.deepEqual(await page.evaluate(()=>{
        const issues=[];if(document.documentElement.scrollWidth>innerWidth)issues.push('page overflow');
        for(const el of document.querySelectorAll('.finance-balances article,.finance-secondary > div,.dashboard-live-metrics > div,.finance-store-values > div'))if(el.checkVisibility()&&el.scrollWidth>el.clientWidth+1)issues.push(el.className||el.tagName);
        return issues;
      }),[],route+' '+width+' '+theme);
      await page.locator('#finance-shops-trigger').focus();await page.keyboard.press('ArrowDown');const menu=page.locator('#finance-shops-options');assert.equal(await menu.isVisible(),true);const box=await menu.boundingBox();assert.ok(box.x>=0&&box.x+box.width<=width+1);
      if(route==='/panel'&&width===320&&theme==='light')await page.screenshot({path:path.join(output,'dashboard-shops-320-light-open.png'),animations:'disabled'});
      await page.keyboard.press('Escape');assert.equal(await menu.isVisible(),false);
      await page.evaluate(()=>scrollTo(0,0));await page.screenshot({path:path.join(output,`${route==='/panel'?'dashboard':'finance'}-${width}-${theme}.png`),animations:'disabled',fullPage:width===1600});
      if(width===1600){
        const ratios=await page.evaluate(()=>{
          const canvas=document.createElement('canvas'),ctx=canvas.getContext('2d');
          const rgb=color=>{ctx.clearRect(0,0,1,1);ctx.fillStyle=color;ctx.fillRect(0,0,1,1);return [...ctx.getImageData(0,0,1,1).data];};
          const lum=c=>c.slice(0,3).map(v=>v/255).map(v=>v<=.04045?v/12.92:((v+.055)/1.055)**2.4).reduce((n,v,i)=>n+v*[.2126,.7152,.0722][i],0);
          return ['#finance-wallet','#finance-sync','.finance-metric-detail summary','.finance-secondary strong'].map(selector=>{
            const el=document.querySelector(selector);let node=el,bg;
            while(node){bg=rgb(getComputedStyle(node).backgroundColor);if(bg[3]===255)break;node=node.parentElement;}
            const a=lum(rgb(getComputedStyle(el).color)),b=lum(bg);return {selector,ratio:(Math.max(a,b)+.05)/(Math.min(a,b)+.05)};
          });
        });
        for(const result of ratios)assert.ok(result.ratio>=4.5,theme+' contrast '+JSON.stringify(result));
      }
    }
    const shopTrigger=page.locator('#finance-shops-trigger'),shopMenu=page.locator('#finance-shops-options');
    await shopTrigger.click();await shopMenu.locator('[data-shop-value="2"]').click();
    assert.equal(await page.locator('#finance-shops').evaluate(el=>el.selectedOptions.length),1,'Choosing one shop from Semua toko isolates that shop');
    assert.equal(await shopMenu.locator('[data-shop-value="1"]').getAttribute('aria-pressed'),'false');
    assert.equal(await shopMenu.isVisible(),true,'Multi-shop picker stays open after a selection');
    assert.equal(await page.locator('#finance-filter-draft').isVisible(),true,'A changed selection prompts the user to apply it');
    await page.locator('#finance-filters button[type=submit]').click();await loaded();
    assert.equal(await page.locator('.finance-store').count(),1);assert.equal(await page.locator('.finance-store').getAttribute('data-store-id'),'2');assert.match(await page.locator('#finance-wallet').innerText(),/Rp\s*0/);assert.deepEqual(requests.at(-1),['2']);
    await page.locator('#finance-clear-shops').click();
    assert.equal(await shopTrigger.innerText(), 'storefront\nPilih toko\nexpand_more');
    assert.equal(await page.locator('#finance-all-shops').isVisible(),true,'An empty selection offers a way to restore all shops');
    const requestCount=requests.length;await page.locator('#finance-filters button[type=submit]').click();
    await page.locator('#finance-error').waitFor({state:'visible'});
    assert.match(await page.locator('#finance-error').innerText(),/Pilih setidaknya satu toko/);
    assert.equal(requests.length,requestCount,'An empty selection cannot silently become Semua toko');
    await page.locator('#finance-all-shops').click();
    stores[1].wallet.amount=null;await refresh();await page.waitForFunction(()=>document.getElementById('finance-wallet').textContent==='Belum tersedia');assert.equal(await page.locator('#finance-wallet-quality .is-partial').isVisible(),true);
    fail=true;await refresh();await page.locator('#finance-error').waitFor({state:'visible'});fail=false;stores[1].wallet.amount=0;await page.locator('#finance-retry').click();await loaded();
    await page.locator('#finance-sync').click();await loaded();
    if(route==='/panel/finance'){
      await page.locator('[data-finance-tab="cost"]').click();await page.locator('#finance-cost-panel').waitFor({state:'visible'});await page.locator('#finance-open-cost-batch').click();assert.equal(await page.locator('#finance-cost-batch-dialog').evaluate(el=>el.open),true);await page.keyboard.press('Escape');assert.equal(await page.locator('#finance-cost-batch-dialog').evaluate(el=>el.open),false);
      await page.locator('[data-finance-tab="details"]').click();await page.locator('#finance-details-panel').waitFor({state:'visible'});await page.locator('[data-finance-tab="summary"]').click();await loaded();
    }
    stores=[store(1),store(2)];
  }
  await page.setViewportSize({width:500,height:1100});await page.evaluate(()=>document.documentElement.style.fontSize='200%');
  assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'200% text reflows');
  assert.equal(writes,2,'Only mocked refresh actions');assert.deepEqual(unexpected,[]);assert.deepEqual(errors,[]);
  console.log('PASS: real Dashboard/Finance templates, 16 responsive/theme views, keyboard disclosures and shop picker, scoped data, null/zero, failure recovery, chart, navigation and HPP dialog. No database or upstream writes.');
}finally{await browser?.close();}})().catch(e=>{console.error(e);process.exit(1);});
