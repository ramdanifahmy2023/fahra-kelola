<?php

class ProcReports extends Controller {
  private function json($payload, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
  }

  private function requireAjax() {
    authRequireMethod('GET');
    if (strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) !== 'xmlhttprequest') {
      $this->json(['status' => 'error', 'message' => 'Invalid request.'], 400);
    }
  }

  private function validDate($value) {
    $value = (string)$value;
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('Asia/Jakarta'));
    return $date && $date->format('Y-m-d') === $value ? $value : null;
  }

  public function summary() {
    $this->requireAjax();
    $endDate = $this->validDate($_GET['end_date'] ?? $_POST['end_date'] ?? '') ?: null;
    $shopId = (int)($_GET['shop_id'] ?? $_POST['shop_id'] ?? 0);
    $sort = strtolower(trim((string)($_GET['sort'] ?? $_POST['sort'] ?? 'confirmed_gmv')));
    try {
      if ($shopId > 0 && !$this->m('Shop')->findBy('id', $shopId)) $this->json(['status' => 'error', 'message' => 'Toko tidak ditemukan.'], 404);
      $payload = $this->m('ShopPerformance')->summary($endDate, $shopId, $sort, authAccountId());
      $this->json(['status' => 'success'] + $payload);
    } catch (Throwable $exception) {
      error_log('Report summary failed: ' . $exception->getMessage());
      $this->json(['status' => 'error', 'message' => 'Laporan belum siap.'], 500);
    }
  }

  public function detail() {
    $this->requireAjax();
    $shopId = (int)($_GET['shop_id'] ?? $_POST['shop_id'] ?? 0);
    if ($shopId < 1) $this->json(['status' => 'error', 'message' => 'Toko wajib dipilih.'], 422);
    if (!$this->m('Shop')->findBy('id', $shopId)) $this->json(['status' => 'error', 'message' => 'Toko tidak ditemukan.'], 404);
    $endDate = $this->validDate($_GET['end_date'] ?? $_POST['end_date'] ?? '') ?: null;
    try {
      $this->json(['status' => 'success'] + $this->m('ShopPerformance')->detail($shopId, $endDate));
    } catch (Throwable $exception) {
      $this->json(['status' => 'error', 'message' => 'Detail laporan belum siap.'], 500);
    }
  }

  public function compare() {
    $this->requireAjax();
    $raw = (string)($_GET['shop_ids'] ?? $_POST['shop_ids'] ?? '');
    $shopIds = $raw === '' ? [] : explode(',', $raw);
    foreach ($shopIds as $id) if ((int)$id > 0 && !$this->m('Shop')->findBy('id', (int)$id)) $this->json(['status' => 'error', 'message' => 'Toko tidak ditemukan.'], 404);
    $endDate = $this->validDate($_GET['end_date'] ?? $_POST['end_date'] ?? '') ?: null;
    try {
      $this->json(['status' => 'success'] + $this->m('ShopPerformance')->compare($shopIds, $endDate, authAccountId()));
    } catch (Throwable $exception) {
      $this->json(['status' => 'error', 'message' => 'Perbandingan belum siap.'], 500);
    }
  }
}
