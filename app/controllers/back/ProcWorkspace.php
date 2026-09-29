<?php
class ProcWorkspace extends Controller {
  private function json(array $data,int $code=200): void {http_response_code($code);header('Content-Type: application/json');header('Cache-Control: no-store');echo json_encode($data);exit;}
  public function save(): void {
    if ($_SERVER['REQUEST_METHOD']!=='POST') $this->json(['status'=>'error'],405);
    if (!authVerifyCsrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) $this->json(['status'=>'error','message'=>'Muat ulang halaman untuk menyimpan pilihan.'],403);
    $raw=file_get_contents('php://input',false,null,0,4097);$data=strlen($raw)<=4096?json_decode($raw,true):null;
    if (!is_array($data) || !is_string($data['scope'] ?? null) || !is_array($data['values'] ?? null)) $this->json(['status'=>'error'],422);
    try {$this->m('AccountWorkspace')->save((int)authUser()['id'],$data['scope'],$data['values'],is_int($data['changed_at'] ?? null)?$data['changed_at']:null);$this->json(['status'=>'success']);}
    catch (InvalidArgumentException $e) {$this->json(['status'=>'error','message'=>$e->getMessage()],422);}
    catch (Throwable $e) {$this->json(['status'=>'error','message'=>'Pilihan belum tersimpan.'],500);}
  }
}
