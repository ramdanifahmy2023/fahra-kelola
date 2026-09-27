<!DOCTYPE html>
<html lang="id" data-theme="shopdash-light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($data['judul'] ?? app_name); ?></title>
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
      };
      update(read());
    })();
  </script>
  <link rel="stylesheet" href="<?= assets; ?>/css/style.css?v=20260928-auth">
  <link rel="stylesheet" href="<?= web_icons; ?>/material-symbols.css">
</head>
<body class="login-body">
  <main class="login-shell">
    <section class="login-visual" aria-label="Shopdash workspace">
      <a href="<?= burl; ?>/" class="login-brand"><span class="landing-mark landing-mark-header"><span class="material-symbols-outlined">monitoring</span></span><span><?= app_name; ?></span></a>
      <div class="login-visual-copy">
        <p class="login-eyebrow"><span></span>Multi-store operations</p>
        <h1>Semua toko dalam satu pandangan.</h1>
        <p>Masuk untuk melihat penjualan, stok, pesanan, iklan, dan aktivitas toko yang tersimpan di workspace Anda.</p>
      </div>
      <div class="login-visual-foot"><span class="material-symbols-outlined">verified_user</span><span>Workspace pribadi dengan sesi yang terlindungi</span></div>
    </section>
    <section class="login-panel">
      <div class="login-card">
        <div class="login-card-heading">
          <div class="login-card-icon"><span class="material-symbols-outlined">lock</span></div>
          <div><p class="login-kicker">Selamat datang kembali</p><h2>Masuk ke Shopdash</h2></div>
        </div>
        <p class="login-description">Gunakan email akun Anda untuk melanjutkan ke dashboard.</p>
        <?php Alert::show(); ?>
        <form class="login-form" method="post" action="<?= burl; ?>/auth/login">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(authCsrfToken(), ENT_QUOTES); ?>">
          <input type="hidden" name="next" value="<?= htmlspecialchars($data['next'] ?? '/panel', ENT_QUOTES); ?>">
          <label for="login-email">Email</label>
          <div class="login-input-wrap"><span class="material-symbols-outlined">mail</span><input id="login-email" name="email" type="email" autocomplete="email" placeholder="nama@contoh.com" required autofocus></div>
          <label for="login-password">Password</label>
          <div class="login-input-wrap"><span class="material-symbols-outlined">key</span><input id="login-password" name="password" type="password" autocomplete="current-password" placeholder="Masukkan password" required><button type="button" class="login-password-toggle" aria-label="Tampilkan password"><span class="material-symbols-outlined">visibility</span></button></div>
          <button class="login-submit" type="submit"><span>Masuk ke dashboard</span><span class="material-symbols-outlined">arrow_forward</span></button>
        </form>
        <a class="login-back" href="<?= burl; ?>/"><span class="material-symbols-outlined">arrow_back</span>Kembali ke halaman depan</a>
      </div>
    </section>
  </main>
  <script>
    (() => {
      const toggle = document.querySelector('.login-password-toggle');
      const input = document.getElementById('login-password');
      if (!toggle || !input) return;
      toggle.addEventListener('click', () => {
        const visible = input.type === 'text';
        input.type = visible ? 'password' : 'text';
        toggle.setAttribute('aria-label', visible ? 'Tampilkan password' : 'Sembunyikan password');
        toggle.querySelector('.material-symbols-outlined').textContent = visible ? 'visibility' : 'visibility_off';
      });
    })();
  </script>
</body>
</html>
