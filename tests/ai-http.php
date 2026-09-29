<?php
require_once __DIR__ . '/../app/helpers/NineRouterClient.php';
$socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$error);
if(!$socket)throw new RuntimeException('Cannot allocate test port');
$address=stream_socket_get_name($socket,false);fclose($socket);
$log=tempnam(sys_get_temp_dir(),'ai-http-');
$process=proc_open([PHP_BINARY,'-S',$address,__DIR__.'/fixtures/nine-router.php'],[0=>['pipe','r'],1=>['file',$log,'a'],2=>['file',$log,'a']],$pipes);
try {
  $ready=false;
  for($i=0;$i<50;$i++) { $connection=@stream_socket_client('tcp://'.$address,$errno,$error,.1);if($connection){fclose($connection);$ready=true;break;}usleep(100000); }
  if(!$ready)throw new RuntimeException('Fixture HTTP server did not start');
  $origin='http://'.$address;$client=new NineRouterClient([$origin]);
  $list=$client->request($origin,'fixture-only-key','models');
  if(($list['models'][0]['id'] ?? '')!=='fixture-combo')throw new RuntimeException('Real HTTP catalog contract failed');
  $test=$client->request($origin,'fixture-only-key','test','fixture-combo');
  if(($test['usage']['total_tokens'] ?? 0)!==5)throw new RuntimeException('Real HTTP generation contract failed');
  foreach(['/redirect/v1','/large/v1'] as $path){$failed=false;try{$client->request($origin.$path,'fixture-only-key','models');}catch(AiConnectionError $e){$failed=$e->getCode()===502;}if(!$failed)throw new RuntimeException('HTTP boundary failed');}
  $badKey=false;try{$client->request($origin,'incorrect-fixture-key','models');}catch(AiConnectionError $e){$badKey=$e->getCode()===502;}
  if(!$badKey)throw new RuntimeException('Auth handling failed');
  echo "PASS: real cURL against local fixture, bearer auth, catalog, chat JSON, redirect refusal and size cap\n";
} finally {proc_terminate($process);foreach($pipes as $pipe)fclose($pipe);proc_close($process);unlink($log);}
