<?php

class DashboardMetrics {
  public static function combine(array $stores, int $selected): array {
    $keys=['uv','pv','product_clicks','orders','buyers','sales'];
    $totals=array_fill_keys($keys,null); $coverage=array_fill_keys($keys,0);
    $hourly=[]; $hourCoverage=[]; $products=[]; $times=[]; $productShops=0;
    foreach ($stores as $store) {
      $metrics=$store['metrics'];
      foreach ($keys as $key) if (is_numeric($metrics['key_metrics'][$key] ?? null)) {
        $totals[$key]=($totals[$key] ?? 0)+(float)$metrics['key_metrics'][$key]; $coverage[$key]++;
      }
      foreach (($metrics['sales_hourly'] ?? []) as $hour=>$value) {
        if (!ctype_digit((string)$hour) || (int)$hour>23) continue;
        $hour=(int)$hour;
        if (!array_key_exists($hour,$hourly)) { $hourly[$hour]=null; $hourCoverage[$hour]=0; }
        if (is_numeric($value)) { $hourly[$hour]=($hourly[$hour] ?? 0)+(float)$value; $hourCoverage[$hour]++; }
      }
      if (is_array($metrics['top_sales_items'] ?? null)) $productShops++;
      foreach (($metrics['top_sales_items'] ?? []) as $product) {
        if (!is_array($product) || !is_numeric($product['sales'] ?? null)) continue;
        $products[]=['shop_id'=>$store['shop_id'],'shop_name'=>$store['shop_name'],
          'item_name'=>trim((string)($product['item_name'] ?? '')) ?: 'Produk tanpa nama','sales'=>(float)$product['sales']];
      }
      $times[]=is_numeric($metrics['time'] ?? null) && $metrics['time']>0 ? (int)$metrics['time'] : null;
    }
    usort($products,static fn($a,$b)=>$b['sales']<=>$a['sales']);
    if ($hourly) for ($i=0,$last=max(array_keys($hourly));$i<=$last;$i++) {
      if (!array_key_exists($i,$hourly)) { $hourly[$i]=null; $hourCoverage[$i]=0; }
    }
    ksort($hourly); ksort($hourCoverage);
    return ['key_metrics'=>$totals,'metric_coverage'=>$coverage,'top_sales_items'=>array_slice($products,0,5),
      'product_shop_count'=>$productShops,'sales_hourly'=>array_values($hourly),'hourly_coverage'=>array_values($hourCoverage),
      'time'=>$times && !in_array(null,$times,true) ? min($times) : null,'selected_shop_count'=>$selected];
  }
}
