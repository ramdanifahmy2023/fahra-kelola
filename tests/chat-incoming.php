<?php
chdir(__DIR__.'/../public');
require '../app/init.php';
require '../app/models/ChatIncomingNotifications.php';
require '../app/models/NotificationCenter.php';
require '../app/models/ChatMonitor.php';
$db=new Database(); $checks=0;
function incomingCheck($ok,$label) { global $checks; $checks++; if (!$ok) throw new RuntimeException($label); }
function incomingSql($sql,array $params=[]) { global $db; $db->query($sql); foreach ($params as $k=>$v) $db->bind($k,$v); return $db->getAll(); }
class IncomingTestCenter extends NotificationCenter { public function __construct($db) { $this->db=$db; } }
class IncomingTestFailure extends ChatIncomingNotifications {
  public function putAlert(int $shopId,string $type,string $entityType,string $entityId,string $severity,array $payload): void { throw new RuntimeException('fixture failure'); }
}
class IncomingTestClient {
  public $id='baseline', $historyCalls=0, $writes=0;
  public function bootstrap($cookie) { return ['ok'=>true,'remote_shop_id'=>101,'remote_user_id'=>900,'message_region'=>'ID']; }
  public function listConversations($session,$cookie,array $cursor=[]) {
    $rows=empty($cursor['last_message_id'])||$cursor['direction']==='latest' ? [['id'=>'c1','shop_id'=>101,'to_id'=>501,'to_name'=>'Fixture buyer','status'=>'activated','latest_message_id'=>$this->id,'last_message_time'=>gmdate('Y-m-d\TH:i:s\Z')]] : [];
    return ['ok'=>true,'data'=>['conversations'=>$rows]];
  }
  public function getMessages($session,$cookie,$id,$limit,$offset) {
    $this->historyCalls++;
    return ['ok'=>true,'data'=>[['id'=>$this->id,'shop_id'=>101,'conversation_id'=>$id,'from_id'=>501,'to_id'=>900,'type'=>'text','content'=>['text'=>'fixture'],'created_at'=>$this->id==='baseline'?'2020-01-01T00:00:00Z':gmdate('Y-m-d\TH:i:s\Z')]]];
  }
  public function sendMessage(...$args) { $this->writes++; throw new RuntimeException('No sends allowed'); }
  public function markRead(...$args) { $this->writes++; throw new RuntimeException('No activation allowed'); }
}
$tables=['alerts','shops','products','chat_shop_snapshots','chat_conversations','chat_messages','sync_schedules'];
$additional=['notification_receipts','notification_checks','chat_sync_progress','chat_thread_sync','chat_outbox','chat_incoming_monitors','chat_incoming_events'];
try {
  foreach ($tables as $table) {
    $sql=incomingSql("SHOW CREATE TABLE {$table}")[0]['Create Table'];
    $sql=preg_replace('/^CREATE TABLE/','CREATE TEMPORARY TABLE',$sql);
    $sql=preg_replace('/^\s*CONSTRAINT.*\n/m','',$sql);
    incomingSql(str_replace(",\n)","\n)",$sql));
  }
  foreach (['20260929_notifications.sql','20260929_chat_delivery.sql','20260930_chat_incoming.sql'] as $file) {
    foreach (explode(';',file_get_contents('../database/migrations/'.$file)) as $sql) if (trim($sql)) incomingSql(str_replace('CREATE TABLE IF NOT EXISTS','CREATE TEMPORARY TABLE',$sql));
  }
  incomingSql("INSERT INTO shops(id,shop_id,name,cookie,sync_status) VALUES (1,101,'Fixture shop','fixture','connected')");
  incomingSql("INSERT INTO products(id,shop_id,name,status,total_stock) VALUES (10,1,'Fixture product',1,2)");
  incomingSql("INSERT INTO sync_schedules(shop_id,sync_type,enabled) VALUES (1,'chat',0)");
  $model=new ChatIncomingNotifications($db); $model->startShop(1);
  $baseline=incomingSql('SELECT started_at FROM chat_incoming_monitors')[0]['started_at'];
  $model->startShop(1);
  incomingCheck(incomingSql('SELECT started_at FROM chat_incoming_monitors')[0]['started_at']===$baseline,'Activation cutoff persists across polling');
  $conversation=['remote_conversation_id'=>'c1','buyer_id'=>501,'buyer_name'=>'Fixture buyer','raw_payload'=>'{"shop_id":101}'];
  $message=['id'=>'new1','shop_id'=>101,'conversation_id'=>'c1','from_id'=>501,'to_id'=>900,'type'=>'text','content'=>['text'=>'Fixture new message'],'created_at'=>gmdate('Y-m-d\TH:i:s\Z',time()+1)];
  incomingCheck(!$model->record(1,$conversation,array_replace($message,['created_at'=>'2020-01-01T00:00:00Z']),900),'Historical imports are silent');
  foreach ([['from_id'=>900],['to_id'=>999],['from_id'=>777],['shop_id'=>202],['conversation_id'=>'other'],['type'=>'notification'],['type'=>'system'],['created_at'=>'2026-09-30 01:00:00'],['created_at'=>gmdate('Y-m-d\TH:i:s\Z',time()+3600)]] as $change) {
    incomingCheck(!$model->record(1,$conversation,array_replace($message,$change),900),'Wrong identity/system/unproven time suppressed');
  }
  incomingCheck($model->cursor()==='0','Filtered history creates no sound events');
  $center=new IncomingTestCenter($db); $center->ensureSchema(); $center->reconcileShop(1);
  $stock=$center->notifications(1)[0];
  incomingCheck($model->record(1,$conversation,$message,900),'Verified incoming text creates event');
  $cursor=$model->cursor();
  incomingCheck($center->overview(1)['unread']===2,'Chat and existing stock share one unread total');
  $chat=array_values(array_filter($center->notifications(1),fn($row)=>$row['type']==='chat_incoming'))[0];
  incomingCheck($chat['severity']==='info' && $chat['path']==='/panel/chat?shop_id=1&conversation_id=c1','Chat has scoped local destination and its own type');
  $center->markRead(1,[['id'=>$chat['id'],'revision'=>$chat['revision']]]);
  incomingCheck($center->overview(1)['unread']===1 && $center->overview(2)['unread']===2,'Read receipt is per operator and preserves stock');
  incomingCheck(!$model->record(1,$conversation,$message,900) && $model->cursor()===$cursor,'Repeated history neither escalates nor sounds');
  incomingCheck($center->overview(1)['unread']===1,'Repeated history preserves read receipt');
  $media=array_replace($message,['id'=>'new2','type'=>'image','content'=>[]]);
  incomingCheck($model->record(1,$conversation,$media,900),'Buyer media creates event without text');
  $chat2=array_values(array_filter($center->notifications(1),fn($row)=>$row['type']==='chat_incoming'))[0];
  incomingCheck($chat2['id']===$chat['id'] && $chat2['revision']===$chat['revision']+1 && str_contains($chat2['message'],'[image]'),'One conversation alert escalates on each new message');
  incomingCheck($center->overview(1)['unread']===2 && $center->overview(1)['total']===2,'Next message becomes unread without duplicate bell rows');
  incomingCheck(array_values(array_filter($center->notifications(1),fn($r)=>$r['type']==='low_stock'))[0]['revision']===$stock['revision'],'Chat does not alter stock revision');
  $failure=new IncomingTestFailure($db); $failure->ensureSchema();
  try { $failure->record(1,$conversation,array_replace($message,['id'=>'retry']),900); incomingCheck(false,'Expected rollback'); }
  catch (RuntimeException $e) { incomingCheck($e->getMessage()==='fixture failure','Injected alert failure occurs'); }
  incomingCheck(count(incomingSql("SELECT id FROM chat_incoming_events WHERE remote_message_id='retry'"))===0,'Event and alert roll back together');
  incomingCheck($model->record(1,$conversation,array_replace($message,['id'=>'retry']),900),'Failed materialization can be retried');
  // This exercises the worker path; no remote mutation method may be called.
  incomingSql('UPDATE chat_incoming_monitors SET started_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 10 SECOND)');
  $client=new IncomingTestClient(); $monitor=new ChatMonitor($db,$client,$model);
  incomingCheck($monitor->syncShop(1)['ok'],'Worker imports baseline history');
  $events=count(incomingSql('SELECT id FROM chat_incoming_events'));
  $client->id='changed-in-same-second';
  incomingSql('UPDATE chat_thread_sync SET synced_at=UTC_TIMESTAMP()');
  incomingCheck($monitor->syncShop(1)['ok'] && count(incomingSql('SELECT id FROM chat_incoming_events'))===$events+1,'Message ID catches a change within the last sync second');
  $calls=$client->historyCalls; $monitor->syncShop(1);
  incomingCheck($client->historyCalls===$calls && $client->writes===0,'Unchanged cursor skips history and never writes Shopee');
  echo "PASS: {$checks} isolated chat cutoff, ownership, dedupe, mixed notifications, per-user receipts, rollback and worker checks\n";
} finally {
  foreach (array_reverse(array_merge($tables,$additional)) as $table) incomingSql("DROP TEMPORARY TABLE IF EXISTS {$table}");
}
