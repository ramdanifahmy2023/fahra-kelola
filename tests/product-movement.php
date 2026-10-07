<?php
chdir(__DIR__.'/../public'); require '../app/init.php'; require '../app/models/Product.php';
class ProductMovementFixture extends Product { public function __construct($db) { $this->db=$db; } }
function movementCheck($value,$message){if(!$value)throw new RuntimeException($message);}
$db=new Database(); $model=new ProductMovementFixture($db); $exec=function($sql)use($db){$db->query($sql);$db->exe();};
$tables=['shops','products','orders','order_items'];
try {
  foreach($tables as $table){$db->query('SHOW CREATE TABLE '.$table);$schema=$db->single()['Create Table'];$schema=preg_replace('/^CREATE TABLE/','CREATE TEMPORARY TABLE',$schema);$schema=preg_replace('/^\s*CONSTRAINT.*\n/m','',$schema);$exec(str_replace(",\n)","\n)",$schema));}
  $exec("INSERT INTO shops(id,shop_id,name,sync_status) VALUES(1,101,'One','connected'),(2,202,'Two','connected')");
  $exec("INSERT INTO products(id,shop_id,name,status,total_stock,sold_count) VALUES(10,1,'Fast',1,2,99),(20,1,'Slow',1,50,20),(30,1,'Dead',1,12,10),(40,2,'Other',1,99,2)");
  $exec("INSERT INTO orders(id,shop_id,order_sn,status_type,created_at) VALUES(1,1,'A','Completed','2026-09-15 10:00:00'),(2,1,'B','Delivered','2026-09-20 10:00:00'),(3,1,'C','Cancelled','2026-09-20 10:00:00'),(4,2,'D','Completed','2026-09-20 10:00:00'),(5,1,'E','Selesai','2026-09-21 10:00:00'),(6,1,'F','Pesanan diterima','2026-09-22 10:00:00')");
  $exec("INSERT INTO order_items(order_id,product_id,quantity) VALUES(1,10,10),(2,20,1),(3,30,5),(4,40,8),(5,10,2),(6,20,3)");
  $result=$model->movementSummary(1,'2026-09-01','2026-09-30'); $rows=[]; foreach($result['rows'] as $row)$rows[$row['id']]=$row;
  movementCheck((int)$rows[10]['units_sold']===12,'Completed and Indonesian completed sales counted'); movementCheck((int)$rows[20]['units_sold']===4,'Delivered and Indonesian received sales counted'); movementCheck((int)$rows[30]['units_sold']===0,'Cancelled sales excluded'); movementCheck(isset($rows[30])&&$rows[30]['movement_key']==='dead','Dead stock classification'); movementCheck(!isset($rows[40]),'Other shops excluded'); movementCheck((int)$result['days']===30,'Period uses inclusive day count');
} finally {foreach(array_reverse($tables) as $table)$exec('DROP TEMPORARY TABLE IF EXISTS '.$table);}
echo "PASS: movement period, shop scope, completed/delivered sales, cancelled exclusion, and dead-stock classification\n";
