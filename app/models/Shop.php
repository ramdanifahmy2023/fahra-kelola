<?php

class Shop extends BaseModel {
    protected $table = 'shops';

    private function scopedAccount(): int {
        return function_exists('authAccountId') ? authAccountId() : 0;
    }

    private function cleanRow(array $row): array {
        if (array_key_exists('name', $row)) {
            $row['name'] = trim(preg_replace('/[\x00-\x1F\x7F]/', '', strip_tags((string)$row['name'])));
        }
        return $row;
    }

    public function findAll(): array {
        $account = $this->scopedAccount();
        if ($account < 1) return array_map(fn($row) => $this->cleanRow($row), parent::findAll());
        $this->db->query('SELECT * FROM shops WHERE account_id = :account ORDER BY id ASC');
        $this->db->bind('account', $account);
        return array_map(fn($row) => $this->cleanRow($row), $this->db->getAll());
    }

    public function findBy($column, $value) {
        $account = $this->scopedAccount();
        if ($account > 0 && $column === 'id') {
            $this->db->query('SELECT * FROM shops WHERE id = :id AND account_id = :account LIMIT 1');
            $this->db->bind('id', (int)$value);
            $this->db->bind('account', $account);
            $row = $this->db->single();
            return $row ? $this->cleanRow($row) : false;
        }
        $row = parent::findBy($column, $value);
        return $row ? $this->cleanRow($row) : $row;
    }

    public function findOwned(int $id, ?int $accountId = null): ?array {
        $account = $accountId ?? $this->scopedAccount();
        if ($id < 1 || $account < 1) return null;
        $this->db->query('SELECT * FROM shops WHERE id = :id AND account_id = :account LIMIT 1');
        $this->db->bind('id', $id); $this->db->bind('account', $account);
        $row = $this->db->single();
        return $row ? $this->cleanRow($row) : null;
    }

    public function insertOwned(array $data, int $accountId): int {
        $data['account_id'] = $accountId;
        if (isset($data['name'])) $data['name'] = trim(preg_replace('/[\x00-\x1F\x7F]/', '', strip_tags((string)$data['name'])));
        return parent::insert($data);
    }

    public function updateOwned(int $id, array $data, int $accountId): int {
        if (!$this->findOwned($id, $accountId)) return 0;
        unset($data['id'], $data['account_id']);
        if (isset($data['name'])) $data['name'] = trim(preg_replace('/[\x00-\x1F\x7F]/', '', strip_tags((string)$data['name'])));
        $fields = [];
        foreach ($data as $key => $value) $fields[] = "{$key} = :{$key}";
        if (!$fields) return 0;
        $this->db->query('UPDATE shops SET ' . implode(', ', $fields) . ' WHERE id = :id AND account_id = :account');
        $this->db->bind('id', $id); $this->db->bind('account', $accountId);
        foreach ($data as $key => $value) $this->db->bind($key, $value);
        $this->db->exe();
        return $this->db->row();
    }

    public function deleteOwned(int $id, int $accountId): int {
        $this->db->query('DELETE FROM shops WHERE id = :id AND account_id = :account');
        $this->db->bind('id', $id); $this->db->bind('account', $accountId); $this->db->exe();
        return $this->db->row();
    }
}
