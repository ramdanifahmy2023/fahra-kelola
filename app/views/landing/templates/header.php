<!DOCTYPE html>
<html lang="id" data-theme="shopdash-light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $data['judul'] ?? app_name; ?></title>
  <meta name="description" content="Shopdash membantu pengelola toko Shopee memantau produk, pesanan, iklan, chat, promosi, dan stok dari satu workspace.">
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
      const update = mode => { const resolved = resolve(mode); document.documentElement.dataset.theme = 'shopdash-' + resolved; document.documentElement.dataset.themeMode = mode; document.documentElement.style.colorScheme = resolved; const meta = document.querySelector('meta[name="theme-color"]'); if (meta) meta.content = resolved === 'dark' ? '#0e0d0c' : '#fffdf9'; window.dispatchEvent(new CustomEvent('shopdash:theme', { detail: { mode, resolved } })); };
      window.shopdashTheme = { getMode: read, setMode: mode => { const next = valid.includes(mode) ? mode : 'system'; try { window.localStorage.setItem(key, next); } catch (_) {} update(next); }, resolve };
      if (media?.addEventListener) media.addEventListener('change', () => { if (read() === 'system') update('system'); }); else if (media) media.addListener(() => { if (read() === 'system') update('system'); });
      update(read());
    })();
  </script>
  <link rel="stylesheet" href="<?= assets; ?>/css/style.css?v=20260927-landing">
  <link rel="stylesheet" href="<?= web_icons; ?>/material-symbols.css">
</head>
<body class="landing-body">
  <header class="landing-header">
    <a class="landing-brand" href="<?= burl; ?>/" aria-label="Shopdash beranda"><span class="landing-mark landing-mark-header"><span class="material-symbols-outlined" aria-hidden="true">bolt</span></span><span><?= app_name; ?></span></a>
    <nav class="landing-nav" aria-label="Navigasi utama"><a href="#alur-kerja">Alur kerja</a><a href="#ruang-lingkup">Ruang lingkup</a></nav>
    <div class="landing-header-actions">
      <div class="landing-theme-controls" aria-label="Pilih tema"><button type="button" data-landing-theme="system" aria-label="Gunakan tema sistem" title="Sistem"><span class="material-symbols-outlined" aria-hidden="true">routine</span></button><button type="button" data-landing-theme="light" aria-label="Gunakan tema terang" title="Terang"><span class="material-symbols-outlined" aria-hidden="true">light_mode</span></button><button type="button" data-landing-theme="dark" aria-label="Gunakan tema gelap" title="Gelap"><span class="material-symbols-outlined" aria-hidden="true">dark_mode</span></button></div>
      <a class="landing-header-login" href="<?= burl; ?>/panel">Masuk ke panel<span class="material-symbols-outlined" aria-hidden="true">arrow_outward</span></a>
    </div>
  </header>
  <script>
    (() => { const controls = Array.from(document.querySelectorAll('[data-landing-theme]')); const render = mode => controls.forEach(control => { const active = control.dataset.landingTheme === (mode || window.shopdashTheme?.getMode?.() || 'system'); control.classList.toggle('is-active', active); control.setAttribute('aria-pressed', active ? 'true' : 'false'); }); controls.forEach(control => control.addEventListener('click', () => window.shopdashTheme?.setMode(control.dataset.landingTheme))); window.addEventListener('shopdash:theme', event => render(event.detail?.mode)); render(); })();
  </script>
