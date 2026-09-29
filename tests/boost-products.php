<?php
chdir(__DIR__ . '/../public');
require '../app/init.php';
require '../app/models/Product.php';

class BoostProductFixture extends Product {
    public function __construct($db) {
        $this->db = $db;
        $this->table = 'boost_product_fixture';
    }
}

$db = new Database();
$db->query('CREATE TEMPORARY TABLE boost_product_fixture LIKE products');
$db->exe();
try {
    for ($id = 1; $id <= 14; $id++) {
        $db->query("INSERT INTO boost_product_fixture (id, shop_id, name, status, sold_count, deleted_at) VALUES (:id, :shop, 'Produk uji', :status, :sold, :deleted)");
        $db->bind('id', $id);
        $db->bind('shop', $id === 14 ? 2 : 1);
        $db->bind('status', $id === 13 ? 0 : 1);
        $db->bind('sold', $id * 10);
        $db->bind('deleted', $id === 12 ? '2026-09-01 00:00:00' : null);
        $db->exe();
    }
    $model = new BoostProductFixture($db);
    $ids = array_map('intval', array_column($model->findForBoost(1, '', 10, 0), 'id'));
    if ($ids !== range(11, 2)) throw new RuntimeException('Top ten must be sorted by sales and exclude other shops, deleted and inactive products');
    if ($model->countForBoost(1) !== 11) throw new RuntimeException('Count must use the same active-product filter');
    if ($model->findForBoost(3, '', 10, 0) !== []) throw new RuntimeException('Empty shop must return no products');
    echo "PASS: top ten sales order, shop isolation, active/deleted filters, count, and empty shop\n";
} finally {
    $db->query('DROP TEMPORARY TABLE boost_product_fixture');
    $db->exe();
}
