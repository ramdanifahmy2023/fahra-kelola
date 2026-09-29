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
}
