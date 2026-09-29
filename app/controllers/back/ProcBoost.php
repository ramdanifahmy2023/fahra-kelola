<?php
require_once __DIR__.'/../../helpers/BoostExecutor.php';

class ProcBoost extends Controller {
  private function json(array $data,int $status=200): void {
    http_response_code($status);header('Content-Type: application/json');header('Cache-Control: no-store');
    echo json_encode($data,JSON_UNESCAPED_UNICODE);exit;
  }
  private function store(): BoostStore {
    $s=new BoostStore();if(!$s->ready())throw new BoostError('Fitur sedang disiapkan. Migrasi Boost belum diterapkan oleh pengelola server.',503);return $s;
  }
  private function respond(callable $fn): void {
    try {$this->json(['status'=>'success']+$fn());}
    catch(BoostError $e){$this->json(['status'=>'error','message'=>$e->getMessage()],$e->getCode()?:422);}
    catch(Throwable $e){$this->json(['status'=>'error','message'=>'Data Boost belum dapat diproses. Muat ulang status sebelum mencoba lagi.'],500);}
  }
  private function input(): array {
    if(($_SERVER['REQUEST_METHOD']??'')!=='POST')throw new BoostError('Gunakan POST.',405);
    if(!authVerifyCsrf($_SERVER['HTTP_X_CSRF_TOKEN']??''))throw new BoostError('Sesi formulir berakhir. Muat ulang halaman.',403);
    $body=file_get_contents('php://input',false,null,0,8193);
    if(strlen($body)>8192)throw new BoostError('Permintaan terlalu besar.',413);
    $data=json_decode($body,true);
    if(!is_array($data) || !is_int($data['shop_id']??null) || $data['shop_id']<1)throw new BoostError('Toko tidak valid.',422);
    return $data;
  }
  private function version(array $d): int {
    if(!is_int($d['version']??null) || $d['version']<0)throw new BoostError('Versi tidak valid.',422);return $d['version'];
  }
  public function status(): void {
    $this->respond(function(){
      $id=filter_var($_GET['shop_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
      if(!$id)throw new BoostError('Toko tidak valid.',422);
      $s=$this->store();$shop=$s->shop($id);$preview=(new BoostExecutor($s))->preview($id);
      return $preview+['shop'=>['id'=>$id,'name'=>$shop['name'],'session_status'=>$shop['sync_status']], 'history'=>$s->history($id,8),'unresolved'=>$s->unresolved($id),'worker'=>$s->health(),'sender_enabled'=>BoostTransport::senderEnabled()];
    });
  }
  public function catalog(): void {
    $this->respond(function(){
      $id=(int)($_GET['shop_id']??0);$s=$this->store();$s->shop($id);
      return $s->catalog($id,mb_substr(trim((string)($_GET['search']??'')),0,100),max(1,(int)($_GET['page']??1)));
    });
  }
  public function save(): void {
    $this->respond(function(){$d=$this->input();return ['profile'=>$this->store()->save($d['shop_id'],$this->version($d),BoostPolicy::ids($d['product_ids']??null,true),(int)authUser()['id'])];});
  }
  public function preview(): void {
    $this->respond(function(){
      $d=$this->input();$ids=BoostPolicy::ids($d['product_ids']??null);$s=$this->store();$s->shop($d['shop_id']);
      $products=$s->products($d['shop_id'],$ids);$cooldowns=$s->productCooldowns($d['shop_id'],$ids);
      foreach($products as &$product)$product['reason']=BoostPolicy::localReason($product,$cooldowns[$product['id']]??[]);
      return ['products'=>$products,'summary'=>$s->summary($d['shop_id']),'remote_verified'=>false];
    });
  }
  public function toggle(): void {
    $this->respond(function(){
      $d=$this->input();if(!is_bool($d['enabled']??null))throw new BoostError('Status pengulangan tidak valid.',422);
      $s=$this->store();$s->shop($d['shop_id']);
      if($d['enabled'] && !BoostTransport::senderEnabled())throw new BoostError('Pengiriman belum diaktifkan oleh pengelola server. Pilihan dapat disimpan terlebih dahulu.',503);
      return ['profile'=>$s->toggle($d['shop_id'],$this->version($d),$d['enabled'],(int)authUser()['id'])];
    });
  }
  public function run(): void {
    $this->respond(function(){
      $d=$this->input();$s=$this->store();$ids=BoostPolicy::ids($d['product_ids']??null);
      $key=$d['request_key']??null;if(!is_string($key))throw new BoostError('Kunci permintaan tidak valid.',422);
      $actor=(int)authUser()['id'];session_write_close();
      return ['result'=>(new BoostExecutor($s))->execute($d['shop_id'],'manual',$ids,$actor,$key)];
    });
  }
  public function inspect(): void {
    $this->respond(function(){$d=$this->input();session_write_close();return ['inspection'=>(new BoostExecutor($this->store()))->inspect($d['shop_id'])];});
  }
  public function resolve(): void {
    $this->respond(function(){
      $d=$this->input();if(($d['confirmed']??false)!==true || !is_int($d['run_id']??null) || $d['run_id']<1)throw new BoostError('Konfirmasikan hasil setelah memeriksa Seller Centre.',422);
      $id=BoostPolicy::ids([$d['product_id']??null])[0];
      if(!is_string($d['outcome']??null))throw new BoostError('Hasil tidak valid.',422);
      $this->store()->resolve($d['shop_id'],$d['run_id'],$id,$d['outcome'],(int)authUser()['id']);return [];
    });
  }
}
