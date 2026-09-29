<?php
require_once __DIR__ . '/AiConnectionSupport.php';

class NineRouterClient {
  private array $allowed;
  private $resolver;
  private $transport;
  public function __construct(?array $allowed = null, ?callable $resolver = null, ?callable $transport = null) {
    $this->allowed = $allowed ?? array_filter(array_map('trim', explode(',', AiConnectionSettings::value('AI_CONNECTION_ALLOWED_ORIGINS'))));
    $this->resolver = $resolver;
    $this->transport = $transport;
  }
  public static function model($value, bool $optional = false): string {
    if (!is_string($value) || mb_strlen($value) > 255 || preg_match('/[\x00-\x1f\x7f]/', $value) || (!$optional && trim($value) === '')) {
      throw new AiConnectionError('Isi nama model atau combo yang valid, maksimal 255 karakter.', 422, ['default_model'=>'Isi ID model atau nama combo, maksimal 255 karakter.']);
    }
    return trim($value);
  }
  public static function normalize($url): string {
    if (!is_string($url) || strlen($url) > 2048 || preg_match('/[\x00-\x20\x7f\\\\]/', trim($url))) self::badUrl();
    $p = parse_url(trim($url));
    if (!$p || !in_array(strtolower($p['scheme'] ?? ''), ['https','http'], true) || empty($p['host']) || isset($p['user']) || isset($p['pass']) || isset($p['query']) || isset($p['fragment'])) self::badUrl();
    $host = strtolower($p['host']);
    if (!filter_var(trim($host, '[]'), FILTER_VALIDATE_IP) && !preg_match('/^(?=.{1,253}$)[a-z0-9]+(?:[a-z0-9.-]*[a-z0-9])?$/D', $host)) self::badUrl();
    $path = rtrim($p['path'] ?? '', '/');
    if ($path === '') $path = '/v1';
    if (!preg_match('#^(/[a-zA-Z0-9_-]+)*/v1$#D', $path) || str_contains($path, '/v1/v1')) self::badUrl();
    $scheme = strtolower($p['scheme']);
    $port = $p['port'] ?? ($scheme === 'https' ? 443 : 80);
    return $scheme . '://' . $host . (($scheme === 'https' && $port === 443) || ($scheme === 'http' && $port === 80) ? '' : ':' . $port) . $path;
  }
  private static function badUrl(): void { throw new AiConnectionError('Base URL harus berupa alamat API berakhiran /v1, tanpa query atau credential.', 422, ['base_url'=>'Gunakan origin atau API root lengkap berakhiran /v1.']); }
  private static function inCidr(string $ip, string $network, int $bits): bool {
    $a = inet_pton($ip); $b = inet_pton($network);
    if ($a === false || $b === false || strlen($a) !== strlen($b)) return false;
    $bytes = intdiv($bits, 8); $rest = $bits % 8;
    return substr($a, 0, $bytes) === substr($b, 0, $bytes) && (!$rest || ((ord($a[$bytes]) ^ ord($b[$bytes])) & (255 << (8 - $rest))) === 0);
  }
  public static function publicIp(string $ip): bool {
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
      foreach ([['0.0.0.0',8],['10.0.0.0',8],['100.64.0.0',10],['127.0.0.0',8],['169.254.0.0',16],['172.16.0.0',12],['192.0.0.0',24],['192.0.2.0',24],['192.168.0.0',16],['198.18.0.0',15],['198.51.100.0',24],['203.0.113.0',24],['224.0.0.0',3]] as [$net,$bits]) if (self::inCidr($ip,$net,$bits)) return false;
      return true;
    }
    return self::inCidr($ip,'2000::',3) && !self::inCidr($ip,'2001::',23) && !self::inCidr($ip,'2001:db8::',32) && !self::inCidr($ip,'2002::',16);
  }
  private static function localIp(string $ip): bool {
    foreach ([['127.0.0.0',8],['10.0.0.0',8],['172.16.0.0',12],['192.168.0.0',16],['::1',128],['fc00::',7]] as [$net,$bits]) if (self::inCidr($ip,$net,$bits)) return true;
    return false;
  }
  public function target(string $url): array {
    $url = self::normalize($url); $p = parse_url($url);
    $host = trim($p['host'], '[]'); $port = $p['port'] ?? ($p['scheme'] === 'https' ? 443 : 80);
    $origin = $p['scheme'] . '://' . $p['host'] . (isset($p['port']) ? ':' . $port : '');
    $allowed = in_array($origin, $this->allowed, true);
    if ($p['scheme'] !== 'https' && !$allowed) throw new AiConnectionError('HTTP hanya tersedia untuk origin lokal yang diizinkan pengelola server.', 422, ['base_url'=>'Gunakan HTTPS atau daftarkan origin lokal di konfigurasi server.']);
    if (filter_var($host, FILTER_VALIDATE_IP)) $ips = [$host];
    elseif ($this->resolver) $ips = ($this->resolver)($host);
    else {
      $records = @dns_get_record($host, DNS_A | DNS_AAAA);
      $ips = array_values(array_filter(array_map(fn($r)=>$r['ip'] ?? $r['ipv6'] ?? null, $records ?: [])));
    }
    if (!$ips) throw new AiConnectionError('Alamat server tidak dapat ditemukan melalui DNS.', 502);
    foreach ($ips as $ip) {
      if (!self::publicIp($ip) && !($allowed && self::localIp($ip))) throw new AiConnectionError('Alamat tujuan tidak diizinkan. Periksa origin lokal pada konfigurasi server.', 422, ['base_url'=>'Tujuan jaringan tidak diizinkan.']);
      if ($p['scheme'] === 'http' && !self::localIp($ip)) throw new AiConnectionError('Host publik harus menggunakan HTTPS.',422,['base_url'=>'Gunakan HTTPS untuk host publik.']);
    }
    return ['url'=>$url, 'host'=>$host, 'port'=>$port, 'ip'=>$ips[0]];
  }
  public function request(string $base, string $key, string $kind, string $model = ''): array {
    if (!in_array($kind, ['models','test'], true)) throw new InvalidArgumentException('Unknown probe');
    $target = $this->target($base);
    $body = $kind === 'test' ? ['model'=>self::model($model),'messages'=>[['role'=>'user','content'=>'Ini uji koneksi. Balas hanya: OK']], 'stream'=>false] : null;
    $url = $target['url'] . ($body ? '/chat/completions' : '/models');
    if ($this->transport) $response = ($this->transport)($url, $key, $body, $target);
    else $response = $this->send($url, $key, $body, $target);
    $status = (int)$response['status'];
    if ($status !== 200) {
      $message = match (true) {
        $status === 401 || $status === 403 => 'Provider menolak akses. Periksa API key dan izin koneksi.',
        $status === 404 => 'Endpoint atau model tidak ditemukan. Periksa base URL dan nama model.',
        $status === 429 => 'Batas permintaan provider tercapai. Coba lagi nanti.',
        $status >= 300 && $status < 400 => 'Provider mengarahkan ke URL lain. Gunakan base URL tujuan langsung.',
        default => 'Provider gagal memproses permintaan (HTTP ' . $status . ').'
      };
      throw new AiConnectionError($message, 502);
    }
    if (strlen($response['body']) > 1048576) throw new AiConnectionError('Respons provider terlalu besar.', 502);
    $json = json_decode($response['body'], true);
    if (!is_array($json)) throw new AiConnectionError('Respons provider bukan JSON yang valid.', 502);
    if ($kind === 'models') {
      if (!is_array($json['data'] ?? null)) throw new AiConnectionError('Format daftar model tidak dikenali.', 502);
      $models = [];
      foreach (array_slice($json['data'], 0, 5000) as $row) {
        if (!is_array($row) || !is_string($row['id'] ?? null)) continue;
        try { $id = self::model($row['id']); } catch (AiConnectionError $e) { continue; }
        $models[$id] = ['id'=>$id, 'kind'=>($row['owned_by'] ?? '') === 'combo' ? 'combo' : 'model'];
      }
      return ['models'=>array_values($models)];
    }
    $content = $json['choices'][0]['message']['content'] ?? null;
    if (($json['choices'][0]['message']['role'] ?? '') !== 'assistant' || !is_string($content) || trim($content) === '') throw new AiConnectionError('Model tidak mengembalikan teks jawaban yang valid.', 502);
    $usage = [];
    foreach (['prompt_tokens','completion_tokens','total_tokens'] as $key) if (is_int($json['usage'][$key] ?? null) && $json['usage'][$key] >= 0) $usage[$key] = $json['usage'][$key];
    return ['model'=>$model, 'usage'=>$usage];
  }
  private function send(string $url, string $key, ?array $body, array $target): array {
    if (!extension_loaded('curl')) throw new AiConnectionError('HTTP client server belum tersedia.', 503);
    $ch = curl_init($url); $buffer = ''; $large = false;
    $ip = str_contains($target['ip'], ':') ? '[' . $target['ip'] . ']' : $target['ip'];
    curl_setopt_array($ch, [CURLOPT_HTTPHEADER=>['Authorization: Bearer ' . $key,'Content-Type: application/json','Accept: application/json'],
      CURLOPT_FOLLOWLOCATION=>false, CURLOPT_PROTOCOLS=>CURLPROTO_HTTP | CURLPROTO_HTTPS, CURLOPT_PROXY=>'',
      CURLOPT_SSL_VERIFYPEER=>true, CURLOPT_SSL_VERIFYHOST=>2, CURLOPT_CONNECTTIMEOUT=>5, CURLOPT_TIMEOUT=>$body ? 60 : 15,
      CURLOPT_WRITEFUNCTION=>function($ch,$chunk) use (&$buffer,&$large) { if (strlen($buffer)+strlen($chunk)>1048576) { $large=true; return 0; } $buffer.=$chunk; return strlen($chunk); }]);
    if (!filter_var($target['host'],FILTER_VALIDATE_IP)) curl_setopt($ch,CURLOPT_RESOLVE,[$target['host'] . ':' . $target['port'] . ':' . $ip]);
    if ($body) { curl_setopt($ch,CURLOPT_POST,true); curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($body,JSON_THROW_ON_ERROR)); }
    $ok=curl_exec($ch); $error=curl_errno($ch); $status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
    if ($ok === false) throw new AiConnectionError($large ? 'Respons provider terlalu besar.' : ($error === CURLE_OPERATION_TIMEDOUT ? 'Provider melewati batas waktu. Tes tidak diulang otomatis.' : 'Koneksi ke provider gagal. Periksa jaringan dan sertifikat TLS.'),502);
    return ['status'=>$status,'body'=>$buffer];
  }
}
