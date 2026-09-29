# Penyederhanaan teks panel

29 September 2026

Audit awal: [Audit teks UI](ui-ux-audit-20260929.md).

Arahan pengguna: kurangi teks keterangan agar halaman operasional lebih cepat dipindai. Pertahankan tampilan yang ada, angka, filter, label input, periode, serta peringatan yang memengaruhi keputusan pengguna. UI UX Pro Max dipakai sebagai panduan UX dan antislop sebagai filter teks berulang.

Perubahan:

- Hapus pengantar generik di halaman operasional, label dekoratif, status online statis, dan catatan internal desain.
- Pindahkan definisi metrik dan mekanisme pembaruan ke “Tentang data iklan”. Sumber, kesalahan teknis, dan metadata per toko tersedia di “Detail data”.
- Pertahankan peringatan sesi habis, data lama, laporan belum tersedia, dan perbedaan ringkasan dengan rincian tanggal. Tautan pemulihan mengarah ke halaman Toko atau Sinkronisasi.
- Pertahankan perbedaan nol dengan metrik yang belum tersedia. Waktu laporan dan waktu koneksi tetap terpisah.
- Tampilkan keterangan PPN pada kolom topup. Status sukses tidak lagi menjadi paragraf permanen; status kosong, sedang sinkron, dan gagal tetap terlihat.
- Ringkas Dashboard dan Laporan. Hapus menu Sistem, Profil, dan Pengaturan yang belum memiliki tujuan dalam template.

Verifikasi: lint PHP pada template yang berubah, pemeriksaan sintaks JavaScript termasuk script inline, build Tailwind, serta pemeriksaan render JavaScript dengan DOM tiruan untuk status normal, lama, sesi habis, tidak tersedia, perbedaan angka, nilai nol, dan persistensi disclosure. Pemeriksaan dengan DOM tiruan tidak memverifikasi layout atau interaksi browser.

Pemeriksaan lanjutan memakai Playwright lokal setelah browser tool pada sesi audit gagal dibuka. Halaman Iklan diuji pada lebar 320, 390, 768, dan 1440 px dalam tema terang dan gelap; screenshot mobile terang dan desktop gelap juga diperiksa. Disclosure menggunakan elemen native `details` dan `summary`.

Perbaikan lanjutan:

- Status laporan yang dilewati adalah “Belum diambil”, terpisah dari kegagalan pengambilan.
- Penghitung pilihan produk menampilkan jumlah aktual, menolak pilihan keenam tanpa membatalkan produk lain, dan kembali ke nol setelah pencarian baru. Peringatan koneksi dan kuota mempunyai tempat tersendiri sehingga tidak tertimpa penghitung.
- Hapus pesan sukses permanen pada Naikkan Produk dan Laporan. Ringkas istilah pesanan pada Dashboard.

Hasil pemeriksaan pada lingkup perubahan (bukan sertifikasi aksesibilitas seluruh aplikasi):

- PASS, teks dan hierarki: pengantar berulang dan status sukses permanen dihapus; periode, metrik, label filter, dan peringatan tetap tersedia. Arah visual mengikuti panel operasional yang ada, ENERGY 1 / RHYTHM 1 / MOTION 1.
- PASS, keyboard: tes membuka rincian harian dan Detail data dengan Enter, memeriksa fokus tabel, serta memastikan kedua detail tetap terbuka setelah muat ulang.
- PASS, status: tes mencakup data lama, sesi habis, laporan gagal, metrik belum terverifikasi, nol, data kosong, dan kegagalan jaringan.
- PASS, responsif: tes memastikan tidak ada overflow halaman Iklan pada empat lebar di kedua tema; Naikkan Produk diperiksa pada empat lebar yang sama.
- PASS, implementasi: build Tailwind, lint PHP template yang diubah, pemeriksaan sintaks JavaScript, 89 pemeriksaan browser Iklan, 135 pemeriksaan performa iklan, dan 36 pemeriksaan laporan campaign lulus. `tests/boost-ui.cjs` memeriksa batas pilihan, reset, peringatan sesi, dan overflow dengan respons produk uji tanpa mengirim tindakan boost.

Kontras seluruh panel, seluruh interaksi halaman lain, dan pengujian perangkat fisik belum diaudit ulang. Panduan disclosure berasal dari bagian Forms & Feedback skill; pencarian khusus disclosure tidak menghasilkan kecocokan yang relevan.

## Pilihan toko pada Produk

Temuan: `selectShop()` mengganti label toko, ID tersembunyi, dan URL dengan `history.replaceState()`, tetapi hanya mengambil status sinkronisasi. Tabel dan pagination masih berasal dari render toko sebelumnya. Akibatnya label toko dan produk tidak cocok sampai pengguna memuat ulang halaman secara manual.

Perbaikan: pilihan toko menjadi tautan ke `/panel/products` dengan ID toko tujuan. Navigasi otomatis memuat render server untuk seluruh halaman, termasuk tabel, jumlah produk, pagination, dan target sinkronisasi. Batas jumlah produk serta filter stok kritis dipertahankan; halaman pagination dan sorotan produk lama direset. Penggantian HTML label secara manual dihapus. Ini menggunakan navigasi halaman, bukan pembaruan tabel melalui AJAX.

PASS: `tests/products-ui.cjs` memilih toko lain melalui UI, mencocokkan ID baris dengan hasil server toko tujuan, memeriksa reset pagination dan pelestarian filter, menguji Back serta aktivasi tautan dengan Enter. Lint PHP dan build Tailwind lulus. Pemeriksaan dilakukan pada aplikasi lokal; belum memverifikasi deployment domain produksi.

## Naikkan produk: sepuluh terlaris dan pilihan rekomendasi

Semua toko tetap terlihat. Daftar produk awalnya tertutup; beberapa toko dapat dibuka bersamaan dan menutup daftar tidak menghapus pilihan. Ringkasan menampilkan koneksi, jumlah produk yang ditampilkan, sisa kuota, dan waktu kuota kembali bila habis. Riwayat berada di dalam daftar yang dibuka.

Daftar mengambil sepuluh produk aktif dengan `sold_count` tertinggi per toko; produk yang dihapus dikecualikan. Angka terjual tidak diberi klaim periode karena sumber tidak menyediakan periode. Produk dalam masa tunggu tetap berada pada peringkatnya. Pencarian dan tombol tambah produk dihapus agar daftar tetap terbatas pada sepuluh terlaris.

“Pilih rekomendasi” memilih produk tersedia dalam urutan tersebut sampai sisa kuota terpenuhi. Stok kosong, larangan boost, `show_boost_button=false`, dan masa tunggu menghalangi pemilihan. Sesi bermasalah, kuota habis, proses berjalan, serta kegagalan memuat status menonaktifkan tindakan. Pengguna dapat menghapus atau mengganti pilihan sebelum mengirim. Pemeriksaan Shopee di server tetap menjadi penentu kelayakan saat eksekusi.

Setelah eksekusi, ringkasan dan riwayat dimuat ulang, pilihan dikosongkan, dan hasil berhasil/gagal/belum pasti ditampilkan. JavaScript halaman dipisahkan ke `public/assets/js/boost.js`; CSS sumber berada di `resources/css/input.css`. Screenshot pengujian hanya berada di `tmp/boost-ui/`.

Verifikasi lingkup perubahan:

- PASS, interaksi: `tests/boost-ui.cjs` memeriksa daftar tertutup, buka lewat keyboard, beberapa toko terbuka, persistensi pilihan, rekomendasi, penolakan pilihan berlebih, sisa kuota dua, hasil parsial, penolakan server, sesi habis, proses berjalan, gagal memuat, retry, dan produk kosong. Permintaan boost menggunakan respons uji; tidak menaikkan produk sungguhan.
- PASS, layout: tidak ada overflow halaman pada 320, 390, 768, dan 1440 px di kedua tema; screenshot mobile terang dan desktop gelap diperiksa. Native disclosure, fokus terlihat, nama produk membungkus, dan tombol minimal 44 px.
- PASS, teks: alasan rekomendasi memakai data yang tersedia, angka/metrik contoh hanya berada dalam tes. Kontras teks utama terhadap kartu 17,57:1 (terang) dan 15,60:1 (gelap). Gaya panel dipertahankan, ENERGY 1 / RHYTHM 1 / MOTION 1; aksen utama pada tindakan naikkan produk.
- Pemeriksaan model tersedia melalui `php tests/boost-products.php` menggunakan tabel sementara: urutan sepuluh terlaris, pemisahan toko, pengecualian produk tidak aktif/dihapus, jumlah produk, dan toko kosong.

Pemeriksaan dilakukan secara lokal. Deployment produksi dan keberhasilan permintaan boost nyata belum diverifikasi.

Penyesuaian tata letak: tombol “Naikkan N produk” dipindahkan tepat di samping “Pilih rekomendasi”, sebelum daftar produk. Keduanya memakai dua kolom dengan jarak 8 px dan tinggi minimal 44 px; label boleh membungkus. Penghitung, hapus pilihan, dan muat ulang status berada di baris berikutnya. Hasil tindakan muncul dekat tombol agar tidak perlu menggulir ke akhir daftar. Padding konten lebih kecil pada mobile.

PASS: tes browser memeriksa posisi kedua tombol dalam satu baris, urutan sebelum daftar, jarak antartombol, tinggi sentuh, label dinamis tanpa pemotongan, dan tanpa overflow halaman pada 320, 375, 390, 768, 1408, serta 1440 px dalam kedua tema. Screenshot 320 px terang dan 1408 px gelap diperiksa. Build Tailwind dan lint PHP lulus.
