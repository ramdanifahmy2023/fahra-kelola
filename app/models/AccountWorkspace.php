<?php
require_once __DIR__.'/../helpers/WorkspacePolicy.php';

class AccountWorkspace extends BaseModel {
  private $ready=false;
  public function ensureSchema(): void {
    if ($this->ready) return;
    $this->db->query(file_get_contents(__DIR__.'/../../database/migrations/20260930_workspace.sql'));$this->db->exe();
    $this->db->query('SHOW COLUMNS FROM account_workspace');
    if (!in_array('client_updated_at',array_column($this->db->getAll(),'Field'),true)) {
      try {$this->db->query('ALTER TABLE account_workspace ADD COLUMN client_updated_at BIGINT NOT NULL DEFAULT 0');$this->db->exe();}
      catch (PDOException $e) {if((int)($e->errorInfo[1] ?? 0)!==1060)throw $e;}
    }
    $this->ready=true;
  }
  public function allFor(int $user): array {
    $this->ensureSchema();$this->db->query('SELECT scope,payload FROM account_workspace WHERE user_id=:user');$this->db->bind('user',$user);
    $result=[];foreach ($this->db->getAll() as $row) $result[$row['scope']]=json_decode($row['payload'],true) ?: [];
    return $result;
  }
  private function write(int $user,string $scope,array $values,int $changedAt): void {
    $this->db->query('INSERT INTO account_workspace (user_id,scope,payload,updated_at,client_updated_at) VALUES (:user,:scope,:payload,UTC_TIMESTAMP(),:changed) ON DUPLICATE KEY UPDATE payload=IF(VALUES(client_updated_at)>=client_updated_at,VALUES(payload),payload),updated_at=IF(VALUES(client_updated_at)>=client_updated_at,VALUES(updated_at),updated_at),client_updated_at=GREATEST(client_updated_at,VALUES(client_updated_at))');
    foreach (['user'=>$user,'scope'=>$scope,'payload'=>json_encode($values),'changed'=>$changedAt] as $k=>$v) $this->db->bind($k,$v);$this->db->exe();
  }
  public function save(int $user,string $scope,array $values,?int $changedAt=null): void {
    $now=(int)floor(microtime(true)*1000);$changedAt=$changedAt===null?$now:max(0,min($now+5000,$changedAt));
    if (!array_key_exists($scope,WorkspacePolicy::FILTERS)) throw new InvalidArgumentException('Halaman tidak valid.');
    $this->ensureSchema();$clean=WorkspacePolicy::clean($scope,$values);
    foreach (['shop_id','topup_shop'] as $key) if (!empty($clean[$key])) {
      $this->db->query('SELECT id FROM shops WHERE id=:id');$this->db->bind('id',$clean[$key]);if (!$this->db->single()) unset($clean[$key]);
    }
    $this->db->begin();
    try {
      $this->write($user,$scope,$clean,$changedAt);
      if (isset($clean['shop_id']) && in_array($scope,WorkspacePolicy::SINGLE_SHOP,true)) $this->write($user,'global',['shop_id'=>$clean['shop_id']],$changedAt);
      $this->db->commit();
    } catch (Throwable $e) {$this->db->rollback();throw $e;}
  }
}
