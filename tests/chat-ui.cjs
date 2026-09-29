const assert=require('node:assert/strict');
const {execFileSync}=require('node:child_process');
const fs=require('node:fs'),path=require('node:path');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const root=path.resolve(__dirname,'..'),base=process.env.CHAT_TEST_URL||'http://127.0.0.1:8147';
const php=code=>execFileSync('php',['-r',code],{cwd:root,encoding:'utf8'}).trim();
const sid=php("chdir('public');require '../app/init.php';$d=new Database();$d->query('SELECT id,name,email FROM accounts LIMIT 1');$a=$d->single();session_id(bin2hex(random_bytes(24)));session_start();$_SESSION['auth_user']=$a;echo session_id();session_write_close();");
(async()=>{let browser;try{
  browser=await chromium.launch();const ctx=await browser.newContext({viewport:{width:1600,height:1000}});
  await ctx.addCookies([{name:'PHPSESSID',value:sid,url:base}]);const page=await ctx.newPage(),errors=[],writes=[];
  page.on('pageerror',e=>errors.push(e.message));
  const shops=[{shop_id:1,shop_name:'Toko A dengan nama panjang untuk pemeriksaan tampilan',sync_enabled:false,stale:true,last_sync_at:'2026-09-28 10:00:00'},{shop_id:2,shop_name:'Toko B',sync_enabled:true,error_message:'user_is_forbidden'}];
  const rows=[{shop_id:1,shop_name:shops[0].shop_name,remote_conversation_id:'a',buyer_name:'Pembeli A',status:'activated',is_blocked:0},{shop_id:2,shop_name:'Toko B',remote_conversation_id:'b',buyer_name:'Pembeli B',status:'closed',is_blocked:1}];
  const hidden={shop_id:1,shop_name:shops[0].shop_name,remote_conversation_id:'older-not-in-list',buyer_name:'Pembeli riwayat lama',status:'closed'};
  let releaseA,holdA=false,changed=false,failHistory=false,reads=0;
  await page.route('**/procnotifications/**',r=>r.fulfill({json:{status:'success',summary:{},notifications:[],chat_cursor:'0'}}));
  await page.route('**/procChat/**',async route=>{
    const req=route.request(),url=new URL(req.url()),body=new URLSearchParams(req.postData()||''),endpoint=url.pathname.split('/').pop();
    const done=(payload,status=200)=>route.fulfill({status,json:{status:'success',...payload}});
    if(req.method()==='POST'){assert.ok(req.headers()['x-csrf-token']);writes.push({endpoint,body:Object.fromEntries(body)});}
    if(endpoint==='overview')return done({shops,totals:{conversation_count:2,active_count:1,unread_count:9}});
    if(endpoint==='conversations')return done({conversations:rows.filter(r=>!Number(url.searchParams.get('shop_id'))||r.shop_id===Number(url.searchParams.get('shop_id')))});
    if(endpoint==='messages'){
      reads++;const id=url.searchParams.get('conversation_id');if(id==='a'&&holdA)await new Promise(resolve=>releaseA=resolve);
      if(failHistory)return done({status:'error',message:'Riwayat gagal dimuat.'},503);
      return done({conversation:id===hidden.remote_conversation_id?hidden:rows.find(r=>r.remote_conversation_id===id),messages:[{remote_message_id:'m-'+id,content_text:(changed?'Pesan baru ':'Pesan ')+id,direction:'incoming',remote_created_at:'2026-09-28 10:00:00'}],sync:{synced_at:'2026-09-28 10:00:00'}});
    }
    if(endpoint==='refresh')return done({job_id:7,message:'Pembaruan masuk antrean.'});
    throw new Error('Unexpected chat mutation '+endpoint);
  });
  await page.goto(base+'/panel/chat');await page.locator('[data-conversation-id="b"]').waitFor();
  assert.match(await page.locator('#chat-session-warning').innerText(),/dijeda.*user_is_forbidden/);
  assert.equal(await page.locator('#chat-compose,#chat-send,#chat-message,#chat-mark-read,#chat-reopen,#chat-floating-unread').count(),0);
  holdA=true;await page.locator('[data-conversation-id="a"]').click();
  await page.waitForFunction(()=>document.querySelector('#chat-detail-buyer').textContent==='Pembeli A');
  while(!releaseA)await new Promise(resolve=>setTimeout(resolve,10));
  await page.locator('[data-conversation-id="b"]').click();await page.waitForFunction(()=>document.querySelector('#chat-messages').textContent.includes('Pesan b'));
  holdA=false;releaseA();await page.waitForTimeout(100);
  assert.equal(await page.locator('#chat-detail-buyer').innerText(),'Pembeli B');
  assert.ok(!(await page.locator('#chat-messages').innerText()).includes('Pesan a'));
  assert.equal(await page.locator('#chat-shopee-reply').getAttribute('href'),'https://seller.shopee.co.id/webchat/conversations');
  assert.ok(await page.locator('#chat-shopee-reply').isVisible(),'Closed and blocked histories still offer Shopee navigation');
  assert.ok(writes.every(w=>w.endpoint==='refresh'),'Reading only queues upstream reads');
  const previous=reads;changed=true;
  await page.waitForFunction(()=>document.querySelector('#chat-messages').textContent.includes('Pesan baru b'),null,{timeout:8000});
  assert.ok(reads>previous);
  failHistory=true;await page.click('#chat-refresh-thread');await page.getByText('Riwayat gagal dimuat.',{exact:true}).waitFor();
  failHistory=false;await page.click('#chat-refresh-thread');await page.waitForFunction(()=>document.querySelector('#chat-messages').textContent.includes('Pesan baru b'));
  await page.goto(base+'/panel/chat?shop_id=1&conversation_id='+hidden.remote_conversation_id);
  await page.waitForFunction(()=>document.querySelector('#chat-detail-buyer').textContent==='Pembeli riwayat lama');
  assert.ok((await page.locator('#chat-messages').innerText()).includes(hidden.remote_conversation_id),'Notification target opens outside latest 100 list');
  const headers={'X-Requested-With':'XMLHttpRequest'};
  for(const endpoint of ['send','mark_read'])for(const method of ['get','post']){
    const r=await ctx.request[method](base+'/procChat/'+endpoint,{headers});assert.equal(r.status(),405);assert.equal((await r.json()).attempted,false);
  }
  assert.equal((await ctx.request.post(base+'/procChat/refresh',{headers})).status(),403);
  assert.equal((await ctx.request.get(base+'/procChat/refresh',{headers})).status(),405);
  const out=path.join(root,'tmp/chat-ui');fs.mkdirSync(out,{recursive:true});
  await page.addStyleTag({content:'*,*::before,*::after{transition:none!important;animation:none!important}'});
  for(const width of [320,500,999,1600])for(const theme of ['light','dark']){
    await page.setViewportSize({width,height:1000});await page.evaluate(t=>window.shopdashTheme.setMode(t),theme);
    assert.equal(await page.locator('#chat-page').evaluate(el=>el.scrollWidth>el.clientWidth+1),false);
    await page.click('#chat-open-popup');await page.locator('#chat-popup-close').focus();await page.keyboard.press('Shift+Tab');
    assert.equal(await page.evaluate(()=>document.activeElement.id),'chat-shopee-reply');
    const box=await page.locator('#chat-shell').boundingBox();assert.ok(box.x>=0&&box.x+box.width<=width+1);
    await page.screenshot({path:path.join(out,`${theme}-${width}.png`)});await page.keyboard.press('Escape');
    assert.equal(await page.evaluate(()=>document.activeElement.id),'chat-open-popup');
  }
  await page.setViewportSize({width:320,height:1000});await page.click('#chat-mobile-back');await page.locator('[data-conversation-id="a"]').waitFor({state:'visible'});
  await page.locator('[data-shop-id="2"]').click();await page.locator('[data-conversation-id="b"]').waitFor({state:'visible'});
  assert.equal(await page.locator('[data-conversation-id="a"]').count(),0);
  assert.deepEqual(errors,[]);
  console.log('PASS: read-only chat controls/endpoints, race protection, cache polling, recovery, hidden notification target, shop switching, keyboard and 4 widths x 2 themes');
}finally{await browser?.close();php("session_id('"+sid+"');session_start();session_destroy();");}})().catch(e=>{console.error(e);process.exitCode=1;});
