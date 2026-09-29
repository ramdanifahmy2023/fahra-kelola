<?php
chdir(__DIR__ . '/../public');
require '../app/init.php';
require '../app/models/ShopeeChat.php';
require '../app/models/ChatMonitor.php';
require '../app/models/SyncJob.php';
function checkChat($condition, $message) { if (!$condition) throw new RuntimeException($message); }

$calls = [];
$reply = ['ok' => true, 'http_code' => 200, 'body' => ['conversations' => []], 'error' => null];
$api = new ShopeeChat(function ($url, $cookie, $method, $body, $headers) use (&$calls, &$reply) {
  parse_str(parse_url($url, PHP_URL_QUERY), $query);
  $calls[] = compact('url', 'method', 'body', 'headers', 'query'); return $reply;
});
$session = ['token'=>'fixture', 'remote_shop_id'=>101, 'message_region'=>'ID', 'region'=>'GLOBAL'];
$api->listConversations($session, 'fixture', ['direction'=>'older','last_message_id'=>'9223372036854775800','next_timestamp_nano'=>'0']);
checkChat($calls[0]['body']['direction'] === 'older' && $calls[0]['body']['last_received_message_id'] === '9223372036854775800', 'Older cursor stays a string and does not become latest');
$reply['body'] = ['code'=>9,'message'=>'denied'];
checkChat(!$api->listConversations($session, 'fixture')['ok'], 'HTTP 200 API errors fail');
$reply['body'] = ['unrecognized'=>[]];
checkChat(!$api->getMessages($session, 'fixture', 'c1')['ok'], 'Unknown history envelope fails');
$reply['http_code'] = 403; $reply['ok'] = false; $reply['body'] = ['message'=>'user_is_forbidden'];
$forbidden = $api->listConversations($session, 'fixture');
checkChat($forbidden['forbidden'] && !$forbidden['expired'], 'Permission denial is not cookie expiry');
$conversation = ['remote_conversation_id'=>'c1','buyer_id'=>501,'buyer_shop_id'=>601];
$request = ShopeeChat::uuid();
$reply = ['ok'=>true,'http_code'=>200,'body'=>['id'=>'m1','request_id'=>$request,'conversation_id'=>'c1','content'=>['text'=>'test']], 'error'=>null];
$sent = $api->sendMessage($session, 'fixture', $conversation, 'test', $request, 'content-fixture');
$call = $calls[count($calls)-1];
checkChat($sent['ok'] && $call['query']['x-shop-region'] === 'ID' && $call['query']['uuid'] !== $request, 'Send uses message region and independent UUID');
checkChat($call['body']['source_content'] === [] && $call['body']['content']['uid'] === 'content-fixture', 'Send matches captured body');
checkChat(!isset($call['body']['re_policy']), 'Missing device proof is not fabricated');
$reply['body']['request_id'] = 'different';
checkChat($api->sendMessage($session, 'fixture', $conversation, 'test', $request)['ambiguous'], 'Mismatched receipt is ambiguous');
$reply = ['ok'=>false,'http_code'=>0,'body'=>null,'error'=>'timeout'];
checkChat($api->sendMessage($session, 'fixture', $conversation, 'test', $request)['ambiguous'], 'Timeout cannot be retried automatically');
$reply = ['ok'=>true,'http_code'=>204,'body'=>null,'error'=>null];
checkChat($api->markRead($session, 'fixture', 'c1')['ok'], 'Captured activation 204 accepted');
checkChat(end($calls)['query']['x-shop-region'] === 'ID', 'Activation uses country region');

class ChatFixtureClient extends ShopeeChat {
  public $receipt = null, $duringSend = null;
  public $sends = 0, $histories = 0, $identity = 101, $mode = 'ok', $listFailure = false, $closed = false, $pages = [];
  public function bootstrap($cookie) { return ['ok'=>true,'remote_shop_id'=>$this->identity,'remote_user_id'=>900,'user_id'=>900,'message_region'=>'ID','access_token_expires_at'=>time()+3600]; }
  public function listConversations($session, $cookie, array $cursor = []) {
    $this->pages[] = $cursor;
    if ($this->listFailure) return ['ok'=>false,'message'=>'user_is_forbidden'];
    $rows = [];
    if (($cursor['direction'] ?? '') !== 'older' || empty($cursor['last_message_id'])) {
      for ($i=1;$i<=50;$i++) $rows[] = ['id'=>'c'.$i,'shop_id'=>$i===2?202:101,'to_id'=>500+$i,'to_shop_id'=>600+$i,'to_name'=>'Synthetic buyer '.$i,'status'=>'activated','latest_message_id'=>(string)(10000-$i),'last_message_region'=>'ID','last_message_time'=>'2026-01-01T00:00:00Z'];
    } elseif ($cursor['last_message_id'] !== '8000') $rows[] = ['id'=>'older','shop_id'=>101,'to_id'=>999,'to_shop_id'=>998,'status'=>'closed','latest_message_id'=>'8000','last_message_region'=>'ID','last_message_time'=>'2025-01-01T00:00:00Z'];
    return ['ok'=>true,'data'=>['conversations'=>$rows]];
  }
  public function getMessages($session, $cookie, $id, $limit=50, $offset=0) {
    $this->histories++;
    $messages = [['id'=>'message-'.$id, 'conversation_id'=>$id,'from_id'=>501,'to_id'=>900,'type'=>'text','content'=>['text'=>'incoming fixture'],'created_at'=>'2026-01-01T00:00:00Z']];
    if ($id==='c1' && $this->receipt) $messages[]=$this->receipt;
    return ['ok'=>true,'data'=>$messages];
  }
  public function openConversation($session, $cookie, array $conversation) { return ['ok'=>true,'data'=>['is_chat_availiable'=>true,'conv_is_closed'=>$this->closed]]; }
  public function sendMessage($session, $cookie, array $conversation, $text, $requestId=null, $uid=null) {
    $this->sends++;
    if ($this->duringSend) { $callback=$this->duringSend; $this->duringSend=null; $callback(); }
    if ($this->mode === 'ambiguous') return ['ok'=>false,'ambiguous'=>true,'message'=>'timeout'];
    return ['ok'=>true,'remote_message_id'=>'sent-'.$this->sends,'data'=>['id'=>'sent-'.$this->sends,'request_id'=>$requestId,'conversation_id'=>$conversation['remote_conversation_id'],'content'=>['text'=>$text],'created_at'=>'2026-09-29T12:00:00Z']];
  }
  public function markRead($session, $cookie, $conversationId) { $this->closed=false; return ['ok'=>true]; }
}
class ChatFixtureMonitor extends ChatMonitor { public function ensureSchema() {} }
class ChatFixtureJobs extends SyncJob {
  public function __construct($db) { $this->db=$db; }
  public function ensureSchema() {}
}
$db = new Database();
$tables = ['shops','sync_schedules','sync_jobs','sync_runs','chat_shop_snapshots','chat_conversations','chat_messages'];
$newTables = ['chat_sync_progress','chat_thread_sync','chat_outbox'];
try {
  foreach ($tables as $table) {
    $db->query("SHOW CREATE TABLE {$table}"); $sql = $db->single()['Create Table'];
    $sql = preg_replace('/^CREATE TABLE/', 'CREATE TEMPORARY TABLE', $sql);
    $sql = preg_replace('/^\s*CONSTRAINT.*\n/m', '', $sql);
    $db->query(str_replace(",\n)", "\n)", $sql)); $db->exe();
  }
  foreach (explode(';', file_get_contents('../database/migrations/20260929_chat_delivery.sql')) as $sql) {
    if (!trim($sql)) continue;
    $db->query(str_replace('CREATE TABLE IF NOT EXISTS', 'CREATE TEMPORARY TABLE', $sql)); $db->exe();
  }
  $db->query('ALTER TABLE chat_thread_sync ADD COLUMN latest_message_id VARCHAR(80) NULL'); $db->exe();
  $db->query("INSERT INTO shops (id, shop_id, name, cookie, sync_status) VALUES (1,101,'Fixture A','fixture','connected'),(2,202,'Fixture B','fixture','connected')"); $db->exe();
  $db->query("INSERT INTO sync_schedules (shop_id,sync_type,enabled) VALUES (1,'chat',0),(2,'chat',0)"); $db->exe();
  $client = new ChatFixtureClient(); $monitor = new ChatFixtureMonitor($db, $client, false);
  $jobs = new ChatFixtureJobs($db);
  $firstJob=$jobs->enqueueType(1,'chat');
  checkChat($firstJob>0 && $jobs->enqueueType(1,'chat')===$firstJob, 'Manual requests share active chat job');
  $db->query("UPDATE sync_jobs SET status='completed' WHERE id=:id");$db->bind('id',$firstJob);$db->exe();
  checkChat($jobs->enqueueType(1,'chat')!==$firstJob, 'Refresh after completion works within the same minute');
  $result = $monitor->syncShop(1);
  checkChat($result['ok'] && !$result['complete'] && $client->histories===3, 'Worker bounds history work and continues backfill');
  checkChat(count($monitor->conversations(1,'','',false,false)) === 49, 'Mixed feed only imports owned conversations');
  checkChat(!$monitor->messages(1,'c2')['ok'], 'Foreign thread rejected');
  $db->query("INSERT INTO chat_conversations (shop_id, remote_conversation_id, raw_payload) VALUES (2,'c1','{\"shop_id\":101}')"); $db->exe();
  checkChat(!$monitor->messages(2,'c1')['ok'] && !$monitor->send(2,'c1','test',ShopeeChat::uuid())['ok'], 'Legacy pollution cannot be read or sent');
  checkChat($monitor->overview(2,false)['totals']['conversation_count'] === 0, 'Legacy pollution excluded from counts');
  checkChat($monitor->requestRefresh(1,'c1')['ok'], 'Selected thread requests are durable');
  $result = $monitor->syncShop(1);
  checkChat($result['ok'], 'Second bounded pass succeeds');
  $older = array_values(array_filter($client->pages, fn($page)=>($page['direction']??'')==='older'));
  checkChat(end($older)['last_message_id'] === '9950' && end($older)['next_timestamp_nano'] === '0', 'Backfill follows last row of the unfiltered feed');
  checkChat($monitor->messages(1,'c1')['sync']['synced_at'] !== null, 'Selected thread is refreshed by worker');
  $outgoing = ShopeeChat::uuid();
  $client->duringSend = function () use ($monitor,$outgoing) { checkChat($monitor->send(1,'c1','once',$outgoing)['ambiguous'], 'Overlapping request sees in-flight intent instead of sending again'); };
  checkChat($monitor->send(1,'c1','once',$outgoing)['ok'], 'First send succeeds');
  checkChat($monitor->send(1,'c1','once',$outgoing)['ok'] && $client->sends === 1, 'Duplicate intent returns receipt without second send');
  checkChat(!$monitor->send(1,'c1','changed',$outgoing)['ok'], 'Intent cannot be reused for different text');
  $client->mode='ambiguous'; $ambiguous=ShopeeChat::uuid();
  checkChat($monitor->send(1,'c1','uncertain',$ambiguous)['ambiguous'], 'Ambiguous result retained');
  checkChat($monitor->send(1,'c1','uncertain',$ambiguous)['ambiguous'] && $client->sends===2, 'Ambiguous intent never automatically resent');
  $client->receipt=['id'=>'confirmed-later','conversation_id'=>'c1','request_id'=>$ambiguous,'content'=>['text'=>'uncertain'],'from_id'=>900,'to_id'=>501,'created_at'=>'2026-09-29T12:01:00Z'];
  $monitor->requestRefresh(1,'c1'); $monitor->syncShop(1);
  checkChat($monitor->send(1,'c1','uncertain',$ambiguous)['ok'] && $client->sends===2, 'Matching history reconciles uncertain intent without resending');
  $client->closed=true;
  checkChat(!$monitor->send(1,'c1','closed',ShopeeChat::uuid())['ok'] && $client->sends===2, 'Remote closed state blocks send even when local is active');
  checkChat($monitor->markRead(1,'older',true)['ok'], 'Explicit Chat Lagi activates closed thread');
  $client->identity=202;
  checkChat(!$monitor->send(1,'c1','mismatch',ShopeeChat::uuid())['ok'] && $client->sends===2, 'Mismatched cookie identity blocked');
  $client->identity=101;
  for ($i=1;$i<=205;$i++) {
    $db->query("INSERT INTO chat_messages (shop_id,remote_conversation_id,remote_message_id,content_text,remote_created_at) VALUES (1,'c1',:id,:text,:date)");
    $db->bind('id','bulk-'.$i); $db->bind('text','bulk-'.$i); $db->bind('date',gmdate('Y-m-d H:i:s',1800000000+$i)); $db->exe();
  }
  $messages=$monitor->messages(1,'c1')['messages'];
  checkChat(count($messages)===200 && end($messages)['content_text']==='bulk-205' && $messages[0]['content_text']==='bulk-6', 'History returns recent 200 in chronological order');
  $before=$monitor->overview(1,false)['shops'][0]['last_sync_at'];
  $client->listFailure=true; $monitor->syncShop(1);
  $after=$monitor->overview(1,false)['shops'][0];
  checkChat($after['last_sync_at']===$before && !$after['sync_enabled'] && $after['error_message']==='user_is_forbidden', 'Errors preserve successful time and paused schedules');
  echo "PASS: chat contracts, identity/ownership, legacy isolation, bounded backfill/history, idempotent outbox, ambiguity, reopening, recent history, honest status\n";
} finally {
  foreach (array_reverse(array_merge($tables,$newTables)) as $table) { $db->query("DROP TEMPORARY TABLE IF EXISTS {$table}"); $db->exe(); }
}
