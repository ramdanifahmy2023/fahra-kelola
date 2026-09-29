<?php
chdir(__DIR__ . '/../public');
require '../app/init.php';
require '../app/models/Customer.php';
$db = new Database();
class CustomerShopTest extends Customer {
  public function __construct($db) { $this->db = $db; }
}
function customerCheck($condition, $message) {
  if (!$condition) throw new RuntimeException($message);
}
$tables = ['customers', 'customer_shops', 'orders', 'order_items'];
try {
  foreach ($tables as $table) {
    $db->query("SHOW CREATE TABLE {$table}");
    $schema = $db->single()['Create Table'];
    $schema = preg_replace('/^CREATE TABLE/', 'CREATE TEMPORARY TABLE', $schema);
    $schema = preg_replace('/^\s*CONSTRAINT.*\n/m', '', $schema);
    $db->query(str_replace(",\n)", "\n)", $schema));
    $db->exe();
  }
  $statements = [
    "INSERT INTO customers (id,username,address,created_at) VALUES (1,'shared','A','2026-09-01'),(2,'only-two','B','2026-09-02'),(3,'mapped-only','C','2026-09-03')",
    "INSERT INTO customer_shops (customer_id,shop_id) VALUES (1,1),(1,2),(2,2),(3,1)",
    "INSERT INTO orders (id,shop_id,buyer_username,order_sn,created_at) VALUES (1,1,'shared','TEST-1','2026-09-01'),(2,2,'shared','TEST-2','2026-09-02'),(3,2,'only-two','TEST-3','2026-09-03')"
  ];
  foreach ($statements as $sql) { $db->query($sql); $db->exe(); }
  $model = new CustomerShopTest($db);
  customerCheck($model->countForShop() === 3, 'All shops includes all customers');
  customerCheck($model->countForShop(1) === 2, 'Mapping and order matches are not double-counted');
  customerCheck($model->countForShop(2) === 2, 'Second shop has its own customers');
  customerCheck($model->countForShop(99) === 0, 'Unknown shop is empty');
  $rows = $model->findWithOrderStats(10, 0, 1);
  customerCheck(array_column($rows, 'username') === ['mapped-only', 'shared'], 'Selected shop list is scoped');
  customerCheck((int)$rows[1]['total_orders'] === 1, 'Order counts exclude other shops');
  customerCheck(count($model->findOrderHistory('shared', 1)) === 1, 'History excludes other shops');
  customerCheck(count($model->findOrderHistory('shared')) === 2, 'All-shop history is preserved');
  customerCheck(count($model->findWithOrderStats(1, 1, 1)) === 1, 'Pagination remains scoped');
  $db->query('DELETE FROM customer_shops WHERE customer_id = 1'); $db->exe();
  customerCheck($model->countForShop(1) === 2, 'Customers from orders work before mapping catches up');
  echo "PASS: customer shop membership, counts, history, pagination, and all-shop view\n";
} finally {
  foreach (array_reverse($tables) as $table) {
    $db->query("DROP TEMPORARY TABLE IF EXISTS {$table}"); $db->exe();
  }
}
