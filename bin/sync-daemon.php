<?php

declare(strict_types=1);

$lockHandle = fopen(sys_get_temp_dir() . '/shopdash-sync.lock', 'c');
if (!$lockHandle || !flock($lockHandle, LOCK_EX | LOCK_NB)) {
  exit(0);
}

$root = dirname(__DIR__);
$php = escapeshellarg(PHP_BINARY);
$scheduler = escapeshellarg($root . '/bin/sync-scheduler.php');
$worker = escapeshellarg($root . '/bin/sync-worker.php');

passthru($php . ' ' . $scheduler . ' --limit=200');
passthru($php . ' ' . $worker . ' --batch=20');

flock($lockHandle, LOCK_UN);
fclose($lockHandle);
