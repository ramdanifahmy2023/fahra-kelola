<?php
define('burl','https://shopdash-fixture.test');
define('assets',burl.'/assets');
function authCsrfToken() { return 'fixture-token'; }
function authUser() { return ['id'=>0]; }
$page = $argv[1] ?? 'reports';
$shops = [];
foreach (range(1,8) as $id) $shops[] = ['id'=>$id,'name'=>$id===2 ? 'Toko Contoh Perlengkapan Perjalanan dengan Nama Sangat Panjang' : 'Toko Contoh '.$id,'shop_logo'=>$id===2 ? assets.'/broken-fixture' : ''];
$data = ['active_menu'=>$page,'active_shop_id'=>0,'shops'=>$shops];
?><!doctype html><html lang="id" data-theme="shopdash-light"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Pratinjau UX Shopdash</title><link rel="stylesheet" href="<?= assets; ?>/css/style.css"><link rel="stylesheet" href="<?= assets; ?>/web_icons/material-symbols.css"><style>body{margin:0;background:var(--color-base-200);color:var(--color-base-content)}main{max-width:1360px;margin:auto;padding:16px}.fixture-label{padding:8px 16px;font-size:14px}#page-loader{display:none}</style></head><body><p class="fixture-label">Data uji sintetis · tidak terhubung ke toko</p><main>
<?php if ($page === 'profit'): $financeDashboard=true; ?>
  <section id="finance-page" data-base="<?= burl; ?>" data-dashboard="true">
    <form id="profit-fixture-filters" class="finance-filters">
      <label>Toko<select class="select" id="finance-shops"><option value="1">Toko Contoh 1</option><option value="2">Toko Contoh 2</option></select></label>
      <label>Mulai<input class="input" id="fixture-start" type="date" value="2026-10-01"></label><label>Sampai<input class="input" id="fixture-end" type="date" value="2026-10-02"></label><button type="submit" class="btn">Terapkan</button>
    </form>
    <?php require __DIR__.'/../../app/views/panel/templates/finance-profit.php'; require __DIR__.'/../../app/views/panel/templates/finance-report-scripts.php'; ?>
  </section>
  <script>document.addEventListener('DOMContentLoaded',()=>{const root=document.getElementById('finance-page');const view=window.createFinanceProfitView({root,request:async action=>{const response=await fetch('<?= burl; ?>/procFinance/'+action);const data=await response.json();if(!response.ok)throw new Error(data.message);return data;}});window.refreshProfitFixture=()=>view.load({shops:document.getElementById('finance-shops').value,start:document.getElementById('fixture-start').value,end:document.getElementById('fixture-end').value,basis:document.getElementById('finance-profit-basis').value});document.getElementById('finance-profit-basis').addEventListener('change',window.refreshProfitFixture);document.getElementById('profit-fixture-filters').addEventListener('submit',e=>{e.preventDefault();window.refreshProfitFixture();});document.getElementById('finance-profit-retry').addEventListener('click',window.refreshProfitFixture);window.refreshProfitFixture();});</script>
<?php elseif ($page === 'products'):
  $product = ['id'=>11,'shop_id'=>1,'name'=>"Produk uji 'A' & perjalanan dengan nama lengkap yang panjang " . str_repeat('ABCD',24),'cover_image'=>'broken-fixture','parent_sku'=>str_repeat('SKU',25),'price_min'=>0,'price_max'=>0,'selling_price_min'=>null,'selling_price_max'=>null,'total_stock'=>0,'sold_count'=>0,'status'=>1,'has_discount'=>0,'variant_sku_count'=>1,'variants'=>[['id'=>1,'name'=>'Varian dengan nama panjang','sku'=>'SKU-PANJANG-01']]];
  $data += ['products'=>[$product,array_replace($product,['id'=>12,'total_stock'=>null,'price_min'=>null])],'limit'=>10,'current_page'=>1,'total_pages'=>2,'total_products'=>13];
  $data['active_shop_id']=1;
  $data['page_recap']=['id'=>'products-recap','title'=>'Ringkasan produk','scope'=>'Toko Contoh 1 · posisi saat ini','primary'=>['label'=>'Produk tercatat','value'=>13,'context'=>'Termasuk produk aktif dan arsip'],'metrics'=>[['label'=>'Aktif','value'=>13]],'segments'=>[['label'=>'Stok habis','value'=>1,'tone'=>'error','href'=>burl.'/panel/products?shop_id=1&stock=empty'],['label'=>'Data stok belum lengkap','value'=>1,'tone'=>'neutral','incomplete'=>true,'href'=>burl.'/panel/products?shop_id=1&stock=unknown']],'trend'=>[['label'=>'1 Okt','value'=>3],['label'=>'2 Okt','value'=>4]],'insight'=>'Data stok yang belum tersedia tetap perlu diperiksa.'];
  require __DIR__.'/../../app/views/panel/products.php';
else: require __DIR__.'/../../app/views/panel/reports.php'; endif; ?>
</main></body></html>
