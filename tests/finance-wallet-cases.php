<?php
// Runs inside finance.php's temporary tables and isolated worker lock.
require_once '../app/helpers/FinanceWalletApi.php';
$walletBody=['error'=>0,'data'=>['wallet_available_balance'=>123456,'wallet_active_balance'=>999,'wallet_blocked_balance'=>987,'shopeepay_available_balance'=>456]];
$value=FinanceWalletApi::parse($walletBody);
financeCheck($value===['amount'=>123456,'withdrawal_restricted'=>null,'notice'=>null],'Wallet uses raw Saldo rupiah and ignores other balances');
foreach ([0,'0',-10,'321'] as $amount) {
  $body=$walletBody;$body['data']['wallet_available_balance']=$amount;
  financeCheck(FinanceWalletApi::parse($body)['amount']===(int)$amount,'Valid zero/string/negative amount stays exact');
}
foreach ([null,false,1.5,'','1e3','9007199254740992',[]] as $invalid) {
  $body=$walletBody;$body['data']['wallet_available_balance']=$invalid;
  financeThrows(fn()=>FinanceWalletApi::parse($body));
}
financeThrows(fn()=>FinanceWalletApi::parse(['code'=>0,'data'=>$walletBody['data']]));
financeThrows(fn()=>FinanceWalletApi::parse(['error'=>1,'data'=>$walletBody['data']]));
financeThrows(fn()=>FinanceWalletApi::parse(['error'=>0,'data'=>[]]));
$restricted=$walletBody;$restricted['data']+=['is_seller_withdrawal_maintenance_group_active'=>true,'seller_withdrawal_maintenance_group_description'=>'<b>Verifikasi identitas</b> &amp; baca <a href="javascript:alert(1)">petunjuk</a><script>alert(1)</script>'];
$value=FinanceWalletApi::parse($restricted);
financeCheck($value['amount']===123456 && $value['withdrawal_restricted'] && $value['notice']==='Verifikasi identitas & baca petunjuk','Restriction keeps balance intact and strips active HTML');
$restricted['data']['is_seller_withdrawal_maintenance_group_active']=false;
financeCheck(FinanceWalletApi::parse($restricted)['notice']===null,'Inactive restriction does not surface stale source message');
$calls=[];$identity=101;$status=200;
$walletApi=new FinanceWalletApi(function($path,$query,$cookie)use(&$calls,&$identity,&$status,$walletBody){
  $calls[]=[$path,$query,$cookie];
  return ['status'=>$status,'body'=>str_contains($path,'shop_info') ? ['code'=>0,'data'=>['shop_id'=>$identity]] : $walletBody];
});
$walletShop=$shops[0]+['cookie'=>'other=x; SPC_CDS=fixture; extra=y'];
financeCheck($walletApi->read($walletShop)['amount']===123456 && count($calls)===3,'Identity checked before and after wallet read');
financeCheck($calls[1][1]===['SPC_CDS'=>'fixture','SPC_CDS_VER'=>2,'wallet_provider'=>0,'bank_account_id'=>0] && $calls[1][2]===$walletShop['cookie'],'Wallet request uses same shop cookie and audited parameters');
$identity=202;$calls=[];financeThrows(fn()=>$walletApi->read($walletShop));
financeCheck(count($calls)===1,'Wrong identity stops before wallet request');
$identity=101;$status=302;financeThrows(fn()=>$walletApi->read($walletShop));
financeThrows(fn()=>$walletApi->read($shops[0]));
$switchingCalls=0;
$switching=new FinanceWalletApi(function($path)use(&$switchingCalls,$walletBody){$switchingCalls++;return ['status'=>200,'body'=>str_contains($path,'shop_info') ? ['code'=>0,'data'=>['shop_id'=>$switchingCalls===1 ? 101 : 202]] : $walletBody];});
financeThrows(fn()=>$switching->read($walletShop));

$walletSource=new class {
  public $calls=0,$fail=false,$changeCookie=false,$db;
  public function wallet($shop){
    $this->calls++;
    if($this->changeCookie)$this->db->execute("UPDATE shops SET cookie='changed-during-read' WHERE id=1");
    if($this->fail)throw new RuntimeException('private source details must not leak');
    return ['amount'=>(int)$shop['id']===1 ? 123456 : 0,'withdrawal_restricted'=>(int)$shop['id']===1,'notice'=>(int)$shop['id']===1 ? 'Verifikasi identitas' : null];
  }
};
$walletSource->db=$f;
financeCheck($f->summary($shops,$range)['stores'][0]['wallet']['amount']===null,'Unsynced wallet is unknown');
$importsBefore=(int)$f->one('SELECT COUNT(*) n FROM finance_imports')['n'];
financeCheck($f->refreshWallet($shops[0],$walletSource)['updated'],'Wallet refresh works without income imports');
financeCheck((int)$f->one('SELECT COUNT(*) n FROM finance_imports')['n']===$importsBefore,'Wallet-only does not create income work');
financeCheck(!$f->refreshWallet($shops[0],$walletSource)['updated'] && $walletSource->calls===1,'Repeated worker pages respect interval');
$f->refreshWallet($shops[1],$walletSource);
$snapshot=$f->summary($shops,$range)['stores'];
financeCheck($snapshot[0]['wallet']['amount']===123456 && $snapshot[0]['wallet']['withdrawal_restricted'] && $snapshot[1]['wallet']['amount']===0,'Each shop has its own snapshot, including restricted and verified zero');
financeCheck($f->summary([$shops[0]],FinancePolicy::range('2026-08-01','2026-08-31'))['stores'][0]['wallet']['amount']===123456,'Wallet independent of report dates');
$f->requestWallets([$shops[0]]);
financeCheck(!$f->summary([$shops[0]],$range)['stores'][0]['wallet']['refresh_pending'],'Manual request debounced inside 60 seconds');
$f->execute('UPDATE finance_wallet SET last_attempt_at=UTC_TIMESTAMP()-INTERVAL 61 SECOND WHERE shop_id=1');
$f->requestWallets([$shops[0]]);
financeCheck($f->summary([$shops[0]],$range)['stores'][0]['wallet']['refresh_pending'],'Manual refresh becomes visible');
$walletSource->fail=true;
financeCheck(!$f->refreshWallet($shops[0],$walletSource)['ok'],'Wallet failure reported');
$failedWallet=$f->summary([$shops[0]],$range)['stores'][0]['wallet'];
financeCheck($failedWallet['amount']===123456 && $failedWallet['failed'] && $failedWallet['updated_at']===$snapshot[0]['wallet']['updated_at'] && !$failedWallet['refresh_pending'],'Failure retains previous amount, notice and actual successful timestamp');
financeCheck(!str_contains($failedWallet['error'],'private'),'Errors do not leak upstream details');
$f->execute('UPDATE finance_wallet SET synced_at=UTC_TIMESTAMP()-INTERVAL 21 MINUTE WHERE shop_id=1');
financeCheck($f->summary([$shops[0]],$range)['stores'][0]['wallet']['stale'],'Old snapshot marked stale');
$walletSource->fail=false;$walletSource->changeCookie=true;
$f->execute('UPDATE finance_wallet SET last_attempt_at=UTC_TIMESTAMP()-INTERVAL 11 MINUTE WHERE shop_id=1');
financeCheck(!$f->refreshWallet($shops[0],$walletSource)['ok'],'Cookie changed mid-read prevents publication');
$f->execute("UPDATE shops SET shop_id=999,cookie='' WHERE id=1");
financeCheck($f->summary($f->shops('1'),$range)['stores'][0]['wallet']['amount']===null,'Changed source identity cannot borrow prior balance');
$f->execute('UPDATE shops SET shop_id=101 WHERE id=1');
$walletSource->changeCookie=false;$walletSource->fail=true;
$f->execute('DELETE FROM finance_wallet WHERE shop_id=2');
$f->refreshWallet($shops[1],$walletSource);
financeCheck($f->summary([$shops[1]],$range)['stores'][0]['wallet']['amount']===null,'First-read failure does not become zero');
$f->execute('DELETE FROM finance_wallet');
echo "PASS: wallet source contract, raw rupiah, restrictions, identity, isolation, cadence, manual debounce, old data and failure retention\n";
