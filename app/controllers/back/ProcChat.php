<?php

class ProcChat extends Controller {
  private function json($payload, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
  }

  private function requireAjax() {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    if (!$isAjax) $this->json(['status' => 'error', 'message' => 'Invalid request.'], 400);
  }

  private function monitor() {
    $monitor = $this->m('ChatMonitor');
    $monitor->ensureSchema();
    return $monitor;
  }

  public function overview() {
    $this->requireAjax();
    $shopId = (int)($_GET['shop_id'] ?? 0);
    $refresh = isset($_GET['refresh']) && $_GET['refresh'] === '1';
    $result = $this->monitor()->overview($shopId > 0 ? $shopId : null, $refresh);
    $this->json(['status' => 'success'] + $result);
  }

  public function conversations() {
    $this->requireAjax();
    $shopId = (int)($_GET['shop_id'] ?? 0);
    $rows = $this->monitor()->conversations(
      $shopId,
      trim((string)($_GET['search'] ?? '')),
      trim((string)($_GET['status'] ?? '')),
      !empty($_GET['unread_only']),
      isset($_GET['refresh']) && $_GET['refresh'] === '1'
    );
    $this->json(['status' => 'success', 'conversations' => $rows, 'refreshed_at' => date('c')]);
  }

  public function messages() {
    $this->requireAjax();
    $shopId = (int)($_GET['shop_id'] ?? 0);
    $conversationId = trim((string)($_GET['conversation_id'] ?? ''));
    if ($shopId < 1 || $conversationId === '') $this->json(['status' => 'error', 'message' => 'Toko dan percakapan wajib dipilih.'], 422);
    $result = $this->monitor()->messages($shopId, $conversationId, false);
    if (empty($result['ok'])) $this->json(['status' => 'error', 'message' => $result['message'] ?? 'Pesan gagal dimuat.'], 404);
    $this->json(['status' => 'success'] + $result);
  }

  public function send() {
    $this->requireAjax();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->json(['status' => 'error', 'message' => 'Metode tidak diizinkan.'], 405);
    $shopId = (int)($_POST['shop_id'] ?? 0);
    $conversationId = trim((string)($_POST['conversation_id'] ?? ''));
    $message = trim((string)($_POST['message'] ?? ''));
    if ($shopId < 1 || $conversationId === '' || $message === '') $this->json(['status' => 'error', 'message' => 'Toko, percakapan, dan pesan wajib diisi.'], 422);
    if (mb_strlen($message) > 2000) $this->json(['status' => 'error', 'message' => 'Pesan maksimal 2.000 karakter.'], 422);
    $result = $this->monitor()->send($shopId, $conversationId, $message);
    if (empty($result['ok'])) $this->json(['status' => 'error', 'message' => $result['message'] ?? 'Pesan gagal dikirim.'], 502);
    $this->json([
      'status' => 'success',
      'message' => $result['message'] ?? 'Pesan berhasil dikirim.',
      'remote_message_id' => $result['remote_message_id'] ?? null
    ]);
  }

  public function mark_read() {
    $this->requireAjax();
    $shopId = (int)($_POST['shop_id'] ?? $_GET['shop_id'] ?? 0);
    $conversationId = trim((string)($_POST['conversation_id'] ?? $_GET['conversation_id'] ?? ''));
    if ($shopId < 1 || $conversationId === '') $this->json(['status' => 'error', 'message' => 'Toko dan percakapan wajib dipilih.'], 422);
    $result = $this->monitor()->markRead($shopId, $conversationId);
    if (empty($result['ok'])) $this->json(['status' => 'error', 'message' => $result['message'] ?? 'Status pesan gagal diperbarui.'], 502);
    $this->json(['status' => 'success', 'message' => $result['message'] ?? 'Percakapan diperbarui.']);
  }
}
