<?php

final class WorkspacePolicy {
  public const FILTERS = [
    'products'=>['shop_id','limit','page','stock'],
    'orders'=>['shop_id','limit','page','startDate','endDate'],
    'customers'=>['shop_id','limit','page'],
    'ads'=>['shop_id','period','channel','topup_shop','topup_period','topup_start','topup_end'],
    'reports'=>['shop_id','end_date','sort','metric'], 'automation'=>['shop_id'], 'chat'=>['shop_id','status'],
    'sync'=>['shop_id'], 'boost'=>[],
    'finance'=>['shops','period','start','end','tab','category','state'],
    'dashboard'=>['shops','period','start','end']
  ];
  public const SINGLE_SHOP = ['products','orders','customers','ads','reports','automation','chat','sync'];

  public static function clean(string $scope, array $values): array {
    $clean=[];
    foreach (self::FILTERS[$scope] ?? [] as $key) {
      if (!isset($values[$key]) || !is_scalar($values[$key])) continue;
      $value=trim((string)$values[$key]);
      if (strlen($value)>200) continue;
      if (in_array($key,['shop_id','topup_shop','page','limit'],true)) {
        if (!ctype_digit($value)) continue;
        $n=(int)$value;
        if ($key==='page' && ($n<1 || $n>100000)) continue;
        if ($key==='limit' && !in_array($n,[10,20,50,100],true)) continue;
        $clean[$key]=$n;
      } elseif ($key==='shops') {
        if ($value==='' || preg_match('/^\d+(,\d+)*$/D',$value)) $clean[$key]=$value;
      } elseif (in_array($key,['start','end','startDate','endDate','topup_start','topup_end','end_date'],true)) {
        $d=DateTimeImmutable::createFromFormat('!Y-m-d',$value);
        if ($d && $d->format('Y-m-d')===$value) $clean[$key]=$value;
      } elseif (preg_match('/^[a-z0-9_]*$/D',$value)) $clean[$key]=$value;
    }
    if (isset($values['scroll_y']) && is_numeric($values['scroll_y'])) $clean['scroll_y']=max(0,min(100000,(int)$values['scroll_y']));
    return $clean;
  }

  public static function restore(string $scope, array $query, array $saved, ?int $shop): array {
    $saved=self::clean($scope,$saved);unset($saved['scroll_y']);
    if (in_array($scope,self::SINGLE_SHOP,true)) {
      $chosen=array_key_exists('shop_id',$query)?(int)$query['shop_id']:($shop ?? (int)($saved['shop_id'] ?? 0));
      if (isset($saved['shop_id']) && $chosen!==(int)$saved['shop_id']) unset($saved['page']);
      if (!array_key_exists('shop_id',$query) && $shop!==null) $saved['shop_id']=$shop;
    }
    // Explicit URLs and search/notification destinations always win over saved views.
    if (isset($query['order_id']) || isset($query['highlight']) || isset($query['customer_id'])) unset($saved['page'],$saved['stock']);
    return $query+$saved;
  }
}
