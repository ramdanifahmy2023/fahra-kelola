# Naikkan Produk: pengulangan produk pilihan

Implementasi: 2026-09-29. Rancangan awal dan batas audit berada di [audit Boost](product-boost-automation-audit-20260929.md). Halaman tetap `/panel/boost`.

## Perilaku

- Pengguna menyimpan hingga lima produk tetap per toko, mencari dari seluruh katalog aktif dan mempertahankan pilihan lintas halaman. Simpan pertama tidak mengaktifkan pengulangan.
- Pengulangan memproses hanya produk pilihan yang tersedia. Stok kosong, nonaktif, deleted, cooldown dan hasil yang belum pasti tetap terlihat dengan alasan. Tidak ada pemilihan produk pengganti otomatis.
- Mode manual memakai editor katalog tersendiri dan konfirmasi sekali jalan. Pilihan manual tidak mengubah produk atau jadwal pengulangan.
- Simpan, aktifkan dan jeda memakai CSRF serta versi konfigurasi. Edit saat batch berjalan membatalkan pengiriman berikutnya dari versi lama. Jeda tidak dapat menarik request yang sudah dikirim.
- Pilihan tersimpan tetap ada ketika browser ditutup. Jadwal berasal dari database dan worker CLI, bukan timer browser. Polling UI hanya membaca status lokal.
- Kuota lokal menghitung lima hasil diterima dalam empat jam; jeda konservatif produk tetap 255 menit sesuai baseline. UI menyebut jeda tersebut sebagai aturan lokal dan kuota sebagai perkiraan. Belum ada bukti untuk mengurangi margin menjadi tepat 240 menit.
- Setiap POST didahului pengecekan identitas toko, status remote dan validasi lokal ulang. Status `success` berarti respons API diterima, bukan verifikasi independen bahwa posisi produk atau penjualan berubah.
- Timeout/malformed/HTTP gagal sesudah POST menjadi `unknown`, menahan pengiriman toko sampai hasil diperiksa. Pemeriksaan GET yang gagal sebelum POST menjadi `not_sent`, tanpa memakai kuota.
- Periksa status di Shopee hanya mengirim GET dan menjalankan pemulihan lokal yang eksplisit. Status remote saat ini tidak membuktikan hasil historis. Pengguna dapat mencatat hasil yang telah diperiksa di Seller Centre; tindakan ini memiliki catatan aktor dan tidak mengirim ulang otomatis.

## Komponen dan isolasi

| Bagian | Sumber |
| --- | --- |
| Aturan, respons, waktu due | `app/helpers/BoostPolicy.php` |
| Transport HTTPS Boost terpisah | `app/helpers/BoostTransport.php` |
| Executor manual/otomatis dan inspeksi | `app/helpers/BoostExecutor.php` |
| Profil, katalog, ledger, pengunci dan pemulihan | `app/models/BoostStore.php` |
| Endpoint panel | `app/controllers/back/ProcBoost.php` |
| Worker | `bin/boost-worker.php` |
| UI | `app/views/panel/boost.php`, `public/assets/js/boost.js`, blok `#boost-monitor` di `resources/css/input.css` |
| Migrasi additive | `database/migrations/20260930_boost_automation.sql` bersama migrasi manual existing |

Konfigurasi dan state Boost terpisah dari rating/AI, provider 9Router, finance dan queue sinkronisasi. `ProductBoostMonitor` menjadi wrapper pembacaan untuk endpoint lama; `/procProducts/boost` mengarah ke executor baru melalui kontrak JSON/CSRF. Klien lama yang masih mengirim FormData tanpa CSRF harus memuat aset UI terbaru.

Satu named lock database per toko berlaku untuk manual, otomatis, inspeksi dan konfirmasi hasil. Namanya berasal dari nama database dan local shop ID, bukan path checkout. Koneksi pengunci menggunakan PDO nonpersistent agar proses/request yang mati melepas lock, meskipun koneksi data aplikasi menggunakan pool persistent. Row lock/versi di transaksi menjaga konfigurasi dan reservasi. Run memiliki owner token dan lease dua menit yang diperbarui sebelum setiap item; koneksi pengunci tetap dipegang sepanjang request remote.

Intent `sending` ditulis sebelum POST. Pemulihan hanya dilakukan setelah memperoleh pengunci: item `reserved` menjadi `not_sent`; `sending` dan `pending` legacy menjadi `unknown`. Lease habis saja tidak memberi proses kedua izin mengirim saat proses pertama masih memegang kunci. Tidak ada jaminan exactly-once remote karena API tidak menyediakan kontrak idempotency yang sudah terbukti; request key lokal mencegah pengulangan run yang sama.

Riwayat menggunakan tabel `product_boost_runs/items` existing; konfigurasi, metadata request, event item dan heartbeat worker berada di empat tabel Boost baru. Migrasi tidak menghapus histori atau mereset kuota. Endpoint read tidak melakukan migrasi/recovery. Payload diagnostic dibatasi HTTP status, API code integer dan penanda transport; cookie, header autentikasi, dan HTML mentah tidak disimpan.

## Menyiapkan runtime

Perintah berikut untuk checkout yang memang akan melayani aplikasi. Jangan memasang worker dari worktree pengujian yang terhubung ke database lain. Perubahan Git sendiri tidak menerapkan migrasi atau memasang service.

```sh
php bin/boost-worker.php --migrate
php bin/boost-worker.php --dry-run --shop=1
```

`--migrate` membuat tabel additive; tidak mengaktifkan toko atau memanggil Shopee. `--dry-run` hanya membaca local profile/ledger dan menghitung preview, tanpa heartbeat, recovery, perubahan jadwal atau network. Ganti `1` dengan local shop ID yang benar. Tanpa flag eksekusi, CLI juga default dry-run.

Pengiriman memakai satu sakelar server: `BOOST_SEND_ENABLED=1` dalam `config/.env` atau environment proses. Default tidak aktif. Sakelar diperiksa ulang sebelum setiap POST dan berlaku juga untuk manual. Mengaktifkan sakelar tidak otomatis mengaktifkan profil toko; pengguna masih memilih produk dan menekan Aktifkan pengulangan di panel.

```sh
php bin/boost-worker.php --once --limit=5
./ops/install-boost-worker.sh --install
```

`--once` dapat mengirim Boost untuk profil aktif yang due bila sakelar server aktif. Installer khusus macOS memasang hanya `com.fahra.shopdash.boost`, interval pemeriksaan 60 detik; menolak menimpa definisi/service Boost yang sudah ada. Installer tidak menyentuh web, scheduler sinkronisasi, worker finance atau layanan AI. Jalankan instalasi sekali dari checkout runtime yang dipilih. Linux dapat menjalankan CLI melalui scheduler sistem dengan batas satu runner resmi; pengunci database tetap melindungi toko.

Worker memproses toko due berdasarkan waktu paling lama menunggu, maksimal lima pada service template. Data tidak eligible menunggu jadwal berikutnya, bukan dianggap terkirim. Gangguan sebelum kirim menggunakan backoff terbatas; gangguan preflight dalam run minimal sepuluh menit, dapat naik sampai satu jam, dan honor `Retry-After` tervalidasi hingga 24 jam. Tidak ada auto-retry POST unknown. Default trafik Boost terpisah belum merupakan anggaran request global untuk finance/AI/sync.

Status worker berasal dari heartbeat nyata. UI menampilkan pesan bila profil aktif tetapi heartbeat tidak terlihat dalam tiga menit. Log service: `/tmp/shopdash-boost.log` dan `/tmp/shopdash-boost.error.log`, berisi ID lokal/status atau pesan aplikasi yang tersanitasi.

Untuk berhenti mengirim, set `BOOST_SEND_ENABLED=0` dan jeda profil yang diperlukan. Environment proses mengungguli file config; hapus/ubah override bila digunakan. Request yang sudah dikirim tetap dicatat. Untuk melepas service gunakan `launchctl bootout "gui/$(id -u)/com.fahra.shopdash.boost"` setelah memeriksa run aktif. Pertahankan ledger dan event; jangan menghapus tabel untuk membuka kuota.

## UI dan verifikasi

UI UX Pro Max dan antislop diterapkan sejak rancangan sampai review. Arah: panel operasional Shopdash, ENERGY 1 / RHYTHM 1 / MOTION 1. Identitas toko, ringkasan status, produk tersimpan, tindakan, dan riwayat memiliki urutan tetap agar mudah dibandingkan. Aksen utama menandai aktivasi; pilihan, jeda, dan pemeriksaan menggunakan kontrol sekunder. Tidak ada chart, klaim peningkatan penjualan atau data toko fiktif di produk.

Editor katalog memakai dialog dengan isi yang dapat digulir dan footer tindakan tetap terlihat. Checkbox, thumbnail 48px dan judul memakai kolom terpisah. Pilihan draft tidak tertimpa polling atau pencarian; respons pencarian lama diabaikan. Konflik versi mempertahankan draft dan meminta pemuatan versi baru sebelum simpan. Menutup draft yang berubah memberi kesempatan membatalkan penutupan. Semua waktu UI menggunakan WIB secara eksplisit.

Perintah pengujian:

```sh
php tests/boost-automation.php
php tests/boost-lock.php
php tests/boost-products.php
BOOST_TEST_URL=http://127.0.0.1:8142 node tests/boost-ui.cjs
npm run build
git diff --check
```

Tes browser menerima `PLAYWRIGHT_MODULE` sesuai lokasi instalasi. Gunakan server pengujian tersendiri. Fixture backend memakai tabel temporary; tes lock menjalankan proses PHP kedua dan hanya menguji lock, tanpa menulis tabel bisnis. UI memock seluruh mutasi dan pemeriksaan Shopee. Permintaan HTTP langsung hanya menguji penolakan metode/CSRF/ID produk invalid, bukan pengiriman.

Bukti pada 2026-09-29:

- 61 pemeriksaan backend lolos: validasi pilihan, isolasi toko, optimistic version, subset tetap, cooldown 255 menit, deduplikasi request, GET gagal/Retry-After, identitas respons, hasil ambigu, recovery sending/reserved, konfirmasi hasil, pause/edit/stop server di tengah run, prioritas environment stop, dan preview tanpa penulisan.
- Tes proses terpisah membuktikan lock mencegah pengambilan toko bersamaan, dilepas setelah selesai, dan dilepas saat proses pemilik keluar tanpa unlock eksplisit.
- Tes daftar produk existing lolos. Tes UI sebelum perubahan juga dijalankan sebagai baseline; screenshot sebelum/sesudah disimpan lokal di `tmp/boost-ui-before` dan `tmp/boost-ui`.
- UI baru diuji pada 320/500/999/1600px dalam light/dark: draft, katalog/paginasi/search race, konflik simpan, aktif/jeda, pengiriman manual tiruan, unknown/konfirmasi, kegagalan read, CSRF, keyboard, kontrol minimal 44px, zoom 200%, dan perhitungan kontras teks editor minimal 4.5:1. Tidak ada pageerror.
- Review screenshot menemukan dan memperbaiki posisi thumbnail pada daftar tersimpan mobile; footer editor dibuat terpisah dari area scroll agar tindakan dapat dijangkau.
- Pemeriksaan GET langsung pada satu toko memverifikasi identitas sesi dan TLS, HTTP 200/code integer 0, respons `boost_infos` berupa map untuk lima ID; flags `show_boost_button` dan `disabled_boost_button` boolean. Lima produk tersebut parsed eligible. Bukti ini tidak mencakup kondisi slot penuh, akun lain, atau respons POST.
- Migrasi dan CLI diuji hanya pada database disposable khusus Boost. `--once` dengan sender nonaktif tidak mengirim request. Tidak ada migrasi/service Boost yang diterapkan ke runtime utama, profil produksi yang diaktifkan, atau POST Boost nyata selama implementasi.

Review antislop: PASS untuk hierarki/identitas (komponen Shopdash, aksen tindakan dan alasan komposisi di atas), status/fungsi (tes alur dan mutasi mocked), keterbacaan/keyboard/mobile/tema (assertion dan screenshot), serta kejujuran informasi (slot perkiraan, accepted versus unknown, tanpa klaim performa). Review ini mencakup halaman Boost yang berubah; tidak mengklaim seluruh aplikasi atau pengiriman live sudah tervalidasi.

## Batas sebelum pilot produksi

GET yang berhasil belum membuktikan jumlah slot remote secara menyeluruh atau bahwa respons POST yang ada di baseline masih sama. Implementasi menggunakan preflight per produk dan gagal tertutup jika flags tidak dikenali; slot eksternal yang berubah dapat menghasilkan penolakan saat POST. Pilot produksi masih diperlukan pada toko/produk yang ditentukan pengguna, untuk respons accepted/rejected, slot penuh, dan setidaknya satu pengulangan. Pengiriman otomatis tetap default off sampai operator menyiapkan runtime dan pengguna mengaktifkan profilnya.
