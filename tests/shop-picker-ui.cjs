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
    const output = path.join(root, 'tmp/shop-picker-ui');
    fs.mkdirSync(output, {recursive: true});
    for (const section of ['products', 'orders', 'customers']) {
      await page.goto(base + '/panel/' + section + '?limit=20&page=2');
      if (await page.locator('#page-loader').count()) await page.locator('#page-loader').waitFor({state: 'hidden'});
      for (const width of [320, 390, 999, 1600]) {
        await page.setViewportSize({width, height: 1008});
        for (const theme of ['light', 'dark']) {
          await page.evaluate(mode => window.shopdashTheme.setMode(mode), theme);
          await page.locator('#selectedShopDisplay').click();
          const list = page.locator('.shop-picker-list');
          await list.waitFor({state: 'visible'});
          const trigger = await page.locator('#selectedShopDisplay').boundingBox();
          const bounds = await list.boundingBox();
          assert.ok(bounds.y >= trigger.y + trigger.height, section + ': menu opens below trigger');
          assert.ok(bounds.x >= 0 && bounds.x + bounds.width <= width + 1, section + ': menu stays inside viewport');
          const rows = await list.locator('li').evaluateAll(items => items.map(item => {const r = item.getBoundingClientRect(); return {x: r.x, y: r.y, bottom: r.bottom};}));
          for (let i = 1; i < rows.length; i++) {
            assert.ok(Math.abs(rows[i].x - rows[0].x) < 1, 'One vertical column');
            assert.ok(rows[i].y >= rows[i - 1].bottom - 1, 'Rows never wrap sideways or overlap');
          }
          assert.ok(await list.evaluate(el => el.scrollWidth <= el.clientWidth), 'No horizontal scrolling in menu');
          await list.locator('a').last().scrollIntoViewIfNeeded();
          await list.locator('a').first().scrollIntoViewIfNeeded();
          await page.screenshot({path: path.join(output, `${section}-${width}-${theme}.png`)});
          await page.locator('h2').first().click();
        }
      }
      await page.locator('#selectedShopDisplay').click();
      const link = page.locator('.shop-picker-list a:not([aria-current])').first();
      const target = await link.getAttribute('href');
      const id = new URL(target).searchParams.get('shop_id');
      const expected = await context.request.get(target);
      assert.equal(expected.status(), 200);
      const expectedRows = await page.evaluate(html => new DOMParser().parseFromString(html, 'text/html').querySelector('tbody').textContent, await expected.text());
      await link.focus();
      await Promise.all([page.waitForURL(target), page.keyboard.press('Enter')]);
      assert.equal(await page.inputValue('#selectedShopId'), id);
      assert.equal(new URL(page.url()).searchParams.has('page'), false);
      assert.equal(new URL(page.url()).searchParams.get('limit'), '20');
      assert.equal(await page.locator('tbody').textContent(), expectedRows, 'Switch loads selected shop rows');
      if (section === 'orders') assert.ok(new URL(target).searchParams.has('startDate'));
      if (section === 'customers') {
        const paginationLinks = await page.locator('.join a').evaluateAll(links => links.map(a => a.href));
        for (const href of paginationLinks) assert.equal(new URL(href).searchParams.get('shop_id'), id);
      }
    }
    assert.deepEqual(errors, []);
    console.log('PASS: three shop pickers, vertical scrolling, responsive light/dark, keyboard navigation, selected shop data, and filter preservation');
  } finally {
    await browser?.close();
    php("session_id('" + sid + "'); session_start(); $_SESSION=[]; session_destroy();");
  }
})().catch(error => {console.error(error); process.exitCode = 1;});
