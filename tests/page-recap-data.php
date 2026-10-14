<?php
chdir(__DIR__ . '/../public');
require '../app/init.php';
require '../app/models/Order.php';
require '../app/models/Product.php';
$db = new Database();
class RecapOrderTest extends Order { public function __construct($db) { $this->db = $db; } }
class RecapProductTest extends Product { public function __construct($db) { $this->db = $db; } }
function recapCheck($condition, $message) { if (!$condition) throw new RuntimeException($message); }
$tables = ['orders','products','product_models'];
try {
  $db->query('CREATE TEMPORARY TABLE orders (id INT PRIMARY KEY, shop_id INT NOT NULL, created_at DATETIME NULL, status_type VARCHAR(40) NULL, order_type VARCHAR(40) NULL)'); $db->exe();
  $db->query("INSERT INTO orders VALUES
    (1,1,'2026-10-01 03:00:00','unpaid','normal'),
    (2,1,'2026-10-02 03:00:00','shipped','normal'),
    (3,1,'2026-10-03 03:00:00','completed','normal'),
    (4,1,'2026-10-04 03:00:00','cancelled','normal'),
    (5,1,NULL,'','advance'),
    (6,1,'2026-10-05 03:00:00','unknown_status','normal'),
    (7,1,'2026-10-06 03:00:00','to_confirm_receive','normal'),
    (8,2,'2026-10-03 03:00:00','unpaid','normal')"); $db->exe();
  $orders = new RecapOrderTest($db);
  $summary = $orders->summaryByCreatedDateRange(1,'2026-10-01','2026-10-06');
  recapCheck($summary['total']===7 && $summary['dated']===6 && $summary['undated']===1,'Order totals retain undated legacy rows and separate dated coverage');
  recapCheck($summary['advance']===1 && $summary['waiting']===1 && $summary['processing']===1,'Order type and waiting/processing counts');
  recapCheck($summary['completed']===2 && $summary['cancelled']===1,'Completed aliases and cancellations are counted');
  recapCheck($summary['unknown']===1 && $summary['other']===1,'Blank and unrecognized statuses stay distinguishable');
  recapCheck($summary['trend']===['2026-10-01'=>1,'2026-10-02'=>1,'2026-10-03'=>1,'2026-10-04'=>1,'2026-10-05'=>1,'2026-10-06'=>1],'Daily trend excludes undated records and other shops');
  recapCheck((int)$orders->countByCreatedDateRange(1,'2026-10-01','2026-10-06','unknown')===2,'Unknown status filter matches the recap unknown plus other segment');
  recapCheck((int)$orders->countByCreatedDateRange(1,'2026-10-01','2026-10-06','advance')===1,'Advance filter includes an undated advance order');
  recapCheck($orders->countDatedByCreatedDateRange(1,'2026-10-01','2026-10-06')===6,'Comparison count excludes undated records');
  recapCheck(count($orders->findByCreatedDateRangePaginated(1,'2026-10-01','2026-10-06',10,0,'completed'))===2,'Completed filter returns every supported completed alias');

  $db->query('CREATE TEMPORARY TABLE products (id INT PRIMARY KEY, shop_id INT NOT NULL, status INT NULL, total_stock INT NULL, deleted_at DATETIME NULL)'); $db->exe();
  $db->query('CREATE TEMPORARY TABLE product_models (id INT PRIMARY KEY, product_id INT NOT NULL, sku VARCHAR(64) NULL, deleted_at DATETIME NULL)'); $db->exe();
  $db->query("INSERT INTO products VALUES
    (1,1,1,0,NULL),(2,1,1,-3,NULL),(3,1,1,5,NULL),(4,1,1,14,NULL),
    (5,1,1,15,NULL),(6,1,1,NULL,NULL),(7,1,2,4,NULL),(8,1,0,7,NULL),
    (9,1,1,1,'2026-10-10 00:00:00'),(10,2,1,2,NULL)"); $db->exe();
  $products = new RecapProductTest($db);
  $productSummary = $products->summaryForShop(1);
  recapCheck($productSummary===['total'=>8,'active'=>6,'empty'=>3,'low'=>2,'healthy'=>1,'unknown_stock'=>1,'archived'=>1,'active_stock'=>34],'Stock categories cover the selected shop and exclude deleted products');
  foreach (['critical'=>4,'empty'=>3,'low'=>2,'healthy'=>1,'unknown'=>1,'archived'=>1] as $filter=>$expected) {
    recapCheck($products->countPanel(1,$filter)===$expected,"Product count matches {$filter} filter");
  }
  recapCheck(array_column($products->findPanelPaginated(1,10,0,false,'low'),'id')===[4,3],'Low stock filter returns only active products with 1–14 units');
  recapCheck(array_column($products->findPanelPaginated(1,10,0,false,'empty'),'id')===[7,2,1],'Empty filter separates zero/negative stock and Shopee out-of-stock status');
  recapCheck($products->countPanel(2)===1,'Product recap remains scoped to the selected shop');
  echo "PASS: order status/type/date recap and product stock recap filters\n";
} finally {
  foreach (array_reverse($tables) as $table) { $db->query("DROP TEMPORARY TABLE IF EXISTS {$table}"); $db->exe(); }
}
