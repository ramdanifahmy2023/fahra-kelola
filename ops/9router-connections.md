# Koneksi 9Router: implementasi dan operasi

Tanggal: 2026-09-29. CRUD koneksi tersedia di **AI Agent → Automation → Kelola koneksi**. Pekerjaan dilakukan di worktree `feature/9router-connections`, terpisah dari sesi HPP/finance. Tidak ada file HPP, sinkronisasi keuangan, atau sidebar yang diubah oleh fitur ini.

## Perilaku tersedia

- Tambah, lihat, ubah, hapus koneksi: nama, base URL, API key, dan model/combo default. Simpan tidak memanggil provider.
- Satu koneksi dapat dipilih beberapa toko. `automation_profiles.connection_id` menyimpan relasi; `config.model` adalah override toko. Nilai kosong mengikuti default koneksi. Simpan konfigurasi toko tetap diperlukan.
- Model/combo menerima input manual, maksimum 255 karakter. Muat model mengambil katalog dan membuka picker di form edit. Pilihan tidak otomatis disimpan. Metadata `owned_by=combo` menandai combo; aplikasi tidak mengubah susunan fallback di 9Router.
- Saat edit, key kosong berarti mempertahankan. Perubahan base URL mewajibkan key dimasukkan ulang. Key tersimpan tidak dikembalikan ke browser. Tombol Tampilkan hanya membuka nilai yang sedang diketik.
- Edit stale menghasilkan 409. Menghapus koneksi yang dipakai toko ditolak dengan nama toko terkait. Setelah referensi dilepas, hapus mengosongkan secret dan memberi `deleted_at`; audit metadata dipertahankan.
- Katalog dan generasi mempunyai hasil terpisah dan terikat versi. Respons katalog sukses tidak mengesahkan API key/model. Mengubah koneksi menandai hasil sebelumnya usang.
- Uji model memakai default koneksi yang tersimpan dan satu prompt sintetis, tanpa data pembeli. Pengguna melihat konfirmasi penggunaan kuota. Tidak ada retry generasi otomatis. Hasil menyatakan keberhasilan menghasilkan jawaban, bukan kesiapan automasi rating.
- Koneksi dikelola bersama oleh akun panel yang sudah login, sesuai model akses panel saat ini. Belum ada RBAC atau isolasi tenant.

CRUD ini tidak mengaktifkan worker, tidak mengambil rating, dan tidak mengirim balasan ke Shopee. Pemeriksaan aturan rating tetap lokal. Instalasi 9Router pengguna belum diuji oleh pengembang; pengguna memasukkan konfigurasi nyata melalui form dan menjalankan Uji model.

## Kunci enkripsi

API key dienkripsi dengan Sodium XChaCha20-Poly1305. Nonce baru setiap tulis; AAD mengikat ID koneksi dan versi format. Database menyimpan ciphertext, nonce, serta key ID. Master keys berada pada file server di luar public document root dan Git.

Jalankan sekali dari checkout yang melayani aplikasi:

```sh
php bin/ai-connection-key.php --init
```

Default file adalah `storage/ai-connection-keys.json`, diabaikan Git. File dibuat dengan izin 0600; direktori baru 0700. `--init` menolak menimpa file yang sudah ada. Web tidak membuat master key otomatis. Jika key file hilang/rusak, form menampilkan kondisi server dan simpan secret gagal tertutup.

`AI_CONNECTION_KEY_FILE` dapat menunjuk absolute path di luar checkout. Nilai kosong memakai default. Untuk deploy ke checkout baru yang memakai database sama, gunakan keyring yang sama; jangan membuat keyring berbeda jika ciphertext nyata sudah ada. Backup keyring secara terpisah dan aman bersama prosedur backup database. Kehilangan keyring berarti credential perlu diisi ulang.

Rotasi:

```sh
php bin/ai-connection-key.php --rotate
```

Rotasi atomik membuat active key baru dan mempertahankan semua key lama untuk membaca data lama. Edit koneksi mengenkripsi ulang dengan active key, termasuk ketika API key dikosongkan untuk mempertahankan nilainya. Rotasi ini bukan re-enkripsi massal; jangan menghapus key lama sampai semua ciphertext dan backup terkait sudah ditangani. Command tidak mencetak key. PHP web dan CLI perlu akses baca ke keyring dan ekstensi `sodium`; request provider memerlukan `curl`.

## URL dan koneksi lokal

Origin tanpa path dinormalisasi ke `/v1`; API root dengan prefix seperti `/router/v1` dipertahankan. URL endpoint lengkap, query, fragment, userinfo, path ambigu, serta skema selain HTTP(S) ditolak. Nilai final ditampilkan setelah save. DNS tidak diakses saat save; server offline masih dapat disimpan untuk diuji kemudian.

Host publik memerlukan HTTPS dan TLS verification. Untuk 9Router lokal/LAN, atur allowlist origin pada konfigurasi server, misalnya:

```ini
AI_CONNECTION_ALLOWED_ORIGINS="http://127.0.0.1:20128"
```

Contoh tersebut bukan klaim port instalasi pengguna. Beberapa origin dipisahkan koma, tanpa path atau trailing slash. Scheme/host/port harus sama dengan origin URL yang telah dinormalisasi. `localhost` berarti host PHP. Alamat metadata/link-local, multicast, IPv4-mapped IPv6 dan rentang khusus tetap ditolak. Semua hasil DNS divalidasi, satu IP yang lolos dipin untuk koneksi, redirect ditolak, dan proxy dari environment dinonaktifkan agar tujuan terverifikasi tidak dilewati.

Batas internal: connect timeout 5 detik; request katalog 15 detik, generasi 60 detik; body respons 1 MiB. Lease probe per koneksi 75 detik dengan cooldown 5 detik berlaku lintas akun/tab. Session lock dilepas sebelum pekerjaan jaringan. Tidak ada biaya/token perkiraan buatan; token hanya berasal dari respons provider jika tersedia. Request chat memakai `model`, `messages`, `stream:false`; tidak mengasumsikan dukungan `max_tokens` pada semua model.

## Penyimpanan dan endpoint

`AiConnectionSettings::schema()` menjalankan dua migrasi CREATE IF NOT EXISTS, lalu menambah kolom/index `connection_id` jika belum ada. Tidak ada bootstrap destruktif. Karena skema lama belum memakai foreign key untuk profil, integritas delete/assign dijaga transaksi dan row lock pada koneksi yang sama. Binding profil, config dan version diperbarui bersama. Payload klien lama yang tidak menyertakan connection_id mempertahankan relasi yang ada.

- `ai_connections`: konfigurasi dan ciphertext, version, timestamps, hasil katalog/tes, lease/cooldown, deleted_at.
- `ai_connection_events`: pelaku, tindakan, version, nama field berubah, timestamp. Tidak menyimpan nilai key atau respons mentah provider.
- `automation_profiles`: relasi koneksi opsional di samping konfigurasi yang sudah ada.

`ProcAiConnections` mengekspos GET `list`, `detail?id=…`; POST `create`, `update`, `delete`, `models`, `test` pada `/procAiConnections/…`. Semua perlu login; POST memerlukan CSRF, JSON dan batas body 16 KiB. Error field terstruktur, error provider tersanitasi, response no-store. Save profil toko tetap `/procAutomation/save`.

Kode utama: `AiConnectionSupport.php` (error/settings/schema/secret), `NineRouterClient.php` (validasi URL, HTTP, parser), `AiConnection.php` (CRUD/probe), controller, partial `templates/ai-connections.php`, dan `public/assets/js/ai-connections.js`. Style sumber tetap `resources/css/input.css`; asset dibangun ulang.

Konfigurasi legacy `NINE_ROUTER_*` hanya menampilkan keterangan jika tersedia. Tidak ada import otomatis, penulisan `.env` dari form, atau fallback tersembunyi ke legacy. Impor legacy server-side yang disebut opsional dalam plan belum dibuat; masukkan koneksi melalui form.

## Verifikasi

```sh
php tests/ai-connections.php
php tests/ai-http.php
php tests/automation-profile.php
AUTOMATION_TEST_URL=http://127.0.0.1:8131 PLAYWRIGHT_MODULE=/absolute/path/to/playwright node tests/ai-connections-ui.cjs
AUTOMATION_TEST_URL=http://127.0.0.1:8131 PLAYWRIGHT_MODULE=/absolute/path/to/playwright node tests/automation-ui.cjs
npm run build
git diff --check
```

PHP suite memakai temporary tables: enkripsi/tampering/rotasi, validasi URL/IP, kontrak adapter, CRUD/version, relasi toko, deletion guard, cooldown dan hasil probe stale. HTTP suite menjalankan server fixture lokal pada port acak dan memeriksa cURL nyata, bearer header, chat JSON, redirect serta body cap. Tidak memanggil 9Router eksternal.

Browser suite memalsukan mutasi koneksi/profil dan provider, memeriksa alur form/key, pilihan keyboard, konflik/gagal/hapus, pemilihan koneksi per toko, serta 320/500/999/1600px pada kedua tema. Pemeriksaan auth/CSRF/invalid request memakai endpoint lokal nyata. Tidak ada fixture credential/profile bisnis yang ditulis ke database produksi. Screenshot di `tmp/ai-connections-ui/` tidak dipublikasikan.

Review UI menggunakan UI UX Pro Max dan antislop: form mengikuti tugas CRUD, palet/typography Shopdash dipertahankan, primary accent untuk simpan, kontrol minimal 44px, dropdown satu kolom di bawah input, nama panjang membungkus, error terhubung ke field dan focus, daftar/form responsif. Bayangan hanya membedakan menu terbuka dari form; tidak ada statistik atau status kesiapan palsu.
