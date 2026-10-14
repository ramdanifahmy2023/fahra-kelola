<?php
define('burl', 'https://shopdash-fixture.test');
define('assets', burl . '/assets');
define('app_name', 'Shopdash');
function authCsrfToken(): string { return 'fixture-token'; }
$shops = [
  ['id' => 6, 'name' => 'elfuad.store'],
  ['id' => 7, 'name' => 'Safariana dengan nama toko yang cukup panjang untuk membungkus baris'],
];
$data = [
  'shops' => $shops,
  'active_shop_id' => 0,
  'active_conversation_id' => '',
];
$page = (string)($argv[1] ?? 'overview');
if (str_starts_with($page, 'picker-')) {
  $pickerPage = substr($page, 7);
  $data['active_menu'] = $pickerPage;
  $data['active_shop_id'] = 6;
  $data['limit'] = 20;
  $data['start_date'] = '2026-10-01';
  $data['end_date'] = '2026-10-14';
  $data['order_filter'] = 'completed';
  $data['stock_filter'] = 'low';
  $data['customer_filter'] = 'repeat';
}
?><!doctype html>
<html lang="id" data-theme="shopdash-light"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Shopdash recap fixture</title><link rel="stylesheet" href="<?= assets; ?>/css/style.css"></head><body class="bg-base-200 text-base-content"><main class="mx-auto w-full max-w-6xl p-4">
<?php
if (str_starts_with($page, 'picker-')) require __DIR__ . '/../../app/views/panel/templates/shop-picker.php';
elseif ($page === 'chat') require __DIR__ . '/../../app/views/panel/chat.php';
elseif ($page === 'sync') require __DIR__ . '/../../app/views/panel/sync.php';
elseif ($page === 'ads') require __DIR__ . '/../../app/views/panel/ads.php';
elseif ($page === 'promotions') require __DIR__ . '/../../app/views/panel/promotions.php';
else {
  $data['page_recap'] = [
    'id' => 'fixture-recap', 'title' => 'Ringkasan pesanan', 'description' => 'Tampilan uji data yang sudah diketahui.', 'scope' => 'Toko uji · 1–14 Okt 2026',
    'primary' => ['label' => 'Pesanan tercatat', 'value' => 28, 'context' => '2 belum memiliki tanggal'],
    'metrics' => [
      ['label' => 'Pesanan kilat', 'value' => 3, 'tone' => 'primary'],
      ['label' => 'Perlu diproses', 'value' => 5, 'tone' => 'warning'],
      ['label' => 'Dibatalkan', 'value' => 2, 'tone' => 'error'],
      ['label' => 'Perubahan pesanan', 'display' => '+12,5%', 'context' => '18 vs 16 pesanan bertanggal'],
    ],
    'segments_label' => 'Komposisi status',
    'segments' => [
      ['label' => 'Perlu diproses', 'value' => 5, 'tone' => 'warning', 'href' => '#orders-table?status=waiting'],
      ['label' => 'Selesai', 'value' => 18, 'tone' => 'success'],
      ['label' => 'Status belum lengkap', 'value' => 3, 'tone' => 'neutral'],
    ],
    'trend' => [['label' => '1 Okt', 'value' => 2], ['label' => '7 Okt', 'value' => 7], ['label' => '14 Okt', 'value' => 9]],
    'trend_label' => 'Tren pesanan', 'trend_caption' => '14 hari · data lokal dapat berubah setelah sinkronisasi',
    'shops_label' => 'Pesanan per toko', 'shops' => [['label' => $shops[0]['name'], 'value' => 18], ['label' => $shops[1]['name'], 'value' => 10]],
    'insight' => 'Jumlah pesanan mengikuti periode yang dipilih.',
  ];
  require __DIR__ . '/../../app/views/panel/templates/page-recap.php';
}
?>
</main></body></html>
