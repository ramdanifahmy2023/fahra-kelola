# Naikkan Produk: visual operasional

Implementasi dan review: 2026-09-30. Brief pengguna: halaman lebih modern dan mudah dikenali melalui warna, ikon, logo serta gambar produk. UI UX Pro Max, antislop, antislop-ui, antislop-human, antislop-layoutmobile dan antislop-copywriting diterapkan. Bahasa visual mengikuti Shopdash, dengan ENERGY 2 / RHYTHM 2 / MOTION 1.

## Keputusan visual dan tujuannya

- Ikon pengulangan pada judul dan aksen oranye menghubungkan halaman dengan identitas Shopdash. Ikon outline SVG memiliki ukuran/gaya konsisten, disertai label, dan `aria-hidden="true"` agar pembaca layar tidak mengulang nama tombol. Tidak ada ketergantungan ikon pada unduhan font tambahan.
- Ringkasan seluruh toko menampilkan jumlah profil aktif, produk pilihan tersimpan dan toko yang perlu diperiksa, dari respons status lokal. Angka baru ditampilkan setelah semua toko dimuat; hasil baca gagal memberi pesan bahwa status belum diperbarui. Ringkasan tidak menambah endpoint atau request Shopee.
- Status aktif memakai hijau dan ikon pengulangan; jeda memakai warna netral dan ikon pause; memproses memakai oranye dan ikon refresh; unknown atau profil aktif dengan server/worker yang belum tersedia memakai kuning dan ikon peringatan. Label tetap terlihat supaya warna bukan satu-satunya petunjuk.
- Logo toko tetap berasal dari pemetaan local shop ID melalui komponen bersama. Logo yang sama juga tampil pada editor pilihan. Logo gagal/tidak tersedia menggunakan fallback toko, bukan logo toko lain.
- Produk tersimpan mendapat thumbnail 48px, nama, serta label kesiapan lokal dengan ikon. **Siap diperiksa** tidak menyatakan Boost remote pasti tersedia. Timestamp cooldown tetap berasal dari respons backend. Thumbnail yang gagal mempertahankan ruang 48px dan fallback paket.
- Ringkasan per toko mengelompokkan pilihan, waktu berikutnya dan perkiraan slot dalam bidang tersendiri. Lima segmen membantu mengenali jumlah produk/slot; angka dan label tetap menjadi penjelasan utama. Segmen merupakan data lokal, bukan persentase penjualan atau grafik performa.
- Tombol edit, aktif/jeda, sekali kirim, muat ulang dan pemeriksaan riwayat memiliki ikon sesuai aksinya. Rekomendasi terlaris mendapat ikon tren dan bidang berlatar tema agar berbeda dari draft yang sudah dipilih. Draft pilihan kini menampilkan thumbnail, nama, jumlah terjual dan tombol hapus dalam kolom terpisah.
- Bantuan umum berada dalam disclosure **Cara kerja pengulangan**; pesan operasional, koneksi gagal, konflik dan unknown tetap terlihat. Semua tindakan, aktivasi, request key, aturan cooldown dan pilihan tersimpan memakai alur yang sudah ada.

## Sumber

`app/views/panel/boost.php`, `app/views/panel/templates/boost-icons.php`, `public/assets/js/boost.js`, dan aturan `#boost-monitor` pada `resources/css/input.css`. Aset CSS hasil build dilacak, dan URL aset tetap memakai versi filemtime. Tidak ada perubahan pada worker, konfigurasi runtime, ledger atau transport.

## Bukti review

- `php -l` untuk view/ikon dan `node --check` untuk JavaScript lolos; `npm run build` serta `git diff --check` lolos.
- `tests/boost-ui.cjs` lolos dengan seluruh mutasi Boost mocked: rekomendasi/undo/simpan, pencarian dan pagination, konflik versi, perlindungan draft, aktif/jeda, sekali kirim, rekonsiliasi, pengiriman nonaktif dan penjagaan endpoint.
- Tambahan assertion: status dan ikon aktif/jeda/peringatan/memproses, worker tidak tersedia, jumlah ringkasan, identitas logo kartu/popup, kolom gambar/teks/tombol pilihan, kondisi tanpa toko, dan ikon dekoratif tersembunyi dari teknologi bantu.
- Screenshot serta assertion pada 320/500/999/1600px, masing-masing tema terang dan gelap: tidak ada overflow horizontal, thumbnail tetap 48px, kontrol minimal 44px, footer simpan tetap terjangkau, fokus keyboard terlihat, serta zoom 200%. Nama panjang dan gambar produk gagal termasuk dalam fixture.
- Kontras teks yang diperiksa di halaman/popup minimal 4.5:1. Pemeriksa antislop-human menghitung pasangan success light/dark 6.95/9.99:1 dan warning light/dark 8.31/10.75:1. Pemeriksaan browser menunggu transisi warna kontrol selesai sebelum mengukur.
- Screenshot lokal berada di `tmp/boost-ui/` dan memakai data uji. Review memperbaiki status produk yang semula melebar penuh serta susunan identitas popup pada layar sempit.

Review antislop: PASS. Warna membedakan kondisi, ikon menandai tindakan, gambar/logo membantu identitas, dan bidang/metrik mengikuti data yang digunakan operator. Tidak ada klaim peningkatan penjualan, dekorasi statistik atau aktivasi tersembunyi. Hasil ini mencakup halaman Boost yang berubah; operasi worker tetap mengikuti [panduan Boost](boost-automation.md).
