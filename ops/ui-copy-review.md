# Penyederhanaan teks panel

29 September 2026

Arahan pengguna: kurangi teks keterangan agar halaman operasional lebih cepat dipindai. Pertahankan tampilan yang ada, angka, filter, label input, periode, serta peringatan yang memengaruhi keputusan pengguna. UI UX Pro Max dipakai sebagai panduan UX dan antislop sebagai filter teks berulang.

Perubahan:

- Hapus pengantar generik di halaman operasional, label dekoratif, status online statis, dan catatan internal desain.
- Pindahkan definisi metrik dan mekanisme pembaruan ke “Tentang data iklan”. Sumber, kesalahan teknis, dan metadata per toko tersedia di “Detail data”.
- Pertahankan peringatan sesi habis, data lama, laporan belum tersedia, dan perbedaan ringkasan dengan rincian tanggal. Tautan pemulihan mengarah ke halaman Toko atau Sinkronisasi.
- Pertahankan perbedaan nol dengan metrik yang belum tersedia. Waktu laporan dan waktu koneksi tetap terpisah.
- Tampilkan keterangan PPN pada kolom topup. Status sukses tidak lagi menjadi paragraf permanen; status kosong, sedang sinkron, dan gagal tetap terlihat.
- Ringkas Dashboard dan Laporan. Hapus menu Sistem, Profil, dan Pengaturan yang belum memiliki tujuan dalam template.

Verifikasi: lint PHP pada template yang berubah, pemeriksaan sintaks JavaScript termasuk script inline, build Tailwind, serta pemeriksaan render JavaScript dengan DOM tiruan untuk status normal, lama, sesi habis, tidak tersedia, perbedaan angka, nilai nol, dan persistensi disclosure. Pemeriksaan dengan DOM tiruan tidak memverifikasi layout atau interaksi browser.

Audit visual desktop/mobile dan keyboard belum terverifikasi karena browser tool gagal membuka tab (`codex app-server` tidak ditemukan pada sesi audit). Disclosure menggunakan elemen native `details` dan `summary`.
