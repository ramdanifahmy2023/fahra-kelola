<?php

class Dashboard extends BaseModel {
  protected $table = 'shops';

  public function summary() {
    $this->db->query("SELECT
      (SELECT COUNT(*) FROM shops) AS total_shops,
      (SELECT COUNT(*) FROM shops WHERE sync_status = 'connected') AS connected_shops,
      (SELECT COUNT(*) FROM products WHERE deleted_at IS NULL) AS total_products,
      (SELECT COUNT(*) FROM orders WHERE deleted_at IS NULL) AS total_orders,
      (SELECT COUNT(*) FROM orders WHERE deleted_at IS NULL AND (order_sn IS NULL OR order_sn = '')) AS pending_order_details,
      (SELECT COUNT(*) FROM orders WHERE deleted_at IS NULL AND status_type LIKE '%Completed%') AS completed_orders,
      (SELECT COALESCE(SUM(CASE WHEN status_type LIKE '%Completed%' THEN COALESCE(total_price, 0) ELSE 0 END), 0) FROM orders WHERE deleted_at IS NULL) AS completed_order_value,
      (SELECT COUNT(*) FROM customers) AS total_customers,
      (SELECT MAX(updated_at) FROM shops) AS shops_updated_at,
      (SELECT MAX(updated_at) FROM orders) AS orders_updated_at,
      (SELECT MAX(modify_time) FROM products WHERE deleted_at IS NULL) AS products_updated_at,
      (SELECT COUNT(*) FROM products WHERE deleted_at IS NULL AND status = 1 AND total_stock = 0) AS stock_out_count,
      (SELECT COUNT(*) FROM products WHERE deleted_at IS NULL AND status = 1 AND total_stock > 0 AND total_stock < 15) AS stock_low_count,
      (SELECT COUNT(*) FROM products WHERE deleted_at IS NULL AND status = 1 AND total_stock < 15) AS stock_critical_count");
    return $this->db->single();
  }

  public function shopHealth() {
    $this->db->query("SELECT
      shops.id,
      shops.name,
      shops.username,
      shops.sync_status,
      shops.total_products,
      shops.last_successful_sync_at,
      shops.last_sync_attempt_at,
      (SELECT COUNT(*) FROM products WHERE products.shop_id = shops.id AND products.deleted_at IS NULL) AS product_count,
      (SELECT COUNT(*) FROM orders WHERE orders.shop_id = shops.id AND orders.deleted_at IS NULL) AS order_count,
      (SELECT COUNT(*) FROM orders WHERE orders.shop_id = shops.id AND orders.deleted_at IS NULL AND (orders.order_sn IS NULL OR orders.order_sn = '')) AS pending_order_details
      FROM shops
      ORDER BY shops.name ASC");
    return $this->db->getAll();
  }

  public function recentOrders($limit = 8) {
    $limit = max(1, min(20, (int)$limit));
    $this->db->query("SELECT
      orders.id,
      orders.order_sn,
      orders.status_type,
      orders.total_price,
      orders.created_at,
      shops.name AS shop_name,
      CASE
        WHEN orders.order_sn IS NULL OR orders.order_sn = '' THEN 'Menunggu detail'
        WHEN orders.status_type IS NULL OR orders.status_type = '' THEN 'Status belum tersedia'
        ELSE orders.status_type
      END AS display_status
      FROM orders
      LEFT JOIN shops ON shops.id = orders.shop_id
      WHERE orders.deleted_at IS NULL
        AND (orders.order_sn IS NOT NULL OR orders.created_at IS NOT NULL)
      ORDER BY COALESCE(orders.created_at, orders.updated_at) DESC, orders.id DESC
      LIMIT {$limit}");
    return $this->db->getAll();
  }

  public function lowStockProducts($limit = 10) {
    $limit = max(1, min(50, (int)$limit));
    $this->db->query("SELECT p.id, p.shop_id, p.name, p.total_stock, s.name AS shop_name FROM products p LEFT JOIN shops s ON s.id = p.shop_id WHERE p.deleted_at IS NULL AND p.status = 1 AND p.total_stock < 15 ORDER BY p.total_stock ASC, p.name ASC LIMIT {$limit}");
    return $this->db->getAll();
  }

  public function lowStockByShop() {
    $this->db->query("SELECT s.id AS shop_id, s.name AS shop_name, COALESCE(SUM(p.total_stock = 0), 0) AS stock_out_count, COALESCE(SUM(p.total_stock > 0 AND p.total_stock < 15), 0) AS stock_low_count, COUNT(p.id) AS stock_critical_count FROM shops s LEFT JOIN products p ON p.shop_id = s.id AND p.deleted_at IS NULL AND p.status = 1 AND p.total_stock < 15 GROUP BY s.id, s.name ORDER BY s.name ASC");
    return $this->db->getAll();
  }
}
