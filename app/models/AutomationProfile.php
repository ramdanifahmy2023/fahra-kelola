<?php
require_once __DIR__ . '/../helpers/AutomationPolicy.php';

class AutomationProfile extends BaseModel {
  public function ensureSchema(): void {
    $sql = file_get_contents(__DIR__ . '/../../database/migrations/20260929_automation_profiles.sql');
    foreach (explode(';', $sql) as $statement) {
      if (trim($statement) !== '') { $this->db->query($statement); $this->db->exe(); }
    }
  }

  public function forShop(int $shopId): array {
    $this->db->query('SELECT version, config_json, updated_at FROM automation_profiles WHERE shop_id = :shop');
    $this->db->bind('shop', $shopId);
    $row = $this->db->single();
    return ['version'=>(int)($row['version'] ?? 0), 'updated_at'=>$row['updated_at'] ?? null,
      'config'=>$row ? json_decode($row['config_json'], true, 512, JSON_THROW_ON_ERROR) : AutomationPolicy::defaults()];
  }

  public function save(int $shopId, int $version, array $config, int $actorId): array {
    $config = AutomationPolicy::validate($config);
    $json = json_encode($config, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $this->db->begin();
    try {
      if ($version === 0) {
        $this->db->query('INSERT INTO automation_profiles (shop_id,version,config_json,updated_by,updated_at) VALUES (:shop,1,:config,:actor,UTC_TIMESTAMP())');
      } else {
        $this->db->query('UPDATE automation_profiles SET config_json=:config, version=version+1, updated_by=:actor, updated_at=UTC_TIMESTAMP() WHERE shop_id=:shop AND version=:version');
        $this->db->bind('version', $version);
      }
      $this->db->bind('shop', $shopId); $this->db->bind('config', $json); $this->db->bind('actor', $actorId); $this->db->exe();
      if ($this->db->row() !== 1) throw new RuntimeException('Konfigurasi berubah di tab lain. Muat ulang halaman sebelum menyimpan.', 409);
      $this->db->query('INSERT INTO automation_profile_events (shop_id,version,actor_id,created_at) VALUES (:shop,:version,:actor,UTC_TIMESTAMP())');
      $this->db->bind('shop', $shopId); $this->db->bind('version', $version+1); $this->db->bind('actor', $actorId); $this->db->exe();
      $this->db->commit();
    } catch (Throwable $error) {
      $this->db->rollback();
      if ($error instanceof PDOException && ($error->errorInfo[1] ?? 0) === 1062) throw new RuntimeException('Konfigurasi sudah dibuat di tab lain. Muat ulang halaman.', 409);
      throw $error;
    }
    return $this->forShop($shopId);
  }
}
