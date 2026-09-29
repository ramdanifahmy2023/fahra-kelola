const assert = require('node:assert/strict');
const {execFileSync} = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');
const {chromium} = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const root = path.resolve(__dirname, '..');
const base = process.env.CHAT_TEST_URL || 'http://127.0.0.1:8147';
const php = code => execFileSync('php', ['-r', code], {cwd:root, encoding:'utf8'}).trim();
const sid = php("chdir('public'); require '../app/init.php'; $d=new Database(); $d->query('SELECT id,name,email FROM accounts LIMIT 1'); $a=$d->single(); session_id(bin2hex(random_bytes(24))); session_start(); $_SESSION['auth_user']=$a; echo session_id(); session_write_close();");
(async () => {
  let browser;
  try {
    browser = await chromium.launch({headless:true});
    const context = await browser.newContext({viewport:{width:1600,height:1050}});
    await context.addCookies([{name:'PHPSESSID',value:sid,url:base}]);
    const page = await context.newPage(), errors = [], writes = [];
    page.on('pageerror', error => errors.push(error.message));
    const shops = [{shop_id:1,shop_name:'Toko A dengan nama panjang untuk pemeriksaan tampilan',sync_enabled:false,stale:true,last_sync_at:'2026-09-28 10:00:00'}, {shop_id:2,shop_name:'Toko B',sync_enabled:true,error_message:'user_is_forbidden'}];
    const rows = [{shop_id:1,shop_name:shops[0].shop_name,remote_conversation_id:'a',buyer_name:'Pembeli A',status:'activated',is_blocked:0}, {shop_id:2,shop_name:'Toko B',remote_conversation_id:'b',buyer_name:'Pembeli B',status:'closed',is_blocked:0}];
    let releaseA, holdA=false, releaseSend, holdSend=false, ambiguous=false, rejected=false, reads=0, changed=false;
    await page.addInitScript(()=>Object.defineProperty(navigator,'clipboard',{value:{writeText:async text=>{window.copiedReply=text;}}}));
    await page.route('**/procChat/**', async route => {
      const req=route.request(), url=new URL(req.url()), body=new URLSearchParams(req.postData() || '');
      const endpoint=url.pathname.split('/').pop();
      const done=(payload, status=200) => route.fulfill({status,contentType:'application/json',body:JSON.stringify({status:'success',...payload})});
      if (req.method()==='POST') { assert.ok(req.headers()['x-csrf-token']); writes.push({endpoint,body:Object.fromEntries(body)}); }
      if (endpoint==='overview') return done({shops,totals:{conversation_count:2,active_count:1}});
      if (endpoint==='conversations') return done({conversations:rows.filter(row=>!Number(url.searchParams.get('shop_id')) || row.shop_id===Number(url.searchParams.get('shop_id')))});
      if (endpoint==='messages') {
        reads++; const id=url.searchParams.get('conversation_id');
        if (id==='a' && holdA) await new Promise(resolve=>releaseA=resolve);
        return done({conversation:rows.find(row=>row.remote_conversation_id===id),messages:[{remote_message_id:'m-'+id,content_text:(changed?'Pesan baru ':'Pesan ')+id,direction:'incoming',remote_created_at:'2026-09-28 10:00:00'}],sync:{synced_at:'2026-09-28 10:00:00'},outbox:[]});
      }
      if (endpoint==='refresh') return done({job_id:7,message:'Pembaruan masuk antrean.'});
      if (endpoint==='mark_read') { rows.find(row=>row.remote_conversation_id===body.get('conversation_id')).status='activated'; return done({message:'Percakapan diaktifkan.'}); }
      if (endpoint==='send') {
        if (holdSend) await new Promise(resolve=>releaseSend=resolve);
        if (rejected) return done({status:'error',attempted:true,delivery_status:'failed',message:'Shopee menolak permintaan (90309999).'},502);
        if (ambiguous) return done({status:'error',ambiguous:true,delivery_status:'ambiguous',message:'Pengiriman belum terkonfirmasi.'},409);
        return done({remote_message_id:'remote-test',message:'Terkirim dan dikonfirmasi Shopee.'});
      }
      throw new Error('Unexpected chat request ' + endpoint);
    });
    await page.goto(base+'/panel/chat');
    await page.locator('[data-conversation-id="b"]').waitFor();
    assert.match(await page.locator('#chat-session-warning').innerText(),/dijeda.*user_is_forbidden/);
    assert.match(await page.locator('#chat-state').innerText(),/28\/09\/26/);
    holdA=true;
    await page.locator('[data-conversation-id="a"]').click();
    await page.waitForFunction(()=>document.querySelector('#chat-detail-buyer').textContent==='Pembeli A');
    while (!releaseA) await new Promise(resolve=>setTimeout(resolve,10));
    await page.locator('[data-conversation-id="b"]').click();
    await page.waitForFunction(()=>document.querySelector('#chat-messages').textContent.includes('Pesan b'));
    holdA=false; releaseA();
    await page.waitForTimeout(100);
    assert.equal(await page.locator('#chat-detail-buyer').innerText(),'Pembeli B');
    assert.ok(!(await page.locator('#chat-messages').innerText()).includes('Pesan a'));
    assert.ok(await page.locator('#chat-message').isDisabled());
    await page.locator('#chat-reopen').click();
    await page.waitForFunction(()=>!document.querySelector('#chat-message').disabled);
    assert.equal(writes.find(write=>write.endpoint==='mark_read').body.reopen,'1');
    await page.locator('#chat-message').fill('Draft B');
    await page.locator('[data-conversation-id="a"]').click();
    await page.waitForFunction(()=>!document.querySelector('#chat-message').disabled);
    await page.locator('#chat-message').fill('Draft A');
    await page.locator('[data-conversation-id="b"]').click();
    await page.waitForFunction(()=>!document.querySelector('#chat-message').disabled);
    assert.equal(await page.inputValue('#chat-message'),'Draft B');
    holdSend=true;
    await page.locator('#chat-message').press('Enter');
    await page.locator('#chat-compose').dispatchEvent('submit');
    while (!releaseSend) await new Promise(resolve=>setTimeout(resolve,10));
    assert.equal(writes.filter(write=>write.endpoint==='send').length,1);
    const sent=writes.find(write=>write.endpoint==='send').body;
    assert.equal(sent.shop_id,'2'); assert.equal(sent.conversation_id,'b'); assert.match(sent.request_id,/^[a-f0-9-]{36}$/);
    await page.locator('[data-conversation-id="a"]').click();
    await page.waitForFunction(()=>!document.querySelector('#chat-message').disabled);
    holdSend=false; releaseSend(); await page.waitForTimeout(100);
    assert.equal(await page.inputValue('#chat-message'),'Draft A');
    assert.equal(await page.locator('#chat-detail-buyer').innerText(),'Pembeli A');
    rejected=true;
    await page.locator('#chat-send').click();
    await page.locator('#chat-copy-reply').waitFor({state:'visible'});
    assert.equal(await page.inputValue('#chat-message'),'Draft A');
    await page.locator('#chat-copy-reply').click();
    assert.equal(await page.evaluate(()=>window.copiedReply),'Draft A');
    rejected=false;
    ambiguous=true;
    await page.locator('#chat-send').click();
    await page.waitForFunction(()=>document.querySelector('#chat-compose-state').textContent.includes('belum pasti'));
    assert.ok(await page.locator('#chat-send').isDisabled());
    assert.ok(await page.locator('#chat-send-fallback').isHidden(), 'Uncertain delivery must not encourage another send in Shopee');
    const previous=reads; changed=true;
    await page.waitForFunction(()=>document.querySelector('#chat-messages').textContent.includes('Pesan baru a'),null,{timeout:8000});
    assert.ok(reads>previous, 'Selected message polling refreshes history');
    await page.reload();
    await page.locator('[data-conversation-id="a"]').click();
    await page.waitForFunction(()=>document.querySelector('#chat-messages').textContent.includes('Pesan baru a'));
    assert.ok(await page.locator('#chat-send').isDisabled(), 'Uncertain intent remains blocked after reload');
    const headers={'X-Requested-With':'XMLHttpRequest'};
    assert.equal((await context.request.get(base+'/procChat/mark_read',{headers})).status(),405);
    assert.equal((await context.request.post(base+'/procChat/send',{headers,form:{shop_id:1}})).status(),403);
    assert.equal((await context.request.post(base+'/procChat/send',{headers:{...headers,'X-CSRF-Token':await page.locator('#chat-page').getAttribute('data-csrf')},form:{}})).status(),422);
    fs.mkdirSync(path.join(root,'tmp/chat-ui'),{recursive:true});
    await page.addStyleTag({content:'*,*::before,*::after { transition: none !important; animation: none !important; }'});
    for (const theme of ['light','dark']) {
      for (const width of [320,500,999,1600]) {
        await page.setViewportSize({width,height:1000});
        await page.evaluate(theme=>window.shopdashTheme.setMode(theme),theme);
        const overflow=await page.locator('#chat-page').evaluate(el=>el.scrollWidth>el.clientWidth+1);
        assert.equal(overflow,false,`Chat overflow at ${width}/${theme}`);
        await page.locator('#chat-open-popup').click();
        await page.locator('#chat-popup-close').focus(); await page.keyboard.press('Shift+Tab');
        assert.ok(await page.locator('#chat-refresh-thread').isEnabled());
        if (width===320 && theme==='dark') assert.equal(await page.locator('#chat-refresh-thread').evaluate(el=>getComputedStyle(el).color),'rgb(245, 239, 228)');
        assert.ok(await page.locator('#chat-shell').evaluate(el=>el.contains(document.activeElement)));
        const bounds=await page.locator('#chat-shell').boundingBox();
        assert.ok(bounds.x>=0 && bounds.x+bounds.width<=width+1);
        await page.screenshot({path:path.join(root,`tmp/chat-ui/${theme}-${width}.png`)});
        await page.keyboard.press('Escape');
        assert.equal(await page.locator('#chat-open-popup').getAttribute('aria-expanded'),'false');
      }
    }
    assert.deepEqual(errors,[]);
    console.log('PASS: chat stale/error states, response races, per-thread drafts, explicit reopen, duplicate Enter, target stability, ambiguous reload, selected polling, CSRF, keyboard popup, 4 widths x 2 themes');
  } finally { await browser?.close(); php("session_id('"+sid+"'); session_start(); $_SESSION=[]; session_destroy();"); }
})().catch(error=>{console.error(error);process.exitCode=1;});
