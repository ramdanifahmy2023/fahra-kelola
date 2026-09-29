const assert = require('node:assert/strict');
const {execFileSync} = require('node:child_process');
const path = require('node:path');
const fs = require('node:fs');
const {chromium} = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const root = path.resolve(__dirname, '..');
const base = process.env.ADS_TEST_URL || 'http://127.0.0.1:8123';
const php = code => execFileSync('php', ['-r', code], {cwd: root, encoding: 'utf8'}).trim();
const sid = php("chdir('public'); require '../app/init.php'; $d=new Database(); $d->query('SELECT id,name,email FROM accounts LIMIT 1'); $a=$d->single(); if (!$a) exit(1); session_id(bin2hex(random_bytes(24))); session_start(); $_SESSION['auth_user']=$a; echo session_id(); session_write_close();");

(async () => {
  let browser;
  try {
    browser = await chromium.launch({headless: true});
    const context = await browser.newContext({viewport: {width: 390, height: 844}});
    await context.addCookies([{name: 'PHPSESSID', value: sid, url: base}]);
    const page = await context.newPage();
    let expired = false;
    let remaining = 5;
    let failed = false;
    let empty = false;
    let batch = false;
    let postCount = 0;
    let rejectBoost = false;
    const products = Array.from({length: 10}, (_, i) => ({id: i + 1, name: 'Produk uji ' + (i + 1) + ' dengan nama panjang untuk pemeriksaan tampilan mobile', total_stock: 20, sold_count: 100 - i, show_boost_button: true}));
    products[0].total_stock = 0;
    products[1].show_boost_button = false;
    products[2].disabled_boost_button = true;
    products[3].boost_cooldown = {cooldown_active: true, next_boost_at: '2099-01-01 00:00:00'};
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.route('**/procproducts/boost_products?**', route => {
      const url = new URL(route.request().url());
      assert.equal(url.searchParams.get('limit'), '10');
      assert.equal(url.searchParams.get('page'), '1');
      return route.fulfill(failed ? {status: 503, json: {status: 'error'}} : {json: {
        status: 'success', shop: {session_status: expired ? 'expired' : 'connected'},
        summary: {used_count: 5 - remaining, remaining_count: remaining, batch_active: batch, quota_reset_at: '2099-01-01 00:00:00'},
        total: empty ? 0 : 100, history: [], products: empty ? [] : products
      }});
    });
    await page.route('**/procproducts/boost', async route => {
      postCount++;
      assert.match(route.request().postData(), /\["5","6"\]/);
      await new Promise(resolve => setTimeout(resolve, 150));
      remaining = 0;
      return route.fulfill(rejectBoost ? {status: 409, json: {status: 'error', message: 'Kuota berubah. Muat ulang status.'}} : {json: {status: 'success', result: {success_count: 1, failed_count: 1, unknown_count: 0}}});
    });
    await page.goto(base + '/panel/boost');
    const card = page.locator('[data-card]').first();
    const choices = card.locator('input[data-product-id]');
    await page.waitForFunction(() => [...document.querySelectorAll('[data-card]')].every(card => card.getAttribute('aria-busy') === 'false'));
    assert.equal(await page.locator('[data-product-detail][open]').count(), 0);
    assert.equal(await choices.count(), 10);
    const disclosure = card.locator('[data-product-detail] > summary');
    await disclosure.focus();
    await page.keyboard.press('Enter');
    assert.equal(await card.locator('[data-product-detail]').evaluate(el => el.open), true);
    assert.equal(await card.locator('[data-selection-count]').innerText(), '0/5 dipilih');
    for (let i = 0; i < 4; i++) assert.equal(await choices.nth(i).isDisabled(), true);
    await card.locator('[data-recommend]').click();
    assert.deepEqual(await card.locator('input:checked').evaluateAll(inputs => inputs.map(input => input.dataset.productId)), ['5', '6', '7', '8', '9']);
    assert.equal(await card.locator('[data-boost]').innerText(), 'Naikkan 5 produk');
    await disclosure.click();
    assert.equal(await card.locator('[data-collapsed-count]').innerText(), '· 5 dipilih');
    await disclosure.click();
    assert.equal(await card.locator('input:checked').count(), 5);
    await card.locator('[data-clear]').click();
    assert.equal(await card.locator('input:checked').count(), 0);
    for (let i = 5; i < 10; i++) await choices.nth(i).check();
    // Selecting an earlier row must not silently unselect a different product.
    await choices.nth(4).click();
    assert.equal(await choices.nth(4).isChecked(), false);
    assert.equal(await choices.last().isChecked(), true);
    assert.equal(await card.locator('input:checked').count(), 5);
    assert.equal(await card.locator('[data-selection-count]').innerText(), '5/5 dipilih');
    await choices.last().uncheck();
    assert.equal(await card.locator('[data-selection-count]').innerText(), '4/5 dipilih');
    const refresh = async () => {
      await card.locator('[data-refresh]').click();
      await page.waitForFunction(id => document.querySelector('[data-shop-id="' + id + '"]').getAttribute('aria-busy') === 'false', await card.getAttribute('data-shop-id'));
    };
    remaining = 2;
    await refresh();
    await card.locator('[data-recommend]').click();
    assert.equal(await card.locator('[data-selection-count]').innerText(), '2/2 dipilih');
    await card.locator('[data-boost]').click();
    assert.equal(await card.locator('[data-recommend]').isDisabled(), true);
    await page.waitForFunction(() => document.querySelector('[data-action-result]').textContent.startsWith('Selesai:'));
    await page.waitForFunction(() => document.querySelector('[data-remaining]').textContent === '0');
    assert.equal(postCount, 1);
    assert.match(await card.locator('[data-action-result]').innerText(), /1 berhasil, 1 gagal/);
    assert.equal(await card.locator('input:checked').count(), 0);
    assert.equal(await card.locator('[data-recommend]').isDisabled(), true);
    remaining = 2;
    rejectBoost = true;
    await refresh();
    await card.locator('[data-recommend]').click();
    await card.locator('[data-boost]').click();
    await page.waitForFunction(() => document.querySelector('[data-action-result]').textContent.includes('Kuota berubah'));
    await page.waitForFunction(() => document.querySelector('[data-remaining]').textContent === '0');
    assert.equal(postCount, 2);
    remaining = 5;
    expired = true;
    await refresh();
    await card.locator('[data-selection-help]').waitFor({state: 'visible'});
    assert.equal(await card.locator('[data-selection-count]').innerText(), '0/5 dipilih');
    assert.match(await card.locator('[data-selection-help]').innerText(), /Perbarui koneksi/);
    assert.equal(await card.locator('[data-boost]').isDisabled(), true);
    assert.equal(await card.locator('[data-reconnect]').isVisible(), true);
    expired = false;
    batch = true;
    await refresh();
    assert.equal(await card.locator('[data-recommend]').isDisabled(), true);
    batch = false;
    failed = true;
    await refresh();
    assert.equal(await card.locator('[data-load-state]').isVisible(), true);
    assert.equal(await card.locator('[data-recommend]').isDisabled(), true);
    failed = false;
    empty = true;
    await refresh();
    assert.equal(await choices.count(), 0);
    assert.match(await card.locator('[data-products]').innerText(), /Belum ada produk aktif/);
    empty = false;
    await refresh();
    const second = page.locator('[data-card]').nth(1);
    if (await second.count()) {
      await second.locator('[data-product-detail] > summary').click();
      assert.equal(await page.locator('[data-product-detail][open]').count(), 2);
      assert.equal(await second.locator('input:checked').count(), 0);
    }
    const output = path.join(root, 'tmp/boost-ui');
    fs.mkdirSync(output, {recursive: true});
    assert.equal(await card.locator('.boost-product-row').first().evaluate(el => getComputedStyle(el).display), 'grid');
    for (const width of [320, 390, 768, 1440]) {
      await page.setViewportSize({width, height: 900});
      for (const theme of ['light', 'dark']) {
        await page.evaluate(theme => window.shopdashTheme.setMode(theme), theme);
        await page.evaluate(() => window.scrollTo(0, 0));
        assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), 'No overflow at ' + width + '/' + theme);
        await page.screenshot({path: path.join(output, width + '-' + theme + '.png'), animations: 'disabled'});
      }
    }
    await page.locator('[data-product-detail]').evaluateAll(details => details.forEach(detail => { detail.open = false; }));
    await page.screenshot({path: path.join(output, 'collapsed-desktop.png'), animations: 'disabled'});
    assert.deepEqual(errors, []);
    console.log('PASS: top ten, disclosure, recommendations, eligibility, quota, submission, recovery, keyboard, and responsive themes');
  } finally {
    await browser?.close();
    php("session_id('" + sid + "'); session_start(); $_SESSION=[]; session_destroy();");
  }
})().catch(error => {console.error(error); process.exitCode = 1;});
