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
        $shop['total_products'] = $productModel->countPanel((int)$shop['id']);
    }
    unset($shop);

    $connection = ['connected' => 0, 'expired' => 0, 'other' => 0];
    $totalProducts = 0;
    $productRanking = [];
    foreach ($shops as $shop) {
        $status = (string)($shop['sync_status'] ?? '');
        $key = $status === 'connected' ? 'connected' : ($status === 'expired' ? 'expired' : 'other');
        $connection[$key]++;
        $productCount = max(0, (int)($shop['total_products'] ?? 0));
        $totalProducts += $productCount;
        $productRanking[] = ['label' => (string)($shop['name'] ?: 'Toko #' . (int)$shop['id']), 'value' => $productCount];
    }
    usort($productRanking, static fn($a, $b) => $b['value'] <=> $a['value']);
    $data['page_recap'] = [
        'id' => 'shops-recap',
        'title' => 'Ringkasan toko',
        'description' => 'Koneksi dan jumlah produk yang sudah tersimpan di Shopdash.',
        'scope' => count($shops) . ' toko',
        'primary' => ['label' => 'Toko terdaftar', 'value' => count($shops), 'context' => 'Dalam akun ini'],
        'metrics' => [
            ['label' => 'Terhubung', 'value' => $connection['connected'], 'tone' => 'success'],
            ['label' => 'Koneksi berakhir', 'value' => $connection['expired'], 'tone' => 'error'],
            ['label' => 'Produk tersimpan', 'value' => $totalProducts],
        ],
        'segments_label' => 'Status koneksi toko',
        'segments' => [
            ['label' => 'Terhubung', 'value' => $connection['connected'], 'tone' => 'success'],
            ['label' => 'Koneksi berakhir', 'value' => $connection['expired'], 'tone' => 'error'],
            ['label' => 'Status lain / belum diketahui', 'value' => $connection['other'], 'tone' => 'neutral'],
        ],
        'shops_label' => 'Produk tersimpan per toko',
        'shops' => array_slice($productRanking, 0, 5),
        'insight' => 'Jumlah produk adalah data yang tersimpan saat sinkronisasi terakhir. Perbarui koneksi toko jika statusnya berakhir.',
    ];
    
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
    $data['judul'] = 'Chat Shopee - ' . app_name;
    $data['active_menu'] = 'chat';
    $data['shops'] = $this->m('Shop')->findAll();
    $data['active_shop_id'] = (int)($_GET['shop_id'] ?? 0);
    $data['active_conversation_id'] = trim((string)($_GET['conversation_id'] ?? ''));
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

    if (($_GET['view'] ?? '') === 'movement') {
      $movementFilter = (string)($_GET['movement'] ?? '');
      if (!in_array($movementFilter, ['fast', 'slow', 'dead', 'empty'], true)) $movementFilter = '';
      $data['movement_view'] = true;
      $data['movement_filter'] = $movementFilter;
      $today = new DateTimeImmutable('today', new DateTimeZone('Asia/Jakarta'));
      $start = $today->modify('-29 days')->format('Y-m-d');
      $data['movement'] = $activeShopId ? $this->m('Product')->movementSummary((int)$activeShopId, $start, $today->format('Y-m-d')) : ['rows'=>[],'summary'=>[],'days'=>0];
      $data['movement_shop_name'] = '';
      foreach ($data['shops'] as $shop) if ((int)$shop['id'] === (int)$activeShopId) { $data['movement_shop_name'] = $shop['name']; break; }
      $this->v('panel/templates/header', $data);
      $this->v('panel/product-movement', $data);
      $this->v('panel/templates/footer', $data);
      return;
    }
    
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
        $stockFilter = (string)($_GET['stock'] ?? '');
        if (!in_array($stockFilter, ['critical','empty','low','healthy','unknown','archived'], true)) $stockFilter = '';
        $data['stock_filter'] = $stockFilter;
        $productSummary = $productModel->summaryForShop((int)$activeShopId);
        $data['product_summary'] = $productSummary;
        $data['total_products'] = $productModel->countPanel((int)$activeShopId, $stockFilter);
        $data['total_pages'] = max(1, ceil($data['total_products'] / $limit));
        $data['current_page'] = $page;
        $data['products'] = $productModel->findPanelPaginated($activeShopId, $limit, $offset, false, $stockFilter);
        $activeShopName = 'Toko';
        foreach ($data['shops'] as $shop) if ((int)$shop['id'] === (int)$activeShopId) { $activeShopName = (string)$shop['name']; break; }
        $stockFilterUrl = static function (string $filter) use ($activeShopId): string {
          $query = ['shop_id'=>(int)$activeShopId];
          if ($filter !== '') $query['stock'] = $filter;
          return burl . '/panel/products?' . http_build_query($query);
        };
        $data['page_recap'] = [
          'id'=>'products-recap',
          'title'=>'Ringkasan produk',
          'description'=>'Posisi stok dari data produk terakhir yang tersimpan.',
          'scope'=>$activeShopName . ' · posisi saat ini',
          'primary'=>['label'=>'Produk tercatat','value'=>$productSummary['total'],'context'=>'Termasuk produk aktif dan arsip'],
          'metrics'=>[
            ['label'=>'Produk aktif','value'=>$productSummary['active'],'context'=>number_format($productSummary['active_stock'],0,',','.') . ' unit stok tercatat','tone'=>'primary'],
            ['label'=>'Stok menipis','value'=>$productSummary['low'],'context'=>'Stok aktif 1–14 unit','tone'=>'warning'],
            ['label'=>'Stok habis','value'=>$productSummary['empty'],'context'=>'Status habis atau stok aktif 0','tone'=>'error'],
            ['label'=>'Diarsipkan','value'=>$productSummary['archived'],'context'=>'Tidak termasuk produk aktif','tone'=>'neutral'],
          ],
          'segments_label'=>'Kondisi stok dan katalog',
          'segments'=>[
            ['label'=>'Stok habis','value'=>$productSummary['empty'],'tone'=>'error','href'=>$stockFilterUrl('empty')],
            ['label'=>'Stok menipis','value'=>$productSummary['low'],'tone'=>'warning','href'=>$stockFilterUrl('low')],
            ['label'=>'Stok cukup','value'=>$productSummary['healthy'],'tone'=>'success','href'=>$stockFilterUrl('healthy')],
            ['label'=>'Data stok belum lengkap','value'=>$productSummary['unknown_stock'],'tone'=>'neutral','incomplete'=>true,'href'=>$stockFilterUrl('unknown')],
            ['label'=>'Diarsipkan','value'=>$productSummary['archived'],'tone'=>'neutral','href'=>$stockFilterUrl('archived')],
          ],
          'insight'=>'Ambang stok menipis adalah kurang dari 15 unit. Produk habis dipisahkan dari stok menipis agar tindakan berikutnya lebih jelas.',
        ];
        $productVariants = $this->m('ProductModel');
        foreach ($data['products'] as &$product) $product['variants'] = $productVariants->forProduct((int)$activeShopId,(int)$product['id']);
        unset($product);
        if (!empty($_GET['highlight'])) {
          $highlighted = $productModel->findPanelById($activeShopId, (int)$_GET['highlight']);
          if ($highlighted) $highlighted['variants'] = $productVariants->forProduct((int)$activeShopId,(int)$highlighted['id']);
          $data['products'] = $highlighted ? [$highlighted] : [];
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
    $orderFilter = (string)($_GET['status'] ?? '');
    if (!in_array($orderFilter, ['waiting','processing','completed','cancelled','advance','unknown'], true)) $orderFilter = '';
    $data['order_filter'] = $orderFilter;
    
    // Pagination Logic
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    if ($page < 1) $page = 1;
    
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    if (!in_array($limit, [10, 20, 50, 100])) $limit = 10;
    $data['limit'] = $limit;
    
    // Ambil pesanan berdasarkan toko
    if ($activeShopId) {
        $orderModel = $this->m('Order');
        $orderSummary = $orderModel->summaryByCreatedDateRange((int)$activeShopId, $startDate, $endDate);
        $data['order_summary'] = $orderSummary;
        $data['total_orders'] = $orderModel->countByCreatedDateRange($activeShopId, $startDate, $endDate, $orderFilter);
        $data['total_pages'] = max(1, ceil($data['total_orders'] / $limit));
        $data['current_page'] = min($page, $data['total_pages']);
        $orders = $orderModel->findByCreatedDateRangePaginated($activeShopId, $startDate, $endDate, $limit, ($data['current_page'] - 1) * $limit, $orderFilter);
        
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
        $data['order_summary'] = ['total'=>0,'advance'=>0,'dated'=>0,'undated'=>0,'waiting'=>0,'processing'=>0,'completed'=>0,'cancelled'=>0,'unknown'=>0,'other'=>0,'trend'=>[]];
        $data['orders'] = [];
        $data['total_orders'] = 0;
        $data['total_pages'] = 1;
        $data['current_page'] = 1;
    }

    $summary = $data['order_summary'];
    $activeShopName = '';
    foreach ($data['shops'] as $shop) if ((int)$shop['id'] === (int)$activeShopId) { $activeShopName = (string)$shop['name']; break; }
    $today = new DateTimeImmutable('today', $timezone);
    $trendEnd = min($endDate, $today->format('Y-m-d'));
    $trend = [];
    if ($startDate <= $trendEnd) {
      $cursor = new DateTimeImmutable($startDate, $timezone);
      $lastDay = new DateTimeImmutable($trendEnd, $timezone);
      while ($cursor <= $lastDay) {
        $day = $cursor->format('Y-m-d');
        $trend[] = ['label'=>$cursor->format('j M'),'value'=>(int)($summary['trend'][$day] ?? 0)];
        $cursor = $cursor->modify('+1 day');
      }
    }
    $rangeStart = new DateTimeImmutable($startDate, $timezone);
    $rangeEnd = new DateTimeImmutable($trendEnd, $timezone);
    $compareDisplay = 'Belum ada pembanding';
    $compareContext = 'Periode sebelumnya dengan durasi sama';
    if ($activeShopId && $rangeStart <= $rangeEnd) {
      $days = max(1, (int)$rangeStart->diff($rangeEnd)->days + 1);
      $previousEnd = $rangeStart->modify('-1 day');
      $previousStart = $previousEnd->modify('-' . ($days - 1) . ' days');
      $currentDated = (int)$summary['dated'];
      $previousDated = $this->m('Order')->countDatedByCreatedDateRange((int)$activeShopId, $previousStart->format('Y-m-d'), $previousEnd->format('Y-m-d'));
      if ($previousDated > 0) {
        $change = round((($currentDated - $previousDated) / $previousDated) * 100, 1);
        $compareDisplay = ($change > 0 ? '+' : '') . number_format($change, 1, ',', '.') . '%';
        $compareContext = number_format($currentDated, 0, ',', '.') . ' vs ' . number_format($previousDated, 0, ',', '.') . ' pesanan bertanggal';
      }
    }
    $filterUrl = static function (string $filter) use ($activeShopId, $startDate, $endDate): string {
      $query = ['shop_id'=>(int)$activeShopId,'startDate'=>$startDate,'endDate'=>$endDate];
      if ($filter !== '') $query['status'] = $filter;
      return burl . '/panel/orders?' . http_build_query($query);
    };
    $data['page_recap'] = [
      'id'=>'orders-recap',
      'title'=>'Ringkasan pesanan',
      'description'=>'Status dan tren dari pesanan yang tersimpan di Shopdash.',
      'scope'=>($activeShopName ?: 'Toko') . ' · ' . (new DateTimeImmutable($startDate, $timezone))->format('j M') . '–' . (new DateTimeImmutable($endDate, $timezone))->format('j M Y'),
      'primary'=>['label'=>'Pesanan tercatat','value'=>$summary['total'],'context'=>((int)$summary['undated'] > 0 ? number_format((int)$summary['undated'],0,',','.') . ' belum memiliki tanggal pesanan' : 'Tanggal mengikuti filter di atas')],
      'metrics'=>[
        ['label'=>'Pesanan kilat','value'=>$summary['advance'],'context'=>'Tipe advance booking','tone'=>'primary'],
        ['label'=>'Perlu diproses','value'=>$summary['waiting'],'context'=>'Belum dibayar atau perlu dikirim','tone'=>'warning'],
        ['label'=>'Dibatalkan','value'=>$summary['cancelled'],'context'=>'Status pesanan Shopee','tone'=>'error'],
        ['label'=>'Perubahan pesanan bertanggal','value'=>null,'display'=>$compareDisplay,'context'=>$compareContext,'tone'=>'neutral'],
      ],
      'segments_label'=>'Komposisi status pesanan',
      'segments'=>[
        ['label'=>'Menunggu / perlu dikirim','value'=>$summary['waiting'],'tone'=>'warning','href'=>$filterUrl('waiting')],
        ['label'=>'Dalam proses','value'=>$summary['processing'],'tone'=>'info','href'=>$filterUrl('processing')],
        ['label'=>'Selesai','value'=>$summary['completed'],'tone'=>'success','href'=>$filterUrl('completed')],
        ['label'=>'Dibatalkan','value'=>$summary['cancelled'],'tone'=>'error','href'=>$filterUrl('cancelled')],
        ['label'=>'Status belum lengkap','value'=>$summary['unknown'] + $summary['other'],'tone'=>'neutral','incomplete'=>true,'href'=>$filterUrl('unknown')],
      ],
      'trend'=>$trend,
      'trend_label'=>'Tren jumlah pesanan bertanggal per hari',
      'trend_caption'=>count($trend) . ' hari · data lokal dapat berubah setelah sinkronisasi',
      'insight'=>'Pesanan dibatalkan dan kegagalan sinkronisasi memiliki arti berbeda. Status sinkronisasi dapat diperiksa di halaman Sinkronisasi.',
    ];
    
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
    $customerFilter = (string)($_GET['segment'] ?? '');
    if (!in_array($customerFilter, ['no-orders','one-order','repeat'], true)) $customerFilter = '';
    $data['customer_filter'] = $customerFilter;

    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    if ($page < 1) $page = 1;

    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    if (!in_array($limit, [10, 20, 50, 100])) $limit = 10;

    $customerModel = $this->m('Customer');
    $customerSummary = $customerModel->summaryForShop($shopId);
    $data['customer_summary'] = $customerSummary;
    $data['total_customers'] = match ($customerFilter) {
      'no-orders' => $customerSummary['no_orders'],
      'one-order' => $customerSummary['one_order'],
      'repeat' => $customerSummary['repeat_orders'],
      default => $customerSummary['total'],
    };
    $data['total_pages'] = max(1, ceil($data['total_customers'] / $limit));
    $data['current_page'] = min($page, $data['total_pages']);
    $data['limit'] = $limit;
    $data['customers'] = $customerModel->findWithOrderStats($limit, ($data['current_page'] - 1) * $limit, $shopId, 0, $customerFilter);
    if (!empty($_GET['customer_id'])) {
      $data['customers']=$customerModel->findWithOrderStats(1,0,$shopId,(int)$_GET['customer_id']);
      $data['total_customers']=count($data['customers']);$data['total_pages']=1;$data['current_page']=1;
      $data['focused_customer_id']=(int)$_GET['customer_id'];
    }

    $shopName = 'Semua toko';
    foreach ($data['shops'] as $shop) if ((int)$shop['id'] === $shopId) { $shopName = (string)$shop['name']; break; }
    $segmentUrl = static function (string $segment) use ($shopId): string {
      $query = ['shop_id'=>$shopId];
      if ($segment !== '') $query['segment'] = $segment;
      return burl . '/panel/customers?' . http_build_query($query);
    };
    $data['page_recap'] = [
      'id'=>'customers-recap',
      'title'=>'Ringkasan pelanggan',
      'description'=>'Segmentasi berdasarkan jumlah pesanan yang tersimpan sepanjang waktu.',
      'scope'=>$shopName . ' · semua waktu',
      'primary'=>['label'=>'Pelanggan tercatat','value'=>$customerSummary['total'],'context'=>'Jumlah pelanggan unik pada cakupan toko'],
      'metrics'=>[
        ['label'=>'Pelanggan kembali','value'=>$customerSummary['repeat_orders'],'context'=>'Memiliki 2 pesanan atau lebih','tone'=>'primary'],
        ['label'=>'Satu pesanan','value'=>$customerSummary['one_order'],'context'=>'Memiliki satu pesanan tersimpan','tone'=>'neutral'],
        ['label'=>'Belum ada pesanan','value'=>$customerSummary['no_orders'],'context'=>'Belum ada pesanan pada cakupan toko','tone'=>'warning'],
      ],
      'segments_label'=>'Pola pesanan pelanggan',
      'segments'=>[
        ['label'=>'Pelanggan kembali · 2+ pesanan','value'=>$customerSummary['repeat_orders'],'tone'=>'primary','href'=>$segmentUrl('repeat')],
        ['label'=>'Satu pesanan','value'=>$customerSummary['one_order'],'tone'=>'info','href'=>$segmentUrl('one-order')],
        ['label'=>'Belum ada pesanan tersimpan','value'=>$customerSummary['no_orders'],'tone'=>'neutral','href'=>$segmentUrl('no-orders')],
      ],
      'insight'=>'Jumlah pesanan dihitung dari riwayat lokal yang tersimpan. Segmentasi ini bukan pelanggan baru pada periode tertentu.',
    ];

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
