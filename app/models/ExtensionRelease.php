<?php

class ExtensionRelease {
  private string $root;

  public function __construct(?string $root = null) {
    $this->root = $root ?? dirname(__DIR__, 2);
  }

  public static function validate(array $release): void {
    if (!is_string($release['version'] ?? null) || !preg_match('/^\d+\.\d+\.\d+$/D', $release['version'])) {
      throw new InvalidArgumentException('Versi harus berupa angka major.minor.patch.');
    }
    if (!is_string($release['name'] ?? null) || trim($release['name']) === '') {
      throw new InvalidArgumentException('Setiap rilis harus memiliki nama.');
    }
    $date = is_string($release['date'] ?? null) ? DateTimeImmutable::createFromFormat('!Y-m-d', $release['date']) : false;
    if (!$date || $date->format('Y-m-d') !== $release['date']) {
      throw new InvalidArgumentException('Tanggal rilis harus berupa YYYY-MM-DD yang valid.');
    }
    if (empty($release['changes']) || !is_array($release['changes']) || !array_is_list($release['changes'])) {
      throw new InvalidArgumentException('Rilis harus memiliki catatan perubahan.');
    }
    foreach ($release['changes'] as $change) {
      if (!is_array($change) || !is_string($change['title'] ?? null) || trim($change['title']) === '' || !is_string($change['description'] ?? null) || trim($change['description']) === '') {
        throw new InvalidArgumentException('Setiap perubahan harus memiliki judul dan penjelasan.');
      }
    }
  }

  public static function filename(string $version): string {
    return 'sellerio-get-cookies-' . $version . '.zip';
  }

  public function all(): array {
    $directory = $this->root . '/resources/extensions/releases';
    if (!is_dir($directory)) return [];
    $releases = [];
    foreach (new DirectoryIterator($directory) as $file) {
      if (!$file->isFile() || str_starts_with($file->getFilename(), '.') || $file->getExtension() !== 'json') continue;
      $release = json_decode(file_get_contents($file->getPathname()), true, 512, JSON_THROW_ON_ERROR);
      if (!is_array($release)) throw new RuntimeException('Metadata rilis tidak valid.');
      self::validate($release);
      if ($file->getBasename('.json') !== $release['version'] || !is_int($release['size'] ?? null) || $release['size'] <= 0 || !is_string($release['sha256'] ?? null) || !preg_match('/^[a-f0-9]{64}$/D', $release['sha256'])) {
        throw new RuntimeException('Metadata arsip tidak valid.');
      }
      $release['filename'] = self::filename($release['version']);
      $release['url'] = '/downloads/extensions/' . $release['filename'];
      $archive = $this->root . '/public' . $release['url'];
      $release['available'] = is_file($archive) && filesize($archive) === $release['size'] && hash_equals($release['sha256'], hash_file('sha256', $archive));
      $releases[] = $release;
    }
    usort($releases, fn($a, $b) => version_compare($b['version'], $a['version']));
    return $releases;
  }
}
