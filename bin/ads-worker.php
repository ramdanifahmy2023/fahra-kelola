<?php
chdir(__DIR__ . '/../public');
require_once __DIR__ . '/../app/init.php';
require_once __DIR__ . '/../app/models/AdsMonitor.php';

$monitor = new AdsMonitor();
$count = $monitor->syncAll();
fwrite(STDOUT, json_encode(['shops_synced' => $count]) . PHP_EOL);
