#!/usr/bin/env php
<?php
chdir(__DIR__.'/../public');require '../app/init.php';require '../app/helpers/BoostExecutor.php';
$options=getopt('',['migrate','dry-run','once','limit:','shop:','help']);
if(isset($options['help'])){echo "Boost worker: --migrate | --dry-run [--shop=ID] | --once [--limit=10]\nDefault is dry-run (local reads only). --once honors BOOST_SEND_ENABLED.\n";exit;}
$store=new BoostStore();
try {
  if(isset($options['migrate'])){$store->migrate();echo "Boost schema ready; profiles remain disabled by default.\n";exit;}
  if(!$store->ready())throw new BoostError('Apply Boost migration first.',503);
  $dry=isset($options['dry-run']) || !isset($options['once']);$executor=new BoostExecutor($store);
  $shops=isset($options['shop'])?[['shop_id'=>(int)$options['shop']]]:$store->due((int)($options['limit']??10));
  if(!$dry){$store->heartbeat(BoostTransport::senderEnabled());if(!BoostTransport::senderEnabled()){echo "Boost sender disabled. No requests sent.\n";exit;}}
  $failed=false;
  foreach($shops as $row){
    $shop=(int)$row['shop_id'];
    try {
      if($dry){$p=$executor->preview($shop);echo json_encode(['shop_id'=>$shop,'dry_run'=>true,'enabled'=>$p['profile']['enabled'],'selected_count'=>count($p['products']),'next_check_at'=>$p['next_check_at'],'remote_verified'=>false]).PHP_EOL;}
      else {$result=$executor->execute($shop);echo json_encode(['shop_id'=>$shop,'status'=>$result['status']??'waiting','run_id'=>$result['run_id']??null]).PHP_EOL;$store->heartbeat(true);}
    }catch(Throwable $e){$failed=true;fwrite(STDERR,json_encode(['shop_id'=>$shop,'error'=>$e instanceof BoostError?$e->getMessage():'Boost failed; inspect local history.']).PHP_EOL);}
  }
  if($failed)exit(1);
}catch(Throwable $e){fwrite(STDERR,($e instanceof BoostError?$e->getMessage():'Boost worker failed.').PHP_EOL);exit(1);}
