<?php
require_once __DIR__.'/BoostPolicy.php';

class BoostTransport {
  public function request(string $method, string $path, string $cookie, array $query = [], array $body = []): array {
    $allowed = ['/api/framework/selleraccount/shop_info/','/api/v3/opt/mpsku/list/get_boost_info','/api/v3/opt/product/boost_product/'];
    if (!in_array($path, $allowed, true)) throw new BoostError('Endpoint Boost tidak valid.', 500);
    preg_match('/(?:^|;\s*)SPC_CDS=([^;]+)/', $cookie, $matches);
    $query = ['SPC_CDS'=>$matches[1] ?? '', 'SPC_CDS_VER'=>'2'] + $query;
    $retryAfter = 0;
    $ch = curl_init('https://seller.shopee.co.id'.$path.'?'.http_build_query($query));
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>true, CURLOPT_FOLLOWLOCATION=>false,
      CURLOPT_SSL_VERIFYPEER=>true, CURLOPT_SSL_VERIFYHOST=>2, CURLOPT_CONNECTTIMEOUT=>10, CURLOPT_TIMEOUT=>30,
      CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS, CURLOPT_CUSTOMREQUEST=>$method, CURLOPT_ENCODING=>'',
      CURLOPT_HEADERFUNCTION=>static function($handle,string $header) use (&$retryAfter): int {
        if(stripos($header,'Retry-After:')===0){$value=trim(substr($header,12));$seconds=ctype_digit($value)?(int)$value:max(0,(strtotime($value)?:0)-time());$retryAfter=min(86400,max(0,$seconds));}
        return strlen($header);
      },
      CURLOPT_HTTPHEADER=>['Cookie: '.$cookie,'Content-Type: application/json','Accept: application/json',
        'Origin: https://seller.shopee.co.id','Referer: https://seller.shopee.co.id/portal/product/list/live/all',
        'User-Agent: Mozilla/5.0'],
    ]);
    if ($method === 'POST') curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    $raw = curl_exec($ch);
    return ['http_status'=>(int)curl_getinfo($ch,CURLINFO_HTTP_CODE), 'transport_error'=>curl_errno($ch)!==0, 'retry_after'=>$retryAfter,
      'body'=>is_string($raw) ? json_decode($raw,true) : null];
  }

  private function read(array $response): array {
    if (!empty($response['transport_error']) || $response['http_status'] < 200 || $response['http_status'] >= 300 || !is_array($response['body'])) {
      $error = new BoostError('Shopee belum dapat dihubungi. Pemeriksaan sebelum pengiriman gagal.', 502);
      $error->retryAfter=(int)($response['retry_after']??0);throw $error;
    }
    return $response['body'];
  }

  public function identity(array $shop): void {
    $body = $this->read($this->request('GET','/api/framework/selleraccount/shop_info/',(string)$shop['cookie']));
    if (($body['code'] ?? null) !== 0 || empty($body['data']['shop_id'])) throw new BoostError('Sesi toko belum dapat diverifikasi. Periksa koneksi toko.', 409);
    if ((string)$body['data']['shop_id'] !== (string)$shop['shop_id']) throw new BoostError('Sesi aktif milik toko berbeda. Perbarui koneksi toko.', 409);
  }

  public function info(array $shop, array $ids): array {
    return BoostPolicy::info($this->read($this->request('GET','/api/v3/opt/mpsku/list/get_boost_info',(string)$shop['cookie'],['product_id_list'=>implode(',',$ids)])), $ids);
  }

  public function send(array $shop, string $id): array {
    return $this->request('POST','/api/v3/opt/product/boost_product/',(string)$shop['cookie'],['version'=>'3.1.0'],['id'=>(int)$id]);
  }

  public static function senderEnabled(): bool {
    $env = @parse_ini_file(__DIR__.'/../../config/.env') ?: [];
    return (string)(getenv('BOOST_SEND_ENABLED') ?: ($env['BOOST_SEND_ENABLED'] ?? '0')) === '1';
  }
}
