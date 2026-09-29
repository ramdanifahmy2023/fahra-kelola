<section id="automation-page" data-endpoint="<?= htmlspecialchars(burl . '/procAutomation', ENT_QUOTES, 'UTF-8'); ?>" data-csrf="<?= htmlspecialchars(authCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
  <header class="automation-heading">
    <div><h1>Automation Engine</h1><p>Atur cara setiap toko menanggapi rating pembeli.</p></div>
    <div class="automation-shop"><label for="selectedShopDisplay">Konfigurasi toko</label><?php require __DIR__ . '/templates/shop-picker.php'; ?></div>
  </header>
  <div class="automation-notice"><strong>Pengiriman belum aktif</strong><p>Simpan target, persona, dan aturan toko terlebih dahulu. Pratinjau hanya memeriksa aturan; belum memanggil AI atau mengirim balasan ke Shopee.</p></div>
  <?php if (!$data['active_shop_id']): ?>
    <div class="automation-section"><h2>Tambahkan toko untuk mulai</h2><p>Setiap toko akan memiliki target dan persona sendiri.</p><a class="btn min-h-11" href="<?= burl; ?>/panel/shops">Kelola toko</a></div>
  <?php elseif ($data['automation_error']): ?>
    <div class="automation-section" role="alert"><h2>Konfigurasi belum dapat dimuat</h2><p>Coba muat ulang halaman. Pengaturan tersimpan tidak diubah.</p><a class="btn min-h-11" href="<?= burl; ?>/panel/automation?shop_id=<?= (int)$data['active_shop_id']; ?>">Muat ulang</a></div>
  <?php else: ?>
  <noscript><p>Aktifkan JavaScript untuk mengubah konfigurasi dan memeriksa aturan.</p></noscript>
  <div class="automation-layout">
    <form id="automation-form" class="automation-editor">
      <section class="automation-section" aria-labelledby="automation-target-title">
        <div class="automation-section-heading"><h2 id="automation-target-title">Target rating</h2><span>01</span></div>
        <p>Hanya rating yang belum memiliki balasan yang dapat diproses.</p>
        <label for="automation-scope">Cakupan waktu</label>
        <select id="automation-scope" class="select w-full"><option value="new">Rating baru sejak automasi diaktifkan</option><option value="date_range">Pilih rentang tanggal</option><option value="all">Semua rating yang belum dibalas</option></select>
        <div id="automation-dates" class="automation-fields" hidden>
          <div><label for="automation-start">Mulai tanggal</label><input id="automation-start" class="input w-full" type="date"></div>
          <div><label for="automation-end">Sampai tanggal <span class="automation-optional">(opsional)</span></label><input id="automation-end" class="input w-full" type="date"></div>
        </div>
        <p class="automation-help" id="automation-scope-help"></p>
        <fieldset class="automation-stars"><legend>Bintang yang ditargetkan</legend>
          <?php foreach (range(1,5) as $star): ?><label><input type="checkbox" class="checkbox checkbox-sm checkbox-primary" name="target-star" value="<?= $star; ?>"> <?= $star; ?> bintang</label><?php endforeach; ?>
        </fieldset>
      </section>
      <section class="automation-section" aria-labelledby="automation-persona-title">
        <div class="automation-section-heading"><h2 id="automation-persona-title">Persona toko</h2><span>02</span></div>
        <p>Gaya bicara dan kebijakan bantuan khusus toko ini.</p>
        <label for="automation-persona-name">Nama persona <span class="automation-optional">(opsional)</span></label>
        <input id="automation-persona-name" class="input w-full" maxlength="80" placeholder="Contoh: Tim layanan toko">
        <label for="automation-persona">Gaya dan peran</label>
        <textarea id="automation-persona" class="textarea w-full" rows="3" maxlength="2000" required></textarea>
        <label for="automation-support">Kebijakan bantuan</label>
        <textarea id="automation-support" class="textarea w-full" rows="3" maxlength="2000" aria-describedby="automation-support-help"></textarea>
        <p id="automation-support-help" class="automation-help">Tulis bantuan yang memang tersedia. Jangan menjanjikan refund atau penggantian di luar kebijakan toko.</p>
        <label for="automation-max-length">Batas internal balasan (karakter)</label>
        <input id="automation-max-length" class="input w-full" type="number" min="50" max="1000" step="1" required aria-describedby="automation-length-help">
        <p id="automation-length-help" class="automation-help">Batas editorial toko; batas karakter dari Shopee masih perlu diverifikasi.</p>
      </section>
      <section class="automation-section" aria-labelledby="automation-rules-title">
        <div class="automation-section-heading"><h2 id="automation-rules-title">Aturan per bintang</h2><span>03</span></div>
        <p>Tentukan tindakan dan instruksi tersendiri, termasuk untuk rating 1–3.</p>
        <?php foreach (range(1,5) as $star): ?>
        <details class="automation-rule" <?= $star === 1 ? 'open' : ''; ?>>
          <summary><span><?= $star; ?> bintang</span><span data-rule-summary="<?= $star; ?>"></span></summary>
          <div class="automation-rule-body">
            <label for="automation-action-<?= $star; ?>">Tindakan untuk <?= $star; ?> bintang</label>
            <select id="automation-action-<?= $star; ?>" class="select w-full"><option value="ai">Balas dengan AI sesuai aturan</option><option value="review">Tinjau terlebih dahulu</option><option value="skip">Lewati rating ini</option></select>
            <label for="automation-rule-<?= $star; ?>">Instruksi <?= $star; ?> bintang</label>
            <textarea id="automation-rule-<?= $star; ?>" class="textarea w-full" rows="3" maxlength="1500"></textarea>
          </div>
        </details>
        <?php endforeach; ?>
      </section>
      <div class="automation-save"><div id="automation-save-status" role="status" aria-live="polite"></div><button class="btn btn-primary min-h-11" type="submit" id="automation-save-button">Simpan konfigurasi</button></div>
      <p id="automation-error" class="automation-error" role="alert" tabindex="-1" hidden></p>
    </form>
    <aside class="automation-aside">
      <section class="automation-section" aria-labelledby="automation-provider-title">
        <h2 id="automation-provider-title">Provider AI</h2><p class="automation-provider-name">9Router <span>OpenAI-compatible</span></p>
        <p><?= $data['automation_provider']['configured'] ? 'Konfigurasi server tersedia. Koneksi belum diuji.' : 'Koneksi belum dikonfigurasi di server.'; ?></p>
        <label for="automation-model">Model toko <span class="automation-optional">(opsional)</span></label><input id="automation-model" form="automation-form" class="input w-full" maxlength="120" placeholder="Gunakan model default server">
        <p class="automation-help">Nama model disimpan untuk integrasi berikutnya. Credential tetap di server.</p>
      </section>
      <section class="automation-section" aria-labelledby="automation-preview-title">
        <h2 id="automation-preview-title">Periksa aturan</h2><p>Coba contoh ulasan dengan pengaturan di formulir, termasuk perubahan yang belum disimpan.</p>
        <form id="automation-preview-form">
          <div class="automation-fields"><div><label for="automation-sample-star">Bintang contoh</label><select id="automation-sample-star" class="select w-full"><?php foreach (range(1,5) as $star): ?><option value="<?= $star; ?>"><?= $star; ?> bintang</option><?php endforeach; ?></select></div>
          <div><label for="automation-sample-date">Tanggal ulasan</label><input id="automation-sample-date" class="input w-full" type="date" value="<?= date('Y-m-d'); ?>" required></div></div>
          <label for="automation-sample-text">Contoh ulasan <span class="automation-optional">(opsional)</span></label><textarea id="automation-sample-text" class="textarea w-full" rows="3" maxlength="4000" placeholder="Tulis contoh untuk memeriksa aturan"></textarea>
          <label class="automation-check"><input id="automation-sample-replied" type="checkbox" class="checkbox checkbox-sm"> Sudah dibalas</label>
          <button class="btn min-h-11 w-full" id="automation-preview-button" type="submit">Periksa aturan</button>
        </form>
        <div id="automation-preview-result" class="automation-preview-result" role="status" hidden><strong id="automation-preview-action"></strong><p id="automation-preview-reason"></p><p class="automation-help">Pemeriksaan lokal. Tidak ada panggilan AI atau pengiriman ke Shopee.</p><details id="automation-prompt-detail"><summary>Instruksi yang disusun</summary><pre id="automation-preview-prompt"></pre></details></div>
        <p id="automation-preview-error" class="automation-error" role="alert" hidden></p>
      </section>
    </aside>
  </div>
  <script id="automation-bootstrap" type="application/json"><?= json_encode(['shop_id'=>(int)$data['active_shop_id'],'profile'=>$data['automation_profile']], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?></script>
  <script src="<?= assets; ?>/js/automation.js?v=<?= filemtime(__DIR__ . '/../../../public/assets/js/automation.js'); ?>" defer></script>
  <?php endif; ?>
</section>
