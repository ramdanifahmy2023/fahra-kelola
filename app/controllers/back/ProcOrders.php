<?php

class ProcOrders extends Controller {
  private function json($payload, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
  }

  private function requireAjax() {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    if (!$isAjax) {
      $this->json(['status' => 'error', 'message' => 'Invalid request.'], 400);
    }
  }

  public function sync_index() {
    $this->requireAjax();
    $shopId = (int)($_POST['shop_id'] ?? 0);
    if ($shopId < 1) {
      $this->json(['status' => 'error', 'message' => 'Shop ID tidak ditemukan.'], 422);
    }
    $shop = $this->m('Shop')->findBy('id', $shopId);
    if (!$shop || empty($shop['cookie'])) {
      $this->json(['status' => 'error', 'message' => 'Data toko tidak ditemukan atau cookie kosong.'], 404);
    }
    $mode = ($_POST['sync_mode'] ?? '') === 'full' ? 'full' : 'diff';
    $sync = $this->m('SyncJob');
    $jobId = $sync->enqueue($shopId, $mode);
    $this->json(['status' => 'accepted', 'job_id' => $jobId, 'mode' => $mode, 'poll_url' => burl . '/procorders/sync_status']);
  }

  public function sync_status() {
    $this->requireAjax();
    $shopId = (int)($_POST['shop_id'] ?? $_GET['shop_id'] ?? 0);
    $jobId = (int)($_POST['job_id'] ?? $_GET['job_id'] ?? 0);
    if ($shopId < 1 && $jobId < 1) {
      $this->json(['status' => 'error', 'message' => 'Shop ID tidak ditemukan.'], 422);
    }
    $row = $this->m('SyncJob')->status($shopId, $jobId ?: null);
    if (!$row) {
      $this->json(['status' => 'success', 'job' => null, 'queue' => []]);
    }
    $detailTotal = (int)$row['detail_total'];
    $detailDone = (int)$row['detail_done'] + (int)$row['detail_failed'];
    if (($row['status'] ?? '') === 'completed') {
      $row['progress_percent'] = 100;
    } elseif ($detailTotal > 0) {
      $row['progress_percent'] = min(99, (int)round(($detailDone / $detailTotal) * 100));
    } else {
      $row['progress_percent'] = 0;
    }
    $this->json(['status' => 'success', 'job' => $row]);
  }

  public function get_sync_queue() {
    $this->requireAjax();
    $shopId = (int)($_POST['shop_id'] ?? 0);
    if ($shopId < 1) {
      $this->json(['status' => 'error', 'message' => 'Shop ID tidak ditemukan.'], 422);
    }
    $sync = $this->m('SyncJob');
    $row = $sync->status($shopId);
    $queue = [];
    if ($row) {
      $db = new Database();
      $db->query("SELECT order_id FROM sync_job_orders WHERE job_id = :job_id AND status IN ('queued','retry','running') ORDER BY id ASC");
      $db->bind('job_id', (int)$row['id']);
      foreach ($db->getAll() as $task) {
        $queue[] = (string)$task['order_id'];
      }
    }
    $this->json(['status' => 'success', 'queue' => $queue, 'job' => $row]);
  }

  public function sync_detail() {
    $this->requireAjax();
    $shopId = (int)($_POST['shop_id'] ?? 0);
    $orderId = (int)($_POST['order_id'] ?? 0);
    if ($shopId < 1 || $orderId < 1) {
      $this->json(['status' => 'error', 'message' => 'Missing parameters'], 422);
    }
    $sync = $this->m('SyncJob');
    $jobId = $sync->enqueue($shopId, 'diff');
    $sync->queueOrder($jobId, $orderId);
    $this->json(['status' => 'accepted', 'job_id' => $jobId, 'order_id' => $orderId]);
  }
}
