<?php
require_once __DIR__.'/../helpers/FinancePolicy.php';

class Dashboard extends BaseModel {
  private function rows(string $sql, array $params = []): array {
    $this->db->query($sql);
    foreach ($params as $key=>$value) $this->db->bind($key,$value);
    return $this->db->getAll();
  }

  public function overview(array $shops, array $range): array {
    $ids=implode(',',array_map('intval',array_column($shops,'id'))) ?: '0';
    $utc=new DateTimeZone('UTC');
    $params=['start'=>FinancePolicy::date($range['start'])->setTimezone($utc)->format('Y-m-d H:i:s'),
      'end'=>FinancePolicy::date($range['end'])->modify('+1 day')->setTimezone($utc)->format('Y-m-d H:i:s')];
    $scope="shop_id IN ($ids) AND deleted_at IS NULL";
    $orders=$this->rows("SELECT COUNT(*) total_orders,
      COALESCE(SUM(status_type LIKE '%Completed%'),0) completed_orders,
      CASE WHEN COALESCE(SUM(status_type LIKE '%Completed%'),0)=0 THEN 0
        ELSE SUM(CASE WHEN status_type LIKE '%Completed%' THEN total_price END) END completed_order_value,
      COALESCE(SUM(status_type LIKE '%Completed%' AND total_price IS NULL),0) completed_missing_value,
      COALESCE(SUM(order_sn IS NULL OR order_sn=''),0) pending_order_details
      FROM orders WHERE $scope AND created_at>=:start AND created_at<:end",$params)[0];
    $undated=$this->rows("SELECT COUNT(*) count FROM orders WHERE $scope AND created_at IS NULL")[0]['count'];
    $products=$this->rows("SELECT COUNT(*) total_products,
      COALESCE(SUM(status=1 AND total_stock=0),0) stock_out_count,
      COALESCE(SUM(status=1 AND total_stock>0 AND total_stock<15),0) stock_low_count
      FROM products WHERE $scope")[0];
    $mapped=$this->rows("SELECT c.id FROM customer_shops cs JOIN customers c ON c.id=cs.customer_id WHERE cs.shop_id IN ($ids)");
    // Materialize the selected buyers once; a correlated lookup scans orders for every customer.
    $buyers=$this->rows("SELECT c.id FROM (SELECT DISTINCT buyer_username FROM orders
      WHERE shop_id IN ($ids) AND deleted_at IS NULL AND buyer_username<>'') b JOIN customers c ON c.username=b.buyer_username");
    $customers=count(array_unique(array_merge(array_column($mapped,'id'),array_column($buyers,'id'))));
    $health=$this->rows("SELECT id,name,sync_status,last_successful_sync_at FROM shops WHERE id IN ($ids) ORDER BY name,id");
    $stock=$this->rows("SELECT p.id,p.shop_id,p.name,p.total_stock,s.name shop_name FROM products p JOIN shops s ON s.id=p.shop_id
      WHERE p.shop_id IN ($ids) AND p.deleted_at IS NULL AND p.status=1 AND p.total_stock<15 ORDER BY p.total_stock,p.name LIMIT 10");
    $recent=$this->rows("SELECT o.id,o.shop_id,o.order_sn,o.status_type,o.total_price,o.created_at,s.name shop_name FROM orders o
      JOIN shops s ON s.id=o.shop_id WHERE o.shop_id IN ($ids) AND o.deleted_at IS NULL AND o.created_at>=:start AND o.created_at<:end
      ORDER BY o.created_at DESC,o.id DESC LIMIT 8",$params);
    return ['range'=>$range,'summary'=>$orders+$products+['undated_orders'=>(int)$undated,'total_customers'=>(int)$customers],
      'shops'=>$health,'low_stock'=>$stock,'recent_orders'=>$recent];
  }
}
