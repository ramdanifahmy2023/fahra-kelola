# Sellerio Get Cookies 2.1.0

Ekstensi mengikuti identitas proyek di `resources/css/input.css`, sidebar panel, dan `public/assets/images/favicon.svg`.

Pembacaan desain: popup utilitas untuk pengguna Sellerio, mengikuti gaya dashboard hangat dengan ENERGY 2 / RHYTHM 1 / MOTION 1.

## Keputusan desain

- Oranye `#e98425`, krem `#fffdf9` / `#f7f1e8`, dan cokelat `#1a1714` berasal dari tema proyek.
- Logo petir menggunakan geometri favicon proyek agar ikon toolbar dan panel memiliki identitas yang sama.
- Font sistem mengikuti stack sans-serif Tailwind proyek dan tersedia lokal di macOS/Windows; monospace hanya untuk nilai cookie.
- Satu kolom mengikuti urutan kerja: periksa domain, lihat cookie, salin. Tombol oranye menjadi fokus utama.
- Padding 18–24px mempertahankan ruang baca di popup 320–380px. Tidak ada kartu bertingkat atau dekorasi tambahan.
- Header gelap mengikuti sidebar; area kerja mengikuti tema sistem. Transisi hanya pada warna tombol, dengan dukungan reduced motion.
- Stylesheet ekstensi berada di `popup.css` agar paket mandiri dan tidak membutuhkan server atau hasil build CSS dashboard.

## Validasi

Jalankan `node tests/sellerio-extension-ui.cjs` dengan Playwright tersedia, atau tentukan lokasi modul melalui `PLAYWRIGHT_MODULE`.

Uji memakai profil Chromium terpisah, server loopback, dan cookie fixture. Tidak ada cookie toko nyata atau transaksi bisnis dalam pengujian. Screenshot berada di `tmp/sellerio-extension-ui/` dan tidak masuk Git.

- PASS: Manifest V3 dimuat sebagai ekstensi, nama/version terbaca, dan API cookies membaca cookie fixture lokal di macOS.
- PASS: salin, clipboard fallback, kegagalan clipboard, loading, kosong, kegagalan pembacaan cookie, tab tidak tersedia, dan halaman internal browser.
- PASS: keyboard Tab/Enter, fokus terlihat, ikon salin tetap ada setelah status berubah.
- PASS: light/dark pada lebar 320, 380, 500, 999, 1600px; domain panjang; pembesaran teks 200%; reduced motion.
- PASS: ekspor mempertahankan user-agent macOS dan Windows pada fixture, tanpa path lokal.
- PASS: 14 pasangan warna teks, kontras minimum 5.04:1; lima pasangan batas kontrol/fokus, minimum 3.40:1, dihitung memakai `antislop-human/contrast-check.py`.
- PASS: review screenshot kedua tema dan pembesaran teks; label tombol kembali ke “Salin cookie” setelah pembacaan ulang.

Target paket adalah Chrome/Edge terbaru pada macOS dan Windows. Runtime Windows dan Edge belum diuji langsung. Panduan pemasangan dan referensi kompatibilitas resmi ada di `Sellerio Get Cookies/README.md`.

## Antislop delivery gate

- PASS R-02: copy popup berbahasa Indonesia, tanpa em dash atau klaim pemasaran.
- PASS R-03/R-35: browser menjalankan popup; pemeriksaan overflow, kontrol, tema, dan pembesaran teks lulus.
- PASS R-17/R-18/R-23/R-36/R-38: tidak ada statistik, testimoni, klaim keamanan, atau data pengguna rekaan dalam produk; logo berasal dari proyek, fixture hanya di tes.
- PASS R-24/R-26: tombol salin bekerja; tautan brand lama dihapus; kontrol warisan tetap tersembunyi dan inert.
- PASS R-25: perhitungan kontras tercatat di atas, termasuk kedua tema dan feedback sukses/error.
- PASS R-27: loading, kosong, error, siap, dan tersalin memiliki pesan teks.
- PASS R-28: popup tidak memiliki FAQ.
- PASS R-32: Tab mencapai textarea dan tombol; Enter menjalankan aksi; fokus terlihat.
- PASS R-33: perubahan UI ditulis langsung di sumber dengan patch; raster ikon dirender dari SVG proyek.
- PASS R-34: media query tema sistem mengatur kedua tema dengan layout identik.
- PASS R-37: arah visual berasal dari identitas proyek dan dials tercatat sebelum review.
- PASS R-01/R-07/R-08/R-09/R-10/R-12/R-13/R-14/R-22: tidak ada gradient, grid dekoratif, panah CTA, pill, glass, glow, bayangan besar, kartu fitur, atau ilustrasi tambahan.
- PASS R-04/R-06: logo petir dipakai karena identitas proyek; ikon salin menunjukkan fungsi; alasan tipografi tercatat.
- PASS R-19: gerak hanya transisi hover warna tombol; reduced motion mematikannya.
- PASS liveliness: aksen oranye memberi fokus pada salin; header dan logo mengikuti identitas Sellerio; komposisi mengikuti tugas popup.
- PASS C-1/C-2/C-3/C-4/C-5 dan R-05/R-11/R-15/R-16/R-20/R-21/R-29/R-30/R-31: alasan desain tercatat, radius/spacing konsisten, satu aksi utama, konten spesifik, dan bukti uji mendukung cakupan yang dinyatakan.
