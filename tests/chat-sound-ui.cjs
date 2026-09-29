const assert=require('node:assert/strict'),path=require('node:path');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const root=path.resolve(__dirname,'..'),base=process.env.NOTIFICATION_TEST_URL||'http://127.0.0.1:8131';
const testSession=require('./panel-test-session.cjs')(root),sid=testSession.sid;
(async()=>{let browser;try{
  browser=await chromium.launch();const ctx=await browser.newContext();await ctx.addCookies([{name:'PHPSESSID',value:sid,url:base}]);
  await ctx.route('**/procWorkspace/**',r=>r.fulfill({json:{status:'success'}}));
  await ctx.addInitScript(()=>{
    window.toneStarts=0;window.rejectAudio=false;
    class TestAudio {
      constructor(){this.state='suspended';this.currentTime=0;this.destination={};}
      async resume(){if(window.rejectAudio)throw new Error('Fixture autoplay denial');this.state='running';this.onstatechange?.();}
      async suspend(){this.state='suspended';this.onstatechange?.();}
      createOscillator(){return {frequency:{},connect(){},disconnect(){},start(){window.toneStarts++;},stop(){this.onended?.();}};}
      createGain(){return {gain:{setValueAtTime(){},linearRampToValueAtTime(){}},connect(){},disconnect(){}};}
    }
    window.AudioContext=TestAudio;
  });
  let cursor=9007199254741000n,urgentOnly=false,reads=0,stockRevision=1,metadataUnavailable=false;
  const fixture=(id,type,severity)=>({id,revision:type==='chat_incoming'?2:stockRevision,shop_id:1,shop_name:'Fixture shop',type,severity,unread:true,title:type==='chat_incoming'?'Pesan Shopee baru':'Stok kritis',message:'Fixture notification',action_label:type==='chat_incoming'?'Lihat pesan':'Periksa produk',path:type==='chat_incoming'?'/panel/chat?shop_id=1&conversation_id=fixture':'/panel/products?shop_id=1',icon:type==='chat_incoming'?'chat':'inventory_2',source_at:'2026-09-30 00:00:00'});
  await ctx.route('**/procnotifications/**',async route=>{
    const url=new URL(route.request().url());
    if(route.request().method()==='POST'){reads++;return route.fulfill({json:{status:'success'}});}
    urgentOnly=url.searchParams.get('urgent_only')==='1';
    let notifications=[fixture(1,'low_stock','urgent'),fixture(2,'chat_incoming','info')];
    if(urgentOnly)notifications=notifications.filter(r=>r.severity==='urgent');
    if(url.searchParams.get('type'))notifications=notifications.filter(r=>r.type===url.searchParams.get('type'));
    const payload={status:'success',chat_cursor:metadataUnavailable?null:String(cursor),unread_count:2,summary:{total:2,unread:2,unread_urgent:1},has_more:false};
    if(url.searchParams.get('grouped')==='1')payload.groups=notifications.map(r=>({shop_id:r.shop_id,shop_name:r.shop_name,type:r.type,total:1,unread:1,urgent:r.severity==='urgent'?1:0})),payload.group_count=notifications.length;
    else payload.notifications=notifications,payload.filtered_count=notifications.length;
    return route.fulfill({json:payload});
  });
  const errors=[],pages=[];
  const open=async()=>{const page=await ctx.newPage();pages.push(page);page.on('pageerror',e=>errors.push(e.message));await page.goto(base+'/panel/chat');await page.waitForFunction(()=>document.querySelector('#notification-badge').textContent==='2');await page.click('#notification-button');await page.locator('.notification-group').first().waitFor();return page;};
  const first=await open();
  assert.equal(await first.evaluate(()=>window.toneStarts),0,'Existing chat history is silent on load');
  assert.equal(await first.locator('#notification-button').count(),1);
  await first.evaluate(()=>window.rejectAudio=true);await first.click('#notification-sound-toggle');
  await first.getByText('Fixture autoplay denial',{exact:true}).waitFor();assert.equal(await first.getAttribute('#notification-sound-toggle','aria-pressed'),'false');
  await first.evaluate(()=>window.rejectAudio=false);await first.click('#notification-sound-toggle');
  await first.waitForFunction(()=>document.querySelector('#notification-sound-toggle').getAttribute('aria-pressed')==='true');
  assert.equal(await first.evaluate(()=>window.toneStarts),0,'Activation establishes baseline without a history sound');
  cursor++;await first.click('#notification-reload');await first.waitForFunction(()=>window.toneStarts===2);
  stockRevision++;await first.click('#notification-reload');await first.waitForTimeout(100);assert.equal(await first.evaluate(()=>window.toneStarts),2,'Stock escalation and repeat polling do not sound');
  await first.getByRole('button',{name:'Lihat rincian percakapan Shopee · Fixture shop'}).click();await first.locator('[data-alert-id="2"]').waitFor();
  assert.equal(await first.locator('.notification-item').count(),1);assert.equal(await first.locator('.notification-severity').innerText(),'Pesan masuk');
  assert.equal(await first.locator('.notification-action').getAttribute('href'),base+'/panel/chat?shop_id=1&conversation_id=fixture');
  await first.click('#notification-back');await first.locator('.notification-group').first().waitFor();
  await first.check('#notification-urgent');await first.waitForFunction(()=>document.querySelectorAll('.notification-group').length===1);assert.ok(urgentOnly);
  assert.equal(await first.evaluate(()=>window.toneStarts),2);await first.uncheck('#notification-urgent');
  const second=await open();await second.click('#notification-sound-toggle');await second.waitForFunction(()=>document.querySelector('#notification-sound-toggle').getAttribute('aria-pressed')==='true');
  cursor++;
  await Promise.all([first.click('#notification-reload'),second.click('#notification-reload')]);await second.waitForTimeout(200);
  const total=async()=>{const counts=await Promise.all(pages.map(p=>p.evaluate(()=>window.toneStarts)));return counts.reduce((a,b)=>a+b,0);};
  assert.equal(await total(),4,'Two tabs play one two-note tone for one new event');
  await Promise.all([first.click('#notification-reload'),second.click('#notification-reload')]);await second.waitForTimeout(100);assert.equal(await total(),4);
  await second.evaluate(()=>Object.defineProperty(document,'hidden',{value:true,configurable:true}));
  cursor++;await second.click('#notification-reload');await second.waitForTimeout(100);assert.equal(await total(),6,'An armed background tab can sound');
  await second.evaluate(()=>{const key='shopdash:chat-sound:'+document.querySelector('#notification-menu').dataset.user+':cursor';const old=JSON.parse(localStorage.getItem(key));old.at=Date.now()-120000;localStorage.setItem(key,JSON.stringify(old));});
  cursor+=5n;await second.click('#notification-reload');await second.waitForTimeout(100);assert.equal(await total(),6,'Outage recovery silently baselines a backlog');
  await second.click('#notification-sound-toggle');cursor++;await first.click('#notification-reload');await first.waitForTimeout(100);assert.equal(await total(),6,'Muting on one tab mutes the shared preference');
  await first.reload();await first.waitForFunction(()=>document.querySelector('#notification-badge').textContent==='2');assert.equal(await first.evaluate(()=>window.toneStarts),0);
  const blocked=await open();
  await blocked.evaluate(()=>{window.originalStorageSet=Storage.prototype.setItem;Storage.prototype.setItem=()=>{throw new Error('fixture storage disabled');};});
  await blocked.click('#notification-sound-toggle');await blocked.getByText('Izinkan penyimpanan browser untuk mengaktifkan bunyi chat.',{exact:true}).waitFor();
  assert.equal(await blocked.getAttribute('#notification-sound-toggle','aria-pressed'),'false');
  await blocked.evaluate(()=>Storage.prototype.setItem=window.originalStorageSet);
  metadataUnavailable=true;await blocked.click('#notification-sound-toggle');await blocked.getByText('Data chat belum tersedia. Coba aktifkan bunyi lagi.',{exact:true}).waitFor();
  assert.equal(await blocked.getAttribute('#notification-sound-toggle','aria-pressed'),'false');assert.equal(await blocked.locator('#notification-badge').innerText(),'2','Existing notifications survive missing chat sound metadata');
  assert.equal(reads,0,'Sound and browsing do not acknowledge notifications');assert.deepEqual(errors,[]);
  console.log('PASS: shared chat group/filter/deep link, silent baseline, autoplay/storage/metadata failures, chat-only tone, duplicate suppression, two-tab lock, background, outage and global mute');
}finally{await browser?.close();testSession.cleanup();}})().catch(e=>{console.error(e);process.exitCode=1;});
