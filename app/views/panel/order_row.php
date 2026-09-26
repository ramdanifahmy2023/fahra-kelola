<?php
$statusType = $ord['status_type'] ?? '';
$isCompletedOrCancelled = (stripos($statusType, 'Completed') !== false || stripos($statusType, 'Cancelled') !== false);
$needsSync = empty($ord['order_sn'])
  || !$isCompletedOrCancelled
  || empty($ord['shipping_cargo'])
  || empty($ord['tracking_number']);
?>
<tr class="hover" id="order-row-<?= $ord['id'] ?>" <?= $needsSync ? 'data-needs-sync="true"' : '' ?>>
  <td>
    <?php if (empty($ord['created_at']) || $ord['created_at'] === '0000-00-00 00:00:00'): ?>
      <div class="skeleton h-4 w-20 rounded-md"></div>
    <?php else: ?>
      <div class="text-xs font-medium">
        <?= date('d M Y', strtotime($ord['created_at'])); ?>
      </div>
      <div class="text-[11px] opacity-70">
        <?= date('H:i', strtotime($ord['created_at'])); ?>
      </div>
    <?php endif; ?>
  </td>
  <td>
    <?php if (empty($ord['order_sn'])): ?>
      <div class="skeleton h-4 w-32 rounded-md mb-1"></div>
    <?php else: ?>
      <div class="font-bold text-sm">
        <?= htmlspecialchars($ord['order_sn']); ?>
      </div>
    <?php endif; ?>
    <div class="text-[11px] opacity-60 font-mono mt-0.5">
      <?= htmlspecialchars($ord['id']); ?>
    </div>
  </td>
  <td>
     <div class="flex flex-col gap-2 min-w-[200px] max-w-[250px]">
        <?php 
        $items = $ord['items'] ?? [];
        if (empty($items) && empty($ord['order_sn'])): 
        ?>
        <div class="flex items-start gap-2 w-full">
          <div class="skeleton w-8 h-8 rounded shrink-0"></div>
          <div class="flex-1 w-full space-y-1">
            <div class="skeleton h-3 w-full max-w-full rounded-md"></div>
            <div class="skeleton h-2 w-20 rounded-md"></div>
          </div>
        </div>
        <?php else: ?>
        <?php
        foreach ($items as $item): 
            $itemName = $item['name'] ?? 'Produk';
            $itemImg = $item['image'] ?? '';
            $itemQty = $item['quantity'] ?? 1;
            $itemVar = $item['variation_name'] ?? '';
            if (!empty($itemImg)) $itemImg = 'https://cf.shopee.co.id/file/' . $itemImg;
        ?>
        <div class="flex items-start gap-2">
           <?php if($itemImg): ?>
           <img src="<?= htmlspecialchars($itemImg); ?>" class="w-8 h-8 rounded object-cover border border-base-200 shrink-0" />
           <?php else: ?>
           <div class="w-8 h-8 rounded bg-base-200 border border-base-300 shrink-0 flex items-center justify-center">
             <span class="material-symbols-outlined text-[14px] opacity-50">image</span>
           </div>
           <?php endif; ?>
           <div class="flex-1 min-w-0">
             <div class="js-floating-tooltip text-[11px] font-medium leading-tight line-clamp-2" data-tip="<?= htmlspecialchars($itemName); ?>"><?= htmlspecialchars($itemName); ?></div>
             <div class="text-[10px] opacity-70 mt-0.5">
                <?php if($itemVar): ?>Var: <?= htmlspecialchars($itemVar); ?> | <?php endif; ?>Qty: <?= $itemQty; ?>
             </div>
           </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
     </div>
  </td>
  <td>
    <?php if (empty($ord['order_type'])): ?>
      <div class="skeleton h-4 w-16 rounded-md"></div>
    <?php elseif ($ord['order_type'] === 'advance'): ?>
      <div class="font-bold text-sm text-secondary">Pesanan Kilat</div>
      <?php 
      $advSn = $ord['advance_booking_sn'] ?? '';
      if (empty($advSn) && !empty($ord['raw_data'])) {
          $raw = json_decode($ord['raw_data'], true);
          $advSn = $raw['advance_booking_sn'] ?? '';
      }
      if (!empty($advSn)): 
      ?>
        <div class="tooltip tooltip-top text-[11px] opacity-60 font-mono mt-0.5" data-tip="Advance Booking SN"><?= htmlspecialchars($advSn); ?></div>
      <?php endif; ?>
    <?php else: ?>
      <div class="font-medium text-sm">Pesanan Biasa</div>
    <?php endif; ?>
  </td>
  <td class="text-right">
    <?php if (empty($ord['total_price']) && empty($ord['order_sn'])): ?>
      <div class="flex flex-col items-end gap-1">
        <div class="skeleton h-4 w-24 rounded-md"></div>
        <div class="skeleton h-3 w-16 rounded-md"></div>
      </div>
    <?php else: ?>
      <div class="font-semibold text-sm">Rp <?= number_format($ord['total_price'], 0, ',', '.'); ?></div>
      <?php
        $pmTitle = $ord['payment_method'] ?? '';
        if (!empty($pmTitle)) {
            $dbPm = new Database();
            $dbPm->query("SELECT title FROM payment_methods WHERE code = :code");
            $dbPm->bind('code', $pmTitle);
            $pmRow = $dbPm->single();
            if ($pmRow && !empty($pmRow['title'])) {
                $pmTitle = $pmRow['title'];
            }
        }
      ?>
      <div class="text-[11px] opacity-60 font-medium"><?= htmlspecialchars($pmTitle); ?></div>
    <?php endif; ?>
  </td>
  <td>
    <?php if (empty($ord['status_type'])): ?>
      <div class="skeleton h-5 w-20 rounded-full"></div>
    <?php else: ?>
      <?php
      $statusType = $ord['status_type'] ?? '';
      $statusBadge = 'badge-ghost';
      $statusDot = 'bg-base-content opacity-50';

      $displayStatus = $statusType;
      if (strcasecmp($statusType, 'Cancelled') === 0) $displayStatus = 'Dibatalkan';
      elseif (strcasecmp($statusType, 'Completed') === 0) $displayStatus = 'Selesai';
      elseif (strcasecmp($statusType, 'Unpaid') === 0) $displayStatus = 'Belum Dibayar';
      elseif (strcasecmp($statusType, 'To Ship') === 0) $displayStatus = 'Perlu Dikirim';
      elseif (strcasecmp($statusType, 'Shipping') === 0) $displayStatus = 'Dikirim';
      elseif (strcasecmp($statusType, 'Shipped') === 0) $displayStatus = 'Dikirim';
      elseif (strcasecmp($statusType, 'Order Received') === 0) $displayStatus = 'Diterima';
      elseif (strcasecmp($statusType, 'Delivered') === 0) $displayStatus = 'Terkirim';

      if (stripos($statusType, 'Completed') !== false || stripos($statusType, 'Selesai') !== false || stripos($statusType, 'Shipped') !== false || stripos($statusType, 'Kirim') !== false || stripos($statusType, 'Terkirim') !== false || stripos($statusType, 'Delivered') !== false) {
          $statusBadge = 'badge-success text-white';
          $statusDot = 'bg-white opacity-80';
      } elseif (stripos($statusType, 'Order Received') !== false || stripos($statusType, 'Diterima') !== false) {
          $statusBadge = 'badge-info text-white';
          $statusDot = 'bg-white opacity-80';
      } elseif (stripos($statusType, 'Cancel') !== false || stripos($statusType, 'Batal') !== false) {
          $statusBadge = 'badge-error text-white';
          $statusDot = 'bg-white opacity-80';
      } elseif (stripos($statusType, 'Unpaid') !== false || stripos($statusType, 'Bayar') !== false) {
          $statusBadge = 'badge-warning text-white';
          $statusDot = 'bg-white opacity-80';
      } elseif (stripos($statusType, 'To Ship') !== false || stripos($statusType, 'Perlu') !== false) {
          $statusBadge = 'badge-primary text-white';
          $statusDot = 'bg-white opacity-80';
      }
      ?>
      <div class="flex min-w-[150px] flex-col items-start gap-1">
        <span class="badge <?= $statusBadge ?> badge-sm gap-1"><span class="h-1.5 w-1.5 rounded-full <?= $statusDot ?>"></span><?= htmlspecialchars($displayStatus); ?></span>
        <?php if (!empty($ord['status_description'])): ?>
          <div class="js-floating-tooltip max-w-[150px] truncate text-[10px] text-error" data-tip="<?= htmlspecialchars($ord['status_description']); ?>">
            <?= htmlspecialchars($ord['status_description']); ?>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </td>
  <td>
    <?php
    $cargoCode = $ord['shipping_cargo'] ?? '';
    $trackingNum = $ord['tracking_number'] ?? '';
    $cargoEmpty = empty($cargoCode);
    $trackingEmpty = empty($trackingNum);
    ?>
    <?php if (empty($ord['order_sn'])): ?>
      <div class="skeleton h-4 w-24 rounded-md"></div>
      <div class="skeleton h-3 w-28 rounded-md mt-1"></div>
    <?php else: ?>
      <?php if ($cargoEmpty): ?>
        <div class="text-xs font-medium">-</div>
      <?php else: ?>
        <div class="font-medium text-xs">
          <?php
            $cargoTitle = $cargoCode;
            $dbCargo = new Database();
            $dbCargo->query("SELECT title FROM logistic_channels WHERE code = :code");
            $dbCargo->bind('code', $cargoCode);
            $cargoRow = $dbCargo->single();
            $cargoTitle = $cargoRow['title'] ?? $cargoCode;
          ?>
          <?= htmlspecialchars($cargoTitle); ?>
        </div>
      <?php endif; ?>
      <div class="text-[11px] opacity-60 font-mono mt-0.5"><?= $trackingEmpty ? '-' : htmlspecialchars($trackingNum); ?></div>
    <?php endif; ?>
  </td>
  <td class="text-center">
    <button class="tooltip tooltip-top btn btn-sm btn-ghost btn-square" data-tip="Detail">
      <span class="material-symbols-outlined text-[18px]">visibility</span>
    </button>
  </td>
</tr>
