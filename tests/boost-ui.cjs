const assert = require('node:assert/strict');
const {execFileSync} = require('node:child_process');
const path = require('node:path');
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
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.route('**/procproducts/boost_products?**', route => route.fulfill({json: {
      status: 'success', shop: {session_status: expired ? 'expired' : 'connected'},
      summary: {used_count: 0, remaining_count: 5}, total: 6, history: [],
      products: Array.from({length: 6}, (_, i) => ({id: i + 1, name: 'Produk uji ' + (i + 1), show_boost_button: true}))
    }}));
    await page.goto(base + '/panel/boost');
    const card = page.locator('[data-card]').first();
    const choices = card.locator('input[data-product-id]');
    await choices.last().waitFor();
    assert.equal(await card.locator('[data-selection-count]').innerText(), '0/5 dipilih');
    for (let i = 1; i < 6; i++) await choices.nth(i).check();
    // Selecting an earlier row must not silently unselect a different product.
    await choices.first().click();
    assert.equal(await choices.first().isChecked(), false);
    assert.equal(await choices.last().isChecked(), true);
    assert.equal(await card.locator('input:checked').count(), 5);
    assert.equal(await card.locator('[data-selection-count]').innerText(), '5/5 dipilih');
    await choices.last().uncheck();
    assert.equal(await card.locator('[data-selection-count]').innerText(), '4/5 dipilih');
    expired = true;
    await card.locator('[data-search-button]').click();
    await card.locator('[data-selection-help]').waitFor({state: 'visible'});
    assert.equal(await card.locator('[data-selection-count]').innerText(), '0/5 dipilih');
    await choices.first().check();
    assert.match(await card.locator('[data-selection-help]').innerText(), /Perbarui koneksi/);
    assert.equal(await card.locator('[data-boost]').isDisabled(), true);
    for (const width of [320, 390, 768, 1440]) {
      await page.setViewportSize({width, height: 900});
      assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), 'No overflow at ' + width);
    }
    assert.deepEqual(errors, []);
    console.log('PASS: boost selection limit, reset, session warning, and responsive layout');
  } finally {
    await browser?.close();
    php("session_id('" + sid + "'); session_start(); $_SESSION=[]; session_destroy();");
  }
})().catch(error => {console.error(error); process.exitCode = 1;});
