<?php
require_once __DIR__.'/AdsPerformance.php';

class FinanceAdCost {
  public static function summarize(array $reports, array $shop, array $range): array {
    $period=[]; $daily=[];
    foreach ($reports as $report) {
      $channel=$report['channel'] ?? '';
      if (!isset(AdsPerformance::CHANNELS[$channel]) || empty($report['available'])
        || ($report['mapping_version'] ?? null)!==AdsPerformance::MAPPING_VERSION
        || (string)($report['source_shop_id'] ?? '')!==(string)$shop['shop_id']
        || ($report['currency'] ?? '')!=='IDR' || ($report['timezone'] ?? '')!=='Asia/Jakarta') continue;
      $time=strtotime($report['fetched_at'] ?? '');
      if (!$time) continue;
      $value=$report['raw_metrics']['cost'] ?? null;
      $point=['amount'=>$value,'time'=>$time,'stale'=>!empty($report['stale']) || !empty($report['error_message'])];
      if (($report['start_date'] ?? '')===$range['start'] && ($report['end_date'] ?? '')===$range['end']
        && is_numeric($value) && $value>=0 && $value<=PHP_INT_MAX
        && (!isset($period[$channel]) || $time>$period[$channel]['time'])) $period[$channel]=$point+['basis'=>'period'];
      foreach (($report['daily'] ?? []) as $day) {
        $date=$day['date'] ?? ''; $value=$day['raw_metrics']['cost'] ?? null;
        if ($date<$range['start'] || $date>$range['end'] || $date<($report['start_date'] ?? '') || $date>($report['end_date'] ?? '')
          || !preg_match('/^\d{4}-\d{2}-\d{2}$/D',$date) || !is_numeric($value) || $value<0 || $value>PHP_INT_MAX) continue;
        if (!isset($daily[$channel][$date]) || $time>$daily[$channel][$date]['time']) $daily[$channel][$date]=array_replace($point,['amount'=>$value]);
      }
    }
    foreach (AdsPerformance::CHANNELS as $channel=>$_) {
      $points=$daily[$channel] ?? [];
      if (count($points)!==$range['days']) continue;
      $time=min(array_column($points,'time'));
      if (!isset($period[$channel]) || $time>$period[$channel]['time']) $period[$channel]=[
        'amount'=>array_sum(array_column($points,'amount')),'time'=>$time,'stale'=>in_array(true,array_column($points,'stale'),true),'basis'=>'daily'
      ];
    }
    if (count($period)===count(AdsPerformance::CHANNELS)) return self::result($period,$range['days'],in_array('period',array_column($period,'basis'),true) ? 'period' : 'daily',true);
    // Only intersecting days can be added when a complete period is unavailable.
    $points=[]; $days=0;
    for ($day=FinancePolicy::date($range['start']);$day<=FinancePolicy::date($range['end']);$day=$day->modify('+1 day')) {
      $date=$day->format('Y-m-d'); $row=[];
      foreach (AdsPerformance::CHANNELS as $channel=>$_) if (isset($daily[$channel][$date])) $row[]=$daily[$channel][$date];
      if (count($row)===count(AdsPerformance::CHANNELS)) { $days++; array_push($points,...$row); }
    }
    return self::result($points,$days,'daily',$days===$range['days']);
  }

  private static function result(array $points,int $days,string $basis,bool $complete): array {
    return ['amount'=>$points ? array_sum(array_column($points,'amount'))/100000 : null,'days'=>$days,'basis'=>$basis,'complete'=>$complete,
      'updated_at'=>$points ? gmdate('Y-m-d H:i:s',min(array_column($points,'time'))) : null,
      'stale'=>in_array(true,array_column($points,'stale'),true)];
  }
}
