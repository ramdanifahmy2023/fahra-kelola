<?php

class ProductModel extends BaseModel {
    protected $table = 'product_models';

  public function forProduct(int $shopId,int $productId): array {
    $this->db->query('SELECT m.id,m.product_id,m.shop_id,m.name,m.sku,m.deleted_at FROM product_models m JOIN products p ON p.id=m.product_id AND p.shop_id=:shop_id WHERE m.product_id=:product_id AND m.deleted_at IS NULL ORDER BY m.is_default DESC,m.name,m.id');
    $this->db->bind('shop_id',$shopId);
    $this->db->bind('product_id',$productId);
    return $this->db->getAll();
  }
}
