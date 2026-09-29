# Bukti laporan iklan Seller Centre — 28 September 2026 WIB

Pengamatan menggunakan tab Seller Centre yang sudah login, akses Network CDP, pergantian filter UI, dan satu uji baca laporan melalui kelas request aplikasi yang berjalan. Tidak ada campaign, anggaran, saldo, cookie, atau pengaturan iklan yang diubah. Filter laporan dipulihkan ke Iklan Produk / Hari Ini. Pergantian tanggal oleh UI otomatis memanggil `report/update_time_config`; ini preferensi rentang laporan.

## Identitas dan cakupan bukti

- Browser: `GET https://seller.shopee.co.id/api/v2/login/`, HTTP 200, `errcode: 0`, `shopid: 137867791`, `username: hiban.store`. `shop_name` dalam respons ini kosong. Nama toko pada UI adalah hiban.store.
- Cookie aplikasi: pemeriksaan identitas melalui `ShopeeCurl::check()` berhasil mengembalikan `shop.id: 137867791`, `shop.name: hiban.store`. ID record lokalnya `1`, bukan ID Shopee.
- Toko lain belum dibuka di Seller Centre. UI aplikasi menampilkan tujuh toko dengan laporan ditolak 90309999; reproduksi request langsung dilakukan hanya untuk hiban.store.
- Proses PHP port 8123 (PID 19168 saat inspeksi) berjalan dari `/Volumes/DATA KERJA /Project Dev/fahra-kelola/shopdash`. Workspace tugas ini `/Volumes/DATA KERJA /Project Dev/Shopdash` berbeda. Diagnosis kode mengacu pada folder proses tersebut. Berkas bukti disimpan dalam workspace tugas ini; kode aplikasi tidak diedit.
- Rekaman awal sempat terputus/tereviksi saat reload. Bukti utama di bawah berasal dari perekaman ulang setelah `Network.enable`, memiliki request dan response yang berhasil dibaca. Ini bukan HAR lengkap semua trafik.

## Endpoint dan bukti sukses

Semua path Seller Centre di bawah menggunakan origin `https://seller.shopee.co.id`.

| Endpoint | Metode | Fungsi | Bukti |
|---|---|---|---|
| `/api/v2/login/` | GET | Identitas sesi browser | request `61308.6045`, HTTP 200, errcode 0, shopid 137867791 |
| `/api/pas/v1/report/get_time_graph/` | POST | Aggregate periode dan titik waktu produk/toko/live | keenam request pada tabel berikut: HTTP 200, code 0, msg OK |
| `/api/pas/v1/homepage/query/` | POST | Daftar campaign, bukan sumber utama delapan kartu | `61308.6911`, code 0, data mempunyai entry_list, total, has_report_failure |
| `/api/pas/v1/report/update_time_config/` | POST | UI menyimpan pilihan rentang laporan | `61308.6910`, code 0, body time_period=custom dan batas periode bulanan; tidak perlu dipanggil oleh pembaca laporan |
| `/api/framework/selleraccount/shop_info/` | GET | Validasi cookie aplikasi melalui method check | Uji server mengembalikan shop.id dan shop.name sesuai browser; status HTTP tidak disimpan oleh wrapper |

`get_time_graph` sudah mengandung semua field kandidat yang diperlukan, ditambah `checkout` yang justru cocok dengan kartu Pesanan. Tidak ada kebutuhan endpoint lain yang terbukti untuk delapan metrik produk/toko. Meta/saldo bukan pengganti laporan performa.

| Jenis / periode | Request ID | campaign_type | filter_params.campaign_type | agg_interval | Jumlah titik / selisih key |
|---|---|---|---|---|---|
| Produk harian | 61308.6792 | product_homepage_v2 | new_cpc_homepage | 1 | 96 / 900 detik |
| Produk bulanan | 61308.6833 | product_homepage_v2 | new_cpc_homepage | 96 | 28 / 86400 detik |
| Toko harian | 61308.6891 | shop_homepage | shop_homepage | 1 | 96 / 900 detik |
| Toko bulanan | 61308.6884 | shop_homepage | shop_homepage | 96 | 28 / 86400 detik |
| Live harian | 61308.6907 | live_stream_homepage | live_stream_homepage | 1 | 96 / 900 detik |
| Live bulanan | 61308.6914 | live_stream_homepage | live_stream_homepage | 96 | 28 / 86400 detik |

## Query, body, URL dan periode

Query API yang berhasil: `?SPC_CDS=<SPC_CDS_SESI_TOKO>&SPC_CDS_VER=2`. Tidak ada shop ID eksplisit di body; konteks identitas berasal dari sesi. Pada request browser harian yang diperiksa, nilai SPC_CDS query sama dengan cookie SPC_CDS yang benar-benar dikirim (`Network.requestWillBeSentExtraInfo`); nilai aslinya tidak disimpan dalam artefak.

Contoh body asli produk bulanan, fingerprint disanitasi:

```json
{
  "agg_interval": 96,
  "campaign_type": "product_homepage_v2",
  "start_time": 1788195600,
  "end_time": 1790614799,
  "need_roi_target_setting": false,
  "filter_params": {"campaign_type": "new_cpc_homepage"},
  "device_sz_fingerprint": "<FINGERPRINT_SESI_TOKO>"
}
```

Seluruh sembilan kombinasi periode/jenis terdapat di `requests.sanitized.json`. Enam adalah rekaman browser; tiga contoh mingguan memakai request harian yang ekuivalen secara tanggal, bukan klaim perekaman filter mingguan tersendiri.

| Periode | Awal WIB | Akhir WIB inklusif | start_time | end_time |
|---|---|---|---|---|
| Harian | 28 Sep 2026 00:00:00 | 28 Sep 2026 23:59:59 | 1790528400 | 1790614799 |
| Mingguan Senin–hari ini | 28 Sep 2026 00:00:00 | 28 Sep 2026 23:59:59 | 1790528400 | 1790614799 |
| Bulanan tanggal 1–hari ini | 1 Sep 2026 00:00:00 | 28 Sep 2026 23:59:59 | 1788195600 | 1790614799 |

Hari ini Senin. Menu Seller Centre hanya menampilkan Hari Ini, Kemarin, 1 Minggu Terakhir, 1 Bulan Terakhir, 3 bulan terakhir, serta kalender kustom. Jangan menganggap 1 Minggu Terakhir sama dengan Senin–hari ini, atau 1 Bulan Terakhir sama dengan tanggal 1–hari ini.

URL halaman berubah pada `type`, `from`, `to`, dan `group`:

- Produk: `type=new_cpc_homepage`; toko: `type=shop_homepage`; live: `type=live_stream_homepage`.
- Harian: `from=1790528400&to=1790614799&group=today`.
- Bulanan: `from=1788195600&to=1790614799&group=custom`.
- `offset` halaman pernah berubah 407 → 499 → 591 saat navigasi/scroll; tidak ada field offset dalam body get_time_graph. Fungsi tepat parameter URL ini belum dibuktikan. `source_page_id=1` terdapat pada URL awal lalu hilang saat pergantian jenis. Keduanya jangan dijadikan parameter laporan wajib tanpa bukti.
- Seluruh enam request laporan memakai query autentikasi yang sama bentuknya. Perubahan jenis/periode terdapat di body; tidak ada perubahan header yang terbukti diperlukan khusus oleh jenis/periode.

## Header yang benar-benar dikirim

Dibaca dari event ExtraInfo request produk harian `61308.6792`, bukan hanya perkiraan dari kode frontend:

```text
:authority: seller.shopee.co.id
:method: POST
:scheme: https
:path: /api/pas/v1/report/get_time_graph/?SPC_CDS=<SPC_CDS_SESI_TOKO>&SPC_CDS_VER=2
accept: application/json, text/plain, */*
accept-encoding: gzip, deflate, br, zstd
accept-language: id-ID,id;q=0.9,en-US;q=0.8,en;q=0.7
content-type: application/json;charset=UTF-8
content-length: 337
origin: https://seller.shopee.co.id
referer: https://seller.shopee.co.id/portal/marketing/pas/index?source_page_id=1&from=1790528400&to=1790614799&type=new_cpc_homepage&group=today&offset=591
cookie: <COOKIE_SESI_TOKO>
af-ac-enc-dat: <NILAI_SESI>
af-ac-enc-sz-token: <TOKEN_SESI>
sc-fe-session: <ID_SESI>
sc-fe-ver: 21.167729
x-sz-sdk-version: 1.12.33-sc.3
sec-ch-ua: "Google Chrome";v="153", "Not_A Brand";v="8", "Chromium";v="153"
sec-ch-ua-mobile: ?0
sec-ch-ua-platform: "macOS"
sec-fetch-dest: empty
sec-fetch-mode: cors
sec-fetch-site: same-origin
priority: u=1, i
user-agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36
```

Content-Length adalah fakta rekaman, bukan konstanta untuk disalin. Header HTTP/2 pseudo juga bukan header kustom cURL. Tidak terlihat `Authorization`, `X-CSRFToken`, atau `X-CSRF-Token` pada request ini. Itu tidak membuktikan sistem tidak memakai proteksi CSRF. Kehadiran header/fingerprint tidak membuktikan setiap field wajib; tidak dilakukan percobaan menghapus header atau merekayasa token keamanan.

## Mapping dan pencocokan halaman

| Metrik aplikasi | Field terbukti untuk kartu produk/toko | Skala / rumus |
|---|---|---|
| Iklan dilihat | impression | integer, tanpa skala |
| Jumlah klik | click | integer, tanpa skala |
| CTR / persentase klik | ctr | rasio × 100; sama dengan click / impression × 100 ketika penyebut > 0 |
| Pesanan | checkout | integer. Jangan memakai broad_order untuk meniru kartu ini |
| Produk terjual | broad_order_amount | integer |
| Penjualan | broad_gmv | raw / 100000 IDR |
| Biaya iklan | cost | raw / 100000 IDR |
| ROAS | broad_roi | tanpa skala; broad_gmv / cost ketika cost > 0 |

Sampel produk harian: `checkout=30`, `broad_order=33`, `direct_order=31`; UI Pesanan **30**. Sampel bulanan: `checkout=648`, `broad_order=756`, `direct_order=664`; UI **648**. Toko bulanan: `checkout=21`, `broad_order=31`; UI **21**. Ini mengoreksi kandidat mapping sebelumnya dan kode aplikasi yang masih menggunakan broad_order.

Perbedaan atribusi harus dipertahankan: field broad dan direct adalah kelompok data berbeda. Secara istilah broad menyatakan cakupan atribusi lebih luas daripada direct, tetapi jendela atribusi, deduplikasi, dan definisi persis Shopee tidak diverifikasi lewat tooltip/dokumentasi pada sesi ini. Jangan menyebut checkout sebagai direct_order atau broad_order. Simpan ketiganya sebagai field terpisah. Contoh produk bulanan: broad_gmv=4502688546188, direct_gmv=4039595324903; broad_order_amount=1189, direct_order_amount=1064. Broad tidak boleh diam-diam diganti direct saat kosong.

| Sampel | Dilihat (raw → UI) | Klik (raw → UI) | CTR UI | Pesanan | Produk terjual (raw → UI) | Penjualan UI | Biaya UI | ROAS UI |
|---|---|---|---|---|---|---|---|---|
| Produk harian/mingguan | 6785 → 6.8k | 387 | 5,70% | 30 | 38 | Rp1.684.590 | Rp163.050 | 10,33 |
| Produk bulanan | 207723 → 207.7k | 9768 → 9.8k | 4,70% | 648 | 1189 → 1.2k | Rp45.026.885 | Rp4.376.033 | 10,29 |
| Toko harian/mingguan | 149 | 9 | 6,04% | 0 | 0 | Rp0 | Rp25.000 | 0,00 |
| Toko bulanan | 44931 → 44.9k | 1981 → 2k | 4,41% | 21 | 41 | Rp2.056.878 | Rp704.936 | 2,92 |

Contoh verifikasi uang: raw biaya bulanan produk `437603269846 / 100000 = 4376032.69846`, dibulatkan menjadi Rp4.376.033; raw penjualan `4502688546188 / 100000 = 45026885.46188`, menjadi Rp45.026.885. Jangan membagi ROAS/CTR dengan skala uang. Jangan menjumlahkan angka tampilan yang telah dibulatkan.

Live berhasil mengembalikan semua sembilan field dalam berkas proyeksi, semuanya nol untuk periode ini. UI Live hanya menampilkan Penonton 0, Pesanan 0, Konversi 0%, Penjualan Rp0, Biaya Rp0, Efektivitas Iklan 0,00. Iklan dilihat, klik, CTR, dan produk terjual tidak tampil sebagai kartu yang sama. Nol saja tidak cukup membuktikan mapping semantik live; tandai mapping tersebut belum tervalidasi dengan sampel nonnol. Jangan menamai Penonton sebagai impression tanpa bukti.

## Rincian tanggal dan agregasi

`report_by_time[].key` adalah Unix timestamp detik berupa string pada sampel. `agg_interval:1` menghasilkan 96 slot 15 menit untuk satu hari, termasuk slot hari ini yang belum berlalu. `agg_interval:96` menghasilkan 28 slot harian berawal tengah malam WIB untuk 1–28 September. Kedua observasi sesuai satuan kelipatan 15 menit, tetapi nilai interval lain belum diuji.

Jumlah impression, click, checkout, broad_order, broad_order_amount, broad_gmv, cost pada semua titik sama persis dengan report_aggregate, pada keenam rekaman. Untuk tampilan per tanggal, kelompokkan slot ke tanggal **Asia/Jakarta** dan jumlahkan metrik aditif; hitung ulang CTR dan ROAS dari total pembilang/penyebut, jangan rata-ratakan rasio slot. Tetap gunakan aggregate server sebagai sumber ringkasan periode dan jadikan hasil penjumlahan pemeriksaan rekonsiliasi.

Untuk hari ini, jangan tampilkan slot masa depan sebagai data observasi final. Nol live dengan code 0 adalah data respons sukses, bukan error; `null`, field hilang, dan respons ditolak bukan nol. Bila penyebut rasio nol, simpan nilai raw API (sampel live mengembalikan 0) dan tandai rasio hasil perhitungan sendiri sebagai tidak terdefinisi/null bila diperlukan, agar maknanya jelas.

Weekly Senin–hari ini saat ini hanya satu tanggal sehingga sama dengan harian. Aplikasi memakai agg_interval=12 untuk weekly; request itu belum tervalidasi browser dalam investigasi ini. Untuk minggu multihari, rekam kalender kustom Senin–hari berjalan terlebih dahulu dan konfirmasi intervalnya. Menggunakan 96 untuk memperoleh bucket harian adalah usulan implementasi yang masih perlu uji untuk rentang mingguan; jangan menuliskannya sebagai hasil capture saat ini.

## Diagnosis 90309999

Fakta terverifikasi:

1. UI aplikasi menunjukkan tujuh laporan produk harian ditolak 90309999, sementara status koneksi/meta tersedia. Itu bukti snapshot aplikasi, bukan tujuh uji baru.
2. Uji baca server baru untuk hiban.store memakai cookie yang disimpan aplikasi, menghasilkan identitas toko 137867791 yang sama dengan browser, lalu laporan gagal dengan `error:90309999` dan tanpa aggregate. Key respons: is_customized, is_login, action_type, error, tracking_id, redirect_to_error_page. Nilai flag lainnya dan status HTTP tidak direkam; jangan menebaknya.
3. Request server harian memiliki batas tanggal, campaign_type, filter_params, need_roi_target_setting, dan agg_interval yang sama dengan browser. Body server tidak menyertakan device_sz_fingerprint.
4. Kode aplikasi mengambil SPC_CDS dari cookie record toko itu sendiri. Browser juga mengirim SPC_CDS query yang sama dengan cookie requestnya. **Kesamaan token browser dengan token aplikasi tidak dibandingkan**; identitas toko yang sama tidak berarti sesi yang sama.
5. Browser mengirim af-ac-enc-dat, af-ac-enc-sz-token, sc-fe-session, sc-fe-ver, x-sz-sdk-version dan header konteks browser lainnya; kode request aplikasi tidak menyusunnya. Browser memakai Chrome 153 macOS, kode aplikasi mengirim Chrome 120 Windows. Referer aplikasi hanya path index, sementara browser menyertakan filter pada query.
6. Kode aplikasi memakai `$blockedResponse` setelah error 90309999 pertama lalu meneruskannya ke kombinasi lain. Karena itu sembilan kartu gagal pada satu toko **tidak membuktikan sembilan request terpisah ditolak**.
7. Mapping Pesanan yang keliru merupakan bug tampilan/normalisasi terpisah, bukan penyebab penolakan upstream.

Dugaan yang masuk akal tetapi belum terbukti: perbedaan konteks sesi/browser dan field keamanan dapat memengaruhi penerimaan endpoint laporan. Cookie yang cukup untuk identitas/meta belum tentu cukup untuk endpoint ini. Kode numerik saja tidak menetapkan penyebab sebagai cookie kedaluwarsa, CSRF, fingerprint, IP, rate limit, ataupun batas tanggal.

Tidak ada bukti baru untuk menyimpulkan mismatch SPC_CDS rekaman Sniper lama merupakan penyebab semua tujuh toko. Rekaman Sniper lama tidak direplay; token/fingerprint antartoko tidak digunakan.

Langkah berikutnya: bila perlu refresh sesi, pengguna memasukkan cookie toko terkait melalui halaman pengelolaan toko `/panel/shops`. Setelah itu verifikasi identitas sebelum satu uji laporan baca. Jangan menjanjikan refresh cookie akan menyelesaikan error. Rekam HTTP status, error, flag tantangan keamanan, header names, timestamp dan shop ID tanpa menyimpan token; jalankan satu request per langkah. Bila Seller Centre menampilkan tantangan keamanan, hentikan dan minta pengguna menyelesaikan alur normal. Jangan memalsukan atau menyalin token/fingerprint dari toko/sesi lain. Bila akses server tetap ditolak, gunakan integrasi resmi atau ekspor yang disediakan Seller Centre dan beri status laporan tidak tersedia.

## Instruksi untuk Codex workspace implementasi

Kerjakan pada repo aplikasi yang benar-benar menjalankan port 8123. Mulai dengan memeriksa perubahan pengguna; jangan menimpa implementasi yang sedang dikerjakan.

1. Pada `app/helpers/AdsPerformance.php`, perbaiki mapping `orders` dan daftar field agregasi tanggal menjadi `checkout` untuk kesesuaian kartu Pesanan produk/toko. Pertahankan broad_order dan direct_order terpisah bila diperlukan. Metadata `attribution: broad` tunggal saat ini tidak cukup menjelaskan checkout; dokumentasikan sumber per metrik. Jangan terapkan mapping live sebagai terverifikasi sebelum mendapat sampel nonnol/definisi UI.
2. Gunakan tanggal kalender Asia/Jakarta: daily mulai hari ini 00:00, weekly mulai Senin, monthly mulai tanggal 1; end_time adalah tengah malam besok dikurangi satu detik. Saat weekly sama dengan daily, deduplikasi fetch berdasarkan toko/sesi, jenis, range, interval, dan filter. Konfirmasi agg_interval untuk minggu multihari dari browser, jangan menganggap angka 12 teruji.
3. Pertahankan raw money integer 64-bit/decimal, bagi 100000 untuk nilai IDR, bulatkan hanya ketika merender. Recompute CTR/ROAS dari total, tanpa merata-ratakan persentase. Tangani null, field hilang, dan nol secara terpisah.
4. Pada `app/models/ShopeeCurl.php`, jangan menempel konstanta token/fingerprint/header sesi dari laporan ini. Query SPC_CDS harus berasal dari sesi yang sama dan divalidasi shop ID-nya. Request body produk/toko/live di requests.sanitized.json adalah acuan payload, bukan paket kredensial siap replay.
5. Tambahkan observabilitas tersanitasi pada request: HTTP status, nama endpoint, shop ID terverifikasi, body tanpa fingerprint, nama header, code/error dan status pemeriksaan identitas. Jangan log cookie, query token, fingerprint, header keamanan, tracking URL, maupun token lengkap.
6. Bedakan request ditolak langsung dari request yang dilewati akibat circuit breaker. Simpan alasan 'skipped after previous 90309999' agar laporan diagnosis tidak menyebut semua kombinasi sudah dicoba. Jangan loop retry agresif pada challenge/rejection.
7. Jangan menggunakan meta ads_expense_today untuk mengisi biaya laporan produk, toko, atau live. Meta dan graph berbeda cakupan. Jangan menjumlahkan atribusi lintas jenis tanpa aturan deduplikasi.
8. Gunakan JSON proyeksi sebagai fixture normalisasi (bukan pengganti data produksi), dengan uji bermakna: Pesanan 648 vs broad_order 756, skala uang, total 28 hari, CTR/ROAS berbobot, Senin dan pergantian bulan WIB, missing/null/zero, error 90309999 serta retain-last-good yang hanya berlaku pada identitas/rentang/jenis yang sesuai.
9. Validasi terakhir harus memakai request nyata yang berhasil pada sesi toko yang benar; fixture lulus tidak membuktikan autentikasi server sudah pulih. Tidak ada perbaikan autentikasi yang diklaim selesai dalam investigasi ini.

## Berkas dan batasan

- `requests.sanitized.json`: sembilan contoh request; ekuivalensi weekly diberi label eksplisit.
- `product-monthly.response.projection.json`, `shop-monthly.response.projection.json`, `live-monthly.response.projection.json`: envelope respons sukses, aggregate, dan **seluruh 28 titik waktu**. Ini **proyeksi sembilan field** (delapan metrik beserta broad_order untuk pembanding), bukan dump respons lengkap; field setting dan metrik lain sengaja tidak disertakan. Nilai yang disertakan berasal dari respons, bukan angka dummy. Titik live nol diserialisasikan setelah semua titik/field diverifikasi nol.
- `daily-details.csv`: 84 baris rincian harian untuk ketiga jenis. Uang dalam IDR, CTR dalam persen; sumbernya respons bulanan yang sama. Tanggal 28 juga merupakan rincian harian/mingguan yang diminta.
- Token, cookie, dan fingerprint asli tidak disimpan di artefak. Pengamatan read-only ini tidak membuktikan field keamanan mana yang wajib, tidak menguji sesi tujuh toko secara terpisah, dan tidak memulihkan pengambilan laporan server.
