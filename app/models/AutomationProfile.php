<?php
require_once __DIR__ . '/../helpers/AutomationPolicy.php';
require_once __DIR__ . '/../helpers/AiConnectionSupport.php';

class AutomationProfile extends BaseModel {
  public function ensureSchema(): void {
    AiConnectionSettings::schema($this->db);
  }

  public function forShop(int $shopId): array {
    $this->db->query('SELECT version, config_json, connection_id, updated_at FROM automation_profiles WHERE shop_id = :shop');
    $this->db->bind('shop', $shopId);
    $row = $this->db->single();
    return ['version'=>(int)($row['version'] ?? 0), 'connection_id'=>isset($row['connection_id']) ? (int)$row['connection_id'] : null, 'updated_at'=>$row['updated_at'] ?? null,
      'config'=>$row ? json_decode($row['config_json'], true, 512, JSON_THROW_ON_ERROR) : AutomationPolicy::defaults()];
  }

  public function save(int $shopId, int $version, array $config, int $actorId, $connectionId = false): array {
    $config = AutomationPolicy::validate($config);
    $json = json_encode($config, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    if ($connectionId === false) $connectionId = $this->forShop($shopId)['connection_id'];
    if ($connectionId !== null && (!is_int($connectionId) || $connectionId < 1)) throw new InvalidArgumentException('Koneksi toko tidak valid.');
    $this->db->begin();
    try {
      if ($connectionId !== null) {
        // The same row lock is used by deletion, so assignment cannot race secret removal.
        $this->db->query('SELECT id FROM ai_connections WHERE id=:connection AND deleted_at IS NULL FOR UPDATE');
        $this->db->bind('connection',$connectionId);
        if (!$this->db->single()) throw new InvalidArgumentException('Koneksi sudah dihapus. Pilih koneksi lain.');
      }
      if ($version === 0) {
        $this->db->query('INSERT INTO automation_profiles (shop_id,version,config_json,connection_id,updated_by,updated_at) VALUES (:shop,1,:config,:connection,:actor,UTC_TIMESTAMP())');
      } else {
        $this->db->query('UPDATE automation_profiles SET config_json=:config, connection_id=:connection, version=version+1, updated_by=:actor, updated_at=UTC_TIMESTAMP() WHERE shop_id=:shop AND version=:version');
        $this->db->bind('version', $version);
      }
      $this->db->bind('connection',$connectionId);
      $this->db->bind('shop', $shopId); $this->db->bind('config', $json); $this->db->bind('actor', $actorId); $this->db->exe();
      if ($this->db->row() !== 1) throw new RuntimeException('Konfigurasi berubah di tab lain. Muat ulang halaman sebelum menyimpan.', 409);
      $this->db->query('INSERT INTO automation_profile_events (shop_id,version,actor_id,created_at) VALUES (:shop,:version,:actor,UTC_TIMESTAMP())');
      $this->db->bind('shop', $shopId); $this->db->bind('version', $version+1); $this->db->bind('actor', $actorId); $this->db->exe();
      $result=$this->forShop($shopId);
      $this->db->commit();
    } catch (Throwable $error) {
      $this->db->rollback();
      if ($error instanceof PDOException && ($error->errorInfo[1] ?? 0) === 1062) throw new RuntimeException('Konfigurasi sudah dibuat di tab lain. Muat ulang halaman.', 409);
      throw $error;
    }
    return $result;
  }
}
