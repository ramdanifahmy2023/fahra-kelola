<?php

class ProcPromotions extends Controller {
  private function json($payload, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
  }

  public function summary() {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    if (!$isAjax) $this->json(['status' => 'error', 'message' => 'Invalid request.'], 400);
    $shopId = (int)($_GET['shop_id'] ?? $_POST['shop_id'] ?? 0);
    $shops = $this->m('PromotionMonitor')->summary($shopId > 0 ? $shopId : null, false);
    $this->json(['status' => 'success', 'shops' => $shops, 'refreshed_at' => date('c')]);
  }
}
