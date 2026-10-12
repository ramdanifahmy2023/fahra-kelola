<?php

class ProcCustomers extends Controller {
  public function sync() {
    authRequireMethod('POST');
    authRequireCsrf();
    header('Content-Type: application/json');

    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    if (!$isAjax) {
      http_response_code(400);
      echo json_encode(['status' => 'error', 'message' => 'Invalid request.']);
      exit;
    }

    try {
      $result = $this->m('Customer')->syncFromOrders(authAccountId());
      echo json_encode(['status' => 'success', 'added' => $result['added'], 'updated' => $result['updated']]);
    } catch (Throwable $error) {
      error_log('Customer sync failed: ' . $error->getMessage());
      http_response_code(500);
      echo json_encode(['status' => 'error', 'message' => 'Gagal menyinkronkan data pelanggan.']);
    }
    exit;
  }

  public function history() {
    authRequireMethod('POST');
    authRequireCsrf();
    header('Content-Type: application/json');

    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    $username = trim($_POST['username'] ?? '');
    if (!$isAjax || $username === '') {
      http_response_code(400);
      echo json_encode(['status' => 'error', 'message' => 'Nama pengguna tidak valid.']);
      exit;
    }

    $shopId = max(0, (int)($_POST['shop_id'] ?? 0));
    if ($shopId > 0 && !$this->m('Shop')->findBy('id', $shopId)) {
      http_response_code(404);
      echo json_encode(['status' => 'error', 'message' => 'Toko tidak ditemukan.']);
      exit;
    }
    echo json_encode(['status' => 'success', 'orders' => $this->m('Customer')->findOrderHistory($username, $shopId)]);
    exit;
  }
}
