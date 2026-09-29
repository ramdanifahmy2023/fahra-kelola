const assert = require('node:assert/strict');
const {execFileSync} = require('node:child_process');
const path = require('node:path');
const {chromium} = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const root = path.resolve(__dirname, '..');
const base = process.env.PRODUCTS_TEST_URL || 'http://127.0.0.1:8123';
const php = code => execFileSync('php', ['-r', code], {cwd: root, encoding: 'utf8'}).trim();
const testSession=require('./panel-test-session.cjs')(root);const sid=testSession.sid;

(async () => {
  let browser;
  try {
    browser = await chromium.launch({headless: true});
    const context = await browser.newContext();
    await context.route('**/procWorkspace/**',r=>r.fulfill({json:{status:'success'}}));
    await context.addCookies([{name: 'PHPSESSID', value: sid, url: base}]);
    const page = await context.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.goto(base + '/panel/products?limit=20&page=2');
    const firstShop = await page.inputValue('#selectedShopId');
    const links = page.locator('[data-product-shop]');
    assert.ok(await links.count() >= 2, 'At least two local shops required');
    const targetLink = page.locator('[data-product-shop]:not([aria-current])').first();
    const targetId = await targetLink.getAttribute('data-product-shop');
    const targetUrl = await targetLink.getAttribute('href');
    const expectedResponse = await context.request.get(targetUrl);
    assert.equal(expectedResponse.status(), 200);
    const expectedHtml = await expectedResponse.text();
    const expectedIds = await page.evaluate(html => [...new DOMParser().parseFromString(html, 'text/html').querySelectorAll('tr[id^="product-"]')].map(row => row.id), expectedHtml);
    await page.locator('#page-loader').waitFor({state: 'hidden'});
    await page.locator('#selectedShopDisplay').click();
    await Promise.all([page.waitForURL(targetUrl), targetLink.click()]);
    assert.equal(await page.inputValue('#selectedShopId'), targetId);
    assert.equal(new URL(page.url()).searchParams.get('limit'), '20');
    assert.equal(new URL(page.url()).searchParams.has('page'), false);
    assert.deepEqual(await page.locator('tr[id^="product-"]').evaluateAll(rows => rows.map(row => row.id)), expectedIds);
    if (!expectedIds.length) assert.ok(await page.locator('tbody').innerText());
    await page.goBack();
    assert.equal(await page.inputValue('#selectedShopId'), firstShop);
    assert.equal(new URL(page.url()).searchParams.get('page'), '2');
    await page.goto(base + '/panel/products?shop_id=' + firstShop + '&limit=20&page=2&stock=critical&highlight=123');
    const criticalLink = page.locator('[data-product-shop="' + targetId + '"]');
    const criticalUrl = await criticalLink.getAttribute('href');
    assert.equal(new URL(criticalUrl).searchParams.get('stock'), 'critical');
    assert.equal(new URL(criticalUrl).searchParams.has('highlight'), false);
    await page.locator('#page-loader').waitFor({state: 'hidden'});
    await page.locator('#selectedShopDisplay').click();
    await criticalLink.focus();
    await Promise.all([page.waitForURL(criticalUrl), page.keyboard.press('Enter')]);
    assert.equal(await page.inputValue('#selectedShopId'), targetId);
    assert.equal(new URL(page.url()).searchParams.get('stock'), 'critical');
    assert.deepEqual(errors, []);
    console.log('PASS: shop selection loads target products, resets pagination, preserves filters, supports keyboard and Back');
  } finally {
    await browser?.close();
    testSession.cleanup();
  }
})().catch(error => {console.error(error); process.exitCode = 1;});
