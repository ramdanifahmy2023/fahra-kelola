(() => {
  const root=document.getElementById('notification-menu');
  if(!root)return;
  const $=id=>document.getElementById('notification-'+id);
  const panel=$('panel'),bell=$('button');
  let unreadOnly=true,offset=0,rows=[],busy=false,revision=0,hasMore=false;
  const soundKey='shopdash:chat-sound:'+root.dataset.user, cursorKey=soundKey+':cursor';
  let audio=null,soundLoaded=false,soundBusy=false;
  function stored(key){try{return localStorage.getItem(key);}catch{return null;}}
  function soundReady(){return stored(soundKey)==='on'&&audio?.state==='running';}
  function soundControls(message){
    const supported=!!(navigator.locks&&(window.AudioContext||window.webkitAudioContext));
    const ready=soundReady(),enabled=stored(soundKey)==='on';
    $('sound-toggle').disabled=!supported||soundBusy;
    $('sound-toggle').setAttribute('aria-pressed',String(ready));
    $('sound-toggle').textContent=ready?'Matikan bunyi chat':enabled?'Aktifkan lagi bunyi chat':'Aktifkan bunyi chat';
    $('sound-status').textContent=message||(!supported?'Browser ini belum mendukung bunyi chat antar-tab.':ready?'Bunyi aktif untuk chat baru saat halaman terlihat.':enabled?'Klik untuk mengaktifkan bunyi di halaman ini.':'Bunyi hanya untuk chat baru saat Shopdash terbuka.');
  }
  function playChatTone(){
    if(!soundReady()||document.hidden)return;
    const start=audio.currentTime;
    for(const [offset,hz] of [[0,660],[.16,880]]){
      const oscillator=audio.createOscillator(),gain=audio.createGain();
      oscillator.type='sine';oscillator.frequency.value=hz;
      gain.gain.setValueAtTime(0,start+offset);gain.gain.linearRampToValueAtTime(.045,start+offset+.02);gain.gain.linearRampToValueAtTime(0,start+offset+.14);
      oscillator.connect(gain);gain.connect(audio.destination);
      oscillator.onended=()=>{oscillator.disconnect();gain.disconnect();};
      oscillator.start(start+offset);oscillator.stop(start+offset+.15);
    }
  }
  async function chatSound(cursor,baseline=false){
    if(!/^(0|[1-9]\d{0,19})$/.test(String(cursor))||!navigator.locks)return;
    const first=!soundLoaded;soundLoaded=true;
    try{
      await navigator.locks.request(cursorKey,()=>{
        let previous;try{previous=JSON.parse(stored(cursorKey));}catch{}
        const valid=/^(0|[1-9]\d{0,19})$/.test(String(previous?.cursor)),fresh=valid&&Date.now()-Number(previous.at)<90000;
        const advances=!valid||BigInt(cursor)>BigInt(previous.cursor);
        // Claim once across tabs, and silently establish a baseline after loading or an outage.
        const silent=first||baseline||!fresh;
        if(!silent&&!soundReady()&&stored(soundKey)==='on')return;
        localStorage.setItem(cursorKey,JSON.stringify({cursor:advances?String(cursor):previous.cursor,at:Date.now()}));
        if(!silent&&advances&&soundReady())playChatTone();
      });
    }catch{soundControls('Bunyi belum aktif. Izinkan penyimpanan browser lalu coba lagi.');}
  }
  $('sound-toggle').addEventListener('click',async()=>{
    if(soundBusy)return;soundBusy=true;
    try{
      if(soundReady()){localStorage.setItem(soundKey,'off');await audio.suspend();}
      else{
        const Audio=window.AudioContext||window.webkitAudioContext;
        audio ||= new Audio();audio.onstatechange=()=>soundControls();
        await audio.resume();
        if(audio.state!=='running')throw new Error('Browser belum mengizinkan bunyi. Klik Aktifkan bunyi chat lagi.');
        const data=await request('summary?limit=10');await chatSound(data.chat_cursor,true);
        if(data.chat_cursor===null||data.chat_cursor===undefined)throw new Error('Data chat belum tersedia. Coba aktifkan bunyi lagi.');
        localStorage.setItem(soundKey,'on');
      }
      soundControls();
    }catch(e){soundControls(e.message||'Bunyi chat belum dapat diaktifkan.');}
    finally{soundBusy=false;$('sound-toggle').disabled=false;}
  });
  window.addEventListener('storage',event=>{if(event.key===soundKey)soundControls();});
  soundControls();
  const number=v=>new Intl.NumberFormat('id-ID').format(Number(v||0));
  const text=(tag,value,className='')=>{const el=document.createElement(tag);el.textContent=value;el.className=className;return el;};
  const icon=name=>{const el=text('span',name,'material-symbols-outlined');el.setAttribute('aria-hidden','true');return el;};
  const date=value=>value ? new Date(value.replace(' ','T')+'Z').toLocaleString('id-ID',{timeZone:'Asia/Jakarta',day:'numeric',month:'short',hour:'2-digit',minute:'2-digit'})+' WIB' : 'Waktu sumber belum tersedia';
  function close(focus=false){panel.hidden=true;bell.setAttribute('aria-expanded','false');if(focus)bell.focus();}
  function error(message){$('error').textContent=message;$('error').hidden=false;}
  async function request(action,items){
    const response=await fetch(root.dataset.endpoint+'/'+action,{method:items?'POST':'GET',cache:'no-store',headers:items?{'Content-Type':'application/json','X-CSRF-Token':root.dataset.csrf}:{},body:items?JSON.stringify({items}):undefined});
    if(response.redirected)throw new Error('Sesi berakhir. Muat ulang halaman untuk masuk kembali.');
    let data;try{data=await response.json();}catch{throw new Error('Notifikasi belum dapat dimuat. Coba lagi.');}
    if(!response.ok||data.status!=='success')throw new Error(data.message||'Permintaan gagal. Coba lagi.');
    return data;
  }
  function render(data,countsOnly=false){
    const s=data.summary||{},count=Number(data.unread_count||0);
    $('badge').hidden=count===0;$('badge').textContent=count>99?'99+':number(count);
    bell.setAttribute('aria-label',count?'Notifikasi, '+number(count)+' belum dibaca':'Notifikasi');
    const announcement=number(count)+' belum dibaca · '+number(s.unread_urgent)+' mendesak';
    if($('live').textContent!==announcement)$('live').textContent=announcement;
    $('summary').textContent=announcement;
    $('source-warning').hidden=data.evaluation_available!==false;
    if(countsOnly)return;
    hasMore=!!data.has_more;rows=data.notifications||[];$('list').replaceChildren();
    for(const item of rows){
      const row=text('article','','notification-item');row.dataset.alertId=item.id;
      const heading=text('div','','notification-item-heading');
      heading.append(icon(['inventory_2','local_shipping','link_off','sync_problem','chat'].includes(item.icon)?item.icon:'notifications'));
      const body=text('div','','notification-item-body');
      const shop=text('div','','notification-shop');
      if(item.shop_logo && /^https?:\/\//.test(item.shop_logo)){
        const logo=document.createElement('img');logo.src=item.shop_logo;logo.alt='';logo.width=24;logo.height=24;logo.loading='lazy';logo.addEventListener('error',()=>logo.replaceWith(icon('storefront')),{once:true});shop.append(logo);
      }else shop.append(icon('storefront'));
      shop.append(text('span',item.shop_name||'Toko #'+item.shop_id));
      body.append(shop,text('h3',item.title),text('p',item.message));
      const severity=text('span',item.stale?'Data perlu diperbarui':item.type==='chat_incoming'?'Pesan masuk':item.severity==='urgent'?'Mendesak':'Perlu perhatian','notification-severity');
      severity.dataset.level=item.stale?'stale':item.severity;body.append(severity);
      if(item.deadline)body.append(text('p','Batas kirim '+date(new Date(item.deadline*1000).toISOString().slice(0,19).replace('T',' ')),'notification-time'));
      body.append(text('p','Data: '+date(item.source_at),'notification-time'));
      const actions=text('div','','notification-item-actions');
      const link=text('a',item.action_label,'notification-action');
      link.href=root.dataset.base+(typeof item.path==='string'&&/^\/panel(?:[/?#]|$)/.test(item.path)?item.path:'/panel');
      actions.append(link);
      if(item.unread){
        const read=text('button','','btn notification-read');read.type='button';read.setAttribute('aria-label','Tandai dibaca: '+item.title+' · '+item.shop_name);read.append(icon('done'));read.addEventListener('click',()=>mark([item]));actions.append(read);
      }else actions.append(text('span','Sudah dibaca','notification-read-label'));
      body.append(actions);heading.append(body);row.append(heading);$('list').append(row);
    }
    if(!rows.length){
      const message=unreadOnly&&s.total>0?'Tidak ada notifikasi baru. '+number(s.total)+' notifikasi tersimpan.':s.total>0?'Tidak ada notifikasi pada halaman ini.':'Belum ada notifikasi yang terdeteksi.';
      $('list').append(text('p',message,'notification-empty'));
    }
    $('mark-all').disabled=!rows.some(row=>row.unread)||busy;
    $('prev').disabled=offset===0||busy;$('next').disabled=!data.has_more||busy;
    const total=unreadOnly?s.unread:s.total;
    $('page-status').textContent=rows.length?number(offset+1)+'–'+number(offset+rows.length)+' dari '+number(total):'0 ditampilkan';
  }
  async function load(automatic=false){
    if(busy)return;
    const token=++revision;$('reload').disabled=true;
    try{
      const data=await request('summary?unread_only='+(unreadOnly?'1':'0')+'&limit=10&offset='+offset);
      if(token!==revision)return;
      $('error').hidden=true;
      render(data,automatic&&!panel.hidden);
      await chatSound(data.chat_cursor);
    }catch(e){if(token===revision)error(e.message);}
    finally{if(token===revision)$('reload').disabled=false;}
  }
  async function mark(items){
    if(busy)return;
    busy=true;revision++;$('error').hidden=true;$('mark-all').disabled=true;
    root.querySelectorAll('.notification-read,[data-notification-filter]').forEach(b=>b.disabled=true);
    $('prev').disabled=true;$('next').disabled=true;$('reload').disabled=true;
    let failed=false;
    try{await request('acknowledge',items.map(item=>({id:item.id,revision:item.revision})));offset=0;}
    catch(e){failed=true;error(e.message);$('error').focus();}
    finally{busy=false;root.querySelectorAll('.notification-read,[data-notification-filter]').forEach(b=>b.disabled=false);$('mark-all').disabled=!rows.some(row=>row.unread);$('reload').disabled=false;$('prev').disabled=offset===0;$('next').disabled=!hasMore;}
    if(!failed){await load();if(!panel.hidden)$('mark-all').disabled?$('reload').focus():$('mark-all').focus();}
  }
  bell.addEventListener('click',()=>{if(!panel.hidden){close();return;}panel.hidden=false;bell.setAttribute('aria-expanded','true');load();});
  $('close').addEventListener('click',()=>close(true));
  document.addEventListener('click',e=>{if(!root.contains(e.target))close();});
  root.addEventListener('keydown',e=>{if(e.key==='Escape'&&!panel.hidden){e.preventDefault();close(true);}});
  root.querySelectorAll('[data-notification-filter]').forEach(button=>button.addEventListener('click',()=>{
    unreadOnly=button.dataset.notificationFilter==='new';offset=0;
    root.querySelectorAll('[data-notification-filter]').forEach(el=>el.setAttribute('aria-pressed',String(el===button)));load();
  }));
  $('reload').addEventListener('click',()=>load());
  $('mark-all').addEventListener('click',()=>mark(rows.filter(row=>row.unread)));
  $('prev').addEventListener('click',()=>{offset=Math.max(0,offset-10);load();});
  $('next').addEventListener('click',()=>{offset+=10;load();});
  load();window.setInterval(()=>{if(!document.hidden)load(true);},30000);
})();
