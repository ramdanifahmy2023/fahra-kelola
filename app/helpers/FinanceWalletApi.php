<?php

class FinanceWalletReadException extends RuntimeException {}

class FinanceWalletApi {
  private $transport;
  public function __construct(?callable $transport=null) { $this->transport=$transport; }

  private function request(string $path,array $query,string $cookie): array {
    if ($this->transport) $result=($this->transport)($path,$query,$cookie);
    else {
      $handle=curl_init('https://seller.shopee.co.id'.$path.'?'.http_build_query($query));
      curl_setopt_array($handle,[
        CURLOPT_RETURNTRANSFER=>true, CURLOPT_ENCODING=>'', CURLOPT_CONNECTTIMEOUT=>10, CURLOPT_TIMEOUT=>20,
        CURLOPT_SSL_VERIFYPEER=>true, CURLOPT_SSL_VERIFYHOST=>2, CURLOPT_FOLLOWLOCATION=>false,
        CURLOPT_HTTPHEADER=>['Cookie: '.$cookie,'Accept: application/json','Origin: https://seller.shopee.co.id',
          'Referer: https://seller.shopee.co.id/portal/finance/wallet/shopeepay',
          'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36']
      ]);
      $raw=curl_exec($handle);
      $result=['status'=>curl_getinfo($handle,CURLINFO_HTTP_CODE),'body'=>$raw===false ? null : json_decode($raw,true)];
    }
    if (($result['status'] ?? 0)!==200 || !is_array($result['body'] ?? null)) throw new FinanceWalletReadException('Shopee belum memberikan Saldo Penjual. Coba lagi atau perbarui koneksi toko.');
    return $result['body'];
  }

  private function verify(array $shop,array $query): void {
    $body=$this->request('/api/framework/selleraccount/shop_info/',$query+['_cache_api_sw_v1_'=>1],$shop['cookie']);
    if (($body['code'] ?? null)!==0 || empty($body['data']['shop_id'])) throw new FinanceWalletReadException('Identitas toko belum dapat diperiksa. Perbarui koneksi toko.');
    if ((string)$body['data']['shop_id']!==(string)$shop['shop_id']) throw new FinanceWalletReadException('Cookie tidak cocok dengan toko. Perbarui koneksi toko.');
  }

  public function read(array $shop): array {
    preg_match('/(?:^|;\s*)SPC_CDS=([^;]+)/',(string)($shop['cookie'] ?? ''),$match);
    if (empty($match[1])) throw new FinanceWalletReadException('Cookie toko belum lengkap. Perbarui koneksi toko.');
    $query=['SPC_CDS'=>$match[1],'SPC_CDS_VER'=>2];
    $this->verify($shop,$query);
    $body=$this->request('/api/v4/seller/local_wallet/get_wallet_status',$query+['wallet_provider'=>0,'bank_account_id'=>0],$shop['cookie']);
    $value=self::parse($body);
    $this->verify($shop,$query);
    return $value;
  }

  public static function parse(array $body): array {
    if (($body['error'] ?? null)!==0 || !is_array($body['data'] ?? null)) throw new FinanceWalletReadException('Shopee belum memberikan Saldo Penjual. Coba lagi atau perbarui koneksi toko.');
    $data=$body['data']; $amount=$data['wallet_available_balance'] ?? null;
    if ((!is_int($amount) && !is_string($amount)) || !preg_match('/^-?\d+$/D',(string)$amount) || abs((float)$amount)>9007199254740991) throw new FinanceWalletReadException('Nominal Saldo Penjual belum dapat dibaca. Data terakhir tetap ditampilkan.');
    $restricted=$data['is_seller_withdrawal_maintenance_group_active'] ?? null;
    $restricted=is_bool($restricted) ? $restricted : null; $notice=null;
    if ($restricted===true && is_string($data['seller_withdrawal_maintenance_group_description'] ?? null)) {
      $raw=mb_convert_encoding($data['seller_withdrawal_maintenance_group_description'],'UTF-8','UTF-8');
      $raw=preg_replace('~<(script|style)\b[^>]*>.*?</\1>~is','',$raw);
      $plain=strip_tags(html_entity_decode(strip_tags($raw),ENT_QUOTES | ENT_HTML5,'UTF-8'));
      $plain=preg_replace('/[\x00-\x1F\x7F]/u',' ',$plain);
      $notice=mb_substr(trim(preg_replace('/\s+/u',' ',$plain)),0,1000) ?: null;
    }
    return ['amount'=>(int)$amount,'withdrawal_restricted'=>$restricted,'notice'=>$notice];
  }
}
