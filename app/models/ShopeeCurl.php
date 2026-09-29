<?php

class ShopeeCurl {
  private $lastRequestDiagnostics = [];

  public function getLastRequestDiagnostics() {
    return $this->lastRequestDiagnostics;
  }

    // Base URL Seller Shopee (bisa disesuaikan versi API-nya)
    private $baseUrl = 'https://seller.shopee.co.id/api';

    /**
     * Base cURL method yang fleksibel untuk berbagai macam request ke Shopee
     * 
     * @param string $method Method HTTP (GET / POST)
     * @param string $endpoint Path endpoint (misal: /v3/settings/get_shop_info) atau URL utuh
     * @param string $cookie Data cookie session untuk autentikasi
     * @param array  $data Payload (untuk POST) atau Query Params (untuk GET)
     * @param array  $customHeaders Tambahan header kustom jika diperlukan
     * @return array Hasil respons berupa Array (dari JSON)
     */
    public function request($method, $endpoint, $cookie, $data = [], $customHeaders = []) {
        // Jika endpoint sudah berupa URL utuh, gunakan langsung. Jika belum, gabung dengan baseUrl.
        $url = (strpos($endpoint, 'http') === 0) ? $endpoint : $this->baseUrl . $endpoint;
        
        $ch = curl_init();
        
        // Setup header bawaan wajib
        $defaultHeaders = [
            'Cookie: ' . $cookie,
            'Content-Type: application/json',
            'Accept: application/json, text/plain, */*',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
        ];
        
        // Gabungkan dengan header tambahan jika ada
        $headers = array_merge($defaultHeaders, $customHeaders);

        $this->lastRequestDiagnostics = [
          'method' => strtoupper($method),
          'endpoint' => parse_url($url, PHP_URL_PATH),
          'requested_at' => gmdate('c'),
          'header_names' => array_values(array_unique(array_map(static function ($header) { return strtolower(trim(explode(':', $header, 2)[0])); }, $headers))),
          'cookie_present' => trim($cookie) !== '',
          'fingerprint_present' => is_array($data) && !empty($data['device_sz_fingerprint']),
          'http_status' => null,
          'api_code' => null,
          'api_error' => null
        ];

        $method = strtoupper($method);

        // Penanganan berdasarkan tipe Method
        if ($method == 'GET') {
            if (!empty($data)) {
                // Jika URL sudah punya query string (ada tanda '?'), gunakan '&', jika belum gunakan '?'
                $separator = (parse_url($url, PHP_URL_QUERY) == NULL) ? '?' : '&';
                $url .= $separator . (is_array($data) ? http_build_query($data) : $data);
            }
        } elseif ($method == 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if (!empty($data)) {
                // Jika data berbentuk array, jadikan JSON. Jika string, kirim langsung (raw)
                $payload = is_array($data) ? json_encode($data) : $data;
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            } else {
                // Memastikan Content-Length: 0 untuk POST tanpa body
                curl_setopt($ch, CURLOPT_POSTFIELDS, "");
            }
        }

        // Eksekusi Opsi cURL
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_ENCODING       => '', // Mengizinkan semua tipe encoding (gzip, deflate)
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CUSTOMREQUEST  => $method
        ]);

        $response = curl_exec($ch);
        $err = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $this->lastRequestDiagnostics['http_status'] = $httpCode;
        $this->lastRequestDiagnostics['transport_error'] = $err !== '';
        
        // curl_close($ch); // Deprecated and throws a warning that corrupts JSON output

        if ($err) {
            return [
                'success' => false, 
                'message' => 'cURL Error: ' . $err
            ];
        }

        // Decode JSON, tapi pertahankan format string jika gagal di-decode (biasanya karena terblokir HTML)
        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'success' => false,
                'http_code' => $httpCode,
                'message' => 'Respons bukan JSON yang valid. Mungkin cookie kedaluwarsa atau IP diblokir.',
                'raw_response' => $response
            ];
        }

        if (is_array($decoded)) {
          $this->lastRequestDiagnostics['api_code'] = is_numeric($decoded['code'] ?? $decoded['errcode'] ?? null) ? (int)($decoded['code'] ?? $decoded['errcode']) : null;
          $this->lastRequestDiagnostics['api_error'] = is_numeric($decoded['error'] ?? null) ? (int)$decoded['error'] : null;
        }
        return $decoded;
    }

    /**
     * Method untuk melakukan pengecekan validitas Cookie ke server Shopee
     * Menggunakan endpoint shop_info yang lebih tangguh dan minim error
     * 
     * @param string $cookie Data cookie session
     * @return array
     */
    public function check($cookie) {
        // Ekstrak SPC_CDS dari cookie untuk otorisasi parameter URL
        preg_match('/SPC_CDS=([^;]+)/', $cookie, $matches);
        $spcCds = $matches[1] ?? '';

        $endpoint = 'https://seller.shopee.co.id/api/framework/selleraccount/shop_info/?SPC_CDS=' . $spcCds . '&SPC_CDS_VER=2&_cache_api_sw_v1_=1';
        
        $response = $this->request('GET', $endpoint, $cookie);
        
        // Memetakan ulang (mapping) response JSON agar sesuai dengan ekspektasi controller (ProcShops)
        if (isset($response['code']) && $response['code'] === 0 && isset($response['data'])) {
            return [
                'shop' => [
                    'id' => $response['data']['shop_id'] ?? '',
                    'name' => $response['data']['name'] ?? '',
                    'image' => '' // Endpoint ini tidak memiliki gambar, biarkan kosong
                ]
            ];
        }

        return [
            'message' => $response['message'] ?? 'user_is_unauthorized'
        ];
    }
    /**
     * Method untuk mengambil alamat toko (menggunakan is_pickup_address)
     * Menggunakan endpoint shopee.co.id/api/v4/account/address/get_user_address_list
     * 
     * @param string $cookie Data cookie session
     * @return string|null Mengembalikan string alamat lengkap jika ada, null jika tidak ditemukan
     */
    public function getAddress($cookie) {
        $endpoint = 'https://shopee.co.id/api/v4/account/address/get_user_address_list?with_warehouse_whitelist_status=true';
        
        $response = $this->request('GET', $endpoint, $cookie);
        
        if (isset($response['error']) && $response['error'] === 0 && isset($response['data']['addresses'])) {
            foreach ($response['data']['addresses'] as $addr) {
                // Cari alamat yang digunakan untuk pickup
                if (isset($addr['is_pickup_address']) && $addr['is_pickup_address'] === true) {
                    return $addr['address'];
                }
            }
        }
        
        return null;
    }
    /**
     * Method untuk mengambil data keuangan (saldo wallet toko)
     * Menggunakan endpoint seller.shopee.co.id/api/v4/seller/local_wallet/get_wallet_status
     * 
     * @param string $cookie Data cookie session
     * @return int Mengembalikan nilai saldo yang tersedia (0 jika gagal)
     */
    public function getWallet($cookie) {
        // Ekstrak SPC_CDS dari cookie untuk otorisasi parameter URL
        preg_match('/SPC_CDS=([^;]+)/', $cookie, $matches);
        $spcCds = $matches[1] ?? '';

        $endpoint = 'https://seller.shopee.co.id/api/v4/seller/local_wallet/get_wallet_status?SPC_CDS=' . $spcCds . '&SPC_CDS_VER=2&wallet_provider=0&bank_account_id=0';
        
        $response = $this->request('GET', $endpoint, $cookie);
        
        if (isset($response['error']) && $response['error'] === 0 && isset($response['data'])) {
            return (int) ($response['data']['wallet_available_balance'] ?? 0);
        }
        
        return false;
    }
    /**
     * Method untuk mengambil data iklan (Ads Data)
     * Menggunakan endpoint seller.shopee.co.id/api/pas/v1/meta/get_ads_data/
     * 
     * @param string $cookie Data cookie session
     * @return array|false Mengembalikan array ads_credit jika berhasil, false jika gagal
     */
    public function getAdsData($cookie) {
        // Ekstrak SPC_CDS dari cookie untuk otorisasi parameter URL
        preg_match('/SPC_CDS=([^;]+)/', $cookie, $matches);
        $spcCds = $matches[1] ?? '';

        $endpoint = 'https://seller.shopee.co.id/api/pas/v1/meta/get_ads_data/?SPC_CDS=' . $spcCds . '&SPC_CDS_VER=2';
        
        $payload = [
            'info_type_list' => ["ads_expense","ads_credit","campaign_day","has_ads","incentive","ads_toggle","live_stream_account"]
        ];

        $customHeaders = [
            'origin: https://seller.shopee.co.id',
            'referer: https://seller.shopee.co.id/'
        ];
        
        $response = $this->request('POST', $endpoint, $cookie, $payload, $customHeaders);
        
        if (isset($response['code']) && $response['code'] === 0 && isset($response['data']['ads_credit'])) {
            $adsCredit = $response['data']['ads_credit'];
            $adsCredit['total'] = (int) round(($adsCredit['total'] ?? 0) / 100000);
            return $adsCredit;
        }
        
        return false;
    }

    /**
     * Mengambil ringkasan status iklan toko dari Seller Centre.
     * Endpoint ini tidak membutuhkan campaign_id sehingga aman dipakai untuk
     * ringkasan multi-toko sebelum daftar campaign tersedia.
     */
    public function getAdsSummary($cookie) {
        preg_match('/SPC_CDS=([^;]+)/', $cookie, $matches);
        $spcCds = $matches[1] ?? '';
        if ($spcCds === '') {
            return false;
        }

        $endpoint = 'https://seller.shopee.co.id/api/pas/v1/meta/get_ads_data/?SPC_CDS=' . urlencode($spcCds) . '&SPC_CDS_VER=2';
        $response = $this->request('POST', $endpoint, $cookie, [
            'info_type_list' => [
                'ads_expense',
                'ads_credit',
                'campaign_day',
                'has_ads',
                'incentive',
                'ads_toggle',
                'live_stream_account'
            ]
        ], [
            'Origin: https://seller.shopee.co.id',
            'Referer: https://seller.shopee.co.id/portal/marketing/pas/index'
        ]);

        if (!is_array($response) || (isset($response['code']) && (int)$response['code'] !== 0)) {
            return false;
        }

        $data = $response['data'] ?? null;
        if (!is_array($data)) {
            return false;
        }

        $toMoney = static function ($value) {
            return (int)round(((float)$value) / 100000);
        };

        $credit = is_array($data['ads_credit'] ?? null) ? $data['ads_credit'] : [];
        $expense = is_array($data['ads_expense'] ?? null) ? $data['ads_expense'] : [];
        require_once __DIR__ . '/../helpers/AdsCampaignReports.php';
        $reports = (new AdsCampaignReports($this))->all($cookie);
        return [
            'ads_credit' => [
                'total' => $toMoney($credit['total'] ?? 0),
                'is_low_balance' => !empty($credit['is_low_balance']),
                'low_balance_status' => (string)($credit['low_balance_status'] ?? 'unknown'),
                'expiring_in_30d' => $toMoney($credit['expiring_in_30d'] ?? 0)
            ],
            'ads_expense_today' => $toMoney($expense['ads_expense_today'] ?? 0),
            'has_ads' => is_array($data['has_ads'] ?? null) ? $data['has_ads'] : [],
            'campaign_day' => is_array($data['campaign_day'] ?? null) ? $data['campaign_day'] : [],
            'ads_toggle' => is_array($data['ads_toggle'] ?? null) ? $data['ads_toggle'] : [],
            'incentive' => is_array($data['incentive'] ?? null) ? $data['incentive'] : [],
            'live_stream_account' => is_array($data['live_stream_account'] ?? null) ? $data['live_stream_account'] : [],
            'performance' => $reports['daily']['product'],
            'performance_reports' => $reports
        ];
    }

  public function getAdsPerformanceReports($cookie, ?DateTimeImmutable $now = null) {
    require_once __DIR__ . '/../helpers/AdsPerformance.php';
    preg_match('/SPC_CDS=([^;]+)/', $cookie, $matches);
    $spcCds = $matches[1] ?? '';
    $endpoint = 'https://seller.shopee.co.id/api/pas/v1/report/get_time_graph/?SPC_CDS=' . urlencode($spcCds) . '&SPC_CDS_VER=2';
    $reports = [];
    $blockedResponse = $spcCds === '' ? [] : null;
    $now = $now ?? new DateTimeImmutable('now', new DateTimeZone('Asia/Jakarta'));
    $responses = [];
    foreach (['daily', 'weekly', 'monthly'] as $period) {
      $range = AdsPerformance::range($period, $now);
      foreach (AdsPerformance::CHANNELS as $channel => $config) {
        $body = AdsPerformance::requestBody($range, $channel);
        $key = json_encode($body);
        $state = 'sent';
        $diagnostics = null;
        if (isset($responses[$key])) {
          [$response, $diagnostics] = $responses[$key];
          $state = 'reused';
        } elseif ($blockedResponse !== null) {
          $response = $blockedResponse;
          $state = 'skipped';
        } else {
          $response = $this->request('POST', $endpoint, $cookie, $body, [
            'Origin: https://seller.shopee.co.id',
            'Referer: https://seller.shopee.co.id/portal/marketing/pas/index'
          ]);
          $diagnostics = $this->getLastRequestDiagnostics();
          $responses[$key] = [$response, $diagnostics];
        }
        $reports[$period][$channel] = AdsPerformance::normalize(is_array($response) ? $response : [], $range, $channel);
        $reports[$period][$channel]['request_state'] = $state;
        $reports[$period][$channel]['diagnostics'] = $diagnostics;
        if ($state === 'skipped') {
          $reports[$period][$channel]['attempted_at'] = null;
          $reports[$period][$channel]['error_message'] = $spcCds === '' ? 'Request laporan dilewati karena sesi toko tidak lengkap.' : 'Request laporan ini dilewati setelah request sebelumnya ditolak Shopee (90309999).';
        } elseif ($state === 'reused') {
          $reports[$period][$channel]['attempted_at'] = $diagnostics['requested_at'] ?? null;
        }
        if ((int)($response['error'] ?? 0) === 90309999) $blockedResponse = $response;
      }
    }
    return $reports;
  }

    /**
     * Method untuk mengambil data produk dari toko
     * Menggunakan endpoint seller.shopee.co.id/api/v3/opt/mpsku/list/v2/search_product_list
     * 
     * @param string $cookie Data cookie session
     * @param int $pageSize Jumlah produk per halaman
     * @return array|false Mengembalikan array data produk beserta pagination jika berhasil, false jika gagal
     */
    public function getProducts($cookie, $pageSize = 24) {
        // Ekstrak SPC_CDS dari cookie untuk otorisasi parameter URL
        preg_match('/SPC_CDS=([^;]+)/', $cookie, $matches);
        $spcCds = $matches[1] ?? '';

        $allProducts = [];
        $cursor = '';
        $pageInfo = null;
        $seenCursors = [];
        $maxLoops = 50; // Safety limit (50 * 24 = 1200 products max)

        for ($i = 0; $i < $maxLoops; $i++) {
            $cursorParam = $cursor ? '&cursor=' . urlencode($cursor) : '&page_number=1';
            $endpoint = 'https://seller.shopee.co.id/api/v3/opt/mpsku/list/v2/search_product_list?SPC_CDS=' . $spcCds . '&SPC_CDS_VER=2&page_size=' . $pageSize . $cursorParam . '&list_type=all&request_attribute=&operation_sort_by=recommend_v4&need_ads=true';
            
            $response = $this->request('GET', $endpoint, $cookie);
            
            if (isset($response['code']) && $response['code'] === 0) {
                $products = $response['data']['products'] ?? [];
                
                if (empty($products)) {
                    break;
                }
                
                $allProducts = array_merge($allProducts, $products);
                
                $pageInfo = $response['data']['page_info'] ?? null;
                $newCursor = $pageInfo['cursor'] ?? '';
                
                // Jika cursor kosong, atau cursor sama dengan sebelumnya (infinite loop), hentikan
                if (empty($newCursor) || in_array($newCursor, $seenCursors)) {
                    break;
                }
                
                $seenCursors[] = $newCursor;
                $cursor = $newCursor;
                
                // Jika produk yang ditarik kurang dari pageSize, hentikan loop
                if (count($products) < $pageSize) {
                    break;
                }
                
                // Jeda sebentar untuk menghindari rate limit
                usleep(500000); // 0.5 detik
            } else {
                if (empty($allProducts)) {
                    return false;
                }
                break;
            }
        }
        
        return [
            'products' => $allProducts,
            'page_info' => $pageInfo
        ];
    }

    /**
     * Mengambil metrik dashboard realtime Seller Centre.
     * Payload direkam dari endpoint /api/mydata/v2/campaign_board/realtime_metrics/.
     * Token sesi tetap dibentuk dari cookie di server dan tidak dikirim ke browser.
     */
    public function getRealtimeMetrics($cookie) {
        preg_match('/SPC_CDS=([^;]+)/', $cookie, $matches);
        $spcCds = $matches[1] ?? '';
        if ($spcCds === '') {
            return false;
        }

        $endpoint = 'https://seller.shopee.co.id/api/mydata/v2/campaign_board/realtime_metrics/';
        $response = $this->request('GET', $endpoint, $cookie, [
            'SPC_CDS' => $spcCds,
            'SPC_CDS_VER' => 2,
            'event' => 'confirmed'
        ], [
            'Referer: https://seller.shopee.co.id/datacenter/liveboard?ADTAG=mydata'
        ]);

        if (!is_array($response) || (isset($response['err_code']) && (int)$response['err_code'] !== 0)) {
            return false;
        }

        $data = $response['data'] ?? null;
        if (!is_array($data)) {
            return false;
        }

        return [
            'key_metrics' => is_array($data['key_metrics'] ?? null) ? $data['key_metrics'] : [],
            'top_sales_items' => is_array($data['top_sales_items'] ?? null) ? $data['top_sales_items'] : [],
            'sales_hourly' => is_array($data['sales_hourly'] ?? null) ? $data['sales_hourly'] : [],
            'time' => (int)($data['time'] ?? time())
        ];
    }

    public function getProductsPage($cookie, $pageSize = 24, $cursor = '') {
        preg_match('/SPC_CDS=([^;]+)/', $cookie, $matches);
        $spcCds = $matches[1] ?? '';
        $cursorParam = $cursor ? '&cursor=' . urlencode($cursor) : '&page_number=1';
        $endpoint = 'https://seller.shopee.co.id/api/v3/opt/mpsku/list/v2/search_product_list?SPC_CDS=' . $spcCds . '&SPC_CDS_VER=2&page_size=' . (int)$pageSize . $cursorParam . '&list_type=all&request_attribute=&operation_sort_by=recommend_v4&need_ads=true';
        $response = $this->request('GET', $endpoint, $cookie);

        if (!isset($response['code']) || $response['code'] !== 0) {
            return false;
        }

        if (!is_array($response['data']['products'] ?? null) || !is_array($response['data']['page_info'] ?? null)) return false;
        return [
            'products' => $response['data']['products'],
            'page_info' => $response['data']['page_info']
        ];
    }

    public function getBoostInfo($cookie, array $productIds) {
        $productIds = array_values(array_unique(array_filter(array_map('intval', $productIds))));
        if (!$productIds) return [];
        $endpoint = $this->sellerMarketingEndpoint('/api/v3/opt/mpsku/list/get_boost_info', $cookie, [
            'product_id_list' => implode(',', $productIds)
        ]);
        $response = $this->request('GET', $endpoint, $cookie, [], [
            'Origin: https://seller.shopee.co.id',
            'Referer: https://seller.shopee.co.id/portal/product/list/live/all'
        ]);
        if (!is_array($response) || (int)($response['code'] ?? -1) !== 0) return false;
        return is_array($response['data']['boost_infos'] ?? null) ? $response['data']['boost_infos'] : [];
    }

    public function boostProduct($cookie, $productId) {
        $productId = (int)$productId;
        if ($productId < 1) return false;
        $endpoint = $this->sellerMarketingEndpoint('/api/v3/opt/product/boost_product/', $cookie, [
            'version' => '3.1.0'
        ]);
        return $this->request('POST', $endpoint, $cookie, ['id' => $productId], [
            'Origin: https://seller.shopee.co.id',
            'Referer: https://seller.shopee.co.id/portal/product/list/live/all'
        ]);
    }

    /**
     * Method untuk mengambil data user pemilik akun toko
     * Menggunakan endpoint seller.shopee.co.id/api/selleraccount/user_info/
     * 
     * @param string $cookie Data cookie session
     * @return array|false Mengembalikan array data user jika berhasil, false jika gagal
     */
    public function getUserInfo($cookie) {
        // Ekstrak SPC_CDS dari cookie untuk otorisasi parameter URL
        preg_match('/SPC_CDS=([^;]+)/', $cookie, $matches);
        $spcCds = $matches[1] ?? '';

        $endpoint = 'https://seller.shopee.co.id/api/selleraccount/user_info/?SPC_CDS=' . $spcCds . '&SPC_CDS_VER=2';
        
        $response = $this->request('GET', $endpoint, $cookie);
        
        if (isset($response['code']) && $response['code'] === 0 && isset($response['data'])) {
            return $response['data'];
        }
        
        return false;
    }

    /**
     * Method untuk mengambil data pesanan (Orders)
     * Menggunakan dua endpoint: 
     * 1. search_order_list_index untuk mendapatkan list ID order
     */
    public function getOrderIndexList($cookie, $pageNumber = 1, $pageSize = 40, $nextPageSentinel = '') {
        preg_match('/SPC_CDS=([^;]+)/', $cookie, $matches);
        $spcCds = $matches[1] ?? '';

        $endpointIndex = 'https://seller.shopee.co.id/api/v3/order/search_order_list_index?SPC_CDS=' . $spcCds . '&SPC_CDS_VER=2';
        
        $paginationParams = [
            'page_size' => $pageSize
        ];
        
        if (!empty($nextPageSentinel)) {
            $paginationParams['page_sentinel'] = $nextPageSentinel;
        }
        $paginationParams['from_page_number'] = 1; // Selalu 1 berdasarkan payload yang diberikan user
        $paginationParams['page_number'] = $pageNumber;

        $payloadIndex = json_encode([
            'order_list_tab' => 100, // 100 = Semua Pesanan
            'entity_type' => 1,
            'pagination' => $paginationParams,
            'filter' => [
                'fulfillment_type' => 0,
                'is_drop_off' => 0,
                'fulfillment_source' => 0,
                'action_filter' => 0
            ],
            'sort' => [
                'sort_type' => 3,
                'ascending' => false
            ]
        ]);

        return $this->request('POST', $endpointIndex, $cookie, $payloadIndex);
    }

    /**
     * Mengambil data paket (Package) berdasarkan Order ID
     */
    public function getPackage($cookie, $orderId) {
        preg_match('/SPC_CDS=([^;]+)/', $cookie, $matches);
        $spcCds = $matches[1] ?? '';

        $endpoint = 'https://seller.shopee.co.id/api/v3/order/get_package?SPC_CDS=' . $spcCds . '&SPC_CDS_VER=2&order_id=' . $orderId;
        
        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Cookie: ' . $cookie,
            'Content-Type: application/json',
            'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)'
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        if ($response) {
            $data = json_decode($response, true);
            if (isset($data['code']) && $data['code'] === 0) {
                return $data['data'] ?? [];
            }
        }
        return false;
    }

    /**
     * Mengambil detail dari satu order ID
     */
    public function getOneOrder($cookie, $orderId) {
        preg_match('/SPC_CDS=([^;]+)/', $cookie, $matches);
        $spcCds = $matches[1] ?? '';

        $endpointOneOrder = 'https://seller.shopee.co.id/api/v3/order/get_one_order?SPC_CDS=' . $spcCds . '&SPC_CDS_VER=2&order_id=' . $orderId;
        $response = $this->request('GET', $endpointOneOrder, $cookie);
        
        if (isset($response['code']) && $response['code'] === 0 && isset($response['data'])) {
            return $response['data'];
        }
        
        return false;
    }

    public function getOrderIncomeComponents($cookie, $orderId, $components = [2, 3, 4]) {
        preg_match('/SPC_CDS=([^;]+)/', $cookie, $matches);
        $spcCds = $matches[1] ?? '';

        if ($spcCds === '' || empty($orderId)) {
            return false;
        }

        $endpoint = 'https://seller.shopee.co.id/api/v4/accounting/pc/seller_income/income_detail/get_order_income_components?SPC_CDS=' . urlencode($spcCds) . '&SPC_CDS_VER=2';
        $payload = [
            'order_id' => (int)$orderId,
            'components' => array_values(array_map('intval', $components))
        ];
        $headers = [
            'Origin: https://seller.shopee.co.id',
            'Referer: https://seller.shopee.co.id/'
        ];

        $response = $this->request('POST', $endpoint, $cookie, $payload, $headers);
        if (!isset($response['code']) || $response['code'] !== 0 || !isset($response['data'])) {
            return false;
        }

        return $this->normalizeIncomeAmounts($response['data']);
    }

    private function sellerMarketingEndpoint($path, $cookie, array $query = []) {
        preg_match('/(?:^|;\s*)SPC_CDS=([^;]+)/', $cookie, $matches);
        $query = array_merge([
            'SPC_CDS' => $matches[1] ?? '',
            'SPC_CDS_VER' => 2
        ], $query);
        return 'https://seller.shopee.co.id' . $path . '?' . http_build_query($query);
    }

    /** Ringkasan voucher toko dari Seller Centre. */
    public function getVoucherList($cookie, $offset = 0, $limit = 100, $promotionType = 0) {
        $endpoint = $this->sellerMarketingEndpoint('/api/marketing/v3/voucher/list/', $cookie, [
            'offset' => max(0, (int)$offset),
            'limit' => max(1, min(100, (int)$limit)),
            'promotion_type' => (int)$promotionType
        ]);
        $response = $this->request('GET', $endpoint, $cookie, [], [
            'Origin: https://seller.shopee.co.id',
            'Referer: https://seller.shopee.co.id/portal/marketing/voucher'
        ]);
        if (!is_array($response) || (int)($response['code'] ?? -1) !== 0) {
            return false;
        }
        $data = is_array($response['data'] ?? null) ? $response['data'] : [];
        return [
            'total_count' => (int)($data['total_count'] ?? 0),
            'vouchers' => is_array($data['voucher_list'] ?? null) ? $data['voucher_list'] : [],
            'max_active_count' => (int)($data['max_active_count'] ?? 0)
        ];
    }

    /** Daftar slot flash sale toko. Detail produk tidak diperlukan untuk ringkasan dashboard. */
    public function getFlashSaleList($cookie, $offset = 0, $limit = 100, $type = 0) {
        $endpoint = $this->sellerMarketingEndpoint('/api/marketing/v4/shop_flash_sale/get_shop_flash_sale_list/', $cookie, [
            'offset' => max(0, (int)$offset),
            'limit' => max(1, min(100, (int)$limit)),
            'type' => (int)$type
        ]);
        $response = $this->request('GET', $endpoint, $cookie, [], [
            'Origin: https://seller.shopee.co.id',
            'Referer: https://seller.shopee.co.id/portal/marketing/shop-flash-sale/list?type=3'
        ]);
        if (!is_array($response) || (int)($response['code'] ?? -1) !== 0) {
            return false;
        }
        $data = is_array($response['data'] ?? null) ? $response['data'] : [];
        return [
            'total_count' => (int)($data['total_count'] ?? 0),
            'flash_sales' => is_array($data['flash_sale_list'] ?? null) ? $data['flash_sale_list'] : []
        ];
    }

    /**
     * Ambil metrik performa toko dari dashboard Seller Centre.
     * Endpoint ini dipetakan dari rekaman MCP dan mengembalikan titik harian
     * untuk laporan lokal. Cookie tetap dikirim hanya ke Shopee, tidak pernah
     * dimasukkan ke hasil yang dikembalikan.
     */
    public function getShopPerformance($cookie, DateTimeInterface $start, DateTimeInterface $end, $period = 'custom') {
        preg_match('/(?:^|;\s*)SPC_CDS=([^;]+)/', (string)$cookie, $matches);
        $spcCds = $matches[1] ?? '';
        if ($spcCds === '') {
            return ['ok' => false, 'message' => 'SPC_CDS tidak ditemukan pada cookie toko.'];
        }

        $endpoint = 'https://seller.shopee.co.id/api/mydata/v3/dashboard/key-metrics/';
        $query = [
            'SPC_CDS' => $spcCds,
            'SPC_CDS_VER' => 2,
            'start_time' => (string)$start->getTimestamp(),
            'end_time' => (string)$end->getTimestamp(),
            'period' => (string)$period,
            'fetag' => 'fetag'
        ];
        $response = $this->request('GET', $endpoint, $cookie, $query, [
            'Origin: https://seller.shopee.co.id',
            'Referer: https://seller.shopee.co.id/datacenter/overview'
        ]);
        if (!is_array($response)) {
            return ['ok' => false, 'message' => 'Respons performa toko tidak valid.'];
        }
        if ((int)($response['code'] ?? -1) !== 0 || !is_array($response['result'] ?? null)) {
            $message = (string)($response['message'] ?? $response['msg'] ?? 'Endpoint performa toko menolak permintaan.');
            return ['ok' => false, 'message' => $message, 'response' => $response];
        }
        return ['ok' => true, 'result' => $response['result']];
    }

    private function normalizeIncomeAmounts($value, $path = []) {
        if (!is_array($value)) {
            return $value;
        }

        foreach ($value as $key => $item) {
            $currentPath = [...$path, $key];
            if (is_array($item)) {
                $value[$key] = $this->normalizeIncomeAmounts($item, $currentPath);
                continue;
            }

            $isOrderItemQuantity = $key === 'amount' && in_array('order_items', $path, true);
            $isMoney = is_numeric($item)
                && preg_match('/(amount|price|subtotal|payment|income|fee|discount|credit|balance|cost)$/i', (string)$key)
                && !preg_match('/(id|rate|qty|count|time)$/i', (string)$key);

            if ($isMoney && !$isOrderItemQuantity) {
                $value[$key] = intdiv((int)$item, 100000);
            }
        }

        return $value;
    }

}
