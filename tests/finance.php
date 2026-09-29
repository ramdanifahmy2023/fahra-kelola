<?php
chdir(__DIR__.'/../public');
require '../app/init.php';
require '../app/models/FinanceCost.php';
require '../app/helpers/FinanceApi.php';
class FinanceFixture extends Finance {
  public function __construct($db) { $this->db=$db; }
  protected function lockKey(string $kind,int $shop): string { return 'finance-test-'.getmypid().'-'.$kind.'-'.$shop; }
}
class CostFixture extends FinanceCost { public function __construct($db) { $this->db=$db; } }
function financeCheck($value,$message) { if (!$value) throw new RuntimeException($message); }
function financeThrows(callable $fn,$code=null) { try { $fn(); } catch(Throwable $e) { if($code!==null)financeCheck($e->getCode()===$code,'Expected error code '.$code.', got '.$e->getMessage()); return; } throw new RuntimeException('Expected rejection'); }
function financeEntry($id,$amount,$release=null) { return ['local_income_detail'=>['order_income_info'=>['order_id'=>$id,'order_sn'=>'TEST-'.$id,'item_name'=>'Test product'],'income_amount'=>$amount,'net_income_amount'=>0,'adjustment_income_amount'=>0,'income_released_time'=>$release]]; }
class FinanceSourceFixture {
  public $calls=0,$verifyCalls=0,$fail=false,$repeat=false,$wrong=false,$missingStatuses=false;
  public function verify($shop) { $this->verifyCalls++; if($this->wrong)throw new RuntimeException('Cookie tidak cocok'); }
  public function overview($shop) { return ['pending'=>3000000,'week'=>5000000,'month'=>6000000,'all'=>7000000]; }
  public function pendingStates($shop,$ids,$rateMs) { return $this->missingStatuses ? [] : array_fill_keys($ids,['state'=>'shipping','synced_at'=>gmdate('Y-m-d H:i:s')]); }
  public function page($shop,$import) {
    $this->calls++; if($this->fail)throw new RuntimeException('Sumber gagal');
    if((int)$import['category']===2)return ['rows'=>[financeEntry(30,4000000,strtotime('2026-09-01 18:00:00 UTC'))],'next'=>null];
    if(!$import['cursor_json'] || $this->repeat)return ['rows'=>[financeEntry(10,1000000)],'next'=>['direction'=>0,'limit'=>50,'cursor'=>'page-2']];
    return ['rows'=>[financeEntry(10,1000000),financeEntry(11,2000000)],'next'=>null];
  }
}
$db=new Database(); $f=new FinanceFixture($db); $cost=new CostFixture($db);
$tables=['finance_imports','finance_income_rows','finance_pending_states','finance_pending_diagnostics','finance_paid_intraday','finance_current','finance_days','finance_overview_totals','finance_cost_heads','finance_cost_versions','finance_cost_events','shops','orders','order_items','products','product_models','ad_shop_snapshots','ad_browser_reports','ad_balance_topups','ad_topup_sync_state','sync_schedules','shop_performance_daily'];
try {
  foreach($tables as $table) {
    $schema=$f->one('SHOW CREATE TABLE '.$table)['Create Table'];
    $schema=preg_replace('/^CREATE TABLE/','CREATE TEMPORARY TABLE',$schema);
    $schema=preg_replace('/^\s*CONSTRAINT.*\n/m','',$schema);
    $f->execute(str_replace(",\n)","\n)",$schema));
  }
  $f->execute("INSERT INTO shops (id,shop_id,name) VALUES (1,101,'First'),(2,202,'Second')");
  $f->execute("INSERT INTO products (id,shop_id,name,parent_sku) VALUES (10,1,'One','SAME'),(20,2,'Two','SAME'),(30,1,'Variants','PARENT')");
  $f->execute("INSERT INTO product_models (id,product_id,name,sku) VALUES (31,30,'Red','SAME'),(32,30,'Blue','SAME')");
  $f->execute("INSERT INTO orders (id,shop_id,order_sn,created_at) VALUES (1,1,'O1','2026-09-01 16:59:59'),(2,1,'O2','2026-09-01 17:00:00'),(3,2,'O3','2026-09-01 17:00:00'),(4,1,'O4','2026-09-03 17:00:00')");
  $f->execute("INSERT INTO order_items (order_id,product_id,model_id,quantity) VALUES (1,10,0,1),(2,10,0,2),(3,20,0,5),(4,10,0,3)");
  $shops=$f->shops(); $range=FinancePolicy::range('2026-09-01','2026-09-02');
  financeCheck(count($f->shops('1,2'))===2,'Multiple shops'); financeThrows(fn()=>$f->shops('999'));
  financeCheck(count(FinancePolicy::windows(FinancePolicy::range('2026-08-31','2026-09-02')))===2,'Split calendar months');
  financeThrows(fn()=>FinancePolicy::range('2026-09-31','2026-09-31'));
  financeCheck(FinancePolicy::row(financeEntry(1,123456789,strtotime('2026-09-01 17:00:00 UTC')),2)['released_date']==='2026-09-02','Release midnight WIB');
  financeThrows(fn()=>FinancePolicy::row(financeEntry(1,'bad'),1));
  $state=['detail_synced_at'=>gmdate('Y-m-d H:i:s'),'completed_at'=>gmdate('Y-m-d H:i:s'),'status_description'=>'Pesanan sedang dikirimkan ke Pembeli.'];
  financeCheck(FinancePolicy::pendingState($state)==='shipping','Shipping confirmed by description');
  financeCheck(FinancePolicy::pendingState(['status_key'=>'ps_content_return_processing']+$state)==='return','Return does not enter shipping');
  financeCheck(FinancePolicy::pendingState(['status_code'=>2])==='unknown','Numeric status alone is insufficient');
  financeCheck(FinancePolicy::deliveryState('Pesanan telah tiba di Pembeli. Dana akan dilepaskan paling lambat {timestamp}.')==='delivered','Observed Indonesian arrival description');
  financeCheck(FinancePolicy::deliveryState('Order has been delivered to buyer. Escrow will be released by {timestamp}.')==='delivered','Observed English arrival description');
  financeCheck(FinancePolicy::deliveryState('Order is being shipped to buyer.')==='shipping','Observed English shipping description');
  financeCheck(FinancePolicy::deliveryState('Buyer raised return/refund. Escrow will be released after return/refund completed.')==='return','Return takes priority over arrival');
  financeCheck(FinancePolicy::deliveryState('Telah Dikirim')==='unknown','Ambiguous short status cannot prove arrival');
  financeCheck(FinancePolicy::deliveryState('Order has not been delivered to buyer.')==='unknown','Unrecognized negative description cannot imply delivery');
  financeCheck(FinancePolicy::pendingState(['detail_synced_at'=>'2000-01-01']+$state)==='unknown','Old local status stays unknown');
  $source=new FinanceSourceFixture(); $f->requestImports([$shops[0]],$range);
  financeCheck($f->work($shops[0],$source,1)===[true,null,false],'First page checkpoints');
  financeCheck(!$f->one('SELECT * FROM finance_current'),'Partial pages not published');
  financeCheck($f->work($shops[0],$source,2)===[true,null,true],'Last pages publish');
  $summary=$f->summary($shops,$range)['stores'];
  financeCheck($summary[0]['pending']==30 && $summary[0]['pending_orders']===2,'Pending uses income, deduplicates order');
  financeCheck($summary[0]['released']==40 && $summary[0]['released_days']===2,'Release includes empty covered days');
  financeCheck($summary[1]['pending']===null && $summary[1]['released']===null,'Missing shop is not zero');
  financeCheck($summary[0]['pending_states']['shipping']===30 && $summary[0]['pending_state_counts']['shipping']===2,'Fresh status snapshot works without local order details');
  financeCheck($f->details([$shops[0]],$range,1,1,'','shipping')['total']===2,'Shipping drilldown matches summary');
  financeCheck($f->details([$shops[0]],$range,1,1,'','delivered')['total']===0,'Arrival filter excludes shipping');
  financeCheck($f->details([$shops[1]],$range,1,1,'','shipping')['total']===0,'Drilldown respects shop scope');
  financeThrows(fn()=>$f->details($shops,$range,1,1,'','invalid'));
  financeThrows(fn()=>$f->details($shops,$range,2,1,'','shipping'));
  $f->execute("INSERT INTO ad_topup_sync_state (shop_id,source_shop_id,backfill_complete,synced_at) VALUES (1,101,1,'2026-09-03 01:00:00'),(2,202,1,'2026-09-03 01:00:00')");
  $f->execute("INSERT INTO ad_balance_topups (shop_id,source_shop_id,order_id,actual_price,occurred_at_utc,source_hash) VALUES (1,101,1,27750,'2026-08-31 16:59:59','a'),(1,101,2,55500,'2026-08-31 17:00:00','b'),(1,101,3,111000,'2026-09-02 17:00:00','c'),(1,999,4,999999,'2026-09-01 17:00:00','d')");
  $topups=$f->summary($shops,$range)['stores'];
  financeCheck($topups[0]['topups']['amount']===55500.0 && $topups[0]['topups']['transactions']===1 && $topups[0]['topups']['complete'],'Topups use paid rupiah incl VAT, inclusive WIB range, and verified shop identity');
  financeCheck($topups[0]['ads']['amount']===null,'Topups do not fill or replace ad spend');
  financeCheck($topups[1]['topups']['amount']===0,'Synced empty topups are zero');
  $f->execute('DELETE FROM ad_topup_sync_state WHERE shop_id=2');
  financeCheck($f->summary([$shops[1]],$range)['stores'][0]['topups']['amount']===null,'Unsynced empty topups are unknown');
  financeCheck(!$f->summary([$shops[0]],FinancePolicy::range('2026-07-01','2026-09-02'))['stores'][0]['topups']['complete'],'Before history cutoff is partial');
  $f->execute("UPDATE ad_topup_sync_state SET last_error='fixture error' WHERE shop_id=1");
  $partial=$f->summary([$shops[0]],$range)['stores'][0]['topups'];
  financeCheck(!$partial['complete'] && $partial['amount']===55500.0 && $partial['error'],'Failure retains previous topup amount with incomplete state');
  $f->execute("UPDATE finance_overview_totals SET month_start='2026-09-01',as_of_date='2026-09-02'");
  $overviewSummary=$f->summary([$shops[0]],$range)['stores'][0];
  financeCheck($overviewSummary['released']===60 && $overviewSummary['released_detail']===40 && $overviewSummary['released_difference']===20 && $overviewSummary['released_basis']==='overview_month','Shopee overview takes precedence and discrepancy stays explicit');
  $f->execute("UPDATE finance_overview_totals SET as_of_date='2026-09-03'");
  financeCheck($f->details([$shops[0]],$range,2,1)['total']===1,'Released detail does not multiply by day');
  $f->execute("UPDATE finance_imports SET completed_at='2026-01-01' WHERE state='ready'");
  $f->requestImports([$shops[0]],$range); $source->fail=true;
  financeCheck($f->work($shops[0],$source,1)[0]===false,'Failed source fails import');
  financeCheck($f->summary([$shops[0]],$range)['stores'][0]['pending']==30,'Failure keeps complete snapshot');
  financeCheck($f->summary([$shops[0]],$range)['stores'][0]['error']!==null,'Failed pending remains visible even with later released import');
  $source->fail=false; $f->work($shops[0],$source,1);
  financeCheck($f->details([$shops[0]],$range,2,1)['total']===1,'Overlapping reimport not double counted');
  $f->execute("UPDATE finance_imports SET completed_at='2026-01-01'");
  $f->requestImports([$shops[0]],$range); $source->repeat=true;
  financeCheck($f->work($shops[0],$source,2)[0]===false,'Repeated cursor rejected');
  $source->repeat=false; $source->wrong=true;
  financeCheck($f->work($shops[0],$source,1)[0]===false,'Mismatched cookie rejected');
  financeCheck($f->summary([$shops[0]],$range)['stores'][0]['pending']==30,'Identity failure keeps prior snapshot');
  $source->wrong=false;$source->missingStatuses=true;
  $f->execute("UPDATE finance_imports SET completed_at='2026-01-01'");$f->requestImports([$shops[0]],$range);$f->work($shops[0],$source,3,0);
  $statusFailure=$f->summary([$shops[0]],$range)['stores'][0];
  financeCheck($statusFailure['pending']===30 && $statusFailure['pending_state_counts']['unknown']===2,'Failed status reads publish valid income as unknown, never zero shipping certainty');
  $source->missingStatuses=false;
  $repair=$f->repairPending($shops[0],$source);
  financeCheck($repair['checked']===2 && $repair['identified']===2,'Repair reads only unresolved pending orders');
  financeCheck($f->repairPending($shops[0],$source)['checked']===0,'Identified orders are not requested again during repair');
  financeCheck($f->summary([$shops[0]],$range)['stores'][0]['pending']===30,'Status repair does not change income');

  $catalog=$cost->catalog($shops,1,'SAME'); financeCheck($catalog['total']===4,'Duplicate SKU retained across variants and shops');
  $input=['shop_id'=>1,'product_id'=>'10','model_id'=>'0','unit_cost'=>'20000','valid_from'=>'2026-09-02','version'=>0];
  $preview=$cost->preview($input);
  financeCheck($preview['impact']['orders']===2 && $preview['impact']['units']===5 && $preview['impact']['missing_units']===5,'WIB boundary and local shop scope in preview');
  $expires=time()+600; $proof=['expires'=>$expires,'token'=>FinanceCost::token($preview,$expires,'test-secret')];
  financeThrows(fn()=>$cost->save($input,1,'test-secret'),409);
  $cost->save($input+$proof,1,'test-secret');
  financeThrows(fn()=>$cost->save($input+$proof,2,'test-secret'),409);
  financeCheck($cost->history(['shop_id'=>2,'product_id'=>20,'model_id'=>0])===[],'Other shop cost untouched');
  financeCheck((int)$cost->history($input)[0]['unit_cost']===20000,'Saved cost');
  financeCheck($cost->orderCost(1,'1')['amount']===null,'Earlier order has no invented HPP');
  financeCheck($cost->orderCost(1,'2')['amount']===40000,'Per-order HPP uses quantity and WIB start');
  financeCheck($cost->orderCost(2,'2')['amount']===null,'Per-order HPP is shop scoped');
  $input['version']=1; $input['valid_from']='2026-09-04'; $input['unit_cost']='0';
  $preview=$cost->preview($input); $cost->save($input+['expires'=>$expires,'token'=>FinanceCost::token($preview,$expires,'test-secret')],1,'test-secret');
  $input['version']=2; $input['valid_from']='2026-09-02'; $input['unit_cost']='25000';
  $preview=$cost->preview($input); financeCheck($preview['impact']['units']===2 && $preview['impact']['known_cost']===40000 && $preview['until']==='2026-09-03','Retroactive correction stops at next cost date');
  $proof=['expires'=>$expires,'token'=>FinanceCost::token($preview,$expires,'test-secret')];
  $f->execute('UPDATE order_items SET quantity=4 WHERE order_id=2');
  financeThrows(fn()=>$cost->save($input+$proof,1,'test-secret'),409);
  $preview=$cost->preview($input); $cost->save($input+['expires'=>$expires,'token'=>FinanceCost::token($preview,$expires,'test-secret')],1,'test-secret');
  financeCheck((int)$f->one('SELECT COUNT(*) n FROM finance_cost_events')['n']===3,'Every change audited');
  financeCheck($cost->orderCost(1,'2')['amount']===100000 && $cost->orderCost(1,'4')['amount']===0,'Retroactive HPP recalculates without overwriting later zero');
  $f->execute('DELETE FROM products WHERE id=10');
  $archived=$cost->catalog([$shops[0]],1,'SAME')['rows'];
  financeCheck(count($archived)===3 && (int)$archived[2]['archived']===1 && $archived[2]['unit_cost']===0,'Deleted catalog keeps history and explicit zero');
  financeThrows(fn()=>$cost->preview(['shop_id'=>2]+array_diff_key($input,['shop_id'=>true])));

  $transport=new class { public $response=['code'=>0,'data'=>[]]; public function request(...$args){return $this->response;} };
  $api=new FinanceApi($transport); $shop=['cookie'=>'SPC_CDS=fixture']; $import=['cursor_json'=>null,'category'=>1];
  financeCheck($api->page($shop,$import)===['rows'=>[],'next'=>null],'Observed empty-store data array accepted');
  $transport->response=['code'=>0]; financeThrows(fn()=>$api->page($shop,$import));
  $transport->response=['code'=>1,'data'=>[]]; financeThrows(fn()=>$api->page($shop,$import));
  $transport=new class {
    public $calls=[],$fail=false;
    public function request($method,$url,$cookie,$body,$headers) {
      $this->calls[]=$body;
      if($this->fail) return ['code'=>1];
      $cards=[];foreach($body['order_param_list'] as $order)$cards[]=['order_card'=>['order_ext_info'=>['order_id'=>$order['order_id']],'status_info'=>['status_description'=>['description_value'=>'Order has been delivered to buyer.']]]];
      $cards[]=['order_card'=>['order_ext_info'=>['order_id'=>9999],'status_info'=>['status_description'=>['description_value'=>'Order is being shipped to buyer.']]]];
      return ['code'=>0,'data'=>['card_list'=>$cards]];
    }
  };
  $api=new FinanceApi($transport);$statuses=$api->pendingStates(['cookie'=>'SPC_CDS=fixture','shop_id'=>101],range(1,6),0);
  financeCheck(count($transport->calls)===2 && count($transport->calls[0]['order_param_list'])===5 && count($statuses)===6,'Bounded five-order batches, unrelated response IDs rejected');
  financeCheck($statuses[1]['state']==='delivered' && !isset($statuses[9999]),'Order cards use verified description');
  $transport->fail=true;$failedStatus=$api->pendingStates(['cookie'=>'SPC_CDS=fixture','shop_id'=>101],[1],0)[1];
  financeCheck($failedStatus['state']==='unknown' && $failedStatus['reason']==='request_failed' && $failedStatus['synced_at']===null,'Failed status request remains unknown with cause');

  $transport=new class {
    public $descriptions=[];
    public function request(...$args) {
      return ['code'=>0,'data'=>['card_list'=>array_map(static fn($id,$descriptions)=>['package_level_order_card'=>[
        'order_ext_info'=>['order_id'=>$id], 'package_list'=>array_map(static fn($d)=>['status_info'=>['status_description'=>['description_value'=>$d]]],$descriptions)
      ]],array_keys($this->descriptions),array_values($this->descriptions))]];
    }
  };
  $transport->descriptions=[1=>['Mohon kirim / arrange pickup sebelum {timestamp} untuk menghindari keterlambatan pengiriman.'],2=>['Menunggu pengiriman diverifikasi oleh Jasa Kirim.'],3=>['Paket dipick up pada {timestamp}.'],4=>['Order is being shipped to buyer.','Order has been delivered to buyer.'],5=>['Order is being shipped to buyer.','Status uji tidak dikenal']];
  $parsed=(new FinanceApi($transport))->pendingStates(['cookie'=>'SPC_CDS=fixture','shop_id'=>101],range(1,5),0);
  financeCheck($parsed[1]['state']==='preparing','Package cards identify preparation');
  financeCheck($parsed[2]['state']==='pickup' && $parsed[2]['reason']==='courier_verification','Courier verification has its own note');
  financeCheck($parsed[3]['state']==='pickup' && $parsed[3]['reason']==='pickup_recorded','Recorded pickup differs from waiting verification');
  financeCheck($parsed[4]['state']==='mixed' && $parsed[5]['state']==='unknown','Mixed packages counted once; unknown package prevents false classification');
  financeCheck(FinancePolicy::deliveryState('To avoid late shipment, please arrange drop-off / arrange pickup by {timestamp}.')==='preparing','Observed English preparation');
  financeCheck(FinancePolicy::combineStatuses([$parsed[2],$parsed[3]])===['state'=>'pickup','reason'=>null],'Two known pickup descriptions remain one stage');

  $transport=new class {
    public $badDate=false,$fail=false,$value=0;
    public function getShopPerformance($cookie,$start,$end,$period) {
      financeCheck($period==='real_time' && $start->format('H:i')==='00:00' && $end->format('H:i')==='10:00','Intraday request follows observed WIB hour bounds');
      return ['ok'=>!$this->fail,'result'=>['paid_gmv'=>['value'=>$this->value,'points'=>[['timestamp'=>$start->getTimestamp()-($this->badDate ? 1 : 0),'value'=>$this->value]]]]];
    }
  };
  $api=new FinanceApi($transport);$at=new DateTimeImmutable('2026-09-29 10:35:00',new DateTimeZone('Asia/Jakarta'));
  financeCheck($api->paidToday(['cookie'=>'fixture'],$at)===['date'=>'2026-09-29','amount'=>0,'through_at'=>'2026-09-29 03:00:00'],'Verified zero paid GMV with source cutoff');
  $transport->value=915200.0000000001;financeCheck($api->paidToday(['cookie'=>'fixture'],$at)['amount']===915200,'Observed floating noise preserves whole rupiah');
  $transport->value=12.5;financeThrows(fn()=>$api->paidToday(['cookie'=>'fixture'],$at));$transport->value=0;
  $transport->badDate=true;financeThrows(fn()=>$api->paidToday(['cookie'=>'fixture'],$at));
  $transport->badDate=false;$transport->fail=true;financeThrows(fn()=>$api->paidToday(['cookie'=>'fixture'],$at));
  financeThrows(fn()=>$api->paidToday(['cookie'=>'fixture'],$at->setTime(0,5)));

  $today=FinancePolicy::range()['end'];$yesterday=FinancePolicy::date($today)->modify('-1 day')->format('Y-m-d');$currentRange=FinancePolicy::range($yesterday,$today);
  $f->execute("INSERT INTO shop_performance_daily (shop_id,metric_date,source,paid_gmv,synced_at) VALUES (1,:yesterday,'homepage',100,'2026-09-29 01:00:00'),(1,:today,'homepage',20,'2026-09-29 01:00:00')",['yesterday'=>$yesterday,'today'=>$today]);
  $f->execute("INSERT INTO finance_paid_intraday (shop_id,source_shop_id,metric_date,amount,through_at,synced_at,last_attempt_at) VALUES (1,101,:today,30,'2026-09-29 02:00:00','2026-09-29 02:01:00','2026-09-29 02:01:00'),(1,999,:other,999,NULL,'2026-09-29 03:00:00','2026-09-29 03:00:00') ON DUPLICATE KEY UPDATE amount=VALUES(amount),synced_at=VALUES(synced_at)",['today'=>$today,'other'=>$today]);
  $s=$f->summary([$shops[0]],$currentRange)['stores'][0];
  financeCheck($s['gmv']===130.0 && $s['gmv_days']===2 && $s['gmv_coverage']['today_included'],'Today replaces overlapping daily value, never added twice, with source identity');
  $f->execute('DELETE FROM shop_performance_daily WHERE metric_date=:date',['date'=>$yesterday]);
  $s=$f->summary([$shops[0]],$currentRange)['stores'][0];
  financeCheck($s['gmv_coverage']['missing_dates']===[$yesterday],'Exact missing date remains visible');
  $f->execute('UPDATE finance_paid_intraday SET failed=1 WHERE shop_id=1 AND source_shop_id=101');
  financeCheck($f->summary([$shops[0]],$currentRange)['stores'][0]['gmv_coverage']['today_failed'],'Failed refresh preserves amount and exposes failure');
  echo "PASS: finance pagination, publication, deduplication, identity, WIB, failures, multi-shop scope, SKU variants, HPP history, impact, zero and concurrent edits\n";
} finally { foreach(array_reverse($tables) as $table)$f->execute('DROP TEMPORARY TABLE IF EXISTS '.$table); }
