<?php
$boostIconPaths = [
  'repeat' => '<path d="m17 2 4 4-4 4M3 11V8a2 2 0 0 1 2-2h16M7 22l-4-4 4-4m14-1v3a2 2 0 0 1-2 2H3"/>',
  'box' => '<path d="m12 3 9 5v8l-9 5-9-5V8l9-5Zm0 9v9M3 8l9 4 9-4M7.5 5.5l9 5"/>',
  'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
  'slots' => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
  'check' => '<circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/>',
  'alert' => '<path d="m10.3 4.2-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.7-2.8l-8-14a2 2 0 0 0-3.4 0ZM12 9v4m0 4h.01"/>',
  'pause' => '<rect x="6" y="4" width="4" height="16" rx="1"/><rect x="14" y="4" width="4" height="16" rx="1"/>',
  'play' => '<path d="m7 4 14 8-14 8V4Z"/>',
  'edit' => '<path d="m16 3 5 5-12 12-6 1 1-6L16 3Zm-2 2 5 5"/>',
  'up' => '<path d="M12 20V4m-7 7 7-7 7 7"/>',
  'refresh' => '<path d="M20 7v5h-5M4 17v-5h5m-5 0a8 8 0 0 1 13.7-5.7L20 9M4 15l2.3 2.7A8 8 0 0 0 20 12"/>',
  'trend' => '<path d="m3 17 6-6 4 4 8-10m-6 0h6v6"/>',
  'search' => '<circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 5 5"/>',
  'history' => '<path d="M3 10V4m0 6h6m-6 0a9 9 0 1 1 2 8M12 7v5l3 2"/>',
  'store' => '<path d="M4 10v11h16V10M2 10l2-7h16l2 7M2 10a2.5 2.5 0 0 0 5 0 2.5 2.5 0 0 0 5 0 2.5 2.5 0 0 0 5 0 2.5 2.5 0 0 0 5 0M9 21v-7h6v7"/>',
  'worker' => '<path d="M4 7h16v10H4V7Zm4 0V4m8 3V4M8 17v3m8-3v3M8 11h.01M16 11h.01m-6 3h4"/>',
  'info' => '<circle cx="12" cy="12" r="9"/><path d="M12 11v6m0-10h.01"/>',
];
$boostIcon = static function (string $name): string {
  return '<svg class="boost-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><use href="#boost-icon-'.$name.'"></use></svg>';
};
?>
<svg class="boost-icon-defs" aria-hidden="true" focusable="false"><defs><?php foreach ($boostIconPaths as $name=>$paths): ?><symbol id="boost-icon-<?= $name; ?>" viewBox="0 0 24 24"><?= $paths; ?></symbol><?php endforeach; ?></defs></svg>
