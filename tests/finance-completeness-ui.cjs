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
  let fixtureStores,fixtureRange=result.range;
  await page.route('**/procFinance/summary?*',r=>r.fulfill({json:{status:'success',range:fixtureRange,stores:fixtureStores || [fixture]}}));
  await page.goto(base+'/panel/finance');await loaded();
  assert.match(await page.locator('.finance-store-values').innerText(),/Hari ini masih berjalan/);
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
  await page.clock.install({time:new Date('2026-09-30T05:00:00Z')});
  fixtureRange={start:'2026-09-27',end:'2026-09-30',days:4};
  const complete={...fixture,name:'Toko uji',pending:0,pending_detail:0,released:0,released_days:4,released_detail:0,released_detail_days:4,released_difference:0,gmv:0,gmv_days:4,
    gmv_coverage:{missing_dates:[],today_included:true,today_failed:false},
    ads:{amount:0,days:4,complete:true,stale:false,basis:'period'},
    topups:{amount:0,transactions:0,complete:true,error:false,history_start:'2026-08-01',updated_at:'2026-09-30 04:00:00',stale:false}};
  const seven=()=>Array.from({length:7},(_,i)=>({...structuredClone(complete),id:i+1,name:'Toko uji '+(i+1)}));
  const show=async stores=>{
    fixtureStores=stores;
    await Promise.all([page.waitForResponse(r=>r.url().includes('/procFinance/summary?')),page.evaluate(()=>document.dispatchEvent(new Event('visibilitychange')))]);
    await loaded();
  };
  const metric=i=>page.locator('#finance-secondary > div').nth(i);
  const label=i=>metric(i).locator('.finance-quality').innerText();
  const gap=(store,date)=>({...store,gmv_days:3,gmv_coverage:{...store.gmv_coverage,missing_dates:[date],today_included:date!=='2026-09-30'}});
  fixtureStores=seven();
  await page.goto(base+'/panel/finance?period=custom&start=2026-09-27&end=2026-09-30');await loaded();
  assert.equal(await page.locator('#finance-secondary .is-partial').count(),0,'Current-day provisional data is not a missing-date warning');
  assert.match(await metric(0).locator('strong').innerText(),/Rp\s*0/,'Verified zero remains available');
  assert.match(await metric(0).innerText(),/Angka hari ini masih bisa berubah/);
  await show(seven().map(s=>gap(s,'2026-09-30')));
  assert.match(await label(0),/Data hari ini belum masuk di 7 dari 7 toko/);
  assert.doesNotMatch(await metric(0).innerText(),/Angka hari ini masih bisa berubah/);
  for(const width of [320,500,999,1600]){
    await page.setViewportSize({width,height:1000});
    for(const theme of ['light','dark']){
      await page.evaluate(t=>window.shopdashTheme.setMode(t),theme);
      await page.locator('#finance-secondary').scrollIntoViewIfNeeded();await fit('Specific coverage warning '+width+' '+theme);
      assert.ok(await metric(0).locator('.finance-quality').evaluate(el=>el.scrollWidth<=el.clientWidth),'Coverage label wraps');
      assert.ok(await metric(0).locator('.finance-quality').evaluate(el=>Math.abs(el.children[0].getBoundingClientRect().top-el.children[1].getBoundingClientRect().top)<2),'Icon stays beside the first line');
      await page.screenshot({path:path.join(output,`coverage-${width}-${theme}.png`),animations:'disabled'});
    }
  }
  const mixed=seven();mixed[0]=gap(mixed[0],'2026-09-28');mixed[1]=gap(mixed[1],'2026-09-29');
  await show(mixed);
  assert.match(await label(0),/Ada tanggal belum masuk di 2 dari 7 toko/);
  assert.match(await metric(0).innerText(),/Angka hari ini masih bisa berubah/,'Today does not hide historical gaps');
  assert.match(await page.locator('[data-store-id="1"] .finance-store-values').innerText(),/Belum masuk: 28 Sep 2026/);
  mixed[1]=gap(mixed[1],'2026-09-28');await show(mixed);
  assert.match(await label(0),/Data 28 Sep 2026 belum masuk di 2 dari 7 toko/);
  const subset=seven().map(s=>({...s,gmv:1000}));subset[6]={...subset[6],gmv:null,gmv_days:0};
  await show(subset);
  assert.match(await label(0),/Baru 6 dari 7 toko terhitung/);
  assert.match(await metric(0).locator('strong').innerText(),/6\.000/,'Only the available shops contribute');
  await show([{...gap(complete,'2026-09-30'),released_days:3,ads:{...complete.ads,days:1,complete:false},topups:{...complete.topups,amount:1000,complete:false,error:true}}]);
  assert.match(await label(0),/Data hari ini belum masuk$/);
  assert.match(await label(1),/Pembaruan terakhir gagal/);
  assert.match(await label(2),/Baru 1 dari 4 hari terhitung/);
  assert.match(await page.locator('#finance-released-quality').innerText(),/Baru 3 dari 4 hari terhitung/);
  assert.doesNotMatch(await label(2),/hari ini|30 Sep/,'Do not guess missing dates without source evidence');
  await show([{...complete,topups:{...complete.topups,complete:false,updated_at:'2026-09-29 16:00:00'}}]);
  assert.match(await label(1),/Belum diperbarui sejak 29 Sep 2026/);
  await show(seven().map(s=>({...s,topups:{...s.topups,complete:false,updated_at:'2026-09-29 16:00:00'}})));
  assert.match(await label(1),/Belum diperbarui sejak 29 Sep 2026 di 7 dari 7 toko/);
  fixtureRange={start:'2026-07-30',end:'2026-08-02',days:4};
  await show([{...complete,gmv_coverage:{missing_dates:[],today_included:false},topups:{...complete.topups,complete:false}}]);
  assert.match(await label(1),/Riwayat tersedia mulai 1 Agu 2026/);
  await show(seven().map(s=>({...s,gmv:null,gmv_days:0,gmv_coverage:{missing_dates:[],today_included:false},ads:{amount:null,days:0},topups:null,pending:null,pending_states:null,released:null,released_days:0})));
  assert.match(await label(0),/Data belum tersedia dari 7 toko/);
  assert.equal(await metric(0).locator('strong').innerText(),'Belum tersedia');
  assert.doesNotMatch(await page.locator('#finance-secondary').innerText(),/Total sementara/);
  await page.clock.setSystemTime(new Date());
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
