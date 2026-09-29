<?php
require_once __DIR__.'/../models/BoostStore.php';
require_once __DIR__.'/BoostTransport.php';

class BoostExecutor {
  private $store;
  private $transport;
  private $canSend;
  public function __construct(?BoostStore $store=null,?BoostTransport $transport=null,?callable $canSend=null) {
    $this->store=$store ?: new BoostStore();$this->transport=$transport ?: new BoostTransport();
    $this->canSend=$canSend ?: [BoostTransport::class,'senderEnabled'];
  }
  public function preview(int $shop): array {
    $this->store->shop($shop);
    $p=$this->store->profile($shop);$products=$this->store->products($shop,$p['product_ids']);
    $cooldowns=$this->store->productCooldowns($shop,$p['product_ids']);$summary=$this->store->summary($shop);
    foreach($products as &$product){$product['reason']=BoostPolicy::localReason($product,$cooldowns[$product['id']]??[]);$product['next_boost_at']=$cooldowns[$product['id']]['next_boost_at']??null;}
    return ['profile'=>$p,'products'=>$products,'summary'=>$summary,'next_check_at'=>gmdate('Y-m-d H:i:s',BoostPolicy::nextCheck($products,$cooldowns,$summary,time())),'remote_verified'=>false];
  }
  public function execute(int $shopId,string $mode='auto',array $ids=[],int $actor=0,string $requestKey=''): array {
    if(!in_array($mode,['auto','manual'],true))throw new BoostError('Mode tidak valid.',422);
    if($mode==='manual'){
      $ids=BoostPolicy::ids($ids);
      if(!preg_match('/^[a-zA-Z0-9_-]{16,80}$/',$requestKey))throw new BoostError('Kunci permintaan tidak valid. Muat ulang halaman.',422);
    }
    $s=$this->store;$token=$s->lock($shopId);
    try {
      $s->recover($shopId);$profile=$s->profile($shopId);$version=$profile['version'];
      if($mode==='auto'){
        if(!$profile['enabled'] || ($profile['next_check_at'] && strtotime($profile['next_check_at'].' UTC')>time()))return ['status'=>'waiting'];
        $ids=BoostPolicy::ids($profile['product_ids']);$requestKey='auto_'.bin2hex(random_bytes(16));
        $cooldowns=$s->productCooldowns($shopId,$ids);$positions=array_flip($ids);
        usort($ids,static fn($a,$b)=>strcmp($cooldowns[$a]['last_boost_at']??'', $cooldowns[$b]['last_boost_at']??'') ?: $positions[$a]<=>$positions[$b]);
      }
      $hash=hash('sha256',json_encode([$mode,$ids]));
      if($previous=$s->previous($shopId,$requestKey,$hash))return $previous+['replayed'=>true];
      if(!(($this->canSend)()))throw new BoostError('Pengiriman Boost dinonaktifkan oleh pengelola server.',503);
      $shop=$s->shop($shopId);
      if(empty($shop['cookie']))throw new BoostError('Koneksi toko belum tersedia. Perbarui koneksi toko.',409);
      $reservation=$s->reserve($shopId,$ids,$mode,$version,$actor,$requestKey,$token,$hash);
      if(!$reservation){
        $preview=$this->preview($shopId);
        $s->checked($shopId,$version,'Menunggu produk atau kuota lokal tersedia.',strtotime($preview['next_check_at'].' UTC'));
        return ['status'=>'waiting','message'=>'Menunggu produk atau kuota lokal tersedia.'];
      }
      $run=$reservation['run_id'];$stopped=null;$remoteWait=false;$retryDelay=max(600,min(3600,60*(2**min(6,(int)$profile['failure_count']))));
      try { $this->transport->identity($shop); }
      catch(Throwable $e){$stopped=$e instanceof BoostError?$e->getMessage():'Pemeriksaan koneksi gagal. Tidak ada produk dikirim.';if($e instanceof BoostError)$retryDelay=max($retryDelay,$e->retryAfter);}
      foreach($reservation['products'] as $product){
        $id=$product['id'];
        if($stopped){$s->record($run,$id,'not_sent',$stopped);continue;}
        try {
          $info=$this->transport->info($shop,[$id]);
          if(empty($info[$id]['eligible'])){$remoteWait=true;$s->record($run,$id,'not_sent',$info[$id]['reason']??'Status remote belum tersedia.');continue;}
          if(!(($this->canSend)()))throw new BoostError('Pengiriman dihentikan oleh pengelola server.',503);
          $s->sending($shopId,$run,$id,$token,$mode,$version);
        }catch(Throwable $e){
          $stopped=$e instanceof BoostError?$e->getMessage():'Pemeriksaan sebelum pengiriman gagal.';
          if($e instanceof BoostError)$retryDelay=max($retryDelay,$e->retryAfter);
          $s->record($run,$id,'not_sent',$stopped);continue;
        }
        try {$response=$this->transport->send($shop,$id);$outcome=BoostPolicy::outcome($response);}
        catch(Throwable $e){$response=[];$outcome=['status'=>'unknown','message'=>'Koneksi terputus saat pengiriman. Periksa hasil di Seller Centre.'];}
        $s->record($run,$id,$outcome['status'],$outcome['message'],$response);
        if($outcome['status']!=='success')$stopped=$outcome['message'];
        usleep(250000);
      }
      $result=$s->finishRun($run);$preview=$this->preview($shopId);
      $next=strtotime($preview['next_check_at'].' UTC');
      if($stopped || $remoteWait)$next=max($next,time()+$retryDelay);
      $message=$stopped ?: ($remoteWait?'Sebagian produk menunggu status tersedia di Shopee.':'Pemeriksaan selesai. Menunggu jadwal produk berikutnya.');
      $s->checked($shopId,$version,$message,$next,(bool)$stopped);
      return $result;
    }catch(Throwable $e){
      if(isset($profile) && $mode==='auto')$s->checked($shopId,$profile['version'],$e instanceof BoostError?$e->getMessage():'Proses terhenti. Hasil akan diperiksa pada tick berikutnya.',time()+min(3600,60*(2**min(6,(int)$profile['failure_count']))),true);
      throw $e;
    }finally{$s->unlock($shopId);}
  }
  public function inspect(int $shopId): array {
    $s=$this->store;$s->lock($shopId);
    try {
      $s->recover($shopId);$shop=$s->shop($shopId);$p=$s->profile($shopId);
      $ids=array_values(array_unique(array_merge($p['product_ids'],array_map('strval',array_column($s->unresolved($shopId),'product_id')))));
      if(!$ids)throw new BoostError('Simpan pilihan produk terlebih dahulu.',422);
      $this->transport->identity($shop);$info=[];
      foreach(array_chunk($ids,5) as $chunk)$info+=$this->transport->info($shop,$chunk);
      return ['observed_at'=>gmdate('Y-m-d H:i:s'),'products'=>$info,'message'=>'Status saat ini sudah diperiksa. Status tersedia tidak membuktikan hasil pengiriman sebelumnya.'];
    }finally{$s->unlock($shopId);}
  }
}
