const assert = require('node:assert/strict');
const path = require('node:path');
const fs = require('node:fs');
const {execFileSync} = require('node:child_process');
const {chromium} = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const root = path.resolve(__dirname, '..'), base = 'https://shopdash-fixture.test';
(async () => {
  const browser = await chromium.launch({headless:true});
  try {
    const page = await browser.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.route('**/*', route => {
      const url = new URL(route.request().url());
      assert.equal(url.origin, base);
      if (url.pathname.startsWith('/assets/')) return route.fulfill({path:path.join(root, 'public', url.pathname)});
      return route.fulfill({contentType:'text/html', body:execFileSync('php', ['tests/fixtures/shop-picker.php', url.search.slice(1), url.pathname.split('/').pop()], {cwd:root, encoding:'utf8'})});
    });
    await page.goto(base+'/panel/finance');
    const multi = page.locator('#dynamic-multiple-trigger'), multiMenu = page.locator('#dynamic-multiple-options');
    await multi.click();
    assert.equal(await multiMenu.isVisible(), true);
    await multiMenu.locator('[data-shop-value="7"]').click();
    assert.equal(await page.locator('#dynamic-multiple').evaluate(el => el.selectedOptions.length), 1);
    assert.equal(await page.locator('#dynamic-multiple').evaluate(el => el.options[1].selected), false);
    assert.equal(await multiMenu.isVisible(), true, 'Multi-select stays open after rebuilding its options');
    assert.equal(await multiMenu.locator('[data-shop-value="7"]').getAttribute('aria-pressed'), 'true');
    assert.equal(await multiMenu.locator('[data-shop-value="8"]').getAttribute('aria-pressed'), 'false');
    assert.match(await multi.innerText(), /Toko Uji 7/);
    await multiMenu.locator('[data-shop-value="8"]').click();
    assert.equal(await multiMenu.isVisible(), true);
    assert.equal(await page.locator('#dynamic-multiple').evaluate(el => el.selectedOptions.length), 2);
    await multiMenu.locator('[data-shop-value="8"]').focus();
    await page.keyboard.press('Escape');
    await multiMenu.waitFor({state:'hidden'});
    for (const section of ['orders','products','customers']) for (const width of [320,500,999,1600]) for (const theme of ['light','dark']) {
      await page.setViewportSize({width,height:900});
      await page.goto(base+'/panel/'+section+'?shop_id=7&limit=10&startDate=2026-09-01&endDate=2026-09-30&page=3');
      await page.evaluate(theme => document.documentElement.dataset.theme='shopdash-'+theme, theme);
      const trigger = page.locator('#selectedShopDisplay'), menu = page.locator('#shop-picker-options');
      // Safari/macOS pointer activation need not focus a button. Reproduce that event sequence in Chromium.
      await trigger.evaluate(el => el.addEventListener('mousedown', event => event.preventDefault()));
      await trigger.click();
      assert.equal(await trigger.getAttribute('aria-expanded'), 'true');
      assert.equal(await menu.isVisible(), true, `${section}/${width}/${theme}: pointer must open without focus`);
      if (section === 'orders' && [320,1600].includes(width)) {
        fs.mkdirSync(path.join(root,'tmp/shop-picker-pointer'), {recursive:true});
        await page.screenshot({path:path.join(root,`tmp/shop-picker-pointer/${width}-${theme}.png`), animations:'disabled'});
      }
      const box = await menu.boundingBox();
      assert.ok(box.x >= 0 && box.x+box.width <= width+1);
      assert.ok(await menu.evaluate(el => el.scrollHeight > el.clientHeight), 'Long shop list scrolls');
      await menu.locator('[data-product-shop="8"]').click();
      await page.waitForURL('**/*shop_id=8*');
      const url = new URL(page.url());
      assert.equal(url.searchParams.get('limit'), '10');
      assert.equal(url.searchParams.has('page'), false);
      if (section === 'orders') {
        assert.equal(url.searchParams.get('startDate'), '2026-09-01');
        assert.equal(url.searchParams.get('endDate'), '2026-09-30');
      }
      assert.equal(await page.locator('#loaded-shop').innerText(), 'Data toko 8');
      await trigger.focus(); await page.keyboard.press('ArrowDown');
      assert.equal(await menu.isVisible(), true);
      await page.keyboard.press('Escape');
      await menu.waitFor({state:'hidden'});
      await trigger.click(); await page.locator('#outside').click();
      await menu.waitFor({state:'hidden'});
      const dynamic = page.locator('#dynamic-shop-trigger');
      await dynamic.evaluate(el => el.addEventListener('mousedown', event => event.preventDefault()));
      await dynamic.click();
      assert.equal(await page.locator('#dynamic-shop-options').isVisible(), true, 'Dynamic pointer opening without focus');
      await page.locator('#dynamic-shop-options [data-shop-value="8"]').click();
      assert.equal(await page.locator('#dynamic-shop').inputValue(), '8');
      await page.locator('#dynamic-shop-options').waitFor({state:'hidden'});
      await dynamic.click();
      assert.equal(await page.locator('#dynamic-shop-options').isVisible(), true);
      await page.locator('#outside').click();
      await page.locator('#dynamic-shop-options').waitFor({state:'hidden'});
    }
    assert.deepEqual(errors, []);
    console.log('PASS: shared and dynamic shop pickers, pointer without focus, navigation/filter retention, keyboard, outside dismissal, scrolling; 24 viewport/theme/page combinations. Synthetic data only.');
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exit(1); });
