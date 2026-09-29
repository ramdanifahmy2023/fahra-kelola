const assert=require('node:assert/strict');
const {execFileSync}=require('node:child_process');
const fs=require('node:fs'),path=require('node:path');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const root=path.resolve(__dirname,'..'),base=process.env.FINANCE_TEST_URL || 'http://127.0.0.1:8133';
const php=code=>execFileSync('php',['-r',code],{cwd:root,encoding:'utf8'}).trim();
const sid=php("chdir('public');require '../app/init.php';$d=new Database;$d->query('SELECT id,name,email FROM accounts LIMIT 1');session_id(bin2hex(random_bytes(24)));session_start();$_SESSION['auth_user']=$d->single();echo session_id();session_write_close();");
const output=path.join(root,'tmp/finance-completeness-ui');fs.mkdirSync(output,{recursive:true});
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
  const fit=async(label)=>{
    const violations=await page.evaluate(()=>{
      const failures=[];
      for(const node of document.querySelectorAll('.finance-store-values > div, .finance-state-list > div, .finance-source-list > div, .finance-custom-dates > div')){
        if(!node.checkVisibility())continue;
        const parent=node.getBoundingClientRect();
        for(const child of node.children){
          const b=child.getBoundingClientRect();
          if(b.width && (b.left<parent.left-1 || b.right>parent.right+1 || b.top<parent.top-1 || b.bottom>parent.bottom+1))failures.push(child.tagName+' outside '+node.className);
        }
        const dt=node.querySelector('dt'),dd=node.querySelector('dd');
        if(dt&&dd){const a=dt.getBoundingClientRect(),b=dd.getBoundingClientRect();if(Math.min(a.right,b.right)>Math.max(a.left,b.left)+1 && Math.min(a.bottom,b.bottom)>Math.max(a.top,b.top)+1)failures.push('Overlapping label and amount');}
      }
      if(document.documentElement.scrollWidth>innerWidth)failures.push('Page overflow');
      return failures;
    });
    assert.deepEqual(violations,[],label);
  };
  await page.locator('#finance-page').evaluate(el=>el.style.width='684px');
  await page.locator('#finance-summary-panel').scrollIntoViewIfNeeded();
  await fit('684px available content, desktop shell');
  const ledger=await page.locator('#finance-summary-panel').boundingBox();
  assert.ok(ledger.height<2800,'Seven-store ledger stays compact');
  assert.doesNotMatch(await page.locator('#finance-store-list').innerText(),/Jadwal setiap|transaksi berhasil|Pembaruan paling lama/,'Long metadata starts collapsed');
  await page.screenshot({path:path.join(output,'ledger-684-light.png'),animations:'disabled'});
  console.log(JSON.stringify({content_width:684,ledger_height:Math.round(ledger.height),stores:result.stores.length}));
  await page.locator('#finance-page').evaluate(el=>el.style.removeProperty('width'));
  for(const width of [320,500,768,999,1280,1600]){
    await page.setViewportSize({width,height:1000});
    for(const theme of ['light','dark']){
      await page.evaluate(t=>window.shopdashTheme.setMode(t),theme);
      await page.locator('#finance-summary-panel').scrollIntoViewIfNeeded();await fit(width+' '+theme);
      const summary=page.locator('.finance-store > details > summary').first();
      assert.ok((await summary.boundingBox()).height>=44);
      await summary.focus();await page.keyboard.press('Enter');
      await fit('Expanded '+width+' '+theme);
      assert.match(await page.locator('.finance-store-details').first().innerText(),/Sumber & waktu data/);
      await page.screenshot({path:path.join(output,`ledger-${width}-${theme}.png`),animations:'disabled'});
      await summary.focus();await page.keyboard.press('Enter');
      await page.selectOption('#finance-period','custom');
      await fit('Custom date fields '+width+' '+theme);
      await page.selectOption('#finance-period','month');
    }
  }
  await page.setViewportSize({width:500,height:900});
  await page.evaluate(()=>document.documentElement.style.fontSize='200%');await fit('200% text reflow');
  await page.evaluate(()=>document.documentElement.style.removeProperty('font-size'));
  let fixture={...result.stores[0],name:'Toko uji '+ 'NamaTanpaSpasi'.repeat(12),pending:999999999999,gmv:123456,gmv_days:result.range.days,
    gmv_coverage:{missing_dates:[],first_date:result.range.start,last_date:result.range.end,today_included:true,today_through:'2026-09-29 16:00:00',today_failed:false},
    pending_states:{preparing:100,pickup:200,shipping:300,delivered:400,return:500,mixed:600,unknown:700},
    pending_state_counts:{preparing:1,pickup:2,shipping:3,delivered:4,return:5,mixed:6,unknown:7},
    pending_issues:[{orders:7,message:'Status belum dikenali untuk data uji.'}],ads:{amount:5000000,days:result.range.days,basis:'period',stale:false,updated_at:'2026-09-29 16:00:00'}};
  await page.route('**/procFinance/summary?*',r=>r.fulfill({json:{status:'success',range:result.range,stores:[fixture]}}));
  await page.goto(base+'/panel/finance');await loaded();
  assert.match(await page.locator('.finance-store-values').innerText(),/Termasuk hari ini · sementara/);
  assert.doesNotMatch(await page.locator('.finance-store-values').innerText(),/1\/29/);
  for(const state of ['preparing','pickup','mixed']){
    const url=new URL(await page.locator(`#finance-pending-states [data-pending-state=${state}] a`).getAttribute('href'));
    assert.equal(url.searchParams.get('state'),state);assert.equal(url.searchParams.get('shops'),'');
  }
  await page.locator('.finance-store > details > summary').click();
  assert.match(await page.locator('.finance-store-details').innerText(),/7 pesanan: Status belum dikenali/);
  for(const width of [320,500,999,1600]){await page.setViewportSize({width,height:1000});await fit('Long name/large amount '+width);}
  fixture={...fixture,gmv_days:result.range.days-1,gmv_coverage:{...fixture.gmv_coverage,today_included:false,missing_dates:[filters.end]}};
  await page.evaluate(()=>document.dispatchEvent(new Event('visibilitychange')));
  await page.waitForFunction(()=>document.querySelector('.finance-store-values').textContent.includes('Hari ini belum masuk'));
  await page.unroute('**/procFinance/summary?*');
  await page.goto(base+'/panel/finance?tab=details&state=preparing');
  await page.waitForFunction(()=>document.querySelector('#finance-detail-pagination').textContent.includes('baris'));
  assert.equal(await page.inputValue('#finance-state'),'preparing');
  await page.locator('[data-finance-tab=cost]').click();await page.locator('[data-edit-cost]').first().waitFor();
  for(const width of [320,500,999]){
    await page.setViewportSize({width,height:720});await fit('HPP table '+width);
    await page.locator('[data-edit-cost]').first().click();
    assert.ok(await page.locator('#finance-cost-dialog').evaluate(el=>el.scrollWidth<=el.clientWidth),'HPP dialog contents fit');
    await page.keyboard.press('Escape');
  }
  assert.deepEqual(errors,[]);
  console.log('PASS: compact ledger, actual container widths, expanded source details, long names/amounts, date controls, HPP, 200% reflow, status links and coverage copy');
}finally{await browser?.close();php("session_id('"+sid+"');session_start();$_SESSION=[];session_destroy();");}})().catch(e=>{console.error(e);process.exitCode=1;});
