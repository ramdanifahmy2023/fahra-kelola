<?php
require_once __DIR__.'/Finance.php';

class FinanceCost extends Finance {
  private function catalogSql(): string {
    return "SELECT p.shop_id,p.id product_id,COALESCE(m.id,0) model_id,
        CONVERT(CASE WHEN m.id IS NULL THEN COALESCE(p.parent_sku,'') ELSE COALESCE(m.sku,'') END USING utf8mb4) COLLATE utf8mb4_unicode_ci sku,
        CONVERT(COALESCE(p.parent_sku,'') USING utf8mb4) COLLATE utf8mb4_unicode_ci parent_sku,
        CONVERT(COALESCE(m.sku,'') USING utf8mb4) COLLATE utf8mb4_unicode_ci variant_sku,
        CONVERT(p.name USING utf8mb4) COLLATE utf8mb4_unicode_ci product_name,
        CONVERT(COALESCE(m.name,'') USING utf8mb4) COLLATE utf8mb4_unicode_ci variation_name,
        CONVERT(COALESCE(m.image,'') USING utf8mb4) COLLATE utf8mb4_unicode_ci variant_image,
        CONVERT(COALESCE(p.cover_image,'') USING utf8mb4) COLLATE utf8mb4_unicode_ci product_image,
        CASE WHEN m.id IS NULL THEN 0 ELSE 1 END has_variant,0 archived
      FROM products p LEFT JOIN product_models m ON m.product_id=p.id AND m.deleted_at IS NULL WHERE p.deleted_at IS NULL
      UNION ALL SELECT h.shop_id,h.product_id,h.model_id,h.sku,h.sku parent_sku,h.sku variant_sku,h.product_name,h.variation_name,CONVERT('' USING utf8mb4) COLLATE utf8mb4_unicode_ci variant_image,CONVERT('' USING utf8mb4) COLLATE utf8mb4_unicode_ci product_image,CASE WHEN h.model_id=0 THEN 0 ELSE 1 END has_variant,1 archived
      FROM finance_cost_heads h WHERE NOT EXISTS (SELECT 1 FROM products p LEFT JOIN product_models m ON m.product_id=p.id AND m.deleted_at IS NULL WHERE p.shop_id=h.shop_id AND p.id=h.product_id AND COALESCE(m.id,0)=h.model_id AND p.deleted_at IS NULL)";
  }

  public function catalog(array $shops,int $page=1,string $search='',string $status='all',int $limit=25): array {
    if (!$shops) return ['rows'=>[],'page'=>1,'total'=>0];
    if (!in_array($status,['all','filled','missing','archived'],true)) throw new InvalidArgumentException('Status HPP tidak valid.');
    $ids=implode(',',array_map('intval',array_column($shops,'id')));
    $today=(new DateTimeImmutable('now',new DateTimeZone('Asia/Jakarta')))->format('Y-m-d');
    $where="c.shop_id IN ({$ids})"; $params=[];
    if ($search!=='') { $where.=' AND (c.sku LIKE :q OR c.product_name LIKE :q2 OR c.variation_name LIKE :q3)'; $q='%'.addcslashes(mb_substr($search,0,100),'%_\\').'%'; $params=['q'=>$q,'q2'=>$q,'q3'=>$q]; }
    if ($status==='archived') $where.=' AND c.archived=1';
    if ($status==='filled') { $where.=' AND c.archived=0 AND EXISTS (SELECT 1 FROM finance_cost_versions cv WHERE cv.shop_id=c.shop_id AND cv.product_id=c.product_id AND cv.model_id=c.model_id AND cv.valid_from<=:status_date)'; $params['status_date']=$today; }
    if ($status==='missing') { $where.=' AND c.archived=0 AND NOT EXISTS (SELECT 1 FROM finance_cost_versions cv WHERE cv.shop_id=c.shop_id AND cv.product_id=c.product_id AND cv.model_id=c.model_id AND cv.valid_from<=:status_date)'; $params['status_date']=$today; }
    $from='FROM ('.$this->catalogSql().') c JOIN shops s ON s.id=c.shop_id WHERE '.$where;
    $total=(int)$this->one('SELECT COUNT(*) total '.$from,$params)['total'];
    $limit=max(1,min(10000,$limit)); $page=max(1,min($page,max(1,(int)ceil($total/$limit)))); $offset=($page-1)*$limit;
    $rows=$this->rows('SELECT c.*,s.name shop_name '.$from." ORDER BY c.archived,c.product_name,c.model_id,c.shop_id LIMIT {$limit} OFFSET {$offset}",$params);
    $summaryWhere="c.shop_id IN ({$ids})"; $summaryParams=[];
    if ($search!=='') { $summaryWhere.=' AND (c.sku LIKE :sq OR c.product_name LIKE :sq2 OR c.variation_name LIKE :sq3)'; $summaryParams['sq']=$q; $summaryParams['sq2']=$q; $summaryParams['sq3']=$q; }
    if ($status==='archived') $summaryWhere.=' AND c.archived=1';
    if ($status==='filled') { $summaryWhere.=' AND c.archived=0 AND EXISTS (SELECT 1 FROM finance_cost_versions cv WHERE cv.shop_id=c.shop_id AND cv.product_id=c.product_id AND cv.model_id=c.model_id AND cv.valid_from<=:summary_status_date)'; $summaryParams['summary_status_date']=$today; }
    if ($status==='missing') { $summaryWhere.=' AND c.archived=0 AND NOT EXISTS (SELECT 1 FROM finance_cost_versions cv WHERE cv.shop_id=c.shop_id AND cv.product_id=c.product_id AND cv.model_id=c.model_id AND cv.valid_from<=:summary_status_date)'; $summaryParams['summary_status_date']=$today; }
    $summary=$this->one('SELECT COUNT(*) total,COALESCE(SUM(c.archived=1),0) archived,COALESCE(SUM(c.archived=0 AND EXISTS (SELECT 1 FROM finance_cost_versions cv WHERE cv.shop_id=c.shop_id AND cv.product_id=c.product_id AND cv.model_id=c.model_id AND cv.valid_from<=:summary_filled_date)),0) filled,COALESCE(SUM(c.archived=0 AND NOT EXISTS (SELECT 1 FROM finance_cost_versions cv WHERE cv.shop_id=c.shop_id AND cv.product_id=c.product_id AND cv.model_id=c.model_id AND cv.valid_from<=:summary_missing_date)),0) missing,COUNT(DISTINCT CONCAT(c.shop_id,\':\',c.product_id)) products,COALESCE(SUM(c.has_variant=1),0) variants FROM ('.$this->catalogSql().') c WHERE '.$summaryWhere,$summaryParams+['summary_filled_date'=>$today,'summary_missing_date'=>$today]);
    foreach ($rows as &$row) {
      $key=$this->key($row); $head=$this->one('SELECT version FROM finance_cost_heads WHERE '.$this->where(),$key);
      $cost=$this->one('SELECT unit_cost,valid_from FROM finance_cost_versions WHERE '.$this->where().' AND valid_from<=:date ORDER BY valid_from DESC LIMIT 1',$key+['date'=>$today]);
      $row['version']=(int)($head['version'] ?? 0); $row['unit_cost']=$cost ? (int)$cost['unit_cost'] : null; $row['valid_from']=$cost['valid_from'] ?? null;
      $row['status']=$row['archived'] ? 'archived' : ($cost ? 'filled' : 'missing');
      foreach (['product_id','model_id'] as $id) $row[$id]=(string)$row[$id];
    }
    return ['rows'=>$rows,'page'=>$page,'total'=>$total,'summary'=>array_map('intval',$summary ?: [])];
  }

  public function exportCatalog(array $shops,string $search='',string $status='all'): array {
    return $this->catalog($shops,1,$search,$status,10000);
  }

  public function health(array $shops): array {
    $result=[];
    foreach ($shops as $shop) {
      $shopId=(int)$shop['id'];
      $catalog=$this->catalog([$shop],1,'','all',10000);
      $variantWithoutModel=(int)($this->one('SELECT COUNT(*) total FROM order_items oi JOIN orders o ON o.id=oi.order_id AND o.shop_id=:shop_order JOIN products p ON p.id=oi.product_id AND p.shop_id=:shop_product AND p.deleted_at IS NULL JOIN product_models pm ON pm.product_id=p.id AND pm.deleted_at IS NULL WHERE COALESCE(oi.model_id,0)=0',['shop_order'=>$shopId,'shop_product'=>$shopId])['total'] ?? 0);
      $invalidModel=(int)($this->one('SELECT COUNT(*) total FROM order_items oi JOIN orders o ON o.id=oi.order_id AND o.shop_id=:shop_join LEFT JOIN product_models pm ON pm.id=oi.model_id AND pm.product_id=oi.product_id AND pm.deleted_at IS NULL WHERE o.shop_id=:shop_filter AND oi.model_id IS NOT NULL AND oi.model_id>0 AND pm.id IS NULL',['shop_join'=>$shopId,'shop_filter'=>$shopId])['total'] ?? 0);
      $duplicateRows=(int)($this->one('SELECT COUNT(*) total FROM (SELECT oi.order_id,oi.product_id,COALESCE(oi.model_id,0) model_id FROM order_items oi JOIN orders o ON o.id=oi.order_id WHERE o.shop_id=:shop GROUP BY oi.order_id,oi.product_id,COALESCE(oi.model_id,0) HAVING COUNT(*)>1) duplicates',['shop'=>$shopId])['total'] ?? 0);
      $result[]=['shop_id'=>(string)$shopId,'shop_name'=>$shop['name'] ?? '','hpp'=>$catalog['summary'] ?? [],'order_items'=>['variant_without_model'=>$variantWithoutModel,'invalid_model'=>$invalidModel,'duplicate_rows'=>$duplicateRows],'status'=>($variantWithoutModel||$invalidModel||$duplicateRows||((int)($catalog['summary']['missing'] ?? 0)>0))?'attention':'ready'];
    }
    return ['shops'=>$result];
  }

  private function where(): string { return 'shop_id=:shop AND product_id=:product AND model_id=:model'; }
  private function key(array $input): array {
    $key=[];
    foreach (['shop'=>'shop_id','product'=>'product_id','model'=>'model_id'] as $to=>$from) {
      $value=(string)($input[$from] ?? '');
      if (!ctype_digit($value) || strlen($value)>18 || ($to!=='model' && (int)$value<1)) throw new InvalidArgumentException('Produk atau toko tidak valid.');
      $key[$to]=(int)$value;
    }
    return $key;
  }

  private function keyBySku(array $input): ?array {
    $shop=(string)($input['shop_id'] ?? ''); $product=(string)($input['product_id'] ?? '');
    $sku=trim((string)($input['variant_sku'] ?? $input['sku'] ?? ''));
    if (!ctype_digit($shop) || (int)$shop<1 || !ctype_digit($product) || (int)$product<1 || $sku==='') return null;
    $rows=$this->rows('SELECT c.shop_id,c.product_id,c.model_id FROM ('.$this->catalogSql().') c WHERE c.shop_id=:shop AND c.product_id=:product AND c.sku=:sku LIMIT 2',['shop'=>(int)$shop,'product'=>(int)$product,'sku'=>$sku]);
    if (count($rows)>1) throw new InvalidArgumentException('SKU varian tidak unik untuk produk ini. Gunakan model_id asli dari katalog.');
    if (!$rows) return null;
    return ['shop'=>(int)$rows[0]['shop_id'],'product'=>(int)$rows[0]['product_id'],'model'=>(int)$rows[0]['model_id']];
  }

  private function scope(array $input): ?array {
    if (!array_key_exists('scope_shops',$input)) return null;
    if (!is_array($input['scope_shops']) || !$input['scope_shops'] || count($input['scope_shops'])>500) throw new InvalidArgumentException('Scope toko tidak valid.');
    $ids=[];
    foreach ($input['scope_shops'] as $value) {
      if (!ctype_digit((string)$value) || (int)$value<1) throw new InvalidArgumentException('Scope toko tidak valid.');
      $ids[(int)$value]=true;
    }
    return array_keys($ids);
  }

  private function assertScope(array $input,array $key): ?array {
    $scope=$this->scope($input);
    if ($scope!==null && !in_array($key['shop'],$scope,true)) throw new InvalidArgumentException('Baris HPP berada di luar toko yang dipilih.');
    return $scope;
  }

  private function costValue(array $input): int {
    $value=(string)($input['unit_cost'] ?? '');
    if (!ctype_digit($value) || strlen($value)>10 || (int)$value>1000000000) throw new InvalidArgumentException('Isi HPP dalam rupiah bulat, dari 0 sampai 1 miliar.');
    return (int)$value;
  }

  private function costDate(array $input): string {
    $date=FinancePolicy::date((string)($input['valid_from'] ?? ''))->format('Y-m-d');
    if ($date<'2015-01-01' || $date>'2100-01-01') throw new InvalidArgumentException('Tanggal mulai HPP tidak valid.');
    return $date;
  }

  private function rowsByKeys(string $select,array $keys): array {
    if (!$keys) return [];
    $tuples=[]; $params=[];
    foreach (array_values($keys) as $index=>$key) {
      $tuples[]="(:s{$index},:p{$index},:m{$index})";
      $params["s{$index}"]=$key['shop']; $params["p{$index}"]=$key['product']; $params["m{$index}"]=$key['model'];
    }
    return $this->rows($select.' WHERE (shop_id,product_id,model_id) IN ('.implode(',',$tuples).')',$params);
  }

  private function catalogByKeys(array $keys): array {
    $sql='SELECT c.* FROM ('.$this->catalogSql().') c';
    $rows=$this->rowsByKeys($sql,$keys); $result=[];
    foreach ($rows as $row) $result[$row['shop_id'].':'.$row['product_id'].':'.$row['model_id']]=$row;
    return $result;
  }

  private function headVersionsByKeys(array $keys): array {
    $rows=$this->rowsByKeys('SELECT shop_id,product_id,model_id,version FROM finance_cost_heads',$keys); $result=[];
    foreach ($rows as $row) $result[$row['shop_id'].':'.$row['product_id'].':'.$row['model_id']]=(int)$row['version'];
    return $result;
  }

  private function datedCostsByKeys(array $keys): array {
    $rows=$this->rowsByKeys('SELECT shop_id,product_id,model_id,valid_from,unit_cost FROM finance_cost_versions',$keys); $result=[];
    foreach ($rows as $row) {
      $key=$row['shop_id'].':'.$row['product_id'].':'.$row['model_id'];
      $result[$key][]=['valid_from'=>$row['valid_from'],'unit_cost'=>(int)$row['unit_cost']];
    }
    return $result;
  }

  private function batchRows(array $input): array {
    $source=$input['rows'] ?? null;
    if (!is_array($source) || !$source || count($source)>500) throw new InvalidArgumentException('Batch HPP harus berisi 1 sampai 500 baris.');
    $rows=[]; $errors=[]; $keys=[]; $dates=[]; $valid=0; $scope=$this->scope($input);
    foreach (array_values($source) as $index=>$raw) {
      $line=is_array($raw) ? (int)($raw['row_no'] ?? $index+1) : $index+1; $line=$line>0 ? $line : $index+1;
      if (!is_array($raw)) { $errors[$index]=['row_no'=>$line,'message'=>'Baris tidak dapat dibaca.']; continue; }
      try {
        try { $key=$this->key($raw); } catch (InvalidArgumentException $identityError) { $key=$this->keyBySku($raw); if ($key===null) throw $identityError; }
        if ($scope!==null && !in_array($key['shop'],$scope,true)) throw new InvalidArgumentException('Baris ini berada di luar toko yang dipilih.'); $unit=$this->costValue($raw); $date=$this->costDate($raw);
        $identity=$key['shop'].':'.$key['product'].':'.$key['model']; $unique=$identity.':'.$date;
        if (isset($dates[$unique])) throw new InvalidArgumentException('Baris ini menggandakan target dan tanggal pada baris '.$dates[$unique].'.');
        $dates[$unique]=$line; $keys[$identity]=$key;
        $rows[$index]=['row_no'=>$line,'shop_id'=>$key['shop'],'product_id'=>$key['product'],'model_id'=>$key['model'],'unit_cost'=>$unit,'valid_from'=>$date,'identity'=>$identity];
      } catch (InvalidArgumentException $error) { $errors[$index]=['row_no'=>$line,'message'=>$error->getMessage()]; }
    }
    $catalog=$this->catalogByKeys(array_values($keys)); $versions=$this->headVersionsByKeys(array_values($keys)); $dated=$this->datedCostsByKeys(array_values($keys));
    $increments=[]; $changed=0; $staged=$dated;
    foreach ($rows as $index=>&$row) {
      $item=$catalog[$row['identity']] ?? null;
      if (!$item) { $errors[$index]=['row_no'=>$row['row_no'],'message'=>'Produk atau varian tidak ditemukan. Sinkronkan produk terlebih dahulu.']; continue; }
      $valid++;
      $base=(int)($versions[$row['identity']] ?? 0); $offset=(int)($increments[$row['identity']] ?? 0); $increments[$row['identity']]=$offset+1;
      $previous=null;
      foreach (($staged[$row['identity']] ?? []) as $version) if ($version['valid_from']<=$row['valid_from'] && ($previous===null || $version['valid_from']>$previous['valid_from'])) $previous=$version;
      $row['expected_version']=$base+$offset; $row['previous_cost']=$previous['unit_cost'] ?? null;
      $row['shop_name']=$item['shop_name'] ?? ''; $row['product_name']=$item['product_name']; $row['variation_name']=$item['variation_name']; $row['sku']=$item['sku']; $row['parent_sku']=$item['parent_sku'] ?? ''; $row['variant_sku']=$item['variant_sku'] ?? ''; $row['variant_image']=$item['variant_image'] ?? ''; $row['product_image']=$item['product_image'] ?? ''; $row['archived']=(bool)$item['archived'];
      $next=$this->one('SELECT MIN(valid_from) next_date FROM finance_cost_versions WHERE '.$this->where().' AND valid_from>:date',$key+['date'=>$row['valid_from']])['next_date'] ?? '2100-01-02';
      $start=FinancePolicy::date($row['valid_from'])->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
      $end=FinancePolicy::date($next)->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
      $impact=$this->one("SELECT COUNT(DISTINCT x.order_id) orders,COALESCE(SUM(x.quantity),0) units,COALESCE(SUM(IF(x.cost IS NULL,x.quantity,0)),0) missing_units,COALESCE(SUM(x.quantity*x.cost),0) known_cost FROM (SELECT o.id order_id,oi.quantity,(SELECT v.unit_cost FROM finance_cost_versions v WHERE v.shop_id=o.shop_id AND v.product_id=oi.product_id AND v.model_id=COALESCE(oi.model_id,0) AND v.valid_from<=DATE(DATE_ADD(o.created_at,INTERVAL 7 HOUR)) ORDER BY v.valid_from DESC LIMIT 1) cost FROM orders o JOIN order_items oi ON oi.order_id=o.id WHERE o.shop_id=:shop AND oi.product_id=:product AND COALESCE(oi.model_id,0)=:model AND o.created_at>=:start AND o.created_at<:end AND o.deleted_at IS NULL) x",$key+['start'=>$start,'end'=>$end]);
      $row['impact']=['orders'=>(int)($impact['orders'] ?? 0),'units'=>(int)($impact['units'] ?? 0),'missing_units'=>(int)($impact['missing_units'] ?? 0),'known_cost'=>(int)($impact['known_cost'] ?? 0),'new_cost'=>(int)($impact['units'] ?? 0)*$row['unit_cost']];
      if ($row['previous_cost']===null || (int)$row['previous_cost']!==$row['unit_cost']) $changed++;
      $staged[$row['identity']][]=['valid_from'=>$row['valid_from'],'unit_cost'=>$row['unit_cost']];
      unset($row['identity']);
    }
    unset($row);
    ksort($errors);
    return ['rows'=>array_values($rows),'errors'=>array_values($errors),'summary'=>['total'=>count($source),'valid'=>$valid,'errors'=>count($errors),'changed'=>$changed,'unchanged'=>max(0,$valid-$changed)],'scope_shops'=>$scope];
  }

  public function batchPreview(array $input): array {
    return $this->batchRows($input);
  }

  public function history(array $input): array {
    $rows=$this->rows('SELECT valid_from,unit_cost,actor_id,created_at FROM finance_cost_versions WHERE '.$this->where().' ORDER BY valid_from ASC',$this->key($input));
    $previous=null;
    foreach ($rows as &$row) { $row['previous_cost']=$previous; $previous=(int)$row['unit_cost']; }
    unset($row);
    usort($rows,static fn($a,$b)=>strcmp((string)$b['valid_from'],(string)$a['valid_from']));
    return $rows;
  }

  public function revert(array $input,int $actor): array {
    $key=$this->key($input); $date=$this->costDate($input); $expected=(int)($input['version'] ?? -1);
    if ($expected<0) throw new InvalidArgumentException('Versi HPP tidak valid. Muat ulang daftar terlebih dahulu.');
    $this->db->begin();
    try {
      $head=$this->one('SELECT version FROM finance_cost_heads WHERE '.$this->where().' FOR UPDATE',$key);
      if ((int)($head['version'] ?? -1)!==$expected) throw new RuntimeException('HPP sudah diubah anggota tim. Muat ulang riwayat lalu coba lagi.',409);
      $current=$this->one('SELECT unit_cost FROM finance_cost_versions WHERE '.$this->where().' AND valid_from=:date',$key+['date'=>$date]);
      if (!$current) throw new InvalidArgumentException('Versi HPP yang dipilih sudah tidak tersedia.');
      $previous=$this->one('SELECT unit_cost FROM finance_cost_versions WHERE '.$this->where().' AND valid_from<:date ORDER BY valid_from DESC LIMIT 1',$key+['date'=>$date]);
      if (!$previous) throw new InvalidArgumentException('Tidak ada HPP sebelumnya untuk dipulihkan. Isi nilai baru lewat formulir HPP.');
      $new=(int)$previous['unit_cost']; $nextVersion=$expected+1;
      $this->execute('UPDATE finance_cost_versions SET unit_cost=:cost,actor_id=:actor,created_at=UTC_TIMESTAMP() WHERE '.$this->where().' AND valid_from=:date',$key+['date'=>$date,'cost'=>$new,'actor'=>$actor]);
      $this->execute('UPDATE finance_cost_heads SET version=:version,updated_at=UTC_TIMESTAMP() WHERE '.$this->where(),$key+['version'=>$nextVersion]);
      $this->execute('INSERT INTO finance_cost_events (shop_id,product_id,model_id,valid_from,previous_cost,unit_cost,version,actor_id,created_at) VALUES (:shop,:product,:model,:date,:previous,:cost,:version,:actor,UTC_TIMESTAMP())',$key+['date'=>$date,'previous'=>(int)$current['unit_cost'],'cost'=>$new,'version'=>$nextVersion,'actor'=>$actor]);
      $this->db->commit(); return ['version'=>$nextVersion,'unit_cost'=>$new];
    } catch (Throwable $error) { $this->db->rollback(); throw $error; }
  }

  public function orderCost(int $shop,string $order): array {
    $row=$this->one("SELECT COUNT(*) items,SUM(x.cost IS NULL OR x.quantity IS NULL OR x.quantity<1) missing_items,SUM(x.cost*x.quantity) amount FROM (SELECT oi.quantity,(SELECT v.unit_cost FROM finance_cost_versions v WHERE v.shop_id=o.shop_id AND v.product_id=oi.product_id AND v.model_id=COALESCE(oi.model_id,0) AND v.valid_from<=DATE(DATE_ADD(o.created_at,INTERVAL 7 HOUR)) ORDER BY v.valid_from DESC LIMIT 1) cost FROM orders o JOIN order_items oi ON oi.order_id=o.id WHERE o.shop_id=:shop AND o.id=:order AND o.deleted_at IS NULL) x",['shop'=>$shop,'order'=>$order]);
    return ['amount'=>(int)$row['items'] && !(int)$row['missing_items'] ? (int)$row['amount'] : null,'items'=>(int)$row['items'],'missing_items'=>(int)$row['missing_items']];
  }

  public function preview(array $input): array {
    $key=$this->key($input);
    $scope=$this->assertScope($input,$key);
    $item=$this->one('SELECT c.* FROM ('.$this->catalogSql().') c JOIN shops s ON s.id=c.shop_id WHERE c.shop_id=:shop AND c.product_id=:product AND c.model_id=:model LIMIT 1',$key);
    if (!$item) throw new InvalidArgumentException('Produk tidak ditemukan di toko ini. Sinkronkan produk terlebih dahulu.');
    $value=$this->costValue($input); $date=$this->costDate($input);
    $version=(int)($this->one('SELECT version FROM finance_cost_heads WHERE '.$this->where(),$key)['version'] ?? 0);
    if (!isset($input['version']) || (string)$input['version']!==(string)$version) throw new RuntimeException('HPP sudah diubah anggota tim. Muat ulang daftar lalu periksa lagi.',409);
    $next=$this->one('SELECT MIN(valid_from) next_date FROM finance_cost_versions WHERE '.$this->where().' AND valid_from>:date',$key+['date'=>$date])['next_date'] ?? '2100-01-02';
    $start=FinancePolicy::date($date)->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    $end=FinancePolicy::date($next)->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    $impact=$this->one("SELECT COUNT(DISTINCT x.order_id) orders,COALESCE(SUM(x.quantity),0) units,COALESCE(SUM(IF(x.cost IS NULL,x.quantity,0)),0) missing_units,COALESCE(SUM(x.quantity*x.cost),0) known_cost FROM (SELECT o.id order_id,oi.quantity,(SELECT v.unit_cost FROM finance_cost_versions v WHERE v.shop_id=o.shop_id AND v.product_id=oi.product_id AND v.model_id=COALESCE(oi.model_id,0) AND v.valid_from<=DATE(DATE_ADD(o.created_at,INTERVAL 7 HOUR)) ORDER BY v.valid_from DESC LIMIT 1) cost FROM orders o JOIN order_items oi ON oi.order_id=o.id WHERE o.shop_id=:shop AND oi.product_id=:product AND COALESCE(oi.model_id,0)=:model AND o.created_at>=:start AND o.created_at<:end AND o.deleted_at IS NULL) x",$key+['start'=>$start,'end'=>$end]);
    foreach ($impact as &$number) $number=(int)$number;
    $impact['new_cost']=$impact['units']*$value;
    return ['shop_id'=>$key['shop'],'product_id'=>(string)$key['product'],'model_id'=>(string)$key['model'],'sku'=>$item['sku'],'product_name'=>$item['product_name'],'variation_name'=>$item['variation_name'],'archived'=>(bool)$item['archived'],'unit_cost'=>(int)$value,'valid_from'=>$date,'until'=>$next==='2100-01-02' ? null : FinancePolicy::date($next)->modify('-1 day')->format('Y-m-d'),'version'=>$version,'scope_shops'=>$scope,'impact'=>$impact];
  }

  public static function token(array $preview,int $expires,string $secret): string {
    return hash_hmac('sha256',json_encode([$preview,$expires],JSON_UNESCAPED_UNICODE),$secret);
  }

  public static function batchToken(array $preview,int $expires,string $secret): string {
    return self::token($preview,$expires,$secret);
  }

  private function persistPreviewRow(array $preview,int $actor,int $expectedVersion): void {
    $key=['shop'=>(int)$preview['shop_id'],'product'=>(int)$preview['product_id'],'model'=>(int)$preview['model_id']];
    $this->execute('INSERT INTO finance_cost_heads (shop_id,product_id,model_id,sku,product_name,variation_name,updated_at) VALUES (:shop,:product,:model,:sku,:name,:variation,UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE shop_id=VALUES(shop_id)',$key+['sku'=>$preview['sku'],'name'=>$preview['product_name'],'variation'=>$preview['variation_name']]);
    $head=$this->one('SELECT version FROM finance_cost_heads WHERE '.$this->where().' FOR UPDATE',$key);
    if ((int)$head['version']!==$expectedVersion) throw new RuntimeException('Salah satu HPP sudah diubah anggota tim. Muat ulang pratinjau lalu simpan lagi.',409);
    $old=$this->one('SELECT unit_cost FROM finance_cost_versions WHERE '.$this->where().' AND valid_from=:date',$key+['date'=>$preview['valid_from']]);
    $values=$key+['date'=>$preview['valid_from'],'cost'=>(int)$preview['unit_cost'],'actor'=>$actor];
    $this->execute('INSERT INTO finance_cost_versions (shop_id,product_id,model_id,valid_from,unit_cost,actor_id,created_at) VALUES (:shop,:product,:model,:date,:cost,:actor,UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE unit_cost=VALUES(unit_cost),actor_id=VALUES(actor_id),created_at=VALUES(created_at)',$values);
    $this->execute('UPDATE finance_cost_heads SET version=version+1,sku=:sku,product_name=:name,variation_name=:variation,updated_at=UTC_TIMESTAMP() WHERE '.$this->where(),$key+['sku'=>$preview['sku'],'name'=>$preview['product_name'],'variation'=>$preview['variation_name']]);
    $this->execute('INSERT INTO finance_cost_events (shop_id,product_id,model_id,valid_from,previous_cost,unit_cost,version,actor_id,created_at) VALUES (:shop,:product,:model,:date,:previous,:cost,:version,:actor,UTC_TIMESTAMP())',$values+['previous'=>$old['unit_cost'] ?? null,'version'=>$expectedVersion+1]);
  }

  public function batchSave(array $input,int $actor,string $secret): array {
    $preview=$this->batchRows($input); if ($preview['errors']) throw new InvalidArgumentException('Perbaiki baris yang ditandai sebelum menyimpan batch.');
    $expires=(int)($input['expires'] ?? 0);
    if ($expires<time() || $expires>time()+601 || !hash_equals(self::batchToken($preview,$expires,$secret),(string)($input['token'] ?? ''))) throw new RuntimeException('Pratinjau batch kedaluwarsa atau data berubah. Periksa lagi sebelum menyimpan.',409);
    $this->db->begin();
    try { foreach ($preview['rows'] as $row) $this->persistPreviewRow($row,$actor,(int)$row['expected_version']); $this->db->commit(); return ['saved'=>count($preview['rows'])]; }
    catch (Throwable $error) { $this->db->rollback(); throw $error; }
  }

  public function save(array $input,int $actor,string $secret): array {
    $preview=$this->preview($input); $expires=(int)($input['expires'] ?? 0);
    if ($expires<time() || $expires>time()+601 || !hash_equals(self::token($preview,$expires,$secret),(string)($input['token'] ?? ''))) throw new RuntimeException('Data berubah atau pratinjau kedaluwarsa. Periksa dampaknya lagi.',409);
    $key=$this->key($input); $this->db->begin();
    try {
      $this->execute('INSERT INTO finance_cost_heads (shop_id,product_id,model_id,sku,product_name,variation_name,updated_at) VALUES (:shop,:product,:model,:sku,:name,:variation,UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE shop_id=VALUES(shop_id)',$key+['sku'=>$preview['sku'],'name'=>$preview['product_name'],'variation'=>$preview['variation_name']]);
      $head=$this->one('SELECT version FROM finance_cost_heads WHERE '.$this->where().' FOR UPDATE',$key);
      if ((int)$head['version']!==$preview['version']) throw new RuntimeException('HPP sudah diubah anggota tim. Muat ulang lalu periksa lagi.',409);
      $old=$this->one('SELECT unit_cost FROM finance_cost_versions WHERE '.$this->where().' AND valid_from=:date',$key+['date'=>$preview['valid_from']]);
      $values=$key+['date'=>$preview['valid_from'],'cost'=>$preview['unit_cost'],'actor'=>$actor];
      $this->execute('INSERT INTO finance_cost_versions (shop_id,product_id,model_id,valid_from,unit_cost,actor_id,created_at) VALUES (:shop,:product,:model,:date,:cost,:actor,UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE unit_cost=VALUES(unit_cost),actor_id=VALUES(actor_id),created_at=VALUES(created_at)',$values);
      $this->execute('UPDATE finance_cost_heads SET version=version+1,sku=:sku,product_name=:name,variation_name=:variation,updated_at=UTC_TIMESTAMP() WHERE '.$this->where(),$key+['sku'=>$preview['sku'],'name'=>$preview['product_name'],'variation'=>$preview['variation_name']]);
      $this->execute('INSERT INTO finance_cost_events (shop_id,product_id,model_id,valid_from,previous_cost,unit_cost,version,actor_id,created_at) VALUES (:shop,:product,:model,:date,:previous,:cost,:version,:actor,UTC_TIMESTAMP())',$values+['previous'=>$old['unit_cost'] ?? null,'version'=>$preview['version']+1]);
      $this->db->commit(); return ['version'=>$preview['version']+1];
    } catch (Throwable $error) { $this->db->rollback(); throw $error; }
  }
}
