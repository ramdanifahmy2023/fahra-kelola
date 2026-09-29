<?php

class Panel extends Controller {
  public function __construct() {
    $path=trim(parse_url($_SERVER['REQUEST_URI'] ?? '/panel',PHP_URL_PATH),'/');
    $scope=basename($path);if ($scope==='panel') $scope='dashboard';
    $explicitNavigation=count(array_diff(array_keys($_GET),['url']))>0;
    try {
      $workspace=$this->m('AccountWorkspace')->allFor((int)authUser()['id']);
      $_GET=WorkspacePolicy::restore($scope,$_GET,$workspace[$scope] ?? [],isset($workspace['global']['shop_id'])?(int)$workspace['global']['shop_id']:null);
      $GLOBALS['panel_workspace']=['saved'=>$workspace,'scope'=>$scope,'available'=>true,'explicitNavigation'=>$explicitNavigation];
    } catch (Throwable $e) {$GLOBALS['panel_workspace']=['saved'=>[],'scope'=>$scope,'available'=>false,'explicitNavigation'=>$explicitNavigation];}
  }

  public function finance() {
    $data=['judul'=>'Keuangan - '.app_name,'active_menu'=>'finance'];
    $data['shops']=$this->m('Finance')->shops();
    $this->v('panel/templates/header',$data);
    $this->v('panel/finance',$data);
    $this->v('panel/templates/footer',$data);
  }

  public function automation() {
    $data = ['judul'=>'Automation Engine - ' . app_name, 'active_menu'=>'automation'];
    $data['shops'] = $this->m('Shop')->findAll();
    $shopIds = array_map('intval', array_column($data['shops'], 'id'));
    $requested = (int)($_GET['shop_id'] ?? 0);
    $data['active_shop_id'] = in_array($requested, $shopIds, true) ? $requested : ($shopIds[0] ?? 0);
    $data['automation_error'] = false;
    $data['automation_profile'] = null;
    require_once __DIR__ . '/../../helpers/AutomationPolicy.php';
    $data['automation_provider'] = AutomationPolicy::providerStatus();
    if ($data['active_shop_id']) {
      try {
        $model = $this->m('AutomationProfile'); $model->ensureSchema();
        $data['automation_profile'] = $model->forShop($data['active_shop_id']);
      } catch (Throwable $error) { $data['automation_error'] = true; }
    }
    $this->v('panel/templates/header', $data);
    $this->v('panel/automation', $data);
    $this->v('panel/templates/footer', $data);
  }

  // Index
  public function index() {
    $data['judul'] = 'Panel - ' . app_name;
    $data['active_menu'] = 'dashboard';
    $data['shops'] = $this->m('Finance')->shops();
    $data['finance_dashboard'] = true;
    
    $this->v('panel/templates/header', $data);
    $this->v('panel/index', $data);
    $this->v('panel/templates/footer', $data);
  }

  public function extensions() {
    $data['judul'] = 'Ekstensi - ' . app_name;
    $data['active_menu'] = 'extensions';
    $data['releases'] = [];
    $data['release_error'] = false;
    try {
      $data['releases'] = $this->m('ExtensionRelease')->all();
    } catch (Throwable $error) {
      error_log('Extension catalog: ' . $error->getMessage());
      $data['release_error'] = true;
    }
    $this->v('panel/templates/header', $data);
    $this->v('panel/extensions', $data);
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

  public function reports() {
    $data['judul'] = 'Laporan Performa Toko - ' . app_name;
    $data['active_menu'] = 'reports';
    $data['shops'] = $this->m('Shop')->findAll();
    $data['active_shop_id'] = max(0,(int)($_GET['shop_id'] ?? 0));
    $this->v('panel/templates/header', $data);
    $this->v('panel/reports', $data);
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
    $validShopIds=array_map('intval',array_column($data['shops'],'id'));
    if (!in_array((int)$activeShopId,$validShopIds,true)) $activeShopId=$validShopIds[0] ?? 0;
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
        $criticalFilter = ($_GET['stock'] ?? '') === 'critical';
        $data['stock_filter'] = $criticalFilter ? 'critical' : '';
        $data['total_products'] = $criticalFilter ? $productModel->countCritical($activeShopId) : $productModel->countWhere(['shop_id' => $activeShopId]);
        $data['total_pages'] = ceil($data['total_products'] / $limit);
        $data['current_page'] = $page;
        $data['products'] = $criticalFilter ? $productModel->findCriticalPaginated($activeShopId, $limit, $offset) : $productModel->findWherePaginated(['shop_id' => $activeShopId], $limit, $offset);
        if (!empty($_GET['highlight'])) {
          $data['products']=$productModel->findWhere(['id'=>(int)$_GET['highlight'],'shop_id'=>(int)$activeShopId]);
          $data['total_products']=count($data['products']);$data['total_pages']=1;$data['current_page']=1;
          $data['focused_product_id']=(int)$_GET['highlight'];
        }
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
    $validShopIds=array_map('intval',array_column($data['shops'],'id'));
    if (!in_array((int)$activeShopId,$validShopIds,true)) $activeShopId=$validShopIds[0] ?? 0;
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
        
        $data['focused_order_id'] = max(0,(int)($_GET['order_id'] ?? 0));
        if ($data['focused_order_id']) {
            $orders = $orderModel->findWhere(['id'=>$data['focused_order_id'],'shop_id'=>(int)$activeShopId]);
            $data['total_orders'] = count($orders);
            $data['total_pages'] = 1;
            $data['current_page'] = 1;
        }
        $orderItemModel = $this->m('OrderItem');
        $referenceDb=new Database();$references=[];
        foreach (['logistic_channels','payment_methods'] as $referenceTable) {
          $referenceDb->query('SELECT code,title FROM '.$referenceTable);$references[$referenceTable]=array_column($referenceDb->getAll(),'title','code');
        }
        foreach ($orders as &$ord) {
            $ord['items'] = $orderItemModel->findWhere(['order_id' => $ord['id']]);
            $ord['shipping_cargo_label']=$references['logistic_channels'][$ord['shipping_cargo'] ?? ''] ?? ($ord['shipping_cargo'] ?? '');
            $ord['payment_method_label']=$references['payment_methods'][$ord['payment_method'] ?? ''] ?? ($ord['payment_method'] ?? '');
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
    $data['shops'] = $this->m('Shop')->findAll();
    $shopId = (int)($_GET['shop_id'] ?? 0);
    if (!in_array($shopId, array_map('intval', array_column($data['shops'], 'id')), true)) $shopId = 0;
    $data['active_shop_id'] = $shopId;

    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    if ($page < 1) $page = 1;

    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    if (!in_array($limit, [10, 20, 50, 100])) $limit = 10;

    $customerModel = $this->m('Customer');
    $data['total_customers'] = $customerModel->countForShop($shopId);
    $data['total_pages'] = max(1, ceil($data['total_customers'] / $limit));
    $data['current_page'] = min($page, $data['total_pages']);
    $data['limit'] = $limit;
    $data['customers'] = $customerModel->findWithOrderStats($limit, ($data['current_page'] - 1) * $limit, $shopId);
    if (!empty($_GET['customer_id'])) {
      $data['customers']=$customerModel->findWithOrderStats(1,0,$shopId,(int)$_GET['customer_id']);
      $data['total_customers']=count($data['customers']);$data['total_pages']=1;$data['current_page']=1;
      $data['focused_customer_id']=(int)$_GET['customer_id'];
    }

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
