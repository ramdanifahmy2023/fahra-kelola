<?php

class FinancePolicy {
  public const STATES = ['preparing','pickup','shipping','delivered','return','mixed','unknown'];
  public const STATUS_NOTES = [
    'not_checked'=>'Status belum diperiksa. Perbarui saldo Shopee.',
    'request_failed'=>'Pembacaan status Shopee gagal. Akan dicoba pada pembaruan berikutnya.',
    'budget'=>'Sebagian status menunggu giliran pembaruan berikutnya.',
    'not_returned'=>'Shopee belum mengembalikan status pesanan ini.',
    'unrecognized'=>'Keterangan status Shopee belum dikenali.',
    'missing_description'=>'Shopee belum memberikan keterangan tahap pengiriman.',
    'mixed_packages'=>'Paket dalam pesanan ini berada di tahap berbeda. Nominal dihitung sekali.',
    'courier_verification'=>'Menunggu pengiriman diverifikasi oleh jasa kirim.',
    'pickup_recorded'=>'Pickup paket sudah tercatat di Shopee.'
  ];
  public static function date(string $value): DateTimeImmutable {
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('Asia/Jakarta'));
    if (!$date || $date->format('Y-m-d') !== $value) throw new InvalidArgumentException('Tanggal tidak valid.');
    return $date;
  }

  public static function range(?string $start = null, ?string $end = null): array {
    $today = new DateTimeImmutable('today', new DateTimeZone('Asia/Jakarta'));
    $from = self::date($start ?? $today->modify('first day of this month')->format('Y-m-d'));
    $to = self::date($end ?? $today->format('Y-m-d'));
    if ($from > $to || $to > $today || $from->format('Y') < '2015') throw new InvalidArgumentException('Pilih rentang berurutan, mulai 2015 sampai hari ini.');
    return ['start'=>$from->format('Y-m-d'),'end'=>$to->format('Y-m-d'),'days'=>(int)$from->diff($to)->format('%a')+1];
  }

  public static function windows(array $range): array {
    $windows = []; $start = self::date($range['start']); $end = self::date($range['end']);
    while ($start <= $end) {
      $last = min($start->modify('last day of this month'), $end);
      $windows[] = ['start'=>$start->format('Y-m-d'),'end'=>$last->format('Y-m-d')];
      $start = $last->modify('+1 day');
    }
    return $windows;
  }

  public static function scaled($value, bool $nullable = false): ?int {
    if ($value === null && $nullable) return null;
    if ((!is_int($value) && !(is_string($value) && preg_match('/^-?\d+$/D', $value))) || abs((float)$value) > PHP_INT_MAX) {
      throw new UnexpectedValueException('Nominal Shopee tidak valid. Data lama tetap disimpan.');
    }
    return (int)$value;
  }

  public static function time($value): ?string {
    if (!$value) return null;
    if (!is_numeric($value) || $value < 0 || $value > 4102444800) throw new UnexpectedValueException('Waktu penghasilan tidak valid.');
    return gmdate('Y-m-d H:i:s', (int)$value);
  }

  public static function row(array $entry, int $category): array {
    $detail = $entry['local_income_detail'] ?? null;
    $order = $detail['order_income_info'] ?? [];
    if (!is_array($detail) || !ctype_digit((string)($order['order_id'] ?? '')) || (int)$order['order_id'] < 1) throw new UnexpectedValueException('Rincian penghasilan tidak dikenali.');
    $released = self::time($detail['income_released_time'] ?? null);
    if ($category === 2 && !$released) throw new UnexpectedValueException('Tanggal pelepasan tidak tersedia.');
    return [
      'external_order_id'=>(string)$order['order_id'], 'order_sn'=>mb_substr((string)($order['order_sn'] ?? ''),0,100),
      'product_name'=>mb_substr((string)($order['item_name'] ?? ''),0,500), 'item_count'=>isset($order['item_count']) ? (int)$order['item_count'] : null,
      'income_amount'=>self::scaled($detail['income_amount'] ?? null),
      'adjustment_amount'=>self::scaled($detail['adjustment_income_amount'] ?? null,true),
      'net_amount'=>self::scaled($detail['net_income_amount'] ?? null,true),
      'released_at'=>$released,
      'released_date'=>$released ? (new DateTimeImmutable($released,new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Asia/Jakarta'))->format('Y-m-d') : null,
      'estimated_at'=>self::time($detail['income_estimated_escrow_time'] ?? null),
      'adjustment_at'=>self::time($detail['income_adjustment_released_time'] ?? null),
      'status_code'=>isset($order['order_status']) ? (int)$order['order_status'] : null,
      'status_key'=>mb_substr((string)($detail['order_status_transify_key'] ?? ''),0,120)
    ];
  }

  public static function pendingState(array $row): string {
    if (($row['status_key'] ?? '') === 'ps_content_return_processing') return 'return';
    if (isset($row['snapshot_state']) && in_array($row['snapshot_state'], self::STATES, true)) return $row['snapshot_state'];
    if (empty($row['detail_synced_at']) || empty($row['completed_at']) || strtotime($row['detail_synced_at'].' UTC') < strtotime($row['completed_at'].' UTC')-900) return 'unknown';
    return self::deliveryState($row['status_description'] ?? '');
  }

  public static function deliveryState(string $description): string {
    $description = mb_strtolower(trim($description));
    if (str_starts_with($description,'pesanan sedang dikembalikan ke penjual') || str_starts_with($description,'pembeli mengajukan pengembalian') || str_starts_with($description,'buyer raised return/refund') || str_starts_with($description,'order is being returned to seller')) return 'return';
    if (str_starts_with($description,'pesanan telah tiba di pembeli') || str_starts_with($description,'order has been delivered to buyer')) return 'delivered';
    if (str_starts_with($description,'pesanan sedang dikirimkan ke pembeli') || str_starts_with($description,'order is being shipped to buyer')) return 'shipping';
    if (str_starts_with($description,'mohon kirim / arrange pickup sebelum') || str_starts_with($description,'to avoid late shipment, please arrange drop-off / arrange pickup by')) return 'preparing';
    if ($description==='menunggu pengiriman diverifikasi oleh jasa kirim.' || str_starts_with($description,'paket dipick up pada')) return 'pickup';
    return 'unknown';
  }

  public static function statusDescription($description): array {
    $description=is_string($description) ? trim($description) : '';
    $state=self::deliveryState($description); $reason=null;
    if ($state==='unknown') $reason=$description==='' ? 'missing_description' : 'unrecognized';
    if ($state==='pickup') $reason=str_starts_with(mb_strtolower($description),'paket dipick up pada') ? 'pickup_recorded' : 'courier_verification';
    return ['state'=>$state,'reason'=>$reason];
  }

  public static function combineStatuses(array $statuses): array {
    if (!$statuses) return ['state'=>'unknown','reason'=>'missing_description'];
    $states=array_unique(array_column($statuses,'state'));
    if (in_array('unknown',$states,true)) {
      foreach ($statuses as $status) if ($status['state']==='unknown') return $status;
    }
    if (count($states)>1) return ['state'=>'mixed','reason'=>'mixed_packages'];
    $reasons=array_unique(array_column($statuses,'reason'));
    return ['state'=>reset($states),'reason'=>count($reasons)===1 ? reset($reasons) : null];
  }
}
