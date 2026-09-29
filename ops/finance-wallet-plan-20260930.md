# Audit dan rencana Saldo Penjual, 30 September 2026

Status: **rencana, belum diimplementasikan**. Pengguna meminta tambahan informasi saldo yang dapat ditarik pada Dashboard dan Keuangan. Audit ini hanya membaca Seller Centre, XYZ Sniper MCP, source aplikasi, dan endpoint saldo. Tidak ada penarikan, perubahan rekening, perubahan jadwal, atau perubahan kode aplikasi. Baseline source: `ca5e5cb`, mencakup perubahan Finance, Boost dan Chat yang sudah masuk main.

## Bukti sumber

- Halaman yang dibuka di tab audit tersendiri: `https://seller.shopee.co.id/portal/finance/wallet/shopeepay`. UI menampilkan **Saldo**, **Saldo Aktif**, dan tombol **Tarik Dana**. Pada sampel browser, kedua nominal berbeda dan tombol penarikan disabled.
- XYZ Sniper MCP project 1 (`seller shopee`), endpoint **10103**, capture **10230/10237**, `last_seen=2026-09-30 00:39:05`: GET `/api/v4/seller/local_wallet/get_wallet_status`. Capture 10230 memakai `SPC_CDS`, `SPC_CDS_VER=2`, `wallet_provider=0`, `bank_account_id=0`; capture 10237 memakai konteks rekening yang dipilih. Cookie/token dan ID rekening tidak dicatat dalam dokumen.
- Respons memiliki envelope `error:0`, `error_msg`, `data`, bukan `code:0` seperti income. Nama dan nilai field saldo cocok dengan label UI. **Nominal sudah dalam rupiah**, tidak dibagi 100000 seperti data income.
- Probe GET server pada **30 September 2026, 00:43:49–00:43:56 WIB** berhasil HTTP 200 / `error:0` untuk **7/7 toko**. Cookie tiap toko dibaca dari konfigurasi tersimpan; respons `shop_info` diperiksa terhadap `shops.shop_id` sebelum membaca saldo. Probe menggunakan TLS verification dan tanpa redirect. Tidak menyimpan respons mentah atau mengubah tabel Finance.
- Pada satu toko ada saldo aktif positif sekaligus pembatasan penarikan dengan pesan verifikasi identitas. Enam toko lain tidak memiliki pembatasan pada flag yang diperiksa. Ini **belum membuktikan** bahwa semua rekening/tujuan dan nominal enam toko tersebut sudah memenuhi semua syarat penarikan.
- Capture **10235**, endpoint **10110** `/get_withdrawal_block_info`, menunjukkan `blocked_amount`, `kyc_status`, `kyc_withdrawal_threshold`, `total_escrow_amount`. Arti enum KYC tidak disimpulkan hanya dari angka.
- Capture **10231**, endpoint **10104** `/get_withdrawal_limits`, berisi batas frekuensi dan jumlah. Capture **10136**, endpoint **9884** `/get_withdrawal_options`, berisi opsi tujuan/default. Data rekening tidak dibutuhkan di frontend Shopdash.
- Capture **14068**, endpoint **10114** `/calculate_user_withdrawal_fee`, memperlihatkan kalkulasi biaya untuk nominal dan tujuan tertentu. Capture ini bukan transaksi penarikan. Audit tidak memanggil endpoint penarikan maupun menghitung ulang biaya untuk rekening lain.
- Header MCP berasal dari ringkasan endpoint terkini, bukan snapshot immutable per capture. Audit tidak menyalin signature atau mengklaim header historis tertentu sebagai syarat keberhasilan. Probe server berhasil tanpa replay signature browser.

## Kontrak angka

| Informasi | Field / sumber | Perlakuan |
| --- | --- | --- |
| Saldo Penjual | `wallet_available_balance` | Cocok dengan label Saldo; jangan otomatis disebut seluruhnya bisa ditarik. |
| Saldo Aktif | `wallet_active_balance` | Ambil nilai asli. Jangan menghitung ulang dari saldo lain. |
| Dana diblokir | `wallet_blocked_balance` | Rincian saldo, bukan Pending dari penghasilan pesanan. |
| Saldo secured | `wallet_secured_balance` | Teramati nol; hubungan bisnis ketika bukan nol belum diverifikasi. Jangan mengarang rumus. |
| Batas penarikan | `bank_min_amount_per_transaction`, `bank_remaining_daily_withdrawal_limit_amount`, `remaining_total_daily_manual_withdrawal_num` | Memengaruhi kelayakan, bukan tambahan saldo. Periksa konteks tujuan sebelum menghitung batas. |
| Pembatasan | `is_seller_withdrawal_maintenance_group_active` dan keterangannya | Pada sampel bernilai true bersamaan dengan tombol disabled. `is_wallet_frozen=false` sendiri tidak membuktikan boleh menarik dana. |
| Saldo ShopeePay | `shopeepay_available_balance` | Dompet berbeda; tidak masuk total Saldo Penjual. |
| Sudah dilepas | Endpoint income yang sudah digunakan | Arus dana selama periode. Tidak sama dengan saldo dompet yang tersisa sekarang. |

Pending, dana dilepas, dan Saldo Penjual tidak dijumlahkan menjadi satu total uang. Topup iklan bisa tercatat sebagai pengeluaran dompet; jangan dikurangkan kembali dari saldo dompet yang sudah diberikan Shopee.

## Rencana tampilan

Tambahkan kartu **Estimasi bisa ditarik** pada komponen bersama Dashboard/Keuangan. Keterangan ringkas: **Posisi terakhir · sebelum biaya penarikan**. Nilai ini baru boleh dihitung setelah pemeriksaan kelayakan di bawah terverifikasi. Rincian kartu/per toko menampilkan **Saldo Penjual**, **Saldo Aktif**, **Dana diblokir**, waktu pembaruan dan alasan pembatasan.

- Angka utama hanya menjumlahkan nominal toko yang terverifikasi memenuhi syarat. Toko dengan pembatasan jelas tidak menyumbang nominal dapat ditarik, tetapi saldo aktifnya tetap terlihat di rincian.
- Status yang belum bisa dipastikan tidak diubah menjadi nol atau dianggap bebas pembatasan. Total sebagian menyebut jumlah toko terhitung dan yang belum terverifikasi; bila belum ada yang dapat diverifikasi, tampilkan `Belum dapat dipastikan`.
- Saldo aktif semua toko tetap tersedia sebagai angka pembanding, sehingga pengguna dapat melihat uang yang ada tetapi belum dapat ditarik. Jika semua toko terverifikasi sedang dibatasi, estimasi dapat ditarik boleh Rp0 dengan alasan jelas; ini tidak berarti saldo aktifnya habis.
- Filter toko berlaku untuk seluruh angka. Filter tanggal hanya berlaku bagi laporan periode seperti Sudah dilepas/Omset/Iklan. Saldo dompet mengikuti posisi terakhir walaupun pengguna memilih bulan lampau. Keterangan tanggal pada ringkasan toko harus ikut diperbarui agar tidak menyebut semua angka selain Pending mengikuti periode.
- Desktop: tiga angka utama dapat berbagi baris jika lebar konten cukup. HP: susun vertikal, nominal dominan, satu keterangan status singkat, detail panjang dalam disclosure yang sudah ada. Verifikasi berdasarkan lebar konten setelah sidebar, bukan lebar layar saja.
- Gunakan ikon dompet dengan label teks, existing shop logos, tema Shopdash dan warna status yang sudah ada. Jangan menambah tombol untuk menjalankan penarikan dalam fitur ini.

Design direction tetap ENERGY 1 / RHYTHM 2 / MOTION 1. Antislop menjaga bahasa singkat, arti uang yang berbeda, dan metadata tidak membanjiri ringkasan. Pencarian UI UX Pro Max pertama tidak menemukan padanan; hasil pencarian lanjutan tidak menyediakan pola khusus saldo penjual. Rekomendasi layout di sini mengikuti brief pengguna dan pola aplikasi yang sudah ada, bukan klaim pola terverifikasi dari database skill. Tidak ada visual baru yang dinyatakan sudah lolos uji.

## Urutan implementasi

1. **Tuntaskan kontrak dapat ditarik.** Cocokkan opsi tujuan/default, status rekening dan batas nominal dengan UI Shopee secara read-only. Bedakan `eligible`, `blocked`, dan `unknown`. Saldo Aktif positif saja tidak cukup. Verifikasi arti enum sebelum membuat mapping. Untuk toko eligible, kandidat nominal adalah saldo aktif yang dibatasi sisa limit tujuan yang terverifikasi, dengan syarat batas minimum dan kuota terpenuhi. Jangan menampilkan hasil sebagai uang bersih masuk rekening; biaya penarikan masih dikecualikan.
2. **Adapter saldo terpisah.** Tambahkan helper khusus wallet yang memeriksa HTTP, `error:0`, tipe field, identitas cookie dan konteks tujuan. Jangan memakai parser income atau faktor 100000. Transport dengan TLS verification; tidak menyalin header anti-bot dari capture. Batasi field yang diambil, hindari penyimpanan rekening atau HTML mentah. Pesan pembatasan dijadikan teks biasa dan di-escape.
3. **Penyimpanan posisi terakhir.** Migrasi additive khusus wallet, berisi local shop ID, source shop ID, nominal asli, status/kelayakan, waktu sukses, waktu percobaan, dan error tersanitasi. Latest success tidak hilang ketika refresh gagal. Cookie yang berpindah identitas tidak boleh meminjam data toko sebelumnya. Nilai hilang berbeda dari nol.
4. **Integrasi worker Finance yang ada.** Refresh wallet independen dari penyelesaian seluruh halaman income, di bawah lock toko dan batas request yang sudah ada. Jalankan pemeriksaan due sebelum early return `hasWork()`; backlog atau kegagalan income tidak boleh menahan wallet tanpa batas. Kegagalan wallet juga tidak menggagalkan publikasi income yang valid. Tidak menambah runner paralel atau mengubah ekstensi/Boost/Chat.
5. **Jadwal dan refresh manual.** Saat audit, 7/7 jadwal Finance aktif dengan interval **600 detik**. Usulan awal mengikuti jadwal 10 menit ini dengan kontrol due agar tidak menarik saldo setiap halaman impor. Antrean dapat membuat pembaruan lebih lambat; tampilkan timestamp sebenarnya. Halaman tetap membaca database setiap 30 detik, bukan memanggil Shopee per pengunjung. Tombol Perbarui saldo Shopee harus tetap menjadwalkan wallet walau income tidak mempunyai pekerjaan baru, dengan deduplikasi dan CSRF yang sudah ada.
6. **API dan dua halaman.** Tambahkan kontrak wallet pada `Finance::summary` dan komponen bersama `app/views/panel/finance.php` / `public/assets/js/finance.js`. Hitung satu toko sekali, gunakan cakupan toko yang sama, pertahankan pilihan toko/tanggal serta disclosure/fokus saat polling. Perbarui help text yang saat ini mengatakan seluruh saldo berasal dari Penghasilan Saya, karena wallet punya sumber sendiri.
7. **Verifikasi lalu kirim.** Cek head main terbaru, kerja di worktree Finance, gabungkan perubahan lain tanpa mengganti source mereka. Deploy hanya setelah parser, klasifikasi, agregasi dan UI memenuhi kriteria berikut.

## Kriteria selesai

- Identitas satu/banyak toko, raw rupiah, pemisahan ShopeePay, nol terverifikasi, field hilang, respons gagal dan saldo lama diuji tanpa penarikan nyata.
- Saldo aktif positif + pembatasan tetap diklasifikasikan blocked; flag false tunggal tidak dianggap bukti eligible. Syarat bank/kuota/minimum/limit, enum tidak dikenal dan snapshot kedaluwarsa memiliki hasil eksplisit.
- Total parsial menyebut toko yang belum terverifikasi. Data stale tidak dipasarkan sebagai bisa ditarik sekarang. Mengubah periode tidak mengubah posisi dompet.
- Refresh wallet tetap berjalan saat antrean income panjang atau tidak ada import baru; failure tidak merusak data income. Manual refresh tidak menggandakan job.
- Dashboard dan Keuangan memberikan angka yang sama untuk filter sama. Per toko mempertahankan saldo aktif dan alasan pembatasan, termasuk ketika estimasi penarikan nol.
- Uji 320/500/999/1600px, lebar konten 684px, tema terang/gelap, nama panjang, nominal besar, zoom, keyboard dan pergantian filter. Semua endpoint bisnis mutasi dimock; tes tidak menarik dana.

Tahap audit sudah membuktikan akses saldo server untuk semua toko dan menjelaskan perbedaan field. Pemeriksaan semua syarat penarikan per tujuan, penyimpanan wallet, klasifikasi eligible, agregasi baru dan UI masih pekerjaan implementasi. Jangan menyajikan rencana ini sebagai fitur yang sudah live.
