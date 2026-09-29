# Sellerio Get Cookies

Ekstensi untuk menyalin cookie dari tab aktif, lalu menempelkannya ke pengaturan toko di Sellerio.

## macOS dan Windows

Gunakan Google Chrome atau Microsoft Edge versi terbaru. Folder ekstensi yang sama dipakai di kedua sistem, tanpa Node.js, PHP, atau aplikasi pendamping. Tema mengikuti pengaturan terang/gelap sistem.

1. Jika memakai ZIP, ekstrak terlebih dahulu dan simpan folder **Sellerio Get Cookies** di lokasi tetap.
2. Buka `chrome://extensions` di Chrome atau `edge://extensions` di Edge.
3. Aktifkan **Developer mode / Mode developer**.
4. Klik **Load unpacked / Muat yang belum dipaketkan**, lalu pilih folder yang berisi `manifest.json`.
5. Sematkan Sellerio Get Cookies melalui menu ekstensi browser.

Jika ekstensi sudah terpasang dari folder ini, klik **Reload / Muat ulang** pada halaman ekstensi setelah memperbarui file.

## Menyalin cookie

1. Buka situs toko dan login.
2. Klik ikon Sellerio Get Cookies.
3. Pastikan domain tab yang ditampilkan sesuai, lalu klik **Salin cookie**.
4. Tempel di kolom cookie toko Sellerio dengan **Command+V** di macOS atau **Ctrl+V** di Windows.

Jika clipboard browser gagal, pilih teks pada kolom cookie dan salin dengan **Command+C** di macOS atau **Ctrl+C** di Windows. Halaman internal browser, seperti tab baru dan pengaturan, tidak menyediakan cookie situs.

## Cakupan kompatibilitas

Paket menggunakan Manifest V3 dan API ekstensi Chromium. Chrome/Edge pada macOS dan Windows merupakan target dukungan. Safari dan Firefox memerlukan paket terpisah.

Validasi otomatis dilakukan pada Chromium di macOS, termasuk membaca cookie uji lokal, clipboard beserta jalur cadangannya, kedua tema, keyboard, dan ekspor user-agent macOS/Windows. Runtime Windows dan Edge belum diuji langsung.

Referensi: [pemasangan lokal Chrome](https://developer.chrome.com/docs/extensions/get-started/tutorial/hello-world#load-unpacked) dan [kompatibilitas ekstensi Chrome dengan Edge](https://learn.microsoft.com/en-us/microsoft-edge/extensions/developer-guide/port-chrome-extension).
