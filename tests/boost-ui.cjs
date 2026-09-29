const assert = require('node:assert/strict');
const {execFileSync} = require('node:child_process');
const path = require('node:path');
const fs = require('node:fs');
const {chromium} = require(process.env.PLAYWRIGHT_MODULE || 'playwright');
const root = path.resolve(__dirname,'..');
const base = process.env.BOOST_TEST_URL || 'http://127.0.0.1:8142';
const php = code => execFileSync('php',['-r',code],{cwd:root,encoding:'utf8'}).trim();
const sid = php("chdir('public'); require '../app/init.php'; session_id(bin2hex(random_bytes(24))); session_start(); $_SESSION['auth_user']=['id'=>1,'name'=>'Penguji Boost','email'=>'boost-test@example.invalid']; echo session_id(); session_write_close();");
(async()=>{
  let browser;
  try {
    browser=await chromium.launch({headless:true});const context=await browser.newContext({viewport:{width:1600,height:1000}});
    await context.addCookies([{name:'PHPSESSID',value:sid,url:base}]);const page=await context.newPage();const errors=[];
    page.on('pageerror',e=>errors.push(e.message));
    const products=Array.from({length:25},(_,i)=>({id:String(i+1),name:'Produk uji '+(i+1)+' perlengkapan perjalanan dan busana sehari-hari',total_stock:20,sold_count:100-i,price_min:85000,status:1}));
    products[0].name='Produk uji dengan nama panjang untuk perjalanan haji dan umroh, perlengkapan harian keluarga';
    products[1].name='NamaProdukTanpaSpasi'.repeat(8);products[0].cover_image='fixture-image';products[2].total_stock=0;
    const profiles=new Map(), saved=id=>profiles.get(id)||{version:1,enabled:false,product_ids:['1','2'],last_checked_at:null,next_check_at:null};
    let sender=true, failRead=false, conflict=false, unknown=false, saves=0, sends=0, delayedSearch=false, emptyShops=false, workerAlive=1, batchActive=false;
    let recommendationMode='success', recommendationDelay=0;
    const recommended=products.filter(p=>p.total_stock>0 && p.sold_count>0).slice(0,5);
    await page.route('**/proc*/**',route=>route.fulfill({json:{status:'success',counts:{}}}));
    await page.route('https://cf.shopee.co.id/**',route=>route.fulfill({status:404,body:''}));
    await page.route('https://boost-fixtures.invalid/**',route=>route.fulfill({contentType:'image/svg+xml',body:'<svg xmlns="http://www.w3.org/2000/svg" width="40" height="40"><rect width="40" height="40" fill="#e98425"/><path d="M10 30V10h14v8H10" stroke="#1a1714" stroke-width="4" fill="none"/></svg>'}));
    await page.route(base+'/panel/boost',async route=>{
      const response=await route.fetch(), body=await response.text();
      const match=body.match(/(<script id="shop-logo-data" type="application\/json">)([^<]+)(<\/script>)/);
      const logos=JSON.parse(match[2]).map(shop=>({...shop,logo:'https://boost-fixtures.invalid/logo-'+shop.id+'.svg'}));
      let html=body.replace(match[0],match[1]+JSON.stringify(emptyShops?[]:logos)+match[3]);
      if(emptyShops)html=html.replace(/(<script id="boost-shops" type="application\/json">)[^<]+(<\/script>)/,'$1[]$2');
      await route.fulfill({response,body:html});
    });
    await page.route('**/procBoost/**',async route=>{
      const url=new URL(route.request().url()), action=url.pathname.split('/').pop();const data=route.request().method()==='POST'?route.request().postDataJSON():null;
      const id=data?.shop_id || Number(url.searchParams.get('shop_id')), profile=saved(id);
      if(data)assert.ok(route.request().headers()['x-csrf-token']);
      if(action==='status')return route.fulfill(failRead?{status:503,json:{status:'error',message:'Koneksi uji gagal.'}}:{json:{status:'success',shop:{id,name:'Toko uji',session_status:'connected'},profile,products:profile.product_ids.map(id=>({...products.find(p=>p.id===id),reason:id==='3'?'Stok kosong':''})),summary:{remaining_count:unknown?0:5,unresolved_count:unknown?1:0,batch_active:batchActive},worker:{alive:workerAlive},sender_enabled:sender,history:[],unresolved:unknown?[{run_id:1,product_id:'1',product_name:products[0].name,status:'unknown',attempted_at:'2026-09-29 08:00:00'}]:[]}});
      if(action==='catalog'){
        const search=url.searchParams.get('search'), p=Number(url.searchParams.get('page'))||1;
        if(delayedSearch && search==='old')await new Promise(r=>setTimeout(r,400));
        const rows=search==='empty'||search==='old'?[]:products;
        return route.fulfill({json:{status:'success',products:rows.slice((p-1)*10,p*10),page:p,pages:rows.length?3:1,total:rows.length}});
      }
      if(action==='recommendations'){
        assert.equal(route.request().method(),'GET');assert.equal(url.searchParams.has('search'),false);assert.equal(url.searchParams.has('page'),false);
        if(recommendationDelay)await new Promise(r=>setTimeout(r,recommendationDelay));
        if(recommendationMode==='error')return route.fulfill({status:503,json:{status:'error',message:'Rekomendasi gagal dimuat.'}});
        return route.fulfill({json:{status:'success',products:recommendationMode==='empty'?[]:recommendationMode==='partial'?recommended.slice(0,2):recommended}});
      }
      if(action==='save'){
        saves++;if(conflict)return route.fulfill({status:409,json:{status:'error',message:'Pengaturan berubah di sesi lain. Muat versi terbaru.'}});
        const p={...profile,product_ids:data.product_ids,version:profile.version+1};profiles.set(id,p);return route.fulfill({json:{status:'success',profile:p}});
      }
      if(action==='preview')return route.fulfill({json:{status:'success',products:data.product_ids.map(id=>({...products.find(p=>p.id===id),reason:id==='3'?'Stok kosong':''})),summary:{remaining_count:5}}});
      if(action==='toggle'){const p={...profile,enabled:data.enabled,version:profile.version+1};profiles.set(id,p);return route.fulfill({json:{status:'success',profile:p}});}
      if(action==='run'){sends++;assert.ok(data.request_key.length>=16);return route.fulfill({json:{status:'success',result:{success_count:1,failed_count:1,unknown_count:0}}});}
      if(action==='inspect')return route.fulfill({json:{status:'success',inspection:{products:{1:{eligible:true}},message:'Status tersedia tidak membuktikan hasil sebelumnya.'}}});
      if(action==='resolve'){assert.equal(data.confirmed,true);unknown=false;return route.fulfill({json:{status:'success'}});}
      throw new Error('Unexpected Boost request '+action);
    });
    await page.goto(base+'/panel/boost');
    const card=page.locator('[data-card]').first(), editor=page.locator('#boost-editor');
    await card.locator('[data-edit]').waitFor();await page.waitForFunction(()=>[...document.querySelectorAll('[data-card]')].every(c=>c.getAttribute('aria-busy')==='false'));
    assert.match(await card.locator('[data-saved-count]').innerText(),/2 \/ 5/);
    assert.equal(await card.getAttribute('data-state'),'paused');assert.equal(await card.locator('[data-mode]').getAttribute('data-tone'),'neutral');
    assert.equal(await card.locator('[data-edit] svg').getAttribute('aria-hidden'),'true');
    const firstShop=await card.getAttribute('data-shop-id');assert.ok((await card.locator('[data-shop-logo] img').getAttribute('src')).endsWith('/logo-'+firstShop+'.svg'));
    await card.locator('[data-edit]').click();await editor.waitFor({state:'visible'});await editor.locator('[data-product-id="1"]').waitFor();
    assert.equal(await editor.locator('[data-recommend]').evaluate(el=>el===document.activeElement),true);
    assert.ok((await editor.locator('[data-editor-logo] img').getAttribute('src')).endsWith('/logo-'+firstShop+'.svg'),'Popup preserves shop logo identity');
    await editor.locator('[data-next]').click();await editor.locator('[data-product-id="11"]').waitFor();await editor.locator('[data-product-id="11"]').check();
    assert.equal(await editor.locator('[data-chosen] li').count(),3);
    await card.evaluate(el=>el.querySelector('[data-refresh]').click());await page.waitForTimeout(100);
    assert.equal(await editor.locator('[data-chosen] li').count(),3,'Refresh preserves draft');
    conflict=true;await editor.locator('[data-save]').click();await editor.locator('[data-reload-version]').waitFor({state:'visible'});
    assert.equal(await editor.locator('[data-save]').isDisabled(),true);assert.equal(await editor.locator('[data-chosen] li').count(),3);
    conflict=false;await editor.locator('[data-reload-version]').click();await page.waitForFunction(()=>!document.querySelector('[data-save]').disabled);
    await editor.locator('[data-save]').click();await editor.waitFor({state:'hidden'});assert.equal(saves,2);assert.equal(sends,0,'Save never sends');
    await page.reload();await page.waitForFunction(()=>document.querySelector('[data-saved-count]').textContent==='3 / 5 produk');
    await card.locator('[data-toggle]').click();await page.locator('#boost-confirm').waitFor({state:'visible'});assert.match(await page.locator('[data-confirm-products]').innerText(),/Produk uji 11/);
    await page.locator('[data-confirm-action]').click();await page.locator('#boost-confirm').waitFor({state:'hidden'});assert.equal(await card.locator('[data-toggle]').innerText(),'Jeda pengulangan');
    assert.equal(await card.getAttribute('data-state'),'active');assert.equal(await card.locator('[data-mode]').getAttribute('data-tone'),'success');assert.equal(await page.locator('[data-total-active]').innerText(),'1 toko');
    await card.locator('[data-toggle]').click();await page.waitForFunction(()=>document.querySelector('[data-toggle]').textContent==='Aktifkan pengulangan');
    await card.locator('[data-manual]').click();await editor.locator('[data-product-id="1"]').waitFor();await editor.locator('[data-product-id="5"]').check();await editor.locator('[data-save]').click();await page.locator('[data-confirm-action]').click();await page.locator('#boost-confirm').waitFor({state:'hidden'});assert.equal(sends,1);assert.equal(saved(1).product_ids.includes('5'),false,'Manual leaves automation selections unchanged');assert.match(await card.locator('[data-action-result]').innerText(),/1 diterima/);
    await card.locator('[data-edit]').click();await editor.locator('[data-product-id="1"]').waitFor();
    await editor.locator('[data-product-id="3"]').check();await editor.locator('[data-product-id="4"]').check();assert.equal(await editor.locator('[data-product-id="5"]').isDisabled(),true,'Limit 5');
    await editor.locator('[data-product-id="4"]').uncheck();
    delayedSearch=true;await page.locator('#boost-search').fill('old');await editor.locator('[data-search-form] button').click();
    await page.locator('#boost-search').fill('');await editor.locator('[data-search-form] button').click();await editor.locator('[data-product-id="1"]').waitFor();await page.waitForTimeout(500);
    assert.equal(await editor.locator('[data-product-id]').count(),10,'Outdated catalog response ignored');
    await page.locator('#boost-search').fill('empty');await editor.locator('[data-search-form] button').click();await page.waitForFunction(()=>document.querySelector('[data-catalog-status]').textContent.startsWith('Tidak ada'));
    assert.equal(await editor.locator('[data-chosen] li').count(),4,'Empty search preserves selections');
    page.once('dialog',dialog=>dialog.dismiss());await page.keyboard.press('Escape');assert.equal(await editor.isVisible(),true,'Dirty close cancellable');
    page.once('dialog',dialog=>dialog.accept());await editor.locator('[data-editor-cancel]').click();await editor.waitFor({state:'hidden'});
    await card.locator('[data-edit]').click();await editor.locator('[data-product-id="1"]').waitFor();
    const beforeRecommendation=await editor.locator('[data-chosen]').innerText(), savesBefore=saves, sendsBefore=sends;
    await editor.locator('[data-next]').click();await editor.locator('[data-product-id="11"]').waitFor();
    await page.locator('#boost-search').fill('empty');await editor.locator('[data-search-form] button').click();await page.waitForFunction(()=>document.querySelector('[data-catalog-status]').textContent.startsWith('Tidak ada'));
    recommendationDelay=300;await editor.locator('[data-recommend]').click();
    assert.equal(await editor.locator('[data-recommend]').isDisabled(),true);assert.equal(await editor.locator('[data-save]').isDisabled(),true);
    await page.waitForFunction(()=>document.querySelector('[data-recommendation-status]').textContent.startsWith('5 produk'));
    assert.equal(await editor.locator('[data-chosen] li').count(),5);assert.match(await editor.locator('[data-chosen]').innerText(),/Terjual 100/);
    assert.equal(saves,savesBefore);assert.equal(sends,sendsBefore,'Recommendation never sends');assert.equal(await page.locator('#boost-search').inputValue(),'empty');
    await editor.locator('[data-undo-recommendation]').click();assert.equal(await editor.locator('[data-chosen]').innerText(),beforeRecommendation);assert.equal(await editor.locator('[data-save]').isDisabled(),true);
    recommendationDelay=0;recommendationMode='error';await editor.locator('[data-recommend]').click();await page.waitForFunction(()=>document.querySelector('[data-recommendation-status]').textContent.includes('gagal dimuat'));
    assert.equal(await editor.locator('[data-chosen]').innerText(),beforeRecommendation);
    recommendationMode='empty';await editor.locator('[data-recommend]').click();await page.waitForFunction(()=>document.querySelector('[data-recommendation-status]').textContent.startsWith('Belum ada'));
    assert.equal(await editor.locator('[data-chosen]').innerText(),beforeRecommendation);
    recommendationMode='partial';await editor.locator('[data-recommend]').click();await page.waitForFunction(()=>document.querySelector('[data-recommendation-status]').textContent.startsWith('2 produk'));
    assert.equal(await editor.locator('[data-chosen] li').count(),2);await editor.locator('[data-undo-recommendation]').click();
    recommendationMode='success';await editor.locator('[data-recommend]').click();await page.waitForFunction(()=>document.querySelector('[data-recommendation-status]').textContent.startsWith('5 produk'));
    await page.locator('#boost-search').fill('');await editor.locator('[data-search-form] button').click();await editor.locator('[data-product-id="1"]').waitFor();
    assert.deepEqual(await editor.locator('[data-product-id]:checked').evaluateAll(inputs=>inputs.map(input=>input.dataset.productId)),recommended.map(p=>p.id),'Recommendations check matching catalog rows');
    await editor.locator('[data-product-id="4"]').uncheck();assert.equal(await editor.locator('[data-undo-recommendation]').isVisible(),false,'Manual edit remains possible');
    await editor.locator('[data-product-id="4"]').check();await editor.locator('[data-save]').click();await editor.waitFor({state:'hidden'});
    assert.deepEqual([...saved(1).product_ids].sort(),recommended.map(p=>p.id).sort());assert.equal(sends,sendsBefore);
    await page.reload();await page.waitForFunction(()=>document.querySelector('[data-saved-count]').textContent==='5 / 5 produk');
    const screenshotProfile={...saved(1)};profiles.set(1,{...screenshotProfile,enabled:true,next_check_at:'2026-09-30 01:30:00',last_checked_at:'2026-09-30 00:30:00'});
    await card.locator('[data-refresh]').click();await page.waitForFunction(()=>document.querySelector('[data-mode]').textContent==='Pengulangan aktif');
    const output=path.join(root,'tmp/boost-ui');fs.mkdirSync(output,{recursive:true});
    for(const width of [320,500,999,1600])for(const theme of ['light','dark']){
      await page.setViewportSize({width,height:1000});await page.evaluate(t=>window.shopdashTheme.setMode(t),theme);
      assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'No page overflow '+width+' '+theme);
      await page.evaluate(()=>scrollTo(0,0));await page.screenshot({path:path.join(output,`${width}-${theme}.png`),animations:'disabled'});
      await card.locator('[data-edit]').click();await editor.locator('[data-product-id="1"]').waitFor();
      assert.equal(await editor.locator('.boost-editor-content').evaluate(el=>el.scrollTop),0,'Recommendation visible when opening');
      await editor.locator('[data-recommend]').click();await page.waitForFunction(()=>document.querySelector('[data-recommendation-status]').textContent.startsWith('5 produk'));
      assert.ok(await editor.evaluate(el=>el.scrollWidth<=el.clientWidth),'No editor overflow '+width);
      const rows=await editor.locator('.boost-product-row').evaluateAll(rows=>rows.map(row=>{const c=row.querySelector('input').getBoundingClientRect(),i=row.querySelector('.boost-product-image').getBoundingClientRect(),t=row.querySelector('.boost-product-title').getBoundingClientRect();return c.right<=i.left && i.right<=t.left && i.width===48;}));assert.ok(rows.every(Boolean),'Separate thumbnail/checkbox/title tracks');
      const targets=await editor.locator('button:visible').evaluateAll(els=>els.map(el=>el.getBoundingClientRect().height));assert.ok(targets.every(h=>h>=44),'Touch targets');
      const chosenRows=await editor.locator('[data-chosen] li').evaluateAll(rows=>rows.map(row=>{const image=row.querySelector('.boost-product-image').getBoundingClientRect(),text=row.querySelector('span:not(.boost-product-image)').getBoundingClientRect(),button=row.querySelector('button').getBoundingClientRect();return image.width===48 && image.right<=text.left && text.right<=button.left;}));assert.ok(chosenRows.every(Boolean),'Selected products reserve thumbnail/text/action columns');
      assert.equal(await editor.locator('.boost-product-image svg').first().getAttribute('aria-hidden'),'true');
      await editor.evaluate(async el=>{await Promise.all(el.getAnimations({subtree:true}).map(animation=>animation.finished.catch(()=>{})));});
      const contrast=await page.evaluate(()=>{
        const canvas=document.createElement('canvas');canvas.width=canvas.height=1;const ctx=canvas.getContext('2d');
        const luminance=rgb=>{const values=rgb.slice(0,3).map(v=>{v/=255;return v<=.04045?v/12.92:((v+.055)/1.055)**2.4;});return values[0]*.2126+values[1]*.7152+values[2]*.0722;};
        return [...document.querySelectorAll('#boost-monitor :is(p,h1,h2,strong,small,label,.boost-mode,.boost-product-state,.boost-worker-state,.btn:not(:disabled))')].filter(el=>el.getBoundingClientRect().height && getComputedStyle(el).visibility!=='hidden').map(el=>{
          ctx.clearRect(0,0,1,1);ctx.fillStyle='#fff';ctx.fillRect(0,0,1,1);const parents=[];for(let p=el;p;p=p.parentElement)parents.push(p);
          parents.reverse().forEach(p=>{ctx.fillStyle=getComputedStyle(p).backgroundColor;ctx.fillRect(0,0,1,1);});const bg=luminance([...ctx.getImageData(0,0,1,1).data]);
          ctx.fillStyle=getComputedStyle(el).color;ctx.fillRect(0,0,1,1);const fg=luminance([...ctx.getImageData(0,0,1,1).data]);return {text:el.textContent.slice(0,60),ratio:(Math.max(bg,fg)+.05)/(Math.min(bg,fg)+.05),color:getComputedStyle(el).color,background:getComputedStyle(el).backgroundColor};
        });
      });assert.ok(contrast.every(r=>r.ratio>=4.5),'Boost text contrast: '+JSON.stringify(contrast.filter(r=>r.ratio<4.5)));
      await page.keyboard.press('Tab');await editor.locator('[data-editor-close]').focus();assert.notEqual(await editor.locator('[data-editor-close]').evaluate(el=>getComputedStyle(el).outlineStyle),'none');
      await page.screenshot({path:path.join(output,`editor-${width}-${theme}.png`),animations:'disabled'});
      await editor.locator('[data-undo-recommendation]').click();
      await page.keyboard.press('Escape');await editor.waitFor({state:'hidden'});
    }
    profiles.set(1,screenshotProfile);await card.locator('[data-refresh]').click();await page.waitForFunction(()=>document.querySelector('[data-mode]').textContent==='Pengulangan dijeda');
    await page.setViewportSize({width:640,height:1000});await page.evaluate(()=>document.documentElement.style.zoom='2');
    await card.locator('[data-edit]').click();await editor.locator('[data-product-id="1"]').waitFor();
    assert.ok(await editor.evaluate(el=>el.scrollWidth<=el.clientWidth),'Editor at 200% zoom');
    assert.ok(await page.locator('[data-save]').isVisible(),'Save reachable at zoom');
    await page.keyboard.press('Escape');await page.evaluate(()=>document.documentElement.style.zoom='');
    sender=false;await card.locator('[data-refresh]').click();await page.waitForFunction(()=>document.querySelector('[data-operation-note]').textContent.includes('belum diaktifkan'));
    assert.equal(await card.locator('[data-toggle]').isDisabled(),true);assert.equal(await card.locator('[data-edit]').isDisabled(),false);
    unknown=true;sender=true;await card.locator('[data-refresh]').click();await page.waitForFunction(()=>document.querySelector('[data-mode]').textContent==='Hasil perlu diperiksa');
    assert.equal(await card.getAttribute('data-state'),'attention');assert.equal(await card.locator('[data-mode]').getAttribute('data-tone'),'warning');assert.equal(await page.locator('[data-total-attention]').innerText(),'1 toko');
    await page.screenshot({path:path.join(output,'attention-dark.png'),animations:'disabled'});
    await card.locator('.boost-history > summary').click();await card.locator('[data-unresolved] button').click();await page.locator('#boost-resolution').waitFor({state:'visible'});
    assert.equal(await page.locator('[data-resolution-save]').isDisabled(),true);await page.locator('[data-resolution-confirm]').check();await page.selectOption('#boost-resolution-outcome','confirmed_not_sent');await page.locator('[data-resolution-save]').click();await page.locator('#boost-resolution').waitFor({state:'hidden'});
    failRead=true;await card.locator('[data-refresh]').click();await page.waitForFunction(()=>document.querySelector('[data-load-state]').textContent.includes('Koneksi uji gagal'));
    failRead=false;await card.locator('[data-refresh]').click();await page.waitForFunction(()=>document.querySelector('[data-load-state]').hidden);
    assert.deepEqual(errors,[]);
    // Direct requests exercise validation only. No valid live mutation is sent.
    assert.equal((await context.request.get(base+'/procBoost/run')).status(),405);
    assert.equal((await context.request.post(base+'/procBoost/save',{data:{shop_id:1}})).status(),403);
    assert.equal((await context.request.get(base+'/procBoost/recommendations?shop_id=invalid')).status(),422);
    assert.equal((await context.request.post(base+'/procBoost/recommendations',{data:{shop_id:1}})).status(),405);
    const recommendationResponse=await context.request.get(base+'/procBoost/recommendations?shop_id=1');
    assert.equal(recommendationResponse.status(),200);const recommendationData=await recommendationResponse.json();
    assert.ok(recommendationData.products.length<=5);assert.ok(recommendationData.products.every(p=>Number(p.shop_id)===1 && Number(p.total_stock)>0 && Number(p.sold_count)>0));
    const csrf=await page.locator('#boost-monitor').getAttribute('data-csrf');
    assert.equal((await context.request.post(base+'/procBoost/save',{headers:{'X-CSRF-Token':csrf},data:{shop_id:1,version:0,product_ids:['1000000000000000']}})).status(),422);
    profiles.set(1,{...saved(1),enabled:true});workerAlive=0;
    await card.locator('[data-refresh]').click();await page.waitForFunction(()=>document.querySelector('[data-mode]').textContent==='Pengulangan tertahan');
    assert.equal(await card.locator('[data-mode]').getAttribute('data-tone'),'warning');assert.equal(await page.locator('[data-worker-state]').innerText(),'Worker belum terpantau');
    workerAlive=1;batchActive=true;await card.locator('[data-refresh]').click();await page.waitForFunction(()=>document.querySelector('[data-mode]').textContent==='Memproses');
    assert.equal(await card.locator('[data-mode]').getAttribute('data-tone'),'processing');assert.equal(await card.locator('[data-manual]').isDisabled(),true);
    batchActive=false;profiles.set(1,screenshotProfile);
    emptyShops=true;await page.reload();await page.waitForFunction(()=>document.querySelector('[data-worker-state]').textContent==='Belum ada toko');
    assert.equal(await page.locator('[data-total-active]').innerText(),'0 toko');assert.equal(await page.locator('[data-total-products]').innerText(),'0 produk');assert.equal(await page.locator('[data-worker-state]').getAttribute('data-tone'),'neutral');
    assert.deepEqual(errors,[]);
    console.log('PASS: visual status/icons/logos/thumbnail grids/empty shops, bestseller recommendations/checks/undo/empty/error/save, persistent selections, pagination/search races, save conflict, draft protection, activation/pause, mocked sends/reconciliation, disabled sender, API guards, eight responsive/theme states, contrast and keyboard');
  }finally{await browser?.close();php("session_id('"+sid+"');session_start();session_destroy();");}
})().catch(error=>{console.error(error);process.exitCode=1;});
