<?php

class Customer extends BaseModel {
  protected $table = 'customers';

  private function shopFilter(int $shopId): string {
    return $shopId > 0 ? " WHERE (EXISTS (SELECT 1 FROM customer_shops cs WHERE cs.customer_id = customers.id AND cs.shop_id = {$shopId}) OR EXISTS (SELECT 1 FROM orders o WHERE o.buyer_username = customers.username AND o.shop_id = {$shopId}))" : '';
  }

  public function countForShop(int $shopId = 0) {
    $this->db->query('SELECT COUNT(*) AS total FROM customers' . $this->shopFilter($shopId));
    return (int)$this->db->single()['total'];
  }

  public function findWithOrderStats($limit = 10, $offset = 0, int $shopId = 0, int $customerId = 0) {
    $limit = (int)$limit;
    $offset = (int)$offset;
    $orderScope = $shopId > 0 ? " AND orders.shop_id = {$shopId}" : '';
    $where=$this->shopFilter($shopId);
    if ($customerId>0) $where.=($where?' AND ':' WHERE ').'customers.id='.(int)$customerId;
    $this->db->query("SELECT customers.*, COUNT(DISTINCT orders.id) AS total_orders, COUNT(order_items.id) AS total_items FROM customers LEFT JOIN orders ON orders.buyer_username = customers.username{$orderScope} LEFT JOIN order_items ON order_items.order_id = orders.id" . $where . " GROUP BY customers.id, customers.username, customers.address, customers.created_at ORDER BY customers.created_at DESC, customers.id DESC LIMIT {$limit} OFFSET {$offset}");
    return $this->db->getAll();
  }

  public function findOrderHistory($username, int $shopId = 0) {
    $orderScope = $shopId > 0 ? " AND orders.shop_id = {$shopId}" : '';
    $this->db->query("SELECT orders.id, orders.order_sn, orders.status_type, orders.total_price, orders.created_at, order_items.name AS item_name, order_items.variation_name, order_items.quantity, order_items.image AS item_image FROM orders LEFT JOIN order_items ON order_items.order_id = orders.id WHERE orders.buyer_username = :username{$orderScope} ORDER BY orders.created_at DESC, order_items.id ASC");
    $this->db->bind('username', $username);
    return $this->db->getAll();
  }

  public function syncFromOrders() {
    $this->db->query("SELECT buyer_username, shipping_address, created_at FROM orders WHERE COALESCE(buyer_username, '') <> '' ORDER BY created_at DESC");
    $orders = $this->db->getAll();
    $customers = [];

    foreach ($orders as $order) {
      $username = trim($order['buyer_username'] ?? '');
      $identity = 'username:' . strtolower($username);
      if (isset($customers[$identity])) continue;

      $customers[$identity] = [
        'username' => $username,
        'address' => trim($order['shipping_address'] ?? ''),
        'created_at' => $order['created_at'] ?: date('Y-m-d H:i:s')
      ];
    }

    $existingCustomers = [];
    foreach ($this->findAll() as $customer) {
      $username = trim($customer['username'] ?? '');
      $identity = 'username:' . strtolower($username);
      if ($username !== '') $existingCustomers[$identity] = $customer;
    }

    $added = 0;
    $updated = 0;
    foreach ($customers as $identity => $customer) {
      if (isset($existingCustomers[$identity])) {
        unset($customer['created_at']);
        $this->update($existingCustomers[$identity]['id'], $customer);
        $updated++;
      } else {
        $this->insert($customer);
        $added++;
      }
    }

    return ['added' => $added, 'updated' => $updated];
  }
}
