<?php

require_once __DIR__ . '/AdsPerformance.php';

class AdsBrowserCapture {
  private static function timestamp($value) {
    if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/', $value)) {
      throw new InvalidArgumentException('Waktu capture wajib ISO 8601 dengan zona waktu.');
    }
    try { $time = new DateTimeImmutable($value); }
    catch (Exception $error) { throw new InvalidArgumentException('Waktu capture tidak valid.'); }
    if (DateTimeImmutable::getLastErrors() !== false) throw new InvalidArgumentException('Waktu capture tidak valid.');
    if ($time->getTimestamp() > time() + 60) throw new InvalidArgumentException('Waktu capture berada di masa depan.');
    return $time;
  }

  public static function normalize(array $bundle, $expectedShopId) {
    if (($bundle['version'] ?? null) !== 1 || ($bundle['source'] ?? '') !== 'seller-centre-browser') {
      throw new InvalidArgumentException('Format capture browser tidak dikenal.');
    }
    $observations = [];
    foreach (['identity_before', 'report', 'identity_after'] as $key) {
      $observation = $bundle[$key] ?? null;
      if (!is_array($observation) || !is_string($observation['context_id'] ?? null)
        || !preg_match('/^[a-zA-Z0-9._:-]{1,80}$/', $observation['context_id'])
        || !is_string($observation['request_id'] ?? null)
        || !preg_match('/^[a-zA-Z0-9._:-]{1,80}$/', $observation['request_id'])
        || ($observation['http_status'] ?? null) !== 200) {
        throw new InvalidArgumentException('Metadata observasi browser tidak lengkap.');
      }
      $observation['time'] = self::timestamp($observation['observed_at'] ?? null);
      $observations[$key] = $observation;
    }
    $before = $observations['identity_before'];
    $capture = $observations['report'];
    $after = $observations['identity_after'];
    if ($before['context_id'] !== $capture['context_id'] || $after['context_id'] !== $capture['context_id']
      || $before['time'] > $capture['time'] || $capture['time'] > $after['time']
      || $after['time']->getTimestamp() - $before['time']->getTimestamp() > 600
      || count(array_unique([$before['request_id'], $capture['request_id'], $after['request_id']])) !== 3) {
      throw new InvalidArgumentException('Laporan harus diapit pemeriksaan identitas pada konteks browser yang sama dalam 10 menit.');
    }
    foreach ([$before, $after] as $identity) {
      if (($identity['path'] ?? '') !== '/api/v2/login/' || ($identity['response']['errcode'] ?? null) !== 0
        || !is_int($identity['response']['shopid'] ?? null) || $expectedShopId < 1
        || $identity['response']['shopid'] !== (int)$expectedShopId) {
        throw new InvalidArgumentException('Identitas browser tidak cocok dengan toko tujuan.');
      }
    }
    if (($capture['path'] ?? '') !== '/api/pas/v1/report/get_time_graph/' || ($capture['method'] ?? '') !== 'POST') {
      throw new InvalidArgumentException('Endpoint laporan tidak sesuai.');
    }
    $body = $capture['request'] ?? [];
    $channel = null;
    foreach (AdsPerformance::CHANNELS as $key => $config) {
      if (($body['campaign_type'] ?? '') === $config['campaign_type']
        && ($body['filter_params'] ?? null) === ['campaign_type' => $config['filter']]) $channel = $key;
    }
    $range = null;
    foreach (['daily', 'weekly', 'monthly'] as $period) {
      $candidate = AdsPerformance::range($period, $capture['time']);
      if (($body['start_time'] ?? null) === $candidate['start_time'] && ($body['end_time'] ?? null) === $candidate['end_time']) {
        $range = $candidate;
        break;
      }
    }
    if ($channel === null || $range === null || ($body['need_roi_target_setting'] ?? null) !== false
      || !in_array($body['agg_interval'] ?? null, [1, 4, 12, 96], true)) {
      throw new InvalidArgumentException('Jenis, rentang kalender, atau interval laporan tidak didukung.');
    }
    $response = $capture['response'] ?? [];
    $aggregate = $response['data']['report_aggregate'] ?? null;
    $points = $response['data']['report_by_time'] ?? null;
    if (($response['code'] ?? null) !== 0 || !empty($response['error']) || !is_array($aggregate)
      || !is_array($points) || !array_is_list($points) || !$points || count($points) > 10000) {
      throw new InvalidArgumentException('Respons browser gagal atau tidak memuat rincian laporan.');
    }
    $required = ['impression', 'click', 'checkout', 'broad_order_amount', 'broad_gmv', 'cost'];
    $project = static function (array $metrics) use ($required) {
      foreach ($required as $field) {
        if (!is_int($metrics[$field] ?? null) || $metrics[$field] < 0) {
          throw new InvalidArgumentException('Metrik laporan wajib berupa integer nonnegatif.');
        }
      }
      return AdsPerformance::rawMetrics($metrics);
    };
    $safe = ['code' => 0, 'data' => ['report_aggregate' => $project($aggregate), 'report_by_time' => []]];
    $keys = [];
    $totals = array_fill_keys($required, 0);
    foreach ($points as $point) {
      $key = $point['key'] ?? null;
      if ((!is_int($key) && !(is_string($key) && ctype_digit($key))) || !is_array($point['metrics'] ?? null)
        || (int)$key < $range['start_time'] || (int)$key > $range['end_time'] || isset($keys[(string)$key])) {
        throw new InvalidArgumentException('Titik waktu laporan tidak valid atau berulang.');
      }
      $keys[(string)$key] = true;
      $metrics = $project($point['metrics']);
      foreach ($required as $field) $totals[$field] += $metrics[$field];
      $safe['data']['report_by_time'][] = ['key' => (string)$key, 'metrics' => $metrics];
    }
    foreach ($required as $field) {
      if ($totals[$field] !== $aggregate[$field]) throw new InvalidArgumentException('Jumlah rincian berbeda dari aggregate laporan.');
    }
    $report = AdsPerformance::normalize($safe, $range, $channel);
    $report['fetched_at'] = $report['attempted_at'] = $capture['time']->setTimezone(new DateTimeZone('UTC'))->format('c');
    $report['source_shop_id'] = (int)$expectedShopId;
    $report['collection_method'] = 'browser_capture';
    $report['request_state'] = 'browser_observed';
    $report['provenance'] = [
      'context_id' => $capture['context_id'],
      'identity_before_request' => $before['request_id'],
      'report_request' => $capture['request_id'],
      'identity_after_request' => $after['request_id']
    ];
    return $report;
  }
}
