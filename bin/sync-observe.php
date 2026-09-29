<?php

declare(strict_types=1);

chdir(__DIR__ . '/../public');
require_once '../app/init.php';
$options = getopt('', ['watch-seconds:', 'interval:']);
$duration = max(0, min(86400, (int)($options['watch-seconds'] ?? 0)));
$interval = max(30, min(3600, (int)($options['interval'] ?? 60)));
$end = time() + $duration;
do {
  try {
    $db = new Database();
    $sample = ['at_utc' => gmdate('c'), 'revision' => trim((string)@file_get_contents(__DIR__ . '/../.git/refs/heads/main'))];
    $queries = [
      'jobs' => "SELECT sync_type,mode,status,COUNT(*) total,MAX(TIMESTAMPDIFF(SECOND,updated_at,NOW())) oldest_turn_seconds
        FROM sync_jobs WHERE status IN ('queued','running') GROUP BY sync_type,mode,status",
      'details' => 'SELECT status,COUNT(*) total FROM sync_job_orders GROUP BY status',
      'expired_leases' => "SELECT COUNT(*) total FROM sync_job_orders WHERE status='running' AND lease_until<NOW()",
      'recent_results' => "SELECT sync_type,mode,status,COUNT(*) total,MAX(TIMESTAMPDIFF(SECOND,created_at,completed_at)) longest_seconds
        FROM sync_jobs WHERE completed_at>=DATE_SUB(NOW(),INTERVAL 15 MINUTE) GROUP BY sync_type,mode,status",
      'schedules' => "SELECT shop_id,sync_type,enabled,interval_seconds,last_success_at,last_full_at,
        CASE WHEN last_error IS NULL THEN 'none' WHEN last_error LIKE '%forbidden%' THEN 'forbidden'
          WHEN last_error LIKE '%90309999%' THEN 'ads_access' ELSE 'error' END error_kind
        FROM sync_schedules ORDER BY shop_id,sync_type",
      'recent_details' => 'SELECT shop_id,COUNT(*) total FROM orders WHERE detail_synced_at>=DATE_SUB(NOW(),INTERVAL 15 MINUTE) GROUP BY shop_id'
    ];
    foreach ($queries as $key => $sql) {
      $db->query($sql);
      $sample[$key] = $db->getAll();
    }
    fwrite(STDOUT, json_encode($sample, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL);
    fflush(STDOUT);
  } catch (Throwable $error) {
    fwrite(STDERR, json_encode(['at_utc'=>gmdate('c'), 'error_class'=>get_class($error)]) . PHP_EOL);
  }
  $remaining = $end - time();
  if ($remaining <= 0) break;
  sleep(min($interval, $remaining));
} while (true);
