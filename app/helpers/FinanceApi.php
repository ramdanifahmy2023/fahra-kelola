<?php
require_once __DIR__ . '/../models/ShopeeCurl.php';

class FinanceApi {
  private $api;
  public function __construct($api = null) { $this->api = $api ?? new ShopeeCurl(); }

  public function verify(array $shop): void {
    $identity = $this->api->check((string)$shop['cookie']);
    if (empty($identity['shop']['id'])) throw new RuntimeException('Cookie belum dapat diverifikasi. Perbarui koneksi toko atau coba lagi.');
    if ((string)$identity['shop']['id'] !== (string)$shop['shop_id']) throw new RuntimeException('Cookie tidak cocok dengan toko yang dipilih. Periksa koneksi toko.');
  }

  private function request(array $shop, string $action, array $body = []): array {
    preg_match('/(?:^|;\s*)SPC_CDS=([^;]+)/', (string)$shop['cookie'], $match);
    if (empty($match[1])) throw new RuntimeException('Cookie toko tidak lengkap. Perbarui koneksi toko.');
    $url = 'https://seller.shopee.co.id/api/v4/accounting/pc/seller_income/income_overview/'.$action.'?SPC_CDS='.urlencode($match[1]).'&SPC_CDS_VER=2';
    $response = $this->api->request($body ? 'POST' : 'GET', $url, $shop['cookie'], $body, ['Origin: https://seller.shopee.co.id','Referer: https://seller.shopee.co.id/portal/finance/income']);
    if (!is_array($response) || ($response['code'] ?? null) !== 0) throw new RuntimeException('Shopee belum memberikan data penghasilan. Coba lagi atau perbarui koneksi toko.');
    return $response;
  }

  public function page(array $shop, array $import): array {
    $pagination = $import['cursor_json'] ? json_decode($import['cursor_json'],true,512,JSON_THROW_ON_ERROR) : ['direction'=>0,'limit'=>50];
    $body = ['source_type'=>0,'income_category'=>(int)$import['category'],'pagination_info'=>$pagination];
    if ((int)$import['category'] === 2) $body['local_query_condition'] = ['start_date'=>$import['start_date'],'end_date'=>$import['end_date']];
    $response = $this->request($shop, 'get_income_detail', $body);
    if (array_key_exists('data',$response) && $response['data'] === []) return ['rows'=>[],'next'=>null];
    if (!is_array($response['data']['list'] ?? null) || !array_is_list($response['data']['list'])) throw new UnexpectedValueException('Daftar penghasilan tidak lengkap.');
    $next = $response['data']['next_page'] ?? null;
    if ($next !== null && (!is_array($next) || !is_scalar($next['cursor'] ?? null))) throw new UnexpectedValueException('Penanda halaman Shopee tidak valid.');
    return ['rows'=>$response['data']['list'],'next'=>empty($next['cursor']) ? null : ['direction'=>(int)($next['direction'] ?? 0),'limit'=>50,'cursor'=>(string)$next['cursor']]];
  }

  public function overview(array $shop): array {
    $response = $this->request($shop,'get_income_overviews');
    $totals=['pending'=>null,'week'=>null,'month'=>null,'all'=>null];
    $keys=[9=>'pending',6=>'week',7=>'month',8=>'all'];
    foreach (($response['list'] ?? []) as $value) if (isset($keys[$value['type'] ?? ''])) $totals[$keys[$value['type']]]=FinancePolicy::scaled($value['amount'] ?? null);
    if ($totals['pending']===null) throw new UnexpectedValueException('Ringkasan pending tidak tersedia.');
    return $totals;
  }

  public function pendingStates(array $shop, array $ids, int $rateMs = 350): array {
    preg_match('/(?:^|;\s*)SPC_CDS=([^;]+)/', (string)$shop['cookie'], $match);
    if (empty($match[1])) return [];
    $states = []; $deadline=microtime(true)+30;
    // Seller Centre captures and a live read accept five order cards per request.
    foreach (array_chunk(array_values(array_unique($ids)),5) as $chunk) {
      if (microtime(true)>=$deadline) break;
      if ($rateMs>0) usleep(min($rateMs,5000)*1000);
      try {
        $response=$this->api->request('POST','https://seller.shopee.co.id/api/v3/order/get_order_list_card_list?SPC_CDS='.urlencode($match[1]).'&SPC_CDS_VER=2',$shop['cookie'],[
          'order_list_tab'=>100,'need_count_down_desc'=>true,
          'order_param_list'=>array_map(static fn($id)=>['order_id'=>(int)$id,'shop_id'=>(int)$shop['shop_id'],'region_id'=>'ID'],$chunk)
        ],['Origin: https://seller.shopee.co.id','Referer: https://seller.shopee.co.id/portal/sale/order']);
        if (($response['code'] ?? null)!==0 || !is_array($response['data']['card_list'] ?? null)) break;
        $batch=[];
        foreach ($response['data']['card_list'] as $card) {
          $order=$card['order_card'] ?? [];
          $id=(string)($order['order_ext_info']['order_id'] ?? '');
          if (!in_array($id,array_map('strval',$chunk),true)) continue;
          $description=$order['status_info']['status_description']['description_value'] ?? '';
          $state=is_string($description) ? FinancePolicy::deliveryState($description) : 'unknown';
          $batch[$id]=isset($batch[$id]) && $batch[$id]!==$state ? 'unknown' : $state;
        }
        foreach ($batch as $id=>$state) $states[$id]=['state'=>$state,'synced_at'=>gmdate('Y-m-d H:i:s')];
      } catch (Throwable $error) {
        // A status-read failure must not discard a valid income snapshot.
        break;
      }
    }
    return $states;
  }
}
