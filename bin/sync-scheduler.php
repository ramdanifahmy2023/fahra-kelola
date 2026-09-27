<?php

declare(strict_types=1);

chdir(__DIR__ . '/../public');
require_once '../app/init.php';
require_once '../app/models/SyncJob.php';
require_once '../app/models/BackgroundSync.php';

$options = getopt('', ['limit::']);
$limit = max(1, min(200, (int)($options['limit'] ?? 50)));
$scheduler = new BackgroundSync();
$jobs = $scheduler->enqueueDue($limit);

fwrite(STDOUT, json_encode([
  'scheduled_at' => date('c'),
  'enqueued' => count($jobs),
  'jobs' => $jobs
], JSON_UNESCAPED_UNICODE) . PHP_EOL);
