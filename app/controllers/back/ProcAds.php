<?php

class ProcAds extends Controller {
  private function json($payload, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
  }

  private function requireAjax() {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    if (!$isAjax) {
      $this->json(['status' => 'error', 'message' => 'Invalid request.'], 400);
    }
  }

  public function summary() {
    $this->requireAjax();
    $shopId = (int)($_GET['shop_id'] ?? $_POST['shop_id'] ?? 0);
    $shops = $this->m('AdsMonitor')->summary($shopId > 0 ? $shopId : null, false);
    $period = null;
    foreach ($shops as $shop) {
      $candidate = $shop['metrics']['performance']['period'] ?? null;
      if (is_array($candidate)) {
        $period = $candidate;
        break;
      }
    }
    $this->json([
      'status' => 'success',
      'period' => $period ?: ['label' => '7 hari terakhir', 'timezone' => 'Asia/Jakarta'],
      'shops' => $shops,
      'refreshed_at' => date('c')
    ]);
  }

  public function mcp() {
    $this->requireAjax();
    $shopId = (int)($_POST['shop_id'] ?? $_GET['shop_id'] ?? 0);
    if ($shopId < 1) $this->json(['status' => 'error', 'message' => 'Pilih toko yang akan diisi dari history Sniper.'], 422);
    $result = $this->m('AdsMonitor')->syncShopFromSniper($shopId);
    if (empty($result['ok'])) $this->json(['status' => 'error', 'message' => $result['message'] ?? 'History Sniper gagal diimpor.'], 502);
    $this->json(['status' => 'success', 'message' => 'History Sniper berhasil dipetakan ke toko.', 'period' => $result['period'] ?? null, 'channels' => $result['channels'] ?? []]);
  }
}
