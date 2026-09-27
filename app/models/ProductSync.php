<?php

/** Durable, session-free product synchronizer for the background worker. */
class ProductSync extends BaseModel {
  private $pageSize = 48;

  private function ensureCheckpointSchema() {
    $this->db->query("CREATE TABLE IF NOT EXISTS sync_checkpoints (
      shop_id INT NOT NULL,
      sync_type VARCHAR(50) NOT NULL,
      mode VARCHAR(16) NOT NULL DEFAULT 'diff',
      `cursor` TEXT NULL,
      page_number INT NOT NULL DEFAULT 0,
      seen_products LONGTEXT NULL,
      seen_models LONGTEXT NULL,
      status VARCHAR(16) NOT NULL DEFAULT 'running',
      last_error TEXT NULL,
      updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (shop_id, sync_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $this->db->exe();
  }

  public function run(array $shop, $mode = 'diff', $maxPages = 100, $rateMs = 350) {
    $shopId = (int)($shop['id'] ?? 0);
    $cookie = trim((string)($shop['cookie'] ?? ''));
    if ($shopId < 1 || $cookie === '') return ['ok' => false, 'message' => 'Cookie toko kosong.'];
    require_once __DIR__ . '/Product.php';
    require_once __DIR__ . '/ProductModel.php';
    require_once __DIR__ . '/ShopeeCurl.php';
    $this->ensureCheckpointSchema();
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
    $this->db->query("SELECT * FROM sync_checkpoints WHERE shop_id = :shop_id AND sync_type = 'products' LIMIT 1");
    $this->db->bind('shop_id', $shopId);
    $checkpoint = $this->db->single();
    if ($checkpoint && (string)$checkpoint['mode'] === (string)$mode && !empty($checkpoint['cursor'])) {
      $cursor = (string)$checkpoint['cursor'];
      $page = (int)$checkpoint['page_number'];
      $seenProducts = json_decode((string)($checkpoint['seen_products'] ?? ''), true) ?: [];
      $seenModels = json_decode((string)($checkpoint['seen_models'] ?? ''), true) ?: [];
    } else {
      $this->saveCheckpoint($shopId, $mode, '', 0, [], [], 'running', null);
    }

    do {
      $response = $shopee->getProductsPage($cookie, $this->pageSize, $cursor);
      if ($response === false) {
        $this->saveCheckpoint($shopId, $mode, $cursor, $page, $seenProducts, $seenModels, 'failed', 'Gagal menarik produk dari Shopee.');
        return ['ok' => false, 'message' => 'Gagal menarik produk dari Shopee.', 'pages' => $page, 'added' => $added, 'updated' => $updated];
      }
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
      $this->saveCheckpoint($shopId, $mode, $cursor, $page, $seenProducts, $seenModels, $cursor === '' ? 'completed' : 'running', null);
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

  private function saveCheckpoint($shopId, $mode, $cursor, $page, array $products, array $models, $status, $error) {
    $this->db->query("INSERT INTO sync_checkpoints (shop_id, sync_type, mode, `cursor`, page_number, seen_products, seen_models, status, last_error) VALUES (:shop_id, 'products', :mode, :cursor, :page_number, :seen_products, :seen_models, :status, :last_error) ON DUPLICATE KEY UPDATE mode = VALUES(mode), `cursor` = VALUES(`cursor`), page_number = VALUES(page_number), seen_products = VALUES(seen_products), seen_models = VALUES(seen_models), status = VALUES(status), last_error = VALUES(last_error)");
    $this->db->bind('shop_id', (int)$shopId);
    $this->db->bind('mode', (string)$mode);
    $this->db->bind('cursor', $cursor === '' ? null : $cursor);
    $this->db->bind('page_number', (int)$page);
    $this->db->bind('seen_products', json_encode(array_keys($products)));
    $this->db->bind('seen_models', json_encode(array_keys($models)));
    $this->db->bind('status', (string)$status);
    $this->db->bind('last_error', $error);
    $this->db->exe();
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
