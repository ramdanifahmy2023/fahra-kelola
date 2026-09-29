<?php

class AiConnectionError extends RuntimeException {
  public array $fields;
  public function __construct(string $message, int $status = 422, array $fields = []) {
    parent::__construct($message, $status);
    $this->fields = $fields;
  }
}

class AiConnectionSettings {
  public static function value(string $key, string $default = ''): string {
    $value = getenv($key);
    if ($value !== false) return $value;
    static $env;
    $env ??= parse_ini_file(__DIR__ . '/../../config/.env') ?: [];
    return (string)($env[$key] ?? $default);
  }

  public static function schema(Database $db): void {
    foreach (['20260929_automation_profiles.sql', '20260929_ai_connections.sql'] as $file) {
      foreach (explode(';', file_get_contents(__DIR__ . '/../../database/migrations/' . $file)) as $sql) {
        if (trim($sql) !== '') { $db->query($sql); $db->exe(); }
      }
    }
    $db->query("SHOW COLUMNS FROM automation_profiles LIKE 'connection_id'");
    if (!$db->single()) {
      try {
        $db->query('ALTER TABLE automation_profiles ADD COLUMN connection_id INT NULL, ADD INDEX automation_connection (connection_id)');
        $db->exe();
      } catch (PDOException $error) {
        if (($error->errorInfo[1] ?? 0) !== 1060) throw $error;
      }
    }
  }
}

class AiConnectionSecret {
  private array $ring;
  public static function path(): string {
    return trim(AiConnectionSettings::value('AI_CONNECTION_KEY_FILE')) ?: __DIR__ . '/../../storage/ai-connection-keys.json';
  }
  public function __construct(?array $ring = null) {
    if (!extension_loaded('sodium')) throw new AiConnectionError('Enkripsi server belum tersedia. Hubungi pengelola server.', 503);
    if ($ring === null) {
      $path = self::path();
      $ring = is_readable($path) ? json_decode(file_get_contents($path), true) : null;
    }
    if (!is_array($ring) || !is_string($ring['active'] ?? null) || !isset($ring['keys'][$ring['active']])) {
      throw new AiConnectionError('Kunci enkripsi server belum siap. Pengelola perlu menjalankan php bin/ai-connection-key.php --init.', 503);
    }
    foreach ($ring['keys'] as $id => $encoded) {
      $key = is_string($encoded) ? base64_decode($encoded, true) : false;
      if (!preg_match('/^[a-zA-Z0-9_-]{1,40}$/', (string)$id) || $key === false || strlen($key) !== 32) {
        throw new AiConnectionError('Kunci enkripsi server tidak valid.', 503);
      }
    }
    $this->ring = $ring;
  }
  public function encrypt(string $value, int $id): array {
    $version = $this->ring['active'];
    $nonce = random_bytes(SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);
    $cipher = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt($value, 'ai-connection:' . $id . ':v1', $nonce, base64_decode($this->ring['keys'][$version]));
    return ['key_ciphertext'=>base64_encode($cipher), 'key_nonce'=>base64_encode($nonce), 'key_version'=>$version];
  }
  public function decrypt(array $row): string {
    $encoded = $this->ring['keys'][$row['key_version']] ?? null;
    $nonce = base64_decode((string)$row['key_nonce'], true);
    $cipher = base64_decode((string)$row['key_ciphertext'], true);
    if (!$encoded || $nonce === false || strlen($nonce) !== 24 || $cipher === false) throw new AiConnectionError('Credential tidak dapat dibuka. Periksa kunci enkripsi server.', 503);
    $value = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt($cipher, 'ai-connection:' . $row['id'] . ':v1', $nonce, base64_decode($encoded));
    if ($value === false) throw new AiConnectionError('Credential tidak dapat dibuka. Periksa kunci enkripsi server.', 503);
    return $value;
  }
}
