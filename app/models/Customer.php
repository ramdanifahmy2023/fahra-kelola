<?php

class Customer extends BaseModel {
  protected $table = 'customers';

  /**
   * A customer row is shared across shops, so every aggregate must scope both
   * the customer mapping and the order join to the current account.  Worker
   * jobs and the existing test fixtures have no authenticated account; those
   * callers intentionally retain their historical global scope.
   */
  private function accountId(): int {
    return function_exists('authAccountId') ? max(0, (int)authAccountId()) : 0;
  }

  private function customerAccountScope(string $alias = 'customers'): string {
    $account = $this->accountId();
    if ($account < 1) return '';
    return " AND (EXISTS (SELECT 1 FROM customer_shops scoped_customer_shops INNER JOIN shops scoped_customer_shop ON scoped_customer_shop.id = scoped_customer_shops.shop_id AND scoped_customer_shop.account_id = {$account} WHERE scoped_customer_shops.customer_id = {$alias}.id) OR EXISTS (SELECT 1 FROM orders scoped_customer_orders INNER JOIN shops scoped_customer_order_shop ON scoped_customer_order_shop.id = scoped_customer_orders.shop_id AND scoped_customer_order_shop.account_id = {$account} WHERE scoped_customer_orders.buyer_username = {$alias}.username))";
  }

  private function orderAccountScope(string $alias = 'orders'): string {
    $account = $this->accountId();
    if ($account < 1) return '';
    return " AND EXISTS (SELECT 1 FROM shops scoped_order_shop WHERE scoped_order_shop.id = {$alias}.shop_id AND scoped_order_shop.account_id = {$account})";
  }

  private function shopFilter(int $shopId): string {
    $conditions = [];
    if ($shopId > 0) {
      $conditions[] = "(EXISTS (SELECT 1 FROM customer_shops cs WHERE cs.customer_id = customers.id AND cs.shop_id = {$shopId}) OR EXISTS (SELECT 1 FROM orders scoped_shop_orders WHERE scoped_shop_orders.buyer_username = customers.username AND scoped_shop_orders.shop_id = {$shopId}))";
      $account = $this->accountId();
      if ($account > 0) $conditions[] = "EXISTS (SELECT 1 FROM shops selected_customer_shop WHERE selected_customer_shop.id = {$shopId} AND selected_customer_shop.account_id = {$account})";
    }
    $scope = $this->customerAccountScope();
    if ($scope !== '') $conditions[] = ltrim($scope, ' AND');
    return $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';
  }

  public function countForShop(int $shopId = 0) {
    $this->db->query('SELECT COUNT(*) AS total FROM customers' . $this->shopFilter($shopId));
    return (int)$this->db->single()['total'];
  }

  public function findWithOrderStats($limit = 10, $offset = 0, int $shopId = 0, int $customerId = 0) {
    $limit = (int)$limit;
    $offset = (int)$offset;
    $orderScope = $shopId > 0 ? " AND orders.shop_id = {$shopId}" : '';
    $orderScope .= $this->orderAccountScope('orders');
    $where=$this->shopFilter($shopId);
    if ($customerId>0) $where.=($where?' AND ':' WHERE ').'customers.id='.(int)$customerId;
    $this->db->query("SELECT customers.*, COUNT(DISTINCT orders.id) AS total_orders, COUNT(order_items.id) AS total_items FROM customers LEFT JOIN orders ON orders.buyer_username = customers.username{$orderScope} LEFT JOIN order_items ON order_items.order_id = orders.id" . $where . " GROUP BY customers.id, customers.username, customers.address, customers.created_at ORDER BY customers.created_at DESC, customers.id DESC LIMIT {$limit} OFFSET {$offset}");
    return $this->db->getAll();
  }

  public function findOrderHistory($username, int $shopId = 0) {
    $orderScope = $shopId > 0 ? " AND orders.shop_id = {$shopId}" : '';
    $orderScope .= $this->orderAccountScope('orders');
    $this->db->query("SELECT orders.id, orders.order_sn, orders.status_type, orders.total_price, orders.created_at, order_items.name AS item_name, order_items.variation_name, order_items.quantity, order_items.image AS item_image FROM orders LEFT JOIN order_items ON order_items.order_id = orders.id WHERE orders.buyer_username = :username{$orderScope} ORDER BY orders.created_at DESC, order_items.id ASC");
    $this->db->bind('username', $username);
    return $this->db->getAll();
  }

  public function syncFromOrders(?int $accountId = null, ?int $shopId = null, int $limit = 5000) {
    $joins = [];
    $where = ["COALESCE(buyer_username, '') <> ''"];
    if ($accountId !== null && $accountId > 0) {
      $joins[] = 'INNER JOIN shops scoped_shops ON scoped_shops.id = orders.shop_id AND scoped_shops.account_id = :account_id';
    }
    if ($shopId !== null && $shopId > 0) $where[] = 'orders.shop_id = :sync_shop_id';
    $limit = max(100, min(20000, $limit));
    $this->db->query("SELECT buyer_username, shipping_address, created_at, shop_id AS sync_shop_id FROM orders " . implode(' ', $joins) . ' WHERE ' . implode(' AND ', $where) . " ORDER BY created_at DESC LIMIT {$limit}");
    if ($accountId !== null && $accountId > 0) $this->db->bind('account_id', $accountId);
    if ($shopId !== null && $shopId > 0) $this->db->bind('sync_shop_id', $shopId);
    $orders = $this->db->getAll();
    $customers = [];

    foreach ($orders as $order) {
      $username = trim($order['buyer_username'] ?? '');
      $identity = 'username:' . strtolower($username);
      if (!isset($customers[$identity])) {
        $customers[$identity] = [
          'username' => $username,
          'address' => trim($order['shipping_address'] ?? ''),
          'created_at' => $order['created_at'] ?: date('Y-m-d H:i:s'),
          'shop_ids' => []
        ];
      }
      $sourceShopId = (int)($order['sync_shop_id'] ?? 0);
      if ($sourceShopId > 0) $customers[$identity]['shop_ids'][$sourceShopId] = true;
    }

    $existingCustomers = [];
    $existingRows = $this->findAll();
    if ($accountId !== null && $accountId > 0) {
      // Avoid updating a similarly named global customer row owned by another
      // account when customer_shops has not been populated yet.
      $this->db->query("SELECT c.* FROM customers c WHERE EXISTS (SELECT 1 FROM customer_shops cs INNER JOIN shops s ON s.id = cs.shop_id AND s.account_id = :existing_account WHERE cs.customer_id = c.id) OR EXISTS (SELECT 1 FROM orders o INNER JOIN shops os ON os.id = o.shop_id AND os.account_id = :existing_order_account WHERE o.buyer_username = c.username)");
      $this->db->bind('existing_account', $accountId);
      $this->db->bind('existing_order_account', $accountId);
      $existingRows = $this->db->getAll();
    }
    foreach ($existingRows as $customer) {
      $username = trim($customer['username'] ?? '');
      $identity = 'username:' . strtolower($username);
      if ($username !== '') $existingCustomers[$identity] = $customer;
    }

    $added = 0;
    $updated = 0;
    foreach ($customers as $identity => $customer) {
      $shopIds = array_keys($customer['shop_ids'] ?? []);
      unset($customer['shop_ids']);
      if (isset($existingCustomers[$identity])) {
        unset($customer['created_at']);
        $this->update($existingCustomers[$identity]['id'], $customer);
        $customerId = (int)$existingCustomers[$identity]['id'];
        $updated++;
      } else {
        $this->insert($customer);
        $this->db->query('SELECT id FROM customers WHERE username = :username ORDER BY id DESC LIMIT 1');
        $this->db->bind('username', $customer['username']);
        $customerId = (int)($this->db->single()['id'] ?? 0);
        $added++;
      }
      if ($customerId > 0) foreach ($shopIds as $mappedShopId) {
        $this->db->query('INSERT INTO customer_shops (customer_id, shop_id, first_seen_at, last_seen_at) VALUES (:customer_id, :shop_id, NOW(), NOW()) ON DUPLICATE KEY UPDATE last_seen_at = NOW()');
        $this->db->bind('customer_id', $customerId);
        $this->db->bind('shop_id', (int)$mappedShopId);
        $this->db->exe();
      }
    }

    return ['added' => $added, 'updated' => $updated];
  }
}
