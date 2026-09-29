# Mapping performa iklan Shopee

Sumber: XYZ Sniper MCP, project `1` (seller shopee), endpoint `721`,
`POST /api/pas/v1/report/get_time_graph/`. Diverifikasi pada 28 September 2026.
Capture harian `1766`, mingguan `10033`, rentang panjang `1287`, iklan toko `1329`, dan live `1335`.
Mapping dikoreksi berdasarkan audit UI dalam [ads-browser-20260928/README.md](ads-browser-20260928/README.md)
dan [ads-network-2026-09-28/README.md](ads-network-2026-09-28/README.md).
Paket kedua disalin dari workspace `Shopdash/shopdash/docs` ke folder ops aplikasi
aktif tanpa mengubah berkas sumber. Fixture hanya menyimpan data tersanitasi.

| Tampilan | Field respons `data.report_aggregate` | Konversi |
| --- | --- | --- |
| Iklan dilihat | `impression` | Bilangan bulat |
| Jumlah klik | `click` | Bilangan bulat |
| Persentase klik | `click / impression` | × 100 persen; sesuai `ctr × 100` |
| Pesanan | `checkout` | Bilangan bulat; cocok dengan kartu Pesanan produk/toko |
| Produk terjual | `broad_order_amount` | Jumlah unit, bukan jumlah pesanan |
| Penjualan | `broad_gmv` | ÷ 100000 menjadi IDR |
| Biaya iklan | `cost` | ÷ 100000 menjadi IDR |
| ROAS | `broad_gmv / cost` | Rasio, sesuai `broad_roi` |

Pesanan memakai `checkout`: audit bulanan produk menunjukkan 648, berbeda dari
`broad_order=756` dan `direct_order=664`. Toko menunjukkan 21, berbeda dari
`broad_order=31`. Ketiga field disimpan terpisah dalam `raw_metrics`; tidak ada
fallback jika checkout hilang. Definisi deduplikasi checkout belum dibuktikan.
Produk terjual/penjualan memakai broad. Laporan antar-channel tidak dijumlahkan.

`supported_metrics` membatasi Live ke penjualan, biaya, dan ROAS; field lainnya
belum terverifikasi sebagai kartu yang sama dengan UI produk/toko. Nilai mentah
tetap disimpan, sementara nilai tampilan yang belum terverifikasi tetap null.
Tidak ada pemetaan Penonton menjadi impression atau Konversi menjadi CTR.

## Request dan periode

Query autentikasi memakai `SPC_CDS` milik cookie toko dan `SPC_CDS_VER=2`.
Cookie tetap berada di server. Token MCP hanya dipakai saat discovery; aplikasi
tidak membutuhkan MCP berjalan untuk sinkronisasi rutin.

| Channel | `campaign_type` | `filter_params.campaign_type` |
| --- | --- | --- |
| product | product_homepage_v2 | new_cpc_homepage |
| shop | shop_homepage | shop_homepage |
| live | live_stream_homepage | live_stream_homepage |

`need_roi_target_setting=false`. Batas tanggal menggunakan Asia/Jakarta (WIB),
mulai 00:00:00 sampai 23:59:59 hari ini, inklusif:

- `daily`: hari ini, `agg_interval=1` (15 menit).
- `weekly`: Senin sampai hari ini; satu hari memakai 1, multihari memakai 12.
- `monthly`: tanggal 1 sampai hari ini; satu hari memakai 1, multihari memakai 96.

Periode mingguan/bulanan aplikasi adalah kalender berjalan. Capture Sniper mencakup
rentang 7 hari dan 32 hari; label rentang tersebut tidak diubah menjadi satu bulan
kalender. Audit baru memverifikasi bulan kalender 1–28 September dengan interval
96 dan Senin satu hari dengan interval 1. Interval 12 mempunyai bukti capture
Sniper tujuh hari, tetapi belum diverifikasi ulang untuk minggu kalender multihari.
Request yang body-nya sama dalam satu sinkronisasi toko dideduplikasi: pada Senin
harian dan mingguan memakai satu request per channel.

`report_by_time[].key` berisi Unix timestamp. Metrik hitungan dan uang dijumlahkan
per tanggal WIB; CTR dan ROAS dihitung ulang dari total, bukan rata-rata rasio.
Presisi uang dipertahankan sampai pemformatan tampilan. Pembagi nol atau field
yang hilang menghasilkan `null`, bukan nilai nol buatan. Titik di masa depan
diabaikan dalam rincian harian. Metrik aditif mentah tetap integer sampai konversi.
Rasio raw API disimpan terpisah dari rasio hasil perhitungan, termasuk raw 0 ketika
rasio hasil perhitungan tidak terdefinisi. `reconciliation` membandingkan jumlah
titik dengan aggregate; ketidakcocokan diberitahukan tanpa menimpa aggregate.

## Integrasi

`ShopeeCurl::getAdsSummary()` mengambil laporan seluruh kombinasi periode/channel.
`AdsMonitor` menyimpan hasil pada payload snapshot yang sudah ada; worker `ads`
dan `bin/ads-worker.php` otomatis memakai implementasi ini.

Endpoint aplikasi (login diperlukan):

```text
GET /procads/summary?period=weekly&channel=product&shop_id=1
X-Requested-With: XMLHttpRequest
```

`period`: daily/weekly/monthly; `channel`: product/shop/live. Default daily/product.
Parameter tidak valid mendapat HTTP 422. `metrics.performance` menyertakan delapan
metrik, `daily`, rentang tanggal, atribusi, waktu pengambilan, dan status error.
Respons hanya mengambil cache; pemilihan filter tidak memanggil Shopee langsung.

Halaman `/panel/ads` memakai endpoint ini untuk filter periode dan jenis iklan;
filter toko bekerja pada hasil yang telah dimuat. Rincian tanggal dibuka per toko,
dengan tabel yang dapat digeser secara horizontal pada layar kecil. Tombol
"Muat ulang data" membaca snapshot terbaru. Link "Lihat sinkronisasi" membuka
halaman pengelolaan sinkronisasi yang sudah ada. Status penolakan laporan dan
status koneksi toko ditampilkan terpisah; biaya meta hari ini tidak dipakai sebagai
fallback biaya laporan. Pergantian filter membatalkan request sebelumnya.

Jika pembaruan gagal, hasil valid terakhir untuk identitas Shopee, rentang, channel,
dan versi mapping yang sama disimpan dengan `stale=true`, waktu sukses lama, dan
error terbaru. Mapping versi 1 tidak dapat dipakai lagi sebagai fallback versi 2
karena definisi Pesanan berbeda. Identitas sesi diperiksa terhadap shop_id toko
sebelum pengambilan laporan; cache juga diperiksa identitasnya saat dibaca.
Snapshot hari sebelumnya tidak dipakai sebagai data hari ini. Data lebih dari
15 menit ditandai stale, sesuai interval worker iklan. Meta saldo iklan dan
status sesi toko terpisah dari ketersediaan laporan performa.

## Validasi dan batasan akses

```sh
php tests/ads-performance.php
php bin/ads-diagnose.php --shop=1 --expect-shop=137867791
```

Pada verifikasi langsung 28 September 2026, endpoint laporan mengembalikan
`error=90309999` untuk ketujuh toko. Respons meta masih dapat berhasil.
Jangan menganggap toko terhubung berarti laporan tersedia, mengisi angka nol,
atau memasukkan capture Sniper sebagai data toko tanpa identitas yang terverifikasi.
Setelah satu penolakan 90309999, sisa request laporan toko dalam siklus itu dihentikan.
`request_state` membedakan `sent`, `reused` (body yang sama), dan `skipped`.
Request dilewati tidak memiliki waktu percobaan maupun status HTTP miliknya sendiri.

Uji tunggal terbaru 28 September 2026 pukul 22.26 WIB: identitas hiban.store cocok
dengan shop_id 137867791, HTTP 200/code 0. Laporan daily/product dari cookie aplikasi
mendapat HTTP 403/error 90309999. Ini bukan sesi tujuh toko yang diuji ulang.
Diagnostik menyimpan path tanpa query, waktu, status HTTP/API, nama header, dan
boolean kehadiran cookie/fingerprint; tidak menyimpan nilai rahasia.

Identitas yang sama tidak membuktikan konteks sesi browser sama. Browser sukses
mengirim header SDK/sesi dan fingerprint yang tidak tersedia di jalur server.
Kehadirannya belum membuktikan field mana penyebab penolakan. Tidak ada token atau
fingerprint statis dari audit yang ditempelkan sebagai solusi autentikasi.
