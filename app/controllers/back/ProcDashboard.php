<?php
require_once __DIR__.'/../../helpers/FinancePolicy.php';

class ProcDashboard extends Controller {
  public function overview(): void {
    header('Content-Type: application/json'); header('Cache-Control: no-store');
    try {
      $shops=$this->m('Finance')->shops($_GET['shops'] ?? '');
      $range=FinancePolicy::range($_GET['start'] ?? null,$_GET['end'] ?? null);
      $result=['status'=>'success']+$this->m('Dashboard')->overview($shops,$range);
    } catch (InvalidArgumentException $e) {
      http_response_code(422); $result=['status'=>'error','message'=>$e->getMessage()];
    } catch (Throwable $e) {
      error_log('Dashboard: '.get_class($e)); http_response_code(500);
      $result=['status'=>'error','message'=>'Ringkasan toko belum dapat dimuat. Coba lagi.'];
    }
    echo json_encode($result,JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
  }
}
