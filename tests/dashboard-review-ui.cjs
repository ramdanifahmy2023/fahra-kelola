const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const base = process.env.DASHBOARD_REVIEW_URL || 'http://127.0.0.1:59171';
const output = path.join(__dirname, '../tmp/dashboard-review');
fs.mkdirSync(output, { recursive: true });

(async () => {
  const browser = await chromium.launch({ headless: true });
  try {
    const context = await browser.newContext({ locale: 'id-ID', timezoneId: 'Asia/Jakarta', reducedMotion: 'reduce' });
    let mode = 'normal';
    let mutations = 0;
    const errors = [], requests = [], external = [];
    await context.route('**/*', async route => {
      const url = new URL(route.request().url());
      if (url.origin !== base) { external.push(url.origin); return route.abort(); }
      if (route.request().method() !== 'GET') {
        assert.ok(['/procFinance/sync', '/procWorkspace/save'].includes(url.pathname), 'Only expected mock mutations');
        if (url.pathname === '/procFinance/sync') mutations++;
        return route.fulfill({ json: { status: 'success', message: 'Pratinjau: tidak ada sinkronisasi toko.' } });
      }
      if (['/procFinance/summary', '/procDashboard/overview', '/procRealtime/metrics'].includes(url.pathname)) {
        requests.push(url.pathname + url.search);
        if (mode === 'error') return route.fulfill({ status: 502, json: { status: 'error', message: 'Koneksi data contoh gagal.' } });
        const response = await route.fetch();
        const data = await response.json();
        if (mode === 'partial') {
          if (url.pathname === '/procFinance/summary') {
            data.stores[0].wallet.amount = 0;
            if (data.stores[1]) data.stores[1].wallet.amount = null;
            data.stores[0].pending_detail = 100;
          }
          if (url.pathname === '/procRealtime/metrics') {
            data.metrics.key_metrics.sales = null;
            data.metrics.metric_coverage.sales = 0;
            data.metrics.metric_coverage.pv = 0;
            data.metrics.key_metrics.pv = null;
          }
        }
        if (mode === 'empty' && url.pathname === '/procRealtime/metrics') {
          Object.keys(data.metrics.key_metrics).forEach(k => { data.metrics.key_metrics[k] = 0; });
          data.metrics.sales_hourly = [];
          data.metrics.top_sales_items = [];
        }
        return route.fulfill({ json: data });
      }
      return route.continue();
    });
    const page = await context.newPage();
    page.on('pageerror', error => errors.push(error.message));
    const loaded = () => page.waitForFunction(() => ['finance-summary-panel', 'dashboard-activity', 'dashboard-operations'].every(id => document.getElementById(id)?.getAttribute('aria-busy') === 'false'));
    const refresh = async () => {
      await page.evaluate(() => document.dispatchEvent(new Event('visibilitychange')));
      await loaded();
    };
    await page.goto(base + '/panel');
    assert.match(await page.locator('main').innerText(), /Data contoh, tidak terhubung ke toko/);
    await loaded();
    assert.equal(await page.locator('#dashboard-live-metrics > div').count(), 4);
    assert.equal(await page.locator('#dashboard-traffic-metrics > div').count(), 2);
    assert.match(await page.locator('[data-live-metric="sales"]').innerText(), /2\.700\.000/);
    assert.match(await page.locator('#dashboard-attention').innerText(), /6 produk/);
    assert.match(await page.locator('#dashboard-attention').innerText(), /1 koneksi perlu diperiksa/);
    assert.match(await page.locator('#finance-pending-summary').innerText(), /Sebagian status belum dipastikan/);
    assert.equal(await page.locator('.dashboard-detail-section').evaluate(el => el.open), false);

    const contrast = [];
    for (const width of [320, 500, 999, 1600]) {
      await page.setViewportSize({ width, height: 1000 });
      for (const theme of ['light', 'dark']) {
        await page.locator('#theme-menu-button').click();
        await page.locator(`[data-theme-choice="${theme}"]`).click();
        assert.equal(await page.locator('html').getAttribute('data-theme'), 'shopdash-' + theme);
        assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), `No page overflow ${width}/${theme}`);
        const targets = await page.locator('#finance-page :is(button,select,summary,a)').evaluateAll(elements => elements.filter(el => el.checkVisibility()).filter(el => el.getBoundingClientRect().height < 43.5).map(el => ({ tag: el.tagName, id: el.id, text: el.textContent.trim(), height: el.getBoundingClientRect().height })));
        assert.deepEqual(targets, [], `44px controls ${width}/${theme}`);
        const primary = page.locator('[data-live-metric="sales"] dd');
        assert.ok(await primary.evaluate(el => el.scrollWidth <= el.clientWidth + 1), 'Amount fits');
        await page.locator('#finance-shops-trigger').focus();
        await page.keyboard.press('ArrowDown');
        const menu = await page.locator('#finance-shops-options').boundingBox();
        assert.ok(menu.x >= 0 && menu.x + menu.width <= width + 1, 'Shop picker stays in viewport');
        await page.keyboard.press('Escape');
        assert.equal(await page.locator('#finance-shops-trigger').evaluate(el => el === document.activeElement), true);
        await page.locator('.dashboard-filter-help > summary').focus(); await page.keyboard.press('Enter');
        const options = await page.locator('.dashboard-filter-options').boundingBox();
        assert.ok(options.x >= 0 && options.x + options.width <= width + 1, 'Shop options stay in viewport');
        await page.keyboard.press('Escape');
        assert.equal(await page.locator('.dashboard-filter-help').evaluate(el => el.open), false);
        if (width === 320) {
          await page.locator('#panel-menu-button').click();
          assert.equal(await page.locator('#panel-menu-button').getAttribute('aria-expanded'), 'true');
          await page.keyboard.press('Escape');
          assert.equal(await page.locator('#panel-menu-button').getAttribute('aria-expanded'), 'false');
        }
        await page.evaluate(() => { document.activeElement.blur(); scrollTo(0, 0); });
        await page.screenshot({ path: path.join(output, `dashboard-${width}-${theme}.png`), animations: 'disabled', fullPage: width === 1600 });
        if (width === 1600) {
          await page.screenshot({ path: path.join(output, `dashboard-desktop-${theme}.png`), animations: 'disabled' });
          const pairs = await page.evaluate(() => {
            const ctx = document.createElement('canvas').getContext('2d');
            const rgb = color => { ctx.clearRect(0,0,1,1); ctx.fillStyle=color; ctx.fillRect(0,0,1,1); return [...ctx.getImageData(0,0,1,1).data]; };
            const lum = c => c.slice(0,3).map(v => v/255).map(v => v<=.04045?v/12.92:((v+.055)/1.055)**2.4).reduce((n,v,i)=>n+v*[.2126,.7152,.0722][i],0);
            return ['#dashboard-live-scope', '[data-live-metric="sales"] dd', '.dashboard-coverage', '.dashboard-source-footer', '.finance-meta', '.dashboard-attention a', '.dashboard-jump-nav a', '.dashboard-chart-bar', '#finance-filters .btn-primary'].map(selector => {
              const el=document.querySelector(selector); let node=el,bg;
              while(node) { bg=rgb(getComputedStyle(node).backgroundColor); if(bg[3]===255) break; node=node.parentElement; }
              const a=lum(rgb(getComputedStyle(el)[selector==='.dashboard-chart-bar'?'fill':'color'])),b=lum(bg);
              return {selector,ratio:(Math.max(a,b)+.05)/(Math.min(a,b)+.05),min:selector==='.dashboard-chart-bar'?3:4.5};
            });
          });
          for (const pair of pairs) assert.ok(pair.ratio >= pair.min, theme + ': ' + JSON.stringify(pair));
          contrast.push({ theme, pairs });
        }
      }
    }
    await page.locator('.dashboard-traffic > summary').focus();
    await page.keyboard.press('Enter');
    assert.equal(await page.locator('#dashboard-traffic-metrics').isVisible(), true);
    await page.keyboard.press('Enter');
    const source = page.locator('.dashboard-metric-source').first();
    await source.locator('summary').focus(); await page.keyboard.press('Enter');
    await refresh();
    assert.equal(await source.evaluate(el => el.open), true, 'Source disclosure survives polling');
    assert.equal(await source.locator('summary').evaluate(el => el === document.activeElement), true, 'Source focus survives polling');
    const pendingSource = page.locator('.finance-balances .finance-metric-detail').first();
    await pendingSource.locator('summary').click();
    await pendingSource.locator('a').click();
    assert.equal(await page.locator('.dashboard-detail-section').evaluate(el => el.open), true, 'Pending link reveals collapsed content');
    await page.locator('.dashboard-detail-section > summary').click();
    mode = 'partial'; await refresh();
    assert.match(await page.locator('#finance-wallet').innerText(), /Rp\s*0/);
    assert.equal(await page.locator('#finance-wallet-quality .is-partial').isVisible(), true);
    assert.match(await page.locator('[data-live-metric="sales"] dd').innerText(), /Belum tersedia/);
    assert.equal(await page.locator('.dashboard-traffic').evaluate(el => el.open), true, 'Incomplete traffic cannot be hidden');
    assert.match(await page.locator('#finance-pending-summary').innerText(), /Rincian berbeda/);
    assert.equal(await page.locator('#finance-pending-summary').isVisible(), true, 'Reconciliation warning survives collapsed details');
    mode = 'empty'; await refresh();
    assert.match(await page.locator('[data-live-metric="sales"] dd').innerText(), /Rp\s*0/);
    assert.match(await page.locator('#dashboard-top-products').innerText(), /Belum ada produk terjual/);
    assert.match(await page.locator('#dashboard-hourly').innerText(), /Data per jam belum tersedia/);
    mode = 'error'; await page.reload(); await loaded();
    assert.match(await page.locator('#dashboard-live-metrics').innerText(), /Penjualan belum tersedia/);
    assert.match(await page.locator('#dashboard-attention').innerText(), /belum berhasil diperbarui/);
    assert.equal(await page.locator('#finance-error').isVisible(), true);
    mode = 'normal'; await page.locator('#finance-retry').click(); await page.locator('#dashboard-live-refresh').click(); await page.locator('#dashboard-operations-retry').click(); await loaded();
    await page.locator('#finance-shops-trigger').click(); await page.locator('[data-shop-value="2"]').click();
    assert.equal(await page.locator('#finance-filter-draft').isVisible(), true);
    await page.locator('#finance-filters button[type="submit"]').click(); await loaded();
    assert.equal(await page.locator('.finance-store').count(), 1);
    assert.match(await page.locator('#dashboard-live-scope').innerText(), /Toko Contoh Aksesori/);
    assert.match(await page.locator('[data-live-metric="sales"] dd').innerText(), /1\.350\.000/);
    assert.ok(requests.some(url => url.startsWith('/procRealtime/metrics?') && url.includes('2')));
    await page.locator('#finance-period').selectOption('custom');
    assert.equal(await page.locator('#finance-custom-dates').isVisible(), true);
    await page.locator('#finance-start').fill('2026-09-01'); await page.locator('#finance-end').fill('2026-09-07');
    await page.locator('#finance-filters button[type="submit"]').click(); await loaded();
    assert.match(await page.locator('#finance-scope-label').innerText(), /1 Sep 2026 sampai 7 Sep 2026/);
    assert.match(await page.locator('#dashboard-live-scope').innerText(), /terpisah dari periode/);
    assert.match(await page.locator('#finance-open').getAttribute('href'), /start=2026-09-01/);
    await page.locator('#finance-sync').click(); await loaded(); assert.equal(mutations, 1);
    await page.setViewportSize({ width: 500, height: 1000 });
    await page.evaluate(() => document.documentElement.style.fontSize='200%');
    assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), '200% text reflow');
    await page.goto(base + '/panel?preview=empty');
    assert.match(await page.locator('.dashboard-empty h2').innerText(), /Mulai dengan menghubungkan toko/);
    assert.equal(await page.locator('.dashboard-jump-nav').count(), 0, 'No dead anchors without shops');
    assert.deepEqual(errors, []); assert.deepEqual(external, []);
    fs.writeFileSync(path.join(output, 'checks.json'), JSON.stringify({ contrast, errors, external, mutations }, null, 2));
    console.log('PASS: full panel shell, 8 width/theme views, contrast, 44px targets, mobile menu, source/pending disclosures, null/zero/partial/error/empty states, shop/date scope, mocked sync, keyboard focus and 200% text. No database or upstream requests.');
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exit(1); });
