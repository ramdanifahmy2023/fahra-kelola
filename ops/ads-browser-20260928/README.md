# Verifikasi browser Seller Centre — 28 September 2026, WIB

Pengamatan read-only melalui Chrome yang sudah login, CDP Network, dan tampilan Seller Centre. Tidak ada perubahan campaign, anggaran, saldo, atau pengaturan. Tab asal terikat ke sesi Codex lain; pengamatan menggunakan tab baru di profil Chrome yang sama. Data dapat berubah setelah waktu pengamatan (sekitar 22.15–22.25 WIB).

## Identitas dan batas bukti

GET `https://seller.shopee.co.id/api/v2/login/` memberikan HTTP 200, `errcode=0`, `username=hiban.store`, `shopid=137867791`. Header halaman juga menampilkan hiban.store / Hiban.store:main. Field `shop_name` dalam respons kosong: nama terverifikasi di sini adalah username/label toko, bukan klaim nama legal. Field respons identitas lainnya tidak disimpan.

Aplikasi `/panel/ads` menampilkan tujuh toko, seluruh laporan belum tersedia dengan 90309999, sedangkan koneksi/meta tersedia. Ini bukti tampilan snapshot aplikasi, bukan tujuh request server baru dalam investigasi ini. Cookie aplikasi tidak dibaca, diubah, atau dibandingkan langsung dengan sesi browser. Ketidakcocokan SPC_CDS Sniper sebelumnya berasal dari laporan terdahulu dan belum diverifikasi ulang.

## Endpoint

| Endpoint pada seller.shopee.co.id | Metode | Fungsi | Bukti sukses baru |
| --- | --- | --- | --- |
| `/api/v2/login/` | GET | Identitas sesi aktif | HTTP 200, errcode 0, shopid 137867791 |
| `/api/pas/v1/report/get_time_graph/` | POST | Aggregate dan time series produk/toko/live | HTTP 200, code 0 pada keenam kombinasi hari ini/bulan berjalan × produk/toko/live |
| `/api/pas/v1/meta/get_ads_data/` | POST dalam kode aplikasi | Meta/saldo | Tidak direkam ulang sebagai bukti sukses Network pada investigasi ini; meta sukses terlihat pada snapshot aplikasi |

Tidak ditemukan kebutuhan endpoint laporan kedua untuk delapan field produk/toko pada capture ini. Live memakai endpoint yang sama tetapi tampilan metrik berbeda. Jangan menggunakan meta sebagai pengganti laporan periode.

## Query, body, dan URL halaman

Contoh sembilan kombinasi periode/channel ada di `requests.sanitized.json`. Semua nilai autentikasi adalah placeholder, bukan kredensial yang dapat digunakan.

Query laporan: `?SPC_CDS=<SESSION_SAME_SHOP>&SPC_CDS_VER=2`.

```json
{
  "agg_interval": 96,
  "campaign_type": "product_homepage_v2",
  "start_time": 1788195600,
  "end_time": 1790614799,
  "need_roi_target_setting": false,
  "filter_params": {"campaign_type": "new_cpc_homepage"},
  "device_sz_fingerprint": "<SAME_SESSION_BROWSER_GENERATED>"
}
```

| Jenis | campaign_type body | filter_params.campaign_type / type URL |
| --- | --- | --- |
| Produk | product_homepage_v2 | new_cpc_homepage |
| Toko+ | shop_homepage | shop_homepage |
| Live | live_stream_homepage | live_stream_homepage |

URL halaman: `/portal/marketing/pas/index?type=<TYPE>&from=<START>&to=<END>&group=<GROUP>`. `offset` juga muncul (622/714 pada pengamatan), tidak ada di body laporan; maknanya belum dibuktikan dan tidak dipakai sebagai zona waktu. `source_page_id=1` merupakan parameter navigasi yang kadang muncul.

| Periode | WIB inklusif | start_time | end_time | group UI | agg_interval teramati | Titik |
| --- | --- | --- | --- | --- | --- | --- |
| Harian | 28/09 00:00:00–23:59:59 | 1790528400 | 1790614799 | today | 1 | 96, jarak 900 detik |
| Minggu berjalan | Senin 28/09–28/09 | 1790528400 | 1790614799 | today (rentang sama) | 1 untuk rentang satu hari ini | Sama dengan harian |
| Bulan berjalan | 01/09 00:00:00–28/09 23:59:59 | 1788195600 | 1790614799 | custom | 96 | 28, jarak 86400 detik |

Hari investigasi adalah Senin. Mingguan tidak diklaim sebagai request terpisah dengan agg_interval 12. Kode aplikasi saat ini mengirim 12 untuk weekly; keberhasilan request server tersebut belum dibuktikan. Opsi UI `1 Minggu Terakhir` adalah rolling period dan tidak digunakan sebagai minggu kalender. Bulan berjalan dipilih manual tanggal 1 dan 28, bukan `1 Bulan Terakhir`.

## Mapping delapan metrik dan pencocokan UI

| Tampilan | Field | Skala / rumus | Produk bulanan: API → UI |
| --- | --- | --- | --- |
| Iklan dilihat | impression | Integer | 207751 → 207.8k |
| Jumlah klik | click | Integer | 9769 → 9.8k |
| CTR | ctr | ×100%; verifikasi click/impression×100 | 0.04702263767683429 → 4,70% |
| Pesanan | **checkout** | Integer untuk kecocokan UI produk/toko ini | **648 → 648** |
| Produk terjual | broad_order_amount | Integer unit | 1189 → 1.2k |
| Penjualan | broad_gmv | ÷100000 IDR | 4502688546188 → Rp45.026.885 |
| Biaya iklan | cost | ÷100000 IDR | 437631585759 → Rp4.376.316 |
| ROAS | broad_roi | Rasio; broad_gmv/cost | 10.288765008537553 → 10,29 |

**Koreksi mapping sebelumnya:** `broad_order` bukan angka kartu Pesanan yang teramati. Produk bulanan: checkout=648, broad_order=756, direct_order=664. Produk hari ini: checkout=30, broad_order=33, direct_order=31. Toko bulanan: checkout=21, broad_order=31. Ini membuktikan perbedaan nilai; definisi deduplikasi/atribusi checkout tidak ditetapkan hanya dari nama field.

| Periode/channel | Impresi | Klik | CTR UI | Pesanan UI/checkout | Unit | Penjualan UI | Biaya UI | ROAS UI |
| --- | ---: | ---: | ---: | ---: | ---: | ---: | ---: | ---: |
| Hari ini / produk | 6813 (6.8k) | 388 | 5,69% | 30 | 38 | Rp1.684.590 | Rp163.333 | 10,31 |
| Bulanan / produk | 207751 (207.8k) | 9769 (9.8k) | 4,70% | 648 | 1189 (1.2k) | Rp45.026.885 | Rp4.376.316 | 10,29 |
| Hari ini / toko | 149 | 9 | 6,04% | 0 | 0 | Rp0 | Rp25.000 | 0,00 |
| Bulanan / toko | 44931 (44.9k) | 1981 (2k) | 4,41% | 21 | 41 | Rp2.056.878 | Rp704.936 | 2,92 |

Live berhasil dengan semua field metrik terkait bernilai 0, 96 titik hari ini dan 28 titik bulanan. Namun UI Live menampilkan Penonton, Pesanan, Konversi, Penjualan, Biaya, Efektivitas Iklan. Tidak ada pembuktian bahwa impression/click/ctr merupakan metrik bermakna yang sama untuk Live; nilai nol tidak cukup untuk memetakan Penonton atau Konversi. Jangan mengganti metrik tersebut secara diam-diam.

`broad_*` dan `direct_*` disimpan terpisah. Pada produk bulanan broad_gmv=4502688546188 sedangkan direct_gmv=4039595324903. Tampilan Penjualan cocok dengan broad. Secara konsep broad mencakup atribusi lebih luas dan direct atribusi langsung; jendela atribusi serta aturan detail belum diverifikasi dalam investigasi ini. Jangan menukar field atau menjumlahkan penjualan antar-channel tanpa aturan deduplikasi.

## Respons JSON dan rincian tanggal

`product-monthly.response.sanitized.json` adalah **proyeksi respons asli**, bukan seluruh envelope byte-for-byte: mempertahankan code, aggregate relevan dan **semua 28 key/titik** beserta tujuh field aditif. Field lain/setting dihilangkan; rasio per titik tidak disalin. Tidak ada angka metrik yang diisi dari data toko lain atau dibuat-buat. Rasio rincian dapat dihitung dari field aditif; aggregate juga menyimpan ctr, broad_roi dan pembanding direct.

Jumlah seluruh titik produk cocok tepat dengan aggregate: impression 207751; click 9769; checkout 648; broad_order 756; broad_order_amount 1189; broad_gmv 4502688546188; cost 437631585759.

Konversikan `key` (string Unix seconds) ke tanggal Asia/Jakarta. Untuk rentang harian, kelompokkan bucket 15 menit menurut tanggal WIB. Untuk bulanan, bucket sudah harian. Jangan merata-ratakan CTR atau ROAS: hitung rasio dari total pembilang dan penyebut. Gunakan aggregate sebagai ringkasan resmi, lalu rekonsiliasi jumlah titik; jangan diam-diam mengganti aggregate bila tidak cocok. Jangan menganggap bucket masa depan sebagai data final. Data hari ini masih bergerak dan atribusi dapat diperbarui. Nilai hilang tetap null; nol yang benar-benar dikirim tetap nol. Untuk pembagi nol, tetapkan kebijakan tampilan eksplisit (UI Shopee pada live nol menampilkan 0,00; aplikasi saat ini memilih null).

## Request browser dibanding kode aplikasi

Sumber kode: `app/models/ShopeeCurl.php`, `app/helpers/AdsPerformance.php`. Ini perbandingan konfigurasi kode, bukan packet capture koneksi server.

| Komponen | Browser sukses | Aplikasi saat dibaca | Kesimpulan |
| --- | --- | --- | --- |
| Identitas | username hiban.store, shopid 137867791 dari respons | Kartu hiban.store ada; ID/sesi cookie belum dibandingkan | Kesamaan sesi belum terbukti |
| SPC_CDS | Query SPC_CDS dan SPC_CDS_VER=2 | Diekstrak dari cookie toko | Tidak boleh memakai nilai capture Sniper/toko lain |
| Cookie | Dikirim menurut ExtraInfo Network; nilainya tidak disimpan | Dikirim oleh cURL dari cookie toko | Kesetaraan cookie belum diperiksa |
| Content-Type | application/json;charset=UTF-8 | application/json | Berbeda, belum terbukti penyebab |
| Origin | https://seller.shopee.co.id | Sama | Ada pada ExtraInfo |
| Referer | URL halaman beserta filter | URL dasar /portal/marketing/pas/index | Berbeda |
| User-Agent | Chrome 153 / macOS | Chrome 120 / Windows | Berbeda |
| Header sesi/SDK | af-ac-enc-dat, af-ac-enc-sz-token, sc-fe-session, sc-fe-ver, x-sz-sdk-version | Tidak dibuat di jalur laporan | Kehadiran terbukti; keharusan belum terbukti |
| Browser headers lain | sec-ch-ua, sec-ch-ua-mobile, sec-ch-ua-platform, sec-fetch-site=same-origin, sec-fetch-mode=cors, sec-fetch-dest=empty, Accept-Language, Accept-Encoding | Tidak dibuat eksplisit di jalur laporan | Jangan menebak header sebagai perbaikan pasti |
| device_sz_fingerprint | Ada pada body browser | Tidak ada pada requestBody aplikasi | Perbedaan terbukti, kausalitas belum terbukti |
| Body bisnis | campaign_type, filter_params, need_roi_target_setting=false, timestamp | Sama untuk daily/monthly | Bukan bukti sesi server valid |
| weekly interval | Rentang Senin ini memakai today/1 | Selalu 12 | Perlu verifikasi berdasarkan panjang rentang |

Tidak terlihat header bernama X-CSRFToken/X-CSRF-Token pada request laporan yang diperiksa. Ini tidak membuktikan tidak ada proteksi CSRF atau bahwa SPC_CDS berfungsi sebagai CSRF: hanya query/cookie/header aktual yang dapat ditegaskan. Jangan menciptakan nilai fingerprint, token, atau signature, dan jangan menyalinnya lintas sesi.

## Error 90309999

Fakta: browser toko 137867791 berhasil mengambil laporan; snapshot aplikasi tujuh toko menunjukkan error 90309999 tetapi meta tersedia; jalur laporan kode aplikasi berbeda dalam fingerprint, header sesi/SDK, User-Agent, Referer, dan interval weekly. Kode juga menghentikan request laporan lain pada siklus toko setelah error 90309999 pertama, sehingga status kombinasi berikutnya tidak membuktikan semua kombinasi pernah dikirim.

Dugaan yang belum terbukti: sesi aplikasi kedaluwarsa/tidak setara; kebutuhan konteks autentikasi laporan yang lebih ketat daripada meta; penilaian sesi/perangkat/transport. Tidak ada bukti bahwa satu field yang hilang adalah akar penyebab, bahwa toko diblokir permanen, atau bahwa endpoint berubah.

Langkah berikutnya: cocokkan shop ID lewat endpoint identitas menggunakan sesi aplikasi toko yang sama, simpan hanya ID dan status kecocokan, kemudian uji satu laporan daily/product dari server. Bila perlu memperbarui cookie, pengguna memasukkannya melalui `/panel/shops`; jangan melalui chat, source code, atau cookie toko lain. Jika ada challenge, pengguna menyelesaikan alur normal Seller Centre. Hasil uji harus menyertakan HTTP status, kode API, timestamp, endpoint, body bisnis, nama header, dan status kehadiran field rahasia tanpa nilainya. Lanjutkan hanya dengan mekanisme autentikasi yang sah; jangan membangun bypass proteksi atau menempelkan token browser statis sebagai solusi produksi.

## Instruksi untuk Codex workspace

1. Baca laporan ini dan fixture baru; jangan memperlakukan mapping Sniper terdahulu sebagai bukti UI terkini.
2. Untuk produk/toko, ubah mapping Pesanan dari broad_order ke checkout pada aggregate dan daily grouping. Pertahankan broad_order dan direct_order terpisah bila diperlukan. Tidak boleh fallback diam-diam ketika checkout hilang. Tes dengan nilai yang berbeda: 648/756/664 dan 21/31.
3. Pertahankan skala uang 100000 dan presisi integer raw sampai formatting. CTR=100×click/impression; ROAS=broad_gmv/cost. Uji hasil harian produk 30 pesanan, 38 unit, Rp1.684.590, Rp163.333.
4. Rentang WIB inklusif. Pada 28/09 weekly=daily; deduplikasi request berdasarkan shop/channel/start/end/interval. Jangan memaksakan interval 12 untuk minggu satu hari berdasarkan asumsi. Rentang lebih panjang perlu capture tambahan; 12 dari mapping lama belum diuji baru.
5. Modelkan dukungan metrik per channel. Live tidak dianggap memiliki delapan metrik UI terverifikasi; jangan mengganti Penonton dengan impresi atau Konversi dengan CTR.
6. Pisahkan masalah mapping dari autentikasi. Tambahkan diagnostik tersanitasi dan validasi identitas, bukan fingerprint/token statis. Jangan menulis cookie/log header utuh. Jangan menjalankan worker untuk tujuh toko berulang-ulang ketika satu laporan sudah ditolak.
7. Pertahankan unavailable/stale dan cache terakhir hanya untuk identitas/rentang/channel sama. Meta sukses tidak mengubah laporan menjadi available. Jangan mengganti error dengan angka nol.
8. Verifikasi perubahan normalisasi secara lokal dengan fixture dan tampilkan hasil sebelum menguji sesi server. Tidak ada kode runtime yang diubah dalam investigasi ini.
