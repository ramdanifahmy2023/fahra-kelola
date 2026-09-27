<?php

class Panel extends Controller {
  // Index
  public function index() {
    $data['judul'] = 'Panel - ' . app_name;
    $data['active_menu'] = 'dashboard';
    $dashboard = $this->m('Dashboard');
    $data['summary'] = $dashboard->summary();
    $data['shop_health'] = $dashboard->shopHealth();
    $data['recent_orders'] = $dashboard->recentOrders();
    
    $this->v('panel/templates/header', $data);
    $this->v('panel/index', $data);
    $this->v('panel/templates/footer', $data);
  }

  // Shops
  public function shops() {
    $data['judul'] = 'Daftar Toko - ' . app_name;
    $data['active_menu'] = 'shops';
    
    $shops = $this->m('Shop')->findAll();
    $productModel = $this->m('Product');
    
    foreach ($shops as &$shop) {
        $shop['total_products'] = $productModel->countWhere(['shop_id' => $shop['id']]);
    }
    
    $data['shops'] = $shops;
    
    $this->v('panel/templates/header', $data);
    $this->v('panel/shops', $data);
    $this->v('panel/templates/footer', $data);
  }

  public function ads() {
    $data['judul'] = 'Monitoring Iklan - ' . app_name;
    $data['active_menu'] = 'ads';
    $data['shops'] = $this->m('Shop')->findAll();
    $this->v('panel/templates/header', $data);
    $this->v('panel/ads', $data);
    $this->v('panel/templates/footer', $data);
  }

  public function promotions() {
    $data['judul'] = 'Monitoring Promosi - ' . app_name;
    $data['active_menu'] = 'promotions';
    $data['shops'] = $this->m('Shop')->findAll();
    $this->v('panel/templates/header', $data);
    $this->v('panel/promotions', $data);
    $this->v('panel/templates/footer', $data);
  }

  public function chat() {
    $data['judul'] = 'Live Chat - ' . app_name;
    $data['active_menu'] = 'chat';
    $data['shops'] = $this->m('Shop')->findAll();
    $data['active_shop_id'] = (int)($_GET['shop_id'] ?? 0);
    $this->v('panel/templates/header', $data);
    $this->v('panel/chat', $data);
    $this->v('panel/templates/footer', $data);
  }

  // Products
  public function products() {
    $data['judul'] = 'Daftar Produk - ' . app_name;
    $data['active_menu'] = 'products';
    $data['shops'] = $this->m('Shop')->findAll();
    
    // Tentukan shop yang aktif
    $activeShopId = $_GET['shop_id'] ?? null;
    if (!$activeShopId && !empty($data['shops'])) {
        $activeShopId = $data['shops'][0]['id'];
    }
    $data['active_shop_id'] = $activeShopId;
    
    // Pagination Logic
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    if ($page < 1) $page = 1;
    
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    if (!in_array($limit, [10, 20, 50, 100])) $limit = 10; // Allowed values
    $data['limit'] = $limit;
    
    $offset = ($page - 1) * $limit;
    
    // Ambil produk berdasarkan toko
    if ($activeShopId) {
        $productModel = $this->m('Product');
        $data['total_products'] = $productModel->countWhere(['shop_id' => $activeShopId]);
        $data['total_pages'] = ceil($data['total_products'] / $limit);
        $data['current_page'] = $page;
        $data['products'] = $productModel->findWherePaginated(['shop_id' => $activeShopId], $limit, $offset);
    } else {
        $data['products'] = [];
        $data['total_products'] = 0;
        $data['total_pages'] = 1;
        $data['current_page'] = 1;
    }
    
    $this->v('panel/templates/header', $data);
    $this->v('panel/products', $data);
    $this->v('panel/templates/footer', $data);
  }

  public function boost() {
    $data['judul'] = 'Naikkan Produk - ' . app_name;
    $data['active_menu'] = 'boost';
    $data['shops'] = $this->m('Shop')->findAll();
    $this->v('panel/templates/header', $data);
    $this->v('panel/boost', $data);
    $this->v('panel/templates/footer', $data);
  }

  // Orders
  public function orders() {
    $data['judul'] = 'Daftar Pesanan - ' . app_name;
    $data['active_menu'] = 'orders';
    $data['shops'] = $this->m('Shop')->findAll();
    
    // Tentukan shop yang aktif
    $activeShopId = $_GET['shop_id'] ?? null;
    if (!$activeShopId && !empty($data['shops'])) {
        $activeShopId = $data['shops'][0]['id'];
    }
    $data['active_shop_id'] = $activeShopId;

    $timezone = new DateTimeZone('Asia/Jakarta');
    $now = new DateTimeImmutable('now', $timezone);
    $defaultStartDate = $now->modify('first day of this month')->format('Y-m-d');
    $defaultEndDate = $now->modify('last day of this month')->format('Y-m-d');
    $startDate = $this->validDate($_GET['startDate'] ?? '') ?: $defaultStartDate;
    $endDate = $this->validDate($_GET['endDate'] ?? '') ?: $defaultEndDate;
    if ($endDate < $startDate) {
        $startDate = $defaultStartDate;
        $endDate = $defaultEndDate;
    }
    $data['start_date'] = $startDate;
    $data['end_date'] = $endDate;
    
    // Pagination Logic
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    if ($page < 1) $page = 1;
    
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    if (!in_array($limit, [10, 20, 50, 100])) $limit = 10;
    $data['limit'] = $limit;
    
    // Ambil pesanan berdasarkan toko
    if ($activeShopId) {
        $orderModel = $this->m('Order');
        $data['total_orders'] = $orderModel->countByCreatedDateRange($activeShopId, $startDate, $endDate);
        $data['total_pages'] = max(1, ceil($data['total_orders'] / $limit));
        $data['current_page'] = min($page, $data['total_pages']);
        $orders = $orderModel->findByCreatedDateRangePaginated($activeShopId, $startDate, $endDate, $limit, ($data['current_page'] - 1) * $limit);
        
        $orderItemModel = $this->m('OrderItem');
        foreach ($orders as &$ord) {
            $ord['items'] = $orderItemModel->findWhere(['order_id' => $ord['id']]);
        }
        $data['orders'] = $orders;
    } else {
        $data['orders'] = [];
        $data['total_orders'] = 0;
        $data['total_pages'] = 1;
        $data['current_page'] = 1;
    }
    
    $this->v('panel/templates/header', $data);
    $this->v('panel/orders', $data);
    $this->v('panel/templates/footer', $data);
  }

  private function validDate($date) {
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone('Asia/Jakarta'));
    return $parsed && $parsed->format('Y-m-d') === $date ? $date : null;
  }

  // Customers
  public function customers() {
    $data['judul'] = 'Daftar Pelanggan - ' . app_name;
    $data['active_menu'] = 'customers';

    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    if ($page < 1) $page = 1;

    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    if (!in_array($limit, [10, 20, 50, 100])) $limit = 10;

    $customerModel = $this->m('Customer');
    $data['total_customers'] = $customerModel->count();
    $data['total_pages'] = max(1, ceil($data['total_customers'] / $limit));
    $data['current_page'] = min($page, $data['total_pages']);
    $data['limit'] = $limit;
    $data['customers'] = $customerModel->findWithOrderStats($limit, ($data['current_page'] - 1) * $limit);

    $this->v('panel/templates/header', $data);
    $this->v('panel/customers', $data);
    $this->v('panel/templates/footer', $data);
  }

  public function sync() {
    $data['judul'] = 'Status Sinkronisasi - ' . app_name;
    $data['active_menu'] = 'sync';
    $data['shops'] = $this->m('Shop')->findAll();
    $this->v('panel/templates/header', $data);
    $this->v('panel/sync', $data);
    $this->v('panel/templates/footer', $data);
  }
}
