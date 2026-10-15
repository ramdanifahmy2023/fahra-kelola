const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { execFileSync } = require('node:child_process');
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');

const base = process.env.DASHBOARD_DISCLOSURES_URL || 'http://127.0.0.1:59177';
assert.ok(['127.0.0.1', 'localhost', '[::1]'].includes(new URL(base).hostname), 'Use the loopback dashboard-preview.php fixture');
const output = path.join(__dirname, '../tmp/dashboard-disclosures');
fs.mkdirSync(output, { recursive: true });
const disclosures = [
  ['Tahap pesanan', '.dashboard-detail-section'],
  ['Dana per toko', '#finance-summary-panel'],
  ['Sumber omset', '.dashboard-metric-source'],
  ['Data hari ini', '#dashboard-activity .finance-metric-detail'],
  ['Angka per jam', '.dashboard-sales-chart > details'],
  ['Opsi toko', '.dashboard-filter-help'],
  ['Perhitungan laba', '.finance-profit-breakdown'],
  ['Batas perhitungan', '#finance-profit-panel > .finance-explanation'],
];

(async () => {
  const browser = await chromium.launch({ headless: true });
  try {
    const context = await browser.newContext({ locale: 'id-ID', timezoneId: 'Asia/Jakarta', reducedMotion: 'reduce' });
    const errors = [];
    let summaryRequests = 0;
    await context.route('**/*', async route => {
      const url = new URL(route.request().url());
      if (url.origin !== new URL(base).origin) return route.abort();
      if (route.request().method() !== 'GET') {
        return route.fulfill({ json: { status: 'success', message: 'Pratinjau: tidak ada perubahan toko.' } });
      }
      if (url.pathname === '/procFinance/summary') {
        summaryRequests++;
        const response = await route.fetch();
        const data = await response.json();
        data.stores[0].pending_detail = 100;
        return route.fulfill({ json: data });
      }
      return route.continue();
    });
    const page = await context.newPage();
    page.on('pageerror', error => errors.push(error.message));
    await page.goto(base + '/panel');
    assert.match(await page.locator('main').innerText(), /Data contoh, tidak terhubung ke toko/);
    const loaded = () => page.waitForFunction(() => ['finance-summary-panel', 'dashboard-activity', 'dashboard-operations'].every(id => document.getElementById(id)?.getAttribute('aria-busy') === 'false'));
    await loaded();

    if (!await page.locator('#finance-profit-panel').count()) {
      const fragment = execFileSync('php', ['-r', '$financeDashboard=true;require $argv[1];', path.join(__dirname, '../app/views/panel/templates/finance-profit.php')], { encoding: 'utf8' });
      await page.locator('#finance-page').evaluate((root, html) => root.insertAdjacentHTML('beforeend', html), fragment);
    }
    await page.evaluate(async () => {
      const root = document.getElementById('finance-page');
      window.disclosureTestProfit = window.createFinanceProfitView({ root, request: async endpoint => (await fetch('/procFinance/' + endpoint)).json() });
      window.disclosureTestFilters = { shops: '', period: 'month', start: document.getElementById('finance-start').value, end: document.getElementById('finance-end').value, basis: 'accrual' };
      await window.disclosureTestProfit.load(window.disclosureTestFilters);
    });
    await page.locator('.finance-profit-breakdown').waitFor();
    const warningsVisible = async () => {
      assert.equal(await page.locator('.dashboard-detail-section').evaluate(el => el.open), false);
      assert.equal(await page.locator('#finance-summary-panel').evaluate(el => el.open), false);
      assert.equal(await page.locator('#finance-pending-summary').isVisible(), true);
      assert.match(await page.locator('#finance-pending-summary').innerText(), /Rincian berbeda/);
      assert.equal(await page.locator('#finance-store-summary').isVisible(), true);
      assert.match(await page.locator('#finance-store-summary').innerText(), /selisih rincian/);
    };
    const noOverflow = async label => assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), true, 'No page overflow: ' + label);

    for (const width of [320, 500, 999, 1600]) {
      await page.setViewportSize({ width, height: 1000 });
      for (const theme of ['light', 'dark']) {
        await page.evaluate(theme => document.documentElement.setAttribute('data-theme', 'shopdash-' + theme), theme);
        await page.locator('#finance-page details').evaluateAll(elements => elements.forEach(el => { el.open = false; }));
        await warningsVisible();
        for (const [name, selector] of disclosures) {
          const detail = page.locator(selector).first();
          const summary = detail.locator(':scope > summary');
          await page.keyboard.press('Tab');
          await summary.focus();
          const closed = await summary.evaluate(el => {
            const css = getComputedStyle(el), arrow = getComputedStyle(el, '::after');
            return { height: el.getBoundingClientRect().height, focusVisible: el.matches(':focus-visible'), focus: (css.outlineStyle !== 'none' && parseFloat(css.outlineWidth) >= 2) || css.boxShadow !== 'none', listStyle: css.listStyleType, display: css.display, arrowContent: arrow.content, arrowWidth: parseFloat(arrow.width), arrowTransform: arrow.transform, duplicateIcons: [...el.querySelectorAll('.finance-disclosure-icon')].filter(icon => icon.checkVisibility()).length };
          });
          const label = `${name}, ${width}px, ${theme}`;
          assert.ok(closed.height >= 43.5, '44px target: ' + label);
          assert.ok(closed.focusVisible && closed.focus, 'Visible keyboard focus: ' + label);
          assert.ok(closed.listStyle === 'none' && closed.display !== 'list-item', 'Native triangle replaced: ' + label);
          assert.ok(!['none', 'normal'].includes(closed.arrowContent) && closed.arrowWidth > 0, 'Visible disclosure indicator: ' + label);
          assert.equal(closed.duplicateIcons, 0, 'Only one indicator: ' + label);
          await page.keyboard.press('Enter');
          assert.equal(await detail.evaluate(el => el.open), true, 'Enter opens: ' + label);
          assert.ok(await detail.evaluate(el => [...el.children].some(child => child.tagName !== 'SUMMARY' && child.checkVisibility())), 'Open content is visible: ' + label);
          assert.notEqual(await summary.evaluate(el => getComputedStyle(el, '::after').transform), closed.arrowTransform, 'Indicator reflects expanded state: ' + label);
          await noOverflow(label + ', open');
          if (selector === '.dashboard-filter-help') {
            const options = await page.locator('.dashboard-filter-options').boundingBox();
            assert.ok(options.x >= 0 && options.x + options.width <= width + 1, 'Shop options fit viewport: ' + label);
            await page.keyboard.press('Escape');
            assert.equal(await detail.evaluate(el => el.open), false, 'Escape closes shop options');
            assert.equal(await summary.evaluate(el => el === document.activeElement), true, 'Escape returns focus to options trigger');
            await page.keyboard.press('Enter');
          }
          await page.keyboard.press('Space');
          assert.equal(await detail.evaluate(el => el.open), false, 'Space closes: ' + label);
          await page.keyboard.press('Space');
          assert.equal(await detail.evaluate(el => el.open), true, 'Space opens: ' + label);
          await page.keyboard.press('Enter');
          assert.equal(await detail.evaluate(el => el.open), false, 'Enter closes: ' + label);
        }
        await warningsVisible();
        await noOverflow(`${width}px, ${theme}, closed`);
        await page.evaluate(() => { document.activeElement.blur(); scrollTo(0, 0); });
        await page.screenshot({ path: path.join(output, `closed-${width}-${theme}.png`), fullPage: true, animations: 'disabled' });
        for (const [, selector] of disclosures.filter(([, selector]) => selector !== '.dashboard-filter-help')) await page.locator(selector).first().evaluate(el => { el.open = true; });
        await noOverflow(`${width}px, ${theme}, all open`);
        await page.screenshot({ path: path.join(output, `open-${width}-${theme}.png`), fullPage: true, animations: 'disabled' });
      }
    }

    const source = page.locator('.dashboard-metric-source').first();
    await source.evaluate(el => { el.open = true; });
    await source.locator('summary').focus();
    const before = summaryRequests;
    const response = page.waitForResponse(response => new URL(response.url()).pathname === '/procFinance/summary');
    await page.evaluate(() => document.dispatchEvent(new Event('visibilitychange')));
    await response;
    await loaded();
    assert.ok(summaryRequests > before, 'A summary refresh was actually requested');
    assert.equal(await source.evaluate(el => el.open), true, 'Open source survives refresh');
    assert.equal(await source.locator('summary').evaluate(el => el === document.activeElement), true, 'Source focus survives refresh');
    const profit = page.locator('.finance-profit-breakdown');
    await profit.evaluate(el => { el.open = true; });
    await profit.locator('summary').focus();
    await page.evaluate(() => window.disclosureTestProfit.load(window.disclosureTestFilters));
    assert.equal(await profit.evaluate(el => el.open), true, 'Open calculation survives refresh');
    assert.equal(await profit.locator('summary').evaluate(el => el === document.activeElement), true, 'Calculation focus survives refresh');
    assert.deepEqual(errors, [], 'No page errors');
    console.log('PASS: eight Dashboard disclosures, four widths, both themes, keyboard, warnings, popover, overflow and refresh state. Screenshots: ' + output);
    await context.close();
  } finally {
    await browser.close();
  }
})().catch(error => { console.error(error); process.exit(1); });
