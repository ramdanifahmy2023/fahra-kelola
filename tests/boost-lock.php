<?php
chdir(__DIR__.'/../public');require '../app/init.php';require '../app/models/BoostStore.php';
$store=new BoostStore();$shop=2147483000;
if(in_array('--child',$argv,true)){
  try{$store->lock($shop);$store->unlock($shop);echo 'acquired';}catch(BoostError $e){echo $e->getCode()===409?'busy':'error';}exit;
}
if(in_array('--crash',$argv,true)){$store->lock($shop);exit;}
function contender(){
  $proc=proc_open([PHP_BINARY,__FILE__,'--child'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
  fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
  if(proc_close($proc)!==0)throw new RuntimeException('Child failed: '.$err);return $out;
}
$store->lock($shop);
try{if(contender()!=='busy')throw new RuntimeException('Another connection bypassed shop lock');}
finally{$store->unlock($shop);}
if(contender()!=='acquired')throw new RuntimeException('Lock did not release');
exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg(__FILE__).' --crash',$output,$code);
if($code!==0 || contender()!=='acquired')throw new RuntimeException('Exited process kept its lock');
echo "PASS: separate PHP processes share the database shop lock and release ownership\n";
