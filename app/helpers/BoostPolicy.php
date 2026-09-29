<?php

class BoostError extends RuntimeException {
  public int $retryAfter = 0;
}

class BoostPolicy {
  const CAPACITY = 5;
  const COOLDOWN_SECONDS = 255 * 60;
  const WINDOW_SECONDS = 4 * 3600;

  public static function ids($value, bool $allowEmpty = false): array {
    if (!is_array($value) || !array_is_list($value) || count($value) > self::CAPACITY || (!$allowEmpty && !$value)) {
      throw new BoostError('Pilih 1 sampai 5 produk.', 422);
    }
    $ids = [];
    foreach ($value as $id) {
      if ((!is_int($id) && !is_string($id)) || !preg_match('/^[1-9][0-9]{0,15}$/', (string)$id)) throw new BoostError('ID produk tidak valid.', 422);
      $ids[] = (string)$id;
    }
    if (count(array_unique($ids)) !== count($ids)) throw new BoostError('Produk yang sama dipilih lebih dari sekali.', 422);
    return $ids;
  }

  public static function localReason(array $product, array $cooldown = []): string {
    if (!empty($product['missing'])) return 'Produk tidak ditemukan di toko ini';
    if (!empty($product['deleted_at']) || (int)($product['status'] ?? 0) !== 1) return 'Produk tidak aktif';
    if ((int)($product['total_stock'] ?? 0) <= 0) return 'Stok kosong';
    if (!empty($cooldown['unresolved'])) return 'Hasil sebelumnya perlu diperiksa';
    if (!empty($cooldown['cooldown_active'])) return 'Menunggu jeda produk';
    return '';
  }

  public static function info($body, array $ids): array {
    if (!is_array($body) || ($body['code'] ?? null) !== 0 || !is_array($body['data']['boost_infos'] ?? null)) {
      throw new BoostError('Status Shopee belum dapat diverifikasi. Pemeriksaan akan diulang.', 502);
    }
    $rows = $body['data']['boost_infos']; $result = [];
    foreach ($ids as $id) {
      $candidate = null;
      if (!array_is_list($rows) && isset($rows[$id]) && is_array($rows[$id])) $candidate = $rows[$id];
      foreach ($rows as $row) {
        if (is_array($row) && (string)($row['product_id'] ?? $row['id'] ?? '') === (string)$id) { $candidate = $row; break; }
      }
      if (!$candidate) { $result[$id] = ['eligible'=>false,'reason'=>'Shopee tidak mengembalikan status produk']; continue; }
      $returnedId=$candidate['product_id']??$candidate['id']??$id;
      if((string)$returnedId!==(string)$id){$result[$id]=['eligible'=>false,'reason'=>'Identitas status produk tidak cocok'];continue;}
      // Missing flags are not permission to send.
      $show = $candidate['show_boost_button'] ?? null;
      $disabled = $candidate['disabled_boost_button'] ?? null;
      $valid = in_array($show, [true,false,0,1], true) && in_array($disabled, [true,false,0,1], true);
      $result[$id] = ['eligible'=>$valid && (bool)$show && !(bool)$disabled, 'reason'=>$valid ? 'Belum tersedia menurut Shopee' : 'Bentuk status Shopee belum dikenali'];
    }
    return $result;
  }

  public static function outcome(array $response): array {
    $http = (int)($response['http_status'] ?? 0); $body = $response['body'] ?? null;
    if (empty($response['transport_error']) && $http >= 200 && $http < 300 && is_array($body) && is_int($body['code'] ?? null)) {
      if ($body['code'] === 0) return ['status'=>'success','message'=>'Diterima Shopee'];
      return ['status'=>'failed','message'=>'Shopee menolak permintaan (kode '.$body['code'].').'];
    }
    return ['status'=>'unknown','message'=>'Hasil pengiriman belum pasti. Periksa Seller Centre sebelum menentukan hasil.'];
  }

  public static function nextCheck(array $products, array $cooldowns, array $summary, int $now): int {
    if (!empty($summary['unresolved_count'])) return $now + 900;
    $times = [];
    foreach ($products as $p) {
      if ((int)($p['status'] ?? 0) !== 1 || !empty($p['deleted_at']) || (int)($p['total_stock'] ?? 0) <= 0) continue;
      $next = $cooldowns[(string)$p['id']]['next_boost_at'] ?? null;
      $times[] = max($now + 60, $next ? strtotime($next.' UTC') : 0);
    }
    $due = $times ? min($times) : $now + 900;
    if (($summary['remaining_count'] ?? 0) === 0 && !empty($summary['quota_reset_at'])) $due = max($due, strtotime($summary['quota_reset_at'].' UTC') + 1);
    return $due;
  }
}
