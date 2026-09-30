<?php
// Render fixtures without a database, authentication session, or upstream request.
$root = dirname(__DIR__);
define('burl', 'https://fixture.invalid');
define('assets', 'https://fixture.invalid/assets');
set_error_handler(static function ($severity, $message, $file, $line) { throw new ErrorException($message, 0, $severity, $file, $line); });
$shop = ['id'=>1, 'shop_id'=>101, 'name'=>"Toko uji perjalanan 'A' & perlengkapan dengan nama panjang", 'shop_logo'=>'', 'username'=>str_repeat('akun-uji', 12), 'sync_status'=>'connected', 'total_products'=>3, 'balances'=>0, 'ads_credit'=>0];
$product = ['id'=>11, 'shop_id'=>1, 'name'=>"Produk uji 'A' & perjalanan dengan nama lengkap yang panjang " . str_repeat('ABCD', 24), 'cover_image'=>'broken-fixture', 'parent_sku'=>str_repeat('SKU', 25), 'price_min'=>0, 'price_max'=>0, 'selling_price_min'=>null, 'selling_price_max'=>null, 'total_stock'=>0, 'sold_count'=>0, 'status'=>1, 'has_discount'=>0];
$data = ['active_menu'=>'products', 'active_shop_id'=>1, 'shops'=>[$shop], 'products'=>[$product, array_replace($product,['id'=>12,'status'=>2,'price_min'=>15000,'price_max'=>25000,'selling_price_min'=>12000,'selling_price_max'=>20000,'cover_image'=>'']), array_replace($product,['id'=>13,'status'=>3,'price_min'=>null,'cover_image'=>''])], 'limit'=>10, 'current_page'=>1, 'total_pages'=>2, 'total_products'=>13];
$render = static function ($path, $data, $ord = null) use ($root) { ob_start(); require $root . '/app/views/panel/' . $path . '.php'; return ob_get_clean(); };
$productHtml = $render('products', $data);
$shopHtml = $render('shops', ['shops'=>[$shop,array_replace($shop,['id'=>2,'sync_status'=>'expired','shop_logo'=>''])]]);
$ord = ['id'=>21,'order_sn'=>'UJI-21','created_at'=>'2026-09-30 00:00:00','order_type'=>'normal','total_price'=>0,'status_type'=>'To Ship','status_description'=>'Kamu bisa atur pengiriman maks. 1 hari sebelum batas waktu pengiriman berakhir. &lt;a href="https://seller.shopee.co.id/edu/article/7093"&gt;Pelajari Lebih Lanjut.&lt;/a&gt;','payment_method'=>'','shipping_cargo'=>'','tracking_number'=>'','items'=>[['name'=>$product['name'],'quantity'=>1,'variation_name'=>'Uji']]];
$orderHtml = '<section id="orders-page"><div class="overflow-x-auto"><table class="table"><tbody>' . $render('order_row', [], $ord) . '</tbody></table></div></section>';

$check = static function ($condition, $message) { if (!$condition) throw new RuntimeException($message); };
$check(str_contains($productHtml,'data-product-details="11"'), 'Product details must identify the displayed product');
$check(str_contains($productHtml,'product-detail-11'), 'Product detail content is available without a request');
$check(str_contains($productHtml,'product-detail-dialog'), 'Native product dialog is rendered');
$check(str_contains($productHtml,'Rp 0'), 'Known zero price remains visible');
$check(str_contains($productHtml,'Belum tersedia'), 'Missing selling price is not zero');
$check(str_contains($productHtml,'Rp 15.000 sampai Rp 25.000'), 'Price range remains a range');
$check(!str_contains($productHtml,'js-floating-tooltip'), 'Product names no longer depend on a hover tooltip');
$check(str_contains($productHtml,'&#039;A&#039; &amp;'), 'Product identity is escaped');
$check(str_contains($productHtml,'data-label="Stok"'), 'Mobile stock has a visible label');
$check(str_contains($shopHtml,'aria-label="Perbarui koneksi'), 'Shop actions have accessible names');
$check(str_contains($shopHtml,'data-label="ID Toko"'), 'Mobile shop ID has a visible label');
$check(str_contains($orderHtml,'order-status-description'), 'Full order reason is rendered');
$check(str_contains($orderHtml,'<a href="https://seller.shopee.co.id/edu/article/7093"'), 'Order status education link is rendered as a link');
$check(!str_contains($orderHtml,'&lt;a href='), 'Order status education link is not shown as escaped markup');
$check(!str_contains($orderHtml,'js-floating-tooltip'), 'Order identity and status no longer depend on hover');
$emptyData = array_replace($data,['products'=>[],'shops'=>[],'active_shop_id'=>0,'total_products'=>0,'total_pages'=>1]);
$check(str_contains($render('products',$emptyData),'Belum ada produk'), 'Empty products retain an explanation');
$check(str_contains($render('shops',['shops'=>[]]),'Belum ada toko'), 'Empty shops retain an explanation');

if (($argv[1] ?? '') === '--actions') {
    preg_match_all('/onclick="((?:triggerEditCookie|confirmDelete)[^"]*)"/', $shopHtml, $matches);
    echo json_encode(['name'=>$shop['name'],'actions'=>array_map(static fn($v)=>htmlspecialchars_decode($v,ENT_QUOTES),$matches[1])],JSON_THROW_ON_ERROR);
    exit;
}
if (($argv[1] ?? '') !== '--preview') { echo "PASS: 15 database-free render checks\n"; exit; }

$stripScripts = static fn($html) => preg_replace('#<script\b[^>]*>.*?</script>#s', '', $html);
$style = file_get_contents($root.'/public/assets/css/style.css');
$script = file_get_contents($root.'/public/assets/js/product-details.js');
$icons = 'file://' . $root . '/public/assets/web_icons/material-symbols.css';
$fixtures = [];
foreach (['products'=>$productHtml,'shops'=>$shopHtml,'orders'=>$orderHtml,'empty-products'=>$render('products',$emptyData),'empty-shops'=>$render('shops',['shops'=>[]])] as $key=>$html) {
    $html = $stripScripts($html);
    $html = preg_replace('#https://(?:cf\.shopee\.co\.id/file/[^"\s]+|fixture\.invalid[^"\s]*)#', 'file:///missing-frontend-fixture', $html);
    $fixtures[$key] = '<!doctype html><html lang="id" data-theme="shopdash-light"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><style>'.$style.' body{margin:0} main{padding:16px} #page-loader{display:none}</style><link rel="stylesheet" href="'.htmlspecialchars($icons,ENT_QUOTES).'"></head><body class="bg-base-200"><main>'.$html.'</main><script>'.$script.'</script></body></html>';
}
?>
<!doctype html><html lang="id"><meta charset="utf-8"><title>Frontend comfort fixtures</title>
<style>body{font:14px system-ui;margin:16px;color:#222;background:#faf8f4}button,select{font:inherit;min-height:44px;padding:8px;margin:4px}iframe{display:block;border:1px solid #999;height:900px;max-width:none}pre{white-space:pre-wrap;max-width:1000px}</style>
<h1>Frontend comfort: data uji sintetis</h1>
<p>Tidak terhubung ke database atau layanan. Pemeriksaan ini hanya untuk permukaan yang diubah.</p>
<label>Halaman <select id="fixture"><option>products</option><option>shops</option><option>orders</option><option>empty-products</option><option>empty-shops</option></select></label>
<label>Lebar <select id="width"><option>320</option><option>500</option><option>999</option><option>1600</option></select></label>
<label>Tema <select id="theme"><option>light</option><option>dark</option></select></label>
<button id="run">Jalankan pemeriksaan</button><pre id="results" role="status">Belum diperiksa.</pre>
<iframe id="preview" title="Pratinjau frontend"></iframe>
<script>
const fixtures=<?= json_encode($fixtures,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); ?>;
const frame=document.getElementById('preview'),output=document.getElementById('results');
const sleep=ms=>new Promise(r=>setTimeout(r,ms));
function load(){frame.style.width=document.getElementById('width').value+'px';frame.srcdoc=fixtures[document.getElementById('fixture').value].replace('data-theme="shopdash-light"','data-theme="shopdash-'+document.getElementById('theme').value+'"');}
['fixture','width','theme'].forEach(id=>document.getElementById(id).addEventListener('change',load));load();
document.getElementById('run').addEventListener('click',async()=>{
  output.textContent='Memeriksa…';let count=0;const failures=[],metrics=[];
  const check=(ok,msg)=>{count++;if(!ok)failures.push(msg);};
  for(const theme of ['light','dark'])for(const width of [320,500,999,1600])for(const fixture of Object.keys(fixtures)){
    frame.style.width=width+'px';await new Promise(resolve=>{frame.onload=resolve;frame.srcdoc=fixtures[fixture].replace('data-theme="shopdash-light"','data-theme="shopdash-'+theme+'"');});await sleep(40);
    const d=frame.contentDocument,w=frame.contentWindow,key=fixture+'/'+width+'/'+theme;
    check(d.documentElement.scrollWidth<=w.innerWidth+1,key+' page overflow');
    if(fixture==='products'){
      const button=d.querySelector('[data-product-details="11"]');button.focus();button.click();
      const dialog=d.getElementById('product-detail-dialog');
      check(dialog.open,key+' dialog opens');check(d.activeElement.id==='product-detail-close',key+' initial focus');
      check(dialog.textContent.includes('Rp 0')&&dialog.textContent.includes('Belum tersedia'),key+' null/zero');
      check(dialog.scrollWidth<=dialog.clientWidth+1,key+' dialog overflow');
      dialog.close();await sleep(0);check(d.activeElement===button,key+' focus restored');
      check(!d.querySelector('.product-cover'),key+' image fallback');
      check([...d.querySelectorAll('.btn,.product-name,.select')].filter(e=>e.getClientRects().length).every(e=>e.getBoundingClientRect().height>=43.5),key+' controls 44px');
      const name=d.querySelector('.product-name');check(name.scrollWidth<=name.clientWidth+1,key+' full name reflow');
    }
    if(fixture==='shops')check([...d.querySelectorAll('.shop-table .btn')].every(e=>e.getBoundingClientRect().height>=43.5),key+' shop controls 44px');
    if(fixture==='orders'){const name=d.querySelector('.order-item-name'),reason=d.querySelector('.order-status-description');check(w.getComputedStyle(name).webkitLineClamp==='none',key+' full item name');check(w.getComputedStyle(reason).textOverflow!=='ellipsis',key+' full reason');}
    metrics.push(key+': overflow '+(d.documentElement.scrollWidth-w.innerWidth)+'px');
  }
  output.textContent=(failures.length?'FAIL':'PASS')+': '+count+' checks; '+failures.length+' failures\n'+failures.join('\n')+'\n'+metrics.join('\n');
  load();
});
</script></html>
