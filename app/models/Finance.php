<?php
require_once __DIR__ . '/../helpers/FinancePolicy.php';

class Finance extends BaseModel {
  private $ready = false;
  protected function lockKey(string $kind,int $shop): string { return 'shopdash-finance-'.$kind.'-'.$shop; }
  public function ensureSchema(): void {
    if ($this->ready) return;
    foreach (explode(';',file_get_contents(__DIR__.'/../../database/migrations/20260929_finance.sql')) as $sql) {
      if (trim($sql) !== '') $this->execute($sql);
    }
    foreach (explode(';',file_get_contents(__DIR__.'/../../database/migrations/20260929_ad_balance_topups.sql')) as $sql) {
      if (trim($sql) !== '') $this->execute($sql);
    }
    $this->ready = true;
  }

  public function execute(string $sql, array $params = []): void {
    $this->db->query($sql); foreach ($params as $key=>$value) $this->db->bind($key,$value); $this->db->exe();
  }
  public function rows(string $sql, array $params = []): array {
    $this->db->query($sql); foreach ($params as $key=>$value) $this->db->bind($key,$value); return $this->db->getAll();
  }
  public function one(string $sql, array $params = []): ?array { return $this->rows($sql,$params)[0] ?? null; }

  public function shops($requested = []): array {
    $shops = $this->rows('SELECT id,shop_id,name,shop_logo FROM shops ORDER BY name,id');
    if (!is_array($requested)) $requested = explode(',',(string)$requested);
    $ids = [];
    foreach ($requested as $id) {
      if ($id === '' || $id === '0' || $id === 0) continue;
      if (!ctype_digit((string)$id) || (int)$id < 1) throw new InvalidArgumentException('Pilihan toko tidak valid.');
      $ids[] = (int)$id;
    }
    if (!$ids) return $shops;
    if (array_diff($ids,array_map('intval',array_column($shops,'id')))) throw new InvalidArgumentException('Toko tidak ditemukan.');
    return array_values(array_filter($shops,static fn($shop)=>in_array((int)$shop['id'],$ids,true)));
  }

  public function requestImports(array $shops, array $range, bool $force = true, bool $pendingOnly = false): array {
    $this->ensureSchema(); $result = [];
    foreach ($shops as $shop) {
      $key = $this->lockKey('queue',(int)$shop['id']);
      if (!(int)($this->one('SELECT GET_LOCK(:key,2) locked',['key'=>$key])['locked'] ?? 0)) throw new RuntimeException('Antrean toko sedang diperbarui. Coba lagi.');
      try {
        $windows = $pendingOnly ? [] : array_map(static fn($w)=>[2,$w['start'],$w['end']],FinancePolicy::windows($range));
        array_unshift($windows,[1,'1970-01-01','1970-01-01']);
        foreach ($windows as [$category,$start,$end]) {
          $params = ['shop'=>(int)$shop['id'],'source'=>(int)$shop['shop_id'],'category'=>$category,'start'=>$start,'end'=>$end];
          $last = $this->one('SELECT * FROM finance_imports WHERE shop_id=:shop AND source_shop_id=:source AND category=:category AND start_date=:start AND end_date=:end ORDER BY id DESC LIMIT 1',$params);
          if ($last && (in_array($last['state'],['queued','running'],true) || strtotime(($last['completed_at'] ?? $last['created_at']).' UTC') > time()-($force ? 60 : 600))) continue;
          $this->execute("INSERT INTO finance_imports (shop_id,source_shop_id,category,start_date,end_date,created_at) VALUES (:shop,:source,:category,:start,:end,UTC_TIMESTAMP())",$params);
          $result[] = (int)$this->db->lastId();
        }
      } finally { $this->one('SELECT RELEASE_LOCK(:key) released',['key'=>$key]); }
    }
    return $result;
  }

  public function hasWork(int $shopId): bool {
    return (bool)$this->one("SELECT id FROM finance_imports WHERE shop_id=:shop AND state IN ('queued','running') LIMIT 1",['shop'=>$shopId]);
  }

  public function work(array $shop, $api = null, int $pages = 3, int $rateMs = 350): array {
    $this->ensureSchema(); require_once __DIR__.'/../helpers/FinanceApi.php';
    $api = $api ?? new FinanceApi(); $shopId = (int)$shop['id']; $key = $this->lockKey('worker',$shopId);
    if (!(int)($this->one('SELECT GET_LOCK(:key,0) locked',['key'=>$key])['locked'] ?? 0)) return [true,null,false];
    $import = null;
    try {
      if (!$this->hasWork($shopId)) {
        $range=FinancePolicy::range();
        $coverage=(int)$this->one("SELECT COUNT(*) days FROM finance_days d JOIN finance_imports i ON i.id=d.import_id AND i.state='ready' AND i.source_shop_id=:source WHERE d.shop_id=:shop AND d.income_date>=:start AND d.income_date<:end",['shop'=>$shopId,'source'=>(int)$shop['shop_id'],'start'=>$range['start'],'end'=>$range['end']])['days'];
        if ($coverage>=($range['days']-1)) $range=FinancePolicy::range(FinancePolicy::date($range['end'])->modify('-2 days')->format('Y-m-d'),$range['end']);
        $this->requestImports([$shop],$range,false);
      }
      if (!$this->hasWork($shopId)) return [true,null,true];
      $api->verify($shop);
      for ($page=0; $page<$pages; $page++) {
        if ($page>0 && $rateMs>0) usleep(min($rateMs,5000)*1000);
        $import = $this->one("SELECT * FROM finance_imports WHERE shop_id=:shop AND state IN ('queued','running') ORDER BY id LIMIT 1",['shop'=>$shopId]);
        if (!$import) break;
        if ((string)$import['source_shop_id'] !== (string)$shop['shop_id']) throw new RuntimeException('Identitas toko berubah. Minta pembaruan data keuangan kembali.');
        $this->execute("UPDATE finance_imports SET state='running' WHERE id=:id",['id'=>(int)$import['id']]);
        $response = $api->page($shop,$import);
        $rows = array_map(static fn($row)=>FinancePolicy::row($row,(int)$import['category']),$response['rows']);
        $pendingStates=(int)$import['category']===1 ? $api->pendingStates($shop,array_column($rows,'external_order_id'),$rateMs) : [];
        $next = $response['next']; $seen = json_decode($import['seen_cursors'] ?? '[]',true) ?: [];
        if ($next && (!$rows || in_array($next['cursor'],$seen,true) || count($seen)>10000)) throw new UnexpectedValueException('Halaman Shopee berulang atau tidak bergerak. Coba pembaruan kembali.');
        if ($next) $seen[] = $next['cursor'];
        foreach ($rows as $row) if ((int)$import['category']===2 && ($row['released_date']<$import['start_date'] || $row['released_date']>$import['end_date'])) throw new UnexpectedValueException('Tanggal data Shopee berada di luar periode yang diminta.');
        $totals = !$next && (int)$import['category']===1 ? $api->overview($shop) : null;
        $overview = $totals['pending'] ?? null;
        if (!$next) $api->verify($shop);
        $this->db->begin();
        try {
          foreach ($rows as $row) {
            $row = ['import_id'=>(int)$import['id'],'shop_id'=>$shopId]+$row;
            $columns = array_keys($row); $updates = array_map(static fn($key)=>$key.'=VALUES('.$key.')',array_diff($columns,['import_id','external_order_id']));
            $this->execute('INSERT INTO finance_income_rows ('.implode(',',$columns).') VALUES (:'.implode(',:',$columns).') ON DUPLICATE KEY UPDATE '.implode(',',$updates),$row);
            if ((int)$import['category']===1) {
              $status=$pendingStates[$row['external_order_id']] ?? ['state'=>'unknown','synced_at'=>null];
              $this->execute('INSERT INTO finance_pending_states (import_id,external_order_id,state,synced_at) VALUES (:import,:order_id,:state,:synced_at) ON DUPLICATE KEY UPDATE state=VALUES(state),synced_at=VALUES(synced_at)',[
                'import'=>$row['import_id'],'order_id'=>$row['external_order_id'],'state'=>$status['state'],'synced_at'=>$status['synced_at']
              ]);
            }
          }
          $this->execute("UPDATE finance_imports SET cursor_json=:cursor,seen_cursors=:seen,page_count=page_count+1,state=:state,overview_pending=:overview,completed_at=IF(:done=1,UTC_TIMESTAMP(),NULL),error_message=NULL WHERE id=:id",['cursor'=>$next ? json_encode($next) : null,'seen'=>json_encode($seen),'state'=>$next?'running':'ready','overview'=>$overview,'done'=>$next?0:1,'id'=>(int)$import['id']]);
          if (!$next) {
            if ((int)$import['category']===1) {
              $today=new DateTimeImmutable('today',new DateTimeZone('Asia/Jakarta'));
              $this->execute('INSERT INTO finance_overview_totals (import_id,week_start,month_start,as_of_date,week_amount,month_amount,all_amount) VALUES (:id,:week_start,:month_start,:today,:week,:month,:all) ON DUPLICATE KEY UPDATE week_amount=VALUES(week_amount),month_amount=VALUES(month_amount),all_amount=VALUES(all_amount)',[
                'id'=>(int)$import['id'],'week_start'=>$today->modify('monday this week')->format('Y-m-d'),'month_start'=>$today->modify('first day of this month')->format('Y-m-d'),'today'=>$today->format('Y-m-d'),'week'=>$totals['week'],'month'=>$totals['month'],'all'=>$totals['all']
              ]);
              $this->execute('INSERT INTO finance_current (shop_id,import_id) VALUES (:shop,:id) ON DUPLICATE KEY UPDATE import_id=GREATEST(import_id,VALUES(import_id))',['shop'=>$shopId,'id'=>(int)$import['id']]);
            } else {
              for ($day=FinancePolicy::date($import['start_date']);$day<=FinancePolicy::date($import['end_date']);$day=$day->modify('+1 day')) {
                $this->execute('INSERT INTO finance_days (shop_id,income_date,import_id) VALUES (:shop,:date,:id) ON DUPLICATE KEY UPDATE import_id=GREATEST(import_id,VALUES(import_id))',['shop'=>$shopId,'date'=>$day->format('Y-m-d'),'id'=>(int)$import['id']]);
              }
            }
          }
          $this->db->commit();
        } catch (Throwable $error) { $this->db->rollback(); throw $error; }
      }
      return [true,null,!$this->hasWork($shopId)];
    } catch (Throwable $error) {
      $message = $error instanceof PDOException ? 'Penyimpanan data keuangan gagal. Data lengkap sebelumnya tetap tersedia.' : $error->getMessage();
      if (!$import) $import=$this->one("SELECT id FROM finance_imports WHERE shop_id=:shop AND state IN ('queued','running') ORDER BY id LIMIT 1",['shop'=>$shopId]);
      if ($import) $this->execute("UPDATE finance_imports SET state='failed',error_message=:error,completed_at=UTC_TIMESTAMP() WHERE id=:id",['error'=>mb_substr($message,0,255),'id'=>(int)$import['id']]);
      return [false,$message,true];
    } finally { $this->one('SELECT RELEASE_LOCK(:key) released',['key'=>$key]); }
  }

  private function ads(array $shop, array $range): array {
    $snapshot=$this->one('SELECT payload FROM ad_shop_snapshots WHERE shop_id=:shop',['shop'=>(int)$shop['id']]);
    $payload=json_decode($snapshot['payload'] ?? '{}',true) ?: [];
    if ((string)($payload['source_shop_id'] ?? '') !== (string)$shop['shop_id']) return ['amount'=>null,'days'=>0,'updated_at'=>null];
    $daily=[];
    foreach (($payload['performance_reports'] ?? []) as $channels) foreach ($channels as $channel=>$report) {
      if (!in_array($channel,['product','shop','live'],true) || empty($report['available'])) continue;
      foreach (($report['daily'] ?? []) as $point) {
        $date=$point['date'] ?? ''; $amount=$point['raw_metrics']['cost'] ?? null;
        if ($date<$range['start'] || $date>$range['end'] || !is_numeric($amount)) continue;
        $time=$report['fetched_at'] ?? '';
        if (!isset($daily[$date][$channel]) || $time>$daily[$date][$channel]['time']) $daily[$date][$channel]=['amount'=>(int)$amount,'time'=>$time];
      }
    }
    $days=0; $total=0; $updated=0;
    foreach ($daily as $channels) if (count($channels)===3) {
      $days++; $total+=array_sum(array_column($channels,'amount'));
      foreach ($channels as $point) if ($point['time']) $updated=max($updated,(int)strtotime($point['time']));
    }
    return ['amount'=>$days ? $total/100000 : null,'days'=>$days,'updated_at'=>$updated ? gmdate('Y-m-d H:i:s',$updated) : null];
  }

  private function topups(array $shop,array $range): array {
    $params=['shop'=>(int)$shop['id'],'source'=>(int)$shop['shop_id']];
    $sync=$this->one('SELECT backfill_complete,synced_at,last_error FROM ad_topup_sync_state WHERE shop_id=:shop AND source_shop_id=:source',$params);
    $start=FinancePolicy::date($range['start'])->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    $end=FinancePolicy::date($range['end'])->modify('+1 day')->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    $row=$this->one('SELECT COUNT(*) transactions,SUM(actual_price) amount FROM ad_balance_topups WHERE shop_id=:shop AND source_shop_id=:source AND occurred_at_utc>=:start AND occurred_at_utc<:end',$params+['start'=>$start,'end'=>$end]);
    $updated=$sync['synced_at'] ?? null;
    $through=$updated ? (new DateTimeImmutable($updated,new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Asia/Jakarta'))->format('Y-m-d') : null;
    $complete=!empty($sync['backfill_complete']) && $through && $range['start']>='2026-08-01' && $range['end']<=$through && empty($sync['last_error']);
    $schedule=$this->one("SELECT interval_seconds,enabled FROM sync_schedules WHERE shop_id=:shop AND sync_type='ads_topups'",['shop'=>(int)$shop['id']]);
    $interval=(int)($schedule['interval_seconds'] ?? 86400);
    return ['amount'=>$row['transactions'] ? (float)$row['amount'] : ($complete ? 0 : null),'transactions'=>(int)$row['transactions'],
      'complete'=>(bool)$complete,'updated_at'=>$updated,'history_start'=>'2026-08-01','interval_seconds'=>$interval,
      'enabled'=>!empty($schedule['enabled']),'stale'=>$updated && time()-strtotime($updated.' UTC')>$interval,
      'error'=>!empty($sync['last_error'])];
  }

  private function statusFields(): string {
    return "ps.state snapshot_state,ps.synced_at status_synced_at,o.detail_synced_at,i.completed_at,
      COALESCE(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(IF(JSON_VALID(o.raw_data),o.raw_data,'{}'),'$.status_info_v2.status_description.description_value')),'null'),
        NULLIF(JSON_UNQUOTE(JSON_EXTRACT(IF(JSON_VALID(o.raw_data),o.raw_data,'{}'),'$.status_info.status_description.description_value')),'null'),o.status_description) status_description";
  }

  public function summary(array $shops, array $range): array {
    $stores=[];
    foreach ($shops as $shop) {
      $params=['shop'=>(int)$shop['id'],'source'=>(int)$shop['shop_id']];
      $pending=$this->one("SELECT i.id,i.completed_at,i.overview_pending,COALESCE(SUM(r.income_amount),0) detail_amount,COUNT(r.external_order_id) orders FROM finance_current c JOIN finance_imports i ON i.id=c.import_id AND i.state='ready' AND i.source_shop_id=:source LEFT JOIN finance_income_rows r ON r.import_id=i.id WHERE c.shop_id=:shop GROUP BY i.id,i.completed_at,i.overview_pending",$params);
      $rp=$params+['start'=>$range['start'],'end'=>$range['end']];
      $release=$this->one("SELECT COUNT(DISTINCT d.income_date) days,COUNT(r.external_order_id) orders,COALESCE(SUM(r.income_amount),0) amount,SUM(r.adjustment_amount) adjustment,COUNT(r.adjustment_amount) adjustment_known,MIN(i.completed_at) oldest_update FROM finance_days d JOIN finance_imports i ON i.id=d.import_id AND i.state='ready' AND i.source_shop_id=:source LEFT JOIN finance_income_rows r ON r.import_id=d.import_id AND r.released_date=d.income_date WHERE d.shop_id=:shop AND d.income_date BETWEEN :start AND :end",$rp);
      $releasedDetail=(int)$release['days'] ? (int)$release['amount']/100000 : null;
      $released=$releasedDetail; $basis='detail'; $releaseDays=(int)$release['days']; $releaseUpdated=$release['oldest_update'];
      $overviewTotals=$pending ? $this->one('SELECT * FROM finance_overview_totals WHERE import_id=:id',['id'=>(int)$pending['id']]) : null;
      if ($overviewTotals && $range['end']===$overviewTotals['as_of_date']) {
        foreach (['month','week'] as $period) if ($range['start']===$overviewTotals[$period.'_start'] && $overviewTotals[$period.'_amount']!==null) {
          $released=(int)$overviewTotals[$period.'_amount']/100000; $basis='overview_'.$period; $releaseDays=$range['days']; $releaseUpdated=$pending['completed_at']; break;
        }
      }
      $gmv=$this->one("SELECT COUNT(DISTINCT metric_date) days,SUM(paid_gmv) amount,MAX(synced_at) updated_at FROM shop_performance_daily WHERE shop_id=:shop AND metric_date BETWEEN :start AND :end AND source='homepage' AND paid_gmv IS NOT NULL",['shop'=>(int)$shop['id'],'start'=>$range['start'],'end'=>$range['end']]);
      $work=$this->one("SELECT SUM(state IN ('queued','running')) active,SUM(state='failed') failed FROM finance_imports WHERE shop_id=:shop AND source_shop_id=:source AND (category=1 OR (start_date<=:end AND end_date>=:start))",$rp);
      $latest=$this->one("SELECT f.error_message FROM finance_imports f WHERE f.shop_id=:shop AND f.source_shop_id=:source AND f.state='failed' AND (f.category=1 OR (f.start_date<=:end AND f.end_date>=:start)) AND NOT EXISTS (SELECT 1 FROM finance_imports newer WHERE newer.shop_id=f.shop_id AND newer.source_shop_id=f.source_shop_id AND newer.category=f.category AND newer.state='ready' AND newer.id>f.id AND newer.start_date<=f.start_date AND newer.end_date>=f.end_date) ORDER BY f.id DESC LIMIT 1",$rp);
      $states=['shipping'=>0,'return'=>0,'delivered'=>0,'unknown'=>0];
      $stateCounts=array_fill_keys(array_keys($states),0); $statusTimes=[];
      if ($pending) {
        $rows=$this->rows('SELECT r.income_amount,r.status_key,'.$this->statusFields().' FROM finance_income_rows r JOIN finance_imports i ON i.id=r.import_id LEFT JOIN finance_pending_states ps ON ps.import_id=r.import_id AND ps.external_order_id=r.external_order_id LEFT JOIN orders o ON o.id=r.external_order_id AND o.shop_id=r.shop_id WHERE r.import_id=:id',['id'=>(int)$pending['id']]);
        foreach ($rows as $row) {
          $state=FinancePolicy::pendingState($row); $states[$state]+=(int)$row['income_amount']; $stateCounts[$state]++;
          $time=$row['status_key']==='ps_content_return_processing' ? $row['completed_at'] : ($row['status_synced_at'] ?? ($state!=='unknown' ? $row['detail_synced_at'] : null));
          if ($time) $statusTimes[]=$time;
        }
      }
      $stores[]=[
        'id'=>(int)$shop['id'],'name'=>$shop['name'],'logo'=>$shop['shop_logo'],
        'pending'=>$pending ? (int)($pending['overview_pending'] ?? $pending['detail_amount'])/100000 : null,
        'pending_detail'=>$pending ? (int)$pending['detail_amount']/100000 : null,
        'pending_orders'=>$pending ? (int)$pending['orders'] : null,'pending_updated'=>$pending['completed_at'] ?? null,
        'released'=>$released,'released_days'=>$releaseDays,'released_basis'=>$basis,'released_detail'=>$releasedDetail,'released_detail_days'=>(int)$release['days'],
        'released_difference'=>$basis!=='detail' && (int)$release['days']===$range['days'] ? $released-$releasedDetail : null,
        'released_orders'=>(int)$release['orders'],'released_updated'=>$releaseUpdated,
        'adjustment'=>(int)$release['days'] && (int)$release['adjustment_known']===(int)$release['orders'] ? (int)$release['adjustment']/100000 : null,
        'gmv'=>(int)$gmv['days'] ? (float)$gmv['amount'] : null,'gmv_days'=>(int)$gmv['days'],'gmv_updated'=>$gmv['updated_at'],
        'ads'=>$this->ads($shop,$range),'topups'=>$this->topups($shop,$range),'pending_states'=>$pending ? array_map(static fn($v)=>$v/100000,$states) : null,
        'pending_state_counts'=>$pending ? $stateCounts : null,
        'pending_status_updated'=>$statusTimes ? min($statusTimes) : ($pending && !(int)$pending['orders'] ? $pending['completed_at'] : null),
        'active_imports'=>(int)$work['active'],'error'=>$latest['error_message'] ?? null
      ];
    }
    return ['range'=>$range,'stores'=>$stores];
  }

  public function details(array $shops,array $range,int $category,int $page,string $search='',string $state=''): array {
    if (!in_array($state,['','shipping','delivered','return','unknown'],true)) throw new InvalidArgumentException('Pilihan status Pending tidak valid.');
    if ($category!==1 && $state!=='') throw new InvalidArgumentException('Rincian status hanya tersedia untuk Pending.');
    if (!$shops) return ['rows'=>[],'total'=>0,'page'=>1];
    $ids=implode(',',array_map('intval',array_column($shops,'id'))); $params=[];
    if ($category===1) $join='JOIN finance_current c ON c.shop_id=r.shop_id AND c.import_id=r.import_id';
    else { $join='JOIN finance_days d ON d.shop_id=r.shop_id AND d.import_id=r.import_id AND d.income_date=r.released_date AND d.income_date BETWEEN :start AND :end'; $params=['start'=>$range['start'],'end'=>$range['end']]; }
    $where="r.shop_id IN ({$ids})";
    if ($search!=='') { $where.=' AND r.order_sn LIKE :search'; $params['search']='%'.addcslashes(mb_substr($search,0,100),'%_\\').'%'; }
    $from="FROM finance_income_rows r {$join} JOIN finance_imports i ON i.id=r.import_id AND i.state='ready' JOIN shops s ON s.id=r.shop_id AND s.shop_id=i.source_shop_id LEFT JOIN finance_pending_states ps ON ps.import_id=r.import_id AND ps.external_order_id=r.external_order_id LEFT JOIN orders o ON o.id=r.external_order_id AND o.shop_id=r.shop_id WHERE {$where}";
    $select='SELECT r.*,s.name shop_name,'.$this->statusFields().' '.$from.' ORDER BY COALESCE(r.released_at,i.completed_at) DESC,r.external_order_id DESC';
    if ($state!=='') {
      $rows=array_values(array_filter($this->rows($select,$params),static fn($r)=>FinancePolicy::pendingState($r)===$state));
      $total=count($rows); $page=max(1,min($page,max(1,(int)ceil($total/25)))); $rows=array_slice($rows,($page-1)*25,25);
    } else {
      $total=(int)$this->one('SELECT COUNT(*) total '.$from,$params)['total'];
      $page=max(1,min($page,max(1,(int)ceil($total/25)))); $offset=($page-1)*25;
      $rows=$this->rows($select." LIMIT 25 OFFSET {$offset}",$params);
    }
    require_once __DIR__.'/FinanceCost.php'; $costs=new FinanceCost();
    foreach ($rows as &$row) {
      $row['external_order_id']=(string)$row['external_order_id'];
      $row['state']=$category===1 ? FinancePolicy::pendingState($row) : 'released';
      foreach (['income_amount','adjustment_amount','net_amount'] as $key) $row[$key]=$row[$key]===null ? null : (int)$row[$key]/100000;
      $row['cost']=$costs->orderCost((int)$row['shop_id'],(string)$row['external_order_id']);
    }
    return ['rows'=>$rows,'total'=>$total,'page'=>$page];
  }
}
