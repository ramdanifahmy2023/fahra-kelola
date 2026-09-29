# Audit dan rencana Saldo Penjual, 30 September 2026

Status: **rencana, belum diimplementasikan**. Arahan terbaru pengguna: fokus pada angka **Saldo** di Shopee, tampilkan sebagai **Saldo Penjual** pada Dashboard dan Keuangan, dan abaikan Saldo Aktif. Informasi ditahan atau dapat ditarik boleh menjadi keterangan tambahan jika makna dan alasannya benar-benar dijelaskan oleh Shopee. Tidak menghitung estimasi dana yang dapat ditarik.

Koreksi audit: penafsiran sebelumnya tentang nominal `wallet_blocked_balance` sebagai uang yang ditahan tidak cukup terverifikasi dan ditarik kembali. Nama field maupun selisih antara dua saldo tidak membuktikan arti bisnis atau alasan penahanan. Jangan membawa asumsi tersebut ke implementasi.

Audit hanya membaca Seller Centre, XYZ Sniper MCP, source aplikasi, dan endpoint saldo. Tidak ada penarikan, perubahan rekening, perubahan jadwal, atau perubahan kode aplikasi. Baseline audit awal: `ca5e5cb`; revisi rencana mengikuti arahan pengguna setelah main `0a09483`.

## Bukti sumber

- Halaman yang dibuka di tab audit tersendiri: `https://seller.shopee.co.id/portal/finance/wallet/shopeepay`. UI menampilkan **Saldo**, **Saldo Aktif**, dan tombol **Tarik Dana**. Pada sampel browser, kedua nominal berbeda dan tombol penarikan disabled.
- XYZ Sniper MCP project 1 (`seller shopee`), endpoint **10103**, capture **10230/10237**, `last_seen=2026-09-30 00:39:05`: GET `/api/v4/seller/local_wallet/get_wallet_status`. Capture 10230 memakai `SPC_CDS`, `SPC_CDS_VER=2`, `wallet_provider=0`, `bank_account_id=0`; capture 10237 memakai konteks rekening yang dipilih. Cookie/token dan ID rekening tidak dicatat dalam dokumen.
- Respons memiliki envelope `error:0`, `error_msg`, `data`, bukan `code:0` seperti income. Nilai `wallet_available_balance` cocok dengan label **Saldo** di UI. **Nominal sudah dalam rupiah**, tidak dibagi 100000 seperti data income.
- Probe GET server pada **30 September 2026, 00:43:49–00:43:56 WIB** berhasil HTTP 200 / `error:0` untuk **7/7 toko**. Cookie tiap toko dibaca dari konfigurasi tersimpan; respons `shop_info` diperiksa terhadap `shops.shop_id` sebelum membaca saldo. Probe menggunakan TLS verification dan tanpa redirect. Tidak menyimpan respons mentah atau mengubah tabel Finance.
- Pada satu toko, respons memuat pesan pembatasan penarikan yang menyebut verifikasi identitas. Pesan ini adalah informasi terpisah dari nominal Saldo; tidak membuktikan penyebab nominal field saldo lain. Enam toko lain tidak memiliki pembatasan pada flag yang diperiksa, tetapi ketiadaan flag pembatasan tidak cukup untuk menjanjikan bahwa saldo pasti dapat ditarik.
- Capture pendukung tentang opsi rekening, limit, biaya dan field `blocked_amount` pernah dibaca. Arti enum dan hubungan nominalnya tidak ditetapkan sebagai kontrak produk. Pemeriksaan rekening atau kalkulasi biaya tidak diperlukan untuk menampilkan angka Saldo dalam lingkup yang direvisi.
- Header MCP berasal dari ringkasan endpoint terkini, bukan snapshot immutable per capture. Audit tidak menyalin signature atau mengklaim header historis tertentu sebagai syarat keberhasilan. Probe server berhasil tanpa replay signature browser.

## Kontrak angka

| Informasi | Field / sumber | Perlakuan |
| --- | --- | --- |
| Saldo Penjual | `wallet_available_balance` | Ambil angka asli yang cocok dengan label Saldo di Shopee; jangan kurangi berdasarkan status, saldo lain, limit atau biaya. |
| Keterangan pembatasan | Status dan pesan eksplisit Shopee yang telah dicocokkan dengan UI | Tampilkan sebagai informasi terpisah. Jika alasan tidak tersedia, jangan menebaknya. Jangan menetapkan nominal ditahan dari selisih atau nama field. |
| Informasi dapat ditarik | Hanya pernyataan sumber yang jelas dan terverifikasi | Tidak disimpulkan dari saldo positif atau flag pembatasan false. Ketiadaan informasi bukan alasan untuk menahan penampilan Saldo. |
| Saldo ShopeePay | `shopeepay_available_balance` | Dompet berbeda; tidak masuk total Saldo Penjual. |
| Sudah dilepas | Endpoint income yang sudah digunakan | Arus dana selama periode. Tidak sama dengan saldo dompet yang tersisa sekarang. |

Pending, dana dilepas, dan Saldo Penjual tidak dijumlahkan menjadi satu total uang. Topup iklan bisa tercatat sebagai pengeluaran dompet; jangan dikurangkan kembali dari saldo dompet yang sudah diberikan Shopee.

## Rencana tampilan

Tambahkan kartu **Saldo Penjual** pada komponen bersama Dashboard/Keuangan. Keterangan ringkas: **Saldo terakhir dari Shopee**. Nominal berasal langsung dari field Saldo. Tampilkan nilai yang sama pada rincian setiap toko, beserta waktu pembaruan. Saldo Aktif, nominal diblokir, dan estimasi dapat ditarik tidak ditampilkan atau dihitung.

- Angka utama menjumlahkan Saldo semua toko terpilih yang datanya tersedia, termasuk toko yang mempunyai pembatasan penarikan. Pembatasan tidak mengurangi atau mengubah saldo menjadi nol.
- Data saldo yang belum tersedia tidak diubah menjadi nol. Total sebagian menyebut jumlah toko yang datanya sudah masuk; jika belum ada data saldo sama sekali, tampilkan `Belum tersedia`. Saldo terverifikasi nol tetap Rp0.
- Keterangan seperti `Penarikan dibatasi` dan alasan singkat hanya muncul ketika didukung status/pesan Shopee yang jelas. Alasan panjang berada dalam disclosure. Jangan menampilkan label nominal `Dana ditahan` atau label `Bisa ditarik` berdasarkan dugaan. Jika sumber menyatakan pembatasan tetapi tidak menjelaskan alasannya, tulis bahwa alasan belum tersedia.
- Filter toko berlaku untuk seluruh angka. Filter tanggal hanya berlaku bagi laporan periode seperti Sudah dilepas/Omset/Iklan. Saldo dompet mengikuti posisi terakhir walaupun pengguna memilih bulan lampau. Keterangan tanggal pada ringkasan toko harus ikut diperbarui agar tidak menyebut semua angka selain Pending mengikuti periode.
- Desktop: tiga angka utama dapat berbagi baris jika lebar konten cukup. HP: susun vertikal, nominal dominan, satu keterangan status singkat, detail panjang dalam disclosure yang sudah ada. Verifikasi berdasarkan lebar konten setelah sidebar, bukan lebar layar saja.
- Gunakan ikon dompet dengan label teks, existing shop logos, tema Shopdash dan warna status yang sudah ada. Jangan menambah tombol untuk menjalankan penarikan dalam fitur ini.

Design direction tetap ENERGY 1 / RHYTHM 2 / MOTION 1. Antislop menjaga bahasa singkat, arti uang yang berbeda, dan metadata tidak membanjiri ringkasan. Pencarian UI UX Pro Max pertama tidak menemukan padanan; hasil pencarian lanjutan tidak menyediakan pola khusus saldo penjual. Rekomendasi layout di sini mengikuti brief pengguna dan pola aplikasi yang sudah ada, bukan klaim pola terverifikasi dari database skill. Tidak ada visual baru yang dinyatakan sudah lolos uji.

## Urutan implementasi

1. **Tetapkan kontrak Saldo.** Gunakan `wallet_available_balance` yang cocok dengan angka Saldo di halaman Shopee. Jangan membuat rumus dari Saldo Aktif atau field lain. Konfirmasi status/pesan sebelum memakainya sebagai keterangan; kelengkapan informasi penarikan tidak menjadi syarat untuk menampilkan saldo yang valid.
2. **Adapter saldo terpisah.** Tambahkan helper khusus wallet yang memeriksa HTTP, `error:0`, tipe field dan identitas cookie. Gunakan request saldo yang sudah terverifikasi dengan `wallet_provider=0,bank_account_id=0`; tidak memerlukan data rekening. Jangan memakai parser income atau faktor 100000. Transport dengan TLS verification; tidak menyalin header anti-bot dari capture. Batasi field yang diambil dan jangan menyimpan HTML mentah. Pesan pembatasan dijadikan teks biasa dan di-escape.
3. **Penyimpanan posisi terakhir.** Migrasi additive khusus wallet, berisi local shop ID, source shop ID, nominal Saldo, keterangan sumber opsional, waktu sukses, waktu percobaan, dan error tersanitasi. Latest success tidak hilang ketika refresh gagal. Cookie yang berpindah identitas tidak boleh meminjam data toko sebelumnya. Nilai hilang berbeda dari nol.
4. **Integrasi worker Finance yang ada.** Refresh wallet independen dari penyelesaian seluruh halaman income, di bawah lock toko dan batas request yang sudah ada. Jalankan pemeriksaan due sebelum early return `hasWork()`; backlog atau kegagalan income tidak boleh menahan wallet tanpa batas. Kegagalan wallet juga tidak menggagalkan publikasi income yang valid. Tidak menambah runner paralel atau mengubah ekstensi/Boost/Chat.
5. **Jadwal dan refresh manual.** Saat audit, 7/7 jadwal Finance aktif dengan interval **600 detik**. Usulan awal mengikuti jadwal 10 menit ini dengan kontrol due agar tidak menarik saldo setiap halaman impor. Antrean dapat membuat pembaruan lebih lambat; tampilkan timestamp sebenarnya. Halaman tetap membaca database setiap 30 detik, bukan memanggil Shopee per pengunjung. Tombol Perbarui saldo Shopee harus tetap menjadwalkan wallet walau income tidak mempunyai pekerjaan baru, dengan deduplikasi dan CSRF yang sudah ada.
6. **API dan dua halaman.** Tambahkan kontrak wallet pada `Finance::summary` dan komponen bersama `app/views/panel/finance.php` / `public/assets/js/finance.js`. Hitung satu toko sekali, gunakan cakupan toko yang sama, pertahankan pilihan toko/tanggal serta disclosure/fokus saat polling. Perbarui help text yang saat ini mengatakan seluruh saldo berasal dari Penghasilan Saya, karena wallet punya sumber sendiri.
7. **Verifikasi lalu kirim.** Cek head main terbaru, kerja di worktree Finance, gabungkan perubahan lain tanpa mengganti source mereka. Deploy hanya setelah parser saldo, keterangan berbasis sumber, agregasi dan UI memenuhi kriteria berikut.

## Kriteria selesai

- Identitas satu/banyak toko, raw rupiah, pemisahan ShopeePay, nol terverifikasi, field hilang, respons gagal dan saldo lama diuji tanpa penarikan nyata.
- Saldo toko yang dibatasi tetap masuk total. Perubahan Saldo Aktif, field blocked, limit atau biaya tidak mengubah nominal Saldo yang ditampilkan. Alasan tidak disimpulkan dari nama field/selisih; tidak muncul klaim dapat ditarik tanpa bukti sumber.
- Total parsial menyebut toko yang data saldonya belum tersedia. Data lama diberi waktu pembaruan sebenarnya. Mengubah periode tidak mengubah posisi dompet.
- Refresh wallet tetap berjalan saat antrean income panjang atau tidak ada import baru; failure tidak merusak data income. Manual refresh tidak menggandakan job.
- Dashboard dan Keuangan memberikan angka yang sama untuk filter sama. Rincian per toko memakai angka Saldo asli dan keterangan sumber opsional. Hilangnya informasi penarikan tidak menghapus saldo yang valid.
- Uji 320/500/999/1600px, lebar konten 684px, tema terang/gelap, nama panjang, nominal besar, zoom, keyboard dan pergantian filter. Semua endpoint bisnis mutasi dimock; tes tidak menarik dana.

Tahap audit sudah membuktikan akses field Saldo server untuk semua toko. Penyimpanan wallet, keterangan sumber, agregasi baru dan UI masih pekerjaan implementasi. Tidak ada implementasi Saldo Aktif atau perhitungan kelayakan/estimasi penarikan dalam lingkup ini. Jangan menyajikan rencana ini sebagai fitur yang sudah live.
