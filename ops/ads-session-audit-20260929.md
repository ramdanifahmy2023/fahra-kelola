# Audit sesi iklan, 29 September 2026

## Bukti sebelum perubahan runtime

- Repo bersih saat audit dimulai. Tidak ada perubahan kode aplikasi, cookie toko,
  snapshot produksi, campaign, anggaran, atau jadwal sinkronisasi.
- Pembacaan database menunjukkan tujuh jadwal `ads` dinonaktifkan. Snapshot
  ketujuh toko berstatus `partial`; request daily/product mencatat HTTP 403,
  error 90309999. Kombinasi berikutnya berstatus `skipped`, bukan request gagal
  yang dikirim terpisah. Data meta saldo tersedia.
- Uji `php bin/ads-diagnose.php --shop=1 --expect-shop=137867791` pada
  03:21:57 UTC (10:21:57 WIB) memverifikasi identitas hiban.store dengan HTTP
  200/code 0, kemudian menerima HTTP 403/error 90309999 untuk laporan harian.
  Uji tidak menyimpan hasil ke snapshot atau memperbarui cookie.
- Request bisnis aplikasi cocok dengan capture Sniper 11134. Normalisasi
  capture tersebut menghasilkan available=true dan rekonsiliasi matched.
  Seluruh 135 pemeriksaan `tests/ads-performance.php` lulus.

## Pemeriksaan browser yang baru

Tab audit membuka Seller Centre dan memilih Iklan Produk / Hari Ini. Setelah
reload, respons `/api/v2/login/` (request CDP `5842.1015`, HTTP 200, errcode 0)
menunjukkan shopid **137867791**, username **hiban.store**. Respons laporan
`5842.985` pada tab yang sama berhasil HTTP 200/code 0. Identitas serta angka
laporan diperiksa pada UI. Cookie dan token tidak dimasukkan ke dokumen ini.

Payload bisnis laporan:

```json
{
  "agg_interval": 1,
  "campaign_type": "product_homepage_v2",
  "start_time": 1790614800,
  "end_time": 1790701199,
  "need_roi_target_setting": false,
  "filter_params": {"campaign_type": "new_cpc_homepage"}
}
```

| Field aggregate | Nilai mentah |
| --- | ---: |
| impression | 1573 |
| click | 73 |
| checkout | 2 |
| broad_order_amount | 3 |
| broad_gmv | 23786499999 |
| cost | 2543523339 |
| ctr | 0.046408137317228225 |
| broad_roi | 9.35179152252316 |

UI menampilkan tayangan 1.6k, klik 73, CTR 4,64%, pesanan 2, produk 3,
penjualan Rp237.865, biaya Rp25.435, dan ROAS 9,35. Ini data pada waktu
pengamatan, bukan angka tetap atau fixture untuk dimasukkan ke produksi.

Request sebelumnya `5842.559` cocok dengan capture MCP project 1, endpoint
721, payload **11453**, tercatat 10:26:09: tayangan 1559, klik 71, biaya raw
2537463458. Request setelah reload memiliki angka lebih baru. Respons lebih
baru itu belum ditemukan dalam hasil MCP pada saat pemeriksaan. Tidak ada
kesimpulan tentang penyebab perbedaan ketersediaan capture tersebut.

Perbandingan token dilakukan di memori melalui query capture Sniper dan cookie
record lokal toko 1. SPC_CDS capture 11453 berbeda dari SPC_CDS Shopdash.
Capture 11134 juga berbeda. Nilai token tidak dicetak atau disimpan.
Kesamaan identitas toko tidak membuktikan kesamaan sesi browser dan server.

## Hasil setelah pengguna memperbarui cookie

Pengguna mengonfirmasi pembaruan cookie hiban.store. Perbandingan di memori
kemudian membuktikan SPC_CDS yang tersimpan cocok dengan capture 11453.
Kecocokan ini memverifikasi token tersebut; bukan klaim bahwa seluruh cookie
dan kondisi transport browser identik.

| Uji | Waktu WIB | Hasil |
| --- | --- | --- |
| Identitas melalui PHP dengan cookie baru | 10:30:15 | HTTP 200, code 0, shop ID 137867791 cocok |
| Laporan melalui adapter PHP yang ada | 10:30:16 | HTTP 403, error 90309999, aggregate tidak tersedia |
| Satu request PHP dengan body/fingerprint dan header sesi capture 11453 | 10:31:08 | HTTP 200, error 90309999, code tidak ada, aggregate tidak tersedia |

Uji terakhir memakai cookie baru dari record toko yang SPC_CDS-nya sudah cocok,
serta Accept, Content-Type, sc-fe-ver, sc-fe-session, af-ac-enc-dat,
af-ac-enc-sz-token, x-sz-sdk-version, dan User-Agent dari capture. Origin dan
Referer menunjuk Seller Centre dengan filter laporan yang sama. Nilai rahasia
hanya digunakan dalam memori satu proses; tidak ditanam ke kode, file, atau
database. Verifikasi TLS cURL tetap aktif pada uji ini. Hasil tidak disimpan
ke snapshot. Tidak ada pengulangan setelah penolakan.

Kesimpulan yang didukung: memperbarui cookie saja belum memulihkan adapter;
menyalin konteks request capture ke PHP pada percobaan tersebut juga belum
menghasilkan laporan. HTTP 200 saja bukan indikator sukses. Penyebab teknis
persis penolakan belum diketahui. Uji ini tidak memisahkan pengaruh umur token,
header, fingerprint, cookie lain, atau transport, sehingga tidak menetapkan
salah satunya sebagai penyebab tunggal.

## Rencana implementasi berdasarkan bukti

Jalur browser normal terbukti berhasil membaca laporan satu toko. Pengiriman
otomatis dari browser ke Shopdash dan operasi tujuh toko belum diimplementasikan
atau dibuktikan. Pencarian Royal pada pemilih toko tidak menampilkan pilihan
toko lain dalam pengamatan; ini tidak membuktikan akun tidak memiliki akses.
Filter tab audit dipulihkan ke Iklan Live / Hari Ini dan pencarian dikosongkan.

1. Buat pilot pengambilan melalui sesi browser hiban.store. Pembaca mengamati
   respons laporan dari navigasi/filter Seller Centre normal dan mengikatnya
   dengan hasil identitas pada tab/sesi yang sama. Jangan menghasilkan ulang
   signature atau menyimpan header keamanan sebagai konfigurasi statis.
2. Kirim hanya identitas toko, request bisnis, respons laporan yang diperlukan,
   dan waktu capture ke penerima Shopdash yang terautentikasi. Cookie, token,
   fingerprint, dan header keamanan tidak masuk payload penerima. Metode
   autentikasi penerima perlu dirancang sebelum membuat endpoint tulis.
3. Simpan laporan sukses dengan kunci toko lokal, shop ID Shopee, channel,
   tanggal awal/akhir, dan versi mapping. Simpan percobaan gagal terpisah.
   Sukses mensyaratkan code 0, tanpa error, dan aggregate valid. Pertahankan
   hasil sukses serta tanggal aslinya ketika request berikutnya gagal.
4. Halaman mengambil snapshot laporan yang sesuai filter dan tetap menampilkan
   meta saldo secara terpisah. Bedakan muat ulang cache, antrean pengambilan,
   browser perlu login, jadwal dijeda, laporan gagal, dan laporan tersimpan.
5. Uji pilot dari respons nyata sampai halaman Shopdash sebelum memperluas
   cakupan: identitas cocok, aggregate/rincian cocok, nol tidak dianggap error,
   kegagalan tidak menimpa sukses, dan laporan toko lain ditolak. Uji rentang
   kalender mingguan/bulanan melalui filter yang benar, bukan label rolling.
6. Siapkan dan validasi sesi masing-masing toko sebelum mengaktifkan jadwal
   browser. Tidak ada bukti bahwa satu sesi hiban.store dapat membaca tujuh
   toko. Scheduler PHP yang selalu ditolak tetap dijeda sampai ada jalur
   pengambilan yang terbukti untuk toko terkait.

Pada akhir tahap audit ini runtime, UI, dan scheduler belum diubah. Setelah
pengguna melanjutkan ke pilot, jalur import browser dan tampilan satu toko
diimplementasikan serta diverifikasi. Hasilnya ada di
[pilot laporan browser](ads-browser-pilot.md). Integrasi otomatis multi-toko
belum terbukti; scheduler tetap dijeda.
