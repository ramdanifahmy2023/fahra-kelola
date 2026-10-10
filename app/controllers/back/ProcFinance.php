<?php
require_once __DIR__.'/../../helpers/FinancePolicy.php';

class ProcFinance extends Controller {
  private function json(array $data,int $status=200): void {
    http_response_code($status); header('Content-Type: application/json'); header('Cache-Control: no-store');
    echo json_encode($data,JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE); exit;
  }
  private function run(callable $callback): void {
    try { $this->json(['status'=>'success']+$callback()); }
    catch (InvalidArgumentException $error) { $this->json(['status'=>'error','message'=>$error->getMessage()],422); }
    catch (Throwable $error) {
      error_log('Finance: '.get_class($error).' '.($error instanceof PDOException ? $error->getCode() : $error->getMessage()));
      $this->json(['status'=>'error','message'=>$error->getCode()===409 ? $error->getMessage() : 'Data keuangan belum dapat diproses. Coba lagi.'],$error->getCode()===409 ? 409 : 500);
    }
  }
  private function input(): array {
    if ($_SERVER['REQUEST_METHOD']!=='POST') $this->json(['status'=>'error','message'=>'Gunakan POST.'],405);
    if (!authVerifyCsrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) $this->json(['status'=>'error','message'=>'Sesi formulir berakhir. Muat ulang halaman.'],403);
    $body=file_get_contents('php://input',false,null,0,16385);
    if (strlen($body)>16384) $this->json(['status'=>'error','message'=>'Formulir terlalu besar.'],413);
    $data=json_decode($body,true);
    if (!is_array($data)) throw new InvalidArgumentException('Formulir tidak valid.');
    return $data;
  }
  private function batchInput(): array {
    if ($_SERVER['REQUEST_METHOD']!=='POST') $this->json(['status'=>'error','message'=>'Gunakan POST.'],405);
    if (!authVerifyCsrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) $this->json(['status'=>'error','message'=>'Sesi formulir berakhir. Muat ulang halaman.'],403);
    $body=file_get_contents('php://input',false,null,0,262145);
    if (strlen($body)>262144) $this->json(['status'=>'error','message'=>'Batch HPP terlalu besar. Gunakan paling banyak 500 baris.'],413);
    $data=json_decode($body,true);
    if (!is_array($data)) throw new InvalidArgumentException('Formulir batch tidak valid.');
    return $data;
  }
  private function range(array $data): array { return FinancePolicy::range($data['start'] ?? null,$data['end'] ?? null); }
  private function previewKey(): string {
    if (empty($_SESSION['finance_preview_key'])) $_SESSION['finance_preview_key']=bin2hex(random_bytes(32));
    return $_SESSION['finance_preview_key'];
  }
  public function summary(): void { $this->run(function () { $m=$this->m('Finance'); $m->ensureSchema(); return $m->summary($m->shops($_GET['shops'] ?? ''),$this->range($_GET)); }); }
  public function details(): void { $this->run(function () {
    $m=$this->m('Finance'); $m->ensureSchema(); $category=(int)($_GET['category'] ?? 1);
    if (!in_array($category,[1,2],true)) throw new InvalidArgumentException('Jenis penghasilan tidak valid.');
    return $m->details($m->shops($_GET['shops'] ?? ''),$this->range($_GET),$category,(int)($_GET['page'] ?? 1),(string)($_GET['search'] ?? ''),(string)($_GET['state'] ?? ''));
  }); }
  public function catalog(): void { $this->run(function () { $m=$this->m('FinanceCost'); $m->ensureSchema(); return $m->catalog($m->shops($_GET['shops'] ?? ''),(int)($_GET['page'] ?? 1),(string)($_GET['search'] ?? '')); }); }
  public function history(): void { $this->run(function () { $m=$this->m('FinanceCost'); $m->ensureSchema(); return ['rows'=>$m->history($_GET)]; }); }
  public function preview(): void { $this->run(function () {
    $input=$this->input(); $m=$this->m('FinanceCost'); $m->ensureSchema(); $preview=$m->preview($input); $expires=time()+600;
    return ['preview'=>$preview,'expires'=>$expires,'token'=>FinanceCost::token($preview,$expires,$this->previewKey())];
  }); }
  public function batchPreview(): void { $this->run(function () {
    $input=$this->batchInput(); $m=$this->m('FinanceCost'); $m->ensureSchema(); $preview=$m->batchPreview($input); $expires=time()+600;
    return ['preview'=>$preview,'expires'=>$expires,'token'=>$preview['errors'] ? null : FinanceCost::batchToken($preview,$expires,$this->previewKey())];
  }); }
  public function save(): void { $this->run(function () { $input=$this->input(); $m=$this->m('FinanceCost'); $m->ensureSchema(); return $m->save($input,(int)authUser()['id'],$this->previewKey()); }); }
  public function batchSave(): void { $this->run(function () { $input=$this->batchInput(); $m=$this->m('FinanceCost'); $m->ensureSchema(); return $m->batchSave($input,(int)authUser()['id'],$this->previewKey()); }); }
  public function sync(): void { $this->run(function () {
    $input=$this->input(); $m=$this->m('Finance'); $shops=$m->shops($input['shops'] ?? '');
    if (!$shops) throw new InvalidArgumentException('Tambahkan toko terlebih dahulu.');
    $m->requestImports($shops,$this->range($input)); $m->requestWallets($shops); $jobs=$this->m('SyncJob'); $queued=0;
    foreach ($shops as $shop) if ($jobs->enqueueType((int)$shop['id'],'finance')) $queued++;
    return ['queued'=>$queued,'message'=>'Pembaruan saldo dan penghasilan masuk antrean. Data terakhir tetap ditampilkan.'];
  }); }
}
