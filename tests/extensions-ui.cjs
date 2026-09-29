const assert = require('node:assert/strict');
const {execFileSync} = require('node:child_process');
const path = require('node:path');
const fs = require('node:fs');
const crypto = require('node:crypto');
const {chromium} = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const root = path.resolve(__dirname, '..');
const base = process.env.TEST_BASE_URL || 'http://127.0.0.1:8123';
const php = code => execFileSync('php', ['-r', code], {cwd:root, encoding:'utf8'}).trim();
const sid = php("chdir('public'); require '../app/init.php'; $d=new Database(); $d->query('SELECT id,name,email FROM accounts LIMIT 1'); $a=$d->single(); if (!$a) exit(1); session_id(bin2hex(random_bytes(24))); session_start(); $_SESSION['auth_user']=$a; echo session_id(); session_write_close();");
const output = path.join(root, 'tmp/extensions-ui');

(async () => {
  let browser;
  try {
    fs.mkdirSync(output, {recursive:true});
    browser = await chromium.launch({headless:true});
    const context = await browser.newContext();
    await context.addCookies([{name:'PHPSESSID', value:sid, url:base}]);
    const page = await context.newPage();
    page.setDefaultTimeout(10000);
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.route('**/procsync/**', route => route.fulfill({json:{status:'idle',shops:[],data:[]}}));
    await page.route('**/procnotifications/**', route => route.fulfill({json:{status:'success',unread:0,notifications:[]}}));
    const response = await page.goto(base + '/panel/extensions');
    assert.equal(response.status(), 200);
    assert.equal(await page.locator('#extensions-page h1').textContent(), 'Ekstensi');
    assert.equal(await page.locator('aside a[aria-current="page"]').getAttribute('href'), base + '/panel/extensions');
    await page.locator('.extensions-sections a[href="#history-heading"]').click();
    assert.ok((await page.locator('#history-heading').boundingBox()).y >= 56, 'History jump is below sticky navbar');
    await page.locator('.extensions-sections a[href="#installation-heading"]').click();
    assert.ok((await page.locator('#installation-heading').boundingBox()).y >= 56, 'Install jump is below sticky navbar');
    const metadata = JSON.parse(fs.readFileSync(path.join(root, 'resources/extensions/releases/2.1.0.json')));
    const downloadPromise = page.waitForEvent('download');
    await page.locator('[data-latest-download]').click();
    const download = await downloadPromise;
    assert.equal(download.suggestedFilename(), 'sellerio-get-cookies-2.1.0.zip');
    const saved = path.join(output, download.suggestedFilename());
    await download.saveAs(saved);
    assert.equal(crypto.createHash('sha256').update(fs.readFileSync(saved)).digest('hex'), metadata.sha256);
    const archivePromise = page.waitForEvent('download');
    await page.locator('[data-release-download]').first().click();
    assert.equal((await archivePromise).suggestedFilename(), download.suggestedFilename());
    const summary = page.locator('.extension-changelog summary').first();
    await summary.focus();
    await page.keyboard.press('Enter');
    assert.equal(await page.locator('.extension-changelog').first().getAttribute('open'), null);
    await page.keyboard.press('Space');
    assert.notEqual(await page.locator('.extension-changelog').first().getAttribute('open'), null);
    await page.locator('.extension-update-guide summary').click();
    assert.notEqual(await page.locator('.extension-update-guide').getAttribute('open'), null);

    for (const width of [320, 500, 999, 1600]) {
      await page.setViewportSize({width,height:1000});
      for (const theme of ['light','dark']) {
        await page.evaluate(mode => window.shopdashTheme.setMode(mode), theme);
        assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), `${width}/${theme}: no overflow`);
        for (const selector of ['[data-latest-download]', '[data-release-download]', '.extension-changelog summary', '.extension-update-guide summary']) {
          const box = await page.locator(selector).first().boundingBox();
          assert.ok(box.height >= 44, selector + ' touch target');
        }
        await page.locator('[data-latest-download]').focus();
        await page.keyboard.press('Tab');
        await page.keyboard.press('Shift+Tab');
        assert.equal(await page.locator('[data-latest-download]').evaluate(el => getComputedStyle(el).outlineStyle), 'solid');
        await page.evaluate(() => window.scrollTo(0, 0));
        await page.screenshot({path:path.join(output, `${width}-${theme}.png`), fullPage:true, animations:'disabled'});
      }
    }
    await page.setViewportSize({width:320,height:1000});
    await page.locator('#panel-menu-button').focus();
    await page.keyboard.press('Enter');
    assert.equal(await page.locator('#panel-menu-button').getAttribute('aria-expanded'), 'true');
    await page.keyboard.press('Escape');
    assert.equal(await page.locator('#panel-menu-button').getAttribute('aria-expanded'), 'false');
    await page.locator('#panel-menu-button').click();
    await page.locator('aside a[href$="/panel/extensions"]').click();
    await page.waitForURL('**/panel/extensions');
    await page.evaluate(() => document.documentElement.style.fontSize = '32px');
    await page.screenshot({path:path.join(output, '320-zoom.png'), fullPage:true});
    const overflow = await page.evaluate(() => [...document.querySelectorAll('body *')].filter(el => {
      const box=el.getBoundingClientRect(); return box.width > 0 && box.right > innerWidth + 1 && box.left >= 0 && getComputedStyle(el).visibility !== 'hidden';
    }).map(el => ({tag:el.tagName, id:el.id, className:el.className, width:el.getBoundingClientRect().width})).slice(0,12));
    if (overflow.length) console.log('Zoom overflow:', overflow);
    assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), '200% text resize');
    await page.evaluate(() => document.documentElement.style.removeProperty('font-size'));
    assert.deepEqual(errors, []);

    const render = setup => php(`define('burl', ${JSON.stringify(base)}); define('images', ${JSON.stringify(base + '/assets/images')}); require 'app/models/ExtensionRelease.php'; $releases=(new ExtensionRelease())->all(); ${setup} ob_start(); require 'app/views/panel/extensions.php'; echo ob_get_clean();`);
    const fixture = async html => {
      await page.setContent(`<html lang="id" data-theme="shopdash-light"><link rel="stylesheet" href="${base}/assets/css/style.css"><body style="padding:12px">${html}</body></html>`);
    };
    await fixture(render('$data=["releases"=>[],"release_error"=>false];'));
    assert.equal(await page.locator('#extensions-page h2').textContent(), 'Belum ada rilis ekstensi');
    await fixture(render('$data=["releases"=>[],"release_error"=>true];'));
    assert.equal(await page.locator('[role="alert"]').count(), 1);
    await fixture(render('$releases[0]["available"]=false; $data=["releases"=>$releases];'));
    assert.equal(await page.locator('[download]').count(), 0);
    await fixture(render('$old=$releases[0]; $old["version"]="2.0.0"; $old["name"]=str_repeat("Nama rilis fixture panjang ", 8); $data=["releases"=>[$releases[0],$old]];'));
    assert.equal(await page.locator('[data-release-version]').count(), 2);
    await page.locator('.extension-changelog summary').nth(1).click();
    assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), 'Long archived release title wraps');
    assert.notEqual(await page.locator('.extension-changelog').nth(1).getAttribute('open'), null);
    await page.locator('[data-release-download]').nth(1).focus();
    const oldDownload = page.waitForEvent('download');
    await page.keyboard.press('Enter');
    await oldDownload;
    console.log('PASS: dashboard route/menu, latest/archive ZIP downloads + checksum, named changelog, install/update guide, keyboard, light/dark at 320/500/999/1600px, 200% text, empty/error/missing package and multi-version fixtures.');
    console.log('Captures: ' + output);
  } finally {
    if (browser) await browser.close();
    php(`session_id(${JSON.stringify(sid)}); session_start(); session_destroy();`);
  }
})().catch(error => {console.error(error); process.exitCode=1;});
