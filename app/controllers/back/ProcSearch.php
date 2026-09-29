<?php
class ProcSearch extends Controller {
  public function index(): void {
    header('Content-Type: application/json');header('Cache-Control: no-store');
    $q=is_string($_GET['q'] ?? null)?trim($_GET['q']):'';$shop=max(0,(int)($_GET['shop_id'] ?? 0));
    if (mb_strlen($q)<2 || mb_strlen($q)>100) {http_response_code(422);echo json_encode(['status'=>'error','message'=>'Ketik 2 sampai 100 karakter.']);return;}
    try {$rows=$this->m('QuickSearch')->lookup($q,$shop);echo json_encode(['status'=>'success','results'=>$rows],JSON_UNESCAPED_UNICODE);}
    catch (Throwable $e) {http_response_code(500);echo json_encode(['status'=>'error','message'=>'Pencarian belum dapat dimuat. Coba lagi.']);}
  }
}
