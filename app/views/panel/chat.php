<?php require __DIR__ . '/templates/shop-logos.php'; ?>
<div id="chat-page" data-api="<?= htmlspecialchars(burl . '/procChat', ENT_QUOTES); ?>" data-csrf="<?= htmlspecialchars(authCsrfToken(), ENT_QUOTES); ?>" data-shop="<?= (int)($data['active_shop_id'] ?? 0); ?>">
  <header class="mb-5 flex flex-wrap items-center justify-between gap-3">
    <h2 class="text-2xl font-bold">Live Chat</h2>
    <div class="flex flex-wrap gap-2">
      <button id="chat-open-popup" type="button" class="btn btn-sm min-h-11" aria-controls="chat-shell" aria-expanded="false">Buka popup chat</button>
      <button id="chat-refresh" type="button" class="btn btn-primary btn-sm min-h-11">Perbarui dari Shopee</button>
    </div>
  </header>
  <p id="chat-state" class="mb-4 text-sm" role="status" aria-live="polite">Memuat status live chat…</p>
  <div id="chat-session-warning" class="mb-4 hidden rounded-lg border border-warning p-3 text-sm" role="status"></div>
  <section class="mb-5 grid grid-cols-2 gap-3 sm:grid-cols-4" aria-label="Ringkasan live chat">
    <div class="rounded-xl border border-base-content/10 bg-base-100 p-4"><div class="text-xs">Belum dibaca</div><div id="chat-total-unread" class="mt-1 text-2xl font-bold">–</div></div>
    <div class="rounded-xl border border-base-content/10 bg-base-100 p-4"><div class="text-xs">Percakapan</div><div id="chat-total-conversations" class="mt-1 text-2xl font-bold">–</div></div>
    <div class="rounded-xl border border-base-content/10 bg-base-100 p-4"><div class="text-xs">Percakapan aktif</div><div id="chat-total-active" class="mt-1 text-2xl font-bold">–</div></div>
    <div class="rounded-xl border border-base-content/10 bg-base-100 p-4"><div class="text-xs">Sesi perlu diperbarui</div><div id="chat-total-expired" class="mt-1 text-2xl font-bold">–</div></div>
  </section>
  <div id="chat-popup-backdrop" class="chat-popup-backdrop hidden" aria-hidden="true"></div>
  <section id="chat-shell" class="chat-shell rounded-xl border border-base-content/10 bg-base-100" aria-label="Ruang kerja percakapan">
    <button id="chat-popup-close" type="button" class="chat-popup-close btn btn-sm min-h-11" aria-label="Tutup popup chat">Tutup popup</button>
    <aside class="chat-shop-pane" aria-label="Toko">
      <h3 class="p-3 text-sm font-bold">Toko</h3>
      <div id="chat-shops" class="chat-scroll p-2">Memuat toko…</div>
    </aside>
    <section class="chat-list-pane" aria-label="Daftar percakapan">
      <div class="border-b border-base-content/10 p-3">
        <div class="flex items-center justify-between gap-2"><h3 id="chat-selected-shop" class="text-sm font-bold">Semua toko</h3><span id="chat-conversation-count" class="text-xs">0</span></div>
        <label for="chat-search" class="mt-3 block text-xs">Cari pembeli atau pesan</label>
        <input id="chat-search" class="input mt-1 min-h-11 w-full" autocomplete="off">
        <div class="mt-2 flex flex-wrap items-center gap-2">
          <label class="sr-only" for="chat-status">Status percakapan</label>
          <select id="chat-status" class="select min-h-11 w-auto"><option value="">Semua status</option><option value="activated">Aktif</option><option value="closed">Ditutup</option></select>
          <label class="flex min-h-11 items-center gap-2 text-xs"><input id="chat-unread" type="checkbox" class="checkbox checkbox-sm">Belum dibaca</label>
        </div>
      </div>
      <div id="chat-conversations" class="chat-scroll p-2" aria-live="polite"></div>
    </section>
    <section class="chat-detail-pane" aria-label="Detail percakapan">
      <div id="chat-detail-empty" class="p-6 text-sm">Pilih percakapan untuk membaca riwayat dan membalas.</div>
      <div id="chat-detail" class="hidden">
        <header class="border-b border-base-content/10 p-3">
          <button id="chat-mobile-back" type="button" class="chat-mobile-back btn btn-ghost btn-sm min-h-11">Kembali ke daftar</button>
          <div id="chat-detail-buyer" class="font-bold"></div>
          <div id="chat-detail-meta" class="mt-1 text-xs"></div>
          <div class="mt-2 flex flex-wrap gap-2">
            <button id="chat-mark-read" type="button" class="btn btn-ghost btn-sm min-h-11">Tandai dibaca</button>
            <button id="chat-reopen" type="button" class="btn btn-sm min-h-11 hidden">Chat Lagi</button>
            <button id="chat-refresh-thread" type="button" class="btn btn-ghost btn-sm min-h-11">Perbarui pesan</button>
          </div>
          <p id="chat-history-state" class="mt-2 text-xs" role="status"></p>
        </header>
        <div id="chat-messages" class="chat-scroll bg-base-200/40 p-3" role="log" aria-label="Riwayat pesan"></div>
        <form id="chat-compose" class="border-t border-base-content/10 p-3">
          <label for="chat-message" class="mb-1 block text-xs">Balasan</label>
          <textarea id="chat-message" class="textarea min-h-24 w-full" maxlength="2000" required></textarea>
          <div class="mt-2 flex items-start justify-between gap-3">
            <span id="chat-compose-state" class="text-xs" role="status">Maksimal 2.000 karakter</span>
            <button id="chat-send" type="submit" class="btn btn-primary btn-sm min-h-11">Kirim</button>
          </div>
          <div id="chat-send-fallback" class="mt-2 hidden flex-wrap gap-2">
            <button id="chat-copy-reply" type="button" class="btn btn-sm min-h-11">Salin balasan</button>
            <a class="btn btn-sm min-h-11" href="https://seller.shopee.co.id/webchat/conversations" target="_blank" rel="noopener noreferrer">Buka Chat Shopee</a>
          </div>
        </form>
      </div>
    </section>
  </section>
  <button id="chat-floating-launcher" type="button" class="btn btn-primary fixed bottom-5 right-5 z-40 min-h-12" aria-controls="chat-shell" aria-expanded="false">Live Chat <span id="chat-floating-unread" class="badge badge-sm hidden">0</span></button>
</div>
<script src="<?= assets; ?>/js/chat.js?v=<?= filemtime(__DIR__ . '/../../../public/assets/js/chat.js'); ?>"></script>
