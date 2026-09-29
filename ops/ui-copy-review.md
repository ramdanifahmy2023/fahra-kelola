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
