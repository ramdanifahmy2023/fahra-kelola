<?php
require_once __DIR__ . '/../helpers/NineRouterClient.php';

class AiConnection {
  private Database $db;
  private ?AiConnectionSecret $secret;
  public function __construct(?Database $db = null, ?AiConnectionSecret $secret = null) { $this->db = $db ?? new Database(); $this->secret = $secret; }
  public function ensureSchema(): void { AiConnectionSettings::schema($this->db); }
  private function vault(): AiConnectionSecret { return $this->secret ??= new AiConnectionSecret(); }
  private function query(string $sql, array $params = []): void {
    $this->db->query($sql); foreach ($params as $name=>$value) $this->db->bind($name,$value);
  }
  private function row(int $id, bool $lock = false): array {
    $this->query('SELECT * FROM ai_connections WHERE id=:id AND deleted_at IS NULL' . ($lock ? ' FOR UPDATE' : ''), ['id'=>$id]);
    $row=$this->db->single();
    if (!$row) throw new AiConnectionError('Koneksi tidak ditemukan atau sudah dihapus.',404);
    return $row;
  }
  private function sameVersion(array $row, int $version): void {
    if ((int)$row['version'] !== $version) throw new AiConnectionError('Koneksi berubah di tab lain. Muat ulang daftar, lalu pilih Ubah untuk membuka versi terbaru.',409);
  }
  private function shops(int $id): array {
    $this->query('SELECT p.shop_id, s.name, p.config_json FROM automation_profiles p LEFT JOIN shops s ON s.id=p.shop_id WHERE p.connection_id=:id ORDER BY p.shop_id',['id'=>$id]);
    return array_map(function($row) {
      $config=json_decode($row['config_json'],true) ?: [];
      return ['id'=>(int)$row['shop_id'],'name'=>$row['name'] ?? 'Toko ' . $row['shop_id'],'inherits_model'=>trim($config['model'] ?? '') === ''];
    },$this->db->getAll());
  }
  private function metadata(array $row): array {
    $out = array_intersect_key($row,array_flip(['name','base_url','default_model','created_at','updated_at']));
    $out += ['id'=>(int)$row['id'],'version'=>(int)$row['version'],'provider'=>'9router','has_api_key'=>!empty($row['key_ciphertext']),'shops'=>$this->shops((int)$row['id'])];
    foreach (['catalog_result','test_result'] as $key) $out[$key]=$row[$key] ? json_decode($row[$key],true) : null;
    return $out;
  }
  public function listing(): array {
    $this->query('SELECT * FROM ai_connections WHERE deleted_at IS NULL ORDER BY name,id');
    $rows=$this->db->getAll();
    return array_map(fn($row)=>$this->metadata($row),$rows);
  }
  public function detail(int $id): array { return $this->metadata($this->row($id)); }
  private function fields(array $input): array {
    $name=$input['name'] ?? null;
    if (!is_string($name) || trim($name)==='' || mb_strlen($name)>80 || preg_match('/[\x00-\x1f\x7f]/',$name)) throw new AiConnectionError('Isi nama koneksi, maksimal 80 karakter.',422,['name'=>'Nama koneksi wajib diisi, maksimal 80 karakter.']);
    return ['name'=>trim($name),'base_url'=>NineRouterClient::normalize($input['base_url'] ?? null),'default_model'=>NineRouterClient::model($input['default_model'] ?? null)];
  }
  private function key($value, bool $required): string {
    if (!is_string($value) || strlen($value)>4096 || preg_match('/[\x00-\x20\x7f]/',$value) || ($required && $value==='')) throw new AiConnectionError('Isi API key yang valid tanpa spasi, maksimal 4.096 karakter.',422,['api_key'=>'API key wajib saat membuat koneksi atau mengubah base URL.']);
    return $value;
  }
  private function event(int $id, int $actor, string $action, int $version, array $fields): void {
    $this->query('INSERT INTO ai_connection_events (connection_id,actor_id,action,version,changed_fields,created_at) VALUES (:id,:actor,:action,:version,:fields,UTC_TIMESTAMP())', ['id'=>$id,'actor'=>$actor,'action'=>$action,'version'=>$version,'fields'=>implode(',',$fields)]); $this->db->exe();
  }
  public function create(array $input, int $actor): array {
    $fields=$this->fields($input); $key=$this->key($input['api_key'] ?? '',true); $vault=$this->vault();
    $this->db->begin();
    try {
      $this->query('INSERT INTO ai_connections (name,base_url,default_model,created_by,updated_by,created_at,updated_at) VALUES (:name,:base_url,:default_model,:actor,:updated,UTC_TIMESTAMP(),UTC_TIMESTAMP())',$fields+['actor'=>$actor,'updated'=>$actor]); $this->db->exe();
      $id=(int)$this->db->lastId();
      $this->query('UPDATE ai_connections SET key_ciphertext=:key_ciphertext,key_nonce=:key_nonce,key_version=:key_version WHERE id=:id',$vault->encrypt($key,$id)+['id'=>$id]); $this->db->exe();
      $this->event($id,$actor,'create',1,['name','base_url','default_model','api_key']);
      $result=$this->detail($id); $this->db->commit(); return $result;
    } catch (Throwable $error) { $this->db->rollback(); throw $error; }
  }
  public function update(int $id, int $version, array $input, int $actor): array {
    $fields=$this->fields($input); $key=$this->key($input['api_key'] ?? '',false);
    $this->db->begin();
    try {
      $row=$this->row($id,true); $this->sameVersion($row,$version);
      if ($fields['base_url'] !== $row['base_url'] && $key==='') throw new AiConnectionError('Masukkan API key lagi ketika base URL berubah.',422,['api_key'=>'Base URL berubah. Masukkan API key untuk tujuan baru.']);
      $changed=[]; foreach ($fields as $field=>$value) if ($row[$field] !== $value) $changed[]=$field;
      if ($key!=='') $changed[]='api_key';
      // Rewrap under the active key on every edit, retaining old key IDs for reads during rotation.
      $cipher=$this->vault()->encrypt($key!=='' ? $key : $this->vault()->decrypt($row),$id);
      $this->query('UPDATE ai_connections SET name=:name,base_url=:base_url,default_model=:default_model,key_ciphertext=:key_ciphertext,key_nonce=:key_nonce,key_version=:key_version,version=version+1,updated_by=:actor,updated_at=UTC_TIMESTAMP() WHERE id=:id',$fields+$cipher+['actor'=>$actor,'id'=>$id]); $this->db->exe();
      $this->event($id,$actor,'update',$version+1,$changed);
      $result=$this->detail($id); $this->db->commit(); return $result;
    } catch (Throwable $error) { $this->db->rollback(); throw $error; }
  }
  public function remove(int $id, int $version, int $actor): void {
    $this->db->begin();
    try {
      $row=$this->row($id,true); $this->sameVersion($row,$version);
      $shops=$this->shops($id);
      if ($shops) throw new AiConnectionError('Koneksi masih dipakai oleh ' . implode(', ',array_column($shops,'name')) . '. Lepas atau pindahkan koneksi toko dahulu.',409);
      $this->query('UPDATE ai_connections SET deleted_at=UTC_TIMESTAMP(),updated_at=UTC_TIMESTAMP(),updated_by=:actor,version=version+1,key_ciphertext=NULL,key_nonce=NULL,key_version=NULL WHERE id=:id',['actor'=>$actor,'id'=>$id]); $this->db->exe();
      $this->event($id,$actor,'delete',$version+1,['api_key']); $this->db->commit();
    } catch (Throwable $error) { $this->db->rollback(); throw $error; }
  }
  public function probe(int $id, int $version, string $kind, string $model, int $actor, ?NineRouterClient $client = null): array {
    if (!in_array($kind,['models','test'],true)) throw new InvalidArgumentException('Unknown probe');
    if ($kind==='test') $model=NineRouterClient::model($model);
    $token=bin2hex(random_bytes(24));
    $this->query('UPDATE ai_connections SET probe_token=:token,probe_until=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 75 SECOND) WHERE id=:id AND version=:version AND deleted_at IS NULL AND (probe_until IS NULL OR probe_until<UTC_TIMESTAMP()) AND (cooldown_until IS NULL OR cooldown_until<UTC_TIMESTAMP())',['id'=>$id,'version'=>$version,'token'=>$token]); $this->db->exe();
    if ($this->db->row()!==1) { $this->sameVersion($this->row($id),$version); throw new AiConnectionError('Permintaan koneksi sedang berjalan atau baru selesai. Tunggu beberapa detik.',429); }
    $failure=null; $result=[];
    try {
      $row=$this->row($id); $this->sameVersion($row,$version);
      $result=($client ?? new NineRouterClient())->request($row['base_url'],$this->vault()->decrypt($row),$kind,$model);
    } catch (AiConnectionError $error) { $failure=$error; }
    catch (Throwable $error) { $failure=new AiConnectionError('Pengujian provider gagal. Periksa konfigurasi server.',503); }
    $summary=['version'=>$version,'at'=>gmdate('Y-m-d\TH:i:s\Z'),'ok'=>$failure===null,'model'=>$kind==='test' ? $model : null,'message'=>$failure ? $failure->getMessage() : ($kind==='models' ? 'Daftar model berhasil dimuat.' : 'Model berhasil menghasilkan jawaban uji.')];
    $column=$kind==='models' ? 'catalog_result' : 'test_result';
    $this->query("UPDATE ai_connections SET {$column}=CASE WHEN version=:version AND deleted_at IS NULL THEN :result ELSE {$column} END,probe_token=NULL,probe_until=NULL,cooldown_until=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 5 SECOND) WHERE id=:id AND probe_token=:token",['version'=>$version,'result'=>json_encode($summary,JSON_THROW_ON_ERROR),'id'=>$id,'token'=>$token]); $this->db->exe();
    $this->sameVersion($this->row($id),$version);
    $this->event($id,$actor,$kind,$version,[]);
    if ($failure) throw $failure;
    return $result+['connection'=>$this->detail($id)];
  }
}
