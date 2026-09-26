<?php

class OrderIncome extends BaseModel {
  protected $table = 'order_incomes';

  public function ensureOrder($orderId) {
    $this->db->query("INSERT IGNORE INTO {$this->table} (order_id) VALUES (:order_id)");
    $this->db->bind('order_id', $orderId);
    return $this->db->exe();
  }

  public function needsSync($orderId) {
    $income = $this->findBy('order_id', $orderId);
    if (!$income
      || $income['total_price'] === null
      || $income['total_payment'] === null
      || $income['total_payment_detail'] === null
      || $income['total_income'] === null
      || $income['total_income_detail'] === null) {
      return true;
    }
    if ($income['released_time'] !== null) {
      return false;
    }
    return empty($income['income_checked_at'])
      || strtotime($income['income_checked_at']) <= time() - (6 * 60 * 60);
  }

  public function markCancelled($orderId) {
    $this->db->query("INSERT INTO {$this->table} (order_id, total_price, total_payment, total_payment_detail, total_income, total_income_detail, released_time, income_checked_at) VALUES (:order_id, 0, 0, '[]', 0, '[]', NULL, NOW()) ON DUPLICATE KEY UPDATE total_price = 0, total_payment = 0, total_payment_detail = '[]', total_income = 0, total_income_detail = '[]', released_time = NULL, income_checked_at = NOW()");
    $this->db->bind('order_id', $orderId);
    return $this->db->exe();
  }

  public function markChecked($orderId) {
    $this->ensureOrder($orderId);
    $this->db->query("UPDATE {$this->table} SET income_checked_at = NOW() WHERE order_id = :order_id");
    $this->db->bind('order_id', $orderId);
    return $this->db->exe();
  }

  public function upsertDetail($orderId, $incomeData) {
    $sellerBreakdown = $incomeData['seller_income_breakdown']['breakdown'] ?? null;
    $buyerBreakdown = $incomeData['buyer_payment_breakdown']['breakdown'] ?? null;

    $this->db->query("INSERT INTO {$this->table} (order_id, total_price, total_payment, total_payment_detail, total_income, total_income_detail, released_time, income_checked_at) VALUES (:order_id, :total_price, :total_payment, :total_payment_detail, :total_income, :total_income_detail, :released_time, NOW()) ON DUPLICATE KEY UPDATE total_price = VALUES(total_price), total_payment = VALUES(total_payment), total_payment_detail = VALUES(total_payment_detail), total_income = VALUES(total_income), total_income_detail = VALUES(total_income_detail), released_time = VALUES(released_time), income_checked_at = NOW()");
    $this->db->bind('order_id', $orderId);
    $this->db->bind('total_price', $this->breakdownAmount($sellerBreakdown, 'MERCHANDISE_SUBTOTAL'));
    $this->db->bind('total_payment', $this->breakdownAmount($buyerBreakdown, 'BUYER_PAID_AMOUNT'));
    $this->db->bind('total_payment_detail', is_array($buyerBreakdown) ? json_encode($buyerBreakdown) : null);
    $this->db->bind('total_income', $this->breakdownAmount($sellerBreakdown, 'ESCROW_AMOUNT'));
    $this->db->bind('total_income_detail', is_array($sellerBreakdown) ? json_encode($sellerBreakdown) : null);
    $this->db->bind('released_time', $this->releasedTime($incomeData['order_info']['released_time'] ?? null));
    return $this->db->exe();
  }

  private function breakdownAmount($breakdown, $fieldName) {
    if (!is_array($breakdown)) {
      return null;
    }
    foreach ($breakdown as $item) {
      if (($item['field_name'] ?? '') === $fieldName) {
        return (int)($item['amount'] ?? 0);
      }
    }
    return null;
  }

  private function releasedTime($timestamp) {
    if (empty($timestamp)) {
      return null;
    }
    return (new DateTimeImmutable('@' . $timestamp))->setTimezone(new DateTimeZone('Asia/Jakarta'))->format('Y-m-d H:i:s');
  }
}
