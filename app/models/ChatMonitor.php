<?php

class ChatMonitor extends BaseModel {
  protected $table = 'chat_shop_snapshots';
  private $staleSeconds = 30;

  public function ensureSchema() {
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
  }

  private function client() {
    require_once __DIR__ . '/ShopeeChat.php';
    return new ShopeeChat();
  }

  private function shops($shopId = null) {
    $where = '';
    if ((int)$shopId > 0) $where = ' WHERE id = :shop_id';
    $this->db->query("SELECT id, name, cookie, sync_status FROM shops{$where} ORDER BY name ASC");
    if ($where) $this->db->bind('shop_id', (int)$shopId);
    return $this->db->getAll();
  }

  private function snapshot($shopId) {
    $this->db->query("SELECT * FROM {$this->table} WHERE shop_id = :shop_id LIMIT 1");
    $this->db->bind('shop_id', (int)$shopId);
    return $this->db->single() ?: null;
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
    $session = $client->bootstrap($cookie);
    if (empty($session['ok'])) return $this->saveError($shop, !empty($session['expired']), $session['message'] ?? 'Sesi chat Shopee tidak tersedia.');

    $snapshot = $this->snapshot($shopId) ?: [];
    $cursor = [
      'last_message_id' => $snapshot['last_message_id'] ?? '',
      'last_message_region' => $snapshot['last_message_region'] ?? 'ID',
      'next_timestamp_nano' => $snapshot['next_timestamp_nano'] ?? '0'
    ];
    $response = $client->listConversations($session, $cookie, $cursor);
    if (empty($response['ok'])) return $this->saveError($shop, !empty($response['expired']), $response['message'] ?? 'Daftar percakapan tidak tersedia.');
    $data = is_array($response['data'] ?? null) ? $response['data'] : [];
    $conversations = is_array($data['conversations'] ?? null) ? $data['conversations'] : [];
    foreach ($conversations as $conversation) $this->upsertConversation($shopId, $conversation);

    $latestId = (string)($snapshot['last_message_id'] ?? '');
    $latestNano = (string)($snapshot['next_timestamp_nano'] ?? '0');
    $latestRegion = (string)($snapshot['last_message_region'] ?? 'ID');
    foreach ($conversations as $conversation) {
      $candidateId = (string)($conversation['latest_message_id'] ?? '');
      $candidateNano = (string)($conversation['last_message_time_nano'] ?? '');
      if ($candidateId !== '' && ($latestId === '' || strlen($candidateId) > strlen($latestId) || (strlen($candidateId) === strlen($latestId) && strcmp($candidateId, $latestId) > 0))) {
        $latestId = $candidateId;
        $latestRegion = (string)($conversation['last_message_region'] ?? $latestRegion);
      }
      if ($candidateNano !== '' && ($latestNano === '' || strlen($candidateNano) > strlen($latestNano) || (strlen($candidateNano) === strlen($latestNano) && strcmp($candidateNano, $latestNano) > 0))) $latestNano = $candidateNano;
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
      'session_expires_at' => gmdate('Y-m-d H:i:s', (int)$session['access_token_expires_at']),
      'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE)
    ];
    foreach ($values as $key => $value) $this->db->bind($key, $value);
    $this->db->exe();
    $this->db->query("UPDATE shops SET sync_status = 'connected' WHERE id = :shop_id");
    $this->db->bind('shop_id', (int)$shopId);
    $this->db->exe();
    return ['ok' => true, 'status' => 'ok', 'fetched_conversations' => count($conversations)];
  }

  private function saveError(array $shop, $expired, $message) {
    $status = $expired ? 'expired' : 'error';
    $this->db->query("INSERT INTO {$this->table} (shop_id, status, last_sync_at, error_message) VALUES (:shop_id, :status, UTC_TIMESTAMP(), :error_message) ON DUPLICATE KEY UPDATE status = VALUES(status), last_sync_at = UTC_TIMESTAMP(), error_message = VALUES(error_message)");
    $this->db->bind('shop_id', (int)$shop['id']);
    $this->db->bind('status', $status);
    $this->db->bind('error_message', (string)$message);
    $this->db->exe();
    $this->db->query("UPDATE shops SET sync_status = :sync_status WHERE id = :shop_id");
    $this->db->bind('sync_status', $expired ? 'expired' : 'connected');
    $this->db->bind('shop_id', (int)$shop['id']);
    $this->db->exe();
    return ['ok' => false, 'status' => $status, 'message' => (string)$message];
  }

  private function shouldRefresh($snapshot) {
    if (!$snapshot || empty($snapshot['last_sync_at'])) return true;
    return (time() - strtotime($snapshot['last_sync_at'] . ' UTC')) >= $this->staleSeconds;
  }

  private function shopSummary(array $shop, $snapshot) {
    $this->db->query("SELECT COUNT(*) AS conversation_count, COALESCE(SUM(unread_count), 0) AS unread_count, COALESCE(SUM(status = 'activated'), 0) AS active_count, COALESCE(SUM(status = 'closed'), 0) AS closed_count FROM chat_conversations WHERE shop_id = :shop_id");
    $this->db->bind('shop_id', (int)$shop['id']);
    $stats = $this->db->single() ?: [];
    $status = $snapshot['status'] ?? 'pending';
    $expired = $status === 'expired' || ($shop['sync_status'] ?? '') === 'expired';
    return [
      'shop_id' => (int)$shop['id'],
      'shop_name' => $shop['name'] ?? '',
      'remote_shop_id' => !empty($snapshot['remote_shop_id']) ? (int)$snapshot['remote_shop_id'] : null,
      'remote_shop_name' => $snapshot['remote_shop_name'] ?? null,
      'status' => $status,
      'session_status' => $expired ? 'expired' : ($shop['sync_status'] ?? 'unknown'),
      'session_expired' => $expired,
      'error_message' => $snapshot['error_message'] ?? null,
      'last_sync_at' => $snapshot['last_sync_at'] ?? null,
      'unread_count' => (int)($stats['unread_count'] ?? $snapshot['unread_count'] ?? 0),
      'conversation_count' => (int)($stats['conversation_count'] ?? 0),
      'active_count' => (int)($stats['active_count'] ?? 0),
      'closed_count' => (int)($stats['closed_count'] ?? 0),
      'stale' => $this->shouldRefresh($snapshot)
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
    $where = ['1 = 1'];
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
    $this->db->query('SELECT * FROM chat_conversations WHERE shop_id = :shop_id AND (id = :id OR remote_conversation_id = :remote_id) LIMIT 1');
    $this->db->bind('shop_id', (int)$shopId);
    $this->db->bind('id', (int)$conversationId);
    $this->db->bind('remote_id', (string)$conversationId);
    return $this->db->single() ?: null;
  }

  public function messages($shopId, $conversationId, $refresh = true) {
    $this->ensureSchema();
    $conversation = $this->conversation($shopId, $conversationId);
    if (!$conversation) return ['ok' => false, 'status' => 'error', 'message' => 'Percakapan tidak ditemukan.'];
    if ($refresh) {
      $shop = $this->shops($shopId)[0] ?? null;
      if ($shop && !empty($shop['cookie'])) {
        $client = $this->client();
        $session = $client->bootstrap($shop['cookie']);
        if (!empty($session['ok'])) {
          $response = $client->getMessages($session, $shop['cookie'], $conversation['remote_conversation_id'], 100, 0);
          if (!empty($response['ok'])) {
            foreach ((array)($response['data'] ?? []) as $message) $this->upsertMessage($shopId, $conversation['remote_conversation_id'], $message, (int)($session['remote_user_id'] ?? 0));
          } elseif (!empty($response['expired'])) {
            $this->saveError($shop, true, $response['message']);
          }
        } else {
          $this->saveError($shop, !empty($session['expired']), $session['message']);
        }
      }
    }
    $this->db->query('SELECT id, remote_message_id, sender_id, receiver_id, sender_name, message_type, direction, content_text, content_json, remote_created_at, remote_status FROM chat_messages WHERE shop_id = :shop_id AND remote_conversation_id = :conversation_id ORDER BY COALESCE(remote_created_at, created_at) ASC LIMIT 200');
    $this->db->bind('shop_id', (int)$shopId);
    $this->db->bind('conversation_id', $conversation['remote_conversation_id']);
    return ['ok' => true, 'conversation' => $conversation, 'messages' => $this->db->getAll()];
  }

  public function send($shopId, $conversationId, $text) {
    $this->ensureSchema();
    $conversation = $this->conversation($shopId, $conversationId);
    $shop = $this->shops($shopId)[0] ?? null;
    if (!$conversation || !$shop || empty($shop['cookie'])) return ['ok' => false, 'status' => 'error', 'message' => 'Toko atau percakapan tidak ditemukan.'];
    $client = $this->client();
    $session = $client->bootstrap($shop['cookie']);
    if (empty($session['ok'])) return $this->saveError($shop, !empty($session['expired']), $session['message']);
    $response = $client->sendMessage($session, $shop['cookie'], $conversation, $text);
    if (empty($response['ok'])) {
      if (!empty($response['expired'])) $this->saveError($shop, true, $response['message']);
      return ['ok' => false, 'status' => 'error', 'message' => $response['message'] ?? 'Pesan gagal dikirim.'];
    }
    $message = is_array($response['data'] ?? null) ? $response['data'] : [];
    if (!empty($message['id'])) $this->upsertMessage($shopId, $conversation['remote_conversation_id'], $message, (int)($session['user_id'] ?? 0), 'outgoing');
    return ['ok' => true, 'message' => 'Pesan berhasil dikirim.'];
  }

  public function markRead($shopId, $conversationId) {
    $this->ensureSchema();
    $conversation = $this->conversation($shopId, $conversationId);
    $shop = $this->shops($shopId)[0] ?? null;
    if (!$conversation || !$shop || empty($shop['cookie'])) return ['ok' => false, 'message' => 'Toko atau percakapan tidak ditemukan.'];
    $client = $this->client();
    $session = $client->bootstrap($shop['cookie']);
    if (empty($session['ok'])) return $this->saveError($shop, !empty($session['expired']), $session['message']);
    $response = $client->markRead($session, $shop['cookie'], $conversation['remote_conversation_id']);
    if (empty($response['ok'])) return ['ok' => false, 'message' => $response['message'] ?? 'Status pesan gagal diperbarui.'];
    $this->db->query('UPDATE chat_conversations SET unread_count = 0 WHERE id = :id AND shop_id = :shop_id');
    $this->db->bind('id', (int)$conversation['id']);
    $this->db->bind('shop_id', (int)$shopId);
    $this->db->exe();
    return ['ok' => true, 'message' => 'Percakapan ditandai sudah dibaca.'];
  }
}
