<?php
chdir(__DIR__.'/../public');require '../app/init.php';
require '../app/models/AccountWorkspace.php';require '../app/models/QuickSearch.php';require '../app/models/Customer.php';require '../app/models/BackgroundSync.php';require '../app/helpers/SyncPresentation.php';
$db=new Database();$checks=0;
function comfortCheck($ok,$message){global $checks;$checks++;if(!$ok)throw new RuntimeException($message);}
function comfortSql($sql){global $db;$db->query($sql);return $db->getAll();}
class WorkspaceFixture extends AccountWorkspace {public function __construct($db){$this->db=$db;}}
class SearchFixture extends QuickSearch {public function __construct($db){$this->db=$db;}}
class CustomerComfortFixture extends Customer {public function __construct($db){$this->db=$db;}}
class SyncComfortFixture extends BackgroundSync {public function __construct($db){$this->db=$db;}public function ensureSchedules(){}}
$tables=['shops','products','product_models','customers','customer_shops','orders','order_items','sync_schedules','sync_jobs','sync_job_orders','sync_pages'];
try{
 foreach($tables as $table){$schema=comfortSql("SHOW CREATE TABLE {$table}")[0]['Create Table'];$schema=preg_replace('/^CREATE TABLE/','CREATE TEMPORARY TABLE',$schema);$schema=preg_replace('/^\s*CONSTRAINT.*\n/m','',$schema);comfortSql(str_replace(",\n)","\n)",$schema));}
 comfortSql(str_replace('CREATE TABLE','CREATE TEMPORARY TABLE',file_get_contents('../database/migrations/20260930_workspace.sql')));
 comfortSql("INSERT INTO shops(id,name,cookie) VALUES(1,'Shop one','fixture'),(2,'Shop two','fixture')");
 $w=new WorkspaceFixture($db);$w->save(1,'orders',['shop_id'=>2,'page'=>3,'limit'=>20,'startDate'=>'2026-09-01','scroll_y'=>420]);
 $saved=$w->allFor(1);comfortCheck($saved['global']['shop_id']===2 && $saved['orders']['scroll_y']===420,'Account preferences persist');comfortCheck($w->allFor(2)===[],'Preferences isolated per account');
 $w->save(1,'orders',['shop_id'=>1,'page'=>1],1);comfortCheck($w->allFor(1)['orders']['shop_id']===2 && $w->allFor(1)['global']['shop_id']===2,'Late older-tab save cannot overwrite newer preferences');
 $r=WorkspacePolicy::restore('orders',[],$saved['orders'],1);comfortCheck($r['shop_id']===1 && !isset($r['page']),'Store switch resets saved pagination');
 $r=WorkspacePolicy::restore('orders',['shop_id'=>2,'page'=>5,'order_id'=>17],$saved['orders'],1);comfortCheck($r['page']===5 && $r['shop_id']===2 && !isset($r['scroll_y']),'Explicit destination wins and scroll is not a query');
 $clean=WorkspacePolicy::clean('orders',['limit'=>9,'page'=>-1,'startDate'=>'2026-02-30','cookie'=>'private','shop_id'=>2]);comfortCheck($clean===['shop_id'=>2],'Invalid and unknown preferences removed');
 $w->save(2,'orders',['shop_id'=>999]);comfortCheck(!isset($w->allFor(2)['global']),'Unknown store is not persisted globally');
 comfortSql("INSERT INTO products(id,shop_id,name,parent_sku,status) VALUES(101,1,'Blue scarf','SCARF-1',1),(102,2,'Blue scarf second','SCARF-2',1),(103,1,'Literal % test','PCT',1)");
 comfortSql("INSERT INTO product_models(id,shop_id,product_id,name,sku) VALUES(301,1,101,'Variant','VARIANT-UNIQUE')");
 comfortSql("INSERT INTO customers(id,username,created_at) VALUES(501,'customer-blue','2026-09-01')");comfortSql("INSERT INTO customer_shops(customer_id,shop_id) VALUES(501,1),(501,2)");
 comfortSql("INSERT INTO orders(id,shop_id,order_sn,tracking_number,buyer_username,created_at,status_type) VALUES(201,1,'ORDER-BLUE','RESI-UNIQUE','customer-blue','2026-09-01','Perlu Dikirim'),(202,2,'ORDER-BLUE-2','OTHER','customer-blue','2026-09-02','Shipped')");
 $search=new SearchFixture($db);comfortCheck(count($search->lookup('Blue',1))===3,'Search scopes products and order per store');
 $rows=$search->lookup('RESI-UNIQUE',0);comfortCheck(count($rows)===1 && str_contains($rows[0]['path'],'order_id=201'),'Tracking search opens exact order');
 $rows=$search->lookup('VARIANT-UNIQUE',1);comfortCheck(count($rows)===1 && str_contains($rows[0]['path'],'highlight=101'),'Variant SKU finds its product');
 comfortCheck(count($search->lookup('% test',0))===1,'Search treats wildcard literally');comfortCheck($search->lookup("' OR 1=1 --",0)===[],'Bound search does not become SQL');
 $rows=$search->lookup('customer-blue',1);comfortCheck(count($rows)===1 && str_contains($rows[0]['path'],'customer_id=501'),'Customer search preserves store');
 $c=new CustomerComfortFixture($db);comfortCheck(count($c->findWithOrderStats(1,0,1,501))===1 && $c->findWithOrderStats(1,0,99,501)===[],'Customer destination remains scoped');
 $now=time();$row=['shop_id'=>1,'sync_type'=>'orders','enabled'=>1,'job_status'=>'running','job_page_number'=>0,'detail_total'=>100,'detail_done'=>20,'detail_failed'=>0,'recent_done'=>10,'recent_first_at'=>gmdate('Y-m-d H:i:s',$now-120),'last_detail_at'=>gmdate('Y-m-d H:i:s',$now-10)];
 $p=SyncPresentation::describe($row,$now);comfortCheck($p['percent']===20 && $p['eta_seconds']===960,'Progress and ETA use completed detail samples');
 $p=SyncPresentation::describe(array_replace($row,['job_page_number'=>2]),$now);comfortCheck($p['percent']===null && $p['eta_seconds']===null,'Open discovery has no invented denominator');
 $p=SyncPresentation::describe(array_replace($row,['last_detail_at'=>gmdate('Y-m-d H:i:s',$now-180)]),$now);comfortCheck($p['eta_seconds']===null,'Stalled progress hides ETA');
 $p=SyncPresentation::describe(array_replace($row,['detail_failed'=>1]),$now);comfortCheck($p['eta_seconds']===null && $p['detail_failed']===1,'Failures are not successful progress');
 $p=SyncPresentation::describe(array_replace($row,['enabled'=>0,'job_status'=>'completed']),$now);comfortCheck($p['stage']==='Jadwal dijeda','Disabled schedule is not labelled active');
 $p=SyncPresentation::describe(array_replace($row,['job_error'=>'Forbidden 403']),$now);comfortCheck($p['action_path']==='/panel/shops#shop-1' && !str_contains($p['message'],'Forbidden'),'Recovery is actionable without raw errors');
 comfortSql("INSERT INTO sync_schedules(shop_id,sync_type,interval_seconds,enabled) VALUES(1,'orders',180,1)");
 comfortSql("INSERT INTO sync_jobs(id,shop_id,channel_id,sync_type,idempotency_key,status,page_number,total_detail,processed_detail) VALUES(701,1,1,'orders','fixture','running',0,0,0),(700,1,1,'orders','fixture-old','completed',0,100,100)");
 comfortSql("INSERT INTO sync_job_orders(job_id,order_id,status,completed_at) VALUES(701,201,'done',UTC_TIMESTAMP()),(701,202,'queued',NULL),(700,201,'done',UTC_TIMESTAMP())");
 $sync=new SyncComfortFixture($db);$status=$sync->status(1)[0];comfortCheck($status['job_id']==701 && $status['presentation']['detail_total']===2 && $status['presentation']['percent']===50,'Latest job detail rows outrank zero cached counters and historical batches');
 echo "PASS: {$checks} workspace, scoped search, destinations and honest sync progress checks\n";
}finally{foreach(array_reverse(array_merge($tables,['account_workspace']))as $table)comfortSql("DROP TEMPORARY TABLE IF EXISTS {$table}");}
