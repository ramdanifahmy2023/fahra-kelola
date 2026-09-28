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
    $requestedShopIds = $_GET['shop_ids'] ?? $_POST['shop_ids'] ?? null;
    if ($requestedShopIds === null) {
      $legacyShopId = (int)($_GET['shop_id'] ?? $_POST['shop_id'] ?? 0);
      $requestedShopIds = $legacyShopId > 0 ? [$legacyShopId] : [];
    }
    if (!is_array($requestedShopIds)) $requestedShopIds = [$requestedShopIds];
    $requestedShopIds = array_values(array_unique(array_filter(array_map('intval', $requestedShopIds))));

    $shopModel = $this->m('Shop');
    $shops = $requestedShopIds ? array_filter(array_map(function ($shopId) use ($shopModel) {
      return $shopModel->findBy('id', $shopId);
    }, $requestedShopIds)) : $shopModel->findAll();
    if (!$shops) {
      $this->json(['status' => 'error', 'message' => 'Data toko tidak ditemukan.'], 404);
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

    $keyMetrics = ['uv' => 0, 'pv' => 0, 'product_clicks' => 0, 'orders' => 0, 'buyers' => 0, 'sales' => 0];
    $hourly = [];
    $products = [];
    $updatedAt = 0;
    foreach ($successful as $item) {
      $metrics = $item['metrics'];
      foreach ($keyMetrics as $key => $value) $keyMetrics[$key] += (float)($metrics['key_metrics'][$key] ?? 0);
      foreach (($metrics['sales_hourly'] ?? []) as $index => $value) $hourly[$index] = ($hourly[$index] ?? 0) + (float)$value;
      foreach (($metrics['top_sales_items'] ?? []) as $product) {
        $name = trim((string)($product['item_name'] ?? 'Produk')) ?: 'Produk';
        $products[$name] = ($products[$name] ?? 0) + (float)($product['sales'] ?? 0);
      }
      $updatedAt = max($updatedAt, (int)($metrics['time'] ?? 0));
    }
    arsort($products);
    $topProducts = [];
    foreach (array_slice($products, 0, 5, true) as $name => $sales) $topProducts[] = ['item_name' => $name, 'sales' => $sales];
    ksort($hourly);

    $this->json([
      'status' => 'success',
      'shop_count' => count($successful),
      'stores' => array_map(function ($item) { return ['shop_id' => $item['shop_id'], 'shop_name' => $item['shop_name']]; }, $successful),
      'failures' => $failures,
      'metrics' => ['key_metrics' => $keyMetrics, 'top_sales_items' => $topProducts, 'sales_hourly' => array_values($hourly), 'time' => $updatedAt]
    ]);
  }
}
