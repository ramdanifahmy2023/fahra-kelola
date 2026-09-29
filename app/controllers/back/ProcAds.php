<?php

class ProcAds extends Controller {
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

  public function summary() {
    $this->requireAjax();
    $period = $_GET['period'] ?? 'daily';
    $channel = $_GET['channel'] ?? 'product';
    if (!in_array($period, ['daily', 'weekly', 'monthly'], true) || !in_array($channel, ['product', 'shop', 'live'], true)) {
      $this->json(['status' => 'error', 'message' => 'Periode atau channel iklan tidak valid.'], 422);
    }
    $shopId = (int)($_GET['shop_id'] ?? $_POST['shop_id'] ?? 0);
    $shops = $this->m('AdsMonitor')->summary($shopId > 0 ? $shopId : null, false, $period, $channel);
    $this->json(['status' => 'success', 'period' => $period, 'channel' => $channel, 'shops' => $shops, 'refreshed_at' => date('c')]);
  }

  public function topups() {
    $this->requireAjax();
    $shopId = (int)($_GET['shop_id'] ?? $_POST['shop_id'] ?? 0);
    $period = (string)($_GET['period'] ?? 'all');
    if (!in_array($period, ['all', 'this_month', 'last_month', 'last_3_months', 'custom'], true)) {
      $this->json(['status' => 'error', 'message' => 'Pilihan periode topup tidak valid.'], 422);
    }
    try {
      $report = $this->m('AdsMonitor')->topupSummary(
        $shopId > 0 ? $shopId : null,
        $period,
        (string)($_GET['start_date'] ?? ''),
        (string)($_GET['end_date'] ?? '')
      );
    } catch (InvalidArgumentException $error) {
      $this->json(['status' => 'error', 'message' => $error->getMessage()], 422);
    }
    $this->json(['status' => 'success', 'report' => $report]);
  }
}
