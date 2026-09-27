<?php

/** Durable, session-free product synchronizer for the background worker. */
class ProductSync extends BaseModel {
  private $pageSize = 48;

  public function run(array $shop, $mode = 'diff', $maxPages = 100, $rateMs = 350) {
    $shopId = (int)($shop['id'] ?? 0);
    $cookie = trim((string)($shop['cookie'] ?? ''));
    if ($shopId < 1 || $cookie === '') return ['ok' => false, 'message' => 'Cookie toko kosong.'];
    require_once __DIR__ . '/Product.php';
    require_once __DIR__ . '/ProductModel.php';
    require_once __DIR__ . '/ShopeeCurl.php';
    $shopee = new ShopeeCurl();
    $productModel = new Product();
    $modelModel = new ProductModel();
    $cursor = '';
    $page = 0;
    $added = 0;
    $updated = 0;
    $seenProducts = [];
    $seenModels = [];
    $total = 0;

    do {
      $response = $shopee->getProductsPage($cookie, $this->pageSize, $cursor);
      if ($response === false) return ['ok' => false, 'message' => 'Gagal menarik produk dari Shopee.', 'pages' => $page, 'added' => $added, 'updated' => $updated];
      $rows = is_array($response['products'] ?? null) ? $response['products'] : [];
      $pageInfo = is_array($response['page_info'] ?? null) ? $response['page_info'] : [];
      $total = max($total, (int)($pageInfo['total'] ?? 0));
      foreach ($rows as $product) {
        $productId = (int)($product['id'] ?? 0);
        if ($productId < 1) continue;
        $seenProducts[$productId] = true;
        foreach (($product['model_list'] ?? []) as $model) {
          $modelId = (int)($model['id'] ?? 0);
          if ($modelId > 0) $seenModels[$modelId] = true;
        }
        $this->upsertProduct($productModel, $product, $shopId, $added, $updated);
        foreach (($product['model_list'] ?? []) as $model) {
          $modelId = (int)($model['id'] ?? 0);
          if ($modelId < 1) continue;
          $data = [
            'id' => $modelId,
            'product_id' => $productId,
            'name' => (string)($model['name'] ?? ''),
            'sku' => (string)($model['sku'] ?? ''),
            'stock' => (int)($model['stock_detail']['total_available_stock'] ?? 0),
            'sold_count' => (int)($model['statistics']['sold_count'] ?? 0),
            'origin_price' => (int)($model['price_detail']['origin_price'] ?? 0),
            'promotion_price' => (int)($model['price_detail']['promotion_price'] ?? 0),
            'image' => (string)($model['image'] ?? ''),
            'is_default' => !empty($model['is_default']) ? 1 : 0
          ];
          $this->upsertModel($modelModel, $data);
        }
      }
      $cursor = (string)($pageInfo['cursor'] ?? '');
      $page++;
      if ($cursor !== '' && count($rows) >= $this->pageSize && $page < $maxPages) usleep(max(100, (int)$rateMs) * 1000);
      else break;
    } while ($page < $maxPages);

    $this->db->query("UPDATE shops SET total_products = :total_products WHERE id = :shop_id");
    $this->db->bind('total_products', $total > 0 ? $total : count($seenProducts));
    $this->db->bind('shop_id', $shopId);
    $this->db->exe();
    if ($mode === 'full' && $cursor === '') $this->cleanup($shopId, array_keys($seenProducts), array_keys($seenModels));
    return ['ok' => true, 'pages' => $page, 'added' => $added, 'updated' => $updated, 'total' => $total ?: count($seenProducts)];
  }

  private function upsertProduct(Product $model, array $product, $shopId, &$added, &$updated) {
    $id = (int)($product['id'] ?? 0);
    $this->db->query('SELECT id FROM products WHERE id = :id LIMIT 1');
    $this->db->bind('id', $id);
    $exists = $this->db->single();
    $data = [
      'id' => $id,
      'shop_id' => $shopId,
      'name' => (string)($product['name'] ?? ''),
      'status' => (int)($product['status'] ?? 1),
      'cover_image' => (string)($product['cover_image'] ?? ''),
      'parent_sku' => (string)($product['parent_sku'] ?? ''),
      'price_min' => (int)($product['price_detail']['price_min'] ?? 0),
      'price_max' => (int)($product['price_detail']['price_max'] ?? 0),
      'selling_price_min' => (int)($product['price_detail']['selling_price_min'] ?? 0),
      'selling_price_max' => (int)($product['price_detail']['selling_price_max'] ?? 0),
      'has_discount' => !empty($product['price_detail']['has_discount']) ? 1 : 0,
      'total_stock' => (int)($product['stock_detail']['total_available_stock'] ?? 0),
      'view_count' => (int)($product['statistics']['view_count'] ?? 0),
      'liked_count' => (int)($product['statistics']['liked_count'] ?? 0),
      'sold_count' => (int)($product['statistics']['sold_count'] ?? 0),
      'promotion_data' => json_encode($product['promotion'] ?? [], JSON_UNESCAPED_UNICODE),
      'create_time' => (int)($product['create_time'] ?? 0),
      'modify_time' => (int)($product['modify_time'] ?? 0),
      'raw_data' => json_encode($product, JSON_UNESCAPED_UNICODE)
    ];
    if ($exists) {
      unset($data['id']);
      $model->update($id, $data);
      $updated++;
    } else {
      $model->insert($data);
      $added++;
    }
  }

  private function upsertModel(ProductModel $model, array $data) {
    $this->db->query('SELECT id FROM product_models WHERE id = :id LIMIT 1');
    $this->db->bind('id', (int)$data['id']);
    $exists = $this->db->single();
    if ($exists) {
      $id = $data['id'];
      unset($data['id']);
      $model->update($id, $data);
    } else {
      $model->insert($data);
    }
  }

  private function cleanup($shopId, array $products, array $models) {
    $this->db->query('DELETE pm FROM product_models pm INNER JOIN products p ON p.id = pm.product_id WHERE p.shop_id = :shop_id' . ($models ? ' AND pm.id NOT IN (' . implode(',', array_map('intval', $models)) . ')' : ''));
    $this->db->bind('shop_id', $shopId);
    $this->db->exe();
    $this->db->query('DELETE FROM products WHERE shop_id = :shop_id' . ($products ? ' AND id NOT IN (' . implode(',', array_map('intval', $products)) . ')' : ''));
    $this->db->bind('shop_id', $shopId);
    $this->db->exe();
  }
}
