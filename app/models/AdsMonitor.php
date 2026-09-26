<?php

class AdsMonitor extends BaseModel {
  protected $table = 'ad_shop_snapshots';

  public function ensureSchema() {
    $this->db->query("CREATE TABLE IF NOT EXISTS ad_shop_snapshots (
      shop_id INT NOT NULL,
      status VARCHAR(20) NOT NULL DEFAULT 'unknown',
      payload LONGTEXT NULL,
      error_message TEXT NULL,
      synced_at DATETIME NULL,
      updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (shop_id),
      KEY ad_shop_snapshots_status (status),
      KEY ad_shop_snapshots_synced (synced_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $this->db->exe();
  }

  private function shops($shopId = null) {
    $where = '';
    if ((int)$shopId > 0) {
      $where = ' WHERE id = :shop_id';
    }
    $this->db->query("SELECT id, name, sync_status, cookie FROM shops{$where} ORDER BY name ASC");
    if ($where) {
      $this->db->bind('shop_id', (int)$shopId);
    }
    return $this->db->getAll();
  }

  public function syncShop(array $shop) {
    $this->ensureSchema();
    require_once __DIR__ . '/ShopeeCurl.php';
    $shopee = new ShopeeCurl();
    $cookie = trim($shop['cookie'] ?? '');
    if ($cookie === '') {
      $this->saveError($shop, 'expired', 'Cookie toko kosong. Masukkan cookie Shopee terbaru.');
      return false;
    }
    $session = $shopee->check($cookie);
    if (!isset($session['shop']['id'])) {
      $message = (string)($session['message'] ?? 'user_is_unauthorized');
      $status = !empty($session['success']) && $session['success'] === false ? 'error' : 'expired';
      $friendly = $status === 'expired'
        ? 'Sesi Shopee toko habis atau tidak valid (' . $message . '). Perbarui cookie toko.'
        : 'Verifikasi sesi toko gagal karena koneksi atau respons Shopee tidak tersedia (' . $message . ').';
      $this->saveError($shop, $status, $friendly);
      return false;
    }
    $payload = $shopee->getAdsSummary($cookie);
    if ($payload === false) {
      $this->saveError($shop, 'error', 'Endpoint metrik iklan tidak tersedia. Sesi dasar toko masih valid.');
      return false;
    }

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
    $this->db->query("INSERT INTO {$this->table} (shop_id, status, payload, error_message, synced_at) VALUES (:shop_id, :status, NULL, :error_message, NOW()) ON DUPLICATE KEY UPDATE status = VALUES(status), error_message = VALUES(error_message), synced_at = NOW()");
    $this->db->bind('shop_id', (int)$shop['id']);
    $this->db->bind('status', $status);
    $this->db->bind('error_message', $message);
    $this->db->exe();
    $this->db->query("UPDATE shops SET sync_status = :sync_status WHERE id = :shop_id");
    $this->db->bind('sync_status', $status === 'expired' ? 'expired' : 'connected');
    $this->db->bind('shop_id', (int)$shop['id']);
    $this->db->exe();
  }

  public function syncAll() {
    $count = 0;
    foreach ($this->shops() as $shop) {
      if ($this->syncShop($shop)) {
        $count++;
      }
    }
    return $count;
  }

  public function summary($shopId = null, $refresh = true) {
    $this->ensureSchema();
    $shops = $this->shops($shopId);
    $result = [];
    foreach ($shops as $shop) {
      $this->db->query("SELECT status, payload, error_message, synced_at FROM {$this->table} WHERE shop_id = :shop_id LIMIT 1");
      $this->db->bind('shop_id', (int)$shop['id']);
      $snapshot = $this->db->single();
      // MariaDB server stores NOW() in UTC in this deployment; parse it explicitly
      // so a fresh snapshot is not marked stale because of the PHP app timezone.
      $age = !empty($snapshot['synced_at']) ? (time() - strtotime($snapshot['synced_at'] . ' UTC')) : PHP_INT_MAX;
      if ($refresh && (!is_array($snapshot) || $age >= 300) && !empty($shop['cookie'])) {
        $this->syncShop($shop);
        $this->db->query("SELECT status, payload, error_message, synced_at FROM {$this->table} WHERE shop_id = :shop_id LIMIT 1");
        $this->db->bind('shop_id', (int)$shop['id']);
        $snapshot = $this->db->single();
        $age = !empty($snapshot['synced_at']) ? (time() - strtotime($snapshot['synced_at'] . ' UTC')) : PHP_INT_MAX;
      }
      $payload = !empty($snapshot['payload']) ? json_decode($snapshot['payload'], true) : [];
      if (($snapshot['status'] ?? '') === 'expired') {
        $shop['sync_status'] = 'expired';
      } elseif (($snapshot['status'] ?? '') === 'ok') {
        $shop['sync_status'] = 'connected';
      }
      if (trim($shop['cookie'] ?? '') === '') {
        $shop['sync_status'] = 'expired';
        if (empty($snapshot['error_message'])) {
          $snapshot['error_message'] = 'Cookie toko kosong. Masukkan cookie Shopee terbaru.';
        }
      }
      $sessionStatus = $shop['sync_status'] ?? 'unknown';
      $sessionExpired = $sessionStatus === 'expired' || (($snapshot['status'] ?? '') === 'expired');
      $result[] = [
        'shop_id' => (int)$shop['id'],
        'shop_name' => $shop['name'] ?? '',
        'shop_status' => $shop['sync_status'] ?? 'unknown',
        'session_status' => $sessionStatus,
        'session_expired' => $sessionExpired,
        'status' => $snapshot['status'] ?? 'pending',
        'error_message' => $snapshot['error_message'] ?? null,
        'synced_at' => $snapshot['synced_at'] ?? null,
        'stale' => !empty($snapshot['synced_at']) && $age >= 300,
        'metrics' => is_array($payload) ? $payload : []
      ];
    }
    return $result;
  }
}
