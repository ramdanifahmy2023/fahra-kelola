<?php

final class NotificationPolicy {
  public const SHIP_WARNING = 86400;
  public const SHIP_URGENT = 21600;
  public const ORDER_FRESHNESS = 1800;
  public const MODULES = ['orders'=>'Pesanan','products'=>'Produk','shops'=>'Toko','chat'=>'Chat','ads'=>'Iklan','ads_topups'=>'Topup iklan','promotions'=>'Promosi','performance'=>'Performa','customers'=>'Pelanggan','packages'=>'Paket','finance'=>'Keuangan'];

  public static function utc(?string $value): ?int {
    if (!$value || str_starts_with($value, '0000-')) return null;
    try { return (new DateTimeImmutable($value, new DateTimeZone('UTC')))->getTimestamp(); }
    catch (Throwable $e) { return null; }
  }

  public static function accessIssue(?string $error): ?string {
    $error = strtolower((string)$error);
    if (preg_match('/forbidden|access denied|permission|akses.*(ditolak|dibatasi)|\b403\b/', $error)) return 'access';
    if (preg_match('/\b401\b|unauthorized|sesi.*(habis|tidak valid|diperbarui)|cookie.*(kosong|expired|tidak valid)|session.*expired|invalid.*(token|session)/', $error)) return 'session';
    return null;
  }

  // null means insufficient/currently stale evidence, false means verified no alert.
  public static function shipping(array $order, int $now) {
    $source = self::utc($order['detail_synced_at'] ?? null);
    if (!$source || $source > $now + 60 || $now - $source > self::ORDER_FRESHNESS) return null;
    $status = strtolower(trim((string)($order['status_type'] ?? '')));
    $open = ['perlu dikirim', 'ready_to_ship', 'ready to ship', 'unshipped'];
    $closed = ['shipped','sudah kirim','telah dikirim','delivered','pesanan diterima','order received','completed','cancelled','canceled','dibatalkan','belum bayar','unpaid'];
    if (in_array($status, $closed, true)) return false;
    if (!in_array($status, $open, true)) return null;
    $deadline = filter_var($order['ship_by_date'] ?? null, FILTER_VALIDATE_INT);
    if (!$deadline || $deadline < 946684800 || $deadline > $now + 366 * 86400) return null;
    $left = $deadline - $now;
    if ($left > self::SHIP_WARNING) return false;
    $stage = $left <= 0 ? 2 : ($left <= self::SHIP_URGENT ? 1 : 0);
    return ['severity'=>$stage ? 'urgent' : 'warning','stage'=>$stage,
      'title'=>$stage === 2 ? 'Batas kirim terlewati' : 'Pesanan mendekati batas kirim',
      'message'=>'Pesanan '.($order['order_sn'] ?: '#'.$order['id']).' masih perlu dikirim.',
      'action_label'=>'Buka pesanan','path'=>'/panel/orders?shop_id='.(int)$order['shop_id'].'&order_id='.(int)$order['id'].'#order-row-'.(int)$order['id'],
      'source_at'=>gmdate('Y-m-d H:i:s',$source),'valid_until'=>gmdate('Y-m-d H:i:s',$source+self::ORDER_FRESHNESS),'deadline'=>$deadline,'icon'=>'local_shipping'];
  }

  public static function sync(array $row, int $now) {
    if (empty($row['enabled'])) return false;
    $success = self::utc($row['last_success_at'] ?? null);
    $origin = $success ?? self::utc($row['first_job_at'] ?? null);
    if (!$origin || $origin > $now) return null;
    $interval = max(30, (int)$row['interval_seconds']);
    $warning = max(900, $interval * 3);
    $urgent = max(3600, $interval * 6);
    if ($now-$origin < $warning) return false;
    $progress = self::utc($row['progress_at'] ?? null);
    if ($progress && $progress <= $now+60 && $now-$progress < $warning) return false;
    $module = self::MODULES[$row['sync_type']] ?? $row['sync_type'];
    return ['severity'=>$now-$origin >= $urgent ? 'urgent':'warning','stage'=>0,
      'title'=>'Pembaruan '.strtolower($module).' tertunda',
      'message'=>$success ? 'Belum ada pembaruan berhasil melewati toleransi jadwal.' : 'Sinkronisasi belum pernah berhasil.',
      'action_label'=>'Periksa sinkronisasi','path'=>'/panel/sync?shop_id='.(int)$row['shop_id'],
      'source_at'=>gmdate('Y-m-d H:i:s',$now),'last_success_at'=>$row['last_success_at'], 'icon'=>'sync_problem'];
  }
}
