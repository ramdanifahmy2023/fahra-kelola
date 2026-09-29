# Pilot laporan iklan melalui browser

Pembaruan 29 September 2026 pukul 11:08 WIB: [audit jalur cookie](ads-cookie-endpoint-audit-20260929.md)
membuktikan endpoint daftar campaign dapat dibaca lewat PHP pada tujuh toko.
Pilot browser di bawah adalah catatan tahap sebelumnya; browser worker tidak
lagi menjadi satu-satunya kandidat pengambilan yang didukung bukti.

Pilot 29 September 2026 berhasil menampilkan laporan **Iklan Produk / Hari Ini**
hiban.store dari Seller Centre ke `/panel/ads`. Pengambilan masih manual oleh
operator lokal. Enam toko lain serta laporan mingguan, bulanan, toko, dan live
belum dibuktikan melalui jalur ini. Jadwal iklan tetap dijeda.

Latar belakang dan referensi MCP Sniper ada di
[audit sesi](ads-session-audit-20260929.md). Request PHP tetap ditolak setelah
pembaruan cookie; penyebab persis penolakan belum diketahui.

## Bukti pilot

Pada tab browser yang sama, respons identitas sebelum dan sesudah laporan
menunjukkan shop ID 137867791. Toko lokal tujuan adalah ID 1.

| Observasi | Request CDP | Hasil |
| --- | --- | --- |
| Identitas sebelum | 40879.1038 | HTTP 200, errcode 0, shopid cocok |
| Laporan | 40879.1531 | HTTP 200, code 0, aggregate dan 96 titik waktu |
| Identitas sesudah | 40879.1584 | HTTP 200, errcode 0, shopid cocok |

Waktu laporan: **29 September 2026, 10:47:15 WIB**. Hasil tersimpan dan UI:
1.655 tayangan, 78 klik, CTR 4,71%, 2 pesanan, 3 produk, penjualan Rp237.865,
biaya Rp27.598,45, ROAS 8,62x. Angka adalah hasil pada waktu capture tersebut.
Ringkasan dan rincian 29 September cocok. Filter bulanan tidak memakai data harian.

## Alur operator

1. Buka Seller Centre dengan sesi toko yang hendak dibaca. Amati respons asli
   dari navigasi normal, menggunakan Network/CDP. Jangan membuat ulang signature.
2. Ambil respons `/api/v2/login/` sebelum laporan, lalu pilih jenis/periode
   yang benar. Ambil request bisnis dan respons sukses
   `POST /api/pas/v1/report/get_time_graph/`.
3. Ambil identitas lagi sesudah laporan pada konteks tab yang sama. Seluruh
   urutan harus selesai dalam 10 menit dan shopid kedua identitas harus cocok.
4. Ekspor bundle JSON yang hanya berisi data di bawah. Jangan ekspor cookie,
   URL query keamanan, fingerprint, atau header sesi. Simpan di luar Git.
5. Validasi, kemudian import melalui CLI lokal:

```sh
php bin/ads-import-browser.php --shop=1 --file=/path/capture.json --dry-run
php bin/ads-import-browser.php --shop=1 --file=/path/capture.json
```

`--shop` adalah ID lokal, bukan shop ID Shopee. CLI membandingkan shopid pada
capture dengan record toko tujuan. Import hanya menyimpan laporan sukses.
`import_processed` berarti input sudah diproses; capture lama atau identik
tidak menggantikan laporan yang lebih baru. Buka halaman Iklan dan muat ulang
untuk membaca hasil tersimpan.

Belum ada collector otomatis atau endpoint HTTP penerima. Hak menjalankan CLI
dan akses database lokal menjadi batas akses import. Bundle harus berasal dari
operator tepercaya: validasi metadata memeriksa konsistensi, bukan membuktikan
keaslian respons secara kriptografis.

## Kontrak bundle versi 1

- Root: `version: 1`, `source: "seller-centre-browser"`, `identity_before`,
  `report`, `identity_after`.
- Ketiga observasi: `context_id` sama, `request_id` berbeda, `http_status: 200`,
  `observed_at` ISO 8601 dengan zona waktu, `path` tanpa query keamanan.
- Identitas: path `/api/v2/login/`, response hanya `errcode` dan `shopid`.
- Laporan: path `/api/pas/v1/report/get_time_graph/`, `method: "POST"`,
  `request` bisnis (`agg_interval`, `campaign_type`, `filter_params`,
  `start_time`, `end_time`, `need_roi_target_setting`), serta `response`.
- Respons laporan: `code: 0`, `data.report_aggregate`,
  `data.report_by_time` berupa daftar `{key, metrics}`. Ekspor hanya metrik
  yang diizinkan `AdsPerformance::rawMetrics()`.
- Aggregate dan setiap titik wajib mempunyai integer nonnegatif `impression`,
  `click`, `checkout`, `broad_order_amount`, `broad_gmv`, `cost`. Jumlah setiap
  metrik titik waktu harus sama dengan aggregate. Titik duplikat ditolak.

Rentang harus cocok dengan hari, minggu kalender, atau bulan berjalan menurut
waktu capture dalam WIB. Filter campaign tertentu tidak boleh dianggap sebagai
laporan seluruh toko. Nol yang sah diterima; field hilang ditolak.

## Penyimpanan dan tampilan

`ad_browser_reports` memakai kunci toko lokal + shop ID Shopee + channel + tanggal
awal/akhir + versi mapping. Tabel dibuat oleh migrasi
`database/migrations/20260929_ads_browser_reports.sql`; bootstrap model/CLI
menjalankan `CREATE TABLE IF NOT EXISTS` yang sama.

Halaman memilih laporan browser yang cocok jika laporan server tidak tersedia
atau lebih lama. Snapshot server tetap terpisah, sehingga kegagalan sinkronisasi
PHP tidak menimpa laporan browser. Data berumur 15 menit ditandai belum terbaru.
Waktu import tidak menggantikan waktu pengambilan. Meta saldo tetap memakai
sumber serta waktu snapshot meta sendiri.

UI menyebut sumber browser dan pembaruan manual. Tombol Muat ulang membaca
data tersimpan. Parameter versi aset JS memastikan keterangan terbaru dimuat.

## Verifikasi

```sh
php tests/ads-browser-import.php
php tests/ads-performance.php
node --check public/assets/js/ads.js
```

26 pemeriksaan import dan 135 pemeriksaan mapping lulus. Tes database import
menggunakan tabel sementara pada koneksi sendiri. Cakupannya termasuk penolakan
toko salah, pergantian identitas, rentang salah, HTTP 200 berisi error, jumlah
tidak cocok, field rahasia tersaring, nol sah, import berulang, serta isolasi
toko/channel/tanggal. Browser memverifikasi nilai nyata, rincian tanggal,
keterangan manual, dan tidak adanya laporan harian pada filter bulanan.

Pemeriksaan copy antislop: keterangan menjelaskan perilaku nyata tombol dan
sumber data; tidak menjanjikan pengambilan otomatis. Tidak ada perubahan desain
visual atau kontrol baru dalam pilot ini.

## Tahap berikutnya

Verifikasi sesi browser dan identitas untuk setiap toko, lalu ulangi pembuktian
per jenis/periode. Tentukan collector dan autentikasi penerima sebelum membuat
pengiriman otomatis. Aktifkan jadwal hanya setelah alur tersebut terbukti.
