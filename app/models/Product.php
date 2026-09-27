<?php

class Product extends BaseModel {
    protected $table = 'products';

    public function findForBoost($shopId, $search = '', $limit = 12, $offset = 0) {
        $where = 'shop_id = :shop_id AND status = 1';
        if ($search !== '') $where .= ' AND name LIKE :search';
        $this->db->query("SELECT id, shop_id, name, status, cover_image, parent_sku, price_min, price_max, selling_price_min, selling_price_max, total_stock, sold_count, raw_data FROM {$this->table} WHERE {$where} ORDER BY sold_count DESC, id DESC LIMIT " . (int)$limit . " OFFSET " . (int)$offset);
        $this->db->bind('shop_id', (int)$shopId);
        if ($search !== '') $this->db->bind('search', '%' . $search . '%');
        return $this->db->getAll();
    }

    public function countForBoost($shopId, $search = '') {
        $where = 'shop_id = :shop_id AND status = 1';
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
        $this->db->query("SELECT id, shop_id, name, status, total_stock, sold_count FROM {$this->table} WHERE shop_id = :shop_id AND status = 1 AND id IN (" . implode(', ', $placeholders) . ")");
        $this->db->bind('shop_id', (int)$shopId);
        foreach ($ids as $index => $id) $this->db->bind('product_id_' . $index, $id);
        return $this->db->getAll();
    }
}
