const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {execFileSync} = require('node:child_process');
const {chromium} = require(process.env.PLAYWRIGHT_MODULE || 'playwright');

const root = path.resolve(__dirname, '..');
const fixtureOrigin = 'https://shopdash-fixture.test';
const localOrigin = process.env.PAGE_RECAP_TEST_URL || 'http://127.0.0.1:59159';
const chromePath = process.env.CHROME_PATH || '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const output = path.join(root, 'tmp/page-recap-ui');
fs.mkdirSync(output, {recursive:true});
const shops = [
  {shop_id:6,shop_name:'elfuad.store',shop_logo:'',session_status:'connected',status:'ok',session_expired:false,error_message:null,last_sync_at:'2026-10-14 02:00:00',synced_at:'2026-10-14 02:00:00',sync_enabled:true,stale:false,unread_count:8,conversation_count:14,active_count:12,closed_count:2},
  {shop_id:7,shop_name:'Safariana dengan nama toko yang cukup panjang untuk membungkus baris',shop_logo:'',session_status:'expired',status:'expired',session_expired:true,error_message:null,last_sync_at:'2026-10-13 02:00:00',synced_at:'2026-10-13 02:00:00',sync_enabled:true,stale:true,unread_count:3,conversation_count:8,active_count:4,closed_count:4},
];
const availableReport = {available:true,stale:false,start_date:'2026-10-01',end_date:'2026-10-14',channel_label:'Iklan produk',fetched_at:'2026-10-14T02:00:00Z',impressions:100,clicks:20,ctr:20,orders:3,items_sold:4,sales:120000,ad_cost:30000,roas:4,daily:[]};
const adShops = [
  {...shops[0],metrics:{performance:availableReport,ads_credit:{total:500000},ads_expense_today:3000,has_ads:{product:true,shop:false,live:false}}},
  {...shops[1],metrics:{performance:{available:false,error_code:90309999,error_message:'Laporan belum tersedia'},ads_credit:{total:null},ads_expense_today:null,has_ads:null}},
];
const promotions = [
  {shop_id:6,shop_name:shops[0].shop_name,status:'ok',session_status:'connected',session_expired:false,stale:false,synced_at:'2026-10-14 02:00:00',summary:{active_voucher_count:4,voucher_total:6,active_flash_sale_count:2,flash_sale_total:5,vouchers:[],active_flash_sales:[]}},
  {shop_id:7,shop_name:shops[1].shop_name,status:'ok',session_status:'connected',session_expired:false,stale:false,synced_at:'2026-10-14 02:00:00',summary:{active_voucher_count:2,voucher_total:4,active_flash_sale_count:1,flash_sale_total:2,vouchers:[],active_flash_sales:[]}},
  {shop_id:9,shop_name:'Toko dengan laporan gagal',status:'error',session_status:'unknown',session_expired:false,stale:true,error_message:'Data uji tidak tersedia.',summary:{}},
];
const schedules = [
  {shop_id:6,shop_name:shops[0].shop_name,sync_type:'orders',job_status:'failed',last_success_at:'2026-10-13 02:00:00',next_run_at:null,enabled:1,interval_seconds:3600,presentation:{tone:'error',stage:'Gagal',message:'Percobaan terakhir gagal.',percent:null,can_retry:false}},
  {shop_id:6,shop_name:shops[0].shop_name,sync_type:'products',job_status:'running',last_success_at:'2026-10-13 02:00:00',next_run_at:null,enabled:1,interval_seconds:3600,presentation:{tone:'info',stage:'Berjalan',message:'Sinkronisasi berjalan.',percent:50,can_retry:false}},
  {shop_id:7,shop_name:shops[1].shop_name,sync_type:'ads',job_status:'queued',last_success_at:null,next_run_at:null,enabled:1,interval_seconds:3600,presentation:{tone:'warning',stage:'Menunggu',message:'Menunggu antrean.',percent:null,can_retry:false}},
  {shop_id:7,shop_name:shops[1].shop_name,sync_type:'customers',job_status:'success',last_success_at:'2026-10-14 02:00:00',next_run_at:'2026-10-14 03:00:00',enabled:1,interval_seconds:3600,presentation:{tone:'success',stage:'Selesai',message:'Sinkronisasi selesai.',percent:null,can_retry:false}},
];
let browser, context, session;
const errors = [], requestScopes = [];

function fixtureHtml(page) {
  return execFileSync('php',['tests/fixtures/page-recap-visual.php',page],{cwd:root,encoding:'utf8'});
}
function mime(file) {
  if (file.endsWith('.css')) return 'text/css';
  if (file.endsWith('.js')) return 'application/javascript';
  if (file.endsWith('.woff2')) return 'font/woff2';
  return 'application/octet-stream';
}
async function waitForRecap(page, kind) {
  const selectors = {
    overview:'#fixture-recap',
    chat:'#chat-recap[aria-busy="false"]',
    sync:'#sync-recap:not([hidden])',
    ads:'#ads-recap:not([hidden])',
    promotions:'#promotion-recap:not([hidden])',
  };
  await page.locator(selectors[kind]).waitFor({state:'visible'});
}
async function responsiveCheck(page, kind) {
  for (const width of [320,500,999,1600]) {
    await page.setViewportSize({width,height:1000});
    for (const theme of ['shopdash-light','shopdash-dark']) {
      await page.evaluate(value=>document.documentElement.dataset.theme=value,theme);
      const overflow = await page.evaluate(() => ({
        page:document.documentElement.scrollWidth > innerWidth + 1,
        recap:[...document.querySelectorAll('.page-recap')].filter(el=>!el.hidden&&el.scrollWidth>el.clientWidth+1).map(el=>el.id),
      }));
      assert.equal(overflow.page,false,`${kind} ${width}px ${theme}: page overflows`);
      assert.deepEqual(overflow.recap,[],`${kind} ${width}px ${theme}: recap overflows`);
      if(width===1600){
        const contrasts=await page.evaluate(()=>{
          const canvas=document.createElement('canvas'),ctx=canvas.getContext('2d');
          const rgba=value=>{ctx.clearRect(0,0,1,1);ctx.fillStyle=value;ctx.fillRect(0,0,1,1);return [...ctx.getImageData(0,0,1,1).data].map((n,i)=>i===3?n/255:n);};
          const luminance=color=>color.slice(0,3).map(value=>value/255).map(value=>value<=.04045?value/12.92:((value+.055)/1.055)**2.4).reduce((sum,value,index)=>sum+value*[.2126,.7152,.0722][index],0);
          return ['.page-recap-heading h2','.page-recap-heading p','.page-recap-primary > strong','.page-recap-metric > span','.page-recap-metric small','.page-recap-segment-link','.page-recap-insight','.page-recap-trend figcaption'].flatMap(selector=>[...document.querySelectorAll(selector)].filter(el=>el.closest('.page-recap')&&!el.closest('.page-recap').hidden).map(el=>{
            let parent=el,bg=[0,0,0,0];while(parent){bg=rgba(getComputedStyle(parent).backgroundColor);if(bg[3]>=.99)break;parent=parent.parentElement;}
            const fg=rgba(getComputedStyle(el).color),alpha=fg[3],composite=fg.slice(0,3).map((value,index)=>value*alpha+bg[index]*(1-alpha));
            const a=luminance(composite),b=luminance(bg);return {selector,ratio:(Math.max(a,b)+.05)/(Math.min(a,b)+.05)};
          }));
        });
        for(const result of contrasts)assert.ok(result.ratio>=4.5,`${kind} ${theme} contrast ${JSON.stringify(result)}`);
      }
    }
  }
  await page.setViewportSize({width:1600,height:1000});
}

(async()=>{
  try {
    browser=await chromium.launch({headless:true,executablePath:chromePath});
    context=await browser.newContext({viewport:{width:1600,height:1000},locale:'id-ID',timezoneId:'Asia/Jakarta',reducedMotion:'reduce'});
    await context.route('**/*',async route=>{
      const url=new URL(route.request().url()),pathname=url.pathname;
      if (url.origin===fixtureOrigin) {
        if (pathname.startsWith('/assets/')) {
          const file=path.join(root,'public',pathname);
          if (!fs.existsSync(file)) return route.fulfill({status:404,body:''});
          return route.fulfill({path:file,contentType:mime(file)});
        }
        const match=pathname.match(/^\/fixture\/(overview|chat|sync|ads|promotions|picker-orders|picker-products|picker-customers)$/);
        if (match) return route.fulfill({contentType:'text/html',body:fixtureHtml(match[1])});
        if (pathname==='/procChat/overview') return route.fulfill({json:{status:'success',shops,totals:{unread_count:11,conversation_count:22,active_count:16,expired_shops:1}}});
        if (pathname==='/procChat/conversations') {
          requestScopes.push(url.searchParams.get('shop_id'));
          return route.fulfill({json:{status:'success',conversations:[]}});
        }
        if (pathname==='/procsync/status') return route.fulfill({json:{status:'success',schedules}});
        if (pathname==='/procads/summary') return route.fulfill({json:{status:'success',shops:adShops}});
        if (pathname==='/procads/topups') return route.fulfill({json:{status:'success',report:{months:[],shops:[],shop_options:[],start_date:'2026-08-01',end_date:'2026-10-14'}}});
        if (pathname==='/procPromotions/summary') return route.fulfill({json:{status:'success',shops:promotions}});
        return route.fulfill({status:404,contentType:'application/json',body:'{}'});
      }
      if (url.origin===new URL(localOrigin).origin) return route.continue();
      return route.abort();
    });

    const page=await context.newPage();
    page.on('pageerror',error=>errors.push(error.message));
    for (const kind of ['overview','chat','sync','ads','promotions']) {
      await page.goto(fixtureOrigin+'/fixture/'+kind);
      await waitForRecap(page,kind);
      if (kind==='overview') {
        assert.equal(await page.locator('#fixture-recap .page-recap-metric').count(),4);
        assert.equal(await page.locator('#fixture-recap-trend-title').textContent(),'Tren pesanan');
        assert.match(await page.locator('#fixture-recap .page-recap-primary strong').textContent(),/28/);
        assert.equal(await page.locator('#fixture-recap .page-recap-segment-link[href]').count(),1);
      }
      if (kind==='chat') {
        assert.equal(await page.locator('#chat-total-unread').textContent(),'11');
        assert.equal(await page.locator('#chat-total-issues').textContent(),'1');
        const scopedRequest=page.waitForRequest(request=>{const url=new URL(request.url());return url.pathname==='/procChat/conversations'&&url.searchParams.get('shop_id')==='7';});
        const shopButton=page.locator('#chat-recap-shops button').filter({hasText:'Safariana'}).first();
        await shopButton.focus(); await page.keyboard.press('Enter');
        await scopedRequest;
        await page.waitForFunction(()=>document.getElementById('chat-selected-shop').textContent.includes('Safariana'));
        assert.equal(requestScopes.at(-1),'7','Chat per-shop recap selects and loads that shop');
      }
      if (kind==='sync') {
        assert.equal(await page.locator('#sync-recap-failed').textContent(),'1');
        const failed=page.locator('#sync-recap-filters [data-filter="failed"]');
        await failed.focus(); await page.keyboard.press('Enter');
        assert.equal(await page.locator('#sync-dashboard .sync-status-card').count(),1,'Keyboard status filter narrows sync cards');
        assert.equal(await page.locator('#sync-dashboard .sync-status-card').getAttribute('data-sync-key'),'6:orders');
      }
      if (kind==='ads') {
        assert.equal(await page.locator('#ads-recap-cost').textContent(),'Rp 30.000');
        assert.match(await page.locator('#ads-recap-cost-context').textContent(),/1 dari 2 toko/);
        assert.equal(await page.locator('#ads-recap-roas').textContent(),'4x');
        const shopButton=page.locator('#ads-recap-shops button').first();
        await shopButton.focus(); await page.keyboard.press('Enter');
        await page.waitForFunction(()=>document.querySelectorAll('#ads-grid article').length===1);
        assert.equal(await page.locator('#ads-shop').inputValue(),'6','Ads comparison selects the corresponding shop');
      }
      if (kind==='promotions') {
        assert.equal(await page.locator('#promotion-recap-vouchers').textContent(),'6');
        assert.equal(await page.locator('#promotion-recap-issues').textContent(),'1');
        assert.match(await page.locator('#promotion-recap-insight').textContent(),/2 toko/);
        const shopButton=page.locator('#promotion-recap-shops button').first();
        await shopButton.focus(); await page.keyboard.press('Enter');
        assert.equal(await page.locator('[data-shop-card="6"]').count(),1,'Promotion comparison scrolls to the matching shop card');
      }
      await responsiveCheck(page,kind);
      if (kind==='overview') { await page.evaluate(()=>document.documentElement.dataset.theme='shopdash-light'); await page.screenshot({path:path.join(output,'overview-1600-light.png'),animations:'disabled'}); }
      if (kind==='chat') { await page.setViewportSize({width:320,height:900}); await page.evaluate(()=>document.documentElement.dataset.theme='shopdash-light'); await page.screenshot({path:path.join(output,'chat-320-light.png'),animations:'disabled'}); }
    }
    for (const [section,parameter,value] of [['orders','status','completed'],['products','stock','low'],['customers','segment','repeat']]) {
      await page.goto(fixtureOrigin+'/fixture/picker-'+section);
      const href=await page.locator(`#shop-picker-options [data-product-shop="7"]`).getAttribute('href');
      const query=new URL(href).searchParams;
      assert.equal(query.get(parameter),value,`${section} shop switch keeps its active filter`);
      assert.equal(query.get('limit'),'20');
      assert.equal(query.has('page'),false);
      if(section==='orders') { assert.equal(query.get('startDate'),'2026-10-01'); assert.equal(query.get('endDate'),'2026-10-14'); }
    }

    session=require('./panel-test-session.cjs')(root);
    await context.addCookies([{name:'PHPSESSID',value:session.sid,url:localOrigin}]);
    for (const [pathName,selector] of [
      ['/panel/orders','#orders-recap'],['/panel/products','#products-page'],['/panel/customers','#customers-recap'],
      ['/panel/shops','#shops-recap'],['/panel/sync','#sync-page'],['/panel/ads','#ads-monitor'],
      ['/panel/promotions','#promotion-grid'],['/panel/chat','#chat-recap'],
    ]) {
      const response=await page.goto(localOrigin+pathName,{waitUntil:'domcontentloaded'});
      assert.ok(response && response.ok(),`${pathName} renders on the local worktree server`);
      assert.equal(await page.locator(selector).count(),1,`${pathName} contains the expected page summary or shell`);
    }
    assert.deepEqual(errors,[],`Browser page errors: ${errors.join('; ')}`);
    console.log('PASS: real recap template and Chat, Sync, Ads, Promotions fixtures; keyboard drilldowns, partial coverage, 4 widths × 2 themes, and eight authenticated local worktree routes. All API writes stayed mocked or unused.');
  } finally {
    await browser?.close();
    session?.cleanup();
  }
})().catch(error=>{console.error(error);process.exit(1);});
