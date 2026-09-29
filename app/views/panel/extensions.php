<?php
$releases = $data['releases'] ?? [];
$latest = $releases[0] ?? null;
$escape = fn($value) => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
$formatDate = function ($date) {
  $months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
  $parsed = new DateTimeImmutable($date);
  return $parsed->format('j') . ' ' . $months[(int)$parsed->format('n') - 1] . ' ' . $parsed->format('Y');
};
$formatSize = fn($size) => $size >= 1048576 ? number_format($size / 1048576, 1, ',', '.') . ' MB' : number_format($size / 1024, 0, ',', '.') . ' KB';
?>
<div id="extensions-page">
  <header class="extensions-heading">
    <h1>Ekstensi</h1>
    <p>Unduh alat bantu Sellerio, lihat perubahan, dan temukan kembali versi sebelumnya.</p>
  </header>

  <?php if (!empty($data['release_error'])): ?>
    <section class="extensions-message" role="alert">
      <h2>Daftar versi belum dapat dimuat</h2>
      <p>Muat ulang halaman untuk mencoba kembali. Jika masih gagal, hubungi pengelola Sellerio.</p>
      <a class="extensions-button extensions-button-secondary" href="<?= burl; ?>/panel/extensions">Muat ulang halaman</a>
    </section>
  <?php elseif (!$latest): ?>
    <section class="extensions-message">
      <h2>Belum ada rilis ekstensi</h2>
      <p>Paket unduhan dan catatan perubahan akan tampil di sini setelah rilis tersedia.</p>
    </section>
  <?php else: ?>
    <section class="extension-current" aria-labelledby="extension-name">
      <div class="extension-product">
        <img src="<?= images; ?>/favicon.svg" alt="" width="52" height="52" />
        <div>
          <p class="extension-eyebrow">Versi terbaru · v<?= $escape($latest['version']); ?></p>
          <h2 id="extension-name">Sellerio Get Cookies</h2>
        </div>
      </div>
      <p class="extension-description">Salin cookie dari tab toko untuk menghubungkannya ke Sellerio.</p>
      <dl class="extension-specs">
        <div><dt>Browser</dt><dd>Google Chrome & Microsoft Edge</dd></div>
        <div><dt>Perangkat</dt><dd>Komputer · macOS & Windows</dd></div>
      </dl>
      <div class="extension-download">
        <?php if ($latest['available']): ?>
          <a class="extensions-button extensions-button-primary" href="<?= burl . $escape($latest['url']); ?>" download="<?= $escape($latest['filename']); ?>" data-latest-download>
            <span class="material-symbols-outlined" aria-hidden="true">download</span>
            Unduh ekstensi v<?= $escape($latest['version']); ?>
          </a>
          <p>ZIP · <?= $escape($formatSize($latest['size'])); ?> · <time datetime="<?= $escape($latest['date']); ?>"><?= $escape($formatDate($latest['date'])); ?></time></p>
        <?php else: ?>
          <p class="extension-unavailable" role="status">Paket versi terbaru belum dapat diunduh. Pilih versi lain yang tersedia di riwayat atau hubungi pengelola.</p>
        <?php endif; ?>
      </div>
    </section>

    <nav class="extensions-sections" aria-label="Bagian halaman ekstensi">
      <a class="extensions-text-link" href="#installation-heading">Cara pasang</a>
      <a class="extensions-text-link" href="#history-heading">Riwayat versi</a>
    </nav>
    <div class="extensions-columns">
      <section class="extension-guide" aria-labelledby="installation-heading">
        <h2 id="installation-heading">Cara pasang</h2>
        <p class="extensions-muted">Unduhan bisa disimpan dari ponsel. Pemasangan dilakukan di browser komputer.</p>
        <ol class="extension-steps">
          <li><h3>Unduh dan ekstrak ZIP</h3><p>Simpan folder <strong>Sellerio Get Cookies</strong> di lokasi tetap pada komputer.</p></li>
          <li><h3>Buka halaman ekstensi</h3><p>Ketik alamat berikut di browser:</p><dl class="extension-browser-addresses"><div><dt>Chrome</dt><dd><code>chrome://extensions</code></dd></div><div><dt>Edge</dt><dd><code>edge://extensions</code></dd></div></dl></li>
          <li><h3>Muat folder ekstensi</h3><p>Aktifkan <strong>Mode developer</strong>, klik <strong>Load unpacked / Muat yang belum dipaketkan</strong>, lalu pilih folder yang berisi <code>manifest.json</code>.</p></li>
          <li><h3>Salin cookie toko</h3><p>Login ke situs toko, buka Sellerio Get Cookies, lalu klik <strong>Salin cookie</strong>. Tempel di pengaturan toko Sellerio.</p></li>
        </ol>
        <a class="extensions-text-link" href="<?= burl; ?>/panel/shops">Buka pengaturan toko<span class="material-symbols-outlined" aria-hidden="true">arrow_forward</span></a>
        <details class="extension-update-guide">
          <summary>Sudah terpasang? Cara ganti versi</summary>
          <p>Unduh versi yang dipilih dari riwayat, lalu ekstrak isinya ke folder instalasi yang sama. Buka halaman ekstensi browser dan klik <strong>Muat ulang / Reload</strong>.</p>
          <p>Langkah ini juga berlaku untuk kembali ke versi lama. Paket setiap versi tetap tersimpan di halaman ini.</p>
        </details>
      </section>

      <section class="extension-history" aria-labelledby="history-heading">
        <div class="extension-history-heading">
          <h2 id="history-heading">Riwayat versi</h2>
          <p class="extensions-muted">Setiap rilis memiliki nama dan catatan perubahan. Versi lama tetap tersedia sebagai backup.</p>
        </div>
        <div class="extension-release-list">
          <?php foreach ($releases as $index => $release): ?>
            <article class="extension-release" id="release-<?= $escape($release['version']); ?>" data-release-version="<?= $escape($release['version']); ?>">
              <div class="extension-release-meta"><span>v<?= $escape($release['version']); ?><?= $index === 0 ? ' · Terbaru' : ''; ?></span><time datetime="<?= $escape($release['date']); ?>"><?= $escape($formatDate($release['date'])); ?></time></div>
              <h3><?= $escape($release['name']); ?></h3>
              <details class="extension-changelog" <?= $index === 0 ? 'open' : ''; ?>>
                <summary>Catatan perubahan<span class="material-symbols-outlined" aria-hidden="true">expand_more</span></summary>
                <ul>
                  <?php foreach ($release['changes'] as $change): ?>
                    <li><h4><?= $escape($change['title']); ?></h4><p><?= $escape($change['description']); ?></p></li>
                  <?php endforeach; ?>
                </ul>
              </details>
              <?php if ($release['available']): ?>
                <a class="extensions-button extensions-button-secondary" href="<?= burl . $escape($release['url']); ?>" download="<?= $escape($release['filename']); ?>" data-release-download>Unduh v<?= $escape($release['version']); ?><span class="extension-file-size">ZIP · <?= $escape($formatSize($release['size'])); ?></span></a>
              <?php else: ?>
                <p class="extension-unavailable">Paket ini belum dapat diunduh. Hubungi pengelola untuk memulihkan arsipnya.</p>
              <?php endif; ?>
            </article>
          <?php endforeach; ?>
        </div>
        <?php if (count($releases) === 1): ?>
          <p class="extension-archive-note">Arsip dimulai dari v<?= $escape($latest['version']); ?>. Versi ini tetap tersedia saat pembaruan berikutnya dirilis.</p>
        <?php endif; ?>
      </section>
    </div>
  <?php endif; ?>
</div>
