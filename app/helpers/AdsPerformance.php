<?php

class AdsPerformance {
  public const MAPPING_VERSION = 2;
  public const ADDITIVE_FIELDS = ['impression', 'click', 'checkout', 'broad_order', 'direct_order', 'broad_order_amount', 'broad_gmv', 'direct_gmv', 'cost'];
  public const CHANNELS = [
    'product' => ['campaign_type' => 'product_homepage_v2', 'filter' => 'new_cpc_homepage', 'label' => 'Iklan produk'],
    'shop' => ['campaign_type' => 'shop_homepage', 'filter' => 'shop_homepage', 'label' => 'Iklan toko'],
    'live' => ['campaign_type' => 'live_stream_homepage', 'filter' => 'live_stream_homepage', 'label' => 'Iklan live']
  ];

  public static function range($period, ?DateTimeImmutable $now = null) {
    $now = ($now ?? new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta')))->setTimezone(new DateTimeZone('Asia/Jakarta'));
    $today = $now->setTime(0, 0);
    switch ($period) {
      case 'daily': $start = $today; $label = 'Hari ini'; break;
      case 'weekly': $start = $today->modify('-' . ((int)$today->format('N') - 1) . ' days'); $label = 'Minggu berjalan'; break;
      case 'monthly': $start = $today->modify('first day of this month'); $label = 'Bulan berjalan'; break;
      default: throw new InvalidArgumentException('Periode harus daily, weekly, atau monthly.');
    }
    return [
      'period_key' => $period,
      'label' => $label,
      'start_date' => $start->format('Y-m-d'),
      'end_date' => $today->format('Y-m-d'),
      'start_time' => $start->getTimestamp(),
      'end_time' => $today->modify('+1 day')->getTimestamp() - 1,
      'timezone' => 'Asia/Jakarta'
    ];
  }

  public static function requestBody(array $range, $channel) {
    if (!isset(self::CHANNELS[$channel])) {
      throw new InvalidArgumentException('Channel iklan tidak valid.');
    }
    return [
      'agg_interval' => ($range['end_time'] - $range['start_time'] < 86400) ? 1 : ($range['period_key'] === 'weekly' ? 12 : 96),
      'campaign_type' => self::CHANNELS[$channel]['campaign_type'],
      'start_time' => $range['start_time'],
      'end_time' => $range['end_time'],
      'need_roi_target_setting' => false,
      'filter_params' => ['campaign_type' => self::CHANNELS[$channel]['filter']]
    ];
  }

  public static function supportedMetrics($channel) {
    return $channel === 'live' ? ['sales', 'ad_cost', 'roas'] : ['impressions', 'clicks', 'ctr', 'orders', 'items_sold', 'sales', 'ad_cost', 'roas'];
  }

  public static function rawMetrics(array $raw) {
    $result = [];
    foreach (self::ADDITIVE_FIELDS as $field) {
      $value = $raw[$field] ?? null;
      $result[$field] = is_numeric($value) && $value >= 0 && $value <= PHP_INT_MAX ? (int)$value : null;
    }
    foreach (['ctr', 'broad_roi'] as $field) {
      $result[$field] = is_numeric($raw[$field] ?? null) ? (float)$raw[$field] : null;
    }
    return $result;
  }

  public static function metrics(array $raw, $channel = 'product') {
    $mapping = ['impressions' => 'impression', 'clicks' => 'click', 'orders' => 'checkout', 'items_sold' => 'broad_order_amount', 'sales' => 'broad_gmv', 'ad_cost' => 'cost'];
    $metrics = [];
    foreach ($mapping as $name => $key) {
      $value = $raw[$key] ?? null;
      $metrics[$name] = is_numeric($value) && (float)$value >= 0
        ? (in_array($name, ['sales', 'ad_cost'], true) ? (float)$value / 100000 : (int)$value)
        : null;
    }
    $metrics['ctr'] = $metrics['impressions'] > 0 && $metrics['clicks'] !== null ? $metrics['clicks'] / $metrics['impressions'] * 100 : null;
    $metrics['roas'] = $metrics['ad_cost'] > 0 && $metrics['sales'] !== null ? $metrics['sales'] / $metrics['ad_cost'] : null;
    foreach ($metrics as $field => $value) {
      if (!in_array($field, self::supportedMetrics($channel), true)) $metrics[$field] = null;
    }
    return $metrics;
  }

  public static function normalize(array $response, array $range, $channel) {
    $report = array_merge($range, [
      'available' => false,
      'mapping_version' => self::MAPPING_VERSION,
      'supported_metrics' => self::supportedMetrics($channel),
      'mapping_note' => $channel === 'live' ? 'Untuk Live, tayangan, klik, CTR, pesanan, dan produk terjual belum terverifikasi sebagai metrik yang sama dengan kartu Seller Centre. Nilai tersebut tidak ditampilkan.' : null,
      'period' => $range['start_date'] === $range['end_date'] ? $range['start_date'] : $range['start_date'] . ' s.d. ' . $range['end_date'],
      'channel' => $channel,
      'channel_label' => self::CHANNELS[$channel]['label'],
      'attribution' => ['orders' => 'checkout', 'items_sold' => 'broad_order_amount', 'sales' => 'broad_gmv'],
      'currency' => 'IDR',
      'source' => '/api/pas/v1/report/get_time_graph/',
      'fetched_at' => null,
      'attempted_at' => gmdate('c'),
      'error_code' => null,
      'error_message' => null,
      'daily' => [],
      'stale' => false
    ], self::metrics([], $channel));
    $aggregate = $response['data']['report_aggregate'] ?? null;
    if (!array_key_exists('code', $response) || (int)$response['code'] !== 0 || !empty($response['error']) || !is_array($aggregate) || $aggregate === []) {
      $code = $response['error'] ?? $response['code'] ?? null;
      $report['error_code'] = is_numeric($code) ? (int)$code : null;
      $report['error_message'] = $report['error_code']
        ? 'Laporan iklan ditolak Shopee (kode ' . $report['error_code'] . '). Data periode ini belum dapat diperbarui.'
        : 'Respons laporan iklan tidak tersedia atau tidak lengkap. Data periode ini belum dapat diperbarui.';
      return $report;
    }
    $report = array_merge($report, self::metrics($aggregate, $channel));
    $report['raw_metrics'] = self::rawMetrics($aggregate);
    $report['available'] = true;
    $report['fetched_at'] = gmdate('c');
    $fields = self::ADDITIVE_FIELDS;
    $days = [];
    foreach (($response['data']['report_by_time'] ?? []) as $point) {
      if (!is_numeric($point['key'] ?? null) || !is_array($point['metrics'] ?? null)) continue;
      $timestamp = (int)$point['key'];
      if ($timestamp < $range['start_time'] || $timestamp > $range['end_time'] || $timestamp > time()) continue;
      $date = (new DateTimeImmutable('@' . $timestamp))->setTimezone(new DateTimeZone('Asia/Jakarta'))->format('Y-m-d');
      if (!isset($days[$date])) $days[$date] = array_fill_keys($fields, 0);
      foreach ($fields as $field) {
        $value = $point['metrics'][$field] ?? null;
        if (!is_numeric($value) || (float)$value < 0) $days[$date][$field] = null;
        elseif ($days[$date][$field] !== null) $days[$date][$field] += (int)$value;
      }
    }
    ksort($days);
    foreach ($days as $date => $raw) {
      $report['daily'][] = array_merge(['date' => $date, 'raw_metrics' => self::rawMetrics($raw)], self::metrics($raw, $channel));
    }
    $report['detail_available'] = count($report['daily']) > 0;
    $report['reconciliation'] = ['status' => 'unavailable', 'differences' => []];
    $complete = $report['detail_available'];
    foreach (['impression', 'click', 'checkout', 'broad_order_amount', 'broad_gmv', 'cost'] as $field) {
      $values = array_column($days, $field);
      if ($report['raw_metrics'][$field] === null || !$values || in_array(null, $values, true)) { $complete = false; continue; }
      $difference = array_sum($values) - $report['raw_metrics'][$field];
      if ($difference !== 0) $report['reconciliation']['differences'][$field] = $difference;
    }
    $report['reconciliation']['status'] = $report['reconciliation']['differences'] ? 'mismatch' : ($complete ? 'matched' : 'unavailable');
    return $report;
  }

  public static function retainLastGood(array $report, ?array $previous) {
    if (!$report['available'] && !empty($previous['available'])
      && ($previous['mapping_version'] ?? null) === self::MAPPING_VERSION
      && !empty($report['source_shop_id']) && ($previous['source_shop_id'] ?? null) === $report['source_shop_id']
      && ($previous['start_date'] ?? '') === $report['start_date']
      && ($previous['end_date'] ?? '') === $report['end_date']
      && ($previous['channel'] ?? '') === $report['channel']) {
      return array_merge($previous, [
        'stale' => true,
        'error_code' => $report['error_code'],
        'error_message' => $report['error_message'],
        'attempted_at' => $report['attempted_at'],
        'request_state' => $report['request_state'] ?? null,
        'diagnostics' => $report['diagnostics'] ?? null
      ]);
    }
    return $report;
  }
}
