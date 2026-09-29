# Sellerio Get Cookies 2.1.0

## Halaman Ekstensi dan arsip rilis

Ditambahkan 2026-09-29. Menu **Manajemen > Ekstensi** membuka `/panel/extensions`. Halaman memuat unduhan terbaru, petunjuk pemasangan Chrome/Edge di komputer, panduan ganti versi, serta riwayat lengkap dengan nama rilis, tanggal, judul setiap perubahan, dan unduhan per versi. Pemasangan ekstensi tetap dilakukan di komputer; halaman dan unduhan dapat diakses dari ponsel.

Arsip web dimulai dari **2.1.0 · Tampilan Sellerio & salin cookie**. Tidak ada versi lebih lama yang direka atau diterbitkan sebagai rilis Sellerio.

- `resources/extensions/releases/<version>.json`: catatan rilis, ukuran ZIP, dan SHA-256.
- `public/downloads/extensions/sellerio-get-cookies-<version>.zip`: paket permanen untuk versi tersebut. ZIP hanya memuat daftar file ekstensi yang diizinkan, tanpa `.DS_Store`, AppleDouble, konfigurasi, atau file kerja lain.
- `app/models/ExtensionRelease.php`: membaca semua catatan, mengurutkan versi dengan `version_compare`, dan memeriksa integritas ZIP sebelum menampilkan tautan unduh. Paket rusak/hilang tetap memiliki entri riwayat dengan pesan yang jelas.
- `bin/release-extension.php`: publikasi dengan lock, validasi versi manifest, nama rilis dan judul/deskripsi perubahan wajib, serta penolakan versi yang sudah ada.

**Kontrak backup:** jangan menghapus, mengganti, mengedit, atau mengemas ulang ZIP maupun metadata rilis yang sudah dipublikasikan. Perbaikan harus menjadi rilis baru. Saat memindahkan/deploy aplikasi, sertakan seluruh direktori arsip beserta seluruh metadata; jangan hanya membawa versi terbaru. Git melacak keduanya.

### Menerbitkan pembaruan berikutnya

1. Edit sumber dalam `Sellerio Get Cookies/`, naikkan versi manifest, dan jalankan pengujian ekstensi yang relevan.
2. Buat catatan baru di file sementara, misalnya `tmp/release-notes.json`:

```json
{
  "version": "2.1.1",
  "name": "Nama rilis sesuai perubahan sebenarnya",
  "date": "2026-09-30",
  "changes": [
    {"title": "Nama perubahan", "description": "Penjelasan perubahan yang benar-benar dibuat."}
  ]
}
```

Contoh tersebut adalah format, bukan rilis yang sudah diterbitkan. Isi tanggal, versi, dan uraian sesuai pembaruan nyata.

3. Jalankan `php bin/release-extension.php --notes=tmp/release-notes.json`. Nama dan versi manifest harus cocok. Tidak ada opsi overwrite atau penghapusan rilis lama.
4. Jalankan `php tests/extension-releases.php` dan `node tests/extensions-ui.cjs` dengan Playwright yang tersedia. Pastikan ZIP lama masih bisa diunduh.
5. Commit sumber terbaru, **file JSON baru**, **ZIP baru**, dan dokumentasi yang berubah. Pertahankan seluruh berkas rilis lama; push sesuai aturan proyek.

### Desain dan verifikasi halaman

Design read: halaman unduhan dan riwayat untuk pengguna dashboard, identitas hangat Sellerio, ENERGY 2 / RHYTHM 1 / MOTION 1. Riset UI UX Pro Max mengarahkan riwayat berurutan dengan nomor versi, bukan pola landing newsletter yang sempat muncul dari query umum. Stack menggunakan PHP/Tailwind yang sudah ada.

- Warna, font, dan radius mengikuti token dashboard; oranye menandai unduhan utama, daftar rilis menggunakan permukaan netral.
- Dua kolom memisahkan panduan dan riwayat di desktop; satu kolom dan tombol penuh di ponsel. Tautan bagian membantu mencapai riwayat tanpa menggulir seluruh panduan.
- Changelog memakai `details` native agar dapat dibuka dengan keyboard dan tetap bekerja tanpa JavaScript. Data dirender di server, sehingga tidak memerlukan spinner pemuatan buatan.
- Navbar bersama diperbaiki agar nama aplikasi tidak bertumpuk dengan kontrol pada layar sempit/pembesaran teks; tombol menu mendukung Enter/Space dan Escape.
- PASS R-03/R-26/R-27/R-32/R-34/R-35/C-4: tes browser menjalankan route, menu, unduhan terbaru/riwayat, checksum, disclosure, fokus, kedua tema pada 320/500/999/1600px, teks 200%, dan fixture kosong/error/paket hilang/multi-versi.
- PASS R-17/R-18/R-23/R-36/R-38/C-5: metadata awal berasal dari versi 2.1.0 yang nyata; versi tambahan hanya ada di direktori/HTML fixture pengujian.
- PASS R-01/R-04/R-06/R-19/R-31/R-37: keputusan visual mengikuti identitas proyek dan alasan di atas; tidak ada dekorasi tambahan atau gerak berulang.
- Bukti browser: `tmp/extensions-ui/`; pengujian arsip: `tests/extension-releases.php`. Test tidak menggunakan cookie toko nyata atau menjalankan transaksi bisnis.
- PASS R-25: pasangan teks muted light 5.04:1, muted dark 8.91:1, serta teks tombol oranye 6.62:1, dihitung dengan checker antislop-human. Brand navbar mobile memakai warna teks utama agar terbaca.
- Verifikasi 2026-09-29: build Tailwind, lint PHP, tes arsip, tes halaman Ekstensi, dan regresi `tests/products-ui.cjs` lulus. ZIP publik di `https://shopee.fahra.my.id/downloads/extensions/sellerio-get-cookies-2.1.0.zip` memberikan HTTP 200 dan SHA-256 identik; `/panel/extensions` mengarahkan pengunjung tanpa sesi ke login.

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
