<?php
$cardId=(int)$ord['id'];$cardStatus=trim((string)($ord['status_type'] ?? ''));
$statusLabels=['completed'=>'Selesai','cancelled'=>'Dibatalkan','unpaid'=>'Belum dibayar','to ship'=>'Perlu dikirim','shipped'=>'Dikirim','shipping'=>'Dikirim','delivered'=>'Terkirim','order received'=>'Diterima'];
$cardStatus=$statusLabels[strtolower($cardStatus)] ?? ($cardStatus ?: 'Status belum tersedia');
$cardItems=$ord['items'] ?? [];$cardFirst=$cardItems[0] ?? [];
$cardImage=$cardFirst['image'] ?? '';
if ($cardImage && !preg_match('#^https?://#',$cardImage)) $cardImage='https://cf.shopee.co.id/file/'.$cardImage;
$cardDeadline=(int)($ord['ship_by_date'] ?? 0);
$cardOpen=in_array(strtolower(trim($ord['status_type'] ?? '')),['perlu dikirim','to ship','ready_to_ship','ready to ship','unshipped'],true);
$cardEscape=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
?>
<article class="order-mobile-card" id="order-card-<?= $cardId; ?>">
  <div class="order-card-top"><h3><?= $cardEscape($ord['order_sn'] ?: '#'.$cardId); ?></h3><span class="order-card-status"><?= $cardEscape($cardStatus); ?></span></div>
  <div class="order-card-product">
    <?php if ($cardImage && preg_match('#^https?://#',$cardImage)): ?><img src="<?= $cardEscape($cardImage); ?>" alt="" width="48" height="48" loading="lazy"><?php else: ?><span class="order-card-image material-symbols-outlined" aria-hidden="true">inventory_2</span><?php endif; ?>
    <div><p><?= $cardEscape($cardFirst['name'] ?? 'Detail produk belum tersedia'); ?></p><?php if ($cardFirst): ?><p class="order-card-meta"><?= $cardEscape($cardFirst['variation_name'] ?? ''); ?> · <?= (int)($cardFirst['quantity'] ?? 1); ?> buah<?= count($cardItems)>1?' · +'.(count($cardItems)-1).' produk':''; ?></p><?php endif; ?></div>
  </div>
  <div class="order-card-summary"><strong><?= isset($ord['total_price'])?'Rp '.number_format($ord['total_price'],0,',','.'):'Nominal belum tersedia'; ?></strong><span><?= !empty($ord['created_at']) && !str_starts_with($ord['created_at'],'0000')?$cardEscape(date('d M Y',strtotime($ord['created_at']))):'Tanggal belum tersedia'; ?></span></div>
  <?php if ($cardOpen && $cardDeadline>946684800): ?><p class="order-card-deadline">Batas kirim <?= $cardEscape((new DateTimeImmutable('@'.$cardDeadline))->setTimezone(new DateTimeZone('Asia/Jakarta'))->format('d M, H.i')); ?> WIB</p><?php endif; ?>
  <details><summary>Lihat rincian pesanan</summary>
    <dl class="order-card-facts"><div><dt>Nomor pesanan</dt><dd><?= $cardEscape($ord['order_sn'] ?: '#'.$cardId); ?></dd></div><div><dt>Kurir</dt><dd><?= $cardEscape(($ord['shipping_cargo_label'] ?? $ord['shipping_cargo']) ?: 'Belum tersedia'); ?></dd></div><div><dt>Resi</dt><dd><?= $cardEscape($ord['tracking_number'] ?: 'Belum tersedia'); ?></dd></div><div><dt>Pembayaran</dt><dd><?= $cardEscape(($ord['payment_method_label'] ?? $ord['payment_method']) ?: 'Belum tersedia'); ?></dd></div><div><dt>Detail diperbarui</dt><dd><?= !empty($ord['detail_synced_at'])?$cardEscape((new DateTimeImmutable($ord['detail_synced_at'],new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('Asia/Jakarta'))->format('d M Y, H.i')).' WIB':'Belum tersedia'; ?></dd></div></dl>
    <?php if (!empty($ord['tracking_number'])): ?><button type="button" class="btn order-copy-tracking" data-tracking="<?= $cardEscape($ord['tracking_number']); ?>">Salin resi</button><?php endif; ?>
    <?php if (!empty($ord['status_description'])): ?><p><?= $cardEscape($ord['status_description']); ?></p><?php endif; ?>
    <ul class="order-card-items"><?php foreach ($cardItems as $cardItem): ?><li><strong><?= $cardEscape($cardItem['name'] ?? 'Produk'); ?></strong><span><?= $cardEscape($cardItem['variation_name'] ?? ''); ?> · <?= (int)($cardItem['quantity'] ?? 1); ?> buah</span></li><?php endforeach; ?></ul>
  </details>
</article>
