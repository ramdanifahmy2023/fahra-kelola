<?php
require_once __DIR__.'/StockAlert.php';

class ChatIncomingNotifications extends StockAlert {
  private $incomingSchemaReady = false;

  public function __construct($db = null) { $this->db = $db ?: new Database(); }

  public function ensureSchema() {
    parent::ensureSchema();
    if ($this->incomingSchemaReady) return;
    foreach (explode(';',file_get_contents(__DIR__.'/../../database/migrations/20260930_chat_incoming.sql')) as $sql) {
      if (trim($sql)) { $this->db->query($sql); $this->db->exe(); }
    }
    $this->incomingSchemaReady = true;
  }

  public function startShop(int $shopId): void {
    $this->ensureSchema();
    $this->db->query('INSERT IGNORE INTO chat_incoming_monitors (shop_id,started_at) VALUES (:shop,UTC_TIMESTAMP(6))');
    $this->db->bind('shop',$shopId); $this->db->exe();
  }

  public function record(int $shopId, array $conversation, array $message, int $sellerId): bool {
    $id=(string)($message['id'] ?? ''); $conversationId=(string)($conversation['remote_conversation_id'] ?? '');
    $buyerId=(string)($conversation['buyer_id'] ?? '');
    $remoteShop=(string)(json_decode($conversation['raw_payload'] ?? '{}',true)['shop_id'] ?? '');
    if ($id==='' || strlen($id)>80 || $conversationId==='' || strlen($conversationId)>80 || !$buyerId || !$sellerId
      || (string)($message['from_id'] ?? '')!==$buyerId || $buyerId===(string)$sellerId
      || (string)($message['to_id'] ?? '')!==(string)$sellerId
      || (string)($message['conversation_id'] ?? '')!==$conversationId
      || $remoteShop==='' || (string)($message['to_shop_id'] ?? '')!==$remoteShop
      || empty($message['type'])
      || in_array($message['type'] ?? '',['notification','system'],true)) return false;
    // A timestamp without a timezone cannot establish that a message arrived after activation.
    $rawDate=$message['created_at'] ?? '';
    if (!is_string($rawDate) || !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/',$rawDate)) return false;
    try { $date=(new DateTimeImmutable($rawDate))->setTimezone(new DateTimeZone('UTC')); }
    catch (Throwable $e) { return false; }
    $at=$date->format('Y-m-d H:i:s.u');
    if ($date->getTimestamp()>time()+60) return false;
    $this->ensureSchema();
    $this->db->begin();
    try {
      $this->db->query('SELECT started_at FROM chat_incoming_monitors WHERE shop_id=:shop FOR UPDATE');
      $this->db->bind('shop',$shopId); $monitor=$this->db->single();
      if (!$monitor || $at<=$monitor['started_at']) { $this->db->commit(); return false; }
      $this->db->query('INSERT IGNORE INTO chat_incoming_events (shop_id,remote_message_id,conversation_id,message_at,detected_at) VALUES (:shop,:id,:conversation,:at,UTC_TIMESTAMP(6))');
      foreach (['shop'=>$shopId,'id'=>$id,'conversation'=>$conversationId,'at'=>$at] as $key=>$value) $this->db->bind($key,$value);
      $this->db->exe();
      if (!$this->db->row()) { $this->db->commit(); return false; }
      $this->db->query('SELECT COUNT(*) stage FROM chat_incoming_events WHERE shop_id=:shop AND conversation_id=:conversation');
      $this->db->bind('shop',$shopId); $this->db->bind('conversation',$conversationId); $stage=(int)$this->db->single()['stage'];
      $preview=trim((string)($message['content']['text'] ?? $message['content']['custom_preview_text']['text'] ?? ''));
      if ($preview==='') $preview='['.(string)($message['type'] ?? 'pesan').']';
      $this->putAlert($shopId,'chat_incoming','conversation',$conversationId,'info',[
        'title'=>'Pesan Shopee baru','message'=>($conversation['buyer_name'] ?: 'Pembeli').' · '.mb_substr($preview,0,180),
        'action_label'=>'Lihat pesan','path'=>'/panel/chat?shop_id='.$shopId.'&conversation_id='.rawurlencode($conversationId),
        'icon'=>'chat','stage'=>$stage,'source_at'=>$date->format('Y-m-d H:i:s')
      ]);
      $this->db->commit(); return true;
    } catch (Throwable $e) { $this->db->rollback(); throw $e; }
  }

  public function cursor(): string {
    $this->ensureSchema();
    $this->db->query('SELECT COALESCE(MAX(e.id),0) event_cursor FROM chat_incoming_events e JOIN shops s ON s.id=e.shop_id');
    return (string)$this->db->single()['event_cursor'];
  }
}
