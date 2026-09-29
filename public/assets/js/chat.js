(() => {
  const $ = id => document.getElementById(id);
  const page = $('chat-page'), api = page.dataset.api, shell = $('chat-shell');
  const state = {shop: Number(page.dataset.shop), shops: [], rows: [], selected: null, version: 0, listVersion: 0, detailVersion: 0, loading: false, popup: false};
  const drafts = new Map(), sending = new Set(), actions = new Set(), intents = new Map();
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
  function intentFor(id) {
    if (!intents.has(id)) {
      try { const saved = JSON.parse(sessionStorage.getItem('chat-intent:' + id)); if (saved) intents.set(id, saved); } catch (_) {}
    }
    return intents.get(id);
  }
  function saveIntent(id, intent) {
    if (intent) intents.set(id, intent); else intents.delete(id);
    try { if (intent) sessionStorage.setItem('chat-intent:' + id, JSON.stringify(intent)); else sessionStorage.removeItem('chat-intent:' + id); } catch (_) {}
  }
  function saveDraft() { if (state.selected) drafts.set(selectedKey(), $('chat-message').value); }
  function compose(message) {
    const row = state.selected, id = selectedKey(), intent = intentFor(id);
    const pending = sending.has(id) || (intent && intent.pending);
    const disabled = !row || state.detailLoading || row.status === 'closed' || Number(row.is_blocked) || pending;
    $('chat-message').disabled = Boolean(disabled); $('chat-send').disabled = Boolean(disabled);
    $('chat-mark-read').disabled = !row || row.status === 'closed' || actions.has(id);
    $('chat-reopen').classList.toggle('hidden', !row || row.status !== 'closed');
    $('chat-reopen').disabled = actions.has(id) || Boolean(Number(row?.is_blocked));
    const fallback = !pending && Boolean(state.sendError?.[id]);
    $('chat-send-fallback').classList.toggle('hidden', !fallback);
    $('chat-send-fallback').classList.toggle('flex', fallback);
    $('chat-compose-state').textContent = message || (pending ? 'Pengiriman belum terkonfirmasi. Perbarui pesan; jangan kirim ulang.' : Number(row?.is_blocked) ? 'Percakapan diblokir di Shopee.' : row?.status === 'closed' ? 'Percakapan ditutup. Pilih Chat Lagi untuk membalas.' : state.detailLoading ? 'Memuat percakapan…' : 'Enter untuk kirim, Shift+Enter untuk baris baru. Maksimal 2.000 karakter.');
  }
  function choose(row) {
    saveDraft(); state.selected = row; state.version++; state.detailVersion++;
    $('chat-detail').classList.toggle('hidden', !row); $('chat-detail-empty').classList.toggle('hidden', Boolean(row));
    shell.classList.toggle('chat-detail-open', Boolean(row));
    $('chat-floating-launcher').classList.toggle('hidden', Boolean(row) || state.popup);
    $('chat-message').value = row ? (drafts.get(key(row)) || '') : '';
    state.detailLoading = Boolean(row); compose();
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
      state.selected = {...row, ...result.conversation}; state.detailLoading = false;
      const id = key(row), intent = intentFor(id), delivery = result.outbox?.find(item => item.request_id === intent?.request_id);
      let feedback = '';
      if (delivery?.status === 'sent') { saveIntent(id, null); drafts.delete(id); $('chat-message').value = ''; feedback = 'Terkirim dan dikonfirmasi Shopee.'; }
      else if (delivery?.status === 'failed') { saveIntent(id, null); feedback = delivery.error_message || 'Pengiriman ditolak Shopee. Pesan dapat diperbaiki lalu dikirim lagi.'; }
      else if (delivery && intent) { intent.pending = true; saveIntent(id, intent); }
      const rejected = result.outbox?.[0];
      if (rejected?.status === 'failed') { state.sendError ||= {}; state.sendError[id] = rejected.error_message; }
      compose(feedback || state.sendError?.[id]);
      $('chat-detail-meta').textContent = (row.shop_name || '') + ' · ' + label(state.selected.status);
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
    } catch (error) { if (version === state.version && detailVersion === state.detailVersion) { state.detailLoading = true; compose(error.message); $('chat-history-state').textContent = error.message; } }
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
      $('chat-floating-unread').textContent = Number(result.totals?.unread_count || 0).toLocaleString('id-ID');
      $('chat-floating-unread').classList.toggle('hidden', !Number(result.totals?.unread_count));
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
  async function changeStatus(reopen) {
    const row = state.selected, id = key(row); if (!row || actions.has(id)) return;
    actions.add(id); compose(reopen ? 'Mengaktifkan percakapan…' : 'Memperbarui status…');
    let feedback;
    try { const result = await request('/mark_read', {...target(row), reopen:reopen ? '1' : ''}); feedback = result.message; if (id === selectedKey()) await loadMessages(); }
    catch (error) { feedback = error.message; }
    finally { actions.delete(id); if (id === selectedKey()) compose(feedback); }
  }
  $('chat-compose').addEventListener('submit', async event => {
    event.preventDefault(); const row = state.selected, id = key(row), text = $('chat-message').value.trim();
    if (!row || !text || $('chat-message').disabled || sending.has(id)) return;
    const intent = intentFor(id) || {request_id:crypto.randomUUID()}; intent.pending = true;
    saveIntent(id, intent); drafts.set(id, text); sending.add(id); compose('Mengirim dan menunggu konfirmasi Shopee…');
    let feedback;
    try {
      const result = await request('/send', {...target(row), message:text, request_id:intent.request_id});
      saveIntent(id, null); drafts.delete(id); if (state.sendError) delete state.sendError[id]; feedback = result.message;
      if (id === selectedKey()) { $('chat-message').value = ''; await loadMessages(); }
    } catch (error) {
      // A lost response can follow server acceptance. Keep the intent until reconciliation.
      if (error.result?.delivery_status === 'failed' || error.result?.attempted === false || [400,401,403,405,422].includes(error.httpStatus)) saveIntent(id, null);
      feedback = error.message;
      state.sendError ||= {}; state.sendError[id] = feedback;
      if (intentFor(id)?.pending) feedback += ' Status belum pasti. Perbarui pesan sebelum mengirim lagi.';
    } finally { sending.delete(id); if (id === selectedKey()) compose(feedback); }
  });
  $('chat-message').addEventListener('input', saveDraft);
  $('chat-copy-reply').addEventListener('click', async () => {
    const id = selectedKey(), text = $('chat-message').value;
    try {
      if (!text.trim()) { compose('Belum ada balasan untuk disalin.'); return; }
      await navigator.clipboard.writeText(text);
      if (id === selectedKey()) compose('Balasan disalin. Pilih pembeli dan toko yang sesuai di Chat Shopee.');
    } catch (_) { if (id === selectedKey()) compose('Salin teks balasan secara manual, lalu buka Chat Shopee.'); }
  });
  $('chat-message').addEventListener('keydown', event => { if (event.key === 'Enter' && !event.shiftKey && !event.isComposing) { event.preventDefault(); if (!$('chat-send').disabled) $('chat-compose').requestSubmit(); } });
  $('chat-mark-read').addEventListener('click', () => changeStatus(false)); $('chat-reopen').addEventListener('click', () => changeStatus(true));
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
  choose(null); overview();
  setInterval(() => { if (!document.hidden) loadMessages(); }, 5000);
  setInterval(() => {
    if (document.hidden) return;
    if (state.selected) queueRefresh(state.selected).catch(error => notice(error.message, true));
    overview();
  }, 30000);
})();
