<?php

/** Shared interpretation of the English and Indonesian order statuses stored locally. */
final class OrderStatusPolicy {
  private const CATEGORIES = [
    'waiting' => [
      'unpaid', 'belum bayar', 'belum dibayar', 'pending',
      'to ship', 'ready to ship', 'unshipped', 'perlu dikirim'
    ],
    'processing' => [
      'processed', 'shipping', 'shipped', 'in transit',
      'dalam pengiriman', 'sedang dikirim', 'sedang dikemas',
      'sudah kirim', 'telah dikirim'
    ],
    'completed' => [
      'completed', 'delivered', 'terkirim', 'order received',
      'to confirm receive', 'selesai', 'pesanan diterima', 'diterima'
    ],
    'cancelled' => ['cancelled', 'canceled', 'dibatalkan']
  ];

  public static function normalize(string $status): string {
    $value = strtolower(trim(str_replace('_', ' ', $status)));
    return preg_replace('/\s+/u', ' ', $value) ?? $value;
  }

  public static function category(string $status): ?string {
    $value = self::normalize($status);
    if ($value === '') return null;
    foreach (self::CATEGORIES as $category => $aliases) {
      if (in_array($value, $aliases, true)) return $category;
    }
    return null;
  }

  public static function isTerminal(string $status): bool {
    return in_array(self::category($status), ['completed', 'cancelled'], true);
  }

  public static function syncState(string $status): string {
    return match (self::category($status)) {
      'completed' => 'completed',
      'cancelled' => 'cancelled',
      'waiting', 'processing' => 'active',
      default => 'pending',
    };
  }

  public static function sql(string $column, string $category): string {
    if (!preg_match('/^[A-Za-z_][A-Za-z0-9_.]*$/D', $column)) {
      throw new InvalidArgumentException('Kolom status pesanan tidak valid.');
    }
    if (!isset(self::CATEGORIES[$category])) {
      throw new InvalidArgumentException('Kategori status pesanan tidak valid.');
    }
    $aliases = array_map([self::class, 'normalize'], self::CATEGORIES[$category]);
    $values = implode(',', array_map(static fn(string $value): string => "'" . str_replace("'", "''", $value) . "'", $aliases));
    $normalizedColumn = "LOWER(TRIM(REPLACE(COALESCE({$column},''),'_',' ')))";
    return "{$normalizedColumn} IN ({$values})";
  }

  public static function knownSql(string $column): string {
    $groups = [];
    foreach (array_keys(self::CATEGORIES) as $category) $groups[] = self::sql($column, $category);
    return '(' . implode(' OR ', $groups) . ')';
  }

  public static function terminalSql(string $column): string {
    return '(' . self::sql($column, 'completed') . ' OR ' . self::sql($column, 'cancelled') . ')';
  }
}
