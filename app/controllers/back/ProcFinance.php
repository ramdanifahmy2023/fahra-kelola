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
  private function range(array $data): array { return FinancePolicy::range($data['start'] ?? null,$data['end'] ?? null); }
  public function summary(): void { $this->run(function () { $m=$this->m('Finance'); $m->ensureSchema(); return $m->summary($m->shops($_GET['shops'] ?? ''),$this->range($_GET)); }); }
  public function details(): void { $this->run(function () {
    $m=$this->m('Finance'); $m->ensureSchema(); $category=(int)($_GET['category'] ?? 1);
    if (!in_array($category,[1,2],true)) throw new InvalidArgumentException('Jenis penghasilan tidak valid.');
    return $m->details($m->shops($_GET['shops'] ?? ''),$this->range($_GET),$category,(int)($_GET['page'] ?? 1),(string)($_GET['search'] ?? ''));
  }); }
  public function catalog(): void { $this->run(function () { $m=$this->m('FinanceCost'); $m->ensureSchema(); return $m->catalog($m->shops($_GET['shops'] ?? ''),(int)($_GET['page'] ?? 1),(string)($_GET['search'] ?? '')); }); }
  public function history(): void { $this->run(function () { $m=$this->m('FinanceCost'); $m->ensureSchema(); return ['rows'=>$m->history($_GET)]; }); }
  public function preview(): void { $this->run(function () {
    $input=$this->input(); $m=$this->m('FinanceCost'); $m->ensureSchema(); $preview=$m->preview($input); $expires=time()+600;
    return ['preview'=>$preview,'expires'=>$expires,'token'=>FinanceCost::token($preview,$expires,authCsrfToken())];
  }); }
  public function save(): void { $this->run(function () { $input=$this->input(); $m=$this->m('FinanceCost'); $m->ensureSchema(); return $m->save($input,(int)authUser()['id'],authCsrfToken()); }); }
  public function sync(): void { $this->run(function () {
    $input=$this->input(); $m=$this->m('Finance'); $shops=$m->shops($input['shops'] ?? '');
    if (!$shops) throw new InvalidArgumentException('Tambahkan toko terlebih dahulu.');
    $imports=$m->requestImports($shops,$this->range($input)); $jobs=$this->m('SyncJob');
    foreach ($shops as $shop) if ($m->hasWork((int)$shop['id'])) $jobs->enqueueType((int)$shop['id'],'finance');
    return ['queued'=>count($imports),'message'=>'Pembaruan masuk antrean. Data lengkap terakhir tetap ditampilkan.'];
  }); }
}
