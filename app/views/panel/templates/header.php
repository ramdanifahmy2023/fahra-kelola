<!DOCTYPE html>
<html lang="id" data-theme="cupcake">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $data['judul'] ?? app_name; ?></title>
  <link rel="icon" type="image/svg+xml" href="<?= images; ?>/favicon.svg">
  <link rel="apple-touch-icon" href="<?= images; ?>/favicon.svg">
  <meta name="theme-color" content="#a3a3a3">
  <link rel="stylesheet" href="<?= assets; ?>/css/style.css">
  <link rel="stylesheet" href="<?= web_icons; ?>/material-symbols.css">
  <style>
    .material-symbols-outlined { display: inline-flex; align-items: center; justify-content: center; max-width: 1em; overflow: hidden; flex-shrink: 0; white-space: nowrap; vertical-align: middle; }
  </style>
</head>
<body>
  <?php Alert::show(); ?>
  <div id="floating-tooltip" class="pointer-events-none fixed z-[100] hidden max-w-xs rounded-md bg-neutral px-2.5 py-1.5 text-[11px] font-medium text-neutral-content shadow-xl"></div>
  <script>
    (() => {
      const tooltip = document.getElementById('floating-tooltip');
      let activeTarget = null;
      const position = event => {
        if (!activeTarget || !tooltip) return;
        tooltip.style.left = Math.min(event.clientX + 12, window.innerWidth - tooltip.offsetWidth - 8) + 'px';
        tooltip.style.top = Math.max(8, event.clientY - tooltip.offsetHeight - 10) + 'px';
      };
      document.addEventListener('pointerover', event => {
        const target = event.target.closest('.js-floating-tooltip');
        if (!target || !tooltip) return;
        activeTarget = target;
        tooltip.textContent = target.dataset.tip || target.textContent.trim();
        tooltip.classList.remove('hidden');
        position(event);
      });
      document.addEventListener('pointermove', position);
      document.addEventListener('pointerout', event => {
        if (!activeTarget || activeTarget.contains(event.relatedTarget)) return;
        activeTarget = null;
        tooltip.classList.add('hidden');
      });
    })();
  </script>
  <div class="drawer lg:drawer-open">
    <input id="panel-drawer" type="checkbox" class="drawer-toggle" />
    <div class="drawer-content flex min-h-screen flex-col bg-base-200">
      <?php require_once __DIR__ . '/navbar.php'; ?>
      <main class="relative flex-1 px-3 py-4 sm:px-5 lg:px-6 lg:py-5">
        <div class="mx-auto w-full max-w-[1600px]">
