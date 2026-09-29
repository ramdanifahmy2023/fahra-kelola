<?php
require_once __DIR__.'/../../helpers/DashboardMetrics.php';

class ProcRealtime extends Controller {
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

  public function metrics() {
    $this->requireAjax();
    $user = authUser();
    $accountId = (int)($user['id'] ?? 0);
    if ($accountId < 1) {
      $this->json(['status' => 'error', 'message' => 'Sesi login tidak valid.'], 401);
    }

    $requestedShopIds = $_GET['shop_ids'] ?? $_POST['shop_ids'] ?? null;
    if ($requestedShopIds === null) {
      $legacyShopId = (int)($_GET['shop_id'] ?? $_POST['shop_id'] ?? 0);
      $requestedShopIds = $legacyShopId > 0 ? [$legacyShopId] : [];
    }
    if (!is_array($requestedShopIds)) $requestedShopIds = [$requestedShopIds];
    $requestedShopIds = array_values(array_unique(array_filter(array_map('intval', $requestedShopIds))));

    $shopModel = $this->m('Shop');
    $allShops = $shopModel->findAll();
    usort($allShops, static function ($left, $right) {
      return (int)($left['id'] ?? 0) <=> (int)($right['id'] ?? 0);
    });
    $shopsById = [];
    foreach ($allShops as $shop) $shopsById[(int)$shop['id']] = $shop;
    if (array_diff($requestedShopIds,array_keys($shopsById))) {
      $this->json(['status'=>'error','message'=>'Toko tidak ditemukan.'],422);
    }
    $shops = $requestedShopIds
      ? array_values(array_filter(array_map(static function ($shopId) use ($shopsById) {
          return $shopsById[$shopId] ?? null;
        }, $requestedShopIds)))
      : $allShops;
    if (!$shops) {
      $this->json(['status' => 'error', 'message' => 'Data toko tidak ditemukan untuk akun ini.'], 404);
    }

    $shopee = $this->m('ShopeeCurl');
    $successful = [];
    $failures = [];
    foreach ($shops as $shop) {
      if (empty($shop['cookie'])) {
        $failures[] = ['shop_id' => (int)$shop['id'], 'shop_name' => $shop['name'] ?? '', 'message' => 'Cookie toko kosong.'];
        continue;
      }
      $metrics = $shopee->getRealtimeMetrics($shop['cookie']);
      if ($metrics === false) {
        $failures[] = ['shop_id' => (int)$shop['id'], 'shop_name' => $shop['name'] ?? '', 'message' => 'Sesi toko kedaluwarsa atau Shopee tidak tersedia.'];
        continue;
      }
      $successful[] = ['shop_id' => (int)$shop['id'], 'shop_name' => $shop['name'] ?? '', 'metrics' => $metrics];
    }
    if (!$successful) {
      $this->json(['status' => 'error', 'message' => 'Metrik realtime tidak tersedia untuk toko yang dipilih.', 'failures' => $failures], 502);
    }

    $this->json([
      'status' => 'success',
      'shop_count' => count($successful),
      'selected_shop_count' => count($shops),
      'failure_count' => count($failures),
      'stores' => array_map(function ($item) { return ['shop_id' => $item['shop_id'], 'shop_name' => $item['shop_name']]; }, $successful),
      'failures' => $failures,
      'metrics' => DashboardMetrics::combine($successful,count($shops))
    ]);
  }
}
