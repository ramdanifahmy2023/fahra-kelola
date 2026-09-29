<?php
require_once __DIR__ . '/../../helpers/AutomationPolicy.php';

class ProcAutomation extends Controller {
  private function json(array $payload, int $code = 200): void {
    http_response_code($code); header('Content-Type: application/json'); header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE); exit;
  }

  private function input(): array {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') $this->json(['status'=>'error','message'=>'Gunakan POST.'],405);
    if (!authVerifyCsrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) $this->json(['status'=>'error','message'=>'Sesi formulir berakhir. Muat ulang halaman.'],403);
    $body = file_get_contents('php://input', false, null, 0, 32769);
    if (strlen($body) > 32768) $this->json(['status'=>'error','message'=>'Konfigurasi terlalu besar.'],413);
    $data = json_decode($body, true);
    if (!is_array($data) || !is_array($data['config'] ?? null)) $this->json(['status'=>'error','message'=>'Konfigurasi tidak valid.'],422);
    if (!is_int($data['shop_id'] ?? null) || $data['shop_id'] < 1 || !$this->m('Shop')->findBy('id',$data['shop_id'])) $this->json(['status'=>'error','message'=>'Toko tidak ditemukan.'],404);
    return $data;
  }

  public function save(): void {
    $data = $this->input();
    try {
      if (!is_int($data['version'] ?? null) || $data['version'] < 0) throw new InvalidArgumentException('Versi konfigurasi tidak valid.');
      $model = $this->m('AutomationProfile'); $model->ensureSchema();
      $connection = array_key_exists('connection_id',$data) ? $data['connection_id'] : false;
      if (array_key_exists('connection_id',$data) && $connection !== null && (!is_int($connection) || $connection < 1)) throw new InvalidArgumentException('Koneksi toko tidak valid.');
      $profile = $model->save($data['shop_id'],$data['version'],$data['config'],(int)authUser()['id'],$connection);
      $this->json(['status'=>'success','profile'=>$profile]);
    } catch (InvalidArgumentException $error) { $this->json(['status'=>'error','message'=>$error->getMessage()],422); }
    catch (Throwable $error) { $this->json(['status'=>'error','message'=>$error->getCode()===409 ? $error->getMessage() : 'Konfigurasi gagal disimpan. Coba lagi.'], $error->getCode()===409 ? 409 : 500); }
  }

  public function preview(): void {
    $data = $this->input();
    try {
      if (!is_array($data['sample'] ?? null)) throw new InvalidArgumentException('Isi contoh ulasan.');
      $preview = AutomationPolicy::preview(AutomationPolicy::validate($data['config']),$data['sample']);
      $this->json(['status'=>'success','preview'=>$preview]);
    } catch (InvalidArgumentException $error) { $this->json(['status'=>'error','message'=>$error->getMessage()],422); }
  }
}
