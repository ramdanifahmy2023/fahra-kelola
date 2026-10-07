<?php

class Product extends BaseModel {
    protected $table = 'products';

    public function movementSummary(int $shopId, string $startDate, string $endDate): array {
        $start = DateTimeImmutable::createFromFormat('!Y-m-d', $startDate, new DateTimeZone('Asia/Jakarta'));
        $end = DateTimeImmutable::createFromFormat('!Y-m-d', $endDate, new DateTimeZone('Asia/Jakarta'));
        if (!$start || !$end || $end < $start) return ['rows' => [], 'summary' => [], 'days' => 0];
        $utc = new DateTimeZone('UTC');
        $startUtc = $start->setTimezone($utc)->format('Y-m-d H:i:s');
        $endUtc = $end->modify('+1 day')->setTimezone($utc)->format('Y-m-d H:i:s');
        $days = max(1, (int)$start->diff($end)->days + 1);
        $this->db->query("SELECT p.id,p.shop_id,p.name,p.cover_image,p.parent_sku,p.total_stock,p.status,
          COALESCE(SUM(CASE WHEN LOWER(TRIM(COALESCE(o.status_type,''))) IN ('completed','delivered','order received','order_received','to confirm receive','selesai','pesanan diterima','sudah kirim','telah dikirim','terkirim','diterima') THEN COALESCE(oi.quantity,0) ELSE 0 END),0) units_sold,
          MAX(CASE WHEN LOWER(TRIM(COALESCE(o.status_type,''))) IN ('completed','delivered','order received','order_received','to confirm receive','selesai','pesanan diterima','sudah kirim','telah dikirim','terkirim','diterima') THEN o.created_at END) last_sold_at,
          s.name shop_name
          FROM products p JOIN shops s ON s.id=p.shop_id
          LEFT JOIN order_items oi ON oi.product_id=p.id
          LEFT JOIN orders o ON o.id=oi.order_id AND o.shop_id=p.shop_id AND o.deleted_at IS NULL AND o.created_at>=:start AND o.created_at<:end
          WHERE p.shop_id=:shop AND p.deleted_at IS NULL
          GROUP BY p.id,p.shop_id,p.name,p.cover_image,p.parent_sku,p.total_stock,p.status,s.name
          ORDER BY units_sold DESC,p.total_stock DESC,p.name ASC");
        $this->db->bind('shop', $shopId); $this->db->bind('start', $startUtc); $this->db->bind('end', $endUtc);
        $rows = $this->db->getAll();
        $rates = array_values(array_filter(array_map(fn($row) => ((int)$row['units_sold']) / $days, $rows), fn($rate) => $rate > 0));
        sort($rates); $median = $rates ? $rates[(int)floor((count($rates)-1)/2)] : 0;
        $summary = ['fast'=>0,'slow'=>0,'dead'=>0,'empty'=>0,'insufficient'=>0];
        foreach ($rows as &$row) {
            $units=(int)$row['units_sold']; $stock=(int)$row['total_stock']; $rate=$units/$days;
            if ($stock <= 0) { $key='empty'; $label='Stok habis'; }
            elseif ($units === 0) { $key='dead'; $label='Dead stock'; }
            elseif ($rate >= max(0.5, $median * 1.5)) { $key='fast'; $label='Fast moving'; }
            else { $key='slow'; $label='Slow moving'; }
            $row['movement_key']=$key; $row['movement_label']=$label; $row['units_sold']=$units; $row['daily_rate']=round($rate,2);
            $row['stock_days']=$rate > 0 ? round($stock/$rate,1) : null; $summary[$key]++;
        }
        unset($row);
        return ['rows'=>$rows,'summary'=>$summary,'days'=>$days,'start'=>$startDate,'end'=>$endDate,'median_rate'=>round($median,2)];
    }

    public function findForBoost($shopId, $search = '', $limit = 12, $offset = 0) {
        $where = 'shop_id = :shop_id AND status = 1 AND deleted_at IS NULL';
        if ($search !== '') $where .= ' AND name LIKE :search';
        $this->db->query("SELECT id, shop_id, name, status, cover_image, parent_sku, price_min, price_max, selling_price_min, selling_price_max, total_stock, sold_count, raw_data FROM {$this->table} WHERE {$where} ORDER BY sold_count DESC, id DESC LIMIT " . (int)$limit . " OFFSET " . (int)$offset);
        $this->db->bind('shop_id', (int)$shopId);
        if ($search !== '') $this->db->bind('search', '%' . $search . '%');
        return $this->db->getAll();
    }

    public function countForBoost($shopId, $search = '') {
        $where = 'shop_id = :shop_id AND status = 1 AND deleted_at IS NULL';
        if ($search !== '') $where .= ' AND name LIKE :search';
        $this->db->query("SELECT COUNT(*) AS total FROM {$this->table} WHERE {$where}");
        $this->db->bind('shop_id', (int)$shopId);
        if ($search !== '') $this->db->bind('search', '%' . $search . '%');
        return (int)($this->db->single()['total'] ?? 0);
    }

    public function findByIdsForBoost($shopId, array $ids) {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids) return [];
        $placeholders = [];
        foreach ($ids as $index => $id) $placeholders[] = ':product_id_' . $index;
        $this->db->query("SELECT id, shop_id, name, status, total_stock, sold_count FROM {$this->table} WHERE shop_id = :shop_id AND status = 1 AND deleted_at IS NULL AND total_stock > 0 AND id IN (" . implode(', ', $placeholders) . ")");
        $this->db->bind('shop_id', (int)$shopId);
        foreach ($ids as $index => $id) $this->db->bind('product_id_' . $index, $id);
        return $this->db->getAll();
    }

    public function countCritical($shopId = 0) {
        $where = 'deleted_at IS NULL AND status = 1 AND total_stock < 15';
        if ((int)$shopId > 0) $where .= ' AND shop_id = :shop_id';
        $this->db->query("SELECT COUNT(*) AS total FROM {$this->table} WHERE {$where}");
        if ((int)$shopId > 0) $this->db->bind('shop_id', (int)$shopId);
        return (int)($this->db->single()['total'] ?? 0);
    }

    public function findCriticalPaginated($shopId, $limit = 10, $offset = 0) {
        $this->db->query("SELECT * FROM {$this->table} WHERE shop_id = :shop_id AND deleted_at IS NULL AND status = 1 AND total_stock < 15 ORDER BY total_stock ASC, name ASC LIMIT " . (int)$limit . " OFFSET " . (int)$offset);
        $this->db->bind('shop_id', (int)$shopId);
        return $this->db->getAll();
    }
}
