const assert = require('node:assert/strict');
const {execFileSync} = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');
const {chromium} = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const root = path.resolve(__dirname, '..');
const base = process.env.ADS_TEST_URL || 'http://127.0.0.1:8123';
const output = path.join(root, 'tmp/ads-ui');
fs.mkdirSync(output, {recursive: true});
const php = code => execFileSync('php', ['-r', code], {cwd: root, encoding: 'utf8'}).trim();
const testSession=require('./panel-test-session.cjs')(root);const sid=testSession.sid;
let checks = 0;
function check(value, message) { assert.ok(value, message); checks++; }
const ready = page => page.waitForFunction(() => document.querySelector('#ads-grid')?.getAttribute('aria-busy') === 'false');

(async () => {
  let browser;
  try {
    browser = await chromium.launch({headless: true});
    const context = await browser.newContext({viewport: {width: 1440, height: 1000}});
    await context.route('**/procWorkspace/**',r=>r.fulfill({json:{status:'success'}}));
    await context.addCookies([{name: 'PHPSESSID', value: sid, url: base}]);
    const page = await context.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.goto(base + '/panel/ads');
    await ready(page);
    const count = await page.locator('#ads-grid article').count();
    check(count > 0, 'Live shops loaded');
    for (const period of ['daily', 'weekly', 'monthly']) {
      for (const channel of ['product', 'shop', 'live']) {
        await page.selectOption('#ads-period', period);
        await page.selectOption('#ads-channel', channel);
        await ready(page);
        check(await page.locator('#ads-grid article').count() === count, period + '/' + channel + ' renders shops');
        check(await page.locator('#ads-grid [data-performance-period]').first().innerText() !== '', 'Date range rendered');
      }
    }
    const shop = await page.locator('#ads-shop option').nth(1).getAttribute('value');
    await page.locator('#ads-shop-trigger').click();
    await page.locator('#ads-shop-trigger + ul [data-shop-value="' + shop + '"]').click();
    check(await page.locator('#ads-grid article').count() === 1, 'Shop filter');
    await page.click('#ads-refresh'); await ready(page);
    check(await page.inputValue('#ads-shop') === shop, 'Reload preserves shop selection');
    for (const width of [320, 500, 999, 1600]) {
      await page.setViewportSize({width, height: 1008});
      for (const theme of ['light', 'dark']) {
        await page.evaluate(mode => window.shopdashTheme.setMode(mode), theme);
        for (const id of ['ads-shop', 'ads-topup-shop']) {
          const trigger = page.locator('#' + id + '-trigger');
          await trigger.click();
          const list = page.locator('#' + id + '-trigger + ul');
          await list.waitFor({state: 'visible'});
          const bounds = await list.boundingBox();
          const buttonBounds = await trigger.boundingBox();
          check(bounds.y >= buttonBounds.y + buttonBounds.height, 'Shop menu opens below');
          check(bounds.x >= 0 && bounds.x + bounds.width <= width + 1, 'Menu stays inside viewport');
          const rows = await list.locator('li').evaluateAll(items => items.map(el => {const r = el.getBoundingClientRect(); return {x:r.x,y:r.y,bottom:r.bottom};}));
          check(rows.length > 1, 'Shop options loaded');
          for (let i = 1; i < rows.length; i++) check(Math.abs(rows[i].x - rows[0].x) < 1 && rows[i].y >= rows[i-1].bottom - 1, 'Single vertical list');
          check(await list.locator('img').count() > 0, 'Shop logos visible in list');
          await list.locator('button').last().scrollIntoViewIfNeeded();
          await list.locator('button').first().scrollIntoViewIfNeeded();
          await page.screenshot({path: path.join(output, `${id}-${width}-${theme}.png`), animations: 'disabled'});
          await page.keyboard.press('Escape');
          await list.waitFor({state: 'hidden'});
        }
      }
    }
    await page.locator('#ads-topup-shop-trigger').click();
    const topupOption = page.locator('#ads-topup-shop-trigger + ul button').nth(1);
    const topupId = await topupOption.getAttribute('data-shop-value');
    const topupResponse = page.waitForResponse(response => response.url().includes('/procads/topups?') && new URL(response.url()).searchParams.get('shop_id') === topupId);
    await topupOption.focus();
    await page.keyboard.press('Enter');
    await topupResponse;
    check(await page.inputValue('#ads-topup-shop') === topupId, 'Topup selection filters request independently');
    check(await page.inputValue('#ads-shop') === shop, 'Topup does not change ads selection');
    await page.locator('#ads-shop-trigger').click();
    await page.locator('#ads-shop-trigger + ul [data-shop-value=""]').click();
    await page.screenshot({path: path.join(output, 'live-desktop.png'), animations: 'disabled'});
    await page.setViewportSize({width: 390, height: 844});
    await page.screenshot({path: path.join(output, 'live-mobile.png'), animations: 'disabled'});
    check(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), 'Live mobile no overflow');

    const report = JSON.parse(php("require 'app/helpers/AdsPerformance.php'; $f=json_decode(file_get_contents('tests/fixtures/ads-reports.json'),true)[0]; $r=array_merge(AdsPerformance::range('daily'),$f['request'],['start_date'=>'2026-09-26','end_date'=>'2026-09-26']); echo json_encode(AdsPerformance::normalize($f['response'],$r,'product'));"));
    let payload = {status: 'success', shops: [{shop_id: 999, shop_name: 'Fixture Sniper (uji browser)', session_status: 'connected', status: 'ok', stale: false, metrics: {performance: report}}]};
    let mode = 'ok';
    let delay = 0;
    await page.route('**/procads/summary?**', async route => {
      if (delay) await new Promise(resolve => setTimeout(resolve, delay));
      if (mode === 'error') return route.fulfill({status: 503, contentType: 'application/json', body: '{}'});
      if (mode === 'invalid') return route.fulfill({status: 200, contentType: 'text/html', body: '<p>Invalid response</p>'});
      if (mode === 'network') return route.abort('failed');
      return route.fulfill({json: payload});
    });
    const reload = async () => {await page.click('#ads-refresh'); await ready(page);};
    await page.selectOption('#ads-period', 'daily');
    await page.selectOption('#ads-channel', 'product'); await ready(page);
    check(await page.locator('[data-impressions]').innerText() === '5.943', 'Captured impressions');
    check(await page.locator('[data-clicks]').innerText() === '266', 'Captured clicks');
    check(await page.locator('[data-ctr]').innerText() === '4,48%', 'CTR percentage');
    check(await page.locator('[data-orders]').innerText() === '9', 'Captured checkout orders, distinct from broad_order=10');
    check(await page.locator('[data-items-sold]').innerText() === '19', 'Units distinct from orders');
    check(await page.locator('[data-sales]').innerText() === 'Rp 686.482,15', 'Currency precision');
    check(await page.locator('[data-ad-cost]').innerText() === 'Rp 104.739,52', 'Period cost');
    check(await page.locator('[data-roas]').innerText() === '6,55x', 'ROAS ratio');
    check(!await page.locator('[data-source-detail]').evaluate(el => el.open), 'Secondary details start collapsed');
    await page.locator('[data-source-detail] summary').focus();
    await page.keyboard.press('Enter');
    check(await page.locator('[data-source-detail]').evaluate(el => el.open), 'Keyboard opens source details');
    await page.locator('[data-detail] summary').focus();
    await page.keyboard.press('Enter');
    check(await page.locator('[data-detail]').evaluate(el => el.open), 'Keyboard opens daily detail');
    check(await page.locator('[data-daily-rows] tr').count() === 1, 'Intraday data grouped into one day');
    check(await page.locator('[data-daily-rows] td').nth(5).innerText() === 'Rp 686.482,15', 'Daily table currency');
    await page.locator('[data-table-region]').focus();
    check(await page.locator('[data-table-region]').evaluate(el => getComputedStyle(el).outlineStyle !== 'none'), 'Keyboard focus visible');
    await reload();
    check(await page.locator('[data-detail]').evaluate(el => el.open), 'Reload preserves expanded details');
    check(await page.locator('[data-source-detail]').evaluate(el => el.open), 'Reload preserves source details');
    for (const width of [320, 390, 768, 1440]) {
      await page.setViewportSize({width, height: 1000});
      for (const theme of ['light', 'dark']) {
        await page.evaluate(theme => window.shopdashTheme.setMode(theme), theme);
        await page.screenshot({path: path.join(output, 'fixture-' + width + '-' + theme + '.png'), animations: 'disabled'});
        check(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), width + '/' + theme + ' has no page overflow');
        check(await page.locator('[data-table-region]').evaluate(el => el.clientWidth <= document.documentElement.clientWidth), 'Table scroll stays contained');
      }
    }
    const longReport = JSON.parse(php("require 'app/helpers/AdsPerformance.php'; $f=json_decode(file_get_contents('ops/ads-browser-20260928/product-monthly.response.sanitized.json'),true); $r=AdsPerformance::range('monthly',new DateTimeImmutable('2026-09-28T22:00:00+07:00')); echo json_encode(AdsPerformance::normalize($f,$r,'product'));"));
    payload.shops[0].metrics.performance = longReport;
    await page.selectOption('#ads-period', 'monthly'); await ready(page);
    check(await page.locator('[data-daily-rows] tr').count() === 28, 'All captured dates rendered in long report');
    check(await page.locator('[data-orders]').innerText() === '648', 'Browser monthly card uses checkout, not broad_order=756');
    check(await page.locator('[data-daily-rows] tr').last().locator('td').nth(3).innerText() === '30', 'Daily checkout matches browser audit');
    await page.setViewportSize({width: 390, height: 844});
    await page.locator('[data-table-region]').scrollIntoViewIfNeeded();
    await page.screenshot({path: path.join(output, 'fixture-long-detail-mobile.png'), animations: 'disabled'});
    check(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), 'Long report table contained on mobile');
    const liveReport = JSON.parse(php("require 'app/helpers/AdsPerformance.php'; $f=json_decode(file_get_contents('ops/ads-network-2026-09-28/live-monthly.response.projection.json'),true); $r=AdsPerformance::range('monthly',new DateTimeImmutable('2026-09-28T22:00:00+07:00')); echo json_encode(AdsPerformance::normalize($f,$r,'live'));"));
    payload.shops[0].metrics.performance = liveReport;
    await page.selectOption('#ads-channel', 'live'); await ready(page);
    check(await page.locator('[data-impressions]').innerText() === '-' && await page.locator('[data-orders]').innerText() === '-', 'Live unsupported metrics are not shown as zeros');
    check((await page.locator('[data-source-note]').innerText()).includes('belum terverifikasi'), 'Live mapping limitation available in source details');
    check(await page.locator('[data-sales]').innerText() === 'Rp 0', 'Verified Live currency zero retained');
    const zero = {impressions: 0, clicks: 0, ctr: null, orders: 0, items_sold: 0, sales: 0, ad_cost: 0, roas: null};
    payload.shops[0].metrics.performance = {...report, ...zero, daily: [{date: '2026-09-26', ...zero}]};
    await reload();
    check(await page.locator('[data-clicks]').innerText() === '0', 'Real zero is visible');
    check(await page.locator('[data-roas]').innerText() === '-', 'Undefined ROAS stays missing');
    check(await page.locator('[data-ctr]').innerText() === '-', 'Undefined CTR stays missing');
    payload.shops[0].metrics.performance = {...report, stale: true, error_message: 'Uji: pembaruan gagal.', error_code: 90309999};
    await reload();
    check(await page.locator('[data-status]').innerText() === 'Belum diperbarui', 'Stale report visible');
    check(await page.locator('[data-warning]').isVisible(), 'Stale warning stays outside collapsed details');
    check((await page.locator('[data-note]').innerText()).includes('data terakhir yang berhasil diambil'), 'Stale values explained');
    payload.shops[0].metrics.performance = {...report, available: false, error_code: 90309999, error_message: 'Laporan iklan ditolak Shopee (kode 90309999).'};
    payload.shops[0].metrics.ads_expense_today = 123;
    await reload();
    check(await page.locator('[data-ad-cost]').innerText() === '-', 'Unavailable report never uses meta cost');
    check(await page.locator('[data-status]').innerText() === 'Gagal dimuat', 'Access rejection distinct from connected shop');
    check((await page.locator('[data-source-note]').innerText()).includes('90309999'), 'Technical error retained in details');
    check(await page.locator('[data-daily-rows] tr').count() === 0, 'Unavailable report never shows old detail');
    payload.shops[0].metrics.performance.request_state = 'skipped';
    await reload();
    check(await page.locator('[data-status]').innerText() === 'Belum diambil', 'Skipped request distinct from an actual rejection');
    payload.shops[0].session_status = 'expired';
    payload.shops[0].shop_name = '<img src=x onerror=alert(1)> (uji keamanan)';
    await reload();
    check(await page.locator('#ads-session-warning').isVisible(), 'Expired session warning');
    check((await page.locator('[data-recovery]').getAttribute('href')).endsWith('/panel/shops'), 'Expired shop recovery points to connection settings');
    check(await page.locator('#ads-grid img').count() === 0, 'Shop names treated as text');
    for (const href of ['/panel/shops', '/panel/sync']) check((await context.request.get(base + href)).status() === 200, 'Help link works: ' + href);
    payload.shops = []; await reload();
    check((await page.locator('#ads-state').innerText()).includes('Belum ada toko'), 'Empty state');
    check(!await page.locator('#ads-session-warning').isVisible(), 'Old warning cleared on empty response');
    for (const failure of ['error', 'invalid', 'network']) {
      mode = failure; await reload();
      check(await page.locator('#ads-grid article').count() === 0, 'Failure hides old results: ' + failure);
      check(!await page.locator('#ads-refresh').isDisabled(), 'Failure allows retry: ' + failure);
      if (failure === 'network') check((await page.locator('#ads-state').innerText()).includes('Koneksi ke aplikasi gagal'), 'Network error has actionable Indonesian copy');
    }
    mode = 'ok'; delay = 250;
    await page.selectOption('#ads-period', 'weekly');
    check(await page.locator('#ads-grid').isHidden(), 'Loading hides data from previous filter');
    await page.selectOption('#ads-period', 'monthly'); await ready(page);
    check(await page.inputValue('#ads-period') === 'monthly', 'Rapid filter change keeps newest request');
    check(errors.length === 0, 'No browser runtime errors: ' + errors.join('; '));
    console.log('PASS: ' + checks + ' browser checks');
  } finally {
    await browser?.close();
    testSession.cleanup();
  }
})().catch(error => {console.error(error); process.exitCode = 1;});
