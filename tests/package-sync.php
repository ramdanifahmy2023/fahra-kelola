<?php
chdir(__DIR__ . '/../public');
require '../app/init.php';
require '../app/helpers/PackageSynchronizer.php';
$db = new Database();
$checks = 0;
function checkPackage($value, $message) {
  global $checks;
  $checks++;
  if (!$value) throw new RuntimeException($message);
}
function packageSql($sql) {
  global $db;
  $db->query($sql);
  return $db->getAll();
}
class PackageTestSource {
  public $calls = [];
  public $fail = true;
  public function getPackage($cookie, $id) {
    $this->calls[] = $id;
    return $id === 1 && $this->fail ? false : ['order_info'=>['package_list'=>[]]];
  }
}
$tables = ['orders', 'sync_jobs', 'sync_runs'];
try {
  foreach ($tables as $table) {
    $schema = packageSql("SHOW CREATE TABLE {$table}")[0]['Create Table'];
    $schema = preg_replace('/^CREATE TABLE/', 'CREATE TEMPORARY TABLE', $schema);
    $schema = preg_replace('/^\s*CONSTRAINT.*\n/m', '', $schema);
    $db->query(str_replace(",\n)", "\n)", $schema)); $db->exe();
  }
  packageSql("INSERT INTO sync_jobs (id,shop_id,channel_id,sync_type,idempotency_key) VALUES (1,1,1,'packages','fixture')");
  packageSql("INSERT INTO sync_runs (shop_id,channel_id,sync_type,job_id,sync_job_id,started_at) VALUES (1,1,'packages',1,1,NOW())");
  for ($id=1; $id<=6; $id++) packageSql("INSERT INTO orders (id,shop_id,detail_synced_at) VALUES ({$id},1,NOW())");
  $source = new PackageTestSource();
  $sync = new PackageSynchronizer($db, $source);
  $shop = ['id'=>1,'cookie'=>'fixture'];
  $result = $sync->run(packageSql('SELECT * FROM sync_jobs WHERE id=1')[0], $shop, 50, 0);
  checkPackage($result[0] && !$result[2] && count($source->calls)===5, 'Large requested batch yields after five packages');
  checkPackage(packageSql('SELECT package_synced_at FROM orders WHERE id=1')[0]['package_synced_at']===null, 'Failed request never marks package synchronized');
  checkPackage(packageSql('SELECT package_synced_at FROM orders WHERE id=2')[0]['package_synced_at']!==null, 'Valid empty package result recorded');
  $result = $sync->run(packageSql('SELECT * FROM sync_jobs WHERE id=1')[0], $shop, 5, 0);
  checkPackage(!$result[0] && $result[2] && str_contains($result[1], '1 detail'), 'Failure from earlier turn survives until final result');
  checkPackage($source->calls===[1,2,3,4,5,6], 'Cursor advances despite failed package without starving later orders');
  $source->fail = false;
  packageSql('UPDATE sync_jobs SET page_sentinel=NULL WHERE id=1');
  packageSql('UPDATE sync_runs SET detail_failed=0 WHERE job_id=1');
  $result = $sync->run(packageSql('SELECT * FROM sync_jobs WHERE id=1')[0], $shop, 5, 0);
  checkPackage($result[0] && $result[2] && $source->calls===[1,2,3,4,5,6,1], 'Next cycle retries failure but skips recently checked empty packages');
  echo "PASS: {$checks} package synchronization checks\n";
} finally {
  foreach (array_reverse($tables) as $table) { $db->query("DROP TEMPORARY TABLE IF EXISTS {$table}"); $db->exe(); }
}
