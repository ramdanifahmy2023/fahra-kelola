const assert=require('node:assert/strict');
const fs=require('node:fs'),path=require('node:path'),{execFileSync}=require('node:child_process');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const root=path.resolve(__dirname,'..'),base='https://shopdash-fixture.test',output=path.join(root,'tmp/ux-review');
fs.mkdirSync(output,{recursive:true});
const ranges={current:{start:'2026-10-01',end:'2026-10-03'},previous:{start:'2026-09-28',end:'2026-09-30'}};
const coverage=complete=>({current:Object.fromEntries(['confirmed_gmv','confirmed_orders','shop_uv','product_clicks'].map(k=>[k,{known_days:complete?3:0,expected_days:3,complete}]))});
const rows=[1,2].map(id=>({shop_id:id,shop_name:id===2?'Toko Contoh Perlengkapan Perjalanan dengan Nama Sangat Panjang':'Toko Contoh 1',rank:id,current_confirmed_gmv:id===1?0:null,current_confirmed_orders:id===1?0:null,current_shop_uv:id===1?30:null,current_product_clicks:id===1?0:null,growth_percent:id===1?-100:null,conversion_rate:id===1?0:null,coverage:coverage(id===1)}));
const summary={status:'success',ranges,rows,totals:{confirmed_gmv:0,confirmed_orders:0,shop_uv:30,product_clicks:0,growth_percent:null,conversion_rate:null,coverage:{current:Object.fromEntries(Object.keys(coverage(true).current).map(k=>[k,{known_days:3,expected_days:6,complete:false}]))}}};
const points=[{date:'2026-10-01',confirmed_gmv:0,confirmed_orders:0,shop_uv:10,product_clicks:0},{date:'2026-10-03',confirmed_gmv:0,confirmed_orders:0,shop_uv:10,product_clicks:0}];
(async()=>{let browser;try{
  browser=await chromium.launch({headless:true});
  const context=await browser.newContext({locale:'id-ID',timezoneId:'Asia/Jakarta',reducedMotion:'reduce',viewport:{width:1600,height:1000}});
  let failReport=false,failProfit=false,holdBasis='',releaseHeld,revision=0,holdReport=false,releaseReport;
  const errors=[],requests=[];
  await context.route('**/*',async route=>{
    const url=new URL(route.request().url()),p=url.pathname;
    if(url.origin!==base)return route.fulfill({status:404,body:''});
    if(route.request().method()!=='GET')throw new Error('No writes allowed: '+p);
    if(p.startsWith('/assets/')){const file=path.join(root,'public',p);return fs.existsSync(file)?route.fulfill({path:file,contentType:p.endsWith('.css')?'text/css':p.endsWith('.js')?'application/javascript':p.endsWith('.woff2')?'font/woff2':'application/octet-stream'}):route.fulfill({status:404,body:''});}
    if(p.startsWith('/fixture/'))return route.fulfill({contentType:'text/html',body:execFileSync('php',['tests/fixtures/ux-review.php',p.split('/').at(-1)],{cwd:root,encoding:'utf8'})});
    requests.push(p+url.search);
    if(p==='/procsync/status')return route.fulfill({json:{status:'success',schedules:[]}});
    if(p==='/procreports/summary'){
      const selected=url.searchParams.get('shop_id');
      if(holdReport && selected==='1')await new Promise(resolve=>{releaseReport=resolve;});
      if(failReport)return route.fulfill({status:502,json:{status:'error',message:'Laporan uji gagal.'}});
      return route.fulfill({json:{...summary,rows:selected && selected!=='0'?rows.filter(r=>String(r.shop_id)===selected):rows}});
    }
    if(p==='/procreports/compare')return route.fulfill({json:{status:'success',series:[{shop_id:1,shop_name:'Toko Contoh 1',points}]}});
    if(p==='/procreports/detail')return route.fulfill({json:{status:'success',ranges,rows:points.map(p=>({...p,metric_date:p.date}))}});
    if(p==='/procFinance/profitLoss'){
      const basis=url.searchParams.get('basis');
      if(basis===holdBasis)await new Promise(resolve=>{releaseHeld=resolve;});
      if(failProfit)return route.fulfill({status:502,json:{status:'error',message:'Laba rugi uji gagal.'}});
      const revenue=(basis==='cash'?222000:111000)+revision;
      const id=Number(url.searchParams.get('shops') || 1),start=url.searchParams.get('start'),end=url.searchParams.get('end'),days=Math.round((Date.parse(end)-Date.parse(start))/86400000)+1;
      return route.fulfill({json:{status:'success',basis,range:{start,end,days},totals:{revenue,cost:null,gross_profit:null,after_ads:null,ads:0},stores:[{id,name:'Toko Contoh '+id,revenue,cost:null,after_ads:null,gross_profit:null,revenue_orders:2,revenue_days:days,incomplete_cost_orders:1,missing_cost_units:1,ads:{amount:0,complete:true,days},coverage:{complete:true,expected_days:days}}]}});
    }
    throw new Error('Unexpected request '+p);
  });
  const page=await context.newPage();page.on('pageerror',e=>errors.push(e.message));
  const reportLoaded=()=>page.waitForFunction(()=>!document.getElementById('report-content').hidden);
  const profitLoaded=()=>page.waitForFunction(()=>document.getElementById('finance-profit-content')?.getAttribute('aria-busy')==='false');
  await page.goto(base+'/fixture/reports?end_date=2026-10-03');await reportLoaded();
  assert.equal(await page.locator('#total-gmv').innerText(),'Rp 0');
  assert.match(await page.locator('#total-gmv-coverage').innerText(),/Sebagian/);
  assert.match(await page.locator('#total-gmv-growth').innerText(),/Belum dapat dibandingkan/);
  assert.match(await page.locator('#total-conversion').innerText(),/Belum dapat dihitung/);
  assert.match(await page.locator('#report-rows tr').nth(1).innerText(),/Belum tersedia/);
  assert.doesNotMatch(await page.locator('#report-rows tr').first().innerText(),/Belum tersedia|Sebagian/,'Complete store is not labelled partial or unavailable');
  for(const width of [320,500,999,1600])for(const theme of ['light','dark']){
    await page.setViewportSize({width,height:1000});await page.evaluate(t=>document.documentElement.dataset.theme='shopdash-'+t,theme);
    assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'Report reflows '+width+'/'+theme);
    assert.deepEqual(await page.locator('#performance-report :is(button,input,select,summary)').evaluateAll(elements=>elements.filter(e=>e.checkVisibility()&&e.getBoundingClientRect().height<43.5).map(e=>e.id||e.tagName)),[]);
    if(width===1600){
      const contrast=await page.evaluate(()=>{
        const canvas=document.createElement('canvas'),ctx=canvas.getContext('2d');
        const rgba=value=>{ctx.clearRect(0,0,1,1);ctx.fillStyle=value;ctx.fillRect(0,0,1,1);return [...ctx.getImageData(0,0,1,1).data];};
        const lum=color=>color.slice(0,3).map(v=>v/255).map(v=>v<=.04045?v/12.92:((v+.055)/1.055)**2.4).reduce((sum,v,i)=>sum+v*[.2126,.7152,.0722][i],0);
        return ['#total-gmv','#report-refresh','.report-totals p','.report-desktop-table th','.report-ranges p'].map(selector=>{
          const el=document.querySelector(selector);let parent=el,bg;
          while(parent){bg=rgba(getComputedStyle(parent).backgroundColor);if(bg[3]===255)break;parent=parent.parentElement;}
          const fg=rgba(getComputedStyle(el).color),alpha=fg[3]/255,a=lum(fg.slice(0,3).map((v,i)=>v*alpha+bg[i]*(1-alpha))),b=lum(bg);
          return {selector,ratio:(Math.max(a,b)+.05)/(Math.min(a,b)+.05)};
        });
      });
      for(const sample of contrast)assert.ok(sample.ratio>=4.5,theme+' '+JSON.stringify(sample));
    }
    if(width<1050){const detail=page.locator('#report-mobile-rows details').first();if(!await detail.evaluate(e=>e.open)){await detail.locator('summary').focus();await page.keyboard.press('Enter');}assert.equal(await detail.evaluate(e=>e.open),true);}
    await page.screenshot({path:path.join(output,`reports-${width}-${theme}.png`),fullPage:true,animations:'disabled'});
  }
  await page.setViewportSize({width:320,height:1000});
  await page.locator('#report-mobile-rows .report-shop-detail').first().click();
  await page.waitForFunction(()=>document.querySelector('#detail-rows')?.textContent.includes('Belum tersedia'));
  assert.equal(await page.locator('#detail-rows tr').count(),6,'Missing dates remain visible as unknown');
  assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'Daily cards reflow');
  await page.locator('.report-chart-section > summary').click();
  await page.waitForFunction(()=>document.querySelectorAll('#compare-chart circle').length===2);
  assert.equal(await page.locator('#compare-chart path').getAttribute('d').then(d=>(d.match(/M/g)||[]).length),2,'Missing date splits the line, rather than inventing zero');
  await page.locator('.report-numeric > summary').click();await page.locator('#compare-values summary').first().click();
  assert.match(await page.locator('#compare-values').innerText(),/2 Okt 2026\s+Belum tersedia/);
  failReport=true;await page.selectOption('#report-sort','shop_uv');
  await page.waitForFunction(()=>document.getElementById('report-refreshed').textContent==='Gagal dimuat');
  assert.equal(await page.locator('#report-content').isVisible(),false,'Failed scope hides old report');
  failReport=false;await page.locator('#report-refresh').click();await reportLoaded();
  await page.locator('#selectedShopDisplay').focus();await page.keyboard.press('ArrowDown');await page.keyboard.press('ArrowDown');await page.keyboard.press('Enter');await reportLoaded();
  assert.equal(await page.inputValue('#report-shop'),'1');
  assert.equal(await page.locator('#selectedShopDisplay').evaluate(el=>el===document.activeElement),true,'Shop selection restores keyboard focus');
  assert.match(requests.filter(r=>r.startsWith('/procreports/summary')).at(-1),/shop_id=1/);
  assert.equal(new URL(page.url()).searchParams.get('sort'),'shop_uv');
  holdReport=true;await page.evaluate(()=>document.getElementById('report-shop').dispatchEvent(new Event('change')));
  await page.waitForTimeout(50);
  await page.evaluate(()=>{document.getElementById('report-shop').value='2';document.getElementById('report-shop').dispatchEvent(new Event('change'));});await reportLoaded();
  releaseReport();holdReport=false;await page.waitForTimeout(80);
  assert.equal(await page.locator('#report-rows .report-shop-detail').getAttribute('data-shop-id'),'2','Late summary cannot overwrite a newer scope');
  await page.evaluate(()=>document.documentElement.style.fontSize='200%');assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'Reports supports 200% text');
  await page.goto(base+'/fixture/profit');await profitLoaded();
  assert.match(await page.locator('#finance-profit-content').innerText(),/111\.000/);
  assert.equal(await page.locator('.finance-profit-breakdown').evaluate(e=>e.open),false,'Detail stays optional');
  assert.match(await page.locator('.finance-profit-caveat').innerText(),/1 pesanan belum/);
  failProfit=true;await page.selectOption('#finance-profit-basis','cash');await profitLoaded();
  assert.equal(await page.locator('#finance-profit-content').innerText(),'','Old accrual numbers cleared after cash fails');
  assert.match(await page.locator('#finance-profit-scope').innerText(),/CASH/);
  await page.selectOption('#finance-profit-basis','accrual');await profitLoaded();
  assert.equal(await page.locator('#finance-profit-content').innerText(),'');
  assert.match(await page.locator('#finance-profit-scope').innerText(),/ACCRUAL/);
  assert.doesNotMatch(await page.locator('#finance-profit-status').innerText(),/Angka terakhir/,'Cleared snapshot is not claimed as retained when reverting to an old basis');
  await page.selectOption('#finance-profit-basis','cash');await profitLoaded();
  failProfit=false;await page.locator('#finance-profit-retry').click();await profitLoaded();assert.match(await page.locator('#finance-profit-content').innerText(),/222\.000/);
  failProfit=true;await page.evaluate(()=>window.refreshProfitFixture());await profitLoaded();
  assert.match(await page.locator('#finance-profit-status').innerText(),/Angka terakhir/);assert.match(await page.locator('#finance-profit-content').innerText(),/222\.000/);
  await page.fill('#fixture-start','2026-09-01');await page.locator('#profit-fixture-filters button').click();await profitLoaded();assert.equal(await page.locator('#finance-profit-content').innerText(),'','Failed date change clears old figures');
  failProfit=false;await page.locator('#finance-profit-retry').click();await profitLoaded();
  failProfit=true;await page.selectOption('#finance-shops','2');await page.locator('#profit-fixture-filters button').click();await profitLoaded();assert.equal(await page.locator('#finance-profit-content').innerText(),'','Failed shop change clears old figures');
  failProfit=false;await page.locator('#finance-profit-retry').click();await profitLoaded();
  holdBasis='accrual';await page.selectOption('#finance-profit-basis','accrual');await page.waitForTimeout(50);await page.selectOption('#finance-profit-basis','cash');await profitLoaded();releaseHeld();holdBasis='';await page.waitForTimeout(80);assert.match(await page.locator('#finance-profit-content').innerText(),/222\.000/,'Late basis is rejected');
  await page.locator('.finance-profit-breakdown > summary').click();
  assert.match(await page.locator('.finance-profit-shop h3').innerText(),/Toko Contoh 2/,'Loaded shop data matches selection');
  await page.locator('[data-focus-key="profit-hpp-2"]').focus();revision=1;await page.evaluate(()=>window.refreshProfitFixture());await profitLoaded();
  assert.equal(await page.locator('.finance-profit-breakdown').evaluate(e=>e.open),true);
  assert.equal(await page.locator('[data-focus-key="profit-hpp-2"]').evaluate(e=>e===document.activeElement),true,'Refresh keeps link focus');
  for(const width of [320,500,999,1600])for(const theme of ['light','dark']){await page.setViewportSize({width,height:1000});await page.evaluate(t=>document.documentElement.dataset.theme='shopdash-'+t,theme);assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'Profit reflows '+width+'/'+theme);await page.screenshot({path:path.join(output,`profit-${width}-${theme}.png`),fullPage:true,animations:'disabled'});}
  await page.goto(base+'/fixture/products');
  await page.locator('#products-recap').waitFor();
  assert.equal(await page.locator('.page-recap-more').evaluate(e=>e.open),false);
  assert.match(await page.locator('#products-recap').innerText(),/Data stok belum lengkap: 1/,'Partial warning stays visible while collapsed');
  const compactHeight=await page.locator('#products-recap').evaluate(e=>e.getBoundingClientRect().height);
  await page.locator('.page-recap-more > summary').click();
  assert.ok(await page.locator('#products-recap').evaluate(e=>e.getBoundingClientRect().height)>compactHeight+100,'Disclosure materially reduces initial recap height');
  await page.locator('.page-recap-more > summary').click();
  for(const width of [320,500,999,1600])for(const theme of ['light','dark']){
    await page.setViewportSize({width,height:1000});await page.evaluate(t=>document.documentElement.dataset.theme='shopdash-'+t,theme);
    assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'Products reflow '+width+'/'+theme);
    assert.ok(await page.locator('.product-pagination-count').evaluate(e=>e.scrollWidth<=e.clientWidth+1),'Pagination remains fully readable');
    const title=page.locator('#product-11 .product-name');await title.focus();await page.keyboard.press('Enter');
    await page.waitForFunction(()=>!document.querySelector('#product-detail-dialog .product-cover'));
    assert.equal(await page.locator('#product-detail-dialog .product-image-fallback').count(),1);
    assert.match(await page.locator('#product-detail-content').innerText(),/ABCDABCDABCD/,'Full title available in keyboard dialog');
    await page.keyboard.press('Escape');assert.equal(await title.evaluate(e=>e===document.activeElement),true);
    const sku=page.locator('#product-11 .product-sku-overview');await sku.locator('summary').click();assert.match(await sku.innerText(),/SKU-PANJANG-01/);await sku.locator('summary').click();
    await page.screenshot({path:path.join(output,`products-${width}-${theme}.png`),fullPage:true,animations:'disabled'});
  }
  assert.match(await page.locator('#product-12 [data-label="Stok"]').innerText(),/Belum tersedia/);
  assert.deepEqual(errors,[]);
  console.log('PASS: Reports/P&L/Product scopes, null/zero/partial, failure after success, late responses, focus, image fallback, 24 responsive/theme views. No business writes.');
}finally{await browser?.close();}})().catch(error=>{console.error(error);process.exit(1);});
