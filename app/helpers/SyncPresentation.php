<?php
require_once __DIR__.'/NotificationPolicy.php';
final class SyncPresentation {
  public static function describe(array $row,int $now): array {
    $status=$row['job_status'] ?? null;$enabled=!empty($row['enabled']);
    $total=(int)($row['detail_total'] ?? 0);$done=(int)($row['detail_done'] ?? 0);$failed=(int)($row['detail_failed'] ?? 0);
    $orders=($row['sync_type'] ?? '')==='orders';$indexClosed=$orders && isset($row['job_page_number']) && (int)$row['job_page_number']===0;
    $retry=NotificationPolicy::utc($row['job_next_retry_at'] ?? null);$error=($row['job_error'] ?? '') ?: ($row['last_error'] ?? '');
    $issue=NotificationPolicy::accessIssue($error);
    $stage=!$status?'Belum mulai':($status==='completed'?'Selesai':($status==='failed'?'Perlu diperiksa':($status==='queued'?'Menunggu giliran':($orders?($indexClosed?'Memperbarui detail pesanan':'Mencari pesanan'):'Memperbarui data'))));
    $tone=$status==='failed'?'error':(in_array($status,['running','queued'],true)?'active':($status==='completed'?'success':'neutral'));
    if ($error && !in_array($status,['running','queued'],true)) {$stage='Perlu diperiksa';$tone='error';}
    if (!$enabled && !in_array($status,['running','queued'],true)) {$stage='Jadwal dijeda';$tone='neutral';}
    if ($retry && $retry>$now && in_array($status,['running','queued'],true)) {$stage='Menunggu percobaan berikutnya';$tone='warning';}
    $message=$issue==='session'?'Sesi toko perlu diperbarui.':($issue==='access'?'Akses modul dibatasi.':($error?'Pembaruan belum berhasil. Sistem mengikuti jadwal percobaan ulang.':(!$enabled?'Jadwal otomatis dijeda.':(!$status?'Belum ada pekerjaan tersimpan.':'Pekerjaan mengikuti antrean dan jadwal toko.'))));
    $percent=$indexClosed && $total>0 ? min(100,(int)floor(100*$done/$total)):null;
    $eta=null;$latest=NotificationPolicy::utc($row['last_detail_at'] ?? null);$first=NotificationPolicy::utc($row['recent_first_at'] ?? null);$recent=(int)($row['recent_done'] ?? 0);
    if ($enabled && $status==='running' && $indexClosed && $total>$done && $failed===0 && !$error && (!$retry||$retry<=$now) && $recent>=5 && $latest && $latest<=$now+60 && $now-$latest<=120 && $first && $now-$first>=60) {
      $eta=(int)ceil(($total-$done)*min(300,$now-$first)/$recent);
    }
    return ['stage'=>$stage,'tone'=>$tone,'message'=>$message,'percent'=>$percent,'eta_seconds'=>$eta,
      'detail_total'=>$orders?$total:null,'detail_done'=>$orders?$done:null,'detail_failed'=>$orders?$failed:null,
      'detail_remaining'=>$orders?max(0,$total-$done-$failed):null,'index_closed'=>$indexClosed,
      'pages_done'=>(int)($row['pages_done'] ?? 0),'retry_at'=>$retry&&$retry>$now?$row['job_next_retry_at']:null,
      'action_label'=>$issue==='session'?'Perbarui koneksi':($issue==='access'?'Periksa koneksi toko':($error||$status==='failed'?'Coba sinkronkan lagi':'Sinkronkan sekarang')),
      'action_path'=>$issue?'/panel/shops#shop-'.(int)$row['shop_id']:null,
      'can_retry'=>$enabled && !in_array($status,['running','queued'],true) && !$issue];
  }
}
