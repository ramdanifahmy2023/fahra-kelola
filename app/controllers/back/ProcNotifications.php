<?php

class ProcNotifications extends Controller {
  private function json(array $payload,int $code=200): void {
    http_response_code($code);header('Content-Type: application/json');header('Cache-Control: no-store');
    echo json_encode($payload,JSON_UNESCAPED_UNICODE);exit;
  }

  private function center() {
    $model=$this->m('NotificationCenter');$model->ensureSchema();return $model;
  }

  public function summary(): void {
    try {
      $model=$this->center();$available=true;
      try { $model->refreshOperations(); } catch(Throwable $e) { $available=false; }
      $user=(int)authUser()['id'];$summary=$model->overview($user);
      $unread=($_GET['unread_only'] ?? '1')!=='0';
      $limit=max(10,min(100,(int)($_GET['limit'] ?? 10)));
      $offset=max(0,(int)($_GET['offset'] ?? 0));
      $rows=$model->notifications($user,$unread,$limit,$offset);
      $chatCursor=null;
      try { $chatCursor=$this->m('ChatIncomingNotifications')->cursor(); }
      catch (Throwable $e) { $chatCursor=null; }
      $this->json(['status'=>'success','summary'=>$summary,'unread_count'=>$summary['unread'],'notifications'=>$rows,
        'chat_cursor'=>$chatCursor,
        'has_more'=>($unread?$summary['unread']:$summary['total'])>$offset+count($rows),'offset'=>$offset,'evaluation_available'=>$available]);
    } catch(Throwable $e) { $this->json(['status'=>'error','message'=>'Notifikasi belum dapat dimuat. Coba lagi.'],500); }
  }

  public function list(): void { $this->summary(); }

  private function items(): array {
    if ($_SERVER['REQUEST_METHOD']!=='POST') $this->json(['status'=>'error','message'=>'Gunakan POST.'],405);
    if (!authVerifyCsrf($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) $this->json(['status'=>'error','message'=>'Sesi formulir berakhir. Muat ulang halaman.'],403);
    $raw=file_get_contents('php://input',false,null,0,16385);
    $data=strlen($raw)<=16384?json_decode($raw,true):null;
    $items=$data['items'] ?? null;
    if (!is_array($items) || !array_is_list($items) || count($items)<1 || count($items)>100) $this->json(['status'=>'error','message'=>'Daftar notifikasi tidak valid.'],422);
    foreach ($items as $item) {
      if (!is_array($item) || !is_int($item['id'] ?? null) || $item['id']<1 || !is_int($item['revision'] ?? null) || $item['revision']<1) $this->json(['status'=>'error','message'=>'Versi notifikasi tidak valid.'],422);
    }
    return $items;
  }

  public function acknowledge(): void {
    $items=$this->items();
    try { $model=$this->center();$model->markRead((int)authUser()['id'],$items);$this->json(['status'=>'success']); }
    catch(Throwable $e) { $this->json(['status'=>'error','message'=>'Status dibaca gagal disimpan. Coba lagi.'],500); }
  }
  public function acknowledge_all(): void { $this->acknowledge(); }
}
