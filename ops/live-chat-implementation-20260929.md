# Implementasi hasil audit Live Chat

29–30 September 2026. Worktree `fahra-kelola-chat-audit`, branch `audit/shopee-live-chat`. Lanjutan [audit](live-chat-audit-20260929.md) dan [rekaman browser](live-chat-browser-capture-20260929.md).

**Status: perbaikan jalur baca dan pengamanan kirim sudah diimplementasikan, diuji, dan dideploy ke live pada 30 September 2026. Pengiriman PHP ke Shopee belum berhasil: satu probe kirim ditolak dengan kode `90309999`, tanpa ID pesan. Ini bukan penyelesaian penuh bug balasan live.**

## Perubahan

- Bootstrap chat harus cocok dengan `shops.shop_id`. Feed lintas toko hanya diimpor ke toko pemilik yang cocok. List, detail, hitungan dan semua mutasi memeriksa kepemilikan `raw_payload.shop_id` terhadap toko lokal. Baris salah pemetaan lama tetap disimpan untuk audit, tetapi tidak ditampilkan/dikirim dari toko yang salah. Tidak ada penghapusan atau pemindahan pesan lama secara spekulatif.
- Worker chat memisahkan cursor latest dan older. Backfill menyimpan cursor baris terakhir **feed utuh**, termasuk baris toko lain; `next_timestamp_nano=0` mengikuti capture older. Backfill berakhir pada halaman kosong; cursor kosong/tidak maju pada halaman berisi dianggap error. Cursor terbaru menyimpan pasangan ID/region/nano dari baris yang sama.
- Setiap pass worker mengambil maksimum tiga detail percakapan, mendahulukan permintaan dari halaman. Permintaan detail tersimpan di `chat_thread_sync`; kegagalan detail mempunyai jeda retry satu menit. Job melanjutkan pass selanjutnya saat masih ada backfill/detail yang siap dikerjakan.
- GET panel hanya membaca cache. POST refresh ber-CSRF meminta detail dan memasukkan job chat. Tab yang terlihat memperbarui cache detail setiap 5 detik dan meminta sync percakapan terpilih setiap 30 detik. Tombol Perbarui memasukkan job untuk filter toko yang dipilih. Jadwal yang dijeda tidak diaktifkan otomatis.
- Enqueue chat memakai lock per toko; permintaan bersamaan berbagi job aktif. Permintaan baru setelah job selesai tidak lagi tersangkut pada idempotency bucket menit yang sama. Jalur sync lain mempertahankan key lama.
- Riwayat lokal menampilkan **200 pesan terbaru** dalam urutan kronologis. Pengambilan upstream masih halaman terbaru dengan limit 100 dan offset 0. Pagination riwayat lama belum dibuktikan; ini tidak dinyatakan sebagai arsip lengkap.
- Send memakai region toko, UUID terpisah untuk request/content/query, `source_content=[]`, dan key `dfp_access_f` hanya jika tersedia. Tidak ada signature/token dari MCP yang ditanam di source. TLS certificate verification aktif. Session expiry tidak direka sebagai 24 jam.
- Sebelum send, endpoint open memeriksa izin chat dan status closed. Intent UUID dicatat secara atomik di `chat_outbox`; duplikat mengembalikan receipt/status lama tanpa kirim ulang. HTTP 2xx saja tidak dianggap sukses: receipt harus mempunyai ID, conversation ID, request ID dan teks yang sesuai. Timeout, 5xx dan receipt yang tidak cocok menjadi ambigu. Riwayat dapat merekonsiliasi intent lewat request ID, scope toko/percakapan dan hash teks.
- UI menjaga target request tetap, mengabaikan respons percakapan lama, menyimpan draft per percakapan, dan menahan Enter/submit ganda. Intent belum pasti bertahan di sessionStorage setelah reload; teks tidak disimpan di sessionStorage. Pesan belum pasti tidak di-retry otomatis. Rejection yang pasti mempertahankan draft dan menawarkan salin balasan/buka Shopee; fallback salin tidak ditawarkan saat pengiriman masih ambigu.
- Error, jadwal dijeda, timestamp sync tersimpan dan status riwayat ditampilkan. Waktu polling browser tidak lagi dilabeli sebagai waktu sukses sync. Label statistik kini “Percakapan aktif”. Popup mempunyai focus trap, Escape dan pengembalian fokus. Identitas/tema/logo toko mengikuti komponen proyek.

## Bukti tambahan Chat Lagi

Mini chat dikendalikan melalui Chrome computer use. Satu percakapan tertutup dibuka lalu tombol **Chat Lagi** ditekan. Composer muncul dan Shopee menambahkan notifikasi agent bergabung; tidak ada pesan teks tambahan pada langkah ini.

XYZ Sniper MCP endpoint **17157**, payload **13531**, `last_seen=2026-09-29 23:23:54`:

```text
PUT /webchat/api/v1.2/mini/conversations/{conversation_id}/status
query/header region: ID
body: {shop_id: <toko pemilik>, status: "activated", biz_id: 2}
response: HTTP 204, tanpa body
```

Ini menggantikan ketidakpastian aktivasi pada rekaman sebelumnya. `mark_read` tidak otomatis dipanggil saat membuka detail. Percakapan closed harus memakai aksi Chat Lagi yang eksplisit karena endpoint status ini juga mengaktifkan percakapan.

## Verifikasi

1. `php tests/chat.php`: fixture transport dan tabel sementara per koneksi. Meliputi scope/identity, baris lama tercampur, pagination, worker detail, active-job dedupe, refresh setelah selesai pada menit yang sama, overlapping send intent, receipt, timeout, rekonsiliasi dari history, closed remote, recent 200, dan jadwal/timestamp saat error.
2. `php tests/sync-recovery.php`: 27 pemeriksaan database/outcome terisolasi lulus.
3. `node tests/chat-ui.cjs`: halaman PHP asli di server worktree, semua mutasi bisnis dan data chat dimock. Mencakup race A/B, draft, target send, double Enter, Chat Lagi, failed/ambiguous, reload, polling terpilih, CSRF/405/422, focus/Escape, dan 320/500/999/1600px pada kedua tema. Clipboard dimock. Screenshot diperiksa di `tmp/chat-ui/`, tidak dikomit.
4. Build Tailwind, PHP lint, JS syntax dan `git diff --check`.
5. Probe baca ke Shopee menggunakan cookie toko lokal 1 yang identitasnya cocok, tetapi seluruh hasil disimpan dalam **tabel sementara**, lalu tabel sementara dihapus. Dua pass: 37 lalu 79 percakapan milik toko yang benar; 86 lalu 121 pesan. Tidak mengubah tabel bisnis, jadwal atau worker produksi. Probe berikutnya setelah validasi identitas pesan diperketat juga sukses: 37 percakapan dan 86 pesan pada satu pass.
6. Probe send memakai target yang sama dengan pesan browser yang sebelumnya diizinkan. Pemeriksaan pertama berhenti sebelum request upstream karena target belum ada pada cache lama; target lalu diambil dari capture yang telah diverifikasi kepemilikannya. **Satu request send PHP** benar-benar dicoba. Bootstrap/open lolos, send ditolak `90309999`; tidak ada ID pesan, tidak ada retry. Outbox probe menggunakan tabel sementara. Jangan mengklaim pesan kedua terkirim.

Review antislop dan UI UX Pro Max mengikuti identitas Shopdash, fokus pada recovery/error placement dan akses keyboard. Kontras teks tombol utama terang 6,62:1; teks utama di permukaan dark 15,60:1. Warna teks utama digunakan pada kontrol aktif di kedua tema; tidak memakai klaim placeholder, testimonial, dekorasi atau desain ulang pemasaran. Launcher mengambang disembunyikan saat detail terbuka agar tidak menutupi tombol kirim.

## Batas dan pekerjaan tersisa

- **Blocker transport send:** cookie + bootstrap + kontrak body/query yang sudah dikoreksi belum cukup untuk sesi yang diuji. Browser Shopee berhasil mengirim pada rekaman sebelumnya, sedangkan PHP ditolak. Header keamanan dinamis dan `re_policy` berbeda; penyebab tepat per header belum diisolasi, sehingga tidak disebut sebagai kepastian. Menyalin nilai signature dari capture ke konfigurasi permanen bukan perbaikan yang terverifikasi.
- Jalur lanjutan yang dapat diuji adalah transport melalui konteks browser Shopee yang masih terautentikasi, atau integrasi resmi dengan akses chat. Ini rencana, bukan klaim bahwa sebuah bridge sudah terbukti bekerja. Komponen yang tersedia sekarang tidak menyediakan transport tersebut untuk aplikasi: XYZ Sniper yang dipakai adalah pembaca capture, Sellerio 2.1.0 adalah penyalin cookie. Belum ada penghubung browser/extension baru yang diterapkan atau diinstal.
- `user_is_forbidden` pada sebagian toko dalam audit awal tidak diperbaiki dengan menganggapnya cookie expired. Probe implementasi ini bukan pengujian ulang seluruh tujuh toko. Refresh menampilkan penolakan apa adanya.
- Tidak ada klaim histori lengkap, pagination history offset berikutnya, burst latest lebih dari satu halaman, multi-negara, atau pengiriman PHP berhasil.
- Daftar UI dibatasi 100 hasil terbaru per filter; pencarian berjalan di server pada seluruh percakapan tersimpan yang valid.
- Row lama tanpa bukti owner tidak dipindahkan otomatis. Backfill mengisi row milik toko yang sah dan history mengambil ulang detail pada scope yang benar.

## Integrasi / operasi

### Deploy live 30 September 2026, sekitar 00.12–00.15 WIB

- Perubahan main dari agent lain sampai `7aaa3b2` digabung dan CSS dibangun di worktree terpisah. Hasil integrasi `4ceebad` dipasang dengan fast-forward ke checkout layanan `shopdash`. Tidak ada reset/force atau penimpaan perubahan kerja agent lain.
- Tes chat, 27 pemeriksaan sync recovery, 38 pemeriksaan notifikasi, dan UI chat empat lebar × dua tema lulus pada hasil integrasi. Perubahan main terakhir setelah tes hanya dokumentasi.
- Service `com.fahra.shopdash.worker` dihentikan singkat untuk mengganti kelas PHP yang sudah dimuat. Setelah memastikan tidak ada worker umum lain, satu lease job yang ditinggalkan dilepas; status, progress, retry dan seluruh item dipertahankan. Snapshot lease disimpan privat di `/tmp/shopdash-chat-audit/deployment-leases.json`. Service yang sama dipasang kembali dari plist yang sudah ada dan berjalan dengan kode baru. Web, scheduler dan service Boost tidak diubah.
- `ChatMonitor::ensureSchema()` berhasil menerapkan tabel tambahan. Query diagnostik jadwal pertama memakai nama tabel yang salah dan gagal setelah migrasi/pelepasan lease selesai; pemeriksaan ulang memakai tabel aktual `sync_schedules` membuktikan tujuh jadwal chat tetap `enabled=0`. Tidak ada import schema dasar.
- Chrome computer use membuka `https://shopee.fahra.my.id/panel/chat`, memilih hiban.store, lalu menekan **Perbarui dari Shopee**. Job `17373` benar-benar diproses worker produksi. Pada 00.14.48 WIB, snapshot toko berstatus `ok`, 125 percakapan dengan owner yang cocok, 154 pesan tersimpan, serta delapan thread mempunyai timestamp history sukses. Job masih meneruskan backfill; angka ini bukan klaim seluruh arsip sudah selesai.
- Detail percakapan uji di domain publik menampilkan riwayat dan pesan browser yang benar-benar dikirim pada 29 September 23.13. Ini bukti jalur baca produksi, bukan fixture UI. Asset JS/CSS yang dilayani origin port 8123 cocok byte-for-byte dengan checkout live. Pemeriksaan asset publik melalui urllib ditolak HTTP 403; verifikasi domain publik dilakukan lewat Chrome yang sudah login.
- Tidak ada pesan uji tambahan pada deploy ini. Hasil send PHP terakhir tetap penolakan `90309999`; deploy tidak dianggap memperbaiki penolakan transport tersebut. Penolakan akses yang tersimpan pada empat toko lain belum diuji ulang dalam smoke test satu toko ini.

Jangan menjalankan worker dari checkout ini bersamaan dengan worker produksi: keduanya memakai database yang sama dan lock proses berasal dari path checkout. Merge source secara terkoordinasi ke checkout web/worker yang sama, kemudian build CSS dari source hasil merge. Ini penting saat agent lain juga mengubah stylesheet.

Migrasi `database/migrations/20260929_chat_delivery.sql` hanya menambah tiga tabel. Model membuatnya melalui `ensureSchema()`; dapat diaplikasikan lebih dahulu saat rollout. Tidak perlu import ulang schema database. Jadwal lama tetap utuh. Perbarui manual adalah job satu kali meskipun jadwal toko dijeda.

Jangan mengaktifkan semua jadwal secara massal atau menyatakan balasan sudah pulih sebelum transport send lolos probe nyata. Setelah transport tersedia: uji satu intent, cocokkan remote receipt/history, pastikan duplikat tidak mengirim ulang, baru lakukan rollout kirim. Dokumen ini sengaja memisahkan tes mock dari bukti Shopee langsung.
