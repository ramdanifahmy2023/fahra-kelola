<?php
declare(strict_types=1);
chdir(__DIR__.'/../public');
require '../app/init.php';
require '../app/models/Finance.php';
$options=getopt('',['shop::','start::','end::','pages::','schema-only','pending-only']);
$finance=new Finance(); $finance->ensureSchema();
if (isset($options['schema-only'])) { echo "Finance schema ready\n"; exit; }
$range=FinancePolicy::range($options['start'] ?? null,$options['end'] ?? null);
$shops=$finance->shops($options['shop'] ?? '');
$finance->requestImports($shops,$range,true,isset($options['pending-only']));
$limit=max(1,min(2000,(int)($options['pages'] ?? 100))); $failed=false;
foreach ($shops as $item) {
  $shop=$finance->one('SELECT * FROM shops WHERE id=:id',['id'=>$item['id']]);
  $result=$finance->work($shop,null,$limit); $failed=$failed || !$result[0];
  echo json_encode(['shop_id'=>(int)$shop['id'],'ok'=>$result[0],'message'=>$result[1],'complete'=>$result[2]],JSON_UNESCAPED_UNICODE).PHP_EOL;
}
exit($failed ? 1 : 0);
