const assert = require('node:assert/strict');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const http = require('node:http');
const {chromium} = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const root = path.resolve(__dirname, '..');
const extension = path.join(root, 'Sellerio Get Cookies');
const output = path.join(root, 'tmp/sellerio-extension-ui');

async function main() {
  fs.mkdirSync(output, {recursive: true});
  const server = http.createServer((req, res) => {
    const name = decodeURIComponent(new URL(req.url, 'http://localhost').pathname.slice(1));
    if (name === 'fixture') return res.end('<title>Sellerio test fixture</title>Local cookie fixture');
    const file = path.join(extension, name);
    if (path.dirname(file) !== extension || !fs.existsSync(file)) {
      res.writeHead(404);
      return res.end();
    }
    const types = {'.html':'text/html', '.css':'text/css', '.js':'text/javascript', '.svg':'image/svg+xml', '.png':'image/png'};
    res.setHeader('Content-Type', types[path.extname(file)] || 'application/octet-stream');
    res.end(fs.readFileSync(file));
  });
  await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
  const base = `http://127.0.0.1:${server.address().port}`;
  let browser, native;
  const profile = fs.mkdtempSync(path.join(os.tmpdir(), 'sellerio-extension-'));
  try {
    browser = await chromium.launch({headless:true});
    const page = await browser.newPage();
    const errors = [];
    page.setDefaultTimeout(10000);
    page.on('pageerror', error => { errors.push(error.message); console.error('Popup error:', error.message); });
    await page.addInitScript(() => {
      window.fixture = {url:'https://seller.example.test/', cookies:[{name:'sellerio_test',value:'fixture_only'}], clipboardMode:'success', fallback:true};
      window.chrome = window.chrome || {};
      Object.assign(window.chrome, {
        runtime: {},
        tabs: {query: async () => {
          if (fixture.queryError) throw new Error('Unavailable');
          return [{id:1,url:fixture.url}];
        }, onUpdated: {addListener() {}}},
        cookies: {getAll: (options, callback) => {
          const finish = () => {
            chrome.runtime.lastError = fixture.cookieError ? {message:'Denied'} : undefined;
            callback(fixture.cookies);
            chrome.runtime.lastError = undefined;
          };
          if (fixture.pending) window.releaseCookies = finish;
          else finish();
        }},
        storage: {local: {set() {}}}
      });
      Object.defineProperty(navigator, 'clipboard', {configurable:true, value:{writeText:async value => {
        if (fixture.clipboardMode === 'failure') throw new Error('Denied');
        window.copiedText = value;
      }}});
      document.execCommand = command => {
        window.copyCommand = command;
        if (fixture.fallback) window.copiedText = document.getElementById('cookieresult').value;
        return fixture.fallback;
      };
    });
    await page.goto(base + '/popup.html');
    await page.waitForFunction(() => document.getElementById('cookie-status').dataset.state === 'ready');
    assert.equal(await page.title(), 'Sellerio Get Cookies');
    assert.match(await page.locator('#cookieresult').inputValue(), /^sellerio_test=fixture_only; /);
    await page.locator('#cookieresult').focus();
    await page.keyboard.press('Tab');
    assert.equal(await page.evaluate(() => document.activeElement.id), 'btncopycookie');
    await page.keyboard.press('Enter');
    assert.equal(await page.evaluate(() => copiedText), await page.locator('#cookieresult').inputValue());
    assert.equal(await page.locator('#copy-label').textContent(), 'Tersalin');
    assert.equal(await page.locator('#btncopycookie svg').count(), 1);

    await page.evaluate(() => {fixture.clipboardMode = 'failure'; window.copiedText = '';});
    await page.locator('#btncopycookie').click();
    assert.equal(await page.evaluate(() => copiedText), await page.locator('#cookieresult').inputValue());
    await page.evaluate(() => {fixture.fallback = false;});
    await page.locator('#btncopycookie').click();
    assert.equal(await page.locator('#cookie-status').getAttribute('data-state'), 'error');
    assert.equal(await page.locator('#copy-label').textContent(), 'Coba salin lagi');

    const load = options => page.evaluate(async options => {Object.assign(fixture, options); await loadCurrentCookie();}, options);
    await load({pending:true});
    assert.equal(await page.locator('#cookieresult').getAttribute('aria-busy'), 'true');
    assert.ok(await page.locator('#btncopycookie').isDisabled());
    await page.evaluate(() => releaseCookies());
    await load({pending:false, cookies:[]});
    assert.equal(await page.locator('#cookieresult').inputValue(), '');
    assert.equal(await page.locator('#cookie-status').getAttribute('data-state'), 'empty');
    assert.ok(await page.locator('#btncopycookie').isDisabled());
    await load({cookieError:true});
    assert.equal(await page.locator('#cookie-status').getAttribute('data-state'), 'error');
    await load({cookieError:false, queryError:true});
    assert.equal(await page.locator('#CurrentCookieUrl').textContent(), 'Tab tidak tersedia');
    await load({queryError:false, url:'chrome://newtab/'});
    assert.equal(await page.locator('#CurrentCookieUrl').textContent(), 'Halaman browser');

    await load({url:`https://${'a'.repeat(63)}.example.test`, cookies:[{name:'sellerio_test',value:'fixture_only'}]});
    assert.equal(await page.locator('#copy-label').textContent(), 'Salin cookie');
    for (const theme of ['light', 'dark']) {
      await page.emulateMedia({colorScheme:theme});
      for (const width of [320, 380, 500, 999, 1600]) {
        await page.setViewportSize({width,height:900});
        assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), `${theme}/${width}: no overflow`);
        const button = await page.locator('#btncopycookie').boundingBox();
        assert.ok(button.height >= 44);
        await page.locator('#btncopycookie').focus();
        assert.equal(await page.locator('#btncopycookie').evaluate(el => getComputedStyle(el).outlineStyle), 'solid');
        await page.locator('body').screenshot({path:path.join(output, `${theme}-${width}.png`)});
      }
      await page.setViewportSize({width:320,height:1100});
      await page.evaluate(() => document.documentElement.style.fontSize = '32px');
      assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), `${theme}: 200% text resize`);
      await page.locator('body').screenshot({path:path.join(output, `${theme}-zoom.png`)});
      await page.evaluate(() => document.documentElement.style.removeProperty('font-size'));
    }
    await page.emulateMedia({reducedMotion:'reduce'});
    assert.equal(await page.locator('#btncopycookie').evaluate(el => getComputedStyle(el).transitionDuration), '0s');
    for (const platform of ['Macintosh; Intel Mac OS X 10_15_7', 'Windows NT 10.0; Win64; x64']) {
      await page.evaluate(platform => Object.defineProperty(navigator, 'userAgent', {configurable:true,value:`Mozilla/5.0 (${platform}) AppleWebKit/537.36 Chrome/140.0.0.0 Safari/537.36`}), platform);
      await load({url:'https://seller.example.test/'});
      const value = await page.locator('#cookieresult').inputValue();
      assert.ok(value.includes(encodeURIComponent(platform)), 'Cookie export preserves browser platform');
      assert.ok(!value.includes('/Volumes/') && !value.includes('C:\\'), 'Export has no local paths');
    }
    for (const theme of ['light', 'dark']) {
      await page.emulateMedia({colorScheme:theme});
      await page.setViewportSize({width:380,height:800});
      await page.locator('#btncopycookie').evaluate(el => el.blur());
      await page.locator('body').screenshot({path:path.join(output, `${theme}-preview.png`)});
    }
    assert.deepEqual(errors, []);

    native = await chromium.launchPersistentContext(profile, {channel:'chromium',headless:true,args:[`--disable-extensions-except=${extension}`,`--load-extension=${extension}`]});
    const worker = native.serviceWorkers()[0] || await native.waitForEvent('serviceworker');
    const manifest = await worker.evaluate(() => chrome.runtime.getManifest());
    assert.equal(manifest.name, 'Sellerio Get Cookies');
    const site = await native.newPage();
    await site.goto(base + '/fixture');
    await native.addCookies([{name:'sellerio_test',value:'fixture_only',url:base}]);
    const popup = await native.newPage();
    await popup.goto(`chrome-extension://${new URL(worker.url()).host}/popup.html`);
    await site.bringToFront();
    await popup.evaluate(() => loadCurrentCookie());
    await popup.waitForFunction(() => document.getElementById('cookieresult').value.includes('sellerio_test=fixture_only;'));
    assert.equal(await popup.locator('#cookie-status').getAttribute('data-state'), 'ready');
    console.log('PASS: packaged Manifest V3 extension loads and reads a local test cookie in Chromium on ' + process.platform);
    console.log('PASS: copy + fallback + failure; loading/empty/error; keyboard; light/dark; 320/380/500/999/1600px; 200% text; reduced motion.');
    console.log('Captures: ' + output);
  } finally {
    if (native) await native.close();
    if (browser) await browser.close();
    await new Promise(resolve => server.close(resolve));
    fs.rmSync(profile, {recursive:true,force:true});
  }
}
main().catch(error => {console.error(error); process.exitCode = 1;});
