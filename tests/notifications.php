<?php
chdir(__DIR__.'/../public');require '../app/init.php';require '../app/models/NotificationCenter.php';
$db=new Database();$checks=0;
class NotificationTestCenter extends NotificationCenter { public function __construct($db){$this->db=$db;} }
function checkNotify($value,$message){global $checks;$checks++;if(!$value)throw new RuntimeException($message);}
function notifySql($sql){global $db;$db->query($sql);return $db->getAll();}
$tables=['alerts','shops','products','orders','sync_schedules','sync_jobs','sync_job_orders','sync_pages'];
try{
 foreach($tables as $table){
  $schema=notifySql("SHOW CREATE TABLE {$table}")[0]['Create Table'];
  $schema=preg_replace('/^CREATE TABLE/','CREATE TEMPORARY TABLE',$schema);
  $schema=preg_replace('/^\s*CONSTRAINT.*\n/m','',$schema);
  notifySql(str_replace(",\n)","\n)",$schema));
 }
 foreach(explode(';',file_get_contents('../database/migrations/20260929_notifications.sql')) as $sql)if(trim($sql))notifySql(str_replace('CREATE TABLE','CREATE TEMPORARY TABLE',$sql));
 $model=new NotificationTestCenter($db);$model->ensureSchema();
 notifySql("INSERT INTO shops(id,name,cookie) VALUES(1,'Toko fixture','fixture-cookie'),(2,'Toko kedua','fixture-cookie')");
 notifySql("INSERT INTO products(id,shop_id,name,status,total_stock) VALUES(11,1,'Produk fixture',1,5)");
 $model->reconcileShop(1);$first=$model->notifications(1)[0];
 checkNotify($first['severity']==='warning' && $first['type']==='low_stock','Initial stock alert');
 $model->markRead(1,[['id'=>$first['id'],'revision'=>$first['revision']]]);
 checkNotify($model->overview(1)['unread']===0 && $model->overview(2)['unread']===1,'Reads isolated by user');
 checkNotify($model->overview(1)['total']===1 && count($model->notifications(1,false))===1,'Read is not resolved');
 notifySql('UPDATE products SET total_stock=0');$model->reconcileShop(1);$urgent=$model->notifications(1)[0];
 checkNotify($urgent['revision']===$first['revision']+1 && $urgent['severity']==='urgent','Escalation resets unread');
 $model->reconcileShop(1);checkNotify($model->notifications(1)[0]['revision']===$urgent['revision'],'Repeated poll does not escalate again');
 $model->markRead(1,[['id'=>$first['id'],'revision'=>$first['revision']]]);
 checkNotify($model->overview(1)['unread']===1,'Stale receipt cannot read newer incident');
 notifySql('UPDATE alerts SET silenced_until=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 HOUR)');
 checkNotify($model->overview(1)['unread']===0 && $model->notifications(1)===[],'Snooze list/count agree');
 notifySql('UPDATE products SET total_stock=30');$model->reconcileShop(1);
 checkNotify($model->overview(1)['total']===0,'Recovered stock resolves');
 notifySql('UPDATE products SET total_stock=3');$model->reconcileShop(1);
 $reopen=$model->notifications(1)[0];checkNotify($reopen['revision']===$urgent['revision']+1,'Reopen resets unread and silence');
 checkNotify((int)notifySql('SELECT occurrence_count FROM alerts')[0]['occurrence_count']===2,'Occurrences count episodes not polling');
 notifySql('UPDATE alerts SET acknowledged_at=UTC_TIMESTAMP()');
 checkNotify($model->overview(99)['unread']===0,'Legacy shared reads preserved for initial revision');
 notifySql('UPDATE products SET total_stock=30');$model->reconcileShop(1);

 $now=time();$order=['id'=>51,'shop_id'=>1,'order_sn'=>'TEST-ORDER','status_type'=>'Perlu Dikirim','ship_by_date'=>$now+3600,'detail_synced_at'=>gmdate('Y-m-d H:i:s',$now-60)];
 checkNotify(NotificationPolicy::shipping($order,$now)['severity']==='urgent','Verified unshipped near deadline');
 checkNotify(NotificationPolicy::shipping($order+['sync_status'=>'active'],$now)['deadline']===$order['ship_by_date'],'Deadline epoch retained');
 checkNotify(NotificationPolicy::shipping(array_replace($order,['status_type'=>'Shipped']),$now)===false,'Shipped is excluded');
 checkNotify(NotificationPolicy::shipping(array_replace($order,['status_type'=>'Dibatalkan']),$now)===false,'Cancelled is excluded');
 checkNotify(NotificationPolicy::shipping(array_replace($order,['status_type'=>'UNKNOWN']),$now)===null,'Unknown status is not presumed unshipped');
 checkNotify(NotificationPolicy::shipping(array_replace($order,['ship_by_date'=>0]),$now)===null,'Missing deadline is not overdue');
 checkNotify(NotificationPolicy::shipping(array_replace($order,['detail_synced_at'=>gmdate('Y-m-d H:i:s',$now-1801)]),$now)===null,'Stale detail does not raise or resolve');
 checkNotify(NotificationPolicy::shipping(array_replace($order,['detail_synced_at'=>gmdate('Y-m-d H:i:s',$now+3600)]),$now)===null,'Future local-time timestamp is not fresh');
 checkNotify(NotificationPolicy::shipping(array_replace($order,['ship_by_date'=>$now+86401]),$now)===false,'Outside warning window');
 checkNotify(NotificationPolicy::shipping(array_replace($order,['ship_by_date'=>$now+86400]),$now)['severity']==='warning','Warning boundary inclusive');
 checkNotify(NotificationPolicy::shipping(array_replace($order,['ship_by_date'=>$now+21600]),$now)['severity']==='urgent','Urgent boundary inclusive');
 checkNotify(NotificationPolicy::shipping(array_replace($order,['ship_by_date'=>$now]),$now)['stage']===2,'Overdue boundary');
 checkNotify(NotificationPolicy::accessIssue('Forbidden 403')==='access','Forbidden is not expired');
 checkNotify(NotificationPolicy::accessIssue('Sesi Shopee toko tidak valid.')==='session','Session classification');
 checkNotify(NotificationPolicy::accessIssue('Network timeout')===null,'Network timeout is not invalid cookie');
 $sync=['enabled'=>1,'interval_seconds'=>180,'last_success_at'=>gmdate('Y-m-d H:i:s',$now-7200),'sync_type'=>'orders','shop_id'=>1,'progress_at'=>gmdate('Y-m-d H:i:s',$now+3600)];
 checkNotify(NotificationPolicy::sync($sync,$now)['severity']==='urgent','Future progress cannot hide stalled synchronization');

 notifySql("INSERT INTO sync_schedules(shop_id,sync_type,interval_seconds,last_success_at,enabled) VALUES
 (1,'shops',3600,UTC_TIMESTAMP(),1),(1,'orders',180,DATE_SUB(UTC_TIMESTAMP(),INTERVAL 2 HOUR),1),
 (1,'products',900,DATE_SUB(UTC_TIMESTAMP(),INTERVAL 3 HOUR),1),(2,'orders',180,DATE_SUB(UTC_TIMESTAMP(),INTERVAL 2 HOUR),0)");
 notifySql("INSERT INTO sync_jobs(id,shop_id,channel_id,sync_type,idempotency_key,status,created_at) VALUES(101,1,1,'orders','fixture','running',DATE_SUB(UTC_TIMESTAMP(),INTERVAL 2 HOUR))");
 notifySql("INSERT INTO sync_job_orders(job_id,order_id,status,completed_at) VALUES(101,51,'done',UTC_TIMESTAMP())");
 notifySql("INSERT INTO orders(id,shop_id,order_sn,status_type,ship_by_date,detail_synced_at) VALUES(51,1,'TEST-ORDER','Perlu Dikirim',UNIX_TIMESTAMP()+3600,UTC_TIMESTAMP()),(52,1,'TEST-SHIPPED','Shipped',UNIX_TIMESTAMP()+3600,UTC_TIMESTAMP())");
 $model->refreshOperations(true);
 $list=$model->notifications(1,false,100);
 checkNotify(count(array_filter($list,fn($a)=>$a['type']==='shipping_deadline'))===1,'Only verified open order notified');
 checkNotify(count(array_filter($list,fn($a)=>$a['type']==='sync_stale'))===1,'Active progress and disabled schedules suppressed');
 checkNotify(str_contains($list[0]['path'],'shop_id=1'),'Action preserves shop scope');
 $shipping=array_values(array_filter($list,fn($a)=>$a['type']==='shipping_deadline'))[0];
 $model->markRead(1,[['id'=>$shipping['id'],'revision'=>$shipping['revision']]]);
 notifySql('UPDATE orders SET ship_by_date=UNIX_TIMESTAMP()-1 WHERE id=51');$model->refreshOperations(true);
 $shipping2=array_values(array_filter($model->notifications(1),fn($a)=>$a['type']==='shipping_deadline'))[0];
 checkNotify($shipping2['revision']===$shipping['revision']+1,'Overdue escalation reopens read urgent notice');
 notifySql("UPDATE orders SET status_type='Shipped' WHERE id=51");$model->refreshOperations(true);
 checkNotify(count(array_filter($model->notifications(1,false),fn($a)=>$a['type']==='shipping_deadline'))===0,'Fresh shipped status resolves deadline');
 notifySql("UPDATE shops SET cookie='' WHERE id=1");$model->refreshOperations(true);
 $list=$model->notifications(1,false);
 checkNotify(count($list)===1 && $list[0]['type']==='connection','Root connection groups downstream sync delays');
 notifySql("UPDATE shops SET cookie='fixture-new' WHERE id=1");
 notifySql("UPDATE sync_schedules SET last_success_at=UTC_TIMESTAMP(),last_error=NULL WHERE shop_id=1");$model->refreshOperations(true);
 checkNotify($model->overview(1)['total']===0,'Confirmed recovery resolves connection');
 notifySql("UPDATE sync_schedules SET last_error='Forbidden 403' WHERE shop_id=1 AND sync_type='products'");$model->refreshOperations(true);
 $list=$model->notifications(1,false);
 checkNotify(count($list)===1 && $list[0]['title']==='Akses modul dibatasi' && str_contains($list[0]['path'],'/panel/sync'),'Module access uses sync action, not cookie expiry');
 $model->markRead(1,[['id'=>$list[0]['id'],'revision'=>$list[0]['revision']]]);
 checkNotify($model->overview(1)['unread_urgent']===0,'Unread urgent count matches unread denominator');
 $model->putAlert(1,'shipping_deadline','order','55','urgent',['title'=>'Fixture','message'=>'Fixture','path'=>'/panel/orders?shop_id=1&order_id=55','action_label'=>'Buka','stage'=>1,'valid_until'=>gmdate('Y-m-d H:i:s',$now-1)]);
 checkNotify(array_values(array_filter($model->notifications(1,false),fn($a)=>$a['type']==='shipping_deadline'))[0]['stale']===true,'Existing stale incident remains visible and labelled stale');
 checkNotify(count($model->notifications(1,false,1,1))===1,'Pagination works');
 echo "PASS: {$checks} notification policy, lifecycle, per-user reads, concurrency, source freshness and detector checks\n";
}finally{
 foreach(array_reverse(array_merge($tables,['notification_receipts','notification_checks'])) as $table)notifySql("DROP TEMPORARY TABLE IF EXISTS {$table}");
}
