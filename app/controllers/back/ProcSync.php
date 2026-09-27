<?php

class ProcSync extends Controller {
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

  public function enqueue() {
    $this->requireAjax();
    $shopId = (int)($_POST['shop_id'] ?? 0);
    $type = strtolower(trim((string)($_POST['sync_type'] ?? '')));
    $mode = ($_POST['sync_mode'] ?? '') === 'full' ? 'full' : 'diff';
    $allowed = ['orders', 'products', 'ads', 'promotions', 'chat', 'customers', 'shops', 'packages'];
    if (($shopId < 1 && $type !== 'customers') || !in_array($type, $allowed, true)) $this->json(['status' => 'error', 'message' => 'Toko dan tipe sinkronisasi wajib diisi.'], 422);
    if ($type === 'customers' && $shopId < 1) {
      $shops = $this->m('Shop')->findAll();
      $jobs = [];
      foreach ($shops as $shop) {
        if (empty($shop['cookie'])) continue;
        $jobs[] = ['shop_id' => (int)$shop['id'], 'job_id' => $this->m('SyncJob')->enqueueType((int)$shop['id'], 'customers', $mode)];
      }
      if (!$jobs) $this->json(['status' => 'error', 'message' => 'Tidak ada toko dengan cookie aktif.'], 404);
      $this->json(['status' => 'accepted', 'sync_type' => $type, 'jobs' => $jobs]);
    }
    $shop = $this->m('Shop')->findBy('id', $shopId);
    if (!$shop || empty($shop['cookie'])) $this->json(['status' => 'error', 'message' => 'Cookie toko kosong atau toko tidak ditemukan.'], 404);
    $sync = $this->m('SyncJob');
    $jobId = $type === 'orders' ? $sync->enqueue($shopId, $mode) : $sync->enqueueType($shopId, $type, $mode);
    if ($jobId < 1) $this->json(['status' => 'error', 'message' => 'Antrean sinkronisasi gagal dibuat.'], 500);
    $this->json(['status' => 'accepted', 'job_id' => $jobId, 'shop_id' => $shopId, 'sync_type' => $type, 'mode' => $mode]);
  }

  public function status() {
    $this->requireAjax();
    $shopId = (int)($_GET['shop_id'] ?? $_POST['shop_id'] ?? 0);
    $rows = $this->m('BackgroundSync')->status($shopId);
    $this->json(['status' => 'success', 'schedules' => $rows, 'refreshed_at' => date('c')]);
  }

  public function configure() {
    $this->requireAjax();
    $shopId = (int)($_POST['shop_id'] ?? 0);
    $type = strtolower(trim((string)($_POST['sync_type'] ?? '')));
    $interval = (int)($_POST['interval_seconds'] ?? 0);
    $enabled = (int)($_POST['enabled'] ?? 1) === 1 ? 1 : 0;
    if ($shopId < 1 || $interval < 30) $this->json(['status' => 'error', 'message' => 'Parameter jadwal tidak valid.'], 422);
    if (!$this->m('BackgroundSync')->configure($shopId, $type, $interval, $enabled)) $this->json(['status' => 'error', 'message' => 'Tipe sinkronisasi tidak valid.'], 422);
    $this->json(['status' => 'success', 'message' => 'Jadwal sinkronisasi diperbarui.']);
  }
}
