<?php
// Real view and scripts, synthetic data only. Rendered by the CLI test runner.
define('burl', 'https://shopdash-fixture.test');
define('assets', burl.'/assets');
function authUser() { return ['id'=>1]; }
function authCsrfToken() { return 'visual-fixture'; }
$data=['finance_dashboard'=>($argv[1] ?? 'dashboard')==='dashboard','shops'=>[
  ['id'=>1,'name'=>'Toko Uji Utama','shop_logo'=>''],
  ['id'=>2,'name'=>'Toko Uji Perlengkapan Perjalanan dengan Nama Panjang','shop_logo'=>'broken-fixture']
]];
?>
<!doctype html><html lang="id" data-theme="shopdash-light"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Data uji visual Shopdash</title><link rel="stylesheet" href="<?= assets; ?>/css/style.css"><link rel="stylesheet" href="<?= assets; ?>/web_icons/material-symbols.css">
<style>body{margin:0;background:var(--color-base-200);color:var(--color-base-content)}main{padding:16px;max-width:1360px;margin:auto}#fixture-label{padding:8px 16px;font-size:14px;background:var(--color-base-100)}@media(min-width:1000px){main{padding:32px}}</style>
</head><body><p id="fixture-label">Data uji sintetis · tidak terhubung ke toko</p><main>
<?php require __DIR__.'/../../app/views/panel/finance.php'; ?>
</main></body></html>
