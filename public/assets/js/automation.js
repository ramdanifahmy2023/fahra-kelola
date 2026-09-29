(() => {
  const root = document.getElementById('automation-page');
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
  const dirty = () => JSON.stringify(config()) !== baseline;
  function fill(value) {
    const mapping = {scope:'scope',start:'start_date',end:'end_date','persona-name':'persona_name',persona:'persona',support:'support_policy','max-length':'max_reply_chars',model:'model'};
    Object.entries(mapping).forEach(([id,key]) => {field(id).value = value[key];});
    form.querySelectorAll('[name="target-star"]').forEach(el => {el.checked = value.stars.includes(Number(el.value));});
    for (let star=1; star<=5; star++) { field('action-'+star).value=value.rules[star].action; field('rule-'+star).value=value.rules[star].instruction; }
  }
  function refresh() {
    const scope=field('scope').value;
    field('dates').hidden=scope!=='date_range'; field('start').required=scope==='date_range';
    field('scope-help').textContent={new:'Waktu mulai akan ditetapkan ketika automasi diaktifkan. Saat ini belum ada aktivasi.',date_range:'Tanggal mengikuti WIB. Kosongkan tanggal akhir untuk menyertakan rating setelah tanggal mulai.',all:'Mencakup riwayat yang belum dibalas. Tidak ada rating yang diproses saat konfigurasi disimpan.'}[scope];
    for (let star=1; star<=5; star++) root.querySelector('[data-rule-summary="'+star+'"]').textContent=labels[field('action-'+star).value];
    field('save-status').textContent=dirty() ? 'Ada perubahan yang belum disimpan.' : version ? 'Konfigurasi tersimpan · versi '+version : 'Belum ada konfigurasi tersimpan untuk toko ini.';
  }
  async function request(action, payload) {
    const response = await fetch(root.dataset.endpoint+'/'+action,{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':root.dataset.csrf,'X-Requested-With':'XMLHttpRequest'},body:JSON.stringify({shop_id:initial.shop_id,...payload})});
    if (response.redirected) throw new Error('Sesi berakhir. Muat ulang halaman untuk masuk kembali.');
    let result;
    try {result=await response.json();} catch {throw new Error('Respons tidak dapat dibaca. Muat ulang halaman lalu coba lagi.');}
    if (!response.ok || result.status!=='success') throw new Error(result.message || 'Permintaan gagal. Coba lagi.');
    return result;
  }
  fill(initial.profile.config); baseline=JSON.stringify(config()); refresh();
  function changed() {refresh(); previewRevision++; field('preview-result').hidden=true;}
  form.addEventListener('input',changed); form.addEventListener('change',changed); field('model').addEventListener('input',changed);
  window.addEventListener('beforeunload',event=>{if(dirty() || saving){event.preventDefault();event.returnValue='';}});
  form.addEventListener('submit',async event=>{
    event.preventDefault(); if(saving) return;
    saving=true; const submitted=config(); const snapshot=JSON.stringify(submitted);
    field('save-button').disabled=true; field('save-button').textContent='Menyimpan…'; field('error').hidden=true;
    try {
      const result=await request('save',{version,config:submitted});
      version=result.profile.version;
      if (JSON.stringify(config())===snapshot) {fill(result.profile.config); baseline=JSON.stringify(config());}
      else baseline=snapshot;
      refresh();
    } catch(error) {field('error').textContent=error.message;field('error').hidden=false;field('error').focus();}
    finally {saving=false;field('save-button').disabled=false;field('save-button').textContent='Simpan konfigurasi';}
  });
  field('preview-form').addEventListener('input',()=>{previewRevision++;field('preview-result').hidden=true;});
  field('preview-form').addEventListener('submit',async event=>{
    event.preventDefault(); if(previewing) return;
    previewing=true; const revision=previewRevision;
    field('preview-button').disabled=true; field('preview-button').textContent='Memeriksa…';field('preview-error').hidden=true;field('preview-result').hidden=true;
    try {
      const result=await request('preview',{config:config(),sample:{star:Number(field('sample-star').value),date:field('sample-date').value,text:field('sample-text').value,replied:field('sample-replied').checked}});
      if(revision!==previewRevision) return;
      const preview=result.preview;
      field('preview-action').textContent=labels[preview.action];field('preview-reason').textContent=preview.reason;
      field('prompt-detail').hidden=!preview.messages.length;
      field('preview-prompt').textContent=preview.messages.map(message=>message.content).join('\n\n');field('preview-result').hidden=false;
    } catch(error) {field('preview-error').textContent=error.message;field('preview-error').hidden=false;}
    finally {previewing=false;field('preview-button').disabled=false;field('preview-button').textContent='Periksa aturan';}
  });
})();
