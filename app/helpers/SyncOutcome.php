<?php

class SyncOutcome {
  public static function ads(array $reports) {
    $failed = 0;
    $firstError = null;
    foreach (['daily', 'weekly', 'monthly'] as $period) {
      foreach (['product', 'shop', 'live'] as $channel) {
        $report = $reports[$period][$channel] ?? [];
        if (empty($report['available']) || !empty($report['stale']) || !empty($report['error_message'])) {
          $failed++;
          $firstError = $firstError ?? ($report['error_message'] ?? 'Laporan belum tersedia.');
        }
      }
    }
    return ['ok' => $failed === 0, 'error' => $failed ? "Meta iklan tersimpan; {$failed}/9 laporan belum berhasil diperbarui. {$firstError}" : null];
  }

  public static function retryDelay($error) {
    return preg_match('/90309999|forbidden|unauthori[sz]ed|sesi|cookie|403/i', (string)$error) ? 900 : 60;
  }
}
