<?php
class QuickSearch extends BaseModel {
  public function lookup(string $query,int $shop=0): array {
    $query=trim($query);if (mb_strlen($query)<2 || mb_strlen($query)>100) return [];
    $like='%'.str_replace(['!','%','_'],['!!','!%','!_'],$query).'%';
    $scope=$shop>0?' AND s.id=:shop':'';$result=[];
    $queries=[
      'order'=>"SELECT o.id,o.shop_id,o.order_sn title,o.status_type detail,s.name shop_name,s.shop_logo FROM orders o JOIN shops s ON s.id=o.shop_id WHERE o.deleted_at IS NULL AND (o.order_sn LIKE :q1 ESCAPE '!' OR o.tracking_number LIKE :q2 ESCAPE '!'){$scope} ORDER BY (o.order_sn=:exact) DESC,o.created_at DESC LIMIT 6",
      'product'=>"SELECT p.id,p.shop_id,p.name title,p.parent_sku detail,s.name shop_name,s.shop_logo FROM products p JOIN shops s ON s.id=p.shop_id WHERE p.deleted_at IS NULL AND (p.name LIKE :q1 ESCAPE '!' OR p.parent_sku LIKE :q2 ESCAPE '!' OR EXISTS (SELECT 1 FROM product_models m WHERE m.product_id=p.id AND (m.shop_id=p.shop_id OR m.shop_id IS NULL) AND m.deleted_at IS NULL AND m.sku LIKE :q3 ESCAPE '!')){$scope} ORDER BY (p.parent_sku=:exact) DESC,p.id DESC LIMIT 6",
      'customer'=>"SELECT DISTINCT c.id,s.id shop_id,c.username title,'Riwayat pesanan' detail,s.name shop_name,s.shop_logo FROM customers c JOIN shops s ON EXISTS (SELECT 1 FROM customer_shops cs WHERE cs.customer_id=c.id AND cs.shop_id=s.id) OR EXISTS (SELECT 1 FROM orders o WHERE o.shop_id=s.id AND o.buyer_username=c.username) WHERE c.username LIKE :q1 ESCAPE '!'{$scope} ORDER BY c.username,s.id LIMIT 6"
    ];
    foreach ($queries as $type=>$sql) {
      $this->db->query($sql);$this->db->bind('q1',$like);if ($type!=='customer') {$this->db->bind('q2',$like);$this->db->bind('exact',$query);}if ($type==='product') $this->db->bind('q3',$like);if ($shop>0) $this->db->bind('shop',$shop);
      foreach ($this->db->getAll() as $row) {
        $path=$type==='order'?'/panel/orders?shop_id='.$row['shop_id'].'&order_id='.$row['id'] : ($type==='product'?'/panel/products?shop_id='.$row['shop_id'].'&highlight='.$row['id'] : '/panel/customers?shop_id='.$row['shop_id'].'&customer_id='.$row['id']);
        $result[]=['id'=>(string)$row['id'],'shop_id'=>(int)$row['shop_id'],'type'=>$type,'title'=>$row['title'],'detail'=>$row['detail'],'shop_name'=>$row['shop_name'],'shop_logo'=>$row['shop_logo'],'path'=>$path];
      }
    }
    return $result;
  }
}
