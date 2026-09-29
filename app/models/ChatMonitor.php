<?php

class ChatMonitor extends BaseModel {
  protected $table = 'chat_shop_snapshots';
  private $staleSeconds = 30;
  private $schemaReady = false;
  private $chatClient;
  private $incomingNotifications;

  public function __construct($db = null, $client = null, $incomingNotifications = null) {
    $this->db = $db ?: new Database();
    $this->chatClient = $client;
    $this->incomingNotifications = $incomingNotifications;
  }

  public function ensureSchema() {
    if ($this->schemaReady) return;
    $this->db->query("CREATE TABLE IF NOT EXISTS chat_shop_snapshots (
      shop_id INT NOT NULL,
      remote_shop_id BIGINT NULL,
      remote_user_id BIGINT NULL,
      remote_shop_name VARCHAR(255) NULL,
      remote_country VARCHAR(8) NULL,
      status VARCHAR(20) NOT NULL DEFAULT 'pending',
      unread_count INT NOT NULL DEFAULT 0,
      conversation_count INT NOT NULL DEFAULT 0,
      last_message_id VARCHAR(80) NULL,
      last_message_region VARCHAR(8) NULL,
      next_timestamp_nano VARCHAR(80) NULL,
      session_expires_at DATETIME NULL,
      last_sync_at DATETIME NULL,
      error_message TEXT NULL,
      payload LONGTEXT NULL,
      updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (shop_id),
      KEY chat_snapshots_status (status),
      KEY chat_snapshots_sync (last_sync_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $this->db->exe();

    $this->db->query("CREATE TABLE IF NOT EXISTS chat_conversations (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      shop_id INT NOT NULL,
      remote_conversation_id VARCHAR(80) NOT NULL,
      buyer_id BIGINT NULL,
      buyer_name VARCHAR(255) NULL,
      buyer_avatar VARCHAR(500) NULL,
      buyer_shop_id BIGINT NULL,
      status VARCHAR(32) NULL,
      is_blocked TINYINT(1) NOT NULL DEFAULT 0,
      unread_count INT NOT NULL DEFAULT 0,
      latest_message_id VARCHAR(80) NULL,
      latest_message_type VARCHAR(32) NULL,
      latest_message_source VARCHAR(64) NULL,
      latest_message_text TEXT NULL,
      latest_message_at DATETIME NULL,
      latest_message_region VARCHAR(8) NULL,
      raw_payload LONGTEXT NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      UNIQUE KEY chat_conversations_remote (shop_id, remote_conversation_id),
      KEY chat_conversations_list (shop_id, latest_message_at),
      KEY chat_conversations_unread (shop_id, unread_count)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $this->db->exe();

    $this->db->query("CREATE TABLE IF NOT EXISTS chat_messages (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      shop_id INT NOT NULL,
      remote_conversation_id VARCHAR(80) NOT NULL,
      remote_message_id VARCHAR(80) NOT NULL,
      sender_id BIGINT NULL,
      receiver_id BIGINT NULL,
      sender_name VARCHAR(255) NULL,
      message_type VARCHAR(32) NULL,
      direction VARCHAR(16) NULL,
      content_text TEXT NULL,
      content_json LONGTEXT NULL,
      remote_created_at DATETIME NULL,
      remote_status VARCHAR(32) NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      UNIQUE KEY chat_messages_remote (shop_id, remote_message_id),
      KEY chat_messages_conversation (shop_id, remote_conversation_id, remote_created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $this->db->exe();
    foreach (explode(';', file_get_contents(__DIR__ . '/../../database/migrations/20260929_chat_delivery.sql')) as $sql) {
      if (trim($sql) === '') continue;
      $this->db->query($sql); $this->db->exe();
    }
    $this->db->query('SHOW COLUMNS FROM chat_thread_sync');
    if (!in_array('latest_message_id',array_column($this->db->getAll(),'Field'),true)) {
      try { $this->db->query('ALTER TABLE chat_thread_sync ADD COLUMN latest_message_id VARCHAR(80) NULL'); $this->db->exe(); }
      catch (PDOException $e) { if ((int)($e->errorInfo[1] ?? 0)!==1060) throw $e; }
    }
    $this->schemaReady = true;
  }

  private function client() {
    require_once __DIR__ . '/ShopeeChat.php';
    return $this->chatClient ?: new ShopeeChat();
  }

  private function incoming() {
    if ($this->incomingNotifications === false) return null;
    if (!$this->incomingNotifications) {
      require_once __DIR__.'/ChatIncomingNotifications.php';
      $this->incomingNotifications = new ChatIncomingNotifications($this->db);
    }
    return $this->incomingNotifications;
  }

  private function shops($shopId = null) {
    $where = '';
    if ((int)$shopId > 0) $where = ' WHERE id = :shop_id';
    $this->db->query("SELECT id, shop_id AS remote_shop_id, name, cookie, sync_status FROM shops{$where} ORDER BY name ASC");
    if ($where) $this->db->bind('shop_id', (int)$shopId);
    return $this->db->getAll();
  }

  private function snapshot($shopId) {
    $this->db->query("SELECT * FROM {$this->table} WHERE shop_id = :shop_id LIMIT 1");
    $this->db->bind('shop_id', (int)$shopId);
    return $this->db->single() ?: null;
  }

  private function session(array $shop) {
    $session = $this->client()->bootstrap($shop['cookie']);
    if (!empty($session['ok']) && ((string)($shop['remote_shop_id'] ?? '') !== (string)$session['remote_shop_id'] || empty($shop['remote_shop_id']))) {
      return ['ok' => false, 'message' => 'Identitas sesi chat tidak cocok dengan toko. Perbarui cookie toko yang dipilih.'];
    }
    return $session;
  }

  private function ownedSql($alias = 'c') {
    // Old mixed-feed rows remain recoverable, but cannot be read or sent under the wrong shop.
    return "JSON_VALID({$alias}.raw_payload) AND JSON_UNQUOTE(JSON_EXTRACT({$alias}.raw_payload, '$.shop_id')) = CAST(s.shop_id AS CHAR)";
  }

  private function utcDate($value) {
    if (!$value) return null;
    try {
      $date = new DateTimeImmutable((string)$value);
      return $date->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    } catch (Throwable $e) {
      return null;
    }
  }

  private function textFromConversation(array $conversation) {
    $content = is_array($conversation['latest_message_content'] ?? null) ? $conversation['latest_message_content'] : [];
    if (!empty($content['text'])) return (string)$content['text'];
    if (!empty($content['notification_for_receiver'])) return (string)$content['notification_for_receiver'];
    $preview = is_array($conversation['crm_message_preview'] ?? null) ? $conversation['crm_message_preview'] : [];
    return (string)($preview['text'] ?? $preview['translated_text'] ?? '');
  }

  private function upsertConversation($shopId, array $conversation) {
    $remoteId = (string)($conversation['id'] ?? '');
    if ($remoteId === '') return;
    $this->db->query("INSERT INTO chat_conversations (
      shop_id, remote_conversation_id, buyer_id, buyer_name, buyer_avatar, buyer_shop_id,
      status, is_blocked, unread_count, latest_message_id, latest_message_type,
      latest_message_source, latest_message_text, latest_message_at, latest_message_region, raw_payload
    ) VALUES (
      :shop_id, :remote_conversation_id, :buyer_id, :buyer_name, :buyer_avatar, :buyer_shop_id,
      :status, :is_blocked, :unread_count, :latest_message_id, :latest_message_type,
      :latest_message_source, :latest_message_text, :latest_message_at, :latest_message_region, :raw_payload
    ) ON DUPLICATE KEY UPDATE
      buyer_id = VALUES(buyer_id), buyer_name = VALUES(buyer_name), buyer_avatar = VALUES(buyer_avatar),
      buyer_shop_id = VALUES(buyer_shop_id), status = VALUES(status), is_blocked = VALUES(is_blocked),
      unread_count = VALUES(unread_count), latest_message_id = VALUES(latest_message_id),
      latest_message_type = VALUES(latest_message_type), latest_message_source = VALUES(latest_message_source),
      latest_message_text = VALUES(latest_message_text), latest_message_at = VALUES(latest_message_at),
      latest_message_region = VALUES(latest_message_region), raw_payload = VALUES(raw_payload)");
    $values = [
      'shop_id' => (int)$shopId,
      'remote_conversation_id' => $remoteId,
      'buyer_id' => (int)($conversation['to_id'] ?? 0),
      'buyer_name' => (string)($conversation['to_name'] ?? ''),
      'buyer_avatar' => (string)($conversation['to_avatar'] ?? ''),
      'buyer_shop_id' => (int)($conversation['to_shop_id'] ?? 0),
      'status' => (string)($conversation['status'] ?? ''),
      'is_blocked' => !empty($conversation['is_blocked']) ? 1 : 0,
      'unread_count' => (int)($conversation['unread_count'] ?? 0),
      'latest_message_id' => (string)($conversation['latest_message_id'] ?? ''),
      'latest_message_type' => (string)($conversation['latest_message_type'] ?? ''),
      'latest_message_source' => (string)($conversation['latest_message_source'] ?? ''),
      'latest_message_text' => $this->textFromConversation($conversation),
      'latest_message_at' => $this->utcDate($conversation['last_message_time'] ?? null),
      'latest_message_region' => (string)($conversation['last_message_region'] ?? ''),
      'raw_payload' => json_encode($conversation, JSON_UNESCAPED_UNICODE)
    ];
    foreach ($values as $key => $value) $this->db->bind($key, $value);
    $this->db->exe();
  }

  private function upsertMessage($shopId, $conversationId, array $message, $sessionUserId = 0, $directionOverride = null) {
    $remoteId = (string)($message['id'] ?? '');
    if ($remoteId === '') return;
    $content = is_array($message['content'] ?? null) ? $message['content'] : [];
    $text = (string)($content['text'] ?? $message['custom_preview_text']['text'] ?? '');
    $senderId = (int)($message['from_id'] ?? 0);
    $receiverId = (int)($message['to_id'] ?? 0);
    $direction = $directionOverride ?: ($sessionUserId > 0 && $senderId === $sessionUserId ? 'outgoing' : 'incoming');
    $this->db->query("INSERT INTO chat_messages (
      shop_id, remote_conversation_id, remote_message_id, sender_id, receiver_id, sender_name,
      message_type, direction, content_text, content_json, remote_created_at, remote_status
    ) VALUES (
      :shop_id, :remote_conversation_id, :remote_message_id, :sender_id, :receiver_id, :sender_name,
      :message_type, :direction, :content_text, :content_json, :remote_created_at, :remote_status
    ) ON DUPLICATE KEY UPDATE
      sender_id = VALUES(sender_id), receiver_id = VALUES(receiver_id), sender_name = VALUES(sender_name),
      message_type = VALUES(message_type), direction = VALUES(direction), content_text = VALUES(content_text),
      content_json = VALUES(content_json), remote_created_at = VALUES(remote_created_at), remote_status = VALUES(remote_status)");
    $values = [
      'shop_id' => (int)$shopId,
      'remote_conversation_id' => (string)$conversationId,
      'remote_message_id' => $remoteId,
      'sender_id' => $senderId,
      'receiver_id' => $receiverId,
      'sender_name' => (string)($message['from_user_name'] ?? $message['to_user_name'] ?? ''),
      'message_type' => (string)($message['type'] ?? ''),
      'direction' => $direction,
      'content_text' => $text,
      'content_json' => json_encode($content, JSON_UNESCAPED_UNICODE),
      'remote_created_at' => $this->utcDate($message['created_at'] ?? null),
      'remote_status' => (string)($message['status'] ?? '')
    ];
    foreach ($values as $key => $value) $this->db->bind($key, $value);
    $this->db->exe();
  }

  public function syncShop($shopId) {
    $this->ensureSchema();
    $shops = $this->shops($shopId);
    $shop = $shops[0] ?? null;
    if (!$shop) return ['ok' => false, 'status' => 'error', 'message' => 'Toko tidak ditemukan.'];
    $cookie = trim((string)($shop['cookie'] ?? ''));
    if ($cookie === '') return $this->saveError($shop, true, 'Cookie toko kosong. Perbarui cookie Shopee.');

    $client = $this->client();
    $session = $this->session($shop);
    if (empty($session['ok'])) return $this->saveError($shop, !empty($session['expired']), $session['message'] ?? 'Sesi chat Shopee tidak tersedia.');

    $this->incoming()?->startShop((int)$shopId);
    $snapshot = $this->snapshot($shopId) ?: [];
    $cursor = [
      'direction' => empty($snapshot['last_message_id']) ? 'older' : 'latest',
      'last_message_id' => $snapshot['last_message_id'] ?? '',
      'last_message_region' => $snapshot['last_message_region'] ?? 'ID',
      'next_timestamp_nano' => $snapshot['next_timestamp_nano'] ?? '0'
    ];
    $response = $client->listConversations($session, $cookie, $cursor);
    if (empty($response['ok'])) return $this->saveError($shop, !empty($response['expired']), $response['message'] ?? 'Daftar percakapan tidak tersedia.');
    $data = is_array($response['data'] ?? null) ? $response['data'] : [];
    $conversations = is_array($data['conversations'] ?? null) ? $data['conversations'] : [];
    foreach ($conversations as $conversation) {
      if ((string)($conversation['shop_id'] ?? '') === (string)$session['remote_shop_id']) $this->upsertConversation($shopId, $conversation);
    }

    $latestId = (string)($snapshot['last_message_id'] ?? '');
    $latestNano = (string)($snapshot['next_timestamp_nano'] ?? '0');
    $latestRegion = (string)($snapshot['last_message_region'] ?? 'ID');
    foreach ($conversations as $conversation) {
      $candidateId = (string)($conversation['latest_message_id'] ?? '');
      $candidateNano = (string)($conversation['last_message_time_nano'] ?? '');
      if ($candidateId !== '' && ($latestId === '' || strlen($candidateId) > strlen($latestId) || (strlen($candidateId) === strlen($latestId) && strcmp($candidateId, $latestId) > 0))) {
        $latestId = $candidateId;
        $latestRegion = (string)($conversation['last_message_region'] ?? $latestRegion);
        $latestNano = $candidateNano ?: '0';
      }
    }
    $attributions = is_array($data['attributions'] ?? null) ? $data['attributions'] : [];
    $remoteShop = is_array($session['shop'] ?? null) ? $session['shop'] : [];
    $payload = [
      'attributions' => $attributions,
      'shop' => [
        'id' => (int)$session['remote_shop_id'],
        'name' => (string)($remoteShop['name'] ?? $session['remote_shop_name'] ?? ''),
        'country' => (string)($remoteShop['country'] ?? '')
      ],
      'fetched_conversations' => count($conversations)
    ];
    $this->db->query("INSERT INTO {$this->table} (
      shop_id, remote_shop_id, remote_user_id, remote_shop_name, remote_country, status,
      unread_count, conversation_count, last_message_id, last_message_region, next_timestamp_nano,
      session_expires_at, last_sync_at, error_message, payload
    ) VALUES (
      :shop_id, :remote_shop_id, :remote_user_id, :remote_shop_name, :remote_country, 'ok',
      :unread_count, :conversation_count, :last_message_id, :last_message_region, :next_timestamp_nano,
      :session_expires_at, UTC_TIMESTAMP(), NULL, :payload
    ) ON DUPLICATE KEY UPDATE
      remote_shop_id = VALUES(remote_shop_id), remote_user_id = VALUES(remote_user_id), remote_shop_name = VALUES(remote_shop_name),
      remote_country = VALUES(remote_country), status = 'ok', unread_count = VALUES(unread_count),
      conversation_count = VALUES(conversation_count), last_message_id = VALUES(last_message_id),
      last_message_region = VALUES(last_message_region), next_timestamp_nano = VALUES(next_timestamp_nano),
      session_expires_at = VALUES(session_expires_at), last_sync_at = UTC_TIMESTAMP(), error_message = NULL, payload = VALUES(payload)");
    $values = [
      'shop_id' => (int)$shopId,
      'remote_shop_id' => (int)$session['remote_shop_id'],
      'remote_user_id' => (int)($session['remote_user_id'] ?? 0),
      'remote_shop_name' => (string)($remoteShop['name'] ?? $session['remote_shop_name'] ?? ''),
      'remote_country' => (string)($remoteShop['country'] ?? ''),
      'unread_count' => (int)($attributions['total_unread_msg_count'] ?? 0),
      'conversation_count' => count($conversations),
      'last_message_id' => $latestId,
      'last_message_region' => $latestRegion,
      'next_timestamp_nano' => $latestNano,
      'session_expires_at' => !empty($session['access_token_expires_at']) ? gmdate('Y-m-d H:i:s', (int)$session['access_token_expires_at']) : null,
      'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE)
    ];
    foreach ($values as $key => $value) $this->db->bind($key, $value);
    $this->db->exe();
    $backfill = $this->backfill($shop, $session);
    if (empty($backfill['ok'])) return $this->saveError($shop, false, $backfill['message']);
    $details = $this->syncDetails($shop, $session);
    if (empty($details['ok'])) return $this->saveError($shop, false, $details['message']);
    return ['ok' => true, 'status' => 'ok', 'complete' => $backfill['complete'] && $details['complete'], 'fetched_conversations' => count($conversations)];
  }

  private function saveError(array $shop, $expired, $message) {
    $status = $expired && stripos((string)$message, 'forbidden') === false ? 'expired' : 'error';
    $this->db->query("INSERT INTO {$this->table} (shop_id, status, error_message) VALUES (:shop_id, :status, :error_message) ON DUPLICATE KEY UPDATE status = VALUES(status), error_message = VALUES(error_message)");
    $this->db->bind('shop_id', (int)$shop['id']);
    $this->db->bind('status', $status);
    $this->db->bind('error_message', (string)$message);
    $this->db->exe();
    return ['ok' => false, 'status' => $status, 'message' => (string)$message];
  }

  private function backfill(array $shop, array $session) {
    $this->db->query('INSERT IGNORE INTO chat_sync_progress (shop_id) VALUES (:id)');
    $this->db->bind('id', (int)$shop['id']); $this->db->exe();
    $this->db->query('SELECT * FROM chat_sync_progress WHERE shop_id = :id');
    $this->db->bind('id', (int)$shop['id']); $progress = $this->db->single();
    if ($progress['backfill_done']) return ['ok' => true, 'complete' => true];
    $response = $this->client()->listConversations($session, $shop['cookie'], [
      'direction' => 'older', 'last_message_id' => $progress['older_cursor'],
      'last_message_region' => $progress['older_cursor'] ? $progress['older_region'] : $session['message_region'],
      'next_timestamp_nano' => '0'
    ]);
    if (empty($response['ok'])) return $response;
    $rows = $response['data']['conversations'];
    $last = $rows ? $rows[count($rows) - 1] : [];
    $cursor = (string)($last['latest_message_id'] ?? '');
    if ($rows && ($cursor === '' || $cursor === $progress['older_cursor'])) return ['ok' => false, 'message' => 'Cursor daftar chat tidak maju. Sinkronisasi dihentikan agar tidak mengulang halaman.'];
    foreach ($rows as $row) {
      if ((string)($row['shop_id'] ?? '') === (string)$session['remote_shop_id']) $this->upsertConversation($shop['id'], $row);
    }
    $done = !$rows;
    $this->db->query('UPDATE chat_sync_progress SET older_cursor = :cursor, older_region = :region, backfill_done = :done WHERE shop_id = :id');
    foreach (['cursor' => $cursor, 'region' => $last['last_message_region'] ?? $session['message_region'], 'done' => (int)$done, 'id' => (int)$shop['id']] as $key => $value) $this->db->bind($key, $value);
    $this->db->exe();
    return ['ok' => true, 'complete' => $done];
  }

  private function syncDetails(array $shop, array $session) {
    $this->db->query("SELECT c.* FROM chat_conversations c JOIN shops s ON s.id = c.shop_id LEFT JOIN chat_thread_sync t ON t.shop_id = c.shop_id AND t.conversation_id = c.remote_conversation_id WHERE c.shop_id = :id AND " . $this->ownedSql() . " AND (t.retry_at IS NULL OR t.retry_at <= UTC_TIMESTAMP()) AND (t.synced_at IS NULL OR t.requested_at IS NOT NULL OR NOT (c.latest_message_id <=> t.latest_message_id)) ORDER BY t.requested_at IS NULL, t.requested_at DESC, c.latest_message_at DESC LIMIT 4");
    $this->db->bind('id', (int)$shop['id']); $rows = $this->db->getAll();
    $failure = null;
    foreach (array_slice($rows, 0, 3) as $conversation) {
      $started = gmdate('Y-m-d H:i:s');
      $id = $conversation['remote_conversation_id'];
      $response = $this->client()->getMessages($session, $shop['cookie'], $id, 100, 0);
      if (!empty($response['ok'])) {
        $messages=$response['data'];
        usort($messages,static fn($a,$b)=>strcmp((string)($a['created_at'] ?? ''),(string)($b['created_at'] ?? '')));
        foreach ($messages as $message) {
          if (!is_array($message) || empty($message['id']) || (!empty($message['conversation_id']) && (string)$message['conversation_id'] !== $id)) continue;
          $this->upsertMessage($shop['id'], $id, $message, (int)$session['remote_user_id']);
          $this->incoming()?->record((int)$shop['id'], $conversation, $message, (int)$session['remote_user_id']);
          if (!empty($message['request_id'])) {
            $this->db->query("UPDATE chat_outbox SET status = 'sent', remote_message_id = :message, error_message = NULL WHERE request_id = :request AND shop_id = :shop AND conversation_id = :conversation AND content_hash = :hash");
            foreach (['message' => (string)$message['id'], 'request' => (string)$message['request_id'], 'shop' => (int)$shop['id'], 'conversation' => $id, 'hash' => hash('sha256', (string)($message['content']['text'] ?? ''))] as $key => $value) $this->db->bind($key, $value);
            $this->db->exe();
          }
        }
      } else $failure = $response['message'] ?? 'Riwayat pesan gagal diperbarui.';
      $ok = !empty($response['ok']);
      $this->db->query("INSERT INTO chat_thread_sync (shop_id, conversation_id, synced_at, retry_at, error_message, history_count) VALUES (:shop, :conversation, :synced, :retry, :error, :count) ON DUPLICATE KEY UPDATE synced_at = COALESCE(VALUES(synced_at), synced_at), requested_at = IF(requested_at <= :started, NULL, requested_at), retry_at = VALUES(retry_at), error_message = VALUES(error_message), history_count = IF(VALUES(synced_at) IS NULL, history_count, VALUES(history_count))");
      foreach (['shop' => (int)$shop['id'], 'conversation' => $id, 'synced' => $ok ? $started : null, 'retry' => $ok ? null : gmdate('Y-m-d H:i:s', time() + 60), 'error' => $ok ? null : $failure, 'count' => $ok ? count($response['data']) : 0, 'started' => $started] as $key => $value) $this->db->bind($key, $value);
      $this->db->exe();
      if ($ok) {
        $this->db->query('UPDATE chat_thread_sync SET latest_message_id=:message WHERE shop_id=:shop AND conversation_id=:conversation');
        foreach (['message'=>$conversation['latest_message_id'],'shop'=>(int)$shop['id'],'conversation'=>$id] as $key=>$value) $this->db->bind($key,$value);
        $this->db->exe();
      }
    }
    return ['ok' => $failure === null, 'message' => $failure, 'complete' => count($rows) <= 3];
  }

  public function requestRefresh($shopId, $conversationId = '') {
    $this->ensureSchema();
    if (!$this->shops($shopId) || (int)$shopId < 1) return ['ok' => false, 'message' => 'Pilih toko yang akan diperbarui.'];
    if ($conversationId !== '') {
      if (!$this->conversation($shopId, $conversationId)) return ['ok' => false, 'message' => 'Percakapan tidak dimiliki toko ini.'];
      $this->db->query('INSERT INTO chat_thread_sync (shop_id, conversation_id, requested_at) VALUES (:shop, :conversation, UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE requested_at = UTC_TIMESTAMP()');
      $this->db->bind('shop', (int)$shopId); $this->db->bind('conversation', (string)$conversationId); $this->db->exe();
    }
    return ['ok' => true];
  }

  private function shouldRefresh($snapshot) {
    if (!$snapshot || empty($snapshot['last_sync_at'])) return true;
    return (time() - strtotime($snapshot['last_sync_at'] . ' UTC')) >= $this->staleSeconds;
  }

  private function shopSummary(array $shop, $snapshot) {
    $this->db->query("SELECT COUNT(*) AS conversation_count, COALESCE(SUM(c.unread_count), 0) AS unread_count, COALESCE(SUM(c.status = 'activated'), 0) AS active_count, COALESCE(SUM(c.status = 'closed'), 0) AS closed_count FROM chat_conversations c JOIN shops s ON s.id = c.shop_id WHERE c.shop_id = :shop_id AND " . $this->ownedSql());
    $this->db->bind('shop_id', (int)$shop['id']);
    $stats = $this->db->single() ?: [];
    $this->db->query("SELECT enabled FROM sync_schedules WHERE shop_id = :shop_id AND sync_type = 'chat' LIMIT 1");
    $this->db->bind('shop_id', (int)$shop['id']);
    $schedule = $this->db->single();
    $status = $snapshot['status'] ?? 'pending';
    $expired = $status === 'expired' || ($status === 'pending' && ($shop['sync_status'] ?? '') === 'expired');
    return [
      'shop_id' => (int)$shop['id'],
      'shop_name' => $shop['name'] ?? '',
      'remote_shop_id' => !empty($snapshot['remote_shop_id']) ? (int)$snapshot['remote_shop_id'] : null,
      'remote_shop_name' => $snapshot['remote_shop_name'] ?? null,
      'status' => $status,
      'session_status' => $expired ? 'expired' : ($status === 'ok' ? 'connected' : 'unknown'),
      'session_expired' => $expired,
      'error_message' => $snapshot['error_message'] ?? null,
      'last_sync_at' => $snapshot['last_sync_at'] ?? null,
      'unread_count' => (int)($stats['unread_count'] ?? $snapshot['unread_count'] ?? 0),
      'conversation_count' => (int)($stats['conversation_count'] ?? 0),
      'active_count' => (int)($stats['active_count'] ?? 0),
      'closed_count' => (int)($stats['closed_count'] ?? 0),
      'stale' => $this->shouldRefresh($snapshot),
      'sync_enabled' => !empty($schedule['enabled'])
    ];
  }

  public function overview($shopId = null, $refresh = true) {
    $this->ensureSchema();
    $result = [];
    foreach ($this->shops($shopId) as $shop) {
      $snapshot = $this->snapshot($shop['id']);
      if ($refresh && $this->shouldRefresh($snapshot)) {
        $this->syncShop($shop['id']);
        $snapshot = $this->snapshot($shop['id']);
      }
      $result[] = $this->shopSummary($shop, $snapshot);
    }
    $totals = ['unread_count' => 0, 'conversation_count' => 0, 'active_count' => 0, 'expired_shops' => 0];
    foreach ($result as $shop) {
      $totals['unread_count'] += $shop['unread_count'];
      $totals['conversation_count'] += $shop['conversation_count'];
      $totals['active_count'] += $shop['active_count'];
      if ($shop['session_expired']) $totals['expired_shops']++;
    }
    return ['shops' => $result, 'totals' => $totals, 'refreshed_at' => date('c')];
  }

  public function conversations($shopId, $search = '', $status = '', $unreadOnly = false, $refresh = true) {
    $this->ensureSchema();
    if ((int)$shopId > 0) {
      $snapshot = $this->snapshot($shopId);
      if ($refresh && $this->shouldRefresh($snapshot)) $this->syncShop($shopId);
    } elseif ($refresh) {
      foreach ($this->shops() as $shop) {
        if ($this->shouldRefresh($this->snapshot($shop['id']))) $this->syncShop($shop['id']);
      }
    }
    $where = [$this->ownedSql()];
    if ((int)$shopId > 0) { $where[] = 'c.shop_id = :shop_id'; }
    if ($search !== '') $where[] = '(c.buyer_name LIKE :search OR c.latest_message_text LIKE :search OR c.remote_conversation_id = :conversation_id)';
    if ($status !== '') $where[] = 'c.status = :status';
    if ($unreadOnly) $where[] = 'c.unread_count > 0';
    $this->db->query("SELECT c.id, c.shop_id, s.name AS shop_name, c.remote_conversation_id, c.buyer_id, c.buyer_name, c.buyer_avatar, c.buyer_shop_id, c.status, c.is_blocked, c.unread_count, c.latest_message_id, c.latest_message_type, c.latest_message_source, c.latest_message_text, c.latest_message_at, c.latest_message_region FROM chat_conversations c LEFT JOIN shops s ON s.id = c.shop_id WHERE " . implode(' AND ', $where) . ' ORDER BY COALESCE(c.latest_message_at, c.updated_at) DESC LIMIT 100');
    if ((int)$shopId > 0) $this->db->bind('shop_id', (int)$shopId);
    if ($search !== '') {
      $this->db->bind('search', '%' . $search . '%');
      $this->db->bind('conversation_id', $search);
    }
    if ($status !== '') $this->db->bind('status', $status);
    return $this->db->getAll();
  }

  private function conversation($shopId, $conversationId) {
    $this->db->query('SELECT c.* FROM chat_conversations c JOIN shops s ON s.id = c.shop_id WHERE c.shop_id = :shop_id AND c.remote_conversation_id = :remote_id AND ' . $this->ownedSql() . ' LIMIT 1');
    $this->db->bind('shop_id', (int)$shopId);
    $this->db->bind('remote_id', (string)$conversationId);
    return $this->db->single() ?: null;
  }

  public function messages($shopId, $conversationId, $refresh = false) {
    $this->ensureSchema();
    $conversation = $this->conversation($shopId, $conversationId);
    if (!$conversation) return ['ok' => false, 'status' => 'error', 'message' => 'Percakapan tidak ditemukan.'];
    $this->db->query('SELECT id, remote_message_id, sender_id, receiver_id, sender_name, message_type, direction, content_text, content_json, remote_created_at, remote_status FROM chat_messages WHERE shop_id = :shop_id AND remote_conversation_id = :conversation_id ORDER BY COALESCE(remote_created_at, created_at) DESC, id DESC LIMIT 200');
    $this->db->bind('shop_id', (int)$shopId);
    $this->db->bind('conversation_id', $conversation['remote_conversation_id']);
    $messages = array_reverse($this->db->getAll());
    $this->db->query('SELECT synced_at, requested_at, error_message, history_count FROM chat_thread_sync WHERE shop_id = :shop AND conversation_id = :conversation');
    $this->db->bind('shop', (int)$shopId); $this->db->bind('conversation', (string)$conversationId);
    $sync = $this->db->single() ?: null;
    $this->db->query('SELECT request_id, status, remote_message_id, error_message FROM chat_outbox WHERE shop_id = :shop AND conversation_id = :conversation ORDER BY created_at DESC LIMIT 20');
    $this->db->bind('shop', (int)$shopId); $this->db->bind('conversation', (string)$conversationId);
    unset($conversation['raw_payload']);
    return ['ok' => true, 'conversation' => $conversation, 'messages' => $messages, 'sync' => $sync, 'outbox' => $this->db->getAll()];
  }

  public function send($shopId, $conversationId, $text, $requestId = '') {
    $this->ensureSchema();
    $text = trim((string)$text);
    if (!preg_match('/^[a-f0-9]{8}-(?:[a-f0-9]{4}-){3}[a-f0-9]{12}$/i', $requestId) || $text === '' || mb_strlen($text) > 2000) return ['ok' => false, 'message' => 'ID pengiriman atau isi pesan tidak valid.'];
    $conversation = $this->conversation($shopId, $conversationId);
    $shop = $this->shops($shopId)[0] ?? null;
    if (!$conversation || !$shop || empty($shop['cookie'])) return ['ok' => false, 'status' => 'error', 'message' => 'Toko atau percakapan tidak ditemukan.'];
    $client = $this->client();
    $this->db->query('SELECT * FROM chat_outbox WHERE request_id = :request');
    $this->db->bind('request', $requestId); $existing = $this->db->single();
    if ($existing) return $this->outboxResult($existing, $shopId, $conversationId, $text);
    $session = $this->session($shop);
    if (empty($session['ok'])) return $this->saveError($shop, !empty($session['expired']), $session['message']);
    $open = $client->openConversation($session, $shop['cookie'], $conversation);
    if (empty($open['ok'])) return $open;
    if (!empty($conversation['is_blocked']) || empty($open['data']['is_chat_availiable']) || !empty($open['data']['conv_is_closed'])) return ['ok' => false, 'message' => 'Percakapan belum dapat dibalas. Gunakan Chat Lagi atau periksa izin di Shopee.'];
    $uid = ShopeeChat::uuid();
    $this->db->query("INSERT IGNORE INTO chat_outbox (request_id, shop_id, conversation_id, content_hash, content_uid) VALUES (:request, :shop, :conversation, :hash, :uid)");
    foreach (['request' => $requestId, 'shop' => (int)$shopId, 'conversation' => (string)$conversationId, 'hash' => hash('sha256', $text), 'uid' => $uid] as $key => $value) $this->db->bind($key, $value);
    $this->db->exe();
    if (!$this->db->row()) {
      $this->db->query('SELECT * FROM chat_outbox WHERE request_id = :request'); $this->db->bind('request', $requestId);
      return $this->outboxResult($this->db->single(), $shopId, $conversationId, $text);
    }
    try { $response = $client->sendMessage($session, $shop['cookie'], $conversation, $text, $requestId, $uid); }
    catch (Throwable $error) { $response = ['ok' => false, 'ambiguous' => true, 'message' => 'Koneksi terputus. Periksa riwayat sebelum mencoba pesan baru.']; }
    $remoteId = $response['remote_message_id'] ?? null;
    $status = !empty($response['ok']) && $remoteId ? 'sent' : (!empty($response['ambiguous']) ? 'ambiguous' : 'failed');
    $this->db->query('UPDATE chat_outbox SET status = :status, remote_message_id = :remote, error_message = :error WHERE request_id = :request');
    foreach (['status' => $status, 'remote' => $remoteId, 'error' => $response['message'] ?? null, 'request' => $requestId] as $key => $value) $this->db->bind($key, $value);
    $this->db->exe();
    if ($status === 'sent') {
      $this->upsertMessage($shopId, $conversationId, $response['data'], (int)$session['remote_user_id'], 'outgoing');
      $this->db->query("UPDATE chat_conversations SET latest_message_id = :message, latest_message_type = 'text', latest_message_text = :text, latest_message_at = :at WHERE shop_id = :shop AND remote_conversation_id = :conversation");
      foreach (['message' => $remoteId, 'text' => $text, 'at' => $this->utcDate($response['data']['created_at'] ?? null) ?: gmdate('Y-m-d H:i:s'), 'shop' => (int)$shopId, 'conversation' => (string)$conversationId] as $key => $value) $this->db->bind($key, $value);
      $this->db->exe();
    }
    $this->requestRefresh($shopId, $conversationId);
    return ['ok' => $status === 'sent', 'ambiguous' => $status === 'ambiguous', 'delivery_status' => $status, 'request_id' => $requestId, 'remote_message_id' => $remoteId, 'message' => $status === 'sent' ? 'Terkirim dan dikonfirmasi Shopee.' : ($response['message'] ?? 'Pesan belum terkonfirmasi.')];
  }

  private function outboxResult(array $row, $shopId, $conversationId, $text) {
    if ((int)$row['shop_id'] !== (int)$shopId || $row['conversation_id'] !== (string)$conversationId || $row['content_hash'] !== hash('sha256', $text)) return ['ok' => false, 'message' => 'ID pengiriman sudah digunakan untuk pesan lain.'];
    $sent = $row['status'] === 'sent';
    return ['ok' => $sent, 'ambiguous' => in_array($row['status'], ['sending', 'ambiguous'], true), 'delivery_status' => $row['status'], 'request_id' => $row['request_id'], 'remote_message_id' => $row['remote_message_id'], 'message' => $sent ? 'Pesan sudah terkirim; tidak dikirim ulang.' : ($row['error_message'] ?: 'Pengiriman belum terkonfirmasi. Perbarui riwayat; jangan kirim ulang.')];
  }

  public function markRead($shopId, $conversationId, $reopen = false) {
    $this->ensureSchema();
    $conversation = $this->conversation($shopId, $conversationId);
    $shop = $this->shops($shopId)[0] ?? null;
    if (!$conversation || !$shop || empty($shop['cookie'])) return ['ok' => false, 'message' => 'Toko atau percakapan tidak ditemukan.'];
    $client = $this->client();
    if (!$reopen && $conversation['status'] === 'closed') return ['ok' => false, 'message' => 'Gunakan Chat Lagi untuk mengaktifkan percakapan tertutup.'];
    if (!empty($conversation['is_blocked'])) return ['ok' => false, 'message' => 'Percakapan diblokir di Shopee.'];
    $session = $this->session($shop);
    if (empty($session['ok'])) return $this->saveError($shop, !empty($session['expired']), $session['message']);
    $response = $client->markRead($session, $shop['cookie'], $conversation['remote_conversation_id']);
    if (empty($response['ok'])) return ['ok' => false, 'message' => $response['message'] ?? 'Status pesan gagal diperbarui.'];
    $this->db->query("UPDATE chat_conversations SET unread_count = 0, status = 'activated' WHERE id = :id AND shop_id = :shop_id");
    $this->db->bind('id', (int)$conversation['id']);
    $this->db->bind('shop_id', (int)$shopId);
    $this->db->exe();
    return ['ok' => true, 'message' => $reopen ? 'Percakapan diaktifkan. Izin balas akan diperiksa kembali saat kirim.' : 'Percakapan ditandai sudah dibaca.'];
  }
}
