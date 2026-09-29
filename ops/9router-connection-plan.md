# Audit dan rencana CRUD koneksi 9Router

Tanggal: 2026-09-29. Basis aplikasi: `c51b220`. Status: **audit dan rencana saja**. Belum ada form CRUD, migrasi baru, penyimpanan API key, atau panggilan ke instalasi 9Router pengguna dalam pekerjaan ini.

## Tujuan dan batas pekerjaan

Pengguna meminta form base URL, API key, dan nama model/combo 9Router dengan CRUD serta penyimpanan backend. Rekomendasi: beberapa profil koneksi yang dapat dipakai ulang oleh toko, sementara persona dan aturan rating tetap milik tiap toko.

CRUD di sini mengelola konfigurasi koneksi di Shopdash. Nama combo merujuk combo yang sudah ada di 9Router; membuat/mengubah susunan fallback combo melalui API administrasi 9Router tidak termasuk tahap ini. Model yang dipilih bukan aktivasi automasi rating. Worker dan pengiriman Shopee tetap di luar cakupan.

## Temuan audit source lokal

| Bukti | Kondisi aktual | Implikasi |
| --- | --- | --- |
| `app/helpers/AutomationPolicy.php::providerStatus()` | Hanya membaca tiga key `NINE_ROUTER_*` dari `.env`; `verified=false` | Belum ada penyimpanan koneksi dinamis atau uji provider |
| `app/views/panel/automation.php` | Panel Provider AI hanya status dan input model toko | Tambahkan pengelola koneksi dan pemilihan koneksi |
| `AutomationPolicy::validate()` | Nama model opsional, maksimum 120 karakter | Pertahankan data lama; model/combo adalah string, bukan enum hard-coded |
| `AutomationProfile.php` dan migrasi profil | JSON konfigurasi per toko, versi optimistis dan event | Gunakan kembali mekanisme konflik untuk pengaitan koneksi |
| `ProcAutomation.php` | Save dan preview, CSRF; preview lokal | CRUD provider sebaiknya controller terpisah, karena bukan aturan rating |
| `Auth.php`, `Account.php`, `public/index.php` | Login panel, session berisi ID/nama/email; belum ada peran/tenant provider | Jangan mengklaim isolasi multi-tenant atau admin-only yang belum ada |
| Pencarian source enkripsi | Tidak ditemukan helper enkripsi credential provider | Perlu penyimpanan secret tersendiri, jangan masukkan key ke JSON profil |
| Pemeriksaan PHP CLI | Ekstensi `sodium` dan `curl` tersedia | Bisa memakai fasilitas PHP yang ada; SAPI web harus dicek saat implementasi |

Auth tahap awal mengikuti panel saat ini: akun panel terautentikasi merupakan pengelola bersama, koneksi bersifat bersama, dan `created_by` hanya pencatatan pelaku. Jika akun terbatas dibutuhkan, tambahkan otorisasi eksplisit sebelum membagikan CRUD kepada mereka. Jangan menyebut data milik satu akun sambil tetap mengizinkan akses semua akun.

## Kontrak 9Router yang berhasil ditelusuri

- Dokumentasi combo menggunakan nama combo langsung pada field `model` untuk `POST /v1/chat/completions`, dengan bearer API key. Shopdash tidak perlu menyalin algoritme fallback 9Router. [Dokumentasi combo upstream](https://github.com/decolua/9router/blob/master/gitbook/content/en/features/combos.md).
- Handler katalog upstream mengembalikan `{object:"list",data:[...]}`; combo memiliki `id` berupa namanya dan `owned_by:"combo"`. Model chat menjadi cakupan default. Katalog tidak menjamin model dapat menghasilkan jawaban saat itu. [Source katalog](https://github.com/decolua/9router/blob/master/src/app/api/v1/models/route.js).
- Endpoint `/api/combos` adalah API pengelolaan combo yang berbeda dari penggunaan combo untuk generasi. Tidak perlu dipanggil untuk CRUD koneksi Shopdash. [Source pengelolaan combo](https://github.com/decolua/9router/blob/master/src/app/api/combos/route.js).

Source upstream yang dibaca adalah `master`; referensi remote saat audit `f01fb909e37189008080632ddaf404f096345cde`. Ini bukan bukti versi instalasi pengguna. Base URL sebenarnya, aturan autentikasi deployment, daftar combo, akses PHP ke provider, dan kompatibilitas parameter generasi masih belum diuji. Respons katalog sukses tidak cukup untuk menyatakan API key valid; kebijakan autentikasi bisa berada di lapisan lain.

## Rancangan form dan alur

Tetap di **AI Agent → Automation**. Panel Provider AI menampilkan koneksi pilihan toko, model efektif, status uji terakhir, dan tombol **Kelola koneksi**. Pengelola dibuka sebagai bagian form di halaman yang sama dengan fragment `#ai-connections`, dapat diakses walau belum ada toko. Tidak menambah sidebar atau modal berlapis.

Daftar koneksi berisi nama, base URL, model/combo default, jumlah toko pengguna, dan hasil uji bertanggal. Aksi nyata: **Tambah koneksi**, **Ubah**, **Muat model**, **Uji model**, **Hapus**. Pada mobile baris berubah menjadi susunan vertikal; URL dan nama panjang dapat membungkus.

| Field | Perilaku |
| --- | --- |
| Nama koneksi | Wajib; label internal, maksimum 80 karakter |
| Base URL 9Router | Wajib; tampilkan bentuk final setelah normalisasi; contoh placeholder `https://router.example.com/v1` |
| API key | Wajib saat membuat; input password; saat edit kosong berarti pertahankan key lama; tindakan eksplisit untuk mengganti |
| Model / combo default | Wajib saat membuat; input pencarian dengan daftar dari provider dan opsi input manual; simpan ID/nama persis, case-sensitive |

Koneksi dapat disimpan tanpa tes jaringan, sehingga provider offline tidak menghalangi CRUD. Alur awal: isi empat field, **Simpan koneksi**, opsional **Muat model** atau **Uji model**, lalu pilih koneksi pada toko dan **Simpan konfigurasi**. Memilih koneksi tidak menyimpan profil toko secara diam-diam.

Nama model/combo maksimal 255 karakter sebagai batas internal aplikasi, tanpa spasi tepi atau karakter kontrol. Daftar menampilkan kelompok Combo dan Model bila metadata tersedia. Jangan membuat label Combo hanya dari tebakan nama. Jika katalog kosong/gagal atau ID tidak ditemukan, tetap izinkan input manual dengan status belum terverifikasi. Dropdown terbuka ke bawah dalam satu kolom, dapat dicari, memiliki tinggi terbatas dan navigasi keyboard.

Model efektif toko: override `config.model` jika terisi, selain itu `default_model` koneksi pilihan. Tampilkan keduanya dengan jelas. Mengganti default koneksi memengaruhi toko yang mewarisi default; form menyebut daftar/jumlah toko terdampak sebelum simpan. Tidak otomatis mengganti koneksi suatu toko ketika koneksi lain ditambah.

API key tersimpan hanya ditampilkan sebagai **Key tersimpan**, bukan nilai asli atau masker yang dikirim ulang sebagai key. Tombol tampil/sembunyi hanya berlaku untuk key baru yang sedang diketik. Kosong tidak menghapus key; untuk menghilangkan credential, hapus koneksi setelah melepas penggunaannya.

State wajib: belum ada koneksi, memuat daftar, tersimpan namun belum diuji, katalog tersedia, sedang menguji model, uji gagal, koneksi berubah sejak uji, konflik versi, gagal simpan, dan koneksi masih dipakai saat hapus. Error dekat field dengan `aria-describedby`, ringkasan error yang dapat difokuskan, serta input tetap utuh saat gagal. Pesan hasil uji tidak boleh menggantikan status perubahan belum disimpan.

Design read: formulir pengelolaan koneksi bagi pengelola toko, mengikuti warna hangat, tipografi, serta tema Shopdash; ENERGY 1 / RHYTHM 1 / MOTION 1. Aksen primary untuk simpan; susunan daftar dan form mengikuti pekerjaan CRUD, tanpa metrik atau dekorasi AI. Kontrol minimal 44px, mobile satu kolom, kedua tema, focus terlihat. Riset UI UX Pro Max `error summary validation` memberi rekomendasi field error terhubung dan ringkasan berfokus; antislop diterapkan pada copy, struktur, dan state. Verifikasi visual baru dilakukan saat UI diimplementasikan, bukan diklaim lulus dari rencana ini.

## Penyimpanan backend yang diusulkan

1. `ai_connections`: ID, provider tetap `9router`, name, base_url, default_model, key_ciphertext, key_nonce, key_version, version, created_by/updated_by, timestamps, deleted_at. Status uji disimpan terpisah menurut katalog dan generasi, dengan waktu, connection_version, model yang diuji, dan kode hasil tersanitasi.
2. `ai_connection_events`: connection_id, actor_id, action, version, timestamp dan nama field yang berubah. Tidak menyimpan key, plaintext, authorization header, atau body provider.
3. Tambah `connection_id` nullable pada `automation_profiles`, dengan index dan foreign key bila kompatibel dengan skema aktual. Nilai relasi bukan diduplikasi ke config JSON. Simpan relasi dan config dengan satu versi/transaksi profil.

Toko lama tetap `connection_id=NULL`, konfigurasi persona/target/model tidak berubah. Validasi mengizinkan profil disimpan tanpa koneksi agar pondasi masih berguna; generasi kelak membutuhkan koneksi. Model lama maksimum 120 tetap valid ketika batas diperluas menjadi 255.

API key dienkripsi dengan authenticated encryption XChaCha20-Poly1305 dari Sodium: random nonce baru setiap tulis, AAD mengikat connection ID dan versi format. Master key acak 32 byte ada di konfigurasi server di luar database/Git, dengan key ID untuk rotasi. Jangan gunakan password akun, cookie, atau API key provider sebagai master key. Bila master key hilang/tidak valid, operasi secret gagal jelas dan tidak membuat key baru otomatis. Backup/rotasi master key harus mengikuti data yang dienkripsi. [Kontrak fungsi PHP](https://www.php.net/manual/en/function.sodium-crypto-aead-xchacha20poly1305-ietf-encrypt.php).

GET detail/list dan respons save mengembalikan field publik serta `has_api_key`, tidak ciphertext atau secret. Secret hanya masuk melalui POST dari form dan keluar melalui Authorization header adapter server. Jangan simpan pada localStorage, query URL, log, HTML, atau audit event.

Update/delete memakai expected `version`, HTTP 409 jika stale. Delete ditolak ketika masih direferensikan toko, dengan daftar toko yang harus dipindah/dilepas. Setelah tidak direferensikan, soft-delete metadata dan kosongkan ciphertext/nonce dalam transaksi; audit tetap ada. Lock baris koneksi saat pengaitan dan penghapusan agar toko tidak dapat mengaitkan koneksi yang baru dihapus. UI meminta konfirmasi hapus dengan nama koneksi.

## Endpoint aplikasi yang diusulkan

Gunakan casing route custom MVC secara eksplisit. Semua endpoint perlu auth panel; POST memerlukan CSRF dan body terbatas. Respons error tidak berisi respons provider mentah.

| Endpoint | Kontrak |
| --- | --- |
| `GET /procAiConnections/list` | Daftar metadata, has_api_key, penggunaan toko, status uji |
| `GET /procAiConnections/detail?id=…` | Metadata satu koneksi tanpa secret |
| `POST /procAiConnections/create` | name, base_url, api_key, default_model; simpan terenkripsi, tanpa panggilan jaringan |
| `POST /procAiConnections/update` | id, expected version, field editable; `api_key` tidak dikirim berarti pertahankan |
| `POST /procAiConnections/delete` | id, expected version; tolak jika dipakai |
| `POST /procAiConnections/models` | id dan expected version; GET katalog dari backend; hasil sementara, tidak mengganti model pilihan |
| `POST /procAiConnections/test` | id, expected version, model yang diuji; generasi contoh sintetis singkat; tidak ada data pelanggan |
| `POST /procAutomation/save` | Tambah connection_id nullable, validasi koneksi tidak terhapus, pertahankan konflik versi profil |

`Uji model` mengirim contoh tetap tanpa rating pelanggan. Tampilkan sebelum tindakan bahwa tes melakukan satu permintaan AI dan dapat memakai kuota. Jangan jalankan saat buka halaman atau simpan. Gunakan `{model, messages, stream:false}`; parameter pembatas output harus cocok dengan versi/model yang diuji, tidak mengasumsikan semua menerima parameter tambahan yang sama. Respons sukses membutuhkan konten assistant valid, bukan sekadar HTTP 200. Jangan auto-retry tes generasi setelah timeout karena mungkin sudah memakai kuota.

Katalog sukses hanya berarti **Daftar model berhasil dimuat**. Generasi sukses berarti **Model X berhasil diuji pada waktu Y**, bukan semua model/aturan siap. Perubahan base URL/key/default model menandai hasil lama tidak berlaku. Hasil tes yang selesai setelah konfigurasi berubah tidak boleh menimpa status versi baru. Pemakaian token ditampilkan hanya jika upstream memberikannya; jangan mengarang biaya.

## Adapter HTTP dan URL

Base URL disimpan sebagai API root termasuk `/v1`. Hapus trailing slash; tambahkan `/v1` hanya untuk origin tanpa path. Untuk reverse proxy dengan prefix, pengguna memasukkan path API root lengkap. Tolak path endpoint lengkap seperti `/chat/completions`, URL dengan userinfo, query/fragment, karakter kontrol, dan skema selain HTTP(S). Jangan menghasilkan `/v1/v1` atau memotong prefix proxy.

HTTPS wajib untuk host publik, TLS verification tetap aktif. 9Router lokal boleh memakai HTTP hanya untuk origin lokal/LAN yang diizinkan secara eksplisit pada server (scheme, host, port), bukan pengecualian ke semua jaringan privat. `localhost` berarti mesin PHP, bukan browser pengguna. Backend memvalidasi seluruh hasil DNS dan alamat IPv4/IPv6, menolak alamat metadata/link-local, mem-pin alamat yang lolos untuk request, menolak redirect, dan membatasi metode/path ke katalog serta chat completions. Kebijakan proxy harus eksplisit agar validasi tujuan tidak dilewati.

Saat base URL berubah, jangan kirim key tersimpan ke tujuan baru untuk tes. Wajib masukkan ulang API key untuk perubahan API root; validasi dan simpan perubahan keduanya atomik. Ini mencegah aksi edit URL saja memindahkan secret lama ke server berbeda.

Batas awal internal: connect timeout 5 detik, katalog 15 detik, tes model 60 detik, response body maksimum 1 MiB, dan satu tes in-flight per koneksi dengan cooldown singkat server-side. Angka ini usulan aplikasi, bukan batas 9Router. Tangani 401/403, 404, 429, 5xx, TLS, timeout, JSON tidak valid, serta body berlebihan dengan pesan spesifik dan tersanitasi. Lepaskan session lock sebelum request jaringan setelah auth/CSRF selesai agar permintaan provider tidak menahan navigasi panel.

## Transisi dari .env

Database menjadi sumber konfigurasi koneksi yang dipilih secara eksplisit. Jangan menulis `.env` dari form. Nilai `NINE_ROUTER_*` lama dapat ditawarkan sebagai impor server-side sekali ketika lengkap, dengan aksi pengguna dan tanpa mengirim key ke browser; jangan auto-import/auto-assign saat migrasi. Bila belum diimpor, tampilkan sumber legacy sebagai keterangan, bukan koneksi DB palsu. Hindari fallback tersembunyi ke `.env` ketika koneksi DB dihapus atau gagal. Master key dan allowlist tujuan internal tetap konfigurasi deployment, bukan field bebas di form provider.

## Urutan implementasi dan penerimaan

1. Migrasi additive, helper secret, validasi URL, model koneksi, event dan transaksi versi. Uji enkripsi/rotasi, secret tidak bocor, konflik dan delete-versus-assign dengan temporary tables.
2. CRUD controller dan form pengelola; dukung kosong/error/simpan/update/hapus. Uji auth/CSRF, semua operasi CRUD, key kosong dipertahankan, penggantian key, URL berubah harus key baru, dan detail tidak memuat key.
3. Adapter katalog dan tes model memakai HTTP fixtures. Uji model/combo/manual, normalisasi URL, DNS/redirect/IPv6/allowlist, timeout/rate limit, output rusak, dan hasil tes stale. Pengujian biasa tidak memakai provider nyata.
4. Pengaitan profil toko dan transisi konfigurasi lama. Uji satu koneksi dipakai beberapa toko, override model independen, penggantian default, blok penghapusan ketika dipakai, rollback migrasi aplikasi tanpa menghapus data.
5. UI pada 320/500/999/1600px, light/dark, keyboard, nama panjang, dropdown satu kolom, dirty state dan konflik dua tab. Pertahankan pengujian Automation lama; periksa CSS bersama setelah penggabungan.
6. Uji instalasi 9Router sebenarnya hanya setelah base URL/key dimasukkan melalui form. Smoke test sintetik mengikuti tombol Uji model; tidak memakai ulasan pembeli, tidak mengaktifkan worker atau mengirim balasan rating.

File baru yang disarankan: `AiConnection.php`, `AiConnectionSecret.php`, `NineRouterClient.php`, `ProcAiConnections.php`, migrasi khusus, view partial pengelola koneksi, JS dan tests terpisah. File integrasi: Panel, AutomationPolicy/Profile/controller, automation.php/js dan CSS. Nama final mengikuti source saat implementasi.

Kerjakan di worktree terpisah karena beberapa Codex sedang aktif. Integrasikan perubahan sidebar/CSS/controller secara additive, rebuild CSS jika implementasi mengubahnya, commit file task secara eksplisit, dan push tanpa force. Audit ini hanya mengubah dokumentasi; tidak perlu menjalankan ulang tes bisnis atau mengubah database.

## Hal yang perlu dikonfirmasi saat implementasi

- Versi dan API root instalasi 9Router yang dipakai, keterjangkauan dari PHP, serta apakah perlu origin LAN yang diizinkan server.
- Nama combo nyata dan parameter uji generasi yang didukung. Tidak perlu membagikan API key melalui percakapan.
- Asumsi pengelola panel bersama sesuai auth saat ini. Pemisahan akses antar akun memerlukan desain izin tersendiri.

Scope rencana sudah mencakup form dan CRUD lengkap, penyimpanan terenkripsi, serta pemilihan koneksi/model per toko. Rancangan ini belum mengimplementasikan maupun mengaktifkan perilaku tersebut.
