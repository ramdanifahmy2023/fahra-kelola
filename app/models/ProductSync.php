<?php

class ProductSync extends BaseModel {
  public function run(array $shop, $mode = 'diff', $maxPages = 2, $rateMs = 350) {
    require_once __DIR__ . '/../helpers/ProductSynchronizer.php';
    require_once __DIR__ . '/ShopeeCurl.php';
    require_once __DIR__ . '/Product.php';
    require_once __DIR__ . '/ProductModel.php';
    require_once __DIR__ . '/StockAlert.php';
    return (new ProductSynchronizer($this->db, new ShopeeCurl(), new Product(), new ProductModel(), new StockAlert()))
      ->run($shop, $mode, $maxPages, $rateMs);
  }
}
