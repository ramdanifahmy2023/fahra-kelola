<?php
require __DIR__ . '/../app/helpers/AdsCampaignReports.php';
require __DIR__ . '/../app/models/ShopeeCurl.php';

$checks = 0;
function campaignCheck($condition, $message) {
  global $checks;
  $checks++;
  if (!$condition) throw new RuntimeException($message);
}
function campaignEntry($id, $n = 1) {
  return ['campaign' => ['campaign_id' => $id], 'report' => [
    'impression' => 100 * $n, 'click' => 10 * $n, 'checkout' => $n,
    'broad_order' => 2 * $n, 'direct_order' => $n, 'broad_order_amount' => 3 * $n,
    'broad_gmv' => 10000000 * $n, 'direct_gmv' => 9000000 * $n, 'cost' => 1000000 * $n,
    'ctr' => 99, 'broad_roi' => 99
  ]];
}
function campaignPage(array $entries, $total) {
  return ['code' => 0, 'data' => ['has_report_failure' => false, 'total' => $total, 'entry_list' => $entries]];
}
class CampaignClientStub extends ShopeeCurl {
  public $responses = [];
  public $calls = [];
  public $status = 200;
  public function request($method, $endpoint, $cookie, $data = [], $customHeaders = []) {
    $this->calls[] = ['path' => parse_url($endpoint, PHP_URL_PATH), 'body' => $data, 'headers' => $customHeaders];
    return array_shift($this->responses) ?? campaignPage([], 0);
  }
  public function getLastRequestDiagnostics() { return ['http_status' => $this->status]; }
}
$date = new DateTimeImmutable('2026-09-29T11:00:00+07:00');
$range = AdsPerformance::range('daily', $date);
$client = new CampaignClientStub();
$collector = new AdsCampaignReports($client, 0);
$client->responses = [campaignPage([campaignEntry(1), campaignEntry(2, 2)], 3), campaignPage([campaignEntry(3, 3)], 3)];
$good = $collector->fetch('SPC_CDS=fixture', $range, 'product');
campaignCheck($good['available'] && $good['campaign_count'] === 3 && $good['pages_fetched'] === 2, 'All pages collected');
campaignCheck($client->calls[1]['body']['offset'] === 2, 'Offset follows consumed entries');
campaignCheck($client->calls[0]['body']['filter_list'][0]['campaign_type'] === 'product_homepage_v3', 'Product list uses captured v3 type');
campaignCheck($client->calls[0]['headers'] === [], 'Uses the same basic transport as orders');
campaignCheck($good['orders'] === 6 && $good['items_sold'] === 18, 'Checkout and item counts remain distinct');
campaignCheck($good['sales'] === 600.0 && $good['ad_cost'] === 60.0, 'Money normalized once');
campaignCheck($good['ctr'] === 10.0 && $good['roas'] === 10.0, 'Ratios recalculated from totals');
campaignCheck(count($good['daily']) === 1 && $good['reconciliation']['status'] === 'matched', 'Single-day report has valid daily detail');
campaignCheck($good['source'] === AdsCampaignReports::PATH && $good['collection_method'] === 'cookie_campaign', 'Source is explicit');
campaignCheck(!str_contains(json_encode($good), 'fixture'), 'Credentials excluded from output');
$monthly = $collector->fetch('SPC_CDS=fixture', AdsPerformance::range('monthly', $date), 'product');
campaignCheck($monthly['available'] && $monthly['daily'] === [] && !$monthly['detail_available'], 'Period total does not fabricate daily detail');
campaignCheck($monthly['ad_cost'] === 0.0 && $monthly['roas'] === null, 'Verified empty campaign list is zero, ratio undefined');

$cases = [];
$cases['later page failure'] = [campaignPage([campaignEntry(1)], 2), ['error' => 90309999]];
$cases['duplicate campaign'] = [campaignPage([campaignEntry(1)], 2), campaignPage([campaignEntry(1)], 2)];
$cases['total changed'] = [campaignPage([campaignEntry(1)], 2), campaignPage([campaignEntry(2)], 3)];
$cases['early empty page'] = [campaignPage([], 1)];
$cases['too many records'] = [campaignPage([campaignEntry(1)], 0)];
$bad = campaignPage([campaignEntry(1)], 1); $bad['data']['has_report_failure'] = true; $cases['report failure flag'] = [$bad];
$bad = campaignPage([], 0); unset($bad['data']['has_report_failure']); $cases['missing report flag'] = [$bad];
$bad = campaignPage([campaignEntry(1)], 1); unset($bad['data']['entry_list'][0]['report']['cost']); $cases['missing cost'] = [$bad];
$bad = campaignPage([campaignEntry(1)], 1); $bad['data']['entry_list'][0]['report']['impression'] = -1; $cases['negative count'] = [$bad];
$bad = campaignPage([campaignEntry(1)], 1); unset($bad['data']['entry_list'][0]['campaign']); $cases['missing ID'] = [$bad];
$bad = campaignPage([campaignEntry(1)], 1); $bad['data']['entry_list'][0]['report']['cost'] = '100'; $cases['wrong metric type'] = [$bad];
$bad = campaignPage([campaignEntry(1),campaignEntry(2)], 2); $bad['data']['entry_list'][0]['report']['cost'] = PHP_INT_MAX; $cases['sum overflow'] = [$bad];
foreach ($cases as $name => $responses) {
  $client->responses = $responses;
  $failed = $collector->fetch('SPC_CDS=fixture', $range, 'product');
  campaignCheck(!$failed['available'] && $failed['ad_cost'] === null && $failed['daily'] === [], $name . ' cannot publish partial totals');
}
$client->responses = [campaignPage([campaignEntry(1)], 2)];
campaignCheck(!(new AdsCampaignReports($client, 0, 1))->fetch('SPC_CDS=fixture', $range, 'product')['available'], 'Page bound fails closed');
$client->calls = [];
campaignCheck(!$collector->fetch('SPC_CDS=fixture', $range, 'product', microtime(true) - 1)['available'] && !$client->calls, 'Time bound skips requests');
campaignCheck(!$collector->fetch('', $range, 'product')['available'] && !$client->calls, 'Missing cookie skips requests');
$client->responses = []; $client->status = 403;
campaignCheck(!$collector->fetch('SPC_CDS=fixture', $range, 'product')['available'], 'HTTP error is rejected even with code zero');
$client->status = 200; $client->calls = []; $client->responses = [['error' => 90309999]];
$all = $collector->all('SPC_CDS=fixture', $date);
campaignCheck(count($client->calls) === 1 && $all['monthly']['live']['request_state'] === 'skipped', 'Access rejection stops remaining requests');
$client->calls = []; $client->responses = [];
$all = $collector->all('SPC_CDS=fixture', new DateTimeImmutable('2026-06-01T11:00:00+07:00'));
campaignCheck(count($client->calls) === 3 && $all['monthly']['product']['request_state'] === 'reused', 'Identical calendar ranges reuse requests');
campaignCheck($client->calls[1]['body']['filter_list'][0]['campaign_type'] === 'shop_homepage' && $client->calls[2]['body']['filter_list'][0]['campaign_type'] === 'live_stream_homepage', 'Each channel uses captured filter');
$client->responses = [campaignPage([campaignEntry(1)], 1)];
$live = $collector->fetch('SPC_CDS=fixture', $range, 'live');
campaignCheck($live['sales'] === 100.0 && $live['orders'] === null, 'Live preserves existing verified metric scope');
$good['source_shop_id'] = 10; $failed['source_shop_id'] = 10;
$retained = AdsPerformance::retainLastGood($failed, $good);
campaignCheck($retained['available'] && $retained['stale'] && $retained['orders'] === 6, 'Failed collection retains previous successful report');
$failed['source_shop_id'] = 11;
campaignCheck(!AdsPerformance::retainLastGood($failed, $good)['available'], 'Changed shop identity cannot retain another shop report');
$client->calls = []; $client->responses = [['code' => 0, 'data' => ['ads_credit' => ['total' => 100000]]]];
$summary = $client->getAdsSummary('SPC_CDS=fixture');
campaignCheck(count($client->calls) > 1 && $client->calls[1]['path'] === AdsCampaignReports::PATH, 'Production summary uses campaign collector');
campaignCheck(!in_array('/api/pas/v1/report/get_time_graph/', array_column($client->calls, 'path'), true), 'Production sync never falls back to blocked graph endpoint');
echo "PASS: {$checks} campaign report checks\n";
