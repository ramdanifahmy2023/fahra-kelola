<?php
require __DIR__ . '/../app/helpers/AdsBrowserCapture.php';
$checks = 0;
function verifyBrowser($condition, $message) {
  global $checks;
  $checks++;
  if (!$condition) throw new RuntimeException($message);
}
function rejectsBrowser($bundle, $shopId, $message) {
  try { AdsBrowserCapture::normalize($bundle, $shopId); }
  catch (InvalidArgumentException $error) { verifyBrowser(true, $message); return; }
  verifyBrowser(false, $message);
}
$range = AdsPerformance::range('monthly', new DateTimeImmutable('2026-09-28T22:00:00+07:00'));
$response = json_decode(file_get_contents(__DIR__ . '/../ops/ads-network-2026-09-28/product-monthly.response.projection.json'), true);
$identity = ['context_id' => 'fixture-tab', 'request_id' => '1', 'http_status' => 200,
  'observed_at' => '2026-09-28T21:59:50+07:00', 'path' => '/api/v2/login/', 'response' => ['errcode' => 0, 'shopid' => 137867791]];
$bundle = ['version' => 1, 'source' => 'seller-centre-browser', 'identity_before' => $identity,
  'identity_after' => array_merge($identity, ['request_id' => '3', 'observed_at' => '2026-09-28T22:00:10+07:00']),
  'report' => ['context_id' => 'fixture-tab', 'request_id' => '2', 'http_status' => 200,
    'observed_at' => '2026-09-28T22:00:00+07:00', 'path' => '/api/pas/v1/report/get_time_graph/',
    'method' => 'POST', 'request' => AdsPerformance::requestBody($range, 'product'), 'response' => $response]];
$good = AdsBrowserCapture::normalize($bundle, 137867791);
verifyBrowser($good['available'] && $good['orders'] === 648 && count($good['daily']) === 28, 'Real capture maps checkout and all dates');
verifyBrowser($good['fetched_at'] === '2026-09-28T15:00:00+00:00', 'Capture time is preserved instead of import time');
verifyBrowser($good['collection_method'] === 'browser_capture', 'Browser provenance retained');
rejectsBrowser($bundle, 999, 'Wrong destination shop rejected');
$bad = $bundle; $bad['identity_after']['response']['shopid'] = 999; rejectsBrowser($bad, 137867791, 'Shop switch rejected');
$bad = $bundle; $bad['identity_after']['context_id'] = 'other'; rejectsBrowser($bad, 137867791, 'Different browser context rejected');
$bad = $bundle; $bad['identity_before']['response']['errcode'] = 1; rejectsBrowser($bad, 137867791, 'Failed identity rejected');
$bad = $bundle; $bad['identity_after']['observed_at'] = '2026-09-28T23:00:00+07:00'; rejectsBrowser($bad, 137867791, 'Old identity bracket rejected');
$bad = $bundle; $bad['identity_before']['observed_at'] = '2026-09-28T22:00:05+07:00'; rejectsBrowser($bad, 137867791, 'Out-of-order observation rejected');
$bad = $bundle; $bad['report']['observed_at'] = '2026-09-31T22:00:00+07:00'; rejectsBrowser($bad, 137867791, 'Invalid date rejected');
$bad = $bundle; $bad['report']['response'] = ['error' => 90309999]; rejectsBrowser($bad, 137867791, 'HTTP 200 with rejection is not success');
$bad = $bundle; $bad['report']['response']['data']['report_aggregate']['checkout']++; rejectsBrowser($bad, 137867791, 'Inconsistent aggregate rejected');
$bad = $bundle; $bad['report']['response']['data']['report_by_time'][] = $response['data']['report_by_time'][0]; rejectsBrowser($bad, 137867791, 'Duplicate point rejected');
$bad = $bundle; $bad['report']['request']['start_time'] -= 86400; rejectsBrowser($bad, 137867791, 'Rolling range cannot masquerade as calendar month');
$bad = $bundle; $bad['report']['request']['filter_params']['campaign_id'] = 1; rejectsBrowser($bad, 137867791, 'Single campaign cannot masquerade as shop report');
$bad = $bundle; $bad['report']['response']['data']['report_aggregate']['cost'] = null; rejectsBrowser($bad, 137867791, 'Missing money cannot become zero');
$secrets = $bundle;
$secrets['report']['request']['device_sz_fingerprint'] = 'secret-fixture';
$secrets['identity_before']['response']['token'] = 'secret-fixture';
$secrets['report']['response']['data']['unexpected'] = 'secret-fixture';
verifyBrowser(!str_contains(json_encode(AdsBrowserCapture::normalize($secrets, 137867791)), 'secret-fixture'), 'Unexpected fields and credentials excluded from stored projection');
$zero = $bundle;
foreach ($zero['report']['response']['data']['report_aggregate'] as &$value) $value = 0;
unset($value);
foreach ($zero['report']['response']['data']['report_by_time'] as &$point) foreach ($point['metrics'] as &$value) $value = 0;
unset($point, $value);
verifyBrowser(AdsBrowserCapture::normalize($zero, 137867791)['sales'] === 0.0, 'Successful zero report accepted');

chdir(__DIR__ . '/../public');
require '../app/init.php';
require '../app/models/AdsBrowserReport.php';
$db = new Database();
class BrowserReportTestStore extends AdsBrowserReport {
  public function __construct($db) { $this->db = $db; }
}
$db->query(str_replace('CREATE TABLE IF NOT EXISTS', 'CREATE TEMPORARY TABLE', file_get_contents(__DIR__ . '/../database/migrations/20260929_ads_browser_reports.sql')));
$db->exe();
try {
  $store = new BrowserReportTestStore($db);
  $shop = ['id' => 1, 'shop_id' => 137867791];
  $store->save(1, $good);
  verifyBrowser($store->forRange($shop, $range, 'product')['orders'] === 648, 'Validated report can be read by exact shop and range');
  $older = $good; $older['fetched_at'] = '2026-09-28T14:00:00Z'; $older['orders'] = 123;
  $store->save(1, $older);
  verifyBrowser($store->forRange($shop, $range, 'product')['orders'] === 648, 'Older capture does not replace newer data');
  $store->save(1, $good);
  $db->query('SELECT COUNT(*) n FROM ad_browser_reports');
  verifyBrowser((int)$db->single()['n'] === 1, 'Repeat import is idempotent');
  verifyBrowser($store->forRange(['id'=>2,'shop_id'=>137867791], $range, 'product') === null, 'Local shop isolation');
  verifyBrowser($store->forRange(['id'=>1,'shop_id'=>999], $range, 'product') === null, 'Changed remote shop isolation');
  verifyBrowser($store->forRange($shop, $range, 'shop') === null, 'Channel isolation');
  $tomorrow = $range; $tomorrow['end_date'] = '2026-09-29';
  verifyBrowser($store->forRange($shop, $tomorrow, 'product') === null, 'Old period never fills current period');
  $newer = $good; $newer['fetched_at'] = '2026-09-28T15:05:00Z'; $newer['orders'] = 650;
  $store->save(1, $newer);
  verifyBrowser($store->forRange($shop, $range, 'product')['orders'] === 650, 'Newer report replaces same-range report');
} finally {
  $db->query('DROP TEMPORARY TABLE IF EXISTS ad_browser_reports'); $db->exe();
}
echo "PASS: {$checks} browser import checks\n";
