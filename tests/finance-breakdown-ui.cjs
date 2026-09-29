const assert=require('node:assert/strict');
const {execFileSync}=require('node:child_process');
const fs=require('node:fs'),path=require('node:path');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const root=path.resolve(__dirname,'..'),base=process.env.FINANCE_TEST_URL || 'http://127.0.0.1:8133';
const php=code=>execFileSync('php',['-r',code],{cwd:root,encoding:'utf8'}).trim();
const sid=php("chdir('public');require '../app/init.php';$d=new Database;$d->query('SELECT id,name,email FROM accounts LIMIT 1');session_id(bin2hex(random_bytes(24)));session_start();$_SESSION['auth_user']=$d->single();echo session_id();session_write_close();");
const output=path.join(root,'tmp/finance-breakdown-ui');fs.mkdirSync(output,{recursive:true});
(async()=>{let browser;try{
  browser=await chromium.launch({headless:true});
  const context=await browser.newContext({viewport:{width:1600,height:1100},locale:'id-ID',timezoneId:'Asia/Jakarta'});
  await context.addCookies([{name:'PHPSESSID',value:sid,url:base}]);
  const page=await context.newPage(),errors=[];page.on('pageerror',e=>errors.push(e.message));
  const loaded=()=>page.waitForFunction(()=>document.querySelector('#finance-summary-panel').getAttribute('aria-busy')==='false' && document.querySelector('.finance-store'));
  await page.goto(base+'/panel/finance');await loaded();
  const filters=await page.evaluate(()=>window.shopdashFinance.filters);
  const result=await (await context.request.get(base+'/procFinance/summary?'+new URLSearchParams(filters))).json();
  assert.equal(result.status,'success');
  const money=n=>new Intl.NumberFormat('id-ID',{style:'currency',currency:'IDR',maximumFractionDigits:2}).format(n);
  const total=result.stores.reduce((n,s)=>n+(s.topups.amount || 0),0);
  assert.ok((await page.locator('#finance-secondary').innerText()).includes(money(total)));
  assert.match(await page.locator('#finance-secondary').innerText(),/Top up iklan berhasil/);
  assert.match(await page.locator('#finance-secondary').innerText(),/Biaya iklan terpakai/);
  for(const shop of result.stores){
    assert.ok((await page.locator(`[data-topup-shop="${shop.id}"]`).innerText()).includes(shop.topups.amount===null ? 'Belum tersedia' : money(shop.topups.amount)));
    const store=page.locator(`[data-store-id="${shop.id}"]`);await store.locator('summary').click();
    for(const state of ['shipping','delivered','return','unknown']){
      const link=new URL(await store.locator(`[data-pending-state="${state}"] a`).getAttribute('href'));
      assert.equal(link.searchParams.get('shops'),String(shop.id));assert.equal(link.searchParams.get('state'),state);assert.equal(link.searchParams.get('start'),filters.start);
    }
  }
  for(const width of [320,500,999,1600]){
    await page.setViewportSize({width,height:1100});
    for(const theme of ['light','dark']){
      await page.evaluate(t=>window.shopdashTheme.setMode(t),theme);
      await page.locator('#finance-pending-breakdown').scrollIntoViewIfNeeded();
      assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'No overflow '+width+' '+theme);
      await page.screenshot({path:path.join(output,`${width}-${theme}.png`),animations:'disabled'});
      const link=page.locator('#finance-pending-states a').first();await link.focus();assert.equal(await link.evaluate(el=>el===document.activeElement),true);
      assert.ok((await link.boundingBox()).height>=44);
    }
  }
  const shop=result.stores.find(s=>s.pending_orders>0);
  const detailResponse=page.waitForResponse(r=>r.url().includes('/procFinance/details?'));
  await page.locator(`[data-store-id="${shop.id}"] [data-pending-state="shipping"] a`).click();
  const detailData=await (await detailResponse).json();
  await page.waitForFunction(()=>document.querySelector('#finance-detail-pagination').textContent.includes('baris'));
  assert.equal(await page.inputValue('#finance-state'),'shipping');assert.equal(await page.locator('#finance-shops option:checked').count(),1);
  assert.ok((await page.locator('#finance-detail-pagination > span').innerText()).startsWith(detailData.total+' baris'));
  assert.ok(detailData.rows.every(r=>r.state==='shipping' && Number(r.shop_id)===shop.id));
  await page.reload();await page.waitForFunction(()=>document.querySelector('#finance-detail-pagination').textContent.includes('baris'));
  assert.equal(await page.inputValue('#finance-state'),'shipping');
  await page.selectOption('#finance-category','2');assert.equal(await page.locator('#finance-state-field').isVisible(),false);

  let snapshot={...result.stores[0],pending:500,pending_detail:500,pending_states:{shipping:0,delivered:0,return:0,unknown:500},pending_state_counts:{shipping:0,delivered:0,return:0,unknown:1},topups:{amount:null,transactions:0,complete:false,updated_at:null,enabled:false},ads:{amount:123,days:1,updated_at:null}};
  await page.route('**/procFinance/summary?*',route=>route.fulfill({json:{status:'success',range:result.range,stores:[snapshot]}}));
  await page.goto(base+'/panel/finance');await loaded();
  assert.match(await page.locator('#finance-pending-states [data-pending-state=shipping]').innerText(),/Belum teridentifikasi/);
  assert.match(await page.locator('#finance-pending-states [data-pending-state=shipping]').innerText(),/Belum lengkap/);
  assert.match(await page.locator('[data-topup-shop]').innerText(),/Belum tersedia/);
  snapshot={...snapshot,pending:0,pending_detail:0,pending_states:{shipping:0,delivered:0,return:0,unknown:0},pending_state_counts:{shipping:0,delivered:0,return:0,unknown:0},topups:{amount:0,transactions:0,complete:true,updated_at:'2026-09-29 07:30:00',enabled:true,interval_seconds:86400}};
  await page.locator('#finance-pending-states [data-pending-state=shipping] a').focus();
  await page.evaluate(()=>document.dispatchEvent(new Event('visibilitychange')));
  await page.waitForFunction(()=>document.querySelector('#finance-pending-states [data-pending-state=shipping] dd').textContent.includes('0'));
  assert.match(await page.locator('[data-topup-shop] dd').innerText(),/Rp\s*0/);
  assert.doesNotMatch(await page.locator('#finance-pending-states [data-pending-state=shipping]').innerText(),/Belum lengkap/);
  assert.equal(await page.locator('#finance-pending-states [data-pending-state=shipping] a').evaluate(el=>el===document.activeElement),true);
  await page.locator('.finance-store summary').click();
  const storeLink=page.locator('.finance-store [data-pending-state=delivered] a');await storeLink.focus();
  snapshot={...snapshot,pending:10,pending_detail:10,pending_states:{shipping:0,delivered:10,return:0,unknown:0},pending_state_counts:{shipping:0,delivered:1,return:0,unknown:0}};
  await page.evaluate(()=>document.dispatchEvent(new Event('visibilitychange')));
  await page.waitForFunction(()=>document.querySelector('.finance-store [data-pending-state=delivered] dd').textContent.includes('10'));
  assert.equal(await storeLink.evaluate(el=>el===document.activeElement),true);
  assert.equal(await page.locator('.finance-store details').evaluate(el=>el.open),true);
  assert.deepEqual(errors,[]);
  console.log('PASS: finance topup totals/store scope, VAT labels, pending counts/drilldown/reload, unknown versus zero, responsive themes and keyboard');
}finally{await browser?.close();php("session_id('"+sid+"');session_start();$_SESSION=[];session_destroy();");}})().catch(e=>{console.error(e);process.exitCode=1;});
