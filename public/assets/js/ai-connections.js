(() => {
  const root=document.getElementById('ai-connections');
  const $=id=>document.getElementById('ai-'+id);
  const form=$('form');
  let connections=[], editing=null, baseline='', busy=false, pending=0, loadRevision=0;
  let catalog=[], filtered=[], active=-1;
  const fields={name:'name',base_url:'base-url',api_key:'api-key',default_model:'default-model'};
  const values=()=>Object.fromEntries(Object.entries(fields).map(([name,id])=>[name,$(id).value]));
  const dirty=()=>!form.hidden && JSON.stringify(values())!==baseline;
  const text=(tag,content,className='')=>{const el=document.createElement(tag);el.textContent=content;if(className)el.className=className;return el;};
  const icon=name=>{const el=text('span',name,'material-symbols-outlined');el.setAttribute('aria-hidden','true');return el;};
  function reveal(element) {document.getElementById('automation-page').dispatchEvent(new CustomEvent('automation-reveal',{detail:element}));}
  function announce(message) {$('message').hidden=false;$('message').textContent=message;}
  function clearErrors() {
    $('error').hidden=true;
    Object.values(fields).forEach(id=>{$(id).removeAttribute('aria-invalid');$(id+'-error').hidden=true;});
  }
  function showError(error,inline=false,attention=false) {
    if(!inline){$('list-error').textContent=error.message;$('list-error').hidden=false;if(attention){reveal($('list-error'));$('list-error').focus();}return;}
    clearErrors();$('error').replaceChildren(text('p',error.message));$('error').hidden=false;
    Object.entries(error.fields||{}).forEach(([name,message])=>{
      const id=fields[name];if(!id)return;
      $(id).setAttribute('aria-invalid','true');$(id+'-error').textContent=message;$(id+'-error').hidden=false;
      const a=text('a',message);a.href='#ai-'+id;a.addEventListener('click',event=>{event.preventDefault();$(id).focus();});$('error').append(a);
    });
    reveal($('error'));$('error').focus();
  }
  async function request(action,data) {
    const response=await fetch(root.dataset.endpoint+'/'+action,{method:data?'POST':'GET',headers:data?{'Content-Type':'application/json','X-CSRF-Token':document.getElementById('automation-page').dataset.csrf}:{},body:data?JSON.stringify(data):undefined});
    if(response.redirected)throw new Error('Sesi berakhir. Muat ulang halaman untuk masuk kembali.');
    let result;try{result=await response.json();}catch{throw new Error('Respons server tidak dapat dibaca. Coba muat ulang daftar.');}
    if(!response.ok || result.status!=='success'){const error=new Error(result.message||'Permintaan gagal. Coba lagi.');error.fields=result.fields;throw error;}
    return result;
  }
  function status(result,item) {
    if(!result)return 'Belum diuji.';
    if(result.version!==item.version)return 'Konfigurasi berubah; perlu diuji ulang.';
    const at=new Date(result.at).toLocaleString('id-ID',{timeZone:'Asia/Jakarta'});
    return (result.model?'Model '+result.model+': ':'')+result.message+' '+at+' WIB';
  }
  function render() {
    $('list').replaceChildren();
    if(!connections.length) {
      const empty=text('div','','automation-empty automation-section');
      empty.append(icon('link'),text('h3','Belum ada koneksi'),text('p','Tambahkan base URL, API key, dan model/combo untuk mulai.'));
      $('list').append(empty);
    }
    for(const item of connections) {
      const row=document.createElement('article');row.className='ai-row';row.dataset.connectionId=item.id;
      const heading=text('div','','ai-row-heading'),identity=text('div');
      identity.append(text('h3',item.name),text('p',item.default_model,'ai-model-name'));heading.append(icon('link'),identity);row.append(heading);
      const result=item.test_result;
      const state=!result?'Belum diuji':result.version!==item.version?'Perlu diuji ulang':result.ok?'Uji model berhasil':'Uji model gagal';
      const meta=text('div','','ai-row-meta'),testState=text('span',state);
      testState.prepend(icon(!result||result.version!==item.version?'schedule':result.ok?'check_circle':'error'));
      meta.append(testState,text('span',item.shops.length+' toko'));row.append(meta);
      if(result && result.version===item.version && !result.ok)row.append(text('p',result.message,'automation-error'));
      const detail=document.createElement('details');detail.className='automation-disclosure';
      const summary=text('summary','Detail koneksi');summary.append(icon('expand_more'));detail.append(summary);
      detail.append(text('p',item.base_url,'ai-url'),text('p',item.has_api_key?'Key tersimpan':'Key tidak tersedia','automation-help'));
      if(item.shops.length)detail.append(text('p',item.shops.map(shop=>shop.name).join(', '),'automation-help'));
      detail.append(text('p','Katalog: '+status(item.catalog_result,item),'automation-help'),text('p','Generasi: '+status(item.test_result,item),'automation-help'));
      row.append(detail);
      const actions=document.createElement('div');actions.className='ai-actions';
      const more=document.createElement('details');more.className='ai-more';
      const moreSummary=text('summary','Lainnya','btn');moreSummary.setAttribute('aria-label','Aksi lainnya untuk '+item.name);moreSummary.append(icon('expand_more'));more.append(moreSummary);
      const moreActions=text('div','','ai-more-actions');more.append(moreActions);
      for(const [action,label] of [['edit','Ubah'],['test','Uji model'],['models','Muat model'],['delete','Hapus']]) {
        const button=text('button',label,'btn');button.type='button';button.dataset.action=action;
        button.prepend(icon({edit:'edit',test:'science',models:'list',delete:'delete'}[action]));
        button.disabled=busy;button.setAttribute('aria-label',label+' '+item.name);
        button.addEventListener('click',()=>{more.open=false;act(action,item,button);});
        (action==='models'||action==='delete'?moreActions:actions).append(button);
      }
      actions.append(more);
      row.append(actions);$('list').append(row);
    }
    $('list-state').textContent=connections.length+' koneksi tersimpan.';
    window.dispatchEvent(new CustomEvent('ai-connections-changed',{detail:connections}));
  }
  async function load() {
    const revision=++loadRevision;$('reload').disabled=true;$('list-error').hidden=true;$('list-state').textContent='Memuat daftar koneksi…';
    try {const result=await request('list');if(revision!==loadRevision)return;connections=result.connections;render();}
    catch(error){if(revision!==loadRevision)return;showError(error);$('list-state').textContent='Daftar belum dapat dimuat.';const state=document.getElementById('automation-connection-state');if(state)state.textContent='Daftar koneksi gagal dimuat. Coba Muat ulang daftar.';}
    finally {if(revision===loadRevision)$('reload').disabled=false;}
  }
  function closeOptions() {$('model-options').hidden=true;$('default-model').setAttribute('aria-expanded','false');$('default-model').removeAttribute('aria-activedescendant');active=-1;}
  function options() {
    const query=$('default-model').value.toLowerCase();
    filtered=catalog.filter(item=>item.id.toLowerCase().includes(query)).slice(0,100);active=-1;
    $('model-options').replaceChildren();$('default-model').removeAttribute('aria-activedescendant');
    if(!filtered.length)$('model-options').append(text('p',catalog.length?'Tidak ada kecocokan. Nama manual tetap bisa disimpan.':'Belum ada katalog. Simpan koneksi lalu klik Muat model.','ai-option-note'));
    filtered.forEach((item,index)=>{
      const button=text('button',(item.kind==='combo'?'Combo: ':'Model: ')+item.id);button.type='button';button.id='ai-option-'+index;button.tabIndex=-1;button.setAttribute('role','option');button.setAttribute('aria-selected','false');
      button.addEventListener('click',()=>choose(index));$('model-options').append(button);
    });
    $('model-options').hidden=false;$('default-model').setAttribute('aria-expanded','true');
  }
  function choose(index) {$('default-model').value=filtered[index].id;closeOptions();changed();$('default-model').focus();}
  function setBusy(value) {
    busy=value;
    form.querySelectorAll('input,button').forEach(el=>{el.disabled=value;});
    if(!value && root.dataset.ready!=='1')$('save').disabled=true;
    $('add').disabled=value;$('reload').disabled=value;
    $('list').querySelectorAll('button').forEach(el=>{el.disabled=value;});
  }
  function changed() {
    $('form-status').textContent=dirty()?'Ada perubahan koneksi yang belum disimpan.':'';
    const tab=document.getElementById('automation-tab-connections');
    tab.dataset.dirty=String(dirty());tab.setAttribute('aria-label',dirty()?'Koneksi AI, perubahan belum disimpan':'Koneksi AI');
  }
  function open(item=null,models=[]) {
    if(busy || (dirty() && !window.confirm('Buang perubahan koneksi yang belum disimpan?')))return false;
    editing=item;catalog=models;clearErrors();closeOptions();
    for(const [name,id] of Object.entries(fields))$(id).value=name==='api_key'?'':item?.[name]||'';
    $('api-key').type='password';$('key-toggle').textContent='Tampilkan';$('key-toggle').setAttribute('aria-pressed','false');$('api-key').required=!item;
    $('form-title').textContent=item?'Ubah koneksi':'Tambah koneksi';
    $('key-help').textContent=item?'Key tersimpan. Kosongkan untuk mempertahankan, atau isi key pengganti. Base URL baru memerlukan key baru.':'Key disimpan terenkripsi dan tidak ditampilkan kembali.';
    const inheriting=item?.shops.filter(shop=>shop.inherits_model)||[];
    $('impact').textContent=inheriting.length?'Perubahan model default berlaku untuk: '+inheriting.map(shop=>shop.name).join(', ')+'.':'Model default dipakai oleh toko yang tidak mengisi model sendiri.';
    form.hidden=false;baseline=JSON.stringify(values());changed();setBusy(false);reveal(form);$('name').focus();return true;
  }
  async function act(action,item,button) {
    if(busy)return;
    if(action==='edit'){open(item);return;}
    if(action==='delete' && !window.confirm('Hapus koneksi “'+item.name+'”? API key tersimpan akan dihapus.'))return;
    if(action==='models' && dirty()) {announce('Simpan atau batalkan perubahan form sebelum memuat katalog.');return;}
    if(action==='test' && !window.confirm('Uji model “'+item.default_model+'” pada koneksi “'+item.name+'”? Satu permintaan AI dapat memakai kuota.'))return;
    pending++;setBusy(true);$('list-error').hidden=true;button.textContent=action==='delete'?'Menghapus…':action==='models'?'Memuat…':'Menguji…';
    let result=null, failure=null;
    try {
      result=await request(action,{id:item.id,version:item.version,...(action==='test'?{model:item.default_model}:{})});
      if(action==='delete') {
        if(editing?.id===item.id){form.hidden=true;editing=null;$('api-key').value='';changed();}
        announce('Koneksi dihapus.');
      } else announce(action==='models'?'Daftar model dimuat. Pilih model atau ketik nama combo, lalu simpan.':'Model '+item.default_model+' berhasil diuji. Tidak ada balasan rating yang dikirim.');
    } catch(error){failure=error;}
    finally {await load();pending--;setBusy(false);}
    if(failure)showError(failure,false,true);
    if(result && action==='models') {open(result.connection,result.models);$('default-model').focus();options();}
    else if(!failure && !document.getElementById('automation-connections').hidden) {
      const row=$('list').querySelector('[data-connection-id="'+item.id+'"]');
      (action==='test'?row?.querySelector('[data-action=test]'):row?.querySelector('.ai-more summary') || $('add'))?.focus();
    }
  }
  form.addEventListener('input',changed);
  $('default-model').addEventListener('input',()=>{if(!$('model-options').hidden)options();});
  $('model-toggle').addEventListener('click',()=>{$('model-options').hidden?options():closeOptions();$('default-model').focus();});
  $('default-model').addEventListener('keydown',event=>{
    if(event.key==='Escape'){closeOptions();event.preventDefault();}
    if(event.key==='ArrowDown'||event.key==='ArrowUp') {
      event.preventDefault();if($('model-options').hidden)options();if(!filtered.length)return;
      active=event.key==='ArrowDown'?Math.min(active+1,filtered.length-1):Math.max(active-1,0);
      $('model-options').querySelectorAll('[role=option]').forEach((el,index)=>el.setAttribute('aria-selected',String(index===active)));
      const option=document.getElementById('ai-option-'+active);$('default-model').setAttribute('aria-activedescendant',option.id);option.scrollIntoView({block:'nearest'});
    }
    if(event.key==='Enter'&&!$('model-options').hidden&&active>=0){event.preventDefault();choose(active);}
  });
  document.addEventListener('click',event=>{if(!event.target.closest('.ai-combobox'))closeOptions();});
  document.addEventListener('click',event=>root.querySelectorAll('.ai-more[open]').forEach(el=>{if(!el.contains(event.target))el.open=false;}));
  root.addEventListener('keydown',event=>{if(event.key==='Escape'){const more=event.target.closest('.ai-more');if(more?.open){event.preventDefault();more.open=false;more.querySelector('summary').focus();}}});
  $('key-toggle').addEventListener('click',()=>{const show=$('api-key').type==='password';$('api-key').type=show?'text':'password';$('key-toggle').textContent=show?'Sembunyikan':'Tampilkan';$('key-toggle').setAttribute('aria-pressed',String(show));});
  $('add').addEventListener('click',()=>open());
  $('cancel').addEventListener('click',()=>{if(dirty()&&!window.confirm('Buang perubahan koneksi yang belum disimpan?'))return;form.hidden=true;editing=null;$('api-key').value='';closeOptions();changed();$('add').focus();});
  $('reload').addEventListener('click',load);
  window.addEventListener('beforeunload',event=>{if(dirty()||pending){event.preventDefault();event.returnValue='';}});
  form.addEventListener('submit',async event=>{
    event.preventDefault();if(busy||root.dataset.ready!=='1')return;
    let saved=false;
    const input=values();if(editing){input.id=editing.id;input.version=editing.version;if(!input.api_key)delete input.api_key;}
    clearErrors();pending++;setBusy(true);$('save').textContent='Menyimpan…';
    try {
      const result=await request(editing?'update':'create',input);
      editing=result.connection;$('api-key').value='';$('base-url').value=editing.base_url;$('name').value=editing.name;$('default-model').value=editing.default_model;
      baseline=JSON.stringify(values());form.hidden=true;catalog=[];announce('Koneksi tersimpan. Pilih koneksi pada toko lalu simpan pengaturan toko.');await load();
      saved=true;
    } catch(error){showError(error,true);}
    finally {pending--;setBusy(false);$('save').textContent='Simpan koneksi';changed();if(saved&&!document.getElementById('automation-connections').hidden)$('add').focus();}
  });
  load();
})();
