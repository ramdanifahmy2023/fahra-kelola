<section id="automation-page" data-endpoint="<?= htmlspecialchars(burl . '/procAutomation', ENT_QUOTES, 'UTF-8'); ?>" data-csrf="<?= htmlspecialchars(authCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
  <header class="automation-heading">
    <div><h1>Automation Engine</h1><p>Atur tanggapan rating untuk setiap toko.</p></div>
    <div class="automation-shop"><label for="selectedShopDisplay">Toko</label><?php require __DIR__ . '/templates/shop-picker.php'; ?></div>
  </header>
  <div class="automation-notice automation-availability">
    <strong><span class="material-symbols-outlined" aria-hidden="true">pause_circle</span>Pengiriman belum aktif</strong>
    <details><summary>Tentang fitur</summary><p>Pengaturan dapat disimpan. Uji aturan hanya memeriksa target dan instruksi, tanpa membuat atau mengirim balasan ke Shopee.</p></details>
  </div>
  <div class="automation-tabs" role="tablist" aria-label="Automation Engine">
    <button type="button" id="automation-tab-settings" role="tab" aria-controls="automation-settings" aria-selected="true"><span class="material-symbols-outlined" aria-hidden="true">tune</span>Aturan toko</button>
    <button type="button" id="automation-tab-test" role="tab" aria-controls="automation-test" aria-selected="false" tabindex="-1"><span class="material-symbols-outlined" aria-hidden="true">science</span>Uji aturan</button>
    <button type="button" id="automation-tab-connections" role="tab" aria-controls="automation-connections" aria-selected="false" tabindex="-1"><span class="material-symbols-outlined" aria-hidden="true">link</span>Koneksi AI</button>
  </div>
  <noscript><p>Aktifkan JavaScript untuk mengelola pengaturan dan koneksi.</p></noscript>
  <div id="automation-settings" role="tabpanel" aria-labelledby="automation-tab-settings" tabindex="0">
  <?php if (!$data['active_shop_id']): ?>
    <div class="automation-section"><h2>Tambahkan toko untuk mulai</h2><p>Setiap toko memiliki target dan persona sendiri.</p><a class="btn" href="<?= burl; ?>/panel/shops">Kelola toko</a></div>
  <?php elseif ($data['automation_error']): ?>
    <div class="automation-section" role="alert"><h2>Pengaturan belum dapat dimuat</h2><p>Coba muat ulang halaman. Pengaturan tersimpan tidak diubah.</p><a class="btn" href="<?= burl; ?>/panel/automation?shop_id=<?= (int)$data['active_shop_id']; ?>">Muat ulang</a></div>
  <?php else: ?>
    <form id="automation-form" class="automation-editor">
      <div class="automation-layout">
        <div class="automation-column">
          <section class="automation-section" aria-labelledby="automation-target-title">
            <h2 id="automation-target-title" class="automation-section-heading"><span class="material-symbols-outlined" aria-hidden="true">star</span>Target rating</h2>
            <fieldset class="automation-stars"><legend>Bintang yang ditargetkan</legend>
              <?php foreach (range(1,5) as $star): ?><label><input type="checkbox" class="checkbox checkbox-sm checkbox-primary" name="target-star" value="<?= $star; ?>" aria-label="Targetkan rating <?= $star; ?> bintang"><span><?= $star; ?></span><span class="material-symbols-outlined" aria-hidden="true">star</span></label><?php endforeach; ?>
            </fieldset>
            <p class="automation-help">Hanya rating yang belum dibalas.</p>
            <label for="automation-scope">Cakupan waktu</label>
            <select id="automation-scope" class="select w-full"><option value="new">Rating baru sejak aktivasi</option><option value="date_range">Pilih rentang tanggal</option><option value="all">Semua rating yang belum dibalas</option></select>
            <div id="automation-dates" class="automation-fields" hidden>
              <div><label for="automation-start">Mulai tanggal</label><input id="automation-start" class="input w-full" type="date"></div>
              <div><label for="automation-end">Sampai tanggal <span class="automation-optional">(opsional)</span></label><input id="automation-end" class="input w-full" type="date"></div>
            </div>
            <p class="automation-help" id="automation-scope-help"></p>
          </section>
          <section class="automation-section" aria-labelledby="automation-rules-title">
            <h2 id="automation-rules-title" class="automation-section-heading"><span class="material-symbols-outlined" aria-hidden="true">tune</span>Aturan per bintang</h2>
            <?php foreach (range(1,5) as $star): ?>
            <details class="automation-rule">
              <summary><span class="automation-rating"><span class="material-symbols-outlined" aria-hidden="true">star</span><?= $star; ?><span class="sr-only"> bintang</span></span><span class="automation-rule-state"><span data-rule-summary="<?= $star; ?>"></span><small data-rule-target="<?= $star; ?>" hidden>Di luar target</small></span><span class="material-symbols-outlined automation-chevron" aria-hidden="true">expand_more</span></summary>
              <div class="automation-rule-body">
                <label for="automation-action-<?= $star; ?>">Tindakan untuk <?= $star; ?> bintang</label>
                <select id="automation-action-<?= $star; ?>" class="select w-full"><option value="ai">Balas dengan AI sesuai aturan</option><option value="review">Tinjau terlebih dahulu</option><option value="skip">Lewati rating ini</option></select>
                <label for="automation-rule-<?= $star; ?>">Instruksi <?= $star; ?> bintang</label><textarea id="automation-rule-<?= $star; ?>" class="textarea w-full" rows="3" maxlength="1500"></textarea>
              </div>
            </details>
            <?php endforeach; ?>
          </section>
        </div>
        <div class="automation-column">
          <section class="automation-section" aria-labelledby="automation-persona-title">
            <h2 id="automation-persona-title" class="automation-section-heading"><span class="material-symbols-outlined" aria-hidden="true">chat_bubble</span>Persona toko</h2>
            <p id="automation-persona-label" class="automation-persona-name"></p><p id="automation-persona-excerpt" class="automation-excerpt"></p>
            <details id="automation-persona-editor" class="automation-disclosure">
              <summary>Ubah persona<span class="material-symbols-outlined automation-chevron" aria-hidden="true">expand_more</span></summary>
              <label for="automation-persona-name">Nama persona <span class="automation-optional">(opsional)</span></label><input id="automation-persona-name" class="input w-full" maxlength="80" placeholder="Contoh: Tim layanan toko">
              <label for="automation-persona">Gaya dan peran</label><textarea id="automation-persona" class="textarea w-full" rows="3" maxlength="2000" required></textarea>
              <label for="automation-support">Kebijakan bantuan</label><textarea id="automation-support" class="textarea w-full" rows="3" maxlength="2000" aria-describedby="automation-support-help"></textarea>
              <p id="automation-support-help" class="automation-help">Janjikan hanya bantuan yang tersedia sesuai kebijakan toko.</p>
            </details>
          </section>
          <section class="automation-section" aria-labelledby="automation-provider-title">
            <h2 id="automation-provider-title" class="automation-section-heading"><span class="material-symbols-outlined" aria-hidden="true">link</span>Koneksi &amp; model</h2>
            <label for="automation-connection">Koneksi 9Router</label><select id="automation-connection" class="select w-full"><option value="">Tanpa koneksi</option></select>
            <p id="automation-connection-state" class="automation-help" aria-live="polite">Memuat koneksi…</p>
            <p id="automation-effective-model" class="automation-effective-model"></p>
            <a id="automation-manage-connections" class="automation-text-link" href="#ai-connections" data-open-tab="automation-connections">Kelola koneksi</a>
            <details id="automation-advanced" class="automation-disclosure">
              <summary>Opsi lanjutan<span id="automation-override-state" hidden>Model khusus</span><span class="material-symbols-outlined automation-chevron" aria-hidden="true">expand_more</span></summary>
              <label for="automation-model">Model khusus <span class="automation-optional">(opsional)</span></label><input id="automation-model" class="input w-full" maxlength="255" placeholder="Ikuti model default koneksi" aria-describedby="automation-model-help">
              <p id="automation-model-help" class="automation-help">Kosongkan untuk mengikuti model default koneksi.</p>
              <label for="automation-max-length">Batas internal balasan (karakter)</label><input id="automation-max-length" class="input w-full" type="number" min="50" max="1000" step="1" required aria-describedby="automation-length-help">
              <p id="automation-length-help" class="automation-help">Batas editorial toko. Batas Shopee masih perlu diverifikasi.</p>
            </details>
          </section>
        </div>
      </div>
      <p id="automation-error" class="automation-error" role="alert" tabindex="-1" hidden></p>
      <div class="automation-save"><div id="automation-save-status" role="status" aria-live="polite"></div><button class="btn btn-primary" type="submit" id="automation-save-button">Simpan pengaturan</button></div>
    </form>
  <?php endif; ?>
  </div>
  <div id="automation-test" role="tabpanel" aria-labelledby="automation-tab-test" tabindex="0" hidden>
  <?php if ($data['active_shop_id'] && !$data['automation_error']): ?>
    <div class="automation-test-layout">
      <section class="automation-section" aria-labelledby="automation-preview-title">
        <h2 id="automation-preview-title" class="automation-section-heading"><span class="material-symbols-outlined" aria-hidden="true">science</span>Uji aturan</h2>
        <p class="automation-help">Memeriksa aturan saja; tidak membuat atau mengirim balasan.</p><p id="automation-preview-draft" class="automation-help"></p>
        <form id="automation-preview-form">
          <div class="automation-fields"><div><label for="automation-sample-star">Bintang</label><select id="automation-sample-star" class="select w-full"><?php foreach (range(1,5) as $star): ?><option value="<?= $star; ?>"><?= $star; ?> bintang</option><?php endforeach; ?></select></div><div><label for="automation-sample-date">Tanggal ulasan</label><input id="automation-sample-date" class="input w-full" type="date" value="<?= date('Y-m-d'); ?>" required></div></div>
          <label for="automation-sample-text">Contoh ulasan <span class="automation-optional">(opsional)</span></label><textarea id="automation-sample-text" class="textarea w-full" rows="4" maxlength="4000" placeholder="Tulis contoh untuk memeriksa aturan"></textarea>
          <label class="automation-check"><input id="automation-sample-replied" type="checkbox" class="checkbox checkbox-sm">Sudah dibalas</label>
          <button class="btn btn-primary w-full" id="automation-preview-button" type="submit">Periksa aturan</button>
        </form>
        <p id="automation-preview-error" class="automation-error" role="alert" tabindex="-1" hidden></p>
      </section>
      <section class="automation-section automation-result-section" aria-label="Hasil pemeriksaan">
        <div id="automation-preview-empty" class="automation-empty"><span class="material-symbols-outlined" aria-hidden="true">rule</span><h2>Hasil pemeriksaan</h2><p>Isi contoh ulasan, lalu periksa aturan toko.</p></div>
        <div id="automation-preview-result" role="status" hidden>
          <dl class="automation-result-flow"><div><dt><span class="material-symbols-outlined" aria-hidden="true">star</span>Target</dt><dd id="automation-preview-target"></dd></div><div><dt><span class="material-symbols-outlined" aria-hidden="true">tune</span>Tindakan</dt><dd id="automation-preview-action"></dd></div><div><dt><span class="material-symbols-outlined" aria-hidden="true">info</span>Alasan</dt><dd id="automation-preview-reason"></dd></div></dl>
          <details id="automation-prompt-detail" class="automation-disclosure"><summary>Detail instruksi<span class="material-symbols-outlined automation-chevron" aria-hidden="true">expand_more</span></summary><pre id="automation-preview-prompt"></pre></details>
        </div>
      </section>
    </div>
  <?php else: ?>
    <div class="automation-section"><h2>Pengaturan toko diperlukan</h2><p>Pilih toko dan muat pengaturannya sebelum memeriksa aturan.</p><a class="automation-text-link" href="#automation-settings" data-open-tab="automation-settings">Buka aturan toko</a></div>
  <?php endif; ?>
  </div>
  <div id="automation-connections" role="tabpanel" aria-labelledby="automation-tab-connections" tabindex="0" hidden><?php require __DIR__ . '/templates/ai-connections.php'; ?></div>
  <?php if ($data['active_shop_id'] && !$data['automation_error']): ?><script id="automation-bootstrap" type="application/json"><?= json_encode(['shop_id'=>(int)$data['active_shop_id'],'profile'=>$data['automation_profile']], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?></script><?php endif; ?>
</section>
<script src="<?= assets; ?>/js/automation.js?v=<?= filemtime(__DIR__ . '/../../../public/assets/js/automation.js'); ?>" defer></script>
<script src="<?= assets; ?>/js/ai-connections.js?v=<?= filemtime(__DIR__ . '/../../../public/assets/js/ai-connections.js'); ?>" defer></script>
