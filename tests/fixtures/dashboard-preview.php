<?php
// Loopback preview router. No application bootstrap, database, or upstream calls.
if (PHP_SAPI !== 'cli-server') { http_response_code(404); exit; }
$root=dirname(__DIR__,2);
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if (str_starts_with($path,'/assets/')) {
    $file=realpath($root.'/public'.$path);
    if ($file && str_starts_with($file,$root.'/public/assets/') && is_file($file)) return false;
    http_response_code(404); exit;
}
$today=(new DateTimeImmutable('now',new DateTimeZone('Asia/Jakarta')))->format('Y-m-d');
$stamp=$today.' 02:00:00';
$shops=[['id'=>1,'name'=>'Toko Contoh Utama','shop_logo'=>''],['id'=>2,'name'=>'Toko Contoh Aksesori dan Perlengkapan Perjalanan','shop_logo'=>'/assets/broken-fixture']];
$selected=array_filter(explode(',',$_GET['shops']??''));
$scoped=array_values(array_filter($shops,fn($s)=>!$selected || in_array((string)$s['id'],$selected,true)));
$start=$_GET['start']??substr($today,0,8).'01'; $end=$_GET['end']??$today;
$days=max(1,(int)round((strtotime($end)-strtotime($start))/86400)+1);
$reply=static function(array $data): never {header('Content-Type: application/json');echo json_encode($data,JSON_THROW_ON_ERROR);exit;};
if ($path==='/procFinance/summary') {
    $stores=array_map(static function($s) use($stamp,$days) {
        $id=$s['id'];
        return $s+['pending'=>12000000*$id,'released'=>18500000*$id,'pending_updated'=>$stamp,'released_updated'=>$stamp,'released_basis'=>'overview','released_days'=>$days,
            'wallet'=>['amount'=>$id===1?3450000:2100000,'updated_at'=>$stamp,'failed'=>false,'stale'=>false,'withdrawal_restricted'=>false],
            'gmv'=>24000000*$id,'gmv_days'=>$days,'gmv_updated'=>$stamp,'ads'=>['amount'=>350000,'days'=>$days,'updated_at'=>$stamp],
            'topups'=>['amount'=>500000,'transactions'=>2,'complete'=>true,'updated_at'=>$stamp],
            'pending_states'=>['preparing'=>1000000,'pickup'=>0,'shipping'=>8000000,'delivered'=>2000000,'return'=>0,'unknown'=>1000000],
            'pending_state_counts'=>['preparing'=>4,'pickup'=>0,'shipping'=>20,'delivered'=>5,'return'=>0,'unknown'=>2],
            'pending_status_updated'=>$stamp,'pending_detail'=>12000000*$id,'released_detail'=>18500000*$id,'released_detail_days'=>$days,'adjustment'=>0];
    },$scoped);
    $reply(['status'=>'success','range'=>compact('start','end','days'),'stores'=>$stores]);
}
if ($path==='/procFinance/profitLoss') {
    $basis=$_GET['basis']??'accrual';
    $stores=array_map(fn($s)=>$s+['revenue'=>2000000,'cost'=>null,'gross_profit'=>null,'after_ads'=>null,'incomplete_cost_orders'=>1,'missing_cost_units'=>2,'revenue_orders'=>10,'revenue_days'=>$days,'coverage'=>['complete'=>true,'expected_days'=>$days],'ads'=>['amount'=>0,'days'=>$days,'complete'=>true]],$scoped);
    $reply(['status'=>'success','basis'=>$basis,'range'=>compact('start','end','days'),'stores'=>$stores,'totals'=>['revenue'=>2000000*count($stores),'cost'=>null,'gross_profit'=>null,'ads'=>0,'after_ads'=>null]]);
}
if ($path==='/procRealtime/metrics') {
    $ids=$_GET['shop_ids']??[1,2]; $count=count($ids);
    $reply(['status'=>'success','selected_shop_count'=>$count,'failures'=>[],'metrics'=>[
        'key_metrics'=>['uv'=>240*$count,'pv'=>580*$count,'product_clicks'=>120*$count,'orders'=>18*$count,'buyers'=>15*$count,'sales'=>1350000*$count],
        'metric_coverage'=>array_fill_keys(['uv','pv','product_clicks','orders','buyers','sales'],$count),
        'top_sales_items'=>array_map(fn($id)=>['shop_id'=>(int)$id,'shop_name'=>$shops[(int)$id-1]['name'],'item_name'=>'Contoh produk: tas perjalanan dengan kompartemen serbaguna','sales'=>850000*(int)$id],array_reverse($ids)),
        'product_shop_count'=>$count,'sales_hourly'=>[0,null,120000,40000,90000,200000,350000,80000,400000,120000,250000,600000],
        'hourly_coverage'=>[$count,0,$count,$count,$count,$count,$count,$count,$count,$count,$count,$count],
        'time'=>strtotime($stamp.' UTC')]]);
}
if ($path==='/procDashboard/overview') {
    $reply(['status'=>'success','range'=>compact('start','end'),'summary'=>['total_products'=>35*count($scoped),'total_customers'=>240*count($scoped),'total_orders'=>80*count($scoped),'completed_orders'=>70*count($scoped),'completed_order_value'=>5600000*count($scoped),'stock_out_count'=>count($scoped),'stock_low_count'=>2*count($scoped)],
        'shops'=>array_map(fn($s)=>$s+['sync_status'=>$s['id']===1?'connected':'expired','last_successful_sync_at'=>$stamp],$scoped),
        'low_stock'=>array_map(fn($s)=>['id'=>10+$s['id'],'shop_id'=>$s['id'],'shop_name'=>$s['name'],'name'=>'Contoh produk: tas perjalanan dengan nama panjang','total_stock'=>3],$scoped),'recent_orders'=>[]]);
}
if ($path==='/procFinance/sync') $reply(['status'=>'success','message'=>'Pratinjau: tidak ada sinkronisasi toko yang dijalankan.']);
if ($path==='/procWorkspace/save') $reply(['status'=>'success']);
if (str_starts_with($path,'/procnotifications/')) $reply(['status'=>'success','notifications'=>[],'groups'=>[],'summary'=>['total'=>0,'unread'=>0,'unread_urgent'=>0],'unread_count'=>0,'has_more'=>false,'group_count'=>0,'chat_cursor'=>'0']);
if (str_starts_with($path,'/proc')) {http_response_code(404);$reply(['status'=>'error','message'=>'Endpoint tidak tersedia di pratinjau.']);}
define('burl',''); define('assets','/assets'); define('web_icons','/assets/web_icons'); define('images','/assets/images'); define('app_name','SHOPDASH');
function authUser() {return ['id'=>0,'name'=>'Pratinjau','email'=>''];}
function authCsrfToken() {return 'dashboard-preview-only';}
class Alert {public static function show() {}}
require $root.'/app/helpers/WorkspacePolicy.php';
$data=['judul'=>'Pratinjau Dashboard · Shopdash','active_menu'=>'dashboard','finance_dashboard'=>true,'shops'=>($_GET['preview']??'')==='empty'?[]:$shops];
require $root.'/app/views/panel/templates/header.php';
echo '<p style="padding:10px 14px;margin:0 0 20px;border:1px solid currentColor;border-radius:6px;font-size:13px">Pratinjau Dashboard · Data contoh, tidak terhubung ke toko. Tema dapat diganti melalui ikon di kanan atas.</p>';
if (in_array($path,['/','/panel'],true)) require $root.'/app/views/panel/index.php';
else echo '<h1>Pratinjau hanya mencakup Dashboard</h1><p>Halaman lain tetap tersedia pada aplikasi utama.</p><a class="btn" href="/panel">Kembali ke Dashboard</a>';
require $root.'/app/views/panel/templates/footer.php';
