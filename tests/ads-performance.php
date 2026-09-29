<?php

require_once __DIR__ . '/../app/helpers/AdsPerformance.php';
require_once __DIR__ . '/../app/models/ShopeeCurl.php';

$checks = 0;
function check($condition, $message) {
  global $checks;
  $checks++;
  if (!$condition) throw new RuntimeException($message);
}

$now = new DateTimeImmutable('2026-09-28T21:00:00+07:00');
check(AdsPerformance::range('daily', $now)['start_date'] === '2026-09-28', 'Daily uses Jakarta date');
check(AdsPerformance::range('weekly', $now)['start_date'] === '2026-09-28', 'Week starts Monday');
check(AdsPerformance::range('monthly', $now)['start_date'] === '2026-09-01', 'Month starts on first');
check(AdsPerformance::range('daily', new DateTimeImmutable('2026-09-30T18:00:00Z'))['start_date'] === '2026-10-01', 'UTC midnight boundary');
check(AdsPerformance::range('weekly', new DateTimeImmutable('2027-01-01T12:00:00+07:00'))['start_date'] === '2026-12-28', 'Week crosses year');
check(AdsPerformance::range('monthly', new DateTimeImmutable('2028-02-29T12:00:00+07:00'))['end_date'] === '2028-02-29', 'Leap day');
try { AdsPerformance::range('invalid'); check(false, 'Reject period'); } catch (InvalidArgumentException $e) { check(true, 'Reject period'); }

$fixtures = json_decode(file_get_contents(__DIR__ . '/fixtures/ads-reports.json'), true, 512, JSON_THROW_ON_ERROR);
foreach ($fixtures as $fixture) {
  $request = $fixture['request'];
  $period = $request['agg_interval'] === 1 ? 'daily' : ($request['agg_interval'] === 12 ? 'weekly' : 'monthly');
  $range = array_merge(AdsPerformance::range($period, $now), ['start_time' => $request['start_time'], 'end_time' => $request['end_time']]);
  foreach (['start', 'end'] as $edge) {
    $range[$edge . '_date'] = (new DateTimeImmutable('@' . $range[$edge . '_time']))->setTimezone(new DateTimeZone('Asia/Jakarta'))->format('Y-m-d');
  }
  $channel = array_search($request['campaign_type'], array_column(AdsPerformance::CHANNELS, 'campaign_type', null), true);
  $channel = array_keys(AdsPerformance::CHANNELS)[$channel];
  $report = AdsPerformance::normalize($fixture['response'], $range, $channel);
  $raw = $fixture['response']['data']['report_aggregate'];
  check($report['available'], 'Capture ' . $fixture['capture_id'] . ' available');
  check($report['orders'] === ($channel === 'live' ? null : $raw['checkout']), 'Orders use checkout only on verified channels');
  check($report['items_sold'] === ($channel === 'live' ? null : $raw['broad_order_amount']), 'Products use quantities on verified channels');
  check(abs($report['sales'] - $raw['broad_gmv'] / 100000) < 0.00001, 'Money retains precision');
  foreach (['impressions', 'clicks', 'items_sold', 'sales', 'ad_cost'] as $field) {
    if ($report[$field] === null) continue;
    check(abs(array_sum(array_column($report['daily'], $field)) - $report[$field]) < 0.00001, 'Daily sums match captured aggregate: ' . $field);
  }
  check(count(array_filter(array_column($report['daily'], 'orders'), static function ($value) { return $value !== null; })) === 0, 'Older projections lack checkout; daily orders stay null');
  if ($raw['impression'] > 0) check(abs($report['ctr'] - $raw['ctr'] * 100) < 0.00001, 'CTR matches capture');
  if ($raw['cost'] > 0) check(abs($report['roas'] - $raw['broad_roi']) < 0.00001, 'ROAS matches capture');
  check(AdsPerformance::requestBody($range, $channel) === $request, 'Request matches captured contract');
}

$zero = AdsPerformance::metrics(['impression' => 0, 'click' => 0, 'cost' => 0, 'broad_gmv' => 0, 'checkout' => 0, 'broad_order' => 0, 'broad_order_amount' => 0]);
check($zero['impressions'] === 0 && $zero['sales'] === 0.0, 'Real zero retained');
check($zero['ctr'] === null && $zero['roas'] === null, 'Zero denominator is undefined');
check(AdsPerformance::metrics([])['sales'] === null, 'Missing metric is not zero');
check(AdsPerformance::metrics(['direct_order' => 100])['orders'] === null, 'Never substitute direct attribution');
$range = AdsPerformance::range('daily', $now);
$good = AdsPerformance::normalize($fixtures[0]['response'], $range, 'product');
$failed = AdsPerformance::normalize(['error' => 90309999], $range, 'product');
$good['source_shop_id'] = $failed['source_shop_id'] = 137867791;
check(!$failed['available'] && $failed['impressions'] === null && $failed['error_code'] === 90309999, 'Shopee rejection is unavailable');
check(!AdsPerformance::normalize(['code' => 1, 'data' => ['report_aggregate' => ['impression' => 999]]], $range, 'product')['available'], 'Nonzero response code rejects data');
check(!AdsPerformance::normalize(['code' => 0, 'data' => ['report_aggregate' => []]], $range, 'product')['available'], 'Empty aggregate is unavailable');
$retained = AdsPerformance::retainLastGood($failed, $good);
check($retained['available'] && $retained['stale'] && $retained['fetched_at'] === $good['fetched_at'], 'Failure preserves old data and time');
check($retained['error_code'] === 90309999, 'Retained data exposes last error');
$oldMapping = $good; $oldMapping['mapping_version'] = 1;
check(!AdsPerformance::retainLastGood($failed, $oldMapping)['available'], 'Old broad_order mapping cannot be reused');
$wrongShop = $good; $wrongShop['source_shop_id'] = 999;
check(!AdsPerformance::retainLastGood($failed, $wrongShop)['available'], 'Different remote shop cannot reuse data');
$good['end_date'] = '2026-09-27';
check(!AdsPerformance::retainLastGood($failed, $good)['available'], 'Previous day cannot fill current day');

class AdsReportStub extends ShopeeCurl {
  public $requests = [];
  public $response;
  public function request($method, $endpoint, $cookie, $data = [], $headers = []) {
    $this->requests[] = $data;
    return $this->response;
  }
}
$stub = new AdsReportStub();
$stub->response = $fixtures[0]['response'];
$reports = $stub->getAdsPerformanceReports('SPC_CDS=test-only', $now);
check(count($stub->requests) === 6 && count($reports['monthly']) === 3, 'Monday uses six distinct requests for nine reports');
check($reports['weekly']['product']['request_state'] === 'reused', 'Equivalent daily/weekly report reused');
check($stub->requests[0]['agg_interval'] === 1, 'One-day interval is 1');
$stub->requests = [];
$stub->response = ['error' => 90309999];
$reports = $stub->getAdsPerformanceReports('SPC_CDS=test-only', $now);
check(count($stub->requests) === 1, 'Stop redundant requests after access rejection');
check(!$reports['monthly']['live']['available'], 'Rejection covers remaining reports');
check($reports['monthly']['live']['request_state'] === 'skipped' && $reports['monthly']['live']['attempted_at'] === null, 'Skipped request is not reported as sent');
$stub->requests = [];
$stub->getAdsPerformanceReports('');
check(count($stub->requests) === 0, 'Missing session token never sent');

$auditRoot = __DIR__ . '/../ops/ads-network-2026-09-28/';
$range = AdsPerformance::range('monthly', $now);
foreach (['product' => 648, 'shop' => 21, 'live' => null] as $channel => $orders) {
  $response = json_decode(file_get_contents($auditRoot . $channel . '-monthly.response.projection.json'), true, 512, JSON_THROW_ON_ERROR);
  $report = AdsPerformance::normalize($response, $range, $channel);
  check($report['orders'] === $orders, 'Browser verified orders: ' . $channel);
  check(count($report['daily']) === 28, 'All 28 daily buckets retained');
  check($report['reconciliation']['status'] === 'matched', 'Raw additive fields reconcile exactly');
  foreach (['checkout', 'broad_order', 'broad_order_amount', 'impression', 'click', 'cost', 'broad_gmv'] as $field) {
    check(array_sum(array_map(static function ($row) use ($field) { return $row['raw_metrics'][$field]; }, $report['daily'])) === $report['raw_metrics'][$field], 'Exact integer total: ' . $field);
  }
  if ($channel === 'product') {
    check($report['raw_metrics']['broad_order'] === 756, 'Broad orders preserved separately');
    check(round($report['sales']) === 45026885.0 && round($report['ad_cost']) === 4376033.0, 'Monthly money matches browser');
    $last = $report['daily'][27];
    check($last['orders'] === 30 && $last['items_sold'] === 38, 'Daily checkout and units match');
    check(round($last['sales']) === 1684590.0 && round($last['ad_cost']) === 163050.0, 'Daily money matches second capture');
    $response['data']['report_by_time'][0]['metrics']['checkout']++;
    $mismatch = AdsPerformance::normalize($response, $range, $channel);
    check($mismatch['reconciliation']['status'] === 'mismatch' && $mismatch['orders'] === 648, 'Mismatch does not overwrite official aggregate');
  }
  if ($channel === 'live') {
    check($report['available'] && $report['impressions'] === null && $report['ctr'] === null, 'Live request succeeds without implying verified product metrics');
    check($report['raw_metrics']['ctr'] === 0.0 && $report['raw_metrics']['broad_roi'] === 0.0 && $report['roas'] === null, 'Zero API ratios preserved separately from undefined computed ratio');
  }
}
$firstAudit = json_decode(file_get_contents(__DIR__ . '/../ops/ads-browser-20260928/product-monthly.response.sanitized.json'), true);
$verified = AdsPerformance::normalize($firstAudit, $range, 'product');
check($verified['orders'] === 648 && $verified['raw_metrics']['broad_order'] === 756 && $verified['raw_metrics']['direct_order'] === 664, 'Checkout/broad/direct stay distinct');
check($verified['daily'][27]['orders'] === 30 && round($verified['daily'][27]['ad_cost']) === 163333.0, 'First capture values kept distinct from second capture');
check(AdsPerformance::metrics(['broad_order' => 756, 'direct_order' => 664])['orders'] === null, 'Missing checkout never falls back');
foreach (json_decode(file_get_contents($auditRoot . 'requests.sanitized.json'), true) as $captured) {
  $expected = $captured['body']; unset($expected['device_sz_fingerprint']);
  check(AdsPerformance::requestBody(AdsPerformance::range($captured['period'], $now), $captured['channel']) === $expected, 'Business body matches browser: ' . $captured['period'] . '/' . $captured['channel']);
}
echo "PASS: {$checks} checks\n";
