const assert = require('node:assert/strict');
const {execFileSync} = require('node:child_process');
const path = require('node:path');
const fs = require('node:fs');
const {chromium} = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const root = path.resolve(__dirname, '..');
const base = 'http://127.0.0.1:8123';
const php = code => execFileSync('php', ['-r', code], {cwd: root, encoding: 'utf8'}).trim();
const sid = php("chdir('public'); require '../app/init.php'; $d=new Database(); $d->query('SELECT id,name,email FROM accounts LIMIT 1'); $a=$d->single(); session_id(bin2hex(random_bytes(24))); session_start(); $_SESSION['auth_user']=$a; echo session_id(); session_write_close();");
(async () => {
  let browser;
  try {
    browser = await chromium.launch({headless: true});
    const context = await browser.newContext();
    await context.addCookies([{name: 'PHPSESSID', value: sid, url: base}]);
    const page = await context.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    const output = path.join(root, 'tmp/shop-branding-ui');
    fs.mkdirSync(output, {recursive: true});
    for (const section of ['ads', 'promotions', 'boost']) {
      await page.goto(base + '/panel/' + section);
      await page.locator('[data-shop-logo]').first().waitFor();
      const logos = await page.locator('[data-shop-logo]').count();
      assert.ok(logos > 0);
      const sources = await page.locator('#shop-logo-data').textContent();
      const known = JSON.parse(sources).map(shop => shop.logo).filter(Boolean);
      for (const image of await page.locator('[data-shop-logo] img').all()) assert.ok(known.includes(await image.getAttribute('src')), 'Logo comes from connected shop data');
      assert.ok(await page.locator('[data-shop-logo] img').count() > 0, section + ' displays real logos');
      for (const width of [320, 999, 1600]) {
        await page.setViewportSize({width, height: 1008});
        for (const theme of ['light', 'dark']) {
          await page.evaluate(mode => window.shopdashTheme.setMode(mode), theme);
          const logo = await page.locator('[data-shop-logo]').first().boundingBox();
          const name = await page.locator('[data-shop-name]').first().boundingBox();
          assert.equal(logo.width, 40);
          assert.equal(logo.height, 40);
          assert.ok(name.x >= logo.x + logo.width + 11, 'Logo never overlaps shop name');
          assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), section + ' fits viewport');
          await page.screenshot({path: path.join(output, `${section}-${width}-${theme}.png`), animations: 'disabled'});
        }
      }
      await page.locator('[data-shop-logo] img').first().evaluate(img => img.dispatchEvent(new Event('error')));
      assert.ok(await page.locator('[data-shop-logo] .material-symbols-outlined').count() > 0, 'Broken logo shows fallback');
    }
    await page.goto(base + '/panel/reports');
    for (const width of [320, 999, 1600]) {
      await page.setViewportSize({width, height: 1008});
      await page.locator('#selectedShopDisplay').click();
      await page.locator('.shop-picker-list').waitFor({state: 'visible'});
      const bounds = await page.locator('.shop-picker-list li').evaluateAll(rows => rows.map(row => {const r = row.getBoundingClientRect(); return {x:r.x,y:r.y,bottom:r.bottom};}));
      for (let i = 1; i < bounds.length; i++) {
        assert.equal(bounds[i].x, bounds[0].x);
        assert.ok(bounds[i].y >= bounds[i-1].bottom - 1);
      }
      await page.screenshot({path: path.join(output, `reports-${width}.png`), animations: 'disabled'});
      await page.locator('h1').click();
    }
    await page.locator('#selectedShopDisplay').click();
    const option = page.locator('.shop-picker-list a').nth(1);
    const id = await option.getAttribute('data-product-shop');
    const response = page.waitForResponse(res => res.url().includes('/procreports/summary?') && new URL(res.url()).searchParams.get('shop_id') === id);
    await option.focus();
    await page.keyboard.press('Enter');
    await response;
    assert.equal(await page.inputValue('#report-shop'), id);
    assert.ok(await page.locator('#selectedShopDisplay img').count());
    assert.deepEqual(errors, []);
    console.log('PASS: real shop logos, fallback, responsive themes, vertical report picker and filter request');
  } finally {
    await browser?.close();
    php("session_id('" + sid + "'); session_start(); $_SESSION=[]; session_destroy();");
  }
})().catch(error => {console.error(error); process.exitCode = 1;});
