<?php
chdir(__DIR__ . '/../public');
require '../app/init.php';
require '../app/helpers/ProductSynchronizer.php';
$db = new Database();
$checks = 0;
function checkProduct($value, $message) {
  global $checks;
  $checks++;
  if (!$value) throw new RuntimeException($message);
}
function productSql($sql) {
  global $db;
  $db->query($sql);
  return $db->getAll();
}
class ProductTestWriter extends BaseModel {
  public $writes = 0;
  public function __construct($db, $table) { $this->db = $db; $this->table = $table; }
  public function update($id, $data = []) { $this->writes++; return parent::update($id, $data); }
}
class ProductTestSource {
  public $pages = [];
  public $cursors = [];
  public function getProductsPage($cookie, $size, $cursor) {
    $this->cursors[] = $cursor;
    if (!$this->pages) throw new RuntimeException('Unexpected API call');
    return array_shift($this->pages);
  }
}
class ProductTestAlerts {
  public $calls = 0;
  public function reconcileShop($id) { $this->calls++; return 0; }
}
function productPage($id, $cursor, $total = 3) {
  return ['products' => [['id' => $id, 'name' => 'Fixture', 'model_list' => [['id' => $id + 100, 'name' => 'Variant']]]],
    'page_info' => ['cursor' => $cursor, 'total' => $total]];
}
$tables = ['products', 'product_models', 'shops', 'sync_checkpoints'];
try {
  foreach ($tables as $table) {
    $schema = productSql("SHOW CREATE TABLE {$table}")[0]['Create Table'];
    $schema = preg_replace('/^CREATE TABLE/', 'CREATE TEMPORARY TABLE', $schema);
    $schema = preg_replace('/^\s*CONSTRAINT.*\n/m', '', $schema);
    $db->query(str_replace(",\n)", "\n)", $schema)); $db->exe();
  }
  $source = new ProductTestSource();
  $products = new ProductTestWriter($db, 'products');
  $models = new ProductTestWriter($db, 'product_models');
  $alerts = new ProductTestAlerts();
  $sync = new ProductSynchronizer($db, $source, $products, $models, $alerts);
  $shop = ['id' => 1, 'cookie' => 'fixture'];
  $source->pages = [productPage(11, 'next1'), productPage(12, 'next2'), productPage(13, '')];
  $result = $sync->run($shop, 'full', 2, 0);
  checkProduct($result['ok'] && !$result['complete'], 'Two-page turn remains incomplete');
  checkProduct(count($source->pages) === 1 && $alerts->calls === 0, 'No extra page or final reconciliation in partial turn');
  checkProduct(count(productSql('SELECT id FROM products')) === 2, 'Partial pages saved');
  $result = $sync->run($shop, 'full', 2, 0);
  checkProduct($result['ok'] && $result['complete'], 'Resumed final page completes');
  checkProduct($source->cursors === ['', 'next1', 'next2'], 'Cursor resumes across worker turns including short pages');
  checkProduct(count(productSql('SELECT id FROM products')) === 3, 'Cleanup preserves products from earlier turns');
  checkProduct(count(productSql('SELECT id FROM product_models')) === 3, 'Cleanup preserves variants from earlier turns');
  checkProduct($alerts->calls === 1, 'Alerts reconcile only at completion');
  $source->pages = [productPage(11, 'next1'), productPage(12, 'next2'), productPage(13, '')];
  $sync->run($shop, 'diff', 2, 0);
  $sync->run($shop, 'diff', 2, 0);
  checkProduct($products->writes === 0 && $models->writes === 0, 'Unchanged data avoids redundant writes');
  $source->pages = [productPage(11, 'next1'), false];
  checkProduct(!$sync->run($shop, 'full', 2, 0)['ok'], 'Source failure is not success');
  checkProduct(count(productSql('SELECT id FROM products')) === 3, 'Failed traversal does not delete previous products');
  $source->pages = [productPage(12, 'next1')];
  checkProduct(!$sync->run($shop, 'full', 2, 0)['ok'], 'Repeated cursor fails without looping');
  productSql('DELETE FROM sync_checkpoints');
  $source->pages = [['products' => [], 'page_info' => []]];
  checkProduct(!$sync->run($shop, 'full', 2, 0)['ok'], 'Unverified empty response cannot erase catalog');
  $source->pages = [productPage(11, '', 99)];
  checkProduct(!$sync->run($shop, 'full', 2, 0)['ok'], 'Missing products prevent successful reconciliation');
  checkProduct(count(productSql('SELECT id FROM products')) === 3, 'Total mismatch keeps existing catalog');
  echo "PASS: {$checks} product synchronization checks\n";
} finally {
  foreach (array_reverse($tables) as $table) { $db->query("DROP TEMPORARY TABLE IF EXISTS {$table}"); $db->exe(); }
}
