<?php
require_once __DIR__ . '/../bin/release-extension.php';

function checkRelease(bool $condition, string $message): void {
  if (!$condition) throw new RuntimeException($message);
}
function rejectsRelease(callable $action, string $message): void {
  try { $action(); } catch (InvalidArgumentException | RuntimeException $error) { return; }
  throw new RuntimeException($message);
}
$root = sys_get_temp_dir() . '/sellerio-releases-' . bin2hex(random_bytes(6));
$source = $root . '/source';
mkdir($source, 0755, true);
try {
  foreach (glob(__DIR__ . '/../Sellerio Get Cookies/*') as $file) {
    if (is_file($file)) copy($file, $source . '/' . basename($file));
  }
  file_put_contents($source . '/._metadata', 'Excluded test fixture');
  file_put_contents($source . '/private-test.txt', 'Excluded test fixture');
  $notes = ['version' => '2.1.0', 'name' => 'Rilis fixture pertama', 'date' => '2026-09-29', 'changes' => [['title' => 'Perubahan fixture', 'description' => 'Catatan pengujian.']]];
  $first = publishExtensionRelease($root, $source, $notes);
  file_put_contents($root . '/resources/extensions/releases/._2.1.0.json', 'Ignored macOS metadata');
  $firstPath = $root . '/public/downloads/extensions/' . ExtensionRelease::filename('2.1.0');
  $firstMetadata = $root . '/resources/extensions/releases/2.1.0.json';
  $metadataHash = hash_file('sha256', $firstMetadata);
  rejectsRelease(fn() => publishExtensionRelease($root, $source, $notes), 'Rilis duplikat harus ditolak.');
  checkRelease(hash_file('sha256', $firstPath) === $first['sha256'], 'Rilis duplikat mengubah arsip lama.');
  $zip = new ZipArchive();
  checkRelease($zip->open($firstPath) === true, 'ZIP tidak dapat dibuka.');
  checkRelease($zip->numFiles === 14, 'ZIP harus berisi 14 file paket saja.');
  checkRelease($zip->locateName('Sellerio Get Cookies/._metadata') === false, 'Metadata macOS masuk paket.');
  $packed = json_decode($zip->getFromName('Sellerio Get Cookies/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
  checkRelease($packed['version'] === '2.1.0', 'Manifest dalam ZIP tidak sesuai versi.');
  $zip->close();

  foreach ([['name' => ''], ['date' => '2026-02-31'], ['version' => '../../old'], ['changes' => []], ['changes' => [['title' => '', 'description' => 'Fixture']]]] as $invalid) {
    rejectsRelease(fn() => publishExtensionRelease($root, $source, array_replace($notes, $invalid)), 'Metadata rilis invalid diterima.');
  }
  rejectsRelease(fn() => publishExtensionRelease($root, $source, array_replace($notes, ['version' => '2.1.1'])), 'Versi manifest yang berbeda harus ditolak.');
  $packed['version'] = '2.1.1';
  file_put_contents($source . '/manifest.json', json_encode($packed));
  $second = publishExtensionRelease($root, $source, array_replace($notes, ['version' => '2.1.1', 'name' => 'Rilis fixture kedua']));
  $catalog = new ExtensionRelease($root);
  $releases = $catalog->all();
  checkRelease(array_column($releases, 'version') === ['2.1.1', '2.1.0'], 'Riwayat harus memuat seluruh versi, terbaru dahulu.');
  checkRelease($releases[0]['available'] && $releases[1]['available'], 'Versi lama harus tetap dapat diunduh.');
  checkRelease(hash_file('sha256', $firstPath) === $first['sha256'] && hash_file('sha256', $firstMetadata) === $metadataHash, 'Publikasi baru mengubah rilis lama.');
  $latestPath = $root . '/public' . $releases[0]['url'];
  file_put_contents($latestPath, 'Corrupted test fixture');
  checkRelease(!$catalog->all()[0]['available'] && $catalog->all()[1]['available'], 'Arsip rusak harus dinonaktifkan, versi lain tetap tersedia.');
  unlink($latestPath);
  checkRelease(count($catalog->all()) === 2 && !$catalog->all()[0]['available'], 'Metadata versi hilang harus tetap ditampilkan.');
  $actual = (new ExtensionRelease())->all();
  checkRelease(count($actual) > 0 && !in_array(false, array_column($actual, 'available'), true), 'Arsip proyek harus lengkap dan sesuai checksum.');
  echo "PASS: metadata bernama, ZIP mandiri, versi duplikat ditolak, arsip lama identik setelah rilis baru, urutan versi, arsip rusak/hilang, integritas paket proyek.\n";
} finally {
  $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
  foreach ($files as $file) $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
  rmdir($root);
}
