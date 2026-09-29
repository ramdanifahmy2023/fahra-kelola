<?php

class ProcProducts extends Controller {

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

    private function boostProducts($shopId, $search, $page, $limit) {
        $productModel = $this->m('Product');
        $offset = ($page - 1) * $limit;
        $products = $productModel->findForBoost($shopId, $search, $limit, $offset);
        foreach ($products as &$product) {
            $raw = json_decode($product['raw_data'] ?? '', true);
            $info = is_array($raw['boost_info'] ?? null) ? $raw['boost_info'] : [];
            $product['show_boost_button'] = !array_key_exists('show_boost_button', $info) || !empty($info['show_boost_button']);
            $product['disabled_boost_button'] = !empty($info['disabled_boost_button']);
            $product['boost_entry_status'] = (int)($info['boost_entry_status'] ?? 0);
            unset($product['raw_data']);
        }
        return [$products, $productModel->countForBoost($shopId, $search)];
    }

    public function boost_products() {
        $this->requireAjax();
        $shopId = (int)($_GET['shop_id'] ?? 0);
        if ($shopId < 1) $this->json(['status' => 'error', 'message' => 'Shop ID tidak ditemukan.'], 422);
        $shop = $this->m('Shop')->findBy('id', $shopId);
        if (!$shop) $this->json(['status' => 'error', 'message' => 'Toko tidak ditemukan.'], 404);
        $page = max(1, (int)($_GET['page'] ?? 1));
        $limit = min(20, max(5, (int)($_GET['limit'] ?? 12)));
        $search = trim((string)($_GET['search'] ?? ''));
        [$products, $total] = $this->boostProducts($shopId, $search, $page, $limit);
        $monitor = $this->m('ProductBoostMonitor');
        $cooldowns = $monitor->productCooldowns($shopId, array_column($products, 'id'));
        foreach ($products as &$product) {
            $product['boost_cooldown'] = $cooldowns[(string)$product['id']] ?? [
                'last_boost_at' => null,
                'next_boost_at' => null,
                'cooldown_active' => false,
                'cooldown_seconds' => 0,
                'cooldown_minutes' => 255
            ];
        }
        unset($product);
        $this->json([
            'status' => 'success',
            'shop' => ['id' => (int)$shop['id'], 'name' => $shop['name'] ?? '', 'session_status' => $shop['sync_status'] ?? 'unknown'],
            'products' => $products,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'summary' => $monitor->summary($shopId),
            'history' => $monitor->history($shopId, 5)
        ]);
    }

    public function boost() {
        require_once __DIR__ . '/ProcBoost.php';
        (new ProcBoost())->run();
    }

    public function boost_history() {
        $this->requireAjax();
        $shopId = (int)($_GET['shop_id'] ?? 0);
        if ($shopId < 1) $this->json(['status' => 'error', 'message' => 'Shop ID tidak ditemukan.'], 422);
        $monitor = $this->m('ProductBoostMonitor');
        $this->json(['status' => 'success', 'summary' => $monitor->summary($shopId), 'history' => $monitor->history($shopId, 20)]);
    }
    
    // AJAX method untuk fetch dan save products
    public function add() {
        header('Content-Type: application/json');
        
        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
        if (!$isAjax) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid request.']);
            exit;
        }

        $shopId = $_POST['shop_id'] ?? null;
        if (empty($shopId)) {
            echo json_encode(['status' => 'error', 'message' => 'Shop ID tidak ditemukan.']);
            exit;
        }

        // Ambil data toko
        $shop = $this->m('Shop')->findBy('id', $shopId);
        if (!$shop || empty($shop['cookie'])) {
            echo json_encode(['status' => 'error', 'message' => 'Data toko tidak ditemukan atau cookie kosong.']);
            exit;
        }

        $pageSize = 48;
        $pageNumber = max(1, (int)($_POST['page_number'] ?? 1));
        $cursor = $_POST['next_page_sentinel'] ?? '';
        $syncMode = $_POST['sync_mode'] ?? '';
        $syncSessionKey = 'product_sync_seen_' . $shopId;
        if (empty($cursor)) {
            $_SESSION[$syncSessionKey] = ['products' => [], 'models' => []];
        }
        $canCleanup = isset($_SESSION[$syncSessionKey]);
        $productModel = $this->m('Product');
        $productVarModel = $this->m('ProductModel');
        $isEmpty = $syncMode ? $syncMode === 'full' : $productModel->countWhere(['shop_id' => $shopId]) === 0;

        // Tarik data dari API
        $shopee = $this->m('ShopeeCurl');
        $response = $shopee->getProductsPage($shop['cookie'], $pageSize, $cursor);

        if ($response === false) {
            // Kita coba panggil raw request untuk lihat error dari Shopee
            preg_match('/SPC_CDS=([^;]+)/', $shop['cookie'], $matches);
            $spcCds = $matches[1] ?? '';
            $endpoint = 'https://seller.shopee.co.id/api/v3/opt/mpsku/list/v2/search_product_list?SPC_CDS=' . $spcCds . '&SPC_CDS_VER=2&page_size=12&list_type=all&request_attribute=&operation_sort_by=recommend_v4&need_ads=true';
            $debugResponse = $shopee->request('GET', $endpoint, $shop['cookie']);
            
            echo json_encode(['status' => 'error', 'message' => 'Gagal menarik data dari Shopee.', 'debug' => $debugResponse]);
            exit;
        }

        $addedCount = 0;
        $updatedCount = 0;

        $productsList = $response['products'] ?? [];
        $productIds = array_column($productsList, 'id');
        $modelIds = [];
        foreach ($productsList as $product) {
            foreach ($product['model_list'] ?? [] as $model) {
                $modelIds[] = $model['id'];
            }
        }

        if ($canCleanup) {
            foreach ($productIds as $productId) {
                $_SESSION[$syncSessionKey]['products'][$productId] = true;
            }
            foreach ($modelIds as $modelId) {
                $_SESSION[$syncSessionKey]['models'][$modelId] = true;
            }
        }

        $existingProducts = [];
        if ($productIds) {
            $dbProducts = new Database();
            $productPlaceholders = [];
            foreach ($productIds as $index => $productId) {
                $productPlaceholders[] = ':product_id_' . $index;
            }
            $dbProducts->query('SELECT id, modify_time FROM products WHERE id IN (' . implode(', ', $productPlaceholders) . ')');
            foreach ($productIds as $index => $productId) {
                $dbProducts->bind('product_id_' . $index, $productId);
            }
            foreach ($dbProducts->getAll() as $existingProduct) {
                $existingProducts[$existingProduct['id']] = $existingProduct;
            }
        }

        $existingModels = [];
        if ($modelIds) {
            $dbModels = new Database();
            $modelPlaceholders = [];
            foreach ($modelIds as $index => $modelId) {
                $modelPlaceholders[] = ':model_id_' . $index;
            }
            $dbModels->query('SELECT id FROM product_models WHERE id IN (' . implode(', ', $modelPlaceholders) . ')');
            foreach ($modelIds as $index => $modelId) {
                $dbModels->bind('model_id_' . $index, $modelId);
            }
            foreach ($dbModels->getAll() as $existingModel) {
                $existingModels[$existingModel['id']] = true;
            }
        }
        
        foreach ($productsList as $prod) {
            $existing = $existingProducts[$prod['id']] ?? null;
            $modifyTime = $prod['modify_time'] ?? 0;
            if ($existing && (int)$existing['modify_time'] === (int)$modifyTime) continue;

            $data = [
                'id' => $prod['id'],
                'shop_id' => $shopId,
                'name' => $prod['name'],
                'status' => $prod['status'] ?? 1,
                'cover_image' => $prod['cover_image'] ?? '',
                'parent_sku' => $prod['parent_sku'] ?? '',
                'price_min' => (int) ($prod['price_detail']['price_min'] ?? 0),
                'price_max' => (int) ($prod['price_detail']['price_max'] ?? 0),
                'selling_price_min' => (int) ($prod['price_detail']['selling_price_min'] ?? 0),
                'selling_price_max' => (int) ($prod['price_detail']['selling_price_max'] ?? 0),
                'has_discount' => !empty($prod['price_detail']['has_discount']) ? 1 : 0,
                'total_stock' => $prod['stock_detail']['total_available_stock'] ?? 0,
                'view_count' => $prod['statistics']['view_count'] ?? 0,
                'liked_count' => $prod['statistics']['liked_count'] ?? 0,
                'sold_count' => $prod['statistics']['sold_count'] ?? 0,
                'promotion_data' => json_encode($prod['promotion'] ?? []),
                'create_time' => $prod['create_time'] ?? 0,
                'modify_time' => $modifyTime,
                'raw_data' => json_encode($prod)
            ];

            if ($existing) {
                // Update
                $updateData = $data;
                unset($updateData['id']); // Jangan update PK
                $productModel->update($prod['id'], $updateData);
                $updatedCount++;
            } else {
                // Insert
                $productModel->insert($data);
                $addedCount++;
            }

            // Proses varian (models)
            if (!empty($prod['model_list'])) {
                foreach ($prod['model_list'] as $model) {
                    $existingModel = isset($existingModels[$model['id']]);
                    
                    $mData = [
                        'id' => $model['id'],
                        'product_id' => $prod['id'],
                        'name' => $model['name'] ?? '',
                        'sku' => $model['sku'] ?? '',
                        'stock' => $model['stock_detail']['total_available_stock'] ?? 0,
                        'sold_count' => $model['statistics']['sold_count'] ?? 0,
                        'origin_price' => (int) ($model['price_detail']['origin_price'] ?? 0),
                        'promotion_price' => (int) ($model['price_detail']['promotion_price'] ?? 0),
                        'image' => $model['image'] ?? '',
                        'is_default' => !empty($model['is_default']) ? 1 : 0
                    ];

                    if ($existingModel) {
                        $updateMData = $mData;
                        unset($updateMData['id']);
                        $productVarModel->update($model['id'], $updateMData);
                    } else {
                        $productVarModel->insert($mData);
                    }
                }
            }
        }
        
        // Update total produk di tabel shops
        $totalShopee = $response['page_info']['total'] ?? 0;
        $this->m('Shop')->update($shopId, ['total_products' => $totalShopee]);

        $nextCursor = $response['page_info']['cursor'] ?? '';
        $hasNext = !empty($nextCursor) && count($productsList) === $pageSize;
        $deletedProducts = 0;
        $deletedModels = 0;

        if (!$hasNext && $canCleanup) {
            $seenProducts = array_keys($_SESSION[$syncSessionKey]['products']);
            $seenModels = array_keys($_SESSION[$syncSessionKey]['models']);
            $dbCleanup = new Database();

            if ($seenModels) {
                $modelPlaceholders = [];
                foreach ($seenModels as $index => $modelId) {
                    $modelPlaceholders[] = ':seen_model_' . $index;
                }
                $dbCleanup->query('DELETE product_models FROM product_models INNER JOIN products ON products.id = product_models.product_id WHERE products.shop_id = :shop_id AND product_models.id NOT IN (' . implode(', ', $modelPlaceholders) . ')');
                $dbCleanup->bind('shop_id', $shopId);
                foreach ($seenModels as $index => $modelId) {
                    $dbCleanup->bind('seen_model_' . $index, $modelId);
                }
            } else {
                $dbCleanup->query('DELETE product_models FROM product_models INNER JOIN products ON products.id = product_models.product_id WHERE products.shop_id = :shop_id');
                $dbCleanup->bind('shop_id', $shopId);
            }
            $dbCleanup->exe();
            $deletedModels = $dbCleanup->row();

            if ($seenProducts) {
                $productPlaceholders = [];
                foreach ($seenProducts as $index => $productId) {
                    $productPlaceholders[] = ':seen_product_' . $index;
                }
                $dbCleanup->query('DELETE FROM products WHERE shop_id = :shop_id AND id NOT IN (' . implode(', ', $productPlaceholders) . ')');
                $dbCleanup->bind('shop_id', $shopId);
                foreach ($seenProducts as $index => $productId) {
                    $dbCleanup->bind('seen_product_' . $index, $productId);
                }
            } else {
                $dbCleanup->query('DELETE FROM products WHERE shop_id = :shop_id');
                $dbCleanup->bind('shop_id', $shopId);
            }
            $dbCleanup->exe();
            $deletedProducts = $dbCleanup->row();
            unset($_SESSION[$syncSessionKey]);
        }

        $processed = min($pageNumber * $pageSize, max($totalShopee, count($productsList)));
        $progressPct = $hasNext && $totalShopee > 0
            ? min(99, (int)round(($processed / $totalShopee) * 100))
            : 100;

        echo json_encode([
            'status' => 'success', 
            'message' => "Berhasil sinkronisasi. $addedCount ditambahkan, $updatedCount diperbarui.",
            'added' => $addedCount,
            'updated' => $updatedCount,
            'deleted_products' => $deletedProducts,
            'deleted_models' => $deletedModels,
            'has_next' => $hasNext,
            'next_page_sentinel' => $nextCursor,
            'is_empty_mode' => $isEmpty,
            'progress_percent' => $progressPct,
            'debug' => [
                'total_fetched' => count($productsList),
                'total_shopee' => $totalShopee
            ]
        ]);
        exit;
    }
}
