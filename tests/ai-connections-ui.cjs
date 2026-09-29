const assert=require('node:assert/strict');
const {execFileSync}=require('node:child_process');
const fs=require('node:fs');const path=require('node:path');
const {chromium}=require(process.env.PLAYWRIGHT_MODULE||'playwright');
const root=path.resolve(__dirname,'..');const base=process.env.AUTOMATION_TEST_URL||'http://127.0.0.1:8131';
const php=code=>execFileSync('php',['-r',code],{cwd:root,encoding:'utf8'}).trim();
const sid=php("chdir('public'); require '../app/init.php'; $d=new Database(); $d->query('SELECT id,name,email FROM accounts LIMIT 1'); $a=$d->single(); session_id(bin2hex(random_bytes(24))); session_start(); $_SESSION['auth_user']=$a; echo session_id(); session_write_close();");
(async()=>{
 let browser;
 try{
  browser=await chromium.launch({headless:true});const context=await browser.newContext({viewport:{width:1440,height:1008}});
  await context.addCookies([{name:'PHPSESSID',value:sid,url:base}]);const page=await context.newPage();
  page.on('dialog',dialog=>dialog.accept());const errors=[];page.on('pageerror',e=>errors.push(e.message));
  let rows=[],writes=0,fail=false,conflict=false,probeFailure=false,selected=null;
  await page.route('**/procAiConnections/*',async route=>{
   const action=new URL(route.request().url()).pathname.split('/').at(-1);
   const input=route.request().method()==='POST'?route.request().postDataJSON():{};
   const reply=(data,status=200)=>route.fulfill({status,json:{status:status===200?'success':'error',...data}});
   if(action==='list')return reply({connections:rows});
   assert.ok(route.request().headers()['x-csrf-token']);
   if(fail)return reply({message:'Koneksi belum dapat diproses. Coba lagi.'},500);
   if(conflict)return reply({message:'Koneksi berubah di tab lain. Muat ulang daftar.'},409);
   if(action==='create'){
    assert.equal(input.api_key,'fixture-only-key');writes++;
    rows.push({id:711,name:input.name,base_url:input.base_url.replace(/\/$/,'')+(input.base_url.endsWith('/v1')?'':'/v1'),default_model:input.default_model,version:1,has_api_key:true,shops:[],catalog_result:null,test_result:null});return reply({connection:rows[0]});
   }
   const item=rows.find(row=>row.id===input.id);assert.ok(item);
   if(action==='update'){
    if(input.base_url!==item.base_url && !input.api_key)return reply({message:'Masukkan API key lagi ketika base URL berubah.',fields:{api_key:'Base URL berubah. Masukkan key baru.'}},422);
    assert.equal(input.version,item.version);writes++;Object.assign(item,{name:input.name,base_url:input.base_url,default_model:input.default_model,version:item.version+1});return reply({connection:item});
   }
   if(action==='delete'){
    if(item.shops.length)return reply({message:'Koneksi masih dipakai oleh Toko A. Lepas koneksi dahulu.'},409);
    rows=[];writes++;return reply({});
   }
   if(probeFailure)return reply({message:'Provider menolak akses. Periksa API key.'},502);
   const kind=action==='models'?'catalog_result':'test_result';
   item[kind]={version:item.version,at:new Date().toISOString(),ok:true,model:action==='test'?input.model:null,message:action==='models'?'Daftar model berhasil dimuat.':'Model berhasil menghasilkan jawaban uji.'};
   return reply({connection:item,models:[{id:'alpha-combo',kind:'combo'},{id:'provider/long-model-name-that-wraps-on-mobile-1234567890',kind:'model'},{id:'zeta',kind:'model'}]});
  });
  await page.route('**/procAutomation/save',route=>{const body=route.request().postDataJSON();selected=body.connection_id;return route.fulfill({json:{status:'success',profile:{config:body.config,version:body.version+1,connection_id:selected}}});});
  await page.goto(base+'/panel/automation');await page.addStyleTag({content:'*,*::before,*::after { transition: none !important; animation: none !important; }'});await page.getByText('Belum ada koneksi. Tambahkan base URL', {exact:false}).waitFor();
  await page.click('#ai-add');await page.fill('#ai-name','Router uji <script>');await page.fill('#ai-base-url','https://router.example.com');await page.fill('#ai-api-key','fixture-only-key');await page.fill('#ai-default-model','review-combo');
  await page.click('#ai-key-toggle');assert.equal(await page.getAttribute('#ai-api-key','type'),'text');await page.click('#ai-key-toggle');
  fail=true;await page.click('#ai-save');await page.locator('#ai-error').waitFor({state:'visible'});assert.equal(await page.inputValue('#ai-api-key'),'fixture-only-key');
  fail=false;await page.click('#ai-save');await page.locator('#ai-form').waitFor({state:'hidden'});assert.equal(writes,1);
  assert.equal(await page.inputValue('#ai-api-key'),'');assert.ok((await page.locator('#ai-list').innerText()).includes('Router uji <script>'));
  await page.selectOption('#automation-connection','711');await page.click('#automation-save-button');await page.waitForFunction(()=>document.querySelector('#automation-save-status').textContent.includes('tersimpan'));assert.equal(selected,711);
  assert.match(await page.locator('#automation-effective-model').innerText(),/review-combo/);
  await page.fill('#automation-model','shop-override');assert.match(await page.locator('#automation-effective-model').innerText(),/shop-override/);await page.click('#automation-save-button');
  await page.locator('[data-action=edit]').click();assert.equal(await page.inputValue('#ai-api-key'),'');await page.fill('#ai-base-url','https://new.example.com/v1');await page.click('#ai-save');await page.locator('#ai-api-key-error').waitFor({state:'visible'});assert.equal(await page.getAttribute('#ai-api-key','aria-invalid'),'true');
  await page.fill('#ai-api-key','replacement-fixture-key');await page.click('#ai-save');await page.locator('#ai-form').waitFor({state:'hidden'});assert.equal(writes,2);
  await page.locator('[data-action=edit]').click();await page.fill('#ai-name','Router diperbarui');conflict=true;await page.click('#ai-save');await page.locator('#ai-error').waitFor({state:'visible'});assert.match(await page.locator('#ai-error').innerText(),/tab lain/);assert.equal(await page.inputValue('#ai-name'),'Router diperbarui');
  conflict=false;await page.click('#ai-save');await page.locator('#ai-form').waitFor({state:'hidden'});
  await page.locator('[data-action=models]').click();await page.locator('#ai-model-options').waitFor({state:'visible'});await page.fill('#ai-default-model','');await page.press('#ai-default-model','ArrowDown');await page.press('#ai-default-model','Enter');assert.equal(await page.inputValue('#ai-default-model'),'alpha-combo');await page.click('#ai-save');await page.locator('#ai-form').waitFor({state:'hidden'});
  await page.locator('[data-action=test]').click();await page.waitForFunction(()=>document.querySelector('#ai-list').textContent.includes('Model berhasil menghasilkan'));
  probeFailure=true;await page.locator('[data-action=test]').click();await page.locator('#ai-list-error').waitFor({state:'visible'});assert.match(await page.locator('#ai-list-error').innerText(),/menolak akses/);probeFailure=false;
  rows[0].shops=[{id:1,name:'Toko A',inherits_model:true}];await page.click('#ai-reload');await page.waitForFunction(()=>document.querySelector('#ai-list').textContent.includes('1 toko'));
  await page.locator('[data-action=delete]').click();await page.waitForFunction(()=>document.querySelector('#ai-list-error').textContent.includes('masih dipakai'));
  await page.locator('[data-action=models]').click();await page.locator('#ai-model-options').waitFor({state:'visible'});await page.fill('#ai-default-model','');
  const output=path.join(root,'tmp/ai-connections-ui');fs.mkdirSync(output,{recursive:true});
  for(const width of [320,500,999,1600])for(const theme of ['light','dark']){
   await page.setViewportSize({width,height:1008});await page.evaluate(t=>window.shopdashTheme.setMode(t),theme);
   await page.locator('#ai-form').scrollIntoViewIfNeeded();await page.click('#ai-model-toggle');if(await page.locator('#ai-model-options').isHidden())await page.click('#ai-model-toggle');
   const box=await page.locator('#ai-model-options').boundingBox();const input=await page.locator('#ai-default-model').boundingBox();
   assert.ok(box.y>=input.y+input.height-1);assert.ok(box.x>=0&&box.x+box.width<=width+1);
   const options=await page.locator('#ai-model-options [role=option]').evaluateAll(nodes=>nodes.map(n=>({x:n.getBoundingClientRect().x,y:n.getBoundingClientRect().y})));
   assert.ok(options.length>=2&&options.every((o,i)=>i===0||(o.y>options[i-1].y&&Math.abs(o.x-options[0].x)<1)));
   assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));
   const contrast=await page.locator('#ai-model-toggle').evaluate(el=>{
    const s=getComputedStyle(el),c=document.createElement('canvas');c.width=c.height=1;const ctx=c.getContext('2d');
    const rgb=color=>{ctx.fillStyle=color;ctx.fillRect(0,0,1,1);return [...ctx.getImageData(0,0,1,1).data].slice(0,3).map(v=>{v/=255;return v<=.04045?v/12.92:((v+.055)/1.055)**2.4;});};
    const luminance=color=>rgb(color).reduce((sum,v,i)=>sum+v*[.2126,.7152,.0722][i],0);
    const a=luminance(s.color),b=luminance(s.backgroundColor);return (Math.max(a,b)+.05)/(Math.min(a,b)+.05);
   });assert.ok(contrast>=4.5,theme+' secondary button contrast '+contrast);
   await page.screenshot({path:path.join(output,width+'-'+theme+'.png')});await page.press('#ai-default-model','Escape');assert.ok(await page.locator('#ai-model-options').isHidden());
  }
  await page.fill('#ai-default-model','manual-combo');await page.click('#ai-save');await page.locator('#ai-form').waitFor({state:'hidden'});
  rows[0].shops=[];await page.click('#ai-reload');await page.waitForFunction(()=>document.querySelector('#ai-list').textContent.includes('0 toko'));await page.locator('[data-action=delete]').click();await page.waitForFunction(()=>document.querySelector('#ai-list-state').textContent==='0 koneksi tersimpan.');
  const csrf=await page.locator('#automation-page').getAttribute('data-csrf');
  const denied=await context.request.post(base+'/procAiConnections/create',{data:{}});assert.equal(denied.status(),403);
  const invalid=await context.request.post(base+'/procAiConnections/create',{headers:{'X-CSRF-Token':csrf},data:{name:'',base_url:'bad'}});assert.equal(invalid.status(),422);
  const invalidId=await context.request.post(base+'/procAiConnections/update',{headers:{'X-CSRF-Token':csrf},data:{id:'1',version:1}});assert.equal(invalidId.status(),404);
  const unauth=await browser.newContext();const redirect=await unauth.request.get(base+'/procAiConnections/list',{maxRedirects:0});assert.equal(redirect.status(),302);await unauth.close();
  assert.deepEqual(errors,[]);console.log('PASS: connection CRUD UI, masked keys, model/combo keyboard picker, shop selection, conflicts, failures, deletion guards, CSRF and responsive light/dark');
 }finally{if(browser)await browser.close();php(`session_id(${JSON.stringify(sid)});session_start();session_destroy();`);}
})().catch(e=>{console.error(e);process.exit(1);});
