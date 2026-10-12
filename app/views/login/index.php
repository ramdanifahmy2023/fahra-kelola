<!DOCTYPE html>
<html lang="id" data-theme="shopdash-light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($data['judul'] ?? app_name); ?></title>
  <meta name="description" content="Masuk ke Shopdash untuk memantau produk, HPP, pesanan, promosi, chat, dan stok dari satu workspace.">
  <link rel="icon" type="image/svg+xml" href="<?= images; ?>/favicon.svg">
  <link rel="apple-touch-icon" href="<?= images; ?>/favicon.svg">
  <meta name="theme-color" content="#fffdf9">
  <script>
    (() => {
      const key = 'shopdash-theme';
      const valid = ['system', 'light', 'dark'];
      const media = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;
      const read = () => { try { const value = window.localStorage.getItem(key); return valid.includes(value) ? value : 'system'; } catch (_) { return 'system'; } };
      const resolve = mode => mode === 'system' ? (media?.matches ? 'dark' : 'light') : mode;
      const update = mode => {
        const resolved = resolve(mode);
        document.documentElement.dataset.theme = 'shopdash-' + resolved;
        document.documentElement.dataset.themeMode = mode;
        document.documentElement.style.colorScheme = resolved;
        const meta = document.querySelector('meta[name="theme-color"]');
        if (meta) meta.content = resolved === 'dark' ? '#0e0d0c' : '#fffdf9';
        window.dispatchEvent(new CustomEvent('shopdash:theme', { detail: { mode, resolved } }));
      };
      window.shopdashTheme = { getMode: read, setMode: mode => { const next = valid.includes(mode) ? mode : 'system'; try { window.localStorage.setItem(key, next); } catch (_) {} update(next); } };
      if (media?.addEventListener) media.addEventListener('change', () => { if (read() === 'system') update('system'); }); else if (media) media.addListener(() => { if (read() === 'system') update('system'); });
      update(read());
    })();
  </script>
  <link rel="stylesheet" href="<?= assets; ?>/css/style.css?v=20261012-ui">
  <link rel="stylesheet" href="<?= web_icons; ?>/material-symbols.css">
</head>
<body class="login-body">
  <a class="skip-link" href="#login-content">Lewati ke formulir masuk</a>
  <main id="login-content" tabindex="-1" class="login-shell">
    <section class="login-visual" aria-label="Shopdash workspace">
      <a href="<?= burl; ?>/" class="login-brand"><span class="landing-mark landing-mark-header"><span class="material-symbols-outlined" aria-hidden="true">monitoring</span></span><span><?= app_name; ?></span></a>
      <div class="login-visual-copy">
        <p class="login-eyebrow"><span></span>Multi-store operations</p>
        <h1>Semua toko dalam satu pandangan.</h1>
        <p>Masuk untuk melihat penjualan, stok, pesanan, iklan, dan aktivitas toko yang tersimpan di workspace Anda.</p>
        <ul class="login-signal-list" aria-label="Area kerja Shopdash"><li><span class="material-symbols-outlined" aria-hidden="true">inventory_2</span><span><strong>Produk dan HPP</strong><small>Katalog lebih siap ditindaklanjuti</small></span></li><li><span class="material-symbols-outlined" aria-hidden="true">receipt_long</span><span><strong>Pesanan</strong><small>Status dan detail tetap dekat</small></span></li><li><span class="material-symbols-outlined" aria-hidden="true">priority_high</span><span><strong>Prioritas</strong><small>Perhatian penting lebih terlihat</small></span></li></ul>
      </div>
      <div class="login-visual-foot"><span class="material-symbols-outlined" aria-hidden="true">verified_user</span><span>Workspace pribadi dengan sesi yang terlindungi</span></div>
    </section>
    <section class="login-panel">
      <div class="login-card">
        <div class="login-card-tools"><span class="login-card-context">Workspace Shopdash</span><div class="login-theme-controls" role="group" aria-label="Pilih tema"><button type="button" data-login-theme="system" aria-label="Gunakan tema sistem" title="Sistem"><span class="material-symbols-outlined" aria-hidden="true">routine</span></button><button type="button" data-login-theme="light" aria-label="Gunakan tema terang" title="Terang"><span class="material-symbols-outlined" aria-hidden="true">light_mode</span></button><button type="button" data-login-theme="dark" aria-label="Gunakan tema gelap" title="Gelap"><span class="material-symbols-outlined" aria-hidden="true">dark_mode</span></button></div></div>
        <div class="login-card-heading">
          <div class="login-card-icon"><span class="material-symbols-outlined" aria-hidden="true">lock</span></div>
          <div><p class="login-kicker">Selamat datang kembali</p><h2>Masuk ke Shopdash</h2></div>
        </div>
        <p class="login-description">Gunakan email akun Anda untuk melanjutkan ke dashboard.</p>
        <?php Alert::show(); ?>
        <form class="login-form" method="post" action="<?= burl; ?>/auth/login" aria-busy="false">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(authCsrfToken(), ENT_QUOTES); ?>">
          <input type="hidden" name="next" value="<?= htmlspecialchars($data['next'] ?? '/panel', ENT_QUOTES); ?>">
          <label for="login-email">Email</label>
          <div class="login-input-wrap"><span class="material-symbols-outlined" aria-hidden="true">mail</span><input id="login-email" name="email" type="email" inputmode="email" autocomplete="email" placeholder="nama@contoh.com" required autofocus></div>
          <label for="login-password">Password</label>
          <div class="login-input-wrap"><span class="material-symbols-outlined" aria-hidden="true">key</span><input id="login-password" name="password" type="password" autocomplete="current-password" placeholder="Masukkan password" required><button type="button" class="login-password-toggle" aria-label="Tampilkan password" aria-controls="login-password" aria-pressed="false"><span class="material-symbols-outlined" aria-hidden="true">visibility</span></button></div>
          <button class="login-submit" type="submit"><span class="login-submit-label">Masuk ke dashboard</span><span class="material-symbols-outlined login-submit-arrow" aria-hidden="true">arrow_forward</span><span class="material-symbols-outlined login-submit-loading" aria-hidden="true">progress_activity</span></button>
        </form>
        <p class="login-submit-status" id="login-submit-status" aria-live="polite" hidden></p>
        <a class="login-back" href="<?= burl; ?>/"><span class="material-symbols-outlined" aria-hidden="true">arrow_back</span>Kembali ke halaman depan</a>
      </div>
    </section>
  </main>
  <script>
    (() => {
      const themeControls = Array.from(document.querySelectorAll('[data-login-theme]'));
      const renderTheme = mode => themeControls.forEach(control => { const active = control.dataset.loginTheme === (mode || window.shopdashTheme?.getMode?.() || 'system'); control.classList.toggle('is-active', active); control.setAttribute('aria-pressed', active ? 'true' : 'false'); });
      themeControls.forEach(control => control.addEventListener('click', () => window.shopdashTheme?.setMode(control.dataset.loginTheme)));
      window.addEventListener('shopdash:theme', event => renderTheme(event.detail?.mode));
      renderTheme();

      const toggle = document.querySelector('.login-password-toggle');
      const input = document.getElementById('login-password');
      if (toggle && input) toggle.addEventListener('click', () => {
        const visible = input.type === 'text';
        input.type = visible ? 'password' : 'text';
        toggle.setAttribute('aria-label', visible ? 'Tampilkan password' : 'Sembunyikan password');
        toggle.setAttribute('aria-pressed', visible ? 'false' : 'true');
        toggle.querySelector('.material-symbols-outlined').textContent = visible ? 'visibility' : 'visibility_off';
      });

      const form = document.querySelector('.login-form');
      const submit = form?.querySelector('.login-submit');
      const status = document.getElementById('login-submit-status');
      form?.addEventListener('submit', () => {
        if (!form.checkValidity() || !submit) return;
        submit.disabled = true;
        submit.classList.add('is-loading');
        form.setAttribute('aria-busy', 'true');
        if (status) { status.hidden = false; status.textContent = 'Memeriksa akun…'; }
      });
    })();
  </script>
</body>
</html>
