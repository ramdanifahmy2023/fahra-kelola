<?php

$options = getopt('', ['shop:', 'expect-shop:']);
$shopId = filter_var($options['shop'] ?? null, FILTER_VALIDATE_INT);
if (!$shopId || $shopId < 1) {
  fwrite(STDERR, "Usage: php bin/ads-diagnose.php --shop=LOCAL_ID [--expect-shop=SHOPEE_ID]\n");
  exit(1);
}
chdir(__DIR__ . '/../public');
require_once __DIR__ . '/../app/init.php';
require_once __DIR__ . '/../app/models/Shop.php';
require_once __DIR__ . '/../app/models/ShopeeCurl.php';
require_once __DIR__ . '/../app/helpers/AdsPerformance.php';

$shop = (new Shop())->findBy('id', $shopId);
if (!$shop || empty($shop['cookie'])) {
  fwrite(STDERR, "Toko tidak ditemukan atau cookie kosong.\n");
  exit(1);
}
$client = new ShopeeCurl();
$identity = $client->check($shop['cookie']);
$actualShopId = (int)($identity['shop']['id'] ?? 0);
$expectedShopId = isset($options['expect-shop']) ? (int)$options['expect-shop'] : (int)$shop['shop_id'];
$matches = $actualShopId > 0 && $actualShopId === $expectedShopId && $actualShopId === (int)$shop['shop_id'];
$result = [
  'checked_at' => gmdate('c'),
  'local_shop_id' => $shopId,
  'expected_shop_id' => $expectedShopId,
  'actual_shop_id' => $actualShopId ?: null,
  'identity_matches' => $matches,
  'identity_request' => $client->getLastRequestDiagnostics(),
  'report_state' => 'skipped_identity_mismatch'
];
if ($matches) {
  preg_match('/SPC_CDS=([^;]+)/', $shop['cookie'], $token);
  if (!empty($token[1])) {
    $body = AdsPerformance::requestBody(AdsPerformance::range('daily'), 'product');
    $response = $client->request('POST', 'https://seller.shopee.co.id/api/pas/v1/report/get_time_graph/?SPC_CDS=' . urlencode($token[1]) . '&SPC_CDS_VER=2', $shop['cookie'], $body, [
      'Origin: https://seller.shopee.co.id',
      'Referer: https://seller.shopee.co.id/portal/marketing/pas/index'
    ]);
    $result['report_state'] = 'sent_once';
    $result['report_body_business'] = $body;
    $result['report_request'] = $client->getLastRequestDiagnostics();
    $normalized = AdsPerformance::normalize(is_array($response) ? $response : [], AdsPerformance::range('daily'), 'product');
    $result['report_available'] = $normalized['available'];
  } else {
    $result['report_state'] = 'skipped_missing_session';
  }
}
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
