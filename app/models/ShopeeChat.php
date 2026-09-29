<?php

/**
 * Small client for the Seller Centre webchat API.
 *
 * The chat bearer token is bootstrapped for each request from the shop cookie
 * and is never persisted. Only normalized conversations and messages are
 * stored by ChatMonitor.
 */
class ShopeeChat {
  private $transport;
  public function __construct(?callable $transport = null) { $this->transport = $transport; }

  public static function uuid() {
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    $hex = bin2hex($bytes);
    return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4) . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20);
  }
  private $baseUrl = 'https://seller.shopee.co.id/webchat/api/v1.2';
  private $coreUrl = 'https://seller.shopee.co.id/webchat/api/coreapi/v1.2';

  private function cookies($cookie) {
    $result = [];
    foreach (explode(';', (string)$cookie) as $part) {
      $pair = explode('=', trim($part), 2);
      if (count($pair) === 2 && $pair[0] !== '') $result[$pair[0]] = $pair[1];
    }
    return $result;
  }

  private function http($url, $cookie, $method = 'GET', $body = null, $headers = []) {
    if ($this->transport) return ($this->transport)($url, $cookie, $method, $body, $headers);
    $ch = curl_init($url);
    $defaultHeaders = [
      'Cookie: ' . $cookie,
      'Accept: application/json, text/plain, */*',
      'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 Chrome/120 Safari/537.36',
      'Origin: https://seller.shopee.co.id',
      'Referer: https://seller.shopee.co.id/webchat/conversations'
    ];
    curl_setopt_array($ch, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_CUSTOMREQUEST => strtoupper($method),
      CURLOPT_HTTPHEADER => array_merge($defaultHeaders, $headers),
      CURLOPT_TIMEOUT => 25,
      CURLOPT_CONNECTTIMEOUT => 8,
      CURLOPT_SSL_VERIFYPEER => true,
      CURLOPT_SSL_VERIFYHOST => 2,
      CURLOPT_ENCODING => ''
    ]);
    if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, is_string($body) ? $body : json_encode($body));

    $raw = curl_exec($ch);
    $error = curl_error($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $decoded = json_decode((string)$raw, true);

    return [
      'ok' => $error === '' && $status >= 200 && $status < 300 && (is_array($decoded) || $status === 204),
      'http_code' => $status,
      'body' => is_array($decoded) ? $decoded : null,
      'error' => $error ?: null,
      'raw' => is_array($decoded) ? null : (string)$raw
    ];
  }

  public function bootstrap($cookie) {
    $cookie = trim((string)$cookie);
    $cookies = $this->cookies($cookie);
    if ($cookie === '' || empty($cookies['CTOKEN'])) {
      return ['ok' => false, 'expired' => true, 'message' => 'Cookie atau CTOKEN chat toko tidak tersedia.'];
    }

    $query = http_build_query([
      'csrf_token' => $cookies['CTOKEN'],
      'source' => 'sc',
      '_api_source' => 'sc'
    ]);
    $response = $this->http($this->coreUrl . '/mini/login/sc?' . $query, $cookie, 'POST', '', ['Content-Type: application/json']);
    $body = $response['body'] ?? [];
    $token = (string)($body['token'] ?? '');
    $shop = is_array($body['shop'] ?? null) ? $body['shop'] : [];
    $user = is_array($body['user'] ?? null) ? $body['user'] : [];
    $securityHeaders = [];
    foreach (['af-ac-enc-dat', 'x-sz-sdk-version', 'x-sap-ri', 'x-sap-sec', 'af-ac-enc-sz-token'] as $headerName) {
      if (!empty($cookies[$headerName])) $securityHeaders[$headerName] = $cookies[$headerName];
    }

    if (!$response['ok'] || $token === '' || empty($shop['id'])) {
      $expired = in_array((int)$response['http_code'], [400, 401, 403], true);
      return [
        'ok' => false,
        'expired' => $expired,
        'message' => $this->message($body, $response['error'] ?: 'Sesi chat Shopee tidak dapat dibuat.')
      ];
    }

    return [
      'ok' => true,
      'token' => $token,
      'csrf_token' => $cookies['CTOKEN'],
      'chat_cookie' => $cookies['SPC_CDS_CHAT'] ?? '',
      'uid' => (string)($user['uid'] ?? ''),
      'user_id' => (int)($user['id'] ?? 0),
      'remote_shop_id' => (int)$shop['id'],
      'remote_shop_name' => (string)($shop['name'] ?? ''),
      'remote_user_id' => (int)($shop['user_id'] ?? 0),
      'region' => 'GLOBAL',
      'message_region' => strtoupper((string)($shop['country'] ?? 'ID')),
      'dfp_access_f' => (string)($cookies['dfp_access_f'] ?? ''),
      'security_headers' => $securityHeaders,
      'biz_id' => 2,
      'access_token_expires_at' => null,
      'shop' => $shop,
      'user' => $user
    ];
  }

  private function query($session, array $extra = []) {
    return http_build_query(array_merge([
      '_uid' => $session['uid'] ?? '',
      '_v' => '9.1.17',
      'csrf_token' => $session['csrf_token'] ?? '',
      'SPC_CDS_CHAT' => $session['chat_cookie'] ?? '',
      'x-shop-region' => $session['region'] ?? 'GLOBAL',
      '_api_source' => 'sc'
    ], $extra));
  }

  private function api($session, $cookie, $path, $method = 'GET', $body = null, array $query = []) {
    if (empty($session['token'])) return ['ok' => false, 'http_code' => 401, 'body' => null, 'error' => 'Sesi chat belum dibuat.'];
    $url = $this->baseUrl . $path;
    $queryString = $this->query($session, $query);
    if ($queryString !== '') $url .= '?' . $queryString;
    $headers = [
      'Authorization: Bearer ' . $session['token'],
      'x-shop-region: ' . ($session['region'] ?? 'GLOBAL')
    ];
    foreach ((array)($session['security_headers'] ?? []) as $name => $value) {
      if ($value !== '') $headers[] = $name . ': ' . $value;
    }
    if ($body !== null) $headers[] = 'Content-Type: application/json';
    return $this->http($url, $cookie, $method, $body, $headers);
  }

  public function listShops($session, $cookie) {
    $ids = json_encode([(int)$session['remote_shop_id']]);
    $response = $this->api($session, $cookie, '/mini/shop_list', 'GET', null, ['ids' => $ids]);
    return $this->result($response);
  }

  public function listConversations($session, $cookie, array $cursor = []) {
    $body = [
      'direction' => ($cursor['direction'] ?? 'older') === 'latest' ? 'latest' : 'older',
      'biz_id' => 2,
      'on_message_received' => true,
      'last_message_region' => $cursor['last_message_region'] ?? $session['message_region'] ?? 'ID'
    ];
    if (empty($cursor['last_message_id'])) {
      $body['next_timestamp_nano'] = '0';
    } else {
      $body['last_message_region'] = $cursor['last_message_region'] ?? 'ID';
      $body['last_received_message_id'] = (string)$cursor['last_message_id'];
      $body['next_timestamp_nano'] = (string)($cursor['next_timestamp_nano'] ?? '0');
    }
    $response = $this->api($session, $cookie, '/mini/subaccount/serving_mode/conversations', 'POST', $body);
    $result = $this->result($response);
    if (!empty($result['ok']) && (!is_array($result['data']['conversations'] ?? null) || !array_is_list($result['data']['conversations']))) {
      return ['ok' => false, 'message' => 'Format daftar chat Shopee tidak dikenali.'];
    }
    foreach ($result['data']['conversations'] ?? [] as $row) {
      if (!is_array($row) || empty($row['id']) || empty($row['shop_id'])) return ['ok' => false, 'message' => 'Identitas percakapan dari Shopee tidak lengkap.'];
    }
    return $result;
  }

  public function getMessages($session, $cookie, $conversationId, $limit = 50, $offset = 0) {
    $path = '/mini/conversations/' . rawurlencode((string)$conversationId) . '/messages';
    $messageSession = $session;
    $messageSession['region'] = $session['message_region'] ?? 'ID';
    $response = $this->api($messageSession, $cookie, $path, 'GET', null, [
      'shop_id' => (int)$session['remote_shop_id'],
      'biz_id' => 2,
      'direction' => 'older',
      'limit' => max(1, min(100, (int)$limit)),
      'offset' => max(0, (int)$offset),
      'on_message_received' => true
    ]);
    $result = $this->result($response);
    if (!empty($result['ok']) && (!is_array($result['data']) || !array_is_list($result['data']))) {
      return ['ok' => false, 'message' => 'Format riwayat chat Shopee tidak dikenali.'];
    }
    foreach ($result['data'] ?? [] as $row) {
      if (!is_array($row) || empty($row['id']) || (string)($row['conversation_id'] ?? '') !== (string)$conversationId) return ['ok' => false, 'message' => 'Identitas pesan dari Shopee tidak cocok dengan percakapan.'];
    }
    return $result;
  }

  public function sendMessage($session, $cookie, array $conversation, $text, $requestId = null, $contentUid = null) {
    $message = trim((string)$text);
    if ($message === '') return ['ok' => false, 'expired' => false, 'message' => 'Pesan tidak boleh kosong.'];
    if (empty($conversation['buyer_id']) || empty($conversation['remote_conversation_id'])) return ['ok' => false, 'expired' => false, 'message' => 'Tujuan percakapan Shopee tidak lengkap.'];
    $body = [
      'request_id' => $requestId ?: self::uuid(),
      'to_id' => (int)($conversation['buyer_id'] ?? 0),
      'type' => 'text',
      'content' => ['text' => $message, 'uid' => $contentUid ?: self::uuid()],
      'source_content' => [],
      'shop_id' => (int)$session['remote_shop_id'],
      'chat_send_option' => ['force_send_cancel_order_warning' => false, 'comply_cancel_order_warning' => false],
      'entry_point' => 'direct_chat_entry_point',
      'choice_info' => ['real_shop_id' => null],
      'biz_id' => 2,
      'conversation_id' => (string)$conversation['remote_conversation_id'],
      'source' => 'minichat'
    ];
    if (!empty($session['dfp_access_f'])) $body['re_policy'] = ['dfp_access_f' => $session['dfp_access_f']];
    $session['region'] = $session['message_region'] ?? 'ID';
    $response = $this->api($session, $cookie, '/mini/messages', 'POST', $body, ['uuid' => self::uuid()]);
    $result = $this->result($response);
    if (empty($result['ok'])) {
      // A timeout/5xx may follow acceptance upstream. Never automatically resend it.
      $result['ambiguous'] = (int)($response['http_code'] ?? 0) === 0 || (int)($response['http_code'] ?? 0) >= 500 || !is_array($response['body'] ?? null);
      return $result;
    }
    $payload = $this->messagePayload($result['data']);
    $remoteId = (string)($payload['id'] ?? $payload['message_id'] ?? $payload['msg_id'] ?? '');
    if ($remoteId === '' || ($payload['request_id'] ?? '') !== $body['request_id'] || ($payload['conversation_id'] ?? '') !== $body['conversation_id'] || ($payload['content']['text'] ?? null) !== $message) {
      return [
        'ok' => false,
        'expired' => false,
        'ambiguous' => true,
        'http_code' => $result['http_code'] ?? 200,
        'message' => 'Shopee menerima respons tanpa ID pesan. Balasan tidak ditandai terkirim untuk mencegah duplikasi.'
      ];
    }
    $payload['id'] = $remoteId;
    return ['ok' => true, 'http_code' => $result['http_code'] ?? 200, 'data' => $payload, 'remote_message_id' => $remoteId];
  }

  public function markRead($session, $cookie, $conversationId) {
    $session['region'] = $session['message_region'] ?? 'ID';
    $response = $this->api($session, $cookie, '/mini/conversations/' . rawurlencode((string)$conversationId) . '/status', 'PUT', [
      'shop_id' => (int)$session['remote_shop_id'],
      'status' => 'activated',
      'biz_id' => 2
    ]);
    $result = $this->result($response);
    if (!empty($result['ok']) && (int)$result['http_code'] !== 204) return ['ok' => false, 'message' => 'Status percakapan belum dikonfirmasi Shopee.'];
    return $result;
  }

  public function openConversation($session, $cookie, array $conversation) {
    $session['region'] = $session['message_region'] ?? 'ID';
    $result = $this->result($this->api($session, $cookie, '/mini/conversation/open', 'POST', [
      'biz_id' => 2,
      'oppside_user_id' => (int)$conversation['buyer_id'],
      'shop_id' => (int)$session['remote_shop_id'],
      'to_shop_id' => (int)$conversation['buyer_shop_id'],
      'conversation_id' => (string)$conversation['remote_conversation_id']
    ]));
    if (!empty($result['ok']) && (!array_key_exists('is_chat_availiable', $result['data'] ?? []) || !array_key_exists('conv_is_closed', $result['data'] ?? []))) {
      return ['ok' => false, 'message' => 'Shopee belum mengonfirmasi izin membalas percakapan.'];
    }
    return $result;
  }

  private function result(array $response) {
    $body = $response['body'];
    $apiError = is_array($body) && ((!empty($body['error'])) || (isset($body['code']) && (string)$body['code'] !== '0') || (isset($body['success']) && $body['success'] === false));
    if (!$response['ok'] || $apiError) {
      return [
        'ok' => false,
        'expired' => (int)$response['http_code'] === 401,
        'forbidden' => (int)$response['http_code'] === 403,
        'http_code' => $response['http_code'],
        'message' => $this->message(is_array($body) ? $body : [], $response['error'] ?: 'Shopee Chat tidak merespons.')
      ];
    }
    return ['ok' => true, 'http_code' => $response['http_code'], 'data' => $body];
  }

  private function messagePayload($payload) {
    if (!is_array($payload)) return [];
    if (!empty($payload['id']) || !empty($payload['message_id']) || !empty($payload['msg_id'])) return $payload;
    foreach (['message', 'data', 'result', 'chat_message'] as $key) {
      if (!array_key_exists($key, $payload)) continue;
      $candidate = $this->messagePayload($payload[$key]);
      if ($candidate) return $candidate;
    }
    return [];
  }

  private function message(array $body, $fallback) {
    $code = $body['error'] ?? $body['code'] ?? '';
    if (is_scalar($code) && (string)$code === '90309999') return 'Shopee menolak permintaan dari koneksi ini (90309999). Buka Chat Shopee untuk melanjutkan.';
    $value = $body['message'] ?? $body['msg'] ?? $body['error_msg'] ?? $body['error'] ?? $fallback;
    return is_scalar($value) ? (string)$value : (string)$fallback;
  }
}
