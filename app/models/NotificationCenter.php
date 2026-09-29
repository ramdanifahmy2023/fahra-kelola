<?php
require_once __DIR__.'/StockAlert.php';
require_once __DIR__.'/../helpers/NotificationPolicy.php';

class NotificationCenter extends StockAlert {
  private $notificationSchemaReady = false;

  public function ensureSchema() {
    parent::ensureSchema();
    if ($this->notificationSchemaReady) return;
    foreach (explode(';',file_get_contents(__DIR__.'/../../database/migrations/20260929_notifications.sql')) as $sql) {
      if (trim($sql)) { $this->db->query($sql); $this->db->exe(); }
    }
    $this->notificationSchemaReady = true;
  }

  private function rows(string $sql, array $params=[]): array {
    $this->db->query($sql);
    foreach ($params as $key=>$value) $this->db->bind($key,$value);
    return $this->db->getAll();
  }

  public function refreshOperations(bool $force=false): void {
    $this->ensureSchema();
    $lock='shopdash-notify-'.substr(hash('sha256',DB_NAME),0,24);
    $held=$this->rows('SELECT GET_LOCK(:name,0) held',['name'=>$lock])[0]['held'] ?? 0;
    if (!$held) return;
    $transaction=false;
    try {
      $check=$this->rows('SELECT checked_at FROM notification_checks WHERE id=1')[0]['checked_at'] ?? null;
      $now=time();
      if (!$force && $check && $now-(NotificationPolicy::utc($check) ?? 0)<30) return;
      $shops=$this->rows("SELECT id,name,(cookie IS NULL OR TRIM(cookie)='') cookie_missing FROM shops");
      $schedules=$this->rows("SELECT s.*, j.completed_at outcome_at,
        (SELECT MIN(j0.created_at) FROM sync_jobs j0 WHERE j0.shop_id=s.shop_id AND CAST(j0.sync_type AS BINARY)=CAST(s.sync_type AS BINARY)) first_job_at,
        (SELECT MAX(o.completed_at) FROM sync_job_orders o JOIN sync_jobs jo ON jo.id=o.job_id WHERE jo.shop_id=s.shop_id AND CAST(jo.sync_type AS BINARY)=CAST(s.sync_type AS BINARY) AND jo.status IN ('running','queued') AND o.status='done') detail_progress,
        (SELECT MAX(p.completed_at) FROM sync_pages p JOIN sync_jobs jp ON jp.id=p.job_id WHERE jp.shop_id=s.shop_id AND CAST(jp.sync_type AS BINARY)=CAST(s.sync_type AS BINARY) AND jp.status IN ('running','queued') AND p.status='done') page_progress
        FROM sync_schedules s LEFT JOIN sync_jobs j ON j.id=(SELECT MAX(j2.id) FROM sync_jobs j2 WHERE j2.shop_id=s.shop_id AND CAST(j2.sync_type AS BINARY)=CAST(s.sync_type AS BINARY) AND j2.status IN ('completed','failed'))");
      $byShop=[];
      foreach ($schedules as $row) {
        $row['progress_at']=max($row['detail_progress'] ?? '',$row['page_progress'] ?? '') ?: null;
        $byShop[(int)$row['shop_id']][$row['sync_type']]=$row;
      }
      $orders=$this->rows("SELECT o.id,o.shop_id,o.order_sn,o.status_type,o.ship_by_date,o.detail_synced_at FROM orders o
        WHERE LOWER(TRIM(o.status_type)) IN ('perlu dikirim','ready_to_ship','ready to ship','unshipped')
        OR EXISTS (SELECT 1 FROM alerts a WHERE a.type='shipping_deadline' AND a.entity_type='order' AND a.shop_id=o.shop_id AND a.entity_id=CAST(o.id AS CHAR) AND a.resolved_at IS NULL)");
      $this->db->begin(); $transaction=true;
      foreach ($shops as $shop) {
        $shopId=(int)$shop['id']; $schedule=$byShop[$shopId] ?? [];
        $issues=[]; $source=null;
        if ($shop['cookie_missing']) $issues['shops']='session';
        foreach ($schedule as $type=>$row) {
          if (!$row['enabled']) continue;
          $issue=NotificationPolicy::accessIssue($row['last_error']);
          if ($issue) { $issues[$type]=$issue; $source=max($source ?? '',$row['outcome_at'] ?? '') ?: null; }
        }
        if ($issues) {
          $global=isset($issues['shops']);
          $session=in_array('session',$issues,true);
          $names=array_map(static fn($type)=>NotificationPolicy::MODULES[$type] ?? $type,array_keys($issues));
          $this->putAlert($shopId,'connection','shop',(string)$shopId,$session?'urgent':'warning',[
            'title'=>$global?'Koneksi toko perlu diperbarui':($session?'Sesi modul perlu diperbarui':'Akses modul dibatasi'),
            'message'=>$global?'Perbarui sesi toko agar data dapat disinkronkan.':implode(', ',$names).'. Periksa akses modul terkait.',
            'action_label'=>$session?'Periksa koneksi':'Periksa akses', 'path'=>$session?'/panel/shops#shop-'.$shopId:'/panel/sync?shop_id='.$shopId,
            'icon'=>'link_off','stage'=>0,'source_at'=>$shop['cookie_missing']?gmdate('Y-m-d H:i:s',$now):$source,'modules'=>array_keys($issues)
          ]);
        } else {
          $old=$this->rows("SELECT payload,COALESCE(changed_at,first_seen_at) first_seen_at FROM alerts WHERE shop_id=:shop AND type='connection' AND resolved_at IS NULL",['shop'=>$shopId]);
          foreach ($old as $alert) {
            $modules=json_decode($alert['payload'] ?? '{}',true)['modules'] ?? ['shops'];
            $recovered=true;
            foreach ($modules as $type) {
              $row=$schedule[$type] ?? [];
              if (empty($row['last_success_at']) || $row['last_success_at']<$alert['first_seen_at'] || !empty($row['last_error'])) $recovered=false;
            }
            if ($recovered) $this->resolveAlert($shopId,'connection',(string)$shopId);
          }
        }
        foreach (['orders','products'] as $type) {
          if (!isset($schedule[$type])) continue;
          // The connection incident already identifies the cause of these delayed updates.
          $result=isset($issues['shops']) || isset($issues[$type]) ? false : NotificationPolicy::sync($schedule[$type],$now);
          if ($result === false) $this->resolveAlert($shopId,'sync_stale',$type);
          elseif ($result) $this->putAlert($shopId,'sync_stale','sync',$type,$result['severity'],$result);
        }
      }
      foreach ($orders as $order) {
        $result=NotificationPolicy::shipping($order,$now);
        if ($result === false) $this->resolveAlert((int)$order['shop_id'],'shipping_deadline',(string)$order['id']);
        elseif ($result) $this->putAlert((int)$order['shop_id'],'shipping_deadline','order',(string)$order['id'],$result['severity'],$result);
      }
      $this->db->query('INSERT INTO notification_checks (id,checked_at) VALUES (1,UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE checked_at=VALUES(checked_at)'); $this->db->exe();
      $this->db->commit(); $transaction=false;
    } finally {
      if ($transaction) $this->db->rollback();
      $this->rows('SELECT RELEASE_LOCK(:name)',['name'=>$lock]);
    }
  }

  private function unreadExpression(): string {
    // Preserve the old shared read state once; future receipts are per operator/revision.
    return '(COALESCE(r.revision,IF(a.acknowledged_at IS NOT NULL,a.revision,0)) < a.revision)';
  }

  private function visibleWhere(int $userId): string {
    return 'a.resolved_at IS NULL AND (a.silenced_until IS NULL OR a.silenced_until<=UTC_TIMESTAMP()) AND NOT EXISTS (SELECT 1 FROM notification_snoozes z WHERE z.alert_id=a.id AND z.user_id='.(int)$userId.' AND z.revision=a.revision AND z.snoozed_until>UTC_TIMESTAMP())';
  }

  public function overview(int $userId): array {
    $unread=$this->unreadExpression();
    $row=$this->rows("SELECT COUNT(*) total,COALESCE(SUM(a.severity='urgent'),0) urgent,
      COALESCE(SUM({$unread}),0) unread,COALESCE(SUM({$unread} AND a.severity='urgent'),0) unread_urgent
      FROM alerts a JOIN shops s ON s.id=a.shop_id LEFT JOIN notification_receipts r ON r.alert_id=a.id AND r.user_id=:user
      WHERE ".$this->visibleWhere($userId),['user'=>$userId])[0];
    return array_map('intval',$row);
  }

  public function notifications(int $userId, bool $unreadOnly=true, int $limit=10, int $offset=0, array $filters=[]): array {
    $limit=max(1,min(100,$limit));$offset=max(0,$offset);$where=$this->visibleWhere($userId);
    if ($unreadOnly) $where.=' AND '.$this->unreadExpression();
    $where.=$this->filterWhere($filters);
    $rows=$this->rows("SELECT a.*,s.name shop_name,s.shop_logo,p.name product_name,p.total_stock,".$this->unreadExpression()." is_unread
      FROM alerts a JOIN shops s ON s.id=a.shop_id LEFT JOIN products p ON a.entity_type='product' AND p.id=a.entity_id AND p.shop_id=a.shop_id
      LEFT JOIN notification_receipts r ON r.alert_id=a.id AND r.user_id=:user WHERE {$where}
      ORDER BY (a.severity='urgent') DESC,COALESCE(a.changed_at,a.first_seen_at) DESC,a.id DESC LIMIT {$limit} OFFSET {$offset}",['user'=>$userId]);
    return array_map(function($row) {
      $payload=json_decode($row['payload'] ?? '{}',true) ?: [];
      if ($row['type']==='low_stock') {
        $payload=array_merge($payload,['title'=>$row['severity']==='urgent'?'Stok habis':'Stok kritis','message'=>($row['product_name'] ?: 'Produk').' · stok '.(int)$row['total_stock'],
          'icon'=>'inventory_2','path'=>'/panel/products?shop_id='.(int)$row['shop_id'].'&stock=critical&highlight='.rawurlencode($row['entity_id']),'action_label'=>'Periksa produk']);
      }
      $until=NotificationPolicy::utc($payload['valid_until'] ?? null);
      return ['id'=>(int)$row['id'],'revision'=>(int)$row['revision'],'shop_id'=>(int)$row['shop_id'],'shop_name'=>$row['shop_name'],'shop_logo'=>$row['shop_logo'],
        'type'=>$row['type'],'severity'=>$row['severity'],'unread'=>(bool)$row['is_unread'],'title'=>$payload['title'] ?? 'Perlu diperiksa',
        'message'=>$payload['message'] ?? $row['next_action'],'action_label'=>$payload['action_label'] ?? 'Periksa',
        'path'=>$payload['path'] ?? '/panel','icon'=>$payload['icon'] ?? 'notifications',
        'changed_at'=>$row['changed_at'] ?? $row['first_seen_at'],'source_at'=>(array_key_exists('source_at',$payload)?$payload['source_at']:$row['last_seen_at']),
        'deadline'=>$payload['deadline'] ?? null,'stale'=>$until !== null && $until<time()];
    },$rows);
  }

  private function filterWhere(array $filters): string {
    $where='';
    if (!empty($filters['urgent'])) $where.=" AND a.severity='urgent'";
    if (!empty($filters['shop_id'])) $where.=' AND a.shop_id='.(int)$filters['shop_id'];
    if (!empty($filters['type']) && in_array($filters['type'],['low_stock','shipping_deadline','connection','sync_stale','chat_incoming'],true)) $where.=" AND a.type='".$filters['type']."'";
    return $where;
  }

  public function groups(int $userId,bool $unreadOnly,int $limit,int $offset,array $filters=[]): array {
    $where=$this->visibleWhere($userId).$this->filterWhere($filters);
    if ($unreadOnly) $where.=' AND '.$this->unreadExpression();
    $rows=$this->rows("SELECT a.shop_id,a.type,s.name shop_name,s.shop_logo,COUNT(*) total,SUM(".$this->unreadExpression().") unread,SUM(a.severity='urgent') urgent,MAX(COALESCE(a.changed_at,a.first_seen_at)) changed_at
      FROM alerts a JOIN shops s ON s.id=a.shop_id LEFT JOIN notification_receipts r ON r.alert_id=a.id AND r.user_id=:user
      WHERE {$where} GROUP BY a.shop_id,a.type,s.name,s.shop_logo ORDER BY (SUM(a.severity='urgent')>0) DESC,MAX(COALESCE(a.changed_at,a.first_seen_at)) DESC,a.shop_id,a.type",['user'=>$userId]);
    $count=count($rows);$rows=array_slice($rows,max(0,$offset),max(1,min(100,$limit)));
    foreach ($rows as &$row) foreach (['shop_id','total','unread','urgent'] as $key) $row[$key]=(int)$row[$key];unset($row);
    return ['groups'=>$rows,'group_count'=>$count,'has_more'=>$count>$offset+count($rows)];
  }

  public function filteredCount(int $userId,bool $unreadOnly,array $filters): int {
    $where=$this->visibleWhere($userId).$this->filterWhere($filters);if($unreadOnly)$where.=' AND '.$this->unreadExpression();
    return (int)$this->rows('SELECT COUNT(*) total FROM alerts a JOIN shops s ON s.id=a.shop_id LEFT JOIN notification_receipts r ON r.alert_id=a.id AND r.user_id=:user WHERE '.$where,['user'=>$userId])[0]['total'];
  }

  public function snooze(int $userId,array $items,int $seconds=3600): void {
    $seconds=max(300,min(86400,$seconds));
    foreach ($items as $item) {
      $this->db->query("INSERT INTO notification_snoozes (alert_id,user_id,revision,snoozed_until)
        SELECT id,:user,revision,DATE_ADD(UTC_TIMESTAMP(),INTERVAL {$seconds} SECOND) FROM alerts WHERE id=:id AND revision=:revision AND resolved_at IS NULL
        ON DUPLICATE KEY UPDATE snoozed_until=IF(VALUES(revision)>=notification_snoozes.revision,VALUES(snoozed_until),snoozed_until),revision=GREATEST(notification_snoozes.revision,VALUES(revision))");
      foreach (['user'=>$userId,'id'=>$item['id'],'revision'=>$item['revision']] as $key=>$value) $this->db->bind($key,$value);$this->db->exe();
    }
  }

  public function markRead(int $userId, array $items): void {
    foreach ($items as $item) {
      $this->db->query('INSERT INTO notification_receipts (alert_id,user_id,revision,read_at)
        SELECT id,:user,revision,UTC_TIMESTAMP() FROM alerts WHERE id=:id AND revision=:revision AND resolved_at IS NULL
        ON DUPLICATE KEY UPDATE revision=GREATEST(notification_receipts.revision,VALUES(revision)),read_at=UTC_TIMESTAMP()');
      foreach (['user'=>$userId,'id'=>$item['id'],'revision'=>$item['revision']] as $key=>$value) $this->db->bind($key,$value);
      $this->db->exe();
    }
  }
}
