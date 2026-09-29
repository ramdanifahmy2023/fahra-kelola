const assert=require('node:assert/strict');
const fs=require('node:fs'),path=require('node:path');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const root=path.resolve(__dirname,'..'),base=process.env.FINANCE_TEST_URL || 'http://127.0.0.1:8133';
const session=require('./panel-test-session.cjs')(root);
const output=path.join(root,'tmp/finance-wallet-ui');fs.mkdirSync(output,{recursive:true});
const money=value=>new Intl.NumberFormat('id-ID',{style:'currency',currency:'IDR',maximumFractionDigits:2}).format(value);
(async()=>{let browser;try{
  browser=await chromium.launch({headless:true});
  const context=await browser.newContext({viewport:{width:1600,height:1100},locale:'id-ID',timezoneId:'Asia/Jakarta',reducedMotion:'reduce'});
  await context.addCookies([{name:'PHPSESSID',value:session.sid,url:base}]);
  const response=await (await context.request.get(base+'/procFinance/summary')).json();assert.equal(response.status,'success');
  const ids=response.stores.slice(0,2).map(s=>String(s.id));assert.equal(ids.length,2);
  const wallet=amount=>({amount,updated_at:'2026-09-29 17:25:00',withdrawal_restricted:null,notice:null,failed:false,error:null,stale:false,refresh_pending:false,interval_seconds:600});
  let stores=response.stores.slice(0,2).map((s,i)=>({...s,name:'Toko uji '+(i+1),active_imports:0,wallet:wallet(i ? 0 : 123456)}));
  let hold=false,release,fail=false,requests=0;
  await context.route('**/procFinance/summary?*',async route=>{
    const p=new URL(route.request().url()).searchParams,selected=p.get('shops')?.split(',').filter(Boolean) || [];
    const data=structuredClone({...response,stores:stores.filter(s=>!selected.length || selected.includes(String(s.id)))});requests++;
    if(hold)await new Promise(resolve=>{release=resolve;});
    if(fail)return route.fulfill({status:502,json:{status:'error',message:'Gagal membaca data uji.'}});
    await route.fulfill({json:data}).catch(()=>{});
  });
  const page=await context.newPage(),errors=[];page.on('pageerror',e=>errors.push(e.message));
  const loaded=()=>page.waitForFunction(()=>document.querySelector('#finance-summary-panel')?.getAttribute('aria-busy')==='false' && document.querySelector('.finance-store'));
  const refresh=async()=>{await Promise.all([page.waitForResponse(r=>r.url().includes('/procFinance/summary?')),page.evaluate(()=>document.dispatchEvent(new Event('visibilitychange')))]);await loaded();};
  const fit=async label=>{
    assert.deepEqual(await page.evaluate(()=>{
      const issues=[];
      if(document.documentElement.scrollWidth>innerWidth)issues.push('Page overflow');
      for(const node of document.querySelectorAll('.finance-balances article, .finance-store-values > div, .finance-wallet-notice')){
        if(!node.checkVisibility())continue;
        if(node.scrollWidth>node.clientWidth+1)issues.push('Content overflow: '+node.className);
        const b=node.getBoundingClientRect();
        for(const child of node.children){const c=child.getBoundingClientRect();if(c.width && (c.left<b.left-1 || c.right>b.right+1))issues.push('Child outside parent');}
      }
      return issues;
    }),[],label);
  };
  for(const route of ['/panel','/panel/finance']){
    await page.goto(base+route);await loaded();
    assert.equal(await page.locator('#finance-wallet').innerText(),money(123456));
    assert.match(await page.locator('#finance-wallet-quality').innerText(),/Data semua toko tersedia/);
    assert.equal(await page.locator(`[data-wallet-shop="${ids[1]}"] dd`).innerText(),money(0));
    assert.match(await page.locator('#finance-store-period').innerText(),/Pending dan Saldo Penjual: posisi terbaru/);
    assert.doesNotMatch(await page.locator('#finance-summary-panel').innerText(),/Saldo Aktif|Dana diblokir|Bisa ditarik/);
  }
  stores[0].wallet={...wallet(123456),withdrawal_restricted:true,notice:'Verifikasi identitas toko. <img src=x onerror=alert(1)>'};
  await refresh();assert.equal(await page.locator('#finance-wallet').innerText(),money(123456),'Restriction does not reduce balance');
  await page.locator('#finance-wallet-restrictions').focus();await page.keyboard.press('Enter');
  assert.equal(await page.locator('.finance-wallet-notice').isVisible(),true);
  assert.equal(await page.locator('.finance-wallet-notice img').count(),0,'Source notice rendered as escaped text');
  assert.match(await page.locator('.finance-wallet-notice').innerText(),/Saldo tetap masuk total/);
  assert.equal(await page.evaluate(()=>document.activeElement.tagName),'SUMMARY');
  await refresh();assert.equal(await page.locator('.finance-wallet-notice').isVisible(),true,'Polling preserves disclosure');
  stores[0].wallet.notice=null;await refresh();assert.match(await page.locator('.finance-wallet-notice').innerText(),/belum memberikan alasan/);
  stores[0].wallet.failed=true;stores[0].wallet.error='Koneksi toko perlu diperbarui.';await refresh();
  assert.equal(await page.locator('#finance-wallet').innerText(),money(123456));
  assert.match(await page.locator('#finance-wallet-quality').innerText(),/Memakai saldo terakhir di 1 dari 2 toko/);
  assert.match(await page.locator('.finance-source-list').first().innerText(),/Koneksi toko perlu diperbarui/);
  assert.match(await page.locator('#finance-wallet-note').innerText(),/30 Sep 2026, 00.25 WIB/);
  stores[1].wallet.amount=null;await refresh();
  assert.match(await page.locator('#finance-wallet-quality').innerText(),/Baru 1 dari 2 toko terhitung/);
  stores[0].wallet.amount=null;await refresh();assert.equal(await page.locator('#finance-wallet').innerText(),'Belum tersedia');
  stores[0].wallet=wallet(0);stores[1].wallet=wallet(0);await refresh();assert.equal(await page.locator('#finance-wallet').innerText(),money(0));
  assert.equal(await page.locator('#finance-wallet-restrictions').isVisible(),false);
  stores[0].wallet=wallet(123456);stores[1].wallet=wallet(700);await refresh();
  const choose=async selected=>{await page.locator('#finance-shops').evaluate((el,values)=>{for(const o of el.options)o.selected=values.includes(o.value);el.dispatchEvent(new Event('change',{bubbles:true}));},selected);await page.locator('#finance-filters button[type=submit]').click();};
  await choose([ids[0]]);await loaded();assert.equal(await page.locator('#finance-wallet').innerText(),money(123456));
  await page.selectOption('#finance-period','custom');await page.fill('#finance-start','2026-08-01');await page.fill('#finance-end','2026-08-31');await page.locator('#finance-filters button[type=submit]').click();await loaded();
  assert.equal(await page.locator('#finance-wallet').innerText(),money(123456),'Wallet does not change with past report range');
  assert.match(await page.locator('#finance-wallet-note').innerText(),/Tanpa filter tanggal/);
  hold=true;const before=requests;await choose([ids[1]]);await page.waitForFunction(()=>document.querySelector('#finance-wallet').textContent==='Memuat…');
  assert.equal(await page.locator('#finance-wallet-restrictions').isVisible(),false);
  while(requests===before)await new Promise(resolve=>setTimeout(resolve,10));
  hold=false;release();await loaded();assert.equal(await page.locator('#finance-wallet').innerText(),money(700),'New scope never shows previous store balance');
  fail=true;await choose([ids[0]]);await page.locator('#finance-error').waitFor({state:'visible'});
  assert.equal(await page.locator('#finance-wallet').innerText(),'Belum dapat dimuat');
  fail=false;await page.locator('#finance-retry').click();await loaded();
  stores[0]={...stores[0],name:'TokoUjiNamaPanjang'.repeat(12),wallet:{...wallet(999999999999),withdrawal_restricted:true,notice:'Periksa verifikasi identitas toko di Shopee.'}};
  await refresh();await page.locator('#finance-wallet-restrictions').click();
  for(const width of [320,500,999,1600]){
    await page.setViewportSize({width,height:1000});
    for(const theme of ['light','dark']){
      await page.evaluate(t=>window.shopdashTheme.setMode(t),theme);await fit(width+' '+theme);
      assert.ok(await page.locator('#finance-wallet').evaluate(el=>el.getBoundingClientRect().height<=parseFloat(getComputedStyle(el).lineHeight)+1),'Large amount remains on one line at '+width);
      await page.locator('#finance-wallet').scrollIntoViewIfNeeded();
      await page.screenshot({path:path.join(output,`wallet-${width}-${theme}.png`),animations:'disabled'});
    }
  }
  await page.setViewportSize({width:1600,height:1000});await page.locator('#finance-page').evaluate(el=>el.style.width='684px');await fit('684px content');
  await page.locator('#finance-summary-panel').scrollIntoViewIfNeeded();await page.screenshot({path:path.join(output,'ledger-684.png'),animations:'disabled'});
  await page.locator('#finance-page').evaluate(el=>el.style.removeProperty('width'));
  await page.setViewportSize({width:500,height:1000});await page.evaluate(()=>document.documentElement.style.fontSize='200%');await fit('200% text reflow');
  assert.deepEqual(errors,[]);
  console.log('PASS: Saldo Penjual on both pages, multi/single shop, zero/missing/stale data, restriction text, source timestamp, period independence, loading/error, keyboard, responsive themes and reflow');
}finally{await browser?.close();session.cleanup();}})().catch(e=>{console.error(e);process.exitCode=1;});
