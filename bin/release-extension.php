<?php

require_once __DIR__ . '/../app/models/ExtensionRelease.php';

function publishExtensionRelease(string $root, string $source, array $notes): array {
  ExtensionRelease::validate($notes);
  if (!class_exists('ZipArchive')) throw new RuntimeException('Ekstensi PHP zip diperlukan untuk membuat rilis.');
  $manifest = json_decode(file_get_contents($source . '/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
  if (($manifest['name'] ?? '') !== 'Sellerio Get Cookies' || ($manifest['version'] ?? '') !== $notes['version']) {
    throw new InvalidArgumentException('Nama dan versi manifest harus sesuai dengan catatan rilis Sellerio.');
  }
  $files = ['manifest.json', 'popup.html', 'popup.css', 'popup.js', 'background.js', 'jquery.min.js', 'timuid.js', 'timuid.css', 'icon.svg', 'icon.png', 'icon_32.png', 'icon_48.png', 'icon_128.png', 'README.md'];
  foreach ($files as $file) {
    if (!is_file($source . '/' . $file) || is_link($source . '/' . $file)) throw new RuntimeException('File paket tidak tersedia: ' . $file);
  }
  foreach (['tmp', 'resources/extensions/releases', 'public/downloads/extensions'] as $directory) {
    if (!is_dir($root . '/' . $directory) && !mkdir($root . '/' . $directory, 0755, true)) throw new RuntimeException('Direktori rilis tidak dapat dibuat.');
  }
  $lock = fopen($root . '/tmp/extension-release.lock', 'c');
  if (!$lock || !flock($lock, LOCK_EX)) throw new RuntimeException('Proses rilis tidak dapat dikunci.');
  $archive = $root . '/public/downloads/extensions/' . ExtensionRelease::filename($notes['version']);
  $metadata = $root . '/resources/extensions/releases/' . $notes['version'] . '.json';
  $temporary = null;
  $published = false;
  try {
    if (file_exists($archive) || file_exists($metadata)) {
      throw new RuntimeException('Versi ' . $notes['version'] . ' sudah diarsipkan. Gunakan versi baru; arsip lama tidak boleh ditimpa.');
    }
    $temporary = tempnam($root . '/tmp', 'extension-');
    $zip = new ZipArchive();
    if ($zip->open($temporary, ZipArchive::OVERWRITE) !== true) throw new RuntimeException('ZIP tidak dapat dibuat.');
    foreach ($files as $file) {
      if (!$zip->addFile($source . '/' . $file, 'Sellerio Get Cookies/' . $file)) throw new RuntimeException('File tidak dapat dimasukkan ke ZIP.');
    }
    if (!$zip->close()) throw new RuntimeException('ZIP tidak dapat disimpan.');
    $release = [
      'version' => $notes['version'], 'name' => trim($notes['name']), 'date' => $notes['date'],
      'changes' => $notes['changes'], 'size' => filesize($temporary), 'sha256' => hash_file('sha256', $temporary)
    ];
    $json = json_encode($release, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
    if (!rename($temporary, $archive)) throw new RuntimeException('ZIP tidak dapat dipublikasikan.');
    $published = true;
    chmod($archive, 0644);
    if (file_put_contents($metadata, $json, LOCK_EX) === false) throw new RuntimeException('Catatan rilis tidak dapat disimpan.');
    return $release;
  } catch (Throwable $error) {
    if ($published) unlink($archive);
    throw $error;
  } finally {
    if ($temporary && is_file($temporary)) unlink($temporary);
    flock($lock, LOCK_UN);
    fclose($lock);
  }
}

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
  try {
    $options = getopt('', ['notes:']);
    if (empty($options['notes']) || !is_file($options['notes'])) throw new InvalidArgumentException('Penggunaan: php bin/release-extension.php --notes=path/catatan-rilis.json');
    $notes = json_decode(file_get_contents($options['notes']), true, 512, JSON_THROW_ON_ERROR);
    $root = dirname(__DIR__);
    $release = publishExtensionRelease($root, $root . '/Sellerio Get Cookies', $notes);
    echo 'Rilis ' . $release['version'] . ' · ' . $release['name'] . " berhasil diarsipkan. Versi sebelumnya tetap tersedia.\n";
  } catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
  }
}
