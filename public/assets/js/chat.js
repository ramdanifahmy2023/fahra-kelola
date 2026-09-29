(() => {
  const $ = id => document.getElementById(id);
  const page = $('chat-page'), api = page.dataset.api, shell = $('chat-shell');
  const state = {shop: Number(page.dataset.shop), shops: [], rows: [], selected: null, version: 0, listVersion: 0, detailVersion: 0, loading: false, popup: false};
  const key = row => row ? `${row.shop_id}:${row.remote_conversation_id}` : '';
  const selectedKey = () => key(state.selected);
  const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const date = value => value ? new Date(value.replace(' ', 'T') + 'Z').toLocaleString('id-ID', {dateStyle:'short',timeStyle:'short'}) : 'belum tersedia';
  const label = status => status === 'closed' ? 'Ditutup' : status === 'activated' ? 'Aktif' : status || 'Belum diketahui';
  const notice = (text, error = false) => { $('chat-state').textContent = text; $('chat-state').classList.toggle('text-error', error); };
  async function request(path, body) {
    const response = await fetch(api + path, {method: body ? 'POST' : 'GET', cache:'no-store', headers: {'X-Requested-With':'XMLHttpRequest', Accept:'application/json', ...(body ? {'X-CSRF-Token':page.dataset.csrf, 'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'} : {})}, ...(body ? {body:new URLSearchParams(body)} : {})});
    const result = await response.json().catch(() => ({}));
    if (!response.ok || result.status !== 'success') { const error = new Error(result.message || 'Permintaan chat gagal. Coba perbarui data.'); error.result = result; error.httpStatus = response.status; throw error; }
    return result;
  }
  const target = row => ({shop_id: row.shop_id, conversation_id: row.remote_conversation_id});
  function choose(row) {
    state.selected = row; state.version++; state.detailVersion++;
    $('chat-detail').classList.toggle('hidden', !row); $('chat-detail-empty').classList.toggle('hidden', Boolean(row));
    shell.classList.toggle('chat-detail-open', Boolean(row));
    $('chat-floating-launcher').classList.toggle('hidden', Boolean(row) || state.popup);
    if (row) {
      $('chat-detail-buyer').textContent = row.buyer_name || 'Pembeli';
      $('chat-detail-meta').textContent = (row.shop_name || '') + ' · ' + label(row.status);
      $('chat-messages').textContent = 'Memuat pesan…'; delete $('chat-messages').dataset.signature; $('chat-history-state').textContent = '';
      loadMessages(); queueRefresh(row).catch(error => { if (key(row) === selectedKey()) $('chat-history-state').textContent = error.message; });
    }
    renderRows();
  }
  function renderShops() {
    const focusedShop = $('chat-shops').contains(document.activeElement) ? document.activeElement.dataset.shopId : null;
    $('chat-shops').replaceChildren();
    for (const shop of [{shop_id:0,shop_name:'Semua toko'}, ...state.shops]) {
      const button = document.createElement('button'); button.type = 'button'; button.dataset.shopId = shop.shop_id;
      button.className = 'chat-shop-button'; button.setAttribute('aria-pressed', String(Number(shop.shop_id) === state.shop));
      const status = shop.session_expired ? 'Sesi habis' : shop.error_message ? 'Gagal diperbarui' : !shop.sync_enabled ? 'Sinkronisasi dijeda' : shop.stale ? 'Data tersimpan' : 'Tersinkron';
      button.innerHTML = `<span class="chat-shop-logo"></span><span class="chat-shop-name">${esc(shop.shop_name)}${shop.shop_id ? `<small>${esc(status)}</small>` : ''}</span><span>${shop.unread_count || ''}</span>`;
      window.renderShopLogo?.(button.firstElementChild, shop.shop_id);
      button.addEventListener('click', () => { state.shop = Number(shop.shop_id); choose(null); renderShops(); loadRows(); });
      $('chat-shops').append(button);
      if (String(shop.shop_id) === focusedShop) button.focus();
    }
    $('chat-selected-shop').textContent = state.shops.find(shop => Number(shop.shop_id) === state.shop)?.shop_name || 'Semua toko';
  }
  function renderRows() {
    $('chat-conversation-count').textContent = state.rows.length + (state.rows.length === 100 ? ' terbaru' : '');
    const list = $('chat-conversations');
    const focusedKey = list.contains(document.activeElement) ? document.activeElement.dataset.key : null;
    list.replaceChildren();
    if (!state.rows.length) { list.textContent = 'Belum ada percakapan untuk filter ini. Perbarui dari Shopee atau ubah filter.'; return; }
    for (const row of state.rows) {
      const button = document.createElement('button'); button.type = 'button'; button.className = 'chat-conversation-button'; button.dataset.conversationId = row.remote_conversation_id; button.dataset.key = key(row);
      button.setAttribute('aria-pressed', String(key(row) === selectedKey()));
      button.innerHTML = `<strong>${esc(row.buyer_name || 'Pembeli')}</strong><span class="chat-preview">${esc(row.latest_message_text || 'Pesan tanpa teks')}</span><small>${esc(row.shop_name)} · ${esc(label(row.status))}${Number(row.unread_count) ? ` · ${Number(row.unread_count)} belum dibaca` : ''}</small><small>${esc(date(row.latest_message_at))}</small>`;
      button.addEventListener('click', () => choose(row)); list.append(button);
      if (key(row) === focusedKey) button.focus();
    }
  }
  async function loadRows() {
    const version = ++state.listVersion;
    const params = new URLSearchParams({shop_id:state.shop, search:$('chat-search').value.trim(), status:$('chat-status').value, unread_only:$('chat-unread').checked ? '1' : ''});
    try { const result = await request('/conversations?' + params); if (version !== state.listVersion) return; state.rows = result.conversations || []; renderRows(); }
    catch (error) { if (version === state.listVersion) $('chat-conversations').textContent = error.message; }
  }
  async function loadMessages() {
    const row = state.selected; if (!row) return;
    if (state.detailRequest === state.version) return;
    const version = state.version, detailVersion = ++state.detailVersion;
    state.detailRequest = version;
    try {
      const result = await request('/messages?' + new URLSearchParams(target(row)));
      if (version !== state.version || detailVersion !== state.detailVersion) return;
      state.selected = {...row, ...result.conversation};
      const id = key(row);
      $('chat-detail-buyer').textContent = state.selected.buyer_name || 'Pembeli';
      $('chat-detail-meta').textContent = (state.selected.shop_name || '') + ' · ' + label(state.selected.status);
      const sync = result.sync;
      const shopError = state.shops.find(shop => Number(shop.shop_id) === Number(row.shop_id))?.error_message;
      $('chat-history-state').textContent = sync?.error_message || shopError || (sync?.requested_at ? 'Pembaruan masuk antrean. Menunggu sinkronisasi selesai.' : sync?.synced_at ? `Riwayat diperbarui ${date(sync.synced_at)}. Riwayat lama mungkin belum lengkap; lihat Shopee untuk pesan sebelumnya.` : 'Riwayat belum disinkronkan. Menunggu sinkronisasi selesai.');
      const box = $('chat-messages'), nearEnd = box.scrollHeight - box.scrollTop - box.clientHeight < 70;
      const messages = result.messages || [], signature = JSON.stringify(messages);
      if (box.dataset.signature !== id + signature) {
        box.dataset.signature = id + signature;
        box.innerHTML = messages.length ? messages.map(message => `<div class="chat-message ${message.direction === 'outgoing' ? 'chat-outgoing' : ''}"><div>${esc(message.content_text || '[' + (message.message_type || 'pesan') + ']')}</div><small>${esc(date(message.remote_created_at))}</small></div>`).join('') : '<p class="text-sm">Belum ada pesan tersimpan. Status sinkronisasi ditampilkan di atas.</p>';
        if (nearEnd || state.lastRendered !== id) box.scrollTop = box.scrollHeight;
        state.lastRendered = id;
      }
    } catch (error) { if (version === state.version && detailVersion === state.detailVersion) { $('chat-history-state').textContent = error.message; $('chat-messages').textContent = 'Riwayat belum dapat dimuat. Pilih Perbarui pesan untuk mencoba lagi.'; delete $('chat-messages').dataset.signature; } }
    finally { if (state.detailRequest === version) state.detailRequest = null; }
  }
  async function queueRefresh(row) {
    const result = await request('/refresh', target(row));
    if (key(row) === selectedKey()) $('chat-history-state').textContent = result.message;
    return result;
  }
  async function overview() {
    if (state.loading) return; state.loading = true;
    try {
      const result = await request('/overview'); state.shops = result.shops || [];
      for (const [id, prop] of [['unread','unread_count'],['conversations','conversation_count'],['active','active_count'],['expired','expired_shops']]) $('chat-total-' + id).textContent = Number(result.totals?.[prop] || 0).toLocaleString('id-ID');
      renderShops();
      const problems = state.shops.filter(shop => shop.error_message || shop.session_expired || !shop.sync_enabled);
      $('chat-session-warning').classList.toggle('hidden', !problems.length);
      $('chat-session-warning').textContent = problems.map(shop => `${shop.shop_name}: ${shop.error_message || (shop.session_expired ? 'perbarui cookie toko.' : 'jadwal chat dijeda. Perbarui manual tetap tersedia.')}`).join(' ');
      const times = state.shops.map(shop => shop.last_sync_at).filter(Boolean).sort();
      notice(times.length ? `Data tersimpan. Sinkronisasi daftar terbaru: ${date(times[times.length - 1])}. Waktu tiap toko dapat berbeda.` : 'Belum ada sinkronisasi chat yang berhasil. Pilih toko lalu perbarui dari Shopee.');
      await Promise.all([loadRows(), loadMessages()]);
    } catch (error) { notice(error.message, true); }
    finally { state.loading = false; }
  }
  $('chat-mobile-back').addEventListener('click', () => choose(null));
  $('chat-refresh-thread').addEventListener('click', async () => { const row = state.selected; if (!row) return; try { await queueRefresh(row); await loadMessages(); } catch (error) { if (key(row) === selectedKey()) $('chat-history-state').textContent = error.message; } });
  $('chat-refresh').addEventListener('click', async () => {
    if ($('chat-refresh').disabled) return; $('chat-refresh').disabled = true;
    try { for (const shop of state.shops.filter(shop => !state.shop || Number(shop.shop_id) === state.shop)) await request('/refresh', {shop_id:shop.shop_id}); notice('Pembaruan masuk antrean. Data berubah setelah worker selesai.'); }
    catch (error) { notice(error.message, true); } finally { $('chat-refresh').disabled = false; }
  });
  let searchTimer;
  $('chat-search').addEventListener('input', () => { clearTimeout(searchTimer); state.listVersion++; searchTimer = setTimeout(loadRows, 250); });
  $('chat-status').addEventListener('change', loadRows); $('chat-unread').addEventListener('change', loadRows);
  let returnFocus;
  function popup(open) {
    state.popup = open; shell.classList.toggle('is-popup', open); $('chat-popup-backdrop').classList.toggle('hidden', !open);
    $('chat-open-popup').setAttribute('aria-expanded', String(open)); document.body.classList.toggle('overflow-hidden', open);
    $('chat-floating-launcher').setAttribute('aria-expanded', String(open)); $('chat-floating-launcher').classList.toggle('hidden', open || Boolean(state.selected));
    if (open) { returnFocus = document.activeElement; shell.setAttribute('role','dialog'); shell.setAttribute('aria-modal','true'); $('chat-popup-close').focus(); }
    else { shell.removeAttribute('role'); shell.removeAttribute('aria-modal'); returnFocus?.focus(); }
  }
  $('chat-open-popup').addEventListener('click', () => popup(true)); $('chat-popup-close').addEventListener('click', () => popup(false)); $('chat-popup-backdrop').addEventListener('click', () => popup(false));
  $('chat-floating-launcher').addEventListener('click', () => popup(true));
  document.addEventListener('keydown', event => {
    if (!state.popup) return;
    if (event.key === 'Escape') { popup(false); return; }
    if (event.key !== 'Tab') return;
    const focusable = [...shell.querySelectorAll('button,input,select,textarea,a[href]')].filter(el => !el.disabled && el.getClientRects().length);
    const first = focusable[0], last = focusable[focusable.length - 1];
    if (event.shiftKey && (document.activeElement === first || !shell.contains(document.activeElement))) { event.preventDefault(); last?.focus(); }
    else if (!event.shiftKey && (document.activeElement === last || !shell.contains(document.activeElement))) { event.preventDefault(); first?.focus(); }
  });
  choose(null);
  if (state.shop > 0 && page.dataset.conversation) choose({shop_id:state.shop,remote_conversation_id:page.dataset.conversation});
  overview();
  setInterval(() => { if (!document.hidden) loadMessages(); }, 5000);
  setInterval(() => {
    if (document.hidden) return;
    if (state.selected) queueRefresh(state.selected).catch(error => notice(error.message, true));
    overview();
  }, 30000);
})();
