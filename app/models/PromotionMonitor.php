<?php

class PromotionMonitor extends BaseModel {
  protected $table = 'promotion_shop_snapshots';

  public function ensureSchema() {
    $this->db->query("CREATE TABLE IF NOT EXISTS promotion_shop_snapshots (
      shop_id INT NOT NULL,
      status VARCHAR(20) NOT NULL DEFAULT 'unknown',
      payload LONGTEXT NULL,
      error_message TEXT NULL,
      synced_at DATETIME NULL,
      updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (shop_id),
      KEY promotion_snapshots_status (status),
      KEY promotion_snapshots_synced (synced_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $this->db->exe();
  }

  private function shops($shopId = null) {
    $where = '';
    if ((int)$shopId > 0) $where = ' WHERE id = :shop_id';
    $this->db->query("SELECT id, name, sync_status, cookie FROM shops{$where} ORDER BY name ASC");
    if ($where) $this->db->bind('shop_id', (int)$shopId);
    return $this->db->getAll();
  }

  private function isActiveVoucher(array $voucher, $now) {
    $start = (int)($voucher['start_time'] ?? 0);
    $end = (int)($voucher['end_time'] ?? 0);
    return ($voucher['status'] ?? 0) == 1 && $start <= $now && $end >= $now;
  }

  private function normalizeVoucher(array $voucher) {
    $rule = is_array($voucher['rule'] ?? null) ? $voucher['rule'] : [];
    $discount = null;
    $discountType = 'nominal';
    if (isset($voucher['discount']) && (float)$voucher['discount'] > 0) {
      $discount = (float)$voucher['discount'];
      $discountType = 'persen';
    } elseif (isset($voucher['value'])) {
      $discount = (float)$voucher['value'];
    } elseif (isset($voucher['fe_display_coin_amount'])) {
      $discount = (float)$voucher['fe_display_coin_amount'];
      $discountType = 'koin';
    }
    $coinVoucher = is_array($rule['coin_cashback_voucher'] ?? null) ? $rule['coin_cashback_voucher'] : [];
    if (!empty($coinVoucher['coin_percentage_real'])) {
      $discount = (float)$coinVoucher['coin_percentage_real'];
      $discountType = 'koin_persen';
    }
    return [
      'id' => (string)($voucher['voucher_id'] ?? ''),
      'name' => (string)($voucher['name'] ?? 'Voucher tanpa nama'),
      'discount' => $discount,
      'discount_type' => $discountType,
      'max_coin' => (float)($coinVoucher['max_coin'] ?? 0),
      'min_price' => (float)($voucher['min_price'] ?? 0),
      'start_time' => (int)($voucher['start_time'] ?? 0),
      'end_time' => (int)($voucher['end_time'] ?? 0),
      'usage_limit' => (int)($voucher['usage_limit'] ?? 0),
      'current_usage' => (int)($voucher['current_usage'] ?? 0),
      'status' => (int)($voucher['status'] ?? 0),
      'voucher_code' => (string)($voucher['voucher_code'] ?? ''),
      'rule' => $rule
    ];
  }

  private function normalizeFlashSale(array $sale, $now) {
    $start = (int)($sale['start_time'] ?? 0);
    $end = (int)($sale['end_time'] ?? 0);
    return [
      'id' => (string)($sale['flash_sale_id'] ?? ''),
      'start_time' => $start,
      'end_time' => $end,
      'status' => (int)($sale['status'] ?? 0),
      'item_count' => (int)($sale['item_count'] ?? 0),
      'enabled_item_count' => (int)($sale['enabled_item_count'] ?? 0),
      'timeslot_id' => (string)($sale['timeslot_id'] ?? ''),
      'active' => $start <= $now && $end >= $now && (int)($sale['enabled_item_count'] ?? 0) > 0
    ];
  }

  public function syncShop(array $shop) {
    $this->ensureSchema();
    require_once __DIR__ . '/ShopeeCurl.php';
    $cookie = trim($shop['cookie'] ?? '');
    if ($cookie === '') return $this->saveError($shop, 'expired', 'Cookie toko kosong. Masukkan cookie Shopee terbaru.');

    $shopee = new ShopeeCurl();
    $session = $shopee->check($cookie);
    if (!isset($session['shop']['id'])) {
      return $this->saveError($shop, 'expired', 'Sesi Shopee toko habis atau tidak valid. Perbarui cookie toko.');
    }

    $now = time();
    $voucherRows = [];
    $voucherTotal = 0;
    $offset = 0;
    do {
      $page = $shopee->getVoucherList($cookie, $offset, 100);
      if ($page === false) return $this->saveError($shop, 'error', 'Daftar voucher Shopee tidak tersedia.');
      $voucherTotal = (int)$page['total_count'];
      foreach ($page['vouchers'] as $voucher) {
        $normalized = $this->normalizeVoucher($voucher);
        if ($this->isActiveVoucher($voucher, $now)) $voucherRows[] = $normalized;
      }
      $received = count($page['vouchers']);
      $offset += $received;
    } while ($received > 0 && $offset < $voucherTotal && $offset < 5000);

    $flashPage = $shopee->getFlashSaleList($cookie, 0, 100);
    if ($flashPage === false) return $this->saveError($shop, 'error', 'Daftar flash sale Shopee tidak tersedia.');
    $flashRows = array_map(fn($sale) => $this->normalizeFlashSale($sale, $now), $flashPage['flash_sales']);
    $activeFlash = array_values(array_filter($flashRows, fn($sale) => !empty($sale['active'])));

    $payload = [
      'vouchers' => $voucherRows,
      'voucher_total' => $voucherTotal,
      'flash_sales' => $flashRows,
      'flash_sale_total' => (int)$flashPage['total_count'],
      'active_flash_sales' => $activeFlash,
      'active_voucher_count' => count($voucherRows),
      'active_flash_sale_count' => count($activeFlash),
      'captured_at' => date('c')
    ];
    $this->db->query("UPDATE shops SET sync_status = 'connected' WHERE id = :shop_id");
    $this->db->bind('shop_id', (int)$shop['id']);
    $this->db->exe();
    $this->db->query("INSERT INTO {$this->table} (shop_id, status, payload, error_message, synced_at) VALUES (:shop_id, 'ok', :payload, NULL, NOW()) ON DUPLICATE KEY UPDATE status = 'ok', payload = VALUES(payload), error_message = NULL, synced_at = NOW()");
    $this->db->bind('shop_id', (int)$shop['id']);
    $this->db->bind('payload', json_encode($payload, JSON_UNESCAPED_UNICODE));
    $this->db->exe();
    return true;
  }

  private function saveError(array $shop, $status, $message) {
    $this->db->query("INSERT INTO {$this->table} (shop_id, status, payload, error_message, synced_at) VALUES (:shop_id, :status, NULL, :message, NOW()) ON DUPLICATE KEY UPDATE status = VALUES(status), error_message = VALUES(error_message), synced_at = NOW()");
    $this->db->bind('shop_id', (int)$shop['id']);
    $this->db->bind('status', $status);
    $this->db->bind('message', $message);
    $this->db->exe();
    $this->db->query("UPDATE shops SET sync_status = :sync_status WHERE id = :shop_id");
    $this->db->bind('sync_status', $status === 'expired' ? 'expired' : 'connected');
    $this->db->bind('shop_id', (int)$shop['id']);
    $this->db->exe();
    return false;
  }

  public function summary($shopId = null, $refresh = true) {
    $this->ensureSchema();
    $result = [];
    foreach ($this->shops($shopId) as $shop) {
      $this->db->query("SELECT status, payload, error_message, synced_at FROM {$this->table} WHERE shop_id = :shop_id LIMIT 1");
      $this->db->bind('shop_id', (int)$shop['id']);
      $snapshot = $this->db->single();
      $age = !empty($snapshot['synced_at']) ? time() - strtotime($snapshot['synced_at'] . ' UTC') : PHP_INT_MAX;
      if ($refresh && (!is_array($snapshot) || $age >= 300) && !empty($shop['cookie'])) {
        $this->syncShop($shop);
        $this->db->query("SELECT status, payload, error_message, synced_at FROM {$this->table} WHERE shop_id = :shop_id LIMIT 1");
        $this->db->bind('shop_id', (int)$shop['id']);
        $snapshot = $this->db->single();
        $age = !empty($snapshot['synced_at']) ? time() - strtotime($snapshot['synced_at'] . ' UTC') : PHP_INT_MAX;
      }
      $payload = !empty($snapshot['payload']) ? json_decode($snapshot['payload'], true) : [];
      $expired = ($snapshot['status'] ?? '') === 'expired' || trim($shop['cookie'] ?? '') === '';
      $result[] = [
        'shop_id' => (int)$shop['id'], 'shop_name' => $shop['name'] ?? '',
        'status' => $snapshot['status'] ?? 'pending', 'session_expired' => $expired,
        'session_status' => $expired ? 'expired' : ($shop['sync_status'] ?? 'connected'),
        'error_message' => $snapshot['error_message'] ?? null, 'synced_at' => $snapshot['synced_at'] ?? null,
        'stale' => !empty($snapshot['synced_at']) && $age >= 300,
        'summary' => is_array($payload) ? $payload : []
      ];
    }
    return $result;
  }
}
