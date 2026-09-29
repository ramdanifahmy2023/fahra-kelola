<?php
chdir(__DIR__.'/../public');require '../app/init.php';require '../app/helpers/BoostExecutor.php';
$checks=0;
function checkBoost($ok,$label){global $checks;$checks++;if(!$ok)throw new RuntimeException($label);}
function rejectBoost(callable $fn,int $code=422): bool {try{$fn();}catch(BoostError $e){return $e->getCode()===$code;}return false;}
class BoostFixtureTransport extends BoostTransport {
  public $sent=[]; public $preflightFailure=false; public $available=true; public $reply=['http_status'=>200,'body'=>['code'=>0],'transport_error'=>false];public $onInfo=null;public $onSend=null;
  public function identity(array $shop): void {if($this->preflightFailure)throw new BoostError('Fixture preflight failure',502);}
  public function info(array $shop,array $ids): array {if($this->onInfo)($this->onInfo)();$r=[];foreach($ids as $id)$r[$id]=['eligible'=>$this->available,'reason'=>'Fixture unavailable'];return $r;}
  public function send(array $shop,string $id): array {$this->sent[]=$id;if($this->onSend)($this->onSend)($id);return $this->reply;}
}
checkBoost(BoostPolicy::ids(['1',2])===['1','2'],'Normalize bounded IDs');
foreach([[],['1','1'],[0],[-1],[true],['1e2'],['99999999999999999'],range(1,6)] as $ids)checkBoost(rejectBoost(fn()=>BoostPolicy::ids($ids)),'Reject invalid selections');
checkBoost(BoostPolicy::ids([],true)===[],'Allow clearing profile');
checkBoost(!BoostTransport::senderSetting('0','1'),'Environment stop overrides enabled config');
checkBoost(BoostTransport::senderSetting(false,'1'),'Config can enable without process override');
checkBoost(BoostPolicy::outcome(['http_status'=>200,'body'=>['code'=>0]])['status']==='success','Strict accepted response');
foreach([['code'=>'0'],['code'=>false],['code'=>null],[],null,'ok'] as $body)checkBoost(BoostPolicy::outcome(['http_status'=>200,'body'=>$body])['status']==='unknown','Malformed response never retries');
checkBoost(BoostPolicy::outcome(['http_status'=>503,'body'=>['code'=>0]])['status']==='unknown','HTTP failure overrides API code');
checkBoost(BoostPolicy::outcome(['http_status'=>200,'body'=>['code'=>17]])['status']==='failed','Explicit rejection');
$info=BoostPolicy::info(['code'=>0,'data'=>['boost_infos'=>[['product_id'=>1,'show_boost_button'=>true,'disabled_boost_button'=>false]]]],['1','2']);
checkBoost($info[1]['eligible'] && !$info[2]['eligible'],'List response and missing ID');
$info=BoostPolicy::info(['code'=>0,'data'=>['boost_infos'=>['123'=>['show_boost_button'=>1,'disabled_boost_button'=>0]]]],['123']);
checkBoost($info[123]['eligible'],'Map response');
$info=BoostPolicy::info(['code'=>0,'data'=>['boost_infos'=>['123'=>[]]]],['123']);checkBoost(!$info[123]['eligible'],'Missing flags fail closed');
checkBoost(rejectBoost(fn()=>BoostPolicy::info(['code'=>'0'],['1']),502),'Invalid GET schema');
$info=BoostPolicy::info(['code'=>0,'data'=>['boost_infos'=>['123'=>['product_id'=>456,'show_boost_button'=>true,'disabled_boost_button'=>false]]]],['123']);checkBoost(!$info[123]['eligible'],'Mismatched remote item identity');
class BoostReadFixtureTransport extends BoostTransport {
  public function request(string $method,string $path,string $cookie,array $query=[],array $body=[]): array{return ['http_status'=>429,'body'=>null,'retry_after'=>3600];}
}
try{(new BoostReadFixtureTransport())->info(['cookie'=>'fixture'],['1']);checkBoost(false,'Expected read failure');}catch(BoostError $error){checkBoost($error->retryAfter===3600,'GET Retry-After retained');}
$db=new Database();
$exec=function($sql)use($db){$db->query($sql);$db->exe();};
$one=function($sql)use($db){$db->query($sql);return $db->single();};
$tables=['boost_item_events','boost_run_meta','boost_profiles','boost_worker_health','product_boost_items','product_boost_runs','products','shops'];
try {
  $exec('CREATE TEMPORARY TABLE shops (id INT PRIMARY KEY,shop_id BIGINT,name VARCHAR(255),cookie TEXT,sync_status VARCHAR(50)) ENGINE=InnoDB');
  $exec('CREATE TEMPORARY TABLE products (id BIGINT PRIMARY KEY,shop_id INT,name VARCHAR(255),status INT,deleted_at DATETIME NULL,total_stock INT,sold_count INT,cover_image VARCHAR(255),price_min BIGINT) ENGINE=InnoDB');
  foreach(['20260927_product_boost.sql','20260930_boost_automation.sql'] as $name){$sql=preg_replace('/^--.*$/m','',file_get_contents('../database/migrations/'.$name));foreach(explode(';',$sql) as $q)if(trim($q))$exec(str_replace('CREATE TABLE IF NOT EXISTS','CREATE TEMPORARY TABLE',$q));}
  $exec("INSERT INTO shops(id,shop_id,name,cookie,sync_status) VALUES (1,101,'Fixture one','fixture-only','connected'),(2,102,'Fixture two','fixture-only','connected')");
  for($i=1;$i<=7;$i++)$exec("INSERT INTO products(id,shop_id,name,status,total_stock,sold_count) VALUES ($i,1,'Fixture $i',1,10,$i)");
  $exec("INSERT INTO products(id,shop_id,name,status,total_stock) VALUES (100,2,'Other shop',1,10)");
  $exec("UPDATE products SET deleted_at=UTC_TIMESTAMP() WHERE id=6");$exec('UPDATE products SET total_stock=0 WHERE id=7');
  $s=new BoostStore($db);$t=new BoostFixtureTransport();$e=new BoostExecutor($s,$t,fn()=>true);
  $clean=function()use($exec,$t){foreach(['boost_item_events','boost_run_meta','product_boost_items','product_boost_runs'] as $table)$exec('DELETE FROM '.$table);$t->sent=[];$t->preflightFailure=false;$t->available=true;$t->onInfo=null;$t->onSend=null;$t->reply=['http_status'=>200,'body'=>['code'=>0]];};
  $p=$s->save(1,0,['1','2'],1);checkBoost($p['version']===1 && !$p['enabled'],'Save persists without activation');
  checkBoost(rejectBoost(fn()=>$s->save(1,0,['3'],1),409),'Optimistic save conflict');
  checkBoost(rejectBoost(fn()=>$s->save(1,1,['100'],1)),'Cross-shop profile rejected');
  checkBoost(rejectBoost(fn()=>$s->save(1,1,['6'],1)),'Deleted new choice rejected');
  checkBoost($s->profile(2)['product_ids']===[],'Independent shops');
  checkBoost($s->catalog(1,'Fixture',1)['total']===6,'Catalog excludes deleted, includes waiting stock');
  $exec("INSERT INTO products(id,shop_id,name,status,total_stock,sold_count) VALUES (8,1,'Inactive bestseller',0,10,999),(9,1,'No sales',1,10,0),(10,1,'Missing sales',1,10,NULL),(11,1,'Tied sales',1,10,5),(12,1,'Bestseller',1,10,100)");
  $exec('UPDATE products SET sold_count=9999 WHERE id=100');
  $profileBefore=$s->profile(1);$runsBefore=$one('SELECT COUNT(*) AS c FROM product_boost_runs');
  $recommendations=$s->recommendations(1);
  checkBoost(array_column($recommendations,'id')===['12','11','5','4','3'],'Recommend five highest sales with deterministic ties and exclude other shop/inactive/deleted/zero stock');
  checkBoost($s->recommendations(2)[0]['id']==='100','Recommendations scoped independently per shop');
  checkBoost($s->recommendations(3)===[],'Empty recommendation shop');
  $exec('UPDATE products SET total_stock=0 WHERE id IN (1,2,3,4,5,11,12)');
  checkBoost($s->recommendations(1)===[],'Zero or missing sales never presented as bestsellers');
  $exec('UPDATE products SET total_stock=10 WHERE id=1');
  checkBoost(array_column($s->recommendations(1),'id')===['1'],'Fewer than five eligible recommendations');
  checkBoost($s->profile(1)===$profileBefore && $one('SELECT COUNT(*) AS c FROM product_boost_runs')===$runsBefore,'Recommendations do not save, enable or send');
  $exec('DELETE FROM products WHERE id BETWEEN 8 AND 12');$exec('UPDATE products SET total_stock=10 WHERE id BETWEEN 1 AND 5');
  $p=$s->toggle(1,1,true,1);checkBoost($p['enabled'] && $p['version']===2,'Enable versioned');
  $s->toggle(1,2,false,1);checkBoost(!$s->profile(1)['enabled'],'Pause');
  checkBoost(rejectBoost(fn()=>$e->execute(1,'manual',['6'],1,'fixture_deleted_1'),409),'Deleted write rejected');
  checkBoost(rejectBoost(fn()=>$e->execute(1,'manual',['7'],1,'fixture_no_stock_1'),409),'Zero stock write rejected');
  checkBoost(rejectBoost(fn()=>$e->execute(1,'manual',['100'],1,'fixture_other_01'),409),'Cross-shop write rejected');
  $r=$e->execute(1,'manual',['1','2'],1,'fixture_success_1');checkBoost((int)$r['success_count']===2 && $t->sent===['1','2'],'Manual shared executor');
  $again=$e->execute(1,'manual',['1','2'],1,'fixture_success_1');checkBoost($again['replayed'] && count($t->sent)===2,'Idempotent replay');
  checkBoost(rejectBoost(fn()=>$e->execute(1,'manual',['3'],1,'fixture_success_1'),409),'Key payload mismatch rejected');
  $exec('UPDATE product_boost_items SET attempted_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 250 MINUTE)');
  checkBoost(rejectBoost(fn()=>$e->execute(1,'manual',['1'],1,'fixture_cooldown1'),409),'255-minute cooldown enforced inside reservation');
  $exec('UPDATE product_boost_items SET attempted_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 256 MINUTE)');
  checkBoost((int)$e->execute(1,'manual',['1'],1,'fixture_cooldown2')['success_count']===1,'Cooldown eventually releases');
  $clean();$t->preflightFailure=true;$r=$e->execute(1,'manual',['1'],1,'fixture_get_fail1');
  checkBoost($t->sent===[] && $s->summary(1)['remaining_count']===5 && $r['items'][0]['status']==='not_sent','GET failure uses no quota');
  $clean();$t->available=false;$r=$e->execute(1,'manual',['1'],1,'fixture_no_slot01');checkBoost(!$t->sent && $r['items'][0]['status']==='not_sent','Remote unavailable not sent');
  $clean();$t->reply=['http_status'=>0,'transport_error'=>true];$r=$e->execute(1,'manual',['1','2'],1,'fixture_timeout1');
  checkBoost($t->sent===['1'] && $r['items'][0]['status']==='unknown' && $r['items'][1]['status']==='not_sent','Uncertain send stops remainder');
  $exec('UPDATE product_boost_items SET attempted_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 2 DAY)');
  checkBoost($s->summary(1)['remaining_count']===0,'Unknown stays blocked beyond quota window');
  checkBoost(rejectBoost(fn()=>$e->execute(1,'manual',['2'],1,'fixture_unknown1'),409),'Unknown prevents new sends');
  $s->resolve(1,(int)$r['run_id'],'1','confirmed_not_sent',1);checkBoost($s->summary(1)['unresolved_count']===0,'Explicit confirmed-not-sent resolution');
  checkBoost(rejectBoost(fn()=>$s->resolve(1,(int)$r['run_id'],'1','confirmed_sent',1),409),'Cannot resolve twice');
  $clean();$token=$s->lock(1);$reservation=$s->reserve(1,['1','2'],'manual',0,1,'fixture_crash001',$token,'hash');
  $s->sending(1,$reservation['run_id'],'1',$token,'manual',0);$s->unlock(1);
  $token=$s->lock(1);$s->recover(1);$s->unlock(1);$r=$s->result($reservation['run_id']);
  checkBoost($r['items'][0]['status']==='unknown' && $r['items'][1]['status']==='not_sent','Crash separates sending from reserved');
  checkBoost($r['status']==='partial','Crash cannot report completed');
  $s->resolve(1,(int)$r['run_id'],'1','confirmed_sent',1);checkBoost($s->productCooldowns(1,['1'])['1']['cooldown_active'],'Confirmed sent retains original cooldown');
  $clean();$p=$s->profile(1);$p=$s->toggle(1,$p['version'],true,1);
  $t->onSend=function($id)use($s){$p=$s->profile(1);$s->toggle(1,$p['version'],false,1);};
  $r=$e->execute(1);checkBoost($t->sent===['1'] && $r['items'][1]['status']==='not_sent','Pause between products prevents second POST');
  $clean();$p=$s->profile(1);$p=$s->toggle(1,$p['version'],true,1);
  $t->onInfo=function()use($s){$p=$s->profile(1);$s->save(1,$p['version'],['3'],1);};
  $r=$e->execute(1);checkBoost(!$t->sent && $r['items'][0]['status']==='not_sent','Edit after claim invalidates preflight');
  $clean();$p=$s->profile(1);$s->save(1,$p['version'],['1','2','7'],1);
  $r=$e->execute(1);checkBoost($t->sent===['1','2'],'Partial eligibility repeats chosen subset only');
  $count=count($t->sent);$e->execute(1);checkBoost(count($t->sent)===$count,'Next due respected');
  $healthBefore=$s->health();$runBefore=$one('SELECT COUNT(*) AS c FROM product_boost_runs');$e->preview(1);checkBoost($runBefore===$one('SELECT COUNT(*) AS c FROM product_boost_runs') && $healthBefore===$s->health(),'Preview has no mutations');
  $clean();$disabled=new BoostExecutor($s,$t,fn()=>false);checkBoost(rejectBoost(fn()=>$disabled->execute(1,'manual',['1'],1,'fixture_disabled'),503) && !$t->sent,'Global stop blocks manual sender');
  $clean();$switch=true;$t->onSend=function()use(&$switch){$switch=false;};$stoppable=new BoostExecutor($s,$t,function()use(&$switch){return $switch;});
  $r=$stoppable->execute(1,'manual',['1','2'],1,'fixture_hot_stop');checkBoost($t->sent===['1'] && $r['items'][1]['status']==='not_sent','Global stop rechecked between POSTs');
  $clean();$p=$s->profile(1);$s->save(1,$p['version'],[],1);checkBoost(!$s->profile(1)['enabled'],'Clearing selections pauses profile');
  echo "PASS: $checks Boost policy, profile, execution, crash, pause, deduplication and reconciliation checks\n";
} finally {foreach($tables as $table)$exec('DROP TEMPORARY TABLE IF EXISTS '.$table);}
