<?php
require_once __DIR__ . '/../../models/AiConnection.php';

class ProcAiConnections extends Controller {
  private function json(array $data, int $status=200): void {
    http_response_code($status); header('Content-Type: application/json'); header('Cache-Control: no-store');
    echo json_encode($data,JSON_UNESCAPED_UNICODE); exit;
  }
  private function run(string $action): void {
    try {
      if (!authIsLoggedIn()) throw new AiConnectionError('Silakan masuk kembali.',401);
      $read=in_array($action,['list','detail'],true);
      if ($_SERVER['REQUEST_METHOD']!==($read?'GET':'POST')) throw new AiConnectionError('Metode permintaan tidak sesuai.',405);
      if (!$read && !authVerifyCsrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) throw new AiConnectionError('Sesi formulir berakhir. Muat ulang halaman.',403);
      $actor=(int)authUser()['id'];
      if ($read) $input=$_GET;
      else {
        $body=file_get_contents('php://input',false,null,0,16385);
        if (strlen($body)>16384) throw new AiConnectionError('Form terlalu besar.',413);
        $input=json_decode($body,true);
        if (!is_array($input)) throw new AiConnectionError('Form tidak valid.');
      }
      $id=0; $version=0;
      if (!in_array($action,['list','create'],true)) {
        $id=$read ? filter_var($input['id'] ?? null,FILTER_VALIDATE_INT) : ($input['id'] ?? null);
        if (!is_int($id) || $id<1) throw new AiConnectionError('Koneksi tidak ditemukan.',404);
        if (!$read) {
          $version=$input['version'] ?? null;
          if (!is_int($version) || $version<1) throw new AiConnectionError('Versi koneksi tidak valid.');
        }
      }
      session_write_close();
      $model=new AiConnection(); $model->ensureSchema();
      $result=match($action) {
        'list'=>['connections'=>$model->listing()],
        'detail'=>['connection'=>$model->detail($id)],
        'create'=>['connection'=>$model->create($input,$actor)],
        'update'=>['connection'=>$model->update($id,$version,$input,$actor)],
        'models','test'=>$model->probe($id,$version,$action,$action==='test' ? NineRouterClient::model($input['model'] ?? null) : '',$actor),
        'delete'=>$this->remove($model,$id,$version,$actor)
      };
      $this->json(['status'=>'success']+$result);
    } catch (AiConnectionError $error) { $this->json(['status'=>'error','message'=>$error->getMessage(),'fields'=>$error->fields],$error->getCode()); }
    catch (Throwable $error) { $this->json(['status'=>'error','message'=>'Koneksi belum dapat diproses. Coba lagi atau periksa server.'],500); }
  }
  private function remove(AiConnection $model,int $id,int $version,int $actor): array { $model->remove($id,$version,$actor); return []; }
  public function index(): void { $this->json(['status'=>'error','message'=>'Pilih tindakan koneksi.'],404); }
  public function list(): void { $this->run('list'); }
  public function detail(): void { $this->run('detail'); }
  public function create(): void { $this->run('create'); }
  public function update(): void { $this->run('update'); }
  public function delete(): void { $this->run('delete'); }
  public function models(): void { $this->run('models'); }
  public function test(): void { $this->run('test'); }
}
