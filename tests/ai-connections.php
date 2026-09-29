<?php
chdir(__DIR__ . '/../public');
require '../app/init.php';
require '../app/models/AiConnection.php';
require '../app/models/AutomationProfile.php';
$checks=0;
function aiCheck($value,$message) {global $checks;$checks++;if(!$value)throw new RuntimeException($message);}
function aiReject(callable $fn,int $code=422): bool {try{$fn();}catch(AiConnectionError $e){return $e->getCode()===$code;}return false;}
class AiProfileFixture extends AutomationProfile {public function __construct($db){$this->db=$db;}}
$ring=['active'=>'first','keys'=>['first'=>base64_encode(random_bytes(32))]];
$vault=new AiConnectionSecret($ring);
$encrypted=$vault->encrypt('fixture-only-key',12);
aiCheck($vault->decrypt($encrypted+['id'=>12])==='fixture-only-key','Secret roundtrip');
aiCheck($encrypted!==$vault->encrypt('fixture-only-key',12),'Random nonce each write');
aiCheck(aiReject(fn()=>$vault->decrypt($encrypted+['id'=>13]),503),'AAD rejects another connection');
$bad=$encrypted;$bad['key_ciphertext']=base64_encode('tampered');
aiCheck(aiReject(fn()=>$vault->decrypt($bad+['id'=>12]),503),'Tampering rejected');
$rotated=$ring;$rotated['active']='second';$rotated['keys']['second']=base64_encode(random_bytes(32));
$newVault=new AiConnectionSecret($rotated);
aiCheck($newVault->decrypt($encrypted+['id'=>12])==='fixture-only-key','Rotation retains old decryption key');
aiCheck($newVault->encrypt('new',12)['key_version']==='second','New writes use active key');
aiCheck(aiReject(fn()=>new AiConnectionSecret(['active'=>'bad','keys'=>['bad'=>'invalid']]),503),'Invalid master key fails closed');
$keyDir=sys_get_temp_dir().'/shopdash-ai-key-'.bin2hex(random_bytes(8));mkdir($keyDir,0700);
try {
  $env=getenv();$env['AI_CONNECTION_KEY_FILE']=$keyDir.'/keys.json';
  $command=function($arg)use($env){$p=proc_open([PHP_BINARY,__DIR__.'/../bin/ai-connection-key.php',$arg],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,null,$env);foreach($pipes as $i=>$pipe){if($i>0)stream_get_contents($pipe);fclose($pipe);}return proc_close($p);};
  aiCheck($command('--init')===0,'CLI initializes keyring');$first=json_decode(file_get_contents($env['AI_CONNECTION_KEY_FILE']),true);
  aiCheck((fileperms($env['AI_CONNECTION_KEY_FILE'])&0777)===0600,'Keyring permissions are private');
  aiCheck($command('--init')!==0 && json_decode(file_get_contents($env['AI_CONNECTION_KEY_FILE']),true)===$first,'CLI refuses overwrite');
  aiCheck($command('--rotate')===0,'CLI rotates keyring');$next=json_decode(file_get_contents($env['AI_CONNECTION_KEY_FILE']),true);
  aiCheck($first['active']!==$next['active'] && isset($next['keys'][$first['active']]),'CLI preserves old key on rotation');
} finally {foreach(glob($keyDir.'/*') as $file)unlink($file);rmdir($keyDir);}
aiCheck(NineRouterClient::normalize('https://router.example.com/')==='https://router.example.com/v1','Origin normalization');
aiCheck(NineRouterClient::normalize('https://router.example.com/proxy/v1/')==='https://router.example.com/proxy/v1','Proxy prefix retained');
foreach(['ftp://example.com','https://user:pass@example.com','https://example.com/v1?secret=x','https://example.com/#x','https://example.com/v1/chat/completions','https://example.com/v1/v1','https://example.com/../v1','https://example.com/%2e%2e/v1','https://example.com\\@evil.com/v1'] as $url) aiCheck(aiReject(fn()=>NineRouterClient::normalize($url)),'Reject ambiguous URL');
$net=new NineRouterClient([],fn($host)=>['8.8.8.8']);
aiCheck($net->target('https://router.example.com')['ip']==='8.8.8.8','DNS resolved and pinned');
foreach(['127.0.0.1','10.0.0.1','169.254.169.254','100.100.100.200','192.168.1.1','::1','::ffff:127.0.0.1','fe80::1','fc00::1','2002:7f00:1::'] as $ip) aiCheck(!NineRouterClient::publicIp($ip),'Nonpublic address rejected');
aiCheck(NineRouterClient::publicIp('2606:4700:4700::1111'),'Public IPv6 accepted');
$mixed=new NineRouterClient([],fn($host)=>['8.8.8.8','127.0.0.1']);
aiCheck(aiReject(fn()=>$mixed->target('https://router.example.com')),'Mixed DNS answers rejected');
aiCheck(aiReject(fn()=>$net->target('http://router.example.com')),'Public HTTP rejected');
$local=new NineRouterClient(['http://127.0.0.1:20128']);
aiCheck($local->target('http://127.0.0.1:20128/v1')['ip']==='127.0.0.1','Exact local allowlist');
aiCheck(aiReject(fn()=>$local->target('http://127.0.0.1:9999/v1')),'Allowlist port matters');
$metadata=new NineRouterClient(['http://169.254.169.254']);
aiCheck(aiReject(fn()=>$metadata->target('http://169.254.169.254/v1')),'Metadata blocked even with allowlist');
aiCheck(aiReject(fn()=>NineRouterClient::model("x\r\nsecret")),'Model control characters rejected');
$client=new NineRouterClient([],fn($host)=>['8.8.8.8'],function($url,$key,$body,$target){
  aiCheck($key==='fixture-only-key' && $target['ip']==='8.8.8.8','Adapter gets key server-side and pinned destination');
  if($body){aiCheck($body['model']==='review-combo' && $body['stream']===false && !isset($body['shop_id']),'Combo passed exactly with synthetic input');return ['status'=>200,'body'=>json_encode(['choices'=>[['message'=>['role'=>'assistant','content'=>'OK']]],'usage'=>['total_tokens'=>4]])];}
  return ['status'=>200,'body'=>json_encode(['object'=>'list','data'=>[['id'=>'review-combo','owned_by'=>'combo'],['id'=>'provider/model','owned_by'=>'provider'],['id'=>'review-combo','owned_by'=>'combo']]])];
});
aiCheck(count($client->request('https://router.example.com','fixture-only-key','models')['models'])===2,'Catalog deduplicates models');
aiCheck($client->request('https://router.example.com','fixture-only-key','test','review-combo')['usage']['total_tokens']===4,'Real token metadata retained');
foreach([['status'=>302,'body'=>'secret'],['status'=>401,'body'=>'secret'],['status'=>429,'body'=>'secret'],['status'=>500,'body'=>'secret'],['status'=>200,'body'=>'not json'],['status'=>200,'body'=>'{}'],['status'=>200,'body'=>str_repeat('x',1048577)]] as $response){
  $fixture=new NineRouterClient([],fn($host)=>['8.8.8.8'],fn()=>$response);
  aiCheck(aiReject(fn()=>$fixture->request('https://router.example.com','key','models'),502),'Invalid upstream response rejected');
}
$emptyAssistant=new NineRouterClient([],fn($host)=>['8.8.8.8'],fn()=>['status'=>200,'body'=>'{"choices":[{"message":{"role":"assistant","content":""}}]}']);
aiCheck(aiReject(fn()=>$emptyAssistant->request('https://router.example.com','key','test','review-combo'),502),'Empty assistant is not a successful test');

$db=new Database();$tables=['automation_profiles','automation_profile_events','ai_connections','ai_connection_events','shops'];
try {
  foreach(['20260929_automation_profiles.sql','20260929_ai_connections.sql'] as $file)foreach(explode(';',str_replace('CREATE TABLE IF NOT EXISTS','CREATE TEMPORARY TABLE',file_get_contents('../database/migrations/'.$file))) as $sql)if(trim($sql)!==''){$db->query($sql);$db->exe();}
  $db->query('ALTER TABLE automation_profiles ADD COLUMN connection_id INT NULL');$db->exe();
  $db->query('CREATE TEMPORARY TABLE shops (id INT PRIMARY KEY,name VARCHAR(255))');$db->exe();
  $db->query("INSERT INTO shops VALUES (101,'Toko A'),(102,'Toko B')");$db->exe();
  $model=new AiConnection($db,$vault);$profile=new AiProfileFixture($db);
  $input=['name'=>'Koneksi uji','base_url'=>'https://router.example.com','default_model'=>'review-combo','api_key'=>'fixture-only-key'];
  $one=$model->create($input,1);$id=$one['id'];
  aiCheck($one['version']===1 && $one['base_url']==='https://router.example.com/v1','Create returns normalized public metadata');
  aiCheck(!str_contains(json_encode($one),'fixture-only-key') && !isset($one['key_ciphertext']) && $one['has_api_key'],'Read never leaks key material');
  aiCheck(count($model->listing())===1,'List persisted connection');
  $updated=$model->update($id,1,array_replace($input,['api_key'=>'','name'=>'Renamed']),2);
  aiCheck($updated['version']===2,'Update version and preserve blank key');
  aiCheck(aiReject(fn()=>$model->update($id,1,$input,2),409),'Stale edit rejected');
  aiCheck(aiReject(fn()=>$model->update($id,2,array_replace($input,['api_key'=>'','base_url'=>'https://another.example.com']),2)),'URL change requires new key');
  $config=AutomationPolicy::defaults();
  $saved=$profile->save(101,0,$config,1,$id);
  aiCheck($saved['connection_id']===$id,'Shop associates connection');
  $profile->save(102,0,array_replace($config,['model'=>'override-model']),1,$id);
  $shops=$model->detail($id)['shops'];
  aiCheck(count($shops)===2 && $shops[0]['inherits_model'] && !$shops[1]['inherits_model'],'Independent shop overrides');
  aiCheck(aiReject(fn()=>$model->remove($id,2,1),409),'In-use delete rejected');
  aiCheck($profile->save(101,1,$config,1)['connection_id']===$id,'Old client omission preserves selection');
  $probe=$model->probe($id,2,'models','',1,$client);
  aiCheck($probe['connection']['catalog_result']['ok'] && $probe['connection']['test_result']===null,'Catalog success is not generation success');
  aiCheck(aiReject(fn()=>$model->probe($id,2,'test','review-combo',1,$client),429),'Backend cooldown prevents repeated probes');
  $db->query('UPDATE ai_connections SET cooldown_until=NULL');$db->exe();
  $probe=$model->probe($id,2,'test','review-combo',1,$client);
  aiCheck($probe['connection']['test_result']['model']==='review-combo','Test status tied to actual model');
  $db->query('UPDATE ai_connections SET cooldown_until=NULL');$db->exe();
  $failure=new NineRouterClient([],fn($host)=>['8.8.8.8'],fn()=>['status'=>401,'body'=>'fixture-only-key']);
  aiCheck(aiReject(fn()=>$model->probe($id,2,'test','review-combo',1,$failure),502),'Provider authentication failure handled');
  aiCheck(!str_contains(json_encode($model->detail($id)),'fixture-only-key'),'Upstream failure details sanitized');
  $db->query('UPDATE ai_connections SET cooldown_until=NULL');$db->exe();
  $racer=new NineRouterClient([],fn($host)=>['8.8.8.8'],function()use($model,$id,$input){$model->update($id,2,$input,2);return ['status'=>200,'body'=>'{"data":[]}'];});
  aiCheck(aiReject(fn()=>$model->probe($id,2,'models','',1,$racer),409),'Stale probe completion rejected');
  aiCheck($model->detail($id)['catalog_result']['version']===2 && $model->detail($id)['version']===3,'Old result not promoted to new revision');
  $profile->save(101,2,$config,1,null);$profile->save(102,1,$config,1,null);
  $model->remove($id,3,1);
  aiCheck($model->listing()===[],'Delete removes connection from list');
  $db->query('SELECT key_ciphertext,key_nonce FROM ai_connections');$row=$db->single();
  aiCheck($row['key_ciphertext']===null && $row['key_nonce']===null,'Delete erases stored secret');
  $rejected=false;try{$profile->save(101,3,$config,1,$id);}catch(InvalidArgumentException $e){$rejected=true;}
  aiCheck($rejected,'Deleted connection cannot be reassigned');
  $db->query('SELECT * FROM ai_connection_events');
  aiCheck(!str_contains(json_encode($db->getAll()),'fixture-only-key'),'Audit events omit credential');
  echo "PASS: {$checks} connection, encryption, URL, adapter, CRUD, profile and probe checks\n";
} finally {foreach(array_reverse($tables) as $table){$db->query('DROP TEMPORARY TABLE IF EXISTS '.$table);$db->exe();}}
