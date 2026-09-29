<?php

class ProcChat extends Controller {
  private function json($payload, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json');
    header('Cache-Control: no-store');
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

  private function requirePost() {
    $this->requireAjax();
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') $this->json(['status' => 'error', 'message' => 'Gunakan POST.'], 405);
    if (!authVerifyCsrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) $this->json(['status' => 'error', 'message' => 'Sesi formulir berakhir. Muat ulang halaman.'], 403);
    session_write_close();
  }

  public function refresh() {
    $this->requirePost();
    $shopId = (int)($_POST['shop_id'] ?? 0);
    $conversationId = trim((string)($_POST['conversation_id'] ?? ''));
    $result = $this->monitor()->requestRefresh($shopId, $conversationId);
    if (empty($result['ok'])) $this->json(['status' => 'error', 'message' => $result['message']], 422);
    $jobId = $this->m('SyncJob')->enqueueType($shopId, 'chat');
    if (!$jobId) $this->json(['status' => 'error', 'message' => 'Pembaruan belum masuk antrean.'], 503);
    $this->json(['status' => 'success', 'job_id' => $jobId, 'message' => 'Pembaruan masuk antrean. Menunggu sinkronisasi selesai.']);
  }

  public function overview() {
    $this->requireAjax();
    $shopId = (int)($_GET['shop_id'] ?? 0);
    $result = $this->monitor()->overview($shopId > 0 ? $shopId : null, false);
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
      false
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
    $this->json(['status'=>'error','attempted'=>false,'message'=>'Chat Shopdash hanya untuk membaca. Balas pesan melalui Shopee.'],405);
  }

  public function mark_read() {
    $this->json(['status'=>'error','attempted'=>false,'message'=>'Status chat Shopee hanya dapat diubah melalui Shopee.'],405);
  }
}
