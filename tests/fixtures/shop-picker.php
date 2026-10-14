<?php
// Real shared controls with synthetic shops; no database or shop API access.
define('burl', 'https://shopdash-fixture.test');
parse_str($argv[1] ?? '', $query);
$data = ['active_menu'=>$argv[2] ?? 'orders', 'active_shop_id'=>(int)($query['shop_id'] ?? 7), 'limit'=>10,
  'start_date'=>'2026-09-01', 'end_date'=>'2026-09-30', 'shops'=>[]];
for ($id=7; $id<22; $id++) $data['shops'][]=['id'=>$id,'name'=>'Toko Uji '.$id.' Perlengkapan Rumah dengan Nama Panjang','shop_logo'=>''];
?>
<!doctype html><html lang="id" data-theme="shopdash-light"><head><meta name="viewport" content="width=device-width, initial-scale=1"><link rel="stylesheet" href="/assets/css/style.css"></head>
<body><main style="padding:16px"><h1>Data uji pemilih toko</h1><button id="outside">Di luar menu</button><div style="max-width:256px;margin:24px 0">
<?php require __DIR__.'/../../app/views/panel/templates/shop-picker.php'; ?>
</div><p id="loaded-shop">Data toko <?= $data['active_shop_id'] ?></p>
<label for="dynamic-shop">Toko dinamis</label><select id="dynamic-shop"><option value="7">Toko Uji 7</option><option value="8">Toko Uji 8</option></select>
<label for="dynamic-multiple">Toko dinamis multi-pilih</label><select id="dynamic-multiple" multiple><option value="7" selected>Toko Uji 7</option><option value="8" selected>Toko Uji 8</option></select>
<script>window.renderShopLogo = el => { el.textContent = 'storefront'; };</script><script src="/assets/js/shop-select.js"></script><script>enhanceShopSelect(document.getElementById('dynamic-shop'));</script>
<script>enhanceShopSelect(document.getElementById('dynamic-multiple'));</script>
</main></body></html>
