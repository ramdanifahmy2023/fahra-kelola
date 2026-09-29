<?php
require __DIR__.'/../app/helpers/FinancePolicy.php';
require __DIR__.'/../app/helpers/FinanceAdCost.php';
function costCheck($ok,$message) { if (!$ok) throw new RuntimeException($message); }
$shop=['shop_id'=>101];$range=['start'=>'2026-09-01','end'=>'2026-09-29','days'=>29];
$report=['available'=>true,'mapping_version'=>2,'source_shop_id'=>101,'currency'=>'IDR','timezone'=>'Asia/Jakarta','start_date'=>$range['start'],'end_date'=>$range['end'],'fetched_at'=>'2026-09-29T12:00:00Z','daily'=>[]];
$reports=[];
foreach (['product','shop','live'] as $channel) $reports[]=array_merge($report,['channel'=>$channel,'raw_metrics'=>['cost'=>1000000]]);
$sum=FinanceAdCost::summarize($reports,$shop,$range);
costCheck($sum['amount']==30 && $sum['complete'] && $sum['days']===29 && $sum['basis']==='period','Monthly aggregate covers the exact period without fabricated daily points');
$duplicate=array_merge($reports[0],['daily'=>[['date'=>'2026-09-29','raw_metrics'=>['cost'=>1000000]]]]);
costCheck(FinanceAdCost::summarize([...$reports,$duplicate],$shop,$range)['amount']==30,'Overlapping daily/weekly/monthly reports never double count');
$browser=array_merge($reports[0],['fetched_at'=>'2026-09-29T13:00:00Z','raw_metrics'=>['cost'=>2000000],'collection_method'=>'browser_capture']);
$sum=FinanceAdCost::summarize([...$reports,$browser],$shop,$range);
costCheck($sum['amount']==40 && $sum['updated_at']==='2026-09-29 12:00:00','Freshest source per channel and oldest included timestamp');
foreach (['source_shop_id'=>999,'mapping_version'=>1,'currency'=>'USD','timezone'=>'UTC','available'=>false,'channel'=>'invalid','start_date'=>'2026-08-01','fetched_at'=>null] as $key=>$bad) {
  $invalid=array_merge($browser,[$key=>$bad]);costCheck(FinanceAdCost::summarize([...$reports,$invalid],$shop,$range)['amount']==30,'Reject mismatched '.$key);
}
$custom=['start'=>'2026-09-05','end'=>'2026-09-17','days'=>13];
costCheck(FinanceAdCost::summarize($reports,$shop,$custom)['amount']===null,'Monthly total cannot be divided into arbitrary custom dates');
costCheck(FinanceAdCost::summarize(array_slice($reports,0,2),$shop,$range)['amount']===null,'Missing channel cannot imply complete spend');
$zero=array_map(static fn($r)=>array_replace($r,['raw_metrics'=>['cost'=>0]]),$reports);
costCheck(FinanceAdCost::summarize($zero,$shop,$range)['amount']==0 && FinanceAdCost::summarize($zero,$shop,$range)['complete'],'Verified zero is available');
$days=[];
foreach ($reports as $r) $days[]=array_replace($r,['daily'=>[['date'=>'2026-09-05','raw_metrics'=>['cost'=>200000]],['date'=>'2026-09-06','raw_metrics'=>['cost'=>300000]]]]);
$small=['start'=>'2026-09-05','end'=>'2026-09-06','days'=>2];
$sum=FinanceAdCost::summarize($days,$shop,$small);
costCheck($sum['amount']==15 && $sum['complete'] && $sum['basis']==='daily','Real daily history can cover an exact custom range');
$days[2]['daily'][1]['raw_metrics']['cost']=null;
$sum=FinanceAdCost::summarize($days,$shop,$small);
costCheck($sum['amount']==6 && $sum['days']===1 && !$sum['complete'],'Only intersecting complete channel days count');
$stale=$reports;$stale[0]['stale']=true;
costCheck(FinanceAdCost::summarize($stale,$shop,$range)['stale'],'Retained data is visibly stale');
echo "PASS: finance ad period/daily selection, identity, currency, freshness, overlap, custom dates, missing channels and zero\n";
