# Verifikasi halaman iklan

28 September 2026. Ruang lingkup: perubahan `/panel/ads`, JavaScript halaman,
dan CSS berawalan `#ads-monitor`. Shell navigasi aplikasi yang sudah ada tidak
didesain ulang. Antislop diterapkan selama pengerjaan sesuai pilihan pengguna.

## Arah visual

ENERGY 1 / RHYTHM 2 / MOTION 1. Palet krem, teks gelap, oranye identitas aplikasi,
dan tipografi aplikasi dipertahankan. Filter mengawali tugas; delapan metrik
menjadi fokus setiap toko; rincian tanggal dibuka sesuai kebutuhan. Saldo dan
status koneksi adalah informasi pendukung di bagian bawah. Status hijau hanya
untuk laporan yang tersedia dan tidak kedaluwarsa. Tidak ada animasi tambahan.

## Hasil eksekusi

- PASS: build Tailwind melalui `npm run build`.
- PASS: pemeriksaan sintaks PHP dan JavaScript serta `git diff --check`.
- PASS: 135 pemeriksaan mapping melalui `php tests/ads-performance.php` setelah audit browser.
- PASS: 83 pemeriksaan browser melalui `tests/ads-ui.cjs` dengan Chromium.
- PASS: sembilan kombinasi periode/channel memuat ketujuh toko dari API lokal.
- PASS: pemilihan toko, muat ulang, retensi pilihan dan rincian terbuka, serta
  pembatalan request saat filter berubah cepat.
- PASS: delapan nilai terformat sesuai capture, Pesanan=checkout, tabel satu hari dan 28
  tanggal, nol nyata berbeda dari nilai kosong, dan ROAS/CTR tanpa pembagi valid.
- PASS: data lama, penolakan laporan, sesi habis, kosong, HTTP 503, respons bukan
  JSON, dan jaringan gagal; respons gagal tidak menampilkan laporan filter lama.
- PASS: nama toko dirender sebagai teks, bukan HTML; token/cookie tidak diperlukan
  di kode browser. Sesi pengujian lokal dihapus saat tes selesai.

Data sukses dalam pengujian browser berasal dari fixture capture Sniper yang
diintersep hanya di browser pengujian, dengan nama "Fixture Sniper (uji browser)".
Tidak ada fixture yang dimasukkan ke database toko. Respons aplikasi sebenarnya
masih menunjukkan penolakan Shopee `90309999`, terpisah dari koneksi toko.

## Delivery Gate antislop

- PASS R-02: copy baru tidak memakai em dash.
- PASS R-03/R-34/C-4: tidak ada overflow halaman pada 320, 390, 768, dan 1440 px
  dalam tema terang/gelap; tabel memiliki area gulir sendiri. Screenshot diperiksa.
- PASS R-17/R-18/R-23/R-36/R-38/C-5: angka produksi hanya dari API; fixture
  berlabel hanya di tes; tidak ada statistik, identitas, testimoni, atau klaim baru.
- PASS R-24/R-26/C-2: filter, muat ulang, dan rincian berfungsi; tautan Toko dan
  Sinkronisasi diperiksa HTTP 200 menggunakan sesi pengujian.
- PASS R-25: teks memakai warna isi tema tanpa pengurangan opacity. Kontras
  teks utama 17,57:1 terang dan 15,60:1 gelap; catatan 15,90:1 dan 16,97:1;
  label status berhasil 6,95:1 dan 9,99:1. Semua melampaui AA 4,5:1.
- PASS R-27: loading, kosong, gagal, data lama, dan sesi habis memiliki pesan
  khusus; tidak ada fallback biaya periode ke meta biaya hari ini.
- PASS R-28: tidak ada FAQ pada layar operasional ini.
- PASS R-32: kontrol native berlabel, outline fokus eksplisit, pembukaan rincian
  dengan Enter dan fokus area tabel diperiksa melalui browser.
- PASS R-33/R-35: perubahan ditulis pada sumber view, JS, dan CSS; build dan
  eksekusi browser dilakukan tanpa error runtime.
- PASS R-01/R-04/R-06/R-07/R-08/R-09/R-10/R-12/R-13/R-19/R-22:
  tidak ada dekorasi, font, ikon, gradien, glow, glass, tekstur, atau animasi baru;
  label status berbatas dipakai untuk membedakan ketersediaan laporan.
- PASS R-05/R-14/R-20/R-37/C-1/C-3: susunan didasarkan pada tugas membandingkan
  periode/toko, lalu membaca delapan metrik yang diminta; arah visual dinyatakan
  sebelum pengerjaan. Setiap toko memakai struktur konsisten untuk perbandingan.
- PASS R-11/R-15/R-16/R-21/R-29/R-30/R-31: radius 6 px untuk status, 8 px untuk
  catatan, 12 px untuk wadah; label tindakan spesifik; kedua tema mengikuti
  pengaturan pengguna; palet dan identitas aplikasi dipertahankan.
- PASS Liveliness: dials dinyatakan; judul dan angka membentuk hierarki; jarak
  memisahkan filter/metrik/detail; oranye tetap menandai navigasi aktif aplikasi;
  tipografi tebal dan wadah krem mempertahankan identitas tampilan.

Screenshot lokal tersedia di `tmp/ads-ui/` (diabaikan Git), termasuk kondisi
produksi serta fixture terpisah untuk tiap ukuran dan tema.

## Akses sumber

Pemeriksaan Sniper tambahan menemukan nol kecocokan `SPC_CDS` antara capture
endpoint 721 dan cookie ketujuh toko aplikasi. Header/fingerprint dari sesi lain
tidak disalin ke sesi toko. Temuan ini belum menentukan penyebab pasti 90309999.
Audit browser berikutnya serta uji `bin/ads-diagnose.php` pukul 22.26 WIB memastikan
identitas cookie aplikasi hiban.store sama dengan browser (137867791, HTTP 200).
Laporan server tetap HTTP 403/error 90309999. Temuan identitas mengoreksi dugaan
salah toko, namun belum menjelaskan konteks SDK/sesi yang diperlukan laporan.
Nilai fingerprint/header sesi dari browser tidak dipasang statis ke aplikasi.

Koreksi UI setelah audit mempertahankan struktur dan CSS: catatan checkout,
batas dukungan Live, rekonsiliasi, dan status skipped/reused diperjelas. Pemeriksaan
browser mencakup Pesanan bulanan 648, Pesanan harian 30, serta metrik Live yang
belum terverifikasi tampil sebagai tanda -, bukan nol. Delivery Gate tetap PASS.
