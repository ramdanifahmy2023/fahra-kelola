(() => {
  const root = document.getElementById('automation-page');
  const tabs = [...root.querySelectorAll('[role="tab"]')];
  function activate(id, focus = false, updateHash = true) {
    const selected = tabs.find(tab => tab.getAttribute('aria-controls') === id);
    if (!selected) return;
    tabs.forEach(tab => {
      const active = tab === selected;
      tab.setAttribute('aria-selected', String(active)); tab.tabIndex = active ? 0 : -1;
      document.getElementById(tab.getAttribute('aria-controls')).hidden = !active;
    });
    root.querySelector('.automation-shop').hidden = id === 'automation-connections';
    if (updateHash) history.replaceState(null, '', '#' + (id === 'automation-connections' ? 'ai-connections' : id));
    if (focus) selected.focus();
  }
  function reveal(element) {
    const panel = element.closest('[role="tabpanel"]');
    if (panel) activate(panel.id);
    for (let parent = element.parentElement; parent && parent !== root; parent = parent.parentElement) {
      if (parent.tagName === 'DETAILS') parent.open = true;
    }
  }
  root.addEventListener('automation-reveal', event => reveal(event.detail));
  root.addEventListener('invalid', event => reveal(event.target), true);
  tabs.forEach((tab, index) => {
    tab.addEventListener('click', () => activate(tab.getAttribute('aria-controls')));
    tab.addEventListener('keydown', event => {
      let next;
      if (event.key === 'ArrowRight') next = (index + 1) % tabs.length;
      if (event.key === 'ArrowLeft') next = (index + tabs.length - 1) % tabs.length;
      if (event.key === 'Home') next = 0;
      if (event.key === 'End') next = tabs.length - 1;
      if (next !== undefined) { event.preventDefault(); activate(tabs[next].getAttribute('aria-controls'), true); }
    });
  });
  root.querySelectorAll('[data-open-tab]').forEach(link => link.addEventListener('click', event => {
    event.preventDefault(); activate(link.dataset.openTab, true);
  }));
  function fromHash() {
    const id = location.hash.slice(1);
    activate(id === 'ai-connections' ? 'automation-connections' : id || 'automation-settings', false, false);
  }
  window.addEventListener('hashchange', fromHash); fromHash();
  // Release the sticky save bar while the on-screen keyboard may cover the editor.
  root.addEventListener('focusin', event => root.classList.toggle('automation-input-focus', event.target.matches('input, textarea, select')));
  root.addEventListener('focusout', () => requestAnimationFrame(() => root.classList.toggle('automation-input-focus', root.contains(document.activeElement) && document.activeElement.matches('input, textarea, select'))));
  if (!document.getElementById('automation-bootstrap')) return;
  const initial = JSON.parse(document.getElementById('automation-bootstrap').textContent);
  const form = document.getElementById('automation-form');
  const field = name => document.getElementById('automation-' + name);
  const labels = {ai:'Balasan AI', review:'Tinjau dahulu', skip:'Lewati'};
  let version = initial.profile.version;
  let baseline = '';
  let saving = false;
  let previewing = false;
  let previewRevision = 0;

  function config() {
    const rules = {};
    for (let star = 1; star <= 5; star++) rules[star] = {action:field('action-' + star).value, instruction:field('rule-' + star).value};
    return {scope:field('scope').value, start_date:field('start').value, end_date:field('end').value,
      stars:[...form.querySelectorAll('[name="target-star"]:checked')].map(el => Number(el.value)),
      persona_name:field('persona-name').value, persona:field('persona').value, support_policy:field('support').value,
      max_reply_chars:Number(field('max-length').value), model:field('model').value, rules};
  }
  const state = () => ({config:config(),connection_id:Number(field('connection').value)||null});
  const dirty = () => JSON.stringify(state()) !== baseline;
  function fill(value) {
    const mapping = {scope:'scope',start:'start_date',end:'end_date','persona-name':'persona_name',persona:'persona',support:'support_policy','max-length':'max_reply_chars',model:'model'};
    Object.entries(mapping).forEach(([id,key]) => {field(id).value = value[key];});
    form.querySelectorAll('[name="target-star"]').forEach(el => {el.checked = value.stars.includes(Number(el.value));});
    for (let star=1; star<=5; star++) { field('action-'+star).value=value.rules[star].action; field('rule-'+star).value=value.rules[star].instruction; }
  }
  function refresh() {
    const scope=field('scope').value;
    field('dates').hidden=scope!=='date_range'; field('start').required=scope==='date_range';
    field('scope-help').textContent={new:'Awal periode menunggu aktivasi.',date_range:'Waktu WIB. Tanggal akhir boleh dikosongkan.',all:'Seluruh riwayat yang belum dibalas.'}[scope];
    for (let star=1; star<=5; star++) {
      root.querySelector('[data-rule-summary="'+star+'"]').textContent=labels[field('action-'+star).value];
      root.querySelector('[data-rule-target="'+star+'"]').hidden=form.querySelector('[name="target-star"][value="'+star+'"]').checked;
    }
    field('persona-label').textContent=field('persona-name').value.trim();
    field('persona-label').hidden=!field('persona-name').value.trim();
    field('persona-excerpt').textContent=field('persona').value.trim() || 'Belum ada gaya bicara. Buka Ubah persona untuk mengisinya.';
    field('override-state').hidden=!field('model').value.trim();
    field('save-status').textContent=dirty() ? 'Perubahan belum disimpan' : version ? 'Pengaturan tersimpan' : 'Belum disimpan';
    field('tab-settings').dataset.dirty=String(dirty());
    field('tab-settings').setAttribute('aria-label',dirty() ? 'Aturan toko, perubahan belum disimpan' : 'Aturan toko');
    field('preview-draft').textContent=dirty() ? 'Menggunakan perubahan yang belum disimpan.' : 'Menggunakan pengaturan pada formulir toko.';
  }
  async function request(action, payload) {
    const response = await fetch(root.dataset.endpoint+'/'+action,{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':root.dataset.csrf,'X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({shop_id:initial.shop_id,...payload})});
    if (response.redirected) throw new Error('Sesi berakhir. Muat ulang halaman untuk masuk kembali.');
    let result;
    try {result=await response.json();} catch {throw new Error('Respons tidak dapat dibaca. Muat ulang halaman lalu coba lagi.');}
    if (!response.ok || result.status!=='success') throw new Error(result.message || 'Permintaan gagal. Coba lagi.');
    return result;
  }
  let connections=[];
  const selected=initial.profile.connection_id;
  if(selected) field('connection').add(new Option('Koneksi tersimpan #'+selected,String(selected)));
  field('connection').value=selected ? String(selected) : '';
  function providerInfo() {
    const choice=connections.find(item=>item.id===Number(field('connection').value));
    field('effective-model').textContent=choice ? 'Model: '+(field('model').value.trim() || choice.default_model) : '';
  }
  window.addEventListener('ai-connections-changed',event=>{
    connections=event.detail; const value=field('connection').value;
    field('connection').replaceChildren(new Option('Tanpa koneksi',''));
    connections.forEach(item=>field('connection').add(new Option(item.name,String(item.id))));
    if(value && !connections.some(item=>String(item.id)===value)) field('connection').add(new Option('Koneksi tidak tersedia #'+value,value));
    field('connection').value=value;
    field('connection-state').textContent=connections.length ? 'Koneksi bersama; persona tetap khusus toko.' : 'Belum ada koneksi.';
    field('manage-connections').textContent=connections.length ? 'Kelola koneksi' : 'Tambah koneksi';
    providerInfo();
  });
  fill(initial.profile.config); baseline=JSON.stringify(state()); refresh(); providerInfo();
  function clearPreview() {previewRevision++; field('preview-result').hidden=true; field('preview-empty').hidden=false;}
  function changed() {refresh(); clearPreview();}
  form.addEventListener('input',changed); form.addEventListener('change',changed); field('model').addEventListener('input',providerInfo); field('connection').addEventListener('change',providerInfo);
  window.addEventListener('beforeunload',event=>{if(dirty() || saving){event.preventDefault();event.returnValue='';}});
  form.addEventListener('submit',async event=>{
    event.preventDefault(); if(saving) return;
    saving=true; const submitted=state(); const snapshot=JSON.stringify(submitted);
    field('save-button').disabled=true; field('save-button').textContent='Menyimpan…'; field('error').hidden=true;
    try {
      const result=await request('save',{version,...submitted});
      version=result.profile.version;
      if (JSON.stringify(state())===snapshot) {fill(result.profile.config); baseline=JSON.stringify(state());}
      else baseline=snapshot;
      refresh();
    } catch(error) {field('error').textContent=error.message;field('error').hidden=false;reveal(field('error'));field('error').focus();}
    finally {saving=false;field('save-button').disabled=false;field('save-button').textContent='Simpan pengaturan';}
  });
  field('preview-form').addEventListener('input',clearPreview);
  field('preview-form').addEventListener('submit',async event=>{
    event.preventDefault(); if(previewing) return;
    previewing=true; const revision=previewRevision;
    field('preview-button').disabled=true; field('preview-button').textContent='Memeriksa…';field('preview-error').hidden=true;field('preview-result').hidden=true;
    try {
      const result=await request('preview',{config:config(),sample:{star:Number(field('sample-star').value),date:field('sample-date').value,text:field('sample-text').value,replied:field('sample-replied').checked}});
      if(revision!==previewRevision) return;
      const preview=result.preview;
      field('preview-target').textContent=field('sample-star').value+' bintang · '+(config().stars.includes(Number(field('sample-star').value)) ? 'Bintang ditargetkan' : 'Bintang di luar target');
      field('preview-action').textContent=labels[preview.action];field('preview-reason').textContent=preview.reason;
      field('prompt-detail').hidden=!preview.messages.length;
      field('preview-prompt').textContent=preview.messages.map(message=>message.content).join('\n\n');field('preview-result').hidden=false;field('preview-empty').hidden=true;
    } catch(error) {field('preview-error').textContent=error.message;field('preview-error').hidden=false;reveal(field('preview-error'));field('preview-error').focus();}
    finally {previewing=false;field('preview-button').disabled=false;field('preview-button').textContent='Periksa aturan';}
  });
})();
