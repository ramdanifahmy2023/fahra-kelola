<style>
  .chat-shell { min-height: min(720px, calc(100vh - 190px)); }
  .chat-scroll { scrollbar-width: thin; scrollbar-color: hsl(var(--bc) / .16) transparent; }
  .chat-focus:focus-visible { outline: 3px solid hsl(var(--p) / .55); outline-offset: 2px; }
  .chat-message { white-space: pre-wrap; overflow-wrap: anywhere; }
</style>

<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
  <div>
    <div class="mb-1 flex items-center gap-2 text-[10px] font-bold uppercase tracking-[0.16em] text-primary"><span class="material-symbols-outlined text-sm">chat</span>Customer conversations</div>
    <h2 class="text-2xl font-black tracking-tight text-base-content">Live Chat</h2>
    <p class="mt-1 max-w-2xl text-sm text-base-content/60">Pantau percakapan semua toko, baca riwayat, dan balas dari satu ruang kerja.</p>
  </div>
  <button id="chat-refresh" type="button" class="chat-focus btn btn-sm min-h-11 gap-2 rounded-lg border-base-content/10 bg-base-100"><span class="material-symbols-outlined text-base">refresh</span>Perbarui</button>
</div>

<div id="chat-session-warning" class="mb-5 hidden rounded-xl border border-warning/40 bg-warning/10 p-4 text-sm text-warning-content" role="alert" aria-live="polite"></div>
<div id="chat-state" class="mb-5 rounded-xl border border-base-content/10 bg-base-100 p-4 text-sm text-base-content/60" role="status" aria-live="polite">Memuat status live chat…</div>

<section class="mb-5 grid grid-cols-2 gap-3 sm:grid-cols-4" aria-label="Ringkasan live chat">
  <div class="rounded-xl border border-base-content/10 bg-base-100 p-4"><div class="text-[10px] font-bold uppercase tracking-wide text-base-content/45">Belum dibaca</div><div id="chat-total-unread" class="mt-1 text-2xl font-black">-</div></div>
  <div class="rounded-xl border border-base-content/10 bg-base-100 p-4"><div class="text-[10px] font-bold uppercase tracking-wide text-base-content/45">Percakapan</div><div id="chat-total-conversations" class="mt-1 text-2xl font-black">-</div></div>
  <div class="rounded-xl border border-base-content/10 bg-base-100 p-4"><div class="text-[10px] font-bold uppercase tracking-wide text-base-content/45">Toko aktif</div><div id="chat-total-active" class="mt-1 text-2xl font-black">-</div></div>
  <div class="rounded-xl border border-base-content/10 bg-base-100 p-4"><div class="text-[10px] font-bold uppercase tracking-wide text-base-content/45">Sesi perlu diperbarui</div><div id="chat-total-expired" class="mt-1 text-2xl font-black">-</div></div>
</section>

<section class="chat-shell grid grid-cols-1 overflow-hidden rounded-2xl border border-base-content/10 bg-base-100 shadow-sm lg:grid-cols-[220px_minmax(280px,380px)_minmax(0,1fr)]" aria-label="Ruang kerja percakapan">
  <aside class="border-b border-base-content/10 bg-base-200/45 lg:border-b-0 lg:border-r" aria-label="Toko">
    <div class="border-b border-base-content/10 p-4"><div class="text-[10px] font-bold uppercase tracking-[0.16em] text-base-content/45">Toko</div><div class="mt-1 text-xs text-base-content/60">Pilih sumber percakapan</div></div>
    <div id="chat-shops" class="chat-scroll max-h-64 space-y-1 overflow-y-auto p-2 lg:max-h-[calc(100vh-300px)]">
      <div class="p-3 text-xs text-base-content/50">Memuat toko…</div>
    </div>
  </aside>

  <section class="flex min-h-0 flex-col border-b border-base-content/10 lg:border-b-0 lg:border-r" aria-label="Daftar percakapan">
    <div class="border-b border-base-content/10 p-4">
      <div class="flex items-start justify-between gap-3"><div><h3 class="text-sm font-black">Percakapan</h3><div id="chat-selected-shop" class="mt-1 text-xs text-base-content/50">Pilih toko</div></div><span id="chat-conversation-count" class="badge badge-ghost badge-sm">0</span></div>
      <label class="mt-3 flex h-10 items-center gap-2 rounded-lg border border-base-content/10 bg-base-200/40 px-3"><span class="material-symbols-outlined text-base text-base-content/45">search</span><span class="sr-only">Cari percakapan</span><input id="chat-search" class="min-w-0 flex-1 bg-transparent text-xs outline-none placeholder:text-base-content/40" placeholder="Cari pembeli atau isi pesan" autocomplete="off"></label>
      <div class="mt-2 flex flex-wrap gap-2"><select id="chat-status" class="chat-focus select select-bordered select-xs w-auto"><option value="">Semua status</option><option value="activated">Aktif</option><option value="closed">Ditutup</option></select><label class="flex min-h-8 items-center gap-2 text-xs text-base-content/60"><input id="chat-unread" type="checkbox" class="checkbox checkbox-xs checkbox-primary">Belum dibaca</label></div>
    </div>
    <div id="chat-conversations" class="chat-scroll min-h-[260px] flex-1 overflow-y-auto p-2" aria-live="polite"><div class="p-4 text-xs text-base-content/50">Pilih toko untuk memuat percakapan.</div></div>
  </section>

  <section class="flex min-h-0 flex-col" aria-label="Detail percakapan">
    <div id="chat-detail-empty" class="flex min-h-[360px] flex-1 flex-col items-center justify-center p-8 text-center text-base-content/50"><span class="material-symbols-outlined text-5xl text-base-content/20">forum</span><h3 class="mt-4 text-sm font-black text-base-content/70">Pilih percakapan</h3><p class="mt-1 max-w-xs text-xs leading-relaxed">Riwayat pesan dan ruang balasan akan tampil di sini.</p></div>
    <div id="chat-detail" class="hidden min-h-0 flex-1 flex-col">
      <header class="flex flex-wrap items-center justify-between gap-3 border-b border-base-content/10 p-4"><div class="min-w-0"><div id="chat-detail-buyer" class="truncate text-sm font-black"></div><div id="chat-detail-meta" class="mt-1 text-xs text-base-content/50"></div></div><button id="chat-mark-read" type="button" class="chat-focus btn btn-ghost btn-xs min-h-9 gap-1.5"><span class="material-symbols-outlined text-sm">done_all</span>Tandai dibaca</button></header>
      <div id="chat-messages" class="chat-scroll min-h-[250px] flex-1 space-y-3 overflow-y-auto bg-base-200/25 p-4" aria-live="polite"><div class="text-xs text-base-content/50">Memuat pesan…</div></div>
      <form id="chat-compose" class="border-t border-base-content/10 p-3"><label for="chat-message" class="sr-only">Tulis balasan</label><textarea id="chat-message" class="chat-focus textarea textarea-bordered min-h-24 w-full resize-y text-sm" maxlength="2000" placeholder="Tulis balasan…" required></textarea><div class="mt-2 flex items-center justify-between gap-3"><span id="chat-compose-state" class="text-xs text-base-content/50" aria-live="polite">Maksimal 2.000 karakter</span><button id="chat-send" type="submit" class="chat-focus btn btn-primary btn-sm min-h-10 gap-2"><span class="material-symbols-outlined text-base">send</span>Kirim</button></div></form>
    </div>
  </section>
</section>

<script>
(() => {
  const api = '<?= burl; ?>/procChat';
  const initialShopId = <?= (int)($data['active_shop_id'] ?? 0); ?>;
  const state = { selectedShopId: initialShopId || 0, selectedConversationId: '', selectedConversationShopId: 0, shops: [], conversations: [], loading: false };
  const $ = id => document.getElementById(id);
  const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
  const number = value => new Intl.NumberFormat('id-ID').format(Number(value || 0));
  const date = value => value ? new Date(String(value).replace(' ', 'T') + (String(value).includes('Z') || String(value).includes('+') ? '' : 'Z')).toLocaleString('id-ID', {dateStyle:'short', timeStyle:'short'}) : '-';
  const request = async (url, options = {}) => {
    const response = await fetch(url, Object.assign({headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}, cache:'no-store'}, options));
    const payload = await response.json().catch(() => ({}));
    if (!response.ok || payload.status !== 'success') throw new Error(payload.message || 'Permintaan live chat gagal.');
    return payload;
  };
  const statusLabel = status => status === 'activated' ? 'Aktif' : status === 'closed' ? 'Ditutup' : status || 'Menunggu';
  const setState = (message, error = false) => { $('chat-state').textContent = message; $('chat-state').className = 'mb-5 rounded-xl border p-4 text-sm ' + (error ? 'border-error/20 bg-error/5 text-error' : 'border-base-content/10 bg-base-100 text-base-content/60'); };
  const selectedShop = () => state.shops.find(shop => Number(shop.shop_id) === Number(state.selectedShopId));

  function renderSummary(payload) {
    state.shops = payload.shops || [];
    const totals = payload.totals || {};
    $('chat-total-unread').textContent = number(totals.unread_count);
    $('chat-total-conversations').textContent = number(totals.conversation_count);
    $('chat-total-active').textContent = number(totals.active_count);
    $('chat-total-expired').textContent = number(totals.expired_shops);
    const expired = state.shops.filter(shop => shop.session_expired);
    const warning = $('chat-session-warning');
    if (expired.length) { warning.className = 'mb-5 rounded-xl border border-warning/40 bg-warning/10 p-4 text-sm text-warning-content'; warning.innerHTML = '<div class="flex flex-wrap items-start gap-3"><span class="material-symbols-outlined text-xl">warning</span><div><strong>Sesi toko perlu diperbarui</strong><div class="mt-1">' + expired.map(shop => esc(shop.shop_name || ('Toko #' + shop.shop_id))).join(', ') + ' memerlukan cookie Shopee terbaru.</div><a class="mt-2 inline-flex min-h-11 items-center font-bold underline underline-offset-2" href="<?= burl; ?>/panel/shops">Perbarui cookie toko</a></div></div>'; }
    else { warning.className = 'mb-5 hidden rounded-xl border border-warning/40 bg-warning/10 p-4 text-sm text-warning-content'; warning.textContent = ''; }
    const shops = $('chat-shops');
    if (!state.shops.length) { shops.innerHTML = '<div class="p-3 text-xs text-base-content/50">Belum ada toko terhubung.</div>'; return; }
    shops.innerHTML = '';
    const allButton = document.createElement('button'); allButton.type = 'button'; allButton.className = 'chat-focus flex min-h-12 w-full items-center gap-3 rounded-lg px-3 py-2 text-left transition-colors ' + (Number(state.selectedShopId) === 0 ? 'bg-primary text-primary-content' : 'hover:bg-base-content/5');
    allButton.innerHTML = '<span class="grid h-8 w-8 shrink-0 place-items-center rounded-md ' + (Number(state.selectedShopId) === 0 ? 'bg-primary-content/15' : 'bg-base-content/10') + '"><span class="material-symbols-outlined text-base">all_inbox</span></span><span class="min-w-0 flex-1"><span class="block truncate text-xs font-bold">Semua toko</span><span class="mt-0.5 block truncate text-[10px] opacity-65">Gabungan percakapan</span></span><span class="shrink-0 text-xs font-black">' + (totals.unread_count ? number(totals.unread_count) : '') + '</span>';
    allButton.addEventListener('click', () => { state.selectedShopId = 0; state.selectedConversationId = ''; state.selectedConversationShopId = 0; renderSummary({shops:state.shops,totals}); loadConversations(); showEmptyDetail(); });
    shops.appendChild(allButton);
    state.shops.forEach(shop => {
      const button = document.createElement('button'); button.type = 'button'; button.className = 'chat-focus flex min-h-12 w-full items-center gap-3 rounded-lg px-3 py-2 text-left transition-colors ' + (Number(shop.shop_id) === Number(state.selectedShopId) ? 'bg-primary text-primary-content' : 'hover:bg-base-content/5'); button.dataset.shopId = shop.shop_id;
      const status = shop.session_expired ? 'Sesi habis' : shop.status === 'ok' ? 'Terhubung' : shop.status === 'error' ? 'Gagal' : 'Menunggu';
      button.innerHTML = '<span class="grid h-8 w-8 shrink-0 place-items-center rounded-md ' + (Number(shop.shop_id) === Number(state.selectedShopId) ? 'bg-primary-content/15' : 'bg-base-content/10') + '"><span class="material-symbols-outlined text-base">storefront</span></span><span class="min-w-0 flex-1"><span class="block truncate text-xs font-bold">' + esc(shop.shop_name || ('Toko #' + shop.shop_id)) + '</span><span class="mt-0.5 block truncate text-[10px] opacity-65">' + esc(status) + '</span></span><span class="shrink-0 text-xs font-black">' + (shop.unread_count ? number(shop.unread_count) : '') + '</span>';
      button.addEventListener('click', () => { state.selectedShopId = Number(shop.shop_id); state.selectedConversationId = ''; state.selectedConversationShopId = 0; renderSummary({shops:state.shops,totals}); loadConversations(); showEmptyDetail(); }); shops.appendChild(button);
    });
    const shop = selectedShop(); $('chat-selected-shop').textContent = shop ? (shop.shop_name || 'Toko terpilih') : 'Semua toko';
  }

  function renderConversations(rows) {
    state.conversations = rows || [];
    $('chat-conversation-count').textContent = number(state.conversations.length);
    const target = $('chat-conversations');
    if (!state.conversations.length) { target.innerHTML = '<div class="p-4 text-xs leading-relaxed text-base-content/50">Belum ada percakapan untuk filter ini. Coba ubah filter atau perbarui data.</div>'; return; }
    target.innerHTML = '';
    state.conversations.forEach(conversation => {
      const button = document.createElement('button'); button.type = 'button'; button.className = 'chat-focus flex min-h-[76px] w-full items-start gap-3 rounded-xl p-3 text-left transition-colors ' + (String(conversation.remote_conversation_id) === String(state.selectedConversationId) ? 'bg-primary/10 ring-1 ring-primary/30' : 'hover:bg-base-content/5');
      const unread = Number(conversation.unread_count || 0);
      button.innerHTML = '<span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-base-200 text-base-content/55"><span class="material-symbols-outlined text-lg">person</span></span><span class="min-w-0 flex-1"><span class="flex items-center justify-between gap-2"><span class="truncate text-xs font-bold">' + esc(conversation.buyer_name || ('Pembeli #' + conversation.buyer_id)) + '</span><span class="shrink-0 text-[10px] text-base-content/45">' + esc(date(conversation.latest_message_at)) + '</span></span><span class="mt-1 block truncate text-[11px] text-base-content/55">' + esc(conversation.latest_message_text || 'Belum ada preview pesan') + '</span><span class="mt-2 flex items-center gap-2 text-[10px] text-base-content/45"><span class="badge badge-ghost badge-xs">' + esc(statusLabel(conversation.status)) + '</span>' + (Number(state.selectedShopId) === 0 && conversation.shop_name ? '<span class="truncate">' + esc(conversation.shop_name) + '</span>' : '') + (unread ? '<span class="badge badge-primary badge-xs">' + number(unread) + ' baru</span>' : '') + '</span></span>';
      button.addEventListener('click', () => { state.selectedConversationId = String(conversation.remote_conversation_id); state.selectedConversationShopId = Number(conversation.shop_id); renderConversations(state.conversations); loadMessages(); }); target.appendChild(button);
    });
  }

  function showEmptyDetail() { $('chat-detail').classList.add('hidden'); $('chat-detail').classList.remove('flex'); $('chat-detail-empty').classList.remove('hidden'); }
  function showDetail() { $('chat-detail-empty').classList.add('hidden'); $('chat-detail').classList.remove('hidden'); $('chat-detail').classList.add('flex'); }

  function renderMessages(payload) {
    showDetail();
    const conversation = payload.conversation || {};
    $('chat-detail-buyer').textContent = conversation.buyer_name || ('Pembeli #' + (conversation.buyer_id || ''));
    $('chat-detail-meta').textContent = statusLabel(conversation.status) + ' · Pesan terakhir ' + date(conversation.latest_message_at);
    const target = $('chat-messages'); const messages = payload.messages || [];
    if (!messages.length) { target.innerHTML = '<div class="rounded-lg bg-base-100 p-4 text-xs text-base-content/50">Belum ada riwayat pesan pada percakapan ini.</div>'; return; }
    target.innerHTML = messages.map(message => {
      const outgoing = message.direction === 'outgoing';
      return '<div class="flex ' + (outgoing ? 'justify-end' : 'justify-start') + '"><div class="max-w-[88%] rounded-2xl px-3 py-2 ' + (outgoing ? 'rounded-br-sm bg-primary text-primary-content' : 'rounded-bl-sm bg-base-100 ring-1 ring-base-content/10') + '"><div class="chat-message text-sm">' + esc(message.content_text || '[' + (message.message_type || 'pesan') + ']') + '</div><div class="mt-1 text-[10px] opacity-60">' + esc(date(message.remote_created_at)) + '</div></div></div>';
    }).join('');
    target.scrollTop = target.scrollHeight;
  }

  async function loadOverview() {
    if (state.loading) return; state.loading = true;
    try { const payload = await request(api + '/overview'); renderSummary(payload); await loadConversations(true); setState('Data live chat diperbarui ' + new Date().toLocaleTimeString('id-ID')); }
    catch (error) { setState(error.message, true); }
    finally { state.loading = false; }
  }
  async function loadConversations(silent = false) {
    if (!silent) $('chat-conversations').innerHTML = '<div class="p-4 text-xs text-base-content/50">Memuat percakapan…</div>';
    const params = new URLSearchParams({shop_id: state.selectedShopId, search: $('chat-search').value.trim(), status: $('chat-status').value}); if ($('chat-unread').checked) params.set('unread_only', '1');
    try { const payload = await request(api + '/conversations?' + params.toString()); renderConversations(payload.conversations || []); const shop = selectedShop(); $('chat-selected-shop').textContent = shop ? shop.shop_name : 'Semua toko'; }
    catch (error) { $('chat-conversations').innerHTML = '<div class="p-4 text-xs text-error">' + esc(error.message) + '</div>'; }
  }
  async function loadMessages() {
    const conversationShopId = state.selectedConversationShopId || state.selectedShopId;
    if (!conversationShopId || !state.selectedConversationId) return;
    $('chat-messages').innerHTML = '<div class="text-xs text-base-content/50">Memuat pesan…</div>';
    try { const payload = await request(api + '/messages?shop_id=' + encodeURIComponent(conversationShopId) + '&conversation_id=' + encodeURIComponent(state.selectedConversationId)); renderMessages(payload); await markRead(true); }
    catch (error) { showDetail(); $('chat-messages').innerHTML = '<div class="rounded-lg border border-error/20 bg-error/5 p-4 text-xs text-error">' + esc(error.message) + '</div>'; }
  }
  async function markRead(silent = false) {
    const conversationShopId = state.selectedConversationShopId || state.selectedShopId;
    if (!conversationShopId || !state.selectedConversationId) return;
    const body = new URLSearchParams({shop_id: conversationShopId, conversation_id: state.selectedConversationId});
    try { await request(api + '/mark_read', {method:'POST', headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json','Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'}, body}); if (!silent) { await loadOverview(); } }
    catch (error) { if (!silent) $('chat-compose-state').textContent = error.message; }
  }
  $('chat-refresh').addEventListener('click', loadOverview);
  $('chat-search').addEventListener('input', () => { window.clearTimeout(window.chatSearchTimer); window.chatSearchTimer = window.setTimeout(() => loadConversations(), 250); });
  $('chat-status').addEventListener('change', () => loadConversations()); $('chat-unread').addEventListener('change', () => loadConversations());
  $('chat-mark-read').addEventListener('click', () => markRead(false));
  $('chat-compose').addEventListener('submit', async event => { event.preventDefault(); const message = $('chat-message').value.trim(); const conversationShopId = state.selectedConversationShopId || state.selectedShopId; if (!message || !conversationShopId || !state.selectedConversationId) return; const send = $('chat-send'); send.disabled = true; $('chat-compose-state').textContent = 'Mengirim pesan…'; const body = new URLSearchParams({shop_id: conversationShopId, conversation_id: state.selectedConversationId, message}); try { await request(api + '/send', {method:'POST', headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json','Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'}, body}); $('chat-message').value = ''; $('chat-compose-state').textContent = 'Pesan terkirim.'; await loadMessages(); await loadOverview(); } catch (error) { $('chat-compose-state').textContent = error.message; } finally { send.disabled = false; } });
  showEmptyDetail(); loadOverview(); window.setInterval(loadOverview, 30000);
})();
</script>
