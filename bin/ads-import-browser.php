<?php

if (PHP_SAPI !== 'cli') exit(1);
$options = getopt('', ['shop:', 'file:', 'dry-run']);
$shopId = filter_var($options['shop'] ?? null, FILTER_VALIDATE_INT);
$file = $options['file'] ?? null;
if (!$shopId || $shopId < 1 || !is_string($file) || !is_file($file) || filesize($file) > 2 * 1024 * 1024) {
  fwrite(STDERR, "Usage: php bin/ads-import-browser.php --shop=LOCAL_ID --file=CAPTURE.json [--dry-run]\n");
  exit(1);
}
$contents = file_get_contents($file);
chdir(__DIR__ . '/../public');
require_once __DIR__ . '/../app/init.php';
require_once __DIR__ . '/../app/helpers/AdsBrowserCapture.php';
require_once __DIR__ . '/../app/models/AdsBrowserReport.php';
try {
  $bundle = json_decode($contents, true, 64, JSON_THROW_ON_ERROR);
  if (!is_array($bundle)) throw new InvalidArgumentException('Capture harus berupa objek JSON.');
  $db = new Database();
  $db->query('SELECT id, shop_id FROM shops WHERE id = :id');
  $db->bind('id', $shopId);
  $shop = $db->single();
  if (!$shop) throw new InvalidArgumentException('Toko tujuan tidak ditemukan.');
  $report = AdsBrowserCapture::normalize($bundle, (int)$shop['shop_id']);
  if (!isset($options['dry-run'])) {
    $store = new AdsBrowserReport();
    $store->ensureSchema();
    $store->save($shopId, $report);
  }
  echo json_encode([
    'status' => isset($options['dry-run']) ? 'validated' : 'import_processed',
    'local_shop_id' => $shopId, 'source_shop_id' => $report['source_shop_id'],
    'channel' => $report['channel'], 'start_date' => $report['start_date'], 'end_date' => $report['end_date'],
    'captured_at' => $report['fetched_at'], 'metrics' => array_intersect_key($report, array_flip(AdsPerformance::supportedMetrics($report['channel'])))
  ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
} catch (InvalidArgumentException | JsonException $error) {
  fwrite(STDERR, $error->getMessage() . PHP_EOL);
  exit(1);
} catch (Throwable $error) {
  fwrite(STDERR, "Import gagal; periksa koneksi dan schema database.\n");
  exit(1);
}
