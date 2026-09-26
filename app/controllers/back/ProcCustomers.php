<?php

class ProcCustomers extends Controller {
  public function sync() {
    header('Content-Type: application/json');

    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    if (!$isAjax) {
      echo json_encode(['status' => 'error', 'message' => 'Invalid request.']);
      exit;
    }

    try {
      $result = $this->m('Customer')->syncFromOrders();
      echo json_encode(['status' => 'success', 'added' => $result['added'], 'updated' => $result['updated']]);
    } catch (Throwable $error) {
      echo json_encode(['status' => 'error', 'message' => 'Gagal menyinkronkan data pelanggan.']);
    }
    exit;
  }

  public function history() {
    header('Content-Type: application/json');

    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    $username = trim($_POST['username'] ?? '');
    if (!$isAjax || $username === '') {
      echo json_encode(['status' => 'error', 'message' => 'Nama pengguna tidak valid.']);
      exit;
    }

    echo json_encode(['status' => 'success', 'orders' => $this->m('Customer')->findOrderHistory($username)]);
    exit;
  }
}
