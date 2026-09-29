<?php

class OrderSyncPolicy {
  public static function needsDetail(array $row, $now = null) {
    $now = $now ?? time();
    $retry = empty($row['sync_next_retry_at']) ? 0 : strtotime($row['sync_next_retry_at'] . ' UTC');
    if ($retry > $now) return false;
    $synced = empty($row['detail_synced_at']) ? 0 : strtotime($row['detail_synced_at'] . ' UTC');
    $terminal = in_array(strtolower(trim((string)($row['status_type'] ?? ''))), ['completed', 'delivered', 'order received', 'order_received', 'cancelled', 'canceled'], true);
    return !$synced || $now - $synced >= ($terminal ? 86400 : 180);
  }

  public static function continueIndex($mode, array $ids, array $existing, $next, $now = null) {
    if ($next === '' || !$ids) return false;
    $now = $now ?? time();
    $timestamp = (int)explode(',', $next)[0];
    if ($timestamp > 0 && $timestamp < strtotime('-3 months', $now)) return false;
    if ($mode === 'full') return true;
    foreach ($ids as $id) {
      $row = $existing[(string)$id] ?? [];
      $created = empty($row['created_at']) ? 0 : strtotime($row['created_at'] . ' UTC');
      if (!$created || $created >= $now - 172800 || empty($row['detail_synced_at'])) return true;
    }
    return false;
  }

  public static function queueActive($db, $sync, array $job) {
    $db->query("SELECT o.id FROM orders o WHERE o.shop_id = :shop_id AND o.deleted_at IS NULL
      AND (o.status_type IS NULL OR LOWER(TRIM(o.status_type)) NOT IN ('completed','delivered','order received','order_received','cancelled','canceled'))
      AND (o.detail_synced_at IS NULL OR o.detail_synced_at <= DATE_SUB(NOW(), INTERVAL 180 SECOND))
      AND (o.sync_next_retry_at IS NULL OR o.sync_next_retry_at <= NOW())
      AND NOT EXISTS (SELECT 1 FROM sync_job_orders t JOIN sync_jobs j ON j.id = t.job_id
        WHERE t.order_id = o.id AND j.status IN ('queued','running') AND j.mode = 'diff'
        AND t.status IN ('queued','running','retry'))
      ORDER BY COALESCE(o.detail_synced_at, '1970-01-01'), o.id LIMIT 100");
    $db->bind('shop_id', (int)$job['shop_id']);
    foreach ($db->getAll() as $row) $sync->queueOrder((int)$job['id'], (int)$row['id']);
  }
}
