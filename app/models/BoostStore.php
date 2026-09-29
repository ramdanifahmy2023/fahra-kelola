<?php
require_once __DIR__.'/../helpers/BoostPolicy.php';

class BoostStore extends BaseModel {
  private $held = [];
  private $lockConnection;
  public function __construct($db = null) { $this->db = $db ?: new Database(); }
  private function query(string $sql, array $values = []): void {
    $this->db->query($sql);
    foreach ($values as $key=>$value) $this->db->bind($key,$value);
  }
  private function write(string $sql, array $values = []): void { $this->query($sql,$values); $this->db->exe(); }
  private function one(string $sql, array $values = []): array { $this->query($sql,$values); return $this->db->single() ?: []; }
  private function rows(string $sql, array $values = []): array { $this->query($sql,$values); return $this->db->getAll(); }

  public function ready(): bool {
    $row=$this->one("SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ('product_boost_runs','product_boost_items','boost_profiles','boost_run_meta','boost_item_events','boost_worker_health')");
    return (int)$row['c']===6;
  }
  public function migrate(): void {
    foreach (['20260927_product_boost.sql','20260930_boost_automation.sql'] as $name) {
      $sql=preg_replace('/^--.*$/m','',file_get_contents(__DIR__.'/../../database/migrations/'.$name));
      foreach(explode(';',$sql) as $statement) if(trim($statement)) $this->write($statement);
    }
  }
  public function shop(int $id): array {
    $shop=$this->one('SELECT id,shop_id,name,cookie,sync_status FROM shops WHERE id=:id',['id'=>$id]);
    if (!$shop) throw new BoostError('Toko tidak ditemukan.',404);
    return $shop;
  }
  public function profile(int $id, bool $lock = false): array {
    $p=$this->one('SELECT * FROM boost_profiles WHERE shop_id=:id'.($lock?' FOR UPDATE':''),['id'=>$id]);
    if (!$p) return ['shop_id'=>$id,'version'=>0,'enabled'=>false,'product_ids'=>[],'next_check_at'=>null,'last_checked_at'=>null,'last_message'=>null,'failure_count'=>0];
    $p['product_ids']=json_decode($p['product_ids'],true) ?: [];
    $p['enabled']=(bool)$p['enabled'];$p['version']=(int)$p['version'];return $p;
  }
  public function products(int $shop, array $ids): array {
    if (!$ids) return [];
    $params=['shop'=>$shop];$holders=[];
    foreach($ids as $i=>$id){$holders[]=':p'.$i;$params['p'.$i]=(string)$id;}
    $rows=$this->rows('SELECT id,name,shop_id,status,deleted_at,total_stock,sold_count,cover_image,price_min FROM products WHERE shop_id=:shop AND id IN ('.implode(',',$holders).')',$params);
    $map=[];foreach($rows as $p){$p['id']=(string)$p['id'];$map[$p['id']]=$p;}
    return array_map(static fn($id)=>$map[$id] ?? ['id'=>(string)$id,'name'=>'Produk #'.$id,'missing'=>true],$ids);
  }
  public function catalog(int $shop, string $search, int $page): array {
    $values=['shop'=>$shop];$where='shop_id=:shop AND status=1 AND deleted_at IS NULL';
    if($search!==''){$where.=' AND name LIKE :search';$values['search']='%'.$search.'%';}
    $total=(int)$this->one('SELECT COUNT(*) AS c FROM products WHERE '.$where,$values)['c'];
    $page=max(1,min($page,max(1,(int)ceil($total/10))));
    $rows=$this->rows('SELECT id,name,shop_id,status,deleted_at,total_stock,sold_count,cover_image,price_min FROM products WHERE '.$where.' ORDER BY sold_count DESC,id DESC LIMIT 10 OFFSET '.(($page-1)*10),$values);
    foreach($rows as &$row)$row['id']=(string)$row['id'];
    return ['products'=>$rows,'total'=>$total,'page'=>$page,'pages'=>max(1,(int)ceil($total/10))];
  }
  public function recommendations(int $shop): array {
    $rows=$this->rows('SELECT id,name,shop_id,status,total_stock,sold_count,cover_image,price_min FROM products WHERE shop_id=:shop AND status=1 AND deleted_at IS NULL AND total_stock>0 AND sold_count>0 ORDER BY sold_count DESC,id DESC LIMIT 5',['shop'=>$shop]);
    foreach($rows as &$row)$row['id']=(string)$row['id'];
    return $rows;
  }
  public function save(int $shop, int $version, array $ids, int $actor): array {
    $ids=BoostPolicy::ids($ids,true);
    $this->db->begin();
    try {
      if(!$this->one('SELECT id FROM shops WHERE id=:id FOR UPDATE',['id'=>$shop]))throw new BoostError('Toko tidak ditemukan.',404);
      $p=$this->profile($shop,true);
      if($p['version']!==$version)throw new BoostError('Pengaturan berubah di sesi lain. Muat versi terbaru sebelum menyimpan.',409);
      foreach($this->products($shop,$ids) as $product) {
        $already=in_array($product['id'],$p['product_ids'],true);
        if(!$already && (!empty($product['missing']) || !empty($product['deleted_at']) || (int)$product['status']!==1))throw new BoostError('Ada produk tidak aktif atau bukan milik toko ini.',422);
      }
      $enabled=$p['enabled'] && count($ids)>0;
      $this->write("INSERT INTO boost_profiles (shop_id,product_ids,version,enabled,updated_by,next_check_at,updated_at) VALUES (:shop,:ids,:version,:enabled,:actor,UTC_TIMESTAMP(),UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE product_ids=VALUES(product_ids),version=VALUES(version),enabled=VALUES(enabled),updated_by=VALUES(updated_by),next_check_at=VALUES(next_check_at),updated_at=VALUES(updated_at)",['shop'=>$shop,'ids'=>json_encode($ids),'version'=>$version+1,'enabled'=>$enabled?1:0,'actor'=>$actor]);
      $this->db->commit();return $this->profile($shop);
    } catch(Throwable $e){$this->db->rollback();throw $e;}
  }
  public function toggle(int $shop,int $version,bool $enabled,int $actor): array {
    $this->db->begin();
    try {
      $this->one('SELECT id FROM shops WHERE id=:id FOR UPDATE',['id'=>$shop]);$p=$this->profile($shop,true);
      if(!$p['version'] || $p['version']!==$version)throw new BoostError('Pengaturan berubah. Muat versi terbaru.',409);
      if($enabled && !$p['product_ids'])throw new BoostError('Simpan pilihan produk terlebih dahulu.',422);
      $this->write('UPDATE boost_profiles SET enabled=:enabled,version=version+1,updated_by=:actor,updated_at=UTC_TIMESTAMP(),next_check_at=UTC_TIMESTAMP(),last_message=:message WHERE shop_id=:shop',['enabled'=>$enabled?1:0,'actor'=>$actor,'shop'=>$shop,'message'=>$enabled?'Pengulangan aktif. Menunggu pemeriksaan worker.':'Pengulangan dijeda.']);
      $this->db->commit();return $this->profile($shop);
    }catch(Throwable $e){$this->db->rollback();throw $e;}
  }
  public function lock(int $shop): string {
    // A request that dies must close its lock connection, even with the app's persistent PDO pool.
    if(!$this->lockConnection)$this->lockConnection=new PDO('mysql:host='.DB_HOST.';port='.DB_PORT.';dbname='.DB_NAME,DB_USER,DB_PASS,[PDO::ATTR_PERSISTENT=>false,PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    if(isset($this->held[$shop]))throw new BoostError('Toko sudah dikunci oleh proses ini.',409);
    $name='boost:'.substr(hash('sha256',DB_NAME.':'.$shop),0,50);
    $stmt=$this->lockConnection->prepare('SELECT GET_LOCK(?,0)');$stmt->execute([$name]);
    if((int)$stmt->fetchColumn()!==1)throw new BoostError('Toko sedang diproses. Tunggu hasilnya.',409);
    $this->held[$shop]=$name;return bin2hex(random_bytes(32));
  }
  public function unlock(int $shop): void {
    if(isset($this->held[$shop])){$stmt=$this->lockConnection->prepare('SELECT RELEASE_LOCK(?)');$stmt->execute([$this->held[$shop]]);unset($this->held[$shop]);}
  }
  private function assertLock(int $shop): void {
    if(!$this->lockConnection || !isset($this->held[$shop]))throw new BoostError('Proses tidak memiliki kunci toko.',409);
    $stmt=$this->lockConnection->prepare('SELECT IS_USED_LOCK(?)=CONNECTION_ID()');$stmt->execute([$this->held[$shop]]);
    if(!(bool)$stmt->fetchColumn())throw new BoostError('Kepemilikan proses berakhir. Hasil perlu diperiksa.',409);
  }
  public function summary($shopId): array {
    $r=$this->one("SELECT COUNT(CASE WHEN status IN ('unknown','pending','sending') THEN 1 END) AS unresolved_count, COUNT(CASE WHEN status='success' AND attempted_at>=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 4 HOUR) THEN 1 END) AS used_count, MIN(CASE WHEN status='success' AND attempted_at>=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 4 HOUR) THEN attempted_at END) AS oldest FROM product_boost_items WHERE shop_id=:id",['id'=>(int)$shopId]);
    $run=$this->one("SELECT id FROM product_boost_runs WHERE shop_id=:id AND status='running' LIMIT 1",['id'=>(int)$shopId]);
    $unresolved=(int)$r['unresolved_count'];$used=min(5,(int)$r['used_count']);
    return ['used_count'=>$used,'remaining_count'=>$unresolved?0:max(0,5-$used),'unresolved_count'=>$unresolved,'batch_active'=>(bool)$run,'cooldown_active'=>(bool)$run,
      'quota_reset_at'=>$r['oldest']?gmdate('Y-m-d H:i:s',strtotime($r['oldest'].' UTC')+BoostPolicy::WINDOW_SECONDS):null,'quota_source'=>'local','cooldown_minutes'=>255];
  }
  public function productCooldowns($shopId,array $ids,$minutes=255): array {
    if(!$ids)return [];
    $values=['shop'=>(int)$shopId];$holders=[];
    foreach(array_values($ids) as $i=>$id){$holders[]=':product'.$i;$values['product'.$i]=(string)$id;}
    $rows=$this->rows("SELECT product_id,MAX(CASE WHEN status='success' THEN attempted_at END) AS last_at,MAX(status IN ('unknown','pending','sending')) AS unresolved FROM product_boost_items WHERE shop_id=:shop AND product_id IN (".implode(',',$holders).") GROUP BY product_id",$values);
    $result=[];
    foreach($rows as $r){$id=(string)$r['product_id'];
      $at=$r['last_at']?strtotime($r['last_at'].' UTC')+BoostPolicy::COOLDOWN_SECONDS:0;
      $result[$id]=['last_boost_at'=>$r['last_at'],'next_boost_at'=>$at?gmdate('Y-m-d H:i:s',$at):null,'cooldown_active'=>$at>time(),'unresolved'=>(bool)$r['unresolved']];}
    return $result;
  }
  public function recover(int $shop): void {
    $this->assertLock($shop);
    foreach($this->rows("SELECT id FROM product_boost_runs WHERE shop_id=:shop AND status='running'",['shop'=>$shop]) as $run){
      $id=(int)$run['id'];
      $this->write("UPDATE product_boost_items SET status=CASE WHEN status='reserved' THEN 'not_sent' ELSE 'unknown' END,error_message='Proses terputus. Item yang mungkin terkirim perlu diperiksa.' WHERE run_id=:id AND status IN ('pending','sending','reserved')",['id'=>$id]);
      $this->finishRun($id);
    }
  }
  public function previous(int $shop,string $key,string $hash): ?array {
    $r=$this->one('SELECT run_id,request_hash FROM boost_run_meta WHERE shop_id=:shop AND request_key=:key',['shop'=>$shop,'key'=>$key]);
    if(!$r)return null;
    if(!hash_equals($r['request_hash'],$hash))throw new BoostError('Kunci permintaan sudah dipakai untuk pilihan lain.',409);
    return $this->result((int)$r['run_id']);
  }
  public function reserve(int $shop,array $ids,string $mode,int $version,int $actor,string $key,string $token,string $hash): array {
    $this->assertLock($shop);$this->db->begin();
    try {
      $this->one('SELECT id FROM shops WHERE id=:id FOR UPDATE',['id'=>$shop]);
      $p=$this->profile($shop,true);
      if($mode==='auto' && (!$p['enabled'] || $p['version']!==$version))throw new BoostError('Pengaturan pengulangan berubah atau dijeda.',409);
      $summary=$this->summary($shop);
      if($summary['batch_active'] || $summary['unresolved_count'])throw new BoostError('Periksa hasil sebelumnya sebelum menaikkan produk lagi.',409);
      $cooldowns=$this->productCooldowns($shop,$ids);$eligible=[];
      foreach($this->products($shop,$ids) as $product)if(!BoostPolicy::localReason($product,$cooldowns[$product['id']]??[]))$eligible[]=$product;
      if($mode==='manual' && (count($eligible)!==count($ids) || count($eligible)>$summary['remaining_count']))throw new BoostError('Pilihan berubah, masih dalam jeda, atau melebihi kuota lokal. Muat ulang status.',409);
      $eligible=array_slice($eligible,0,$summary['remaining_count']);
      if(!$eligible){$this->db->commit();return [];}
      $this->write("INSERT INTO product_boost_runs (shop_id,mode,status,selected_count,started_at) VALUES (:shop,:mode,'running',:count,UTC_TIMESTAMP())",['shop'=>$shop,'mode'=>$mode,'count'=>count($eligible)]);
      $run=(int)$this->db->lastId();
      $this->write('INSERT INTO boost_run_meta (run_id,shop_id,request_key,request_hash,profile_version,actor_id,owner_token,lease_until) VALUES (:run,:shop,:key,:hash,:version,:actor,:token,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 2 MINUTE))',['run'=>$run,'shop'=>$shop,'key'=>$key,'hash'=>$hash,'version'=>$version,'actor'=>$actor?:null,'token'=>$token]);
      foreach($eligible as $product)$this->write("INSERT INTO product_boost_items (run_id,shop_id,product_id,product_name,status,attempted_at) VALUES (:run,:shop,:id,:name,'reserved',UTC_TIMESTAMP())",['run'=>$run,'shop'=>$shop,'id'=>$product['id'],'name'=>$product['name']]);
      $this->db->commit();return ['run_id'=>$run,'products'=>$eligible];
    }catch(Throwable $e){$this->db->rollback();throw $e;}
  }
  public function sending(int $shop,int $run,string $id,string $token,string $mode,int $version): void {
    $this->assertLock($shop);$this->db->begin();
    try {
      $this->one('SELECT id FROM shops WHERE id=:shop FOR UPDATE',['shop'=>$shop]);
      $p=$this->profile($shop,true);
      if($mode==='auto' && (!$p['enabled'] || $p['version']!==$version))throw new BoostError('Pengulangan dijeda atau pilihan berubah. Produk ini tidak dikirim.',409);
      $m=$this->one('SELECT owner_token,lease_until>=UTC_TIMESTAMP() AS valid FROM boost_run_meta WHERE run_id=:id FOR UPDATE',['id'=>$run]);
      if(empty($m['valid']) || !hash_equals($m['owner_token'],$token))throw new BoostError('Lease pengiriman berakhir.',409);
      $product=$this->products($shop,[$id])[0];
      if(BoostPolicy::localReason($product,$this->productCooldowns($shop,[$id])[$id]??[]))throw new BoostError('Produk sudah tidak memenuhi syarat.',409);
      $this->write('UPDATE boost_run_meta SET lease_until=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 2 MINUTE) WHERE run_id=:run',['run'=>$run]);
      $this->write("UPDATE product_boost_items SET status='sending',attempted_at=UTC_TIMESTAMP() WHERE run_id=:run AND product_id=:id AND status='reserved'",['run'=>$run,'id'=>$id]);
      if($this->db->row()!==1)throw new BoostError('Item sudah pernah diproses.',409);
      $this->event($run,$id,'sending',0,'Intent sebelum POST');$this->db->commit();
    }catch(Throwable $e){$this->db->rollback();throw $e;}
  }
  public function record(int $run,string $id,string $status,string $message, array $response=[]): void {
    $diagnostic=['http_status'=>(int)($response['http_status']??0),'transport_error'=>(bool)($response['transport_error']??false)];
    if(is_int($response['body']['code']??null))$diagnostic['api_code']=$response['body']['code'];
    $this->write('UPDATE product_boost_items SET status=:status,error_message=:message,response_payload=:payload WHERE run_id=:run AND product_id=:id AND status IN (\'reserved\',\'sending\')',['status'=>$status,'message'=>$message,'payload'=>json_encode($diagnostic),'run'=>$run,'id'=>$id]);
    $this->event($run,$id,$status,0,$message);
  }
  private function event(int $run,string $id,string $state,int $actor,string $reason): void {
    $this->write('INSERT INTO boost_item_events (run_id,product_id,state,actor_id,reason,created_at) VALUES (:run,:id,:state,:actor,:reason,UTC_TIMESTAMP())',['run'=>$run,'id'=>$id,'state'=>$state,'actor'=>$actor?:null,'reason'=>$reason]);
  }
  public function finishRun(int $run): array {
    $c=$this->one("SELECT COUNT(*) AS total,SUM(status='success') AS success_count,SUM(status IN ('failed','not_sent')) AS failed_count,SUM(status IN ('unknown','pending','sending')) AS unknown_count,SUM(status='reserved') AS reserved_count FROM product_boost_items WHERE run_id=:id",['id'=>$run]);
    $status=((int)$c['reserved_count']>0 || (int)$c['unknown_count']>0)?'partial':((int)$c['success_count']===(int)$c['total'] && (int)$c['total']>0?'completed':((int)$c['success_count']>0?'partial':'failed'));
    $this->write('UPDATE product_boost_runs SET status=:status,success_count=:ok,failed_count=:bad,unknown_count=:unknown,completed_at=UTC_TIMESTAMP() WHERE id=:id',['status'=>$status,'ok'=>(int)$c['success_count'],'bad'=>(int)$c['failed_count'],'unknown'=>(int)$c['unknown_count'],'id'=>$run]);
    return $this->result($run);
  }
  public function result(int $run): array {
    $row=$this->one('SELECT id AS run_id,status,success_count,failed_count,unknown_count FROM product_boost_runs WHERE id=:id',['id'=>$run]);
    $row['items']=$this->rows('SELECT product_id,product_name,status,error_message,attempted_at FROM product_boost_items WHERE run_id=:id ORDER BY id',['id'=>$run]);return $row;
  }
  public function history($shopId,$limit=10): array {
    $rows=$this->rows('SELECT id,mode,status,selected_count,success_count,failed_count,unknown_count,started_at,completed_at FROM product_boost_runs WHERE shop_id=:shop ORDER BY id DESC LIMIT '.max(1,min(20,(int)$limit)),['shop'=>(int)$shopId]);
    foreach($rows as &$row)$row['items']=$this->result((int)$row['id'])['items'];return $rows;
  }
  public function unresolved(int $shop): array {
    return $this->rows("SELECT run_id,product_id,product_name,status,attempted_at,error_message FROM product_boost_items WHERE shop_id=:shop AND status IN ('pending','sending','unknown') ORDER BY id DESC LIMIT 100",['shop'=>$shop]);
  }
  public function resolve(int $shop,int $run,string $id,string $outcome,int $actor): void {
    if(!in_array($outcome,['confirmed_sent','confirmed_not_sent'],true))throw new BoostError('Pilih hasil yang sudah diperiksa di Seller Centre.',422);
    $this->lock($shop);
    try {
      $this->recover($shop);
      $item=$this->one("SELECT status FROM product_boost_items WHERE shop_id=:shop AND run_id=:run AND product_id=:id AND status='unknown'",['shop'=>$shop,'run'=>$run,'id'=>$id]);
      if(!$item)throw new BoostError('Hasil sudah berubah. Muat ulang riwayat.',409);
      $this->db->begin();
      try {
        $status=$outcome==='confirmed_sent'?'success':'not_sent';
        $this->write('UPDATE product_boost_items SET status=:status,error_message=:message WHERE shop_id=:shop AND run_id=:run AND product_id=:id',['status'=>$status,'message'=>'Hasil dikonfirmasi pengguna setelah memeriksa Seller Centre.','shop'=>$shop,'run'=>$run,'id'=>$id]);
        $this->event($run,$id,$outcome,$actor,'Konfirmasi pengguna; bukan verifikasi otomatis');
        $this->finishRun($run);$this->write('UPDATE boost_profiles SET next_check_at=UTC_TIMESTAMP() WHERE shop_id=:shop',['shop'=>$shop]);$this->db->commit();
      }catch(Throwable $e){$this->db->rollback();throw $e;}
    }finally{$this->unlock($shop);}
  }
  public function checked(int $shop,int $version,string $message,int $next,bool $failed=false): void {
    $this->write('UPDATE boost_profiles SET last_checked_at=UTC_TIMESTAMP(),last_message=:message,next_check_at=:next,failure_count=CASE WHEN :failed=1 THEN failure_count+1 ELSE 0 END WHERE shop_id=:shop AND version=:version',['message'=>$message,'next'=>gmdate('Y-m-d H:i:s',$next),'shop'=>$shop,'version'=>$version,'failed'=>$failed?1:0]);
  }
  public function due(int $limit=10): array {
    return $this->rows('SELECT shop_id FROM boost_profiles WHERE enabled=1 AND (next_check_at IS NULL OR next_check_at<=UTC_TIMESTAMP()) ORDER BY COALESCE(next_check_at,\'1970-01-01\'),shop_id LIMIT '.max(1,min(20,$limit)));
  }
  public function health(): array {
    return $this->one('SELECT last_tick_at,sender_enabled,last_tick_at>=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 3 MINUTE) AS alive FROM boost_worker_health WHERE id=1');
  }
  public function heartbeat(bool $send): void {
    $this->write('INSERT INTO boost_worker_health (id,last_tick_at,sender_enabled) VALUES (1,UTC_TIMESTAMP(),:send) ON DUPLICATE KEY UPDATE last_tick_at=VALUES(last_tick_at),sender_enabled=VALUES(sender_enabled)',['send'=>$send?1:0]);
  }
}
