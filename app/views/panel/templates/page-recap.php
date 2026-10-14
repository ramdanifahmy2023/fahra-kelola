<?php
$recap = is_array($data['page_recap'] ?? null) ? $data['page_recap'] : [];
if (!$recap) return;
$e = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
$formatValue = static function ($value, string $format = 'number'): string {
  if ($value === null || $value === '') return 'Belum tersedia';
  if ($format === 'money') return 'Rp ' . number_format((float)$value, 0, ',', '.');
  if ($format === 'percent') return number_format((float)$value, 1, ',', '.') . '%';
  return number_format((float)$value, 0, ',', '.');
};
$segments = array_values(array_filter($recap['segments'] ?? [], static fn($item) => is_array($item) && isset($item['value'])));
$segmentTotal = array_sum(array_map(static fn($item) => max(0, (float)$item['value']), $segments));
$trend = array_values(array_filter($recap['trend'] ?? [], static fn($item) => is_array($item) && isset($item['value'])));
$trendPoints = [];
if (count($trend) > 1) {
  $maxValue = max(1, ...array_map(static fn($item) => max(0, (float)$item['value']), $trend));
  $lastIndex = count($trend) - 1;
  foreach ($trend as $index => $point) {
    $x = 4 + (352 * $index / $lastIndex);
    $y = 62 - (52 * max(0, (float)$point['value']) / $maxValue);
    $trendPoints[] = number_format($x, 1, '.', '') . ',' . number_format($y, 1, '.', '');
  }
}
$shops = array_values(array_filter($recap['shops'] ?? [], static fn($item) => is_array($item) && isset($item['value'])));
$shopMax = $shops ? max(1, ...array_map(static fn($item) => max(0, (float)$item['value']), $shops)) : 1;
?>
<section class="page-recap" id="<?= $e($recap['id'] ?? 'page-recap'); ?>" aria-labelledby="<?= $e(($recap['id'] ?? 'page-recap') . '-title'); ?>">
  <header class="page-recap-heading">
    <div>
      <h2 id="<?= $e(($recap['id'] ?? 'page-recap') . '-title'); ?>"><?= $e($recap['title'] ?? 'Ringkasan'); ?></h2>
      <?php if (!empty($recap['description'])): ?><p><?= $e($recap['description']); ?></p><?php endif; ?>
    </div>
    <?php if (!empty($recap['scope'])): ?><p class="page-recap-scope"><?= $e($recap['scope']); ?></p><?php endif; ?>
  </header>

  <div class="page-recap-summary">
    <div class="page-recap-primary">
      <span><?= $e($recap['primary']['label'] ?? 'Total'); ?></span>
      <strong><?= $e($formatValue($recap['primary']['value'] ?? null, $recap['primary']['format'] ?? 'number')); ?></strong>
      <?php if (!empty($recap['primary']['context'])): ?><p><?= $e($recap['primary']['context']); ?></p><?php endif; ?>
      <?php if (count($trendPoints) > 1): ?>
        <figure class="page-recap-trend">
          <svg viewBox="0 0 360 72" width="360" height="72" role="img" aria-labelledby="<?= $e(($recap['id'] ?? 'page-recap') . '-trend-title'); ?>">
            <title id="<?= $e(($recap['id'] ?? 'page-recap') . '-trend-title'); ?>"><?= $e($recap['trend_label'] ?? 'Perubahan harian'); ?></title>
            <polyline points="<?= $e(implode(' ', $trendPoints)); ?>" fill="none" stroke="var(--color-primary)" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" vector-effect="non-scaling-stroke" />
          </svg>
          <figcaption><?= $e($recap['trend_caption'] ?? 'Perubahan per hari'); ?></figcaption>
          <ol class="sr-only">
            <?php foreach ($trend as $point): ?><li><?= $e($point['label'] ?? ''); ?>: <?= $e($formatValue($point['value'])); ?></li><?php endforeach; ?>
          </ol>
        </figure>
      <?php endif; ?>
    </div>

    <?php if (!empty($recap['metrics'])): ?>
      <div class="page-recap-metrics">
        <?php foreach ($recap['metrics'] as $metric): ?>
          <div class="page-recap-metric" data-tone="<?= $e($metric['tone'] ?? 'neutral'); ?>">
            <span><?= $e($metric['label'] ?? ''); ?></span>
            <strong><?= $e(array_key_exists('display', $metric) ? $metric['display'] : $formatValue($metric['value'] ?? null, $metric['format'] ?? 'number')); ?></strong>
            <?php if (!empty($metric['context'])): ?><small><?= $e($metric['context']); ?></small><?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <?php if ($segments): ?>
    <div class="page-recap-visual-row">
      <div class="page-recap-breakdown">
        <h3><?= $e($recap['segments_label'] ?? 'Rincian status'); ?></h3>
        <div class="page-recap-bar" role="img" aria-label="<?= $e(implode(', ', array_map(static fn($item) => ($item['label'] ?? '') . ': ' . number_format((float)$item['value'], 0, ',', '.'), $segments))); ?>">
          <?php foreach ($segments as $segment): $value = max(0, (float)$segment['value']); $share = $segmentTotal > 0 ? ($value / $segmentTotal) * 100 : 0; ?>
            <span data-tone="<?= $e($segment['tone'] ?? 'neutral'); ?>" style="--page-recap-share:<?= number_format($share, 4, '.', ''); ?>%"></span>
          <?php endforeach; ?>
        </div>
        <ul class="page-recap-legend">
          <?php foreach ($segments as $segment): ?>
            <li>
              <?php if (!empty($segment['href'])): ?><a href="<?= $e($segment['href']); ?>" class="page-recap-segment-link" aria-label="Tampilkan <?= $e($segment['label'] ?? ''); ?>: <?= $e($formatValue($segment['value'])); ?>">
              <?php else: ?><span class="page-recap-segment-link">
              <?php endif; ?>
                <span class="page-recap-swatch" data-tone="<?= $e($segment['tone'] ?? 'neutral'); ?>" aria-hidden="true"></span>
                <span><?= $e($segment['label'] ?? ''); ?></span>
                <strong><?= $e($formatValue($segment['value'])); ?></strong>
              <?php if (!empty($segment['href'])): ?></a><?php else: ?></span><?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>

      <?php if ($shops): ?>
        <div class="page-recap-shop-rank">
          <h3><?= $e($recap['shops_label'] ?? 'Perbandingan toko'); ?></h3>
          <ol>
            <?php foreach ($shops as $shop): $width = max(0, min(100, ((float)$shop['value'] / $shopMax) * 100)); ?>
              <li>
                <div class="page-recap-shop-line"><span><?= $e($shop['label'] ?? 'Toko'); ?></span><strong><?= $e($formatValue($shop['value'], $shop['format'] ?? 'number')); ?></strong></div>
                <div class="page-recap-shop-track" aria-hidden="true"><span style="width:<?= number_format($width, 3, '.', ''); ?>%"></span></div>
              </li>
            <?php endforeach; ?>
          </ol>
        </div>
      <?php endif; ?>
    </div>
  <?php elseif ($shops): ?>
    <div class="page-recap-shop-rank page-recap-shop-rank-only">
      <h3><?= $e($recap['shops_label'] ?? 'Perbandingan toko'); ?></h3>
      <ol>
        <?php foreach ($shops as $shop): $width = max(0, min(100, ((float)$shop['value'] / $shopMax) * 100)); ?>
          <li><div class="page-recap-shop-line"><span><?= $e($shop['label'] ?? 'Toko'); ?></span><strong><?= $e($formatValue($shop['value'], $shop['format'] ?? 'number')); ?></strong></div><div class="page-recap-shop-track" aria-hidden="true"><span style="width:<?= number_format($width, 3, '.', ''); ?>%"></span></div></li>
        <?php endforeach; ?>
      </ol>
    </div>
  <?php endif; ?>

  <?php if (!empty($recap['insight'])): ?><p class="page-recap-insight"><span class="material-symbols-outlined" aria-hidden="true">info</span><?= $e($recap['insight']); ?></p><?php endif; ?>
</section>
