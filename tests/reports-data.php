<?php
chdir(__DIR__ . '/../public');
require '../app/init.php';
require '../app/models/ShopPerformance.php';
class ReportDataTest extends ShopPerformance {
  public function __construct($db) { $this->db = $db; }
  public function ensureSchema() {}
}
function reportCheck($condition, $message) { if (!$condition) throw new RuntimeException($message); }
$db = new Database();
try {
  $db->query('CREATE TEMPORARY TABLE shops (id INT PRIMARY KEY, account_id INT, name VARCHAR(100), sync_status VARCHAR(30))'); $db->exe();
  $db->query("CREATE TEMPORARY TABLE shop_performance_daily (shop_id INT, metric_date DATE, source VARCHAR(40), confirmed_gmv BIGINT NULL, confirmed_orders BIGINT NULL, shop_uv BIGINT NULL, shop_pv BIGINT NULL, product_clicks BIGINT NULL, PRIMARY KEY(shop_id,metric_date,source))"); $db->exe();
  $db->query("INSERT INTO shops VALUES (1,77,'Toko nol','connected'),(2,77,'Toko parsial','connected'),(3,77,'Toko kosong','expired'),(4,88,'Akun lain','connected')"); $db->exe();
  $db->query("INSERT INTO shop_performance_daily VALUES
    (1,'2026-09-29','homepage',100,2,10,20,5), (1,'2026-09-30','homepage',100,2,10,20,5),
    (1,'2026-10-01','homepage',0,0,10,20,0), (1,'2026-10-02','homepage',0,0,10,20,0),
    (2,'2026-09-29','homepage',100,2,10,20,5), (2,'2026-09-30','homepage',100,2,10,20,5),
    (2,'2026-10-01','homepage',100,NULL,10,20,5), (2,'2026-10-02','homepage',NULL,NULL,NULL,20,5),
    (4,'2026-10-01','homepage',999999,100,100,200,100)"); $db->exe();
  $model = new ReportDataTest($db);
  $zero = $model->summary('2026-10-02',1,'confirmed_gmv',77);
  reportCheck($zero['totals']['confirmed_gmv'] == 0 && $zero['totals']['confirmed_gmv'] !== null,'Verified zero remains zero');
  reportCheck($zero['totals']['growth_percent'] == -100 && $zero['totals']['conversion_rate'] == 0,'Complete zero sales and orders produce valid rates');
  reportCheck($zero['comparison_available'] && $zero['rows'][0]['coverage']['current']['confirmed_gmv']['complete'],'Both periods are complete');
  $partial = $model->summary('2026-10-02',2,'confirmed_gmv',77);
  reportCheck($partial['totals']['confirmed_gmv'] == 100,'Partial total retains known amounts');
  reportCheck($partial['totals']['confirmed_orders'] === null,'All-null metric stays unknown despite daily rows');
  reportCheck($partial['rows'][0]['current_days'] == 2 && $partial['rows'][0]['coverage']['current']['confirmed_gmv']['known_days'] === 1,'Coverage counts each metric independently');
  reportCheck($partial['totals']['growth_percent'] === null && !$partial['comparison_available'],'Complete previous period cannot validate an incomplete current period');
  reportCheck($partial['totals']['conversion_rate'] === null,'Incomplete orders or UV cannot produce a conversion rate');
  $empty = $model->summary('2026-10-02',3,'confirmed_gmv',77);
  reportCheck(count($empty['rows']) === 1 && $empty['rows'][0]['shop_id'] == 3,'Selected shop without snapshots remains visible');
  reportCheck($empty['totals']['confirmed_gmv'] === null && $empty['totals']['coverage']['current']['confirmed_gmv']['known_days'] === 0,'Missing data is not zero');
  $all = $model->summary('2026-10-02',0,'confirmed_gmv',77);
  reportCheck(count($all['rows']) === 3 && $all['totals']['confirmed_gmv'] == 100,'Totals exclude shops from another account');
  reportCheck($all['totals']['coverage']['current']['confirmed_gmv'] === ['known_days'=>3,'expected_days'=>6,'complete'=>false],'Missing shop contributes expected coverage without invented amounts');
  $series = $model->compare([1,2,4],'2026-10-02',77)['series'];
  reportCheck(count($series) === 2,'Comparison keeps account scope');
  reportCheck($series[0]['points'][0]['confirmed_gmv'] == 0 && $series[0]['points'][0]['confirmed_gmv'] !== null,'Chart preserves zero');
  reportCheck($series[1]['points'][1]['confirmed_gmv'] === null,'Chart preserves unknown');
  $db->query("UPDATE shop_performance_daily SET confirmed_gmv=NULL WHERE shop_id=1 AND metric_date='2026-09-29'"); $db->exe();
  reportCheck($model->summary('2026-10-02',1,'confirmed_gmv',77)['totals']['growth_percent'] === null,'Incomplete previous metric also blocks growth');
  $none = $model->summary('2026-10-02',0,'confirmed_gmv',999);
  reportCheck($none['rows'] === [] && $none['totals']['confirmed_gmv'] === null,'Empty account has no fabricated total');
  echo "PASS: Reports null/zero, metric coverage, comparable periods, chart gaps and account scope\n";
} finally {
  foreach (['shop_performance_daily','shops'] as $table) { $db->query("DROP TEMPORARY TABLE IF EXISTS {$table}"); $db->exe(); }
}
