# Sinkronisasi iklan melalui cookie

Implementasi 29 September 2026 menggunakan endpoint
`POST /api/pas/v1/homepage/query/`. Bukti pemilihan endpoint ada di
[audit cookie](ads-cookie-endpoint-audit-20260929.md).

## Alur

`sync_schedules` → `sync_jobs` → worker CLI yang sudah ada → `AdsMonitor` →
`ShopeeCurl::getAdsSummary()` → `AdsCampaignReports` → `ad_shop_snapshots` →
`/panel/ads`.

Worker memakai cookie toko yang tersimpan. Identitas sesi harus cocok dengan
shop ID sebelum pengambilan. Pengambilan berjalan di backend; halaman dashboard
hanya membaca snapshot. Tombol Muat ulang tidak memicu request Shopee. Browser
worker, collector Sniper, dan login browser terpisah tidak diperlukan oleh jalur ini.

Tujuh jadwal iklan diaktifkan dengan interval **900 detik**. Scheduler yang
sudah terpasang memeriksa antrean setiap menit; worker bersama memproses job
secara berurutan, sehingga waktu pengambilan dapat bergeser mengikuti antrean.
Mac/server dan proses background harus tetap berjalan. Cookie yang tidak lagi
valid perlu diperbarui melalui halaman Toko.

## Kontrak laporan

- Produk memakai `product_homepage_v3`, Toko memakai `shop_homepage`, Live
  memakai `live_stream_homepage`.
- Setiap filter memakai `state=all`, tanpa kata pencarian dan tanpa filter rebate.
- Harian = hari ini, mingguan = Senin sampai hari ini, bulanan = tanggal 1
  sampai hari ini, seluruhnya dalam WIB.
- Limit 20, offset mengikuti jumlah entry yang benar-benar diterima. Maksimal
  100 halaman per laporan dan batas waktu batch 120 detik, diperiksa sebelum
  request berikutnya; request yang sedang berjalan memiliki timeout transport
  tersendiri. Jeda antarhalaman 350 ms.
- Sukses mensyaratkan HTTP 200, code 0, tanpa error, has_report_failure=false,
  jumlah campaign unik cocok dengan total, total stabil, dan metrik lengkap.
  Kegagalan di tengah pagination tidak menghasilkan laporan sukses parsial.
- Penolakan akses, rate limit, kegagalan koneksi, atau server error menghentikan
  sisa batch. Rentang identik menggunakan kembali satu hasil request.
- Pesanan = checkout; produk terjual = broad_order_amount; penjualan = broad_gmv;
  biaya = cost. Uang dibagi 100000 sekali. CTR/ROAS dihitung dari total.
- Live tetap hanya menampilkan penjualan, biaya, dan ROAS sesuai batas mapping
  yang sudah diverifikasi; metrik lain tidak ditafsirkan sebagai nol.

Sumber disimpan sebagai `cookie_campaign`, beserta endpoint, jumlah campaign,
jumlah halaman, waktu mulai dan waktu berhasil. Cookie, header sesi, dan isi
campaign yang tidak diperlukan tidak disimpan dalam laporan.

Laporan gagal mempertahankan hasil sukses terakhir dengan toko, channel,
rentang tanggal, dan versi mapping yang sama, serta menandainya belum terbaru.
Pergantian hari tidak memindahkan angka kemarin ke hari ini. Pilot import browser
tetap tersimpan terpisah; laporan sumber yang lebih baru digunakan oleh halaman.

## Rincian tanggal

Ringkasan tersedia untuk tiga periode. Untuk satu hari, total campaign merupakan
rincian tanggal tersebut. Endpoint daftar campaign tidak mengembalikan seri
waktu: rincian per tanggal mingguan/bulanan belum dikumpulkan, sehingga UI
menjelaskan keterbatasan ini dan tidak membagi total periode menjadi angka buatan.

## Verifikasi penerapan

- Pengambilan awal ketujuh toko sukses pada 11:17:05–11:17:41 WIB.
- Setelah jadwal diaktifkan pukul 11:18:58 WIB, scheduler membuat job
  15128–15134. Worker yang sudah berjalan menyelesaikan ketujuh job otomatis
  pada 11:22:13–11:22:53 WIB, seluruhnya completed tanpa last_error. Jadwal
  berikutnya tercatat 11:37:13–11:37:53 WIB. Tidak ada pemanggilan worker kedua
  atau pemrosesan manual job tersebut dalam verifikasi siklus otomatis ini.
- Pembacaan model dashboard menghasilkan **63/63 laporan tersedia**: tujuh toko
  × tiga channel × tiga periode, seluruhnya bersumber dari cookie_campaign.
- Enam metrik hiban.store tanggal 28 September cocok persis dengan grafik
  Seller Centre pada audit sebelumnya.
- Iklan Toko hiban.store: total harian dan bulanan cocok dengan respons grafik
  browser `97675.574` dan `97675.581`. Bulanan: 45.478 tayangan, 1.993 klik,
  21 checkout, 41 produk, broad_gmv raw 205687799997, cost raw 72993557486.
- Respons Live bulanan `97675.600` mengonfirmasi GMV dan biaya nol pada toko ini.
- Browser Shopdash memverifikasi tujuh laporan harian, filter toko, periode
  bulanan, jenis iklan, sumber laporan, dan metrik yang sesuai.

Pemeriksaan otomatis:

```sh
php tests/ads-campaign-reports.php  # 36
php tests/ads-performance.php       # 135
php tests/ads-browser-import.php    # 26, database sementara
php tests/sync-recovery.php         # 27, database sementara
php tests/product-sync.php          # 15
php tests/order-sync.php            # 19
node --check public/assets/js/ads.js
```

258 pemeriksaan lulus. Cakupan collector meliputi pagination, duplicate ID,
total berubah, overflow, field hilang, laporan kosong yang sah, batas halaman/
waktu, penghentian setelah penolakan, rasio, isolasi toko saat retensi, serta
pemastian jalur produksi tidak memanggil endpoint grafik yang ditolak.

Keberhasilan saat penerapan tidak menjamin endpoint internal Shopee tidak berubah.
Snapshot terakhir tetap tersedia ketika sesi atau layanan sumber bermasalah.

## Operasi

Pengaturan interval dan jeda tersedia di halaman Sinkronisasi. Kode tidak
mengaktifkan ulang jadwal yang dijeda operator. Instalasi background yang sudah
ada digunakan tanpa menambah worker kedua. Cadangan snapshot sebelum penerapan
ada di `storage/ads-audit-20260929/before-campaign-rollout.json` (diabaikan Git).

Pemeriksaan copy antislop: sumber data sesuai implementasi, Muat ulang tetap
menjelaskan pembacaan cache, dan rincian tanggal yang belum tersedia dinyatakan
secara eksplisit. Tata letak tidak diubah.
