<?php

require_once __DIR__ . '/AdsPerformance.php';

class AdsCampaignReports {
  public const PATH = '/api/pas/v1/homepage/query/';
  private const TYPES = ['product' => 'product_homepage_v3', 'shop' => 'shop_homepage', 'live' => 'live_stream_homepage'];
  private const REQUIRED = ['impression', 'click', 'checkout', 'broad_order_amount', 'broad_gmv', 'cost'];
  private $client;
  private $rateMs;
  private $maxPages;

  public function __construct($client, $rateMs = 350, $maxPages = 100) {
    $this->client = $client;
    $this->rateMs = max(0, (int)$rateMs);
    $this->maxPages = max(1, (int)$maxPages);
  }

  private function failure(array $range, $channel, $message, $code = null, $state = 'sent') {
    $report = AdsPerformance::normalize([], $range, $channel);
    $report['source'] = self::PATH;
    $report['collection_method'] = 'cookie_campaign';
    $report['request_state'] = $state;
    $report['error_message'] = $message;
    $report['error_code'] = $code;
    if ($state === 'skipped') $report['attempted_at'] = null;
    return $report;
  }

  public function all($cookie, ?DateTimeImmutable $now = null) {
    $now = $now ?? new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta'));
    $reports = [];
    $cache = [];
    $blocked = null;
    $deadline = microtime(true) + 120;
    foreach (['daily', 'weekly', 'monthly'] as $period) {
      $range = AdsPerformance::range($period, $now);
      foreach (self::TYPES as $channel => $type) {
        $key = $channel . ':' . $range['start_date'] . ':' . $range['end_date'];
        if (isset($cache[$key])) {
          $report = array_merge($cache[$key], $range);
          $report['request_state'] = 'reused';
        } elseif ($blocked !== null) {
          $report = $this->failure($range, $channel, 'Pengambilan laporan berikutnya ditunda: ' . $blocked['error_message'], $blocked['error_code'], 'skipped');
        } else {
          $report = $this->fetch($cookie, $range, $channel, $deadline);
          $cache[$key] = $report;
          if (!empty($report['stop_batch'])) $blocked = $report;
        }
        $reports[$period][$channel] = $report;
      }
    }
    return $reports;
  }

  public function fetch($cookie, array $range, $channel, $deadline = null) {
    if (!isset(self::TYPES[$channel])) throw new InvalidArgumentException('Channel iklan tidak valid.');
    if (!preg_match('/(?:^|;\s*)SPC_CDS=([^;]+)/', $cookie, $token)) {
      return $this->failure($range, $channel, 'SPC_CDS tidak tersedia. Perbarui cookie toko.', null, 'skipped') + ['stop_batch' => true];
    }
    $deadline = $deadline ?? microtime(true) + 120;
    $started = gmdate('c');
    $sum = array_fill_keys(AdsPerformance::ADDITIVE_FIELDS, 0);
    $seen = [];
    $total = null;
    $offset = 0;
    $url = 'https://seller.shopee.co.id' . self::PATH . '?SPC_CDS=' . urlencode($token[1]) . '&SPC_CDS_VER=2';
    for ($page = 0; $page < $this->maxPages; $page++) {
      if (microtime(true) >= $deadline) {
        return $this->failure($range, $channel, 'Batas waktu pengambilan campaign tercapai. Laporan parsial tidak disimpan.', null, $page ? 'sent' : 'skipped') + ['stop_batch' => true];
      }
      if ($page && $this->rateMs) usleep($this->rateMs * 1000);
      $body = [
        'start_time' => $range['start_time'], 'end_time' => $range['end_time'],
        'filter_list' => [['campaign_type' => self::TYPES[$channel], 'state' => 'all', 'search_term' => '', 'is_valid_rebate_only' => false]],
        'offset' => $offset, 'limit' => 20
      ];
      $response = $this->client->request('POST', $url, $cookie, $body);
      $diagnostics = $this->client->getLastRequestDiagnostics();
      $http = (int)($diagnostics['http_status'] ?? 0);
      $code = $response['error'] ?? $response['code'] ?? null;
      if ($http !== 200 || ($response['code'] ?? null) !== 0 || !empty($response['error'])) {
        $code = is_numeric($code) ? (int)$code : null;
        return $this->failure($range, $channel, 'Pengambilan campaign gagal (HTTP ' . $http . ($code !== null ? ', kode ' . $code : '') . '). Laporan parsial tidak disimpan.', $code)
          + ['stop_batch' => in_array($http, [0, 401, 403, 429], true) || $http >= 500 || $code === 90309999];
      }
      $data = $response['data'] ?? [];
      if (($data['has_report_failure'] ?? null) !== false || !is_int($data['total'] ?? null) || $data['total'] < 0
        || !is_array($data['entry_list'] ?? null) || !array_is_list($data['entry_list']) || count($data['entry_list']) > 20) {
        return $this->failure($range, $channel, 'Respons campaign tidak lengkap atau Shopee melaporkan kegagalan metrik.');
      }
      if ($total !== null && $total !== $data['total']) return $this->failure($range, $channel, 'Jumlah campaign berubah saat pengambilan. Laporan parsial tidak disimpan.');
      $total = $data['total'];
      foreach ($data['entry_list'] as $entry) {
        $id = $entry['campaign']['campaign_id'] ?? $entry['campaign']['id'] ?? null;
        if (!is_int($id) || $id <= 0 || isset($seen[$id]) || !is_array($entry['report'] ?? null)) {
          return $this->failure($range, $channel, 'Campaign tidak valid atau berulang. Laporan parsial tidak disimpan.');
        }
        $seen[$id] = true;
        foreach (AdsPerformance::ADDITIVE_FIELDS as $field) {
          $value = $entry['report'][$field] ?? null;
          if (!is_int($value) || $value < 0) {
            if (in_array($field, self::REQUIRED, true)) return $this->failure($range, $channel, 'Metrik campaign tidak lengkap. Laporan parsial tidak disimpan.');
            $sum[$field] = null;
          } elseif ($sum[$field] !== null) {
            if ($value > PHP_INT_MAX - $sum[$field]) return $this->failure($range, $channel, 'Nilai metrik campaign melebihi batas.');
            $sum[$field] += $value;
          }
        }
      }
      $offset += count($data['entry_list']);
      if ($offset > $total || ($offset < $total && !$data['entry_list'])) return $this->failure($range, $channel, 'Pagination campaign tidak lengkap.');
      if ($offset === $total) {
        $points = $range['start_date'] === $range['end_date'] ? [['key' => $range['start_time'], 'metrics' => $sum]] : [];
        $report = AdsPerformance::normalize(['code' => 0, 'data' => ['report_aggregate' => $sum, 'report_by_time' => $points]], $range, $channel);
        $report['source'] = self::PATH;
        $report['collection_method'] = 'cookie_campaign';
        $report['request_state'] = 'sent';
        $report['collection_started_at'] = $started;
        $report['campaign_count'] = $total;
        $report['pages_fetched'] = $page + 1;
        $report['detail_message'] = $points ? null : 'Ringkasan berasal dari seluruh campaign untuk periode ini. Rincian per tanggal belum diambil.';
        return $report;
      }
    }
    return $this->failure($range, $channel, 'Batas halaman campaign tercapai. Laporan parsial tidak disimpan.');
  }
}
