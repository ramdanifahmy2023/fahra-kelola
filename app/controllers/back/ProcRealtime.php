<?php

class ProcRealtime extends Controller {
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

  public function metrics() {
    $this->requireAjax();
    $shopId = (int)($_GET['shop_id'] ?? $_POST['shop_id'] ?? 0);
    if ($shopId < 1) {
      $this->json(['status' => 'error', 'message' => 'Shop ID tidak ditemukan.'], 422);
    }

    $shop = $this->m('Shop')->findBy('id', $shopId);
    if (!$shop || empty($shop['cookie'])) {
      $this->json(['status' => 'error', 'message' => 'Data toko tidak ditemukan atau cookie kosong.'], 404);
    }

    $metrics = $this->m('ShopeeCurl')->getRealtimeMetrics($shop['cookie']);
    if ($metrics === false) {
      $this->json(['status' => 'error', 'message' => 'Metrik realtime Shopee tidak tersedia atau sesi toko sudah kedaluwarsa.'], 502);
    }

    $this->json([
      'status' => 'success',
      'shop_id' => $shopId,
      'shop_name' => $shop['name'] ?? '',
      'metrics' => $metrics
    ]);
  }
}
