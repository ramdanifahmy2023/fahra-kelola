<?php
require_once __DIR__ . '/../../../helpers/AiConnectionSupport.php';
$aiKeyError = '';
try { new AiConnectionSecret(); } catch (AiConnectionError $error) { $aiKeyError = $error->getMessage(); }
?>
<section id="ai-connections" class="ai-connections" aria-labelledby="ai-title" data-endpoint="<?= htmlspecialchars(burl . '/procAiConnections', ENT_QUOTES, 'UTF-8'); ?>" data-ready="<?= $aiKeyError === '' ? '1' : '0'; ?>">
  <header class="ai-heading"><div><h2 id="ai-title" class="automation-section-heading"><span class="material-symbols-outlined" aria-hidden="true">link</span>Koneksi 9Router</h2><p>Koneksi bersama untuk semua toko.</p></div><button type="button" class="btn btn-primary" id="ai-add"><span class="material-symbols-outlined" aria-hidden="true">add</span>Tambah koneksi</button></header>
  <?php if ($aiKeyError): ?><p class="automation-error" role="alert"><?= htmlspecialchars($aiKeyError, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
  <?php if ($data['automation_provider']['configured']): ?><p class="automation-help">Konfigurasi lama tersedia di server. Buat koneksi di sini untuk menggunakannya pada toko; konfigurasi lama tidak dipilih otomatis.</p><?php endif; ?>
  <div class="ai-toolbar"><p id="ai-list-state" role="status">Memuat daftar koneksi…</p><button class="btn" type="button" id="ai-reload">Muat ulang daftar</button></div>
  <p id="ai-message" role="status" hidden></p><p id="ai-list-error" class="automation-error" role="alert" tabindex="-1" hidden></p>
  <div class="ai-workspace">
    <div id="ai-list" class="ai-list"></div>
    <form id="ai-form" class="automation-section" hidden>
      <h3 id="ai-form-title">Tambah koneksi</h3>
      <p id="ai-impact" class="automation-help"></p>
      <div id="ai-error" class="automation-error" role="alert" tabindex="-1" hidden></div>
      <label for="ai-name">Nama koneksi</label><input id="ai-name" class="input" maxlength="80" required aria-describedby="ai-name-error"><p id="ai-name-error" class="ai-field-error" hidden></p>
      <label for="ai-base-url">Base URL 9Router</label><input id="ai-base-url" class="input" type="url" maxlength="2048" required placeholder="https://router.example.com/v1" aria-describedby="ai-url-help ai-base-url-error">
      <p class="automation-help" id="ai-url-help">Gunakan URL API /v1. Alamat localhost merujuk server aplikasi ini.</p><p id="ai-base-url-error" class="ai-field-error" hidden></p>
      <label for="ai-api-key">API key</label><div class="ai-key-input"><input id="ai-api-key" class="input" type="password" maxlength="4096" autocomplete="new-password" spellcheck="false" aria-describedby="ai-key-help ai-api-key-error"><button id="ai-key-toggle" type="button" class="btn" aria-pressed="false">Tampilkan</button></div>
      <p id="ai-key-help" class="automation-help">Key disimpan terenkripsi dan tidak ditampilkan kembali.</p><p id="ai-api-key-error" class="ai-field-error" hidden></p>
      <label for="ai-default-model">Model / combo default</label>
      <div class="ai-combobox"><div class="ai-model-input"><input id="ai-default-model" class="input" maxlength="255" required autocomplete="off" spellcheck="false" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="ai-model-options" aria-describedby="ai-model-help ai-default-model-error"><button type="button" class="btn" id="ai-model-toggle" aria-label="Buka daftar model" aria-controls="ai-model-options">Pilih</button></div><div id="ai-model-options" role="listbox" aria-label="Model dan combo 9Router" hidden></div></div>
      <p id="ai-model-help" class="automation-help">Isi nama model/combo persis seperti di 9Router, atau pilih dari katalog setelah Muat model.</p><p id="ai-default-model-error" class="ai-field-error" hidden></p>
      <div class="ai-actions"><button class="btn btn-primary" type="submit" id="ai-save">Simpan koneksi</button><button class="btn" type="button" id="ai-cancel">Batal</button></div>
      <p id="ai-form-status" class="automation-help" role="status"></p>
    </form>
  </div>
  <details class="automation-disclosure ai-test-note"><summary>Tentang uji model<span class="material-symbols-outlined automation-chevron" aria-hidden="true">expand_more</span></summary><p>Uji model memakai model default koneksi dan dapat memakai kuota. Hanya contoh sintetis yang dikirim, tanpa data pembeli atau balasan Shopee.</p></details>
</section>
