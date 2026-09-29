<?php
require_once __DIR__ . '/../app/helpers/AiConnectionSupport.php';
$action = $argv[1] ?? '';
if (!in_array($action, ['--init', '--rotate'], true)) { fwrite(STDERR, "Gunakan --init atau --rotate. Rotasi mempertahankan key lama untuk dekripsi.\n"); exit(1); }
$path = AiConnectionSecret::path();
if (!is_dir(dirname($path)) && !mkdir(dirname($path), 0700, true)) exit(1);
$lock = fopen($path . '.lock', 'c');
chmod($path . '.lock', 0600);
flock($lock, LOCK_EX);
try {
  if ($action === '--init' && file_exists($path)) throw new RuntimeException('Key file sudah ada; tidak diubah.');
  if ($action === '--rotate' && !is_file($path)) throw new RuntimeException('Key file belum ada; gunakan --init.');
  $ring = $action === '--rotate' ? json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR) : ['keys'=>[]];
  if ($action === '--rotate') new AiConnectionSecret($ring);
  $id = bin2hex(random_bytes(8));
  $ring['active'] = $id;
  $ring['keys'][$id] = base64_encode(random_bytes(32));
  $tmp = tempnam(dirname($path), '.ai-key-'); chmod($tmp, 0600);
  if (file_put_contents($tmp, json_encode($ring, JSON_THROW_ON_ERROR)) === false || !rename($tmp, $path)) throw new RuntimeException('Tidak dapat menyimpan key file.');
  echo "Kunci enkripsi siap. Backup key file secara terpisah dari database; jangan masukkan ke Git.\n";
} catch (Throwable $error) { fwrite(STDERR, $error->getMessage() . "\n"); exit(1); }
finally { flock($lock, LOCK_UN); fclose($lock); }
