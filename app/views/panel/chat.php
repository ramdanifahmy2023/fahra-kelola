<?php require __DIR__ . '/templates/shop-logos.php'; ?>
<div id="chat-page" data-api="<?= htmlspecialchars(burl . '/procChat', ENT_QUOTES); ?>" data-csrf="<?= htmlspecialchars(authCsrfToken(), ENT_QUOTES); ?>" data-shop="<?= (int)($data['active_shop_id'] ?? 0); ?>" data-conversation="<?= htmlspecialchars($data['active_conversation_id'] ?? '', ENT_QUOTES); ?>">
  <header class="mb-5 flex flex-wrap items-center justify-between gap-3">
    <h2 class="text-2xl font-bold">Chat Shopee</h2>
    <div class="flex flex-wrap gap-2">
      <button id="chat-open-popup" type="button" class="btn btn-sm min-h-11" aria-controls="chat-shell" aria-expanded="false">Buka popup chat</button>
      <button id="chat-refresh" type="button" class="btn btn-primary btn-sm min-h-11">Perbarui dari Shopee</button>
    </div>
  </header>
  <p class="mb-3 text-sm">Baca riwayat di sini, balas melalui Shopee. Pesan pembeli baru masuk ke lonceng Shopdash setelah sinkronisasi berkala.</p>
  <p id="chat-state" class="mb-4 text-sm" role="status" aria-live="polite">Memuat status chat…</p>
  <div id="chat-session-warning" class="mb-4 hidden rounded-lg border border-warning p-3 text-sm" role="status"></div>
  <section class="page-recap mb-5" id="chat-recap" aria-labelledby="chat-recap-title" aria-busy="true">
    <header class="page-recap-heading">
      <div><h2 id="chat-recap-title">Ringkasan chat</h2><p>Pesan belum dibaca dan kondisi sinkronisasi di seluruh toko.</p></div>
      <p class="page-recap-scope" id="chat-recap-coverage">Memuat cakupan data…</p>
    </header>
    <div class="page-recap-summary">
      <div class="page-recap-primary"><span>Belum dibaca di Shopee</span><strong id="chat-total-unread">–</strong><p id="chat-recap-unread-context">Jumlah dari data chat tersimpan di tiap toko.</p></div>
      <div class="page-recap-metrics">
        <div class="page-recap-metric"><span>Percakapan</span><strong id="chat-total-conversations">–</strong><small>Jumlah yang tersimpan</small></div>
        <div class="page-recap-metric"><span>Percakapan aktif</span><strong id="chat-total-active">–</strong><small>Status aktif dari Shopee</small></div>
        <div class="page-recap-metric" data-tone="warning"><span>Sesi perlu diperbarui</span><strong id="chat-total-expired">–</strong><small>Toko dengan sesi berakhir</small></div>
        <div class="page-recap-metric"><span>Butuh perhatian</span><strong id="chat-total-issues">–</strong><small>Gagal, sesi berakhir, atau sinkronisasi dijeda</small></div>
      </div>
    </div>
    <div class="page-recap-visual-row">
      <div class="page-recap-breakdown"><h3>Kondisi data toko</h3><div id="chat-recap-bar" class="page-recap-bar" role="img" aria-label="Kondisi sinkronisasi toko"></div><ul id="chat-recap-legend" class="page-recap-legend"></ul></div>
      <div class="page-recap-shop-rank"><h3>Pesan belum dibaca per toko</h3><ol id="chat-recap-shops"></ol></div>
    </div>
    <p class="page-recap-insight"><span class="material-symbols-outlined" aria-hidden="true">info</span>Angka mengikuti data chat tersimpan. Balas pesan dan ubah status percakapan melalui Shopee.</p>
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
        <input id="chat-search" class="input mt-1 min-h-11 w-full" autocomplete="off" type="search" placeholder="Cari pembeli atau pesan" aria-describedby="chat-search-help">
        <p id="chat-search-help" class="sr-only">Hasil diperbarui saat Anda mengetik.</p>
        <div class="mt-2 flex flex-wrap items-center gap-2">
          <label class="sr-only" for="chat-status">Status percakapan</label>
          <label class="sr-only" for="chat-status">Status percakapan</label><select id="chat-status" class="select min-h-11 w-auto"><option value="">Semua status</option><option value="activated">Aktif</option><option value="closed">Ditutup</option></select>
          <label class="flex min-h-11 items-center gap-2 text-xs"><input id="chat-unread" type="checkbox" class="checkbox checkbox-sm">Belum dibaca di Shopee</label>
        </div>
      </div>
      <div id="chat-conversations" class="chat-scroll p-2" aria-live="polite" aria-busy="false"></div>
    </section>
    <section class="chat-detail-pane" aria-label="Detail percakapan">
      <div id="chat-detail-empty" class="p-6 text-sm">Pilih percakapan untuk membaca riwayat.</div>
      <div id="chat-detail" class="hidden">
        <header class="border-b border-base-content/10 p-3">
          <button id="chat-mobile-back" type="button" class="chat-mobile-back btn btn-ghost btn-sm min-h-11">Kembali ke daftar</button>
          <div id="chat-detail-buyer" class="font-bold"></div>
          <div id="chat-detail-meta" class="mt-1 text-xs"></div>
          <div class="mt-2 flex flex-wrap gap-2">
            <button id="chat-refresh-thread" type="button" class="btn btn-ghost btn-sm min-h-11">Perbarui pesan</button>
          </div>
          <p id="chat-history-state" class="mt-2 text-xs" role="status" aria-live="polite"></p>
        </header>
        <div id="chat-messages" class="chat-scroll bg-base-200/40 p-3" role="log" aria-label="Riwayat pesan"></div>
        <footer id="chat-readonly-footer" class="border-t border-base-content/10 p-3">
          <a id="chat-shopee-reply" class="btn btn-primary btn-sm min-h-11" href="https://seller.shopee.co.id/webchat/conversations" target="_blank" rel="noopener noreferrer">Balas di Shopee</a>
          <p class="mt-2 text-xs">Pilih toko dan pembeli yang sama di Shopee. Membaca di Shopdash tidak mengubah status chat Shopee.</p>
        </footer>
      </div>
    </section>
  </section>
  <button id="chat-floating-launcher" type="button" class="btn btn-primary fixed bottom-5 right-5 z-40 min-h-12" aria-controls="chat-shell" aria-expanded="false">Chat Shopee</button>
</div>
<script src="<?= assets; ?>/js/chat.js?v=<?= filemtime(__DIR__ . '/../../../public/assets/js/chat.js'); ?>"></script>
