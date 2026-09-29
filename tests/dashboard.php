<?php
chdir(__DIR__.'/../public');
require '../app/init.php';
require '../app/models/Dashboard.php';
require '../app/helpers/DashboardMetrics.php';
class DashboardFixture extends Dashboard { public function __construct($db) { $this->db=$db; } }
function dashboardCheck($value,$message) { if (!$value) throw new RuntimeException($message); }
$db=new Database(); $model=new DashboardFixture($db);
$execute=function($sql) use($db) {$db->query($sql);$db->exe();};
$tables=['shops','orders','products','customers','customer_shops'];
try {
  foreach ($tables as $table) {
    $db->query('SHOW CREATE TABLE '.$table); $schema=$db->single()['Create Table'];
    $schema=preg_replace('/^CREATE TABLE/','CREATE TEMPORARY TABLE',$schema);
    $schema=preg_replace('/^\s*CONSTRAINT.*\n/m','',$schema);
    $execute(str_replace(",\n)","\n)",$schema));
  }
  $execute("INSERT INTO shops(id,shop_id,name,sync_status) VALUES(1,101,'One','connected'),(2,202,'Two','connected')");
  $execute("INSERT INTO products(id,shop_id,name,status,total_stock) VALUES(10,1,'One item',1,0),(20,2,'Other shop',1,9)");
  $execute("INSERT INTO customers(id,username) VALUES(1,'shared'),(2,'shop-two')");
  $execute("INSERT INTO customer_shops(customer_id,shop_id) VALUES(1,1),(1,2),(2,2)");
  $execute("INSERT INTO orders(id,shop_id,order_sn,buyer_username,status_type,total_price,created_at) VALUES
    (1,1,'BEFORE','shared','Completed',999,'2026-08-31 16:59:59'),
    (2,1,'START','shared','Completed',100,'2026-08-31 17:00:00'),
    (3,1,'END','shared','Completed',NULL,'2026-09-01 16:59:59'),
    (4,1,'AFTER','shared','Completed',999,'2026-09-01 17:00:00'),
    (5,2,'OTHER','shop-two','Completed',200,'2026-09-01 10:00:00'),
    (6,1,'UNKNOWN','shared',NULL,NULL,NULL)");
  $range=FinancePolicy::range('2026-09-01','2026-09-01');
  $one=$model->overview([['id'=>1]],$range);
  dashboardCheck((int)$one['summary']['total_orders']===2,'Inclusive WIB dates use UTC storage boundaries');
  dashboardCheck((float)$one['summary']['completed_order_value']===100.0,'Other shops and dates excluded');
  dashboardCheck((int)$one['summary']['completed_missing_value']===1,'Missing values remain disclosed');
  dashboardCheck((int)$one['summary']['undated_orders']===1,'Undated orders are not silently in range');
  dashboardCheck((int)$one['summary']['total_customers']===1,'Shared customers counted once per selection');
  dashboardCheck(array_column($one['low_stock'],'shop_id')===[1],'Stock respects selection');
  dashboardCheck(array_column($one['recent_orders'],'id')===[3,2],'Recent orders respect shop and period');
  $all=$model->overview([['id'=>1],['id'=>2]],$range);
  dashboardCheck((int)$all['summary']['total_orders']===3 && (int)$all['summary']['total_customers']===2,'All shops aggregate without duplicating shared customer');
  dashboardCheck((int)$model->overview([],$range)['summary']['total_products']===0,'No shops does not accidentally select every shop');
} finally {foreach(array_reverse($tables) as $table)$execute('DROP TEMPORARY TABLE IF EXISTS '.$table);}
$stores=[
  ['shop_id'=>1,'shop_name'=>'One','metrics'=>['key_metrics'=>['uv'=>0,'sales'=>100],'sales_hourly'=>[0,null,100],'top_sales_items'=>[['item_name'=>'Same name','sales'=>100]],'time'=>200]],
  ['shop_id'=>2,'shop_name'=>'Two','metrics'=>['key_metrics'=>['sales'=>200],'sales_hourly'=>[null,null,200],'top_sales_items'=>[['item_name'=>'Same name','sales'=>200]],'time'=>100]]
];
$m=DashboardMetrics::combine($stores,3);
dashboardCheck($m['key_metrics']['sales']===300.0 && $m['metric_coverage']['sales']===2,'Partial totals retain coverage');
dashboardCheck($m['key_metrics']['uv']===0.0 && $m['metric_coverage']['uv']===1,'Verified zero differs from missing');
dashboardCheck($m['key_metrics']['orders']===null,'Missing orders are not invented zero');
dashboardCheck($m['sales_hourly'][1]===null && $m['hourly_coverage'][1]===0,'Missing hour is not fabricated zero');
dashboardCheck(count($m['top_sales_items'])===2 && $m['top_sales_items'][0]['shop_id']===2,'Identical product names across shops stay separate');
dashboardCheck($m['time']===100,'Aggregate timestamp is oldest included source');
$stores[0]['metrics']['time']=null;
dashboardCheck(DashboardMetrics::combine($stores,2)['time']===null,'Missing source time is not replaced with now');
echo "PASS: dashboard shop/date scope, WIB boundaries, shared customers, missing values, hourly coverage, product identity, and source timestamps\n";
