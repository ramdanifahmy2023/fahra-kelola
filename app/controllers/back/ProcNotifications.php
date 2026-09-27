<?php

class ProcNotifications extends Controller {
  private function json($payload, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
  }

  private function requireAjax() {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    if (!$isAjax) $this->json(['status' => 'error', 'message' => 'Invalid request.'], 400);
  }

  private function alerts() {
    $model = $this->m('StockAlert');
    $model->ensureSchema();
    return $model;
  }

  public function summary() {
    $this->requireAjax();
    $model = $this->alerts();
    $this->json(['status' => 'success', 'summary' => $model->summary(), 'unread_count' => $model->unreadCount(), 'notifications' => $model->listActive(0, true, 8)]);
  }

  public function list() {
    $this->requireAjax();
    $shopId = (int)($_GET['shop_id'] ?? 0);
    $unreadOnly = ($_GET['unread_only'] ?? '') === '1';
    $this->json(['status' => 'success', 'notifications' => $this->alerts()->listActive($shopId, $unreadOnly, 100)]);
  }

  public function acknowledge() {
    $this->requireAjax();
    $id = (int)($_POST['id'] ?? 0);
    if ($id < 1) $this->json(['status' => 'error', 'message' => 'Notifikasi tidak ditemukan.'], 422);
    $this->alerts()->acknowledge($id);
    $this->json(['status' => 'success', 'unread_count' => $this->alerts()->unreadCount()]);
  }

  public function acknowledge_all() {
    $this->requireAjax();
    $this->alerts()->acknowledgeAll();
    $this->json(['status' => 'success', 'unread_count' => 0]);
  }
}
