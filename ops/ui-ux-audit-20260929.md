# Audit teks UI Fahra Kelola

Tanggal: 29 September 2026. Cakupan: template panel dan JavaScript pembentuk teks, terutama Iklan, Dashboard, Laporan, Sinkronisasi, navigasi, serta pengantar halaman operasional. Audit memakai UI UX Pro Max dan antislop. Tidak ada perubahan aplikasi.

Audit ini berbasis kode. Browser tidak dapat dibuka karena komponen `codex app-server` tidak ditemukan. Ukuran aktual, kontras, posisi pada layar, interaksi keyboard, dan tampilan mobile belum diverifikasi. Dampak pada kecepatan pengguna merupakan penilaian heuristik, bukan hasil uji pengguna.

## Kesimpulan

Teks penjelas terlalu sering tampil permanen. Halaman menjelaskan cara kerja sistem, mengulang judul, dan menyampaikan status yang sama di beberapa tempat. Prioritas perbaikan adalah Iklan, Dashboard, lalu pola teks bersama di seluruh panel.

## Temuan dan tindakan

| Prioritas | Lokasi sumber | Bukti dan masalah | Rekomendasi |
| --- | --- | --- | --- |
| Tinggi | `shopdash/app/views/panel/ads.php:50` | Catatan internal “Draft tanpa arah desain · ENERGY 1 · RHYTHM 1 · MOTION 1” tampil dalam halaman transaksi iklan. | Pindahkan catatan proses desain ke dokumentasi. |
| Tinggi | `shopdash/public/assets/js/ads.js:73-90,116-120` | Setiap kartu mempunyai waktu pengambilan, badge, paragraf gabungan `notes.join(' ')`, status koneksi, metadata, dan jenis iklan aktif. Bahkan keadaan normal selalu mendapat paragraf penjelas. Beban membaca bertambah pada setiap toko. | Tampilkan nama toko, status singkat, waktu data, dan metrik. Sumber, rumus, serta metadata masuk ke “Detail data”. Tampilkan peringatan singkat hanya jika ada masalah. |
| Tinggi | `shopdash/app/views/panel/ads.php:33,41` | Penjelasan mekanisme muat ulang dan paragraf definisi metrik berada di alur utama. | Gunakan waktu pembaruan sebagai konteks utama. Taruh definisi pada tombol “Tentang metrik” yang dapat dibuka dengan klik dan keyboard. Perbedaan muat ulang vs sinkronisasi harus tetap jelas. |
| Tinggi | `shopdash/app/views/panel/ads.php:46,78,85`; `shopdash/public/assets/js/ads.js:152,162` | “Termasuk PPN” diulang pada pengantar, catatan periode, judul kolom, dan status sukses. | Pertahankan pada judul kolom total. Tampilkan rentang tanggal satu kali. Hapus pesan sukses permanen yang mengulang informasi tersebut. |
| Sedang | `shopdash/app/views/panel/index.php:34-36,215-305` | Pengantar, deskripsi kartu, penjelasan database/order detail, dan instruksi sinkronisasi memenuhi dashboard. | Utamakan angka, periode, perubahan, dan hal yang perlu ditangani. Hapus deskripsi yang hanya mengulang judul. Pertahankan batas stok dan peringatan data belum lengkap. |
| Sedang | `shopdash/app/views/panel/reports.php:9,36,41,45,82` | Keterangan metode perbandingan bergabung dengan penjelasan background; subjudul ranking dan grafik menjelaskan hal yang sudah terlihat. Empty state menyebut worker. | Pertahankan tanggal pembanding yang tepat. Hilangkan penjelasan ranking yang redundan. Ganti istilah worker dengan status yang dapat dipahami pengguna. |
| Sedang | `shopdash/app/views/panel/orders.php:25`; `products.php:31`; `shops.php:5`; `customers.php:10`; `chat.php:27` | Pengantar generik seperti “Kelola semua pesanan ... di satu tempat” tidak membantu memilih tindakan. | Hapus pengantar generik. Tetap tampilkan keterangan filter stok kritis karena menjelaskan batas pemilihan data. |
| Sedang | `shopdash/app/views/panel/templates/sidebar.php:14,43-44`; `navbar.php` | “Operations”, “Operations workspace”, “System online”, “Workspace siap digunakan” menambah teks berulang. Status online di sidebar bersifat statis. | Hilangkan dekorasi teks dan status statis. Gunakan status koneksi nyata hanya di konteks yang relevan. |
| Sedang | `shopdash/app/views/panel/sync.php:3-5,22` | “Background sync”, “database lokal”, dan error mentah merupakan bahasa implementasi. | Gunakan judul “Sinkronisasi”, status singkat, dan tindakan pemulihan. Sediakan detail teknis yang dapat dibuka. |
| Sedang | `shopdash/app/views/panel/boost.php:5,25` | Batas lima produk disebut di pengantar dan pemilihan. | Tampilkan dekat pilihan sebagai penghitung “0/5 dipilih”, diperbarui sesuai pilihan. Batas lima harus tetap terlihat. |

## Contoh penyederhanaan

| Saat ini | Usulan |
| --- | --- |
| Kelola semua pesanan dari seluruh toko cabang Anda di satu tempat. | Hapus; judul Pesanan dan filter Toko sudah cukup. |
| Ringkasan data lokal yang terakhir tersimpan dari channel penjualan. | Hapus pengantar; tampilkan waktu data yang benar pada bagian terkait. |
| Laporan terakhir berhasil diambil [waktu] | Diperbarui [waktu] |
| Data tersimpan · belum diperbarui | Belum diperbarui |
| Sesi toko habis. Perbarui cookie melalui halaman Toko. | Sesi habis. [Perbarui koneksi] |
| Belum ada data. Worker akan mengisi setelah sinkronisasi performa berjalan. | Data belum tersedia. [Lihat sinkronisasi] |
| Pilih maksimal 5 produk. | 0/5 dipilih |

Teks dalam tanda kurung siku adalah usulan tautan atau nilai dinamis, bukan data yang sudah tersedia. Label baru hanya boleh dipakai jika sesuai perilaku sebenarnya. Periode laporan, waktu pembaruan, dan waktu koneksi adalah informasi berbeda; jangan digabung menjadi satu waktu yang menyesatkan.

## Informasi yang tetap harus terlihat

- Periode dan toko yang sedang dipilih.
- Status data belum lengkap, kedaluwarsa, atau belum tersedia. Tanda kosong tidak boleh menjadi angka nol.
- Ketidakcocokan ringkasan dengan rincian tanggal, bila terjadi.
- Peringatan sesi habis beserta tindakan pemulihan.
- Batas pilihan produk, batas stok kritis, label input, serta pesan kesalahan formulir.
- Konfirmasi penghapusan dan konsekuensi tindakan.

## Aturan penyederhanaan

1. Pengantar halaman bersifat opsional. Jika judul dan kontrol sudah menjelaskan tugas, pengantar dihapus.
2. Setiap keterangan harus membantu keputusan, mencegah kesalahan, atau menjelaskan status yang belum terlihat.
3. Status normal cukup ringkas. Masalah ditampilkan dengan penyebab yang dipahami dan tindakan berikutnya.
4. Detail teknis dan definisi tersedia melalui disclosure yang dapat dibuka dengan klik, sentuhan, dan keyboard. Jangan mengandalkan hover.
5. Jangan mengurangi ukuran font atau kontras untuk menyamarkan kepadatan. Kode saat ini banyak memakai teks 9–11 px dan opacity rendah; kontras aktual perlu pengukuran pada kedua tema.
6. Gunakan istilah konsisten: Pesanan, Toko, Sinkronisasi, Diperbarui. Hindari mencampur order, workspace, channel, dan background tanpa kebutuhan.

## Temuan tambahan

Menu Sistem memakai `javascript:void(0)` di sidebar. Profil dan Pengaturan pada menu akun tidak memiliki tujuan tautan dalam template. Perlu verifikasi handler sebelum menyatakan menu berfungsi; bila belum tersedia, sembunyikan dari navigasi utama atau beri status yang jelas.

## Urutan pelaksanaan dan verifikasi

1. Hapus catatan internal, pengantar generik, dan status statis.
2. Susun ulang kartu Iklan serta bagian Topup, termasuk teks dinamis di `ads.js`.
3. Ringkas Dashboard dan Laporan sambil mempertahankan arti metrik dan periode.
4. Terapkan istilah dan pola bantuan yang konsisten pada halaman lain.
5. Bandingkan jumlah kata yang terlihat pada kondisi dan jumlah toko yang sama. Uji kondisi normal, data lama, sesi habis, data kosong, dan gagal memuat. Pastikan bantuan dapat dibuka pada mobile dan keyboard.

Pencarian lokal UI UX Pro Max untuk progressive disclosure tidak menghasilkan kecocokan yang relevan setelah satu pengulangan. Rekomendasi pengungkapan detail memakai panduan umum Forms & Feedback dari skill serta penilaian langsung atas kode, bukan hasil pencarian tersebut.
