<!DOCTYPE html>
<html lang="id" data-theme="shopdash-light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $data['judul'] ?? app_name; ?></title>
  <link rel="icon" type="image/svg+xml" href="<?= images; ?>/favicon.svg">
  <link rel="apple-touch-icon" href="<?= images; ?>/favicon.svg">
  <meta name="theme-color" content="#a3a3a3">
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
      window.shopdashTheme = {
        getMode: read,
        setMode: mode => {
          const next = valid.includes(mode) ? mode : 'system';
          try { window.localStorage.setItem(key, next); } catch (_) {}
          update(next);
        },
        resolve
      };
      if (media?.addEventListener) media.addEventListener('change', () => { if (read() === 'system') update('system'); });
      else if (media) media.addListener(() => { if (read() === 'system') update('system'); });
      update(read());
    })();
  </script>
  
  <!-- CSS Tailwind & DaisyUI -->
  <link rel="stylesheet" href="<?= assets; ?>/css/style.css">
  
  <!-- Google Material Symbols -->
  <link rel="stylesheet" href="<?= web_icons; ?>/material-symbols.css">
</head>
<body class="min-h-screen bg-base-200 text-base-content flex flex-col">
  <header class="flex items-center justify-between gap-4 border-b border-base-content/10 bg-base-100 px-4 py-3 sm:px-6">
    <div class="text-sm font-black tracking-tight text-primary"><?= app_name; ?></div>
    <div class="flex items-center gap-1 rounded-xl border border-base-content/10 bg-base-200 p-1" aria-label="Pilih tema">
      <button type="button" data-landing-theme="system" class="btn btn-ghost btn-sm min-h-10 rounded-lg px-2" aria-label="Gunakan tema sistem" title="Sistem"><span class="material-symbols-outlined text-base">routine</span></button>
      <button type="button" data-landing-theme="light" class="btn btn-ghost btn-sm min-h-10 rounded-lg px-2" aria-label="Gunakan tema terang" title="Terang"><span class="material-symbols-outlined text-base">light_mode</span></button>
      <button type="button" data-landing-theme="dark" class="btn btn-ghost btn-sm min-h-10 rounded-lg px-2" aria-label="Gunakan tema gelap" title="Gelap"><span class="material-symbols-outlined text-base">dark_mode</span></button>
    </div>
  </header>
  <script>
    (() => {
      const controls = Array.from(document.querySelectorAll('[data-landing-theme]'));
      const render = mode => controls.forEach(control => {
        const active = control.dataset.landingTheme === (mode || window.shopdashTheme?.getMode?.() || 'system');
        control.classList.toggle('bg-primary', active);
        control.classList.toggle('text-primary-content', active);
        control.setAttribute('aria-pressed', active ? 'true' : 'false');
      });
      controls.forEach(control => control.addEventListener('click', () => window.shopdashTheme?.setMode(control.dataset.landingTheme)));
      window.addEventListener('shopdash:theme', event => render(event.detail?.mode));
      render();
    })();
  </script>
