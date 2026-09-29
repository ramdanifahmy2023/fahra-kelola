# Chat Shopee: riwayat dan notifikasi

30 September 2026. Pengguna memilih Shopdash sebagai pembaca riwayat, balasan melalui Shopee, dan notifikasi memakai lonceng/badge yang sudah ada dengan bunyi. Penghubung browser untuk mengirim tidak dilanjutkan. Implementasi dibuat di worktree `fahra-kelola-chat-audit`.

## Bukti audit

- Probe baca langsung sekitar 01.10 WIB: hiban.store (lokal 1), Royal Abiya (2), dan Hermosa Brand Fashion (5) berhasil bootstrap dengan identitas toko cocok, mengambil daftar, dan membaca history. Haviel (3), Hiban Signature (4), elfuad.store (6), dan Safariana (7) tetap mendapat `user_is_forbidden`. Ini tidak dianggap cookie expired.
- History tiga toko sehat berisi 23, 60, dan 4 pesan. Pesan pembeli non-system yang diperiksa berjumlah 6, 21, dan 1; seluruhnya mempunyai `to_id` sama dengan user seller bootstrap dan `to_shop_id` sama dengan toko pemilik. `shop_id` seluruh pesan history ini bernilai **0**. Guard memakai identitas penerima/percakapan, bukan menganggap `message.shop_id` sebagai owner.
- XYZ Sniper MCP `get_payloads`, endpoint history 1274, payload 3807, HTTP 200: 12 pesan, semuanya `shop_id=0`, dengan field `to_shop_id`. Capture memeriksa bentuk; probe PHP langsung memeriksa identitas penerima. Tidak menyimpan token/cookie/body pelanggan dalam dokumen ini.
- Penolakan kirim PHP `90309999` dari [audit pengiriman](live-chat-send-plan-20260930.md) tetap merupakan hasil historis. Menonaktifkan pengiriman menghindari jalur yang ditolak; tidak membuktikan transport kirim sudah diperbaiki.

## Kontrak implementasi

1. Halaman **Chat Shopee** membaca cache. Form kirim, aktivasi ulang dan tindakan mengubah status Shopee dihapus. `/procChat/send` dan `/procChat/mark_read` selalu 405 dengan `attempted=false`, sebelum memuat client/job. Refresh ber-CSRF hanya mengantrekan pembacaan.
2. **Balas di Shopee** membuka URL Seller Centre yang diamati, `https://seller.shopee.co.id/webchat/conversations`. Tidak mengarang deep link Shopee ke pembeli tertentu; pengguna memilih toko/pembeli yang sesuai.
3. `chat_incoming_monitors.started_at` ditetapkan sekali per toko sebelum import pertama dengan sesi valid. History sebelum/sama dengan cutoff tidak membuat notifikasi. Detector menolak waktu tanpa timezone/jauh di masa depan, identitas sender/penerima/toko/percakapan yang tidak cocok, system/notification dan pesan keluar.
4. `chat_incoming_events` unik pada `(shop_id,remote_message_id)`. Event dan `alerts` berada dalam satu transaksi. Kegagalan alert membatalkan event supaya retry bisa membuat notifikasi. Guard bootstrap/feed yang sudah ada tetap berlaku.
5. Satu alert `chat_incoming`, severity `info`, per percakapan. Jumlah event percakapan adalah stage monotonik; pesan berikutnya menaikkan revision, polling ulang tidak. Badge menghitung percakapan/notifikasi belum dibaca, bukan setiap pesan dalam percakapan yang sama.
6. `NotificationCenter` tetap mengelola satu lonceng, hitungan, group toko/type, filter mendesak, paging, receipt dan pengingat nanti. Filter type mengizinkan chat; chat tidak dilabeli mendesak. Receipt/snooze per akun/revision tidak menulis Shopee. Pesan baru melewati receipt/snooze revision lama; alert stok/pengiriman tidak berubah.
7. Link lokal membawa `shop_id` dan `conversation_id`. Detail bisa dibuka di luar 100 hasil daftar terbaru. Label “Belum dibaca di Shopee” dibedakan dari receipt pada lonceng Shopdash.
8. `chat_thread_sync.latest_message_id` ditambah idempoten oleh `ChatMonitor::ensureSchema()`. Perubahan ID, bukan hanya waktu detik, menentukan history yang perlu dibaca ulang. Kegagalan history tidak memajukan ID tersinkron.

## Bunyi pada lonceng yang sama

- Satu controller `notifications.js`, tanpa bell/toast/push terpisah. `chat_cursor` berasal dari event chat; perubahan stok/pengiriman tidak memutar nada chat. Kegagalan metadata chat tidak mematikan response notifikasi operasional.
- Klik **Aktifkan bunyi chat** pada lonceng membuat/resume AudioContext. Status aktif bergantung pada state browser, bukan preference saja. Setelah reload, halaman mungkin perlu diaktifkan lagi. Tanpa AudioContext/Web Locks/penyimpanan yang berfungsi, aplikasi tidak mengklaim bunyi aktif.
- Web Locks dan localStorage mengklaim satu event/burst untuk akun pada origin/browser yang sama. Dua tab aktif tidak memutar dua bunyi. Cursor string/BigInt mempertahankan ID besar. Mute per akun pada browser dibagikan antar-tab.
- Snapshot awal, aktivasi bunyi, dan recovery setelah cursor lebih lama dari 90 detik menetapkan baseline hening. Satu burst memutar satu nada pendek dua bagian.
- Poll sekitar 30 detik, juga pada tab di belakang. Browser dapat menunda timer/suspend audio; membuka tab kembali memicu pemeriksaan. Tab tertutup tidak mempunyai bunyi/push. Data tetap diambil worker selama host/service berjalan.
- Referensi: [Web Locks](https://developer.mozilla.org/en-US/docs/Web/API/Web_Locks_API), [AudioContext.resume](https://developer.mozilla.org/en-US/docs/Web/API/AudioContext/resume). Tidak menjanjikan notifikasi seketika.

## Rencana rollout dan batas

Bangun CSS gabungan main dan jalankan tes sebelum fast-forward checkout live yang bersih. Hentikan hanya worker umum terpasang untuk mengganti kelas PHP; pastikan proses lama berhenti sebelum melepas lease yang ditinggalkannya. Jangan mengubah status/progress/retry, menjalankan worker worktree uji, atau merestart Boost.

Terapkan schema tambahan, seed cutoff dan aktifkan **hanya chat toko 1, 2, 5** dengan interval 60 detik berdasarkan probe berhasil dan permintaan notifikasi otomatis. Empat toko yang ditolak tetap dijeda. Scheduler terpasang berjalan per 60 detik; antrean/polling UI menambah keterlambatan. Ini berkala, bukan realtime.

History upstream masih 100 pesan terbaru per pengambilan; detail cache menampilkan 200 terbaru; backfill daftar bertahap. Pagination history lebih lama dan burst latest lebih dari satu halaman belum dibuktikan. Jangan mengklaim arsip lengkap/dukungan tujuh toko. Tidak membuat pesan pembeli baru untuk uji produksi; bukti event baru/bunyi berasal dari fixture sampai ada pesan pembeli nyata setelah cutoff.

### Deploy live, sekitar 01.23–01.26 WIB

- Source main dari agent lain sampai `730a92a`, termasuk saldo seller dan pembaruan workspace/notifikasi, ikut digabung. CSS dibangun ulang dari source gabungan; tes chat/notifikasi empat lebar × dua tema dan 31 pemeriksaan incoming dijalankan ulang. Checkout live bersih di-fast-forward ke `513d3ad`.
- Hanya `com.fahra.shopdash.worker` dihentikan singkat. Setelah proses lama berhenti dan lock worker checkout live diperoleh, satu lease job dan lima lease detail yang ditinggalkan dilepas. Status/progress/retry dipertahankan; snapshot privat `readonly-deployment-state.json`. Service yang sama dibootstrap dari plist lama, satu runner teramati PID 81410. Web, scheduler dan Boost tidak direstart.
- Schema tambahan berhasil. Cutoff toko 1/2/5 tercatat sekitar 01.23.06 WIB. Ketiga jadwal aktif dengan interval 60 detik; 3/4/6/7 tetap `enabled=0`. Job baca 1 memakai job aktif 17373; toko 2/5 memakai 17579/17580. Tidak ada job send.
- Pada 01.26 WIB, snapshot toko 1/2/5 `ok` tanpa error dan waktu baca baru sekitar 01.25–01.26. History cursor baru terisi pada 11/9/9 thread. Job masih meneruskan backfill; `last_success_at` schedule belum menandai seluruh pekerjaan selesai. Event/alert chat baru masih **0**, sehingga impor lama terbukti tidak menaikkan badge chat pada pengamatan ini.
- Computer use Chrome membuka domain publik, memakai login tersimpan saat sesi lama expired, memilih hiban.store dan membuka history. Halaman berjudul **Chat Shopee**, riwayat nyata terlihat, dan **Balas di Shopee** mempunyai destination Seller Centre. Lonceng tetap menampilkan 62 notifikasi operasional belum dibaca dalam lima kelompok; tidak diacknowledge. **Aktifkan bunyi chat** berubah menjadi **Matikan bunyi chat**, dengan status browser aktif. Ini bukti aktivasi audio, bukan klaim sudah mendengar pesan pembeli baru.

## Verifikasi

- `php tests/chat-incoming.php`: 31 pemeriksaan temporary tables untuk cutoff, recipient mapping `shop_id=0`, identitas asing, dedupe, mixed badge/group, receipt/snooze, revision, rollback/retry, ID berubah dalam detik yang sama, worker tanpa mutasi Shopee.
- Tes chat lama, 43 pemeriksaan notifikasi dan 27 sync recovery lulus.
- `tests/chat-ui.cjs`: UI/endpoint hanya baca, response race, polling cache/recovery, target di luar daftar terbaru, pilihan toko, popup/keyboard dan 320/500/999/1600px di dua tema.
- `tests/chat-sound-ui.cjs`: grouped chat/filter/link, baseline hening, autoplay ditolak, nada khusus cursor chat, polling duplikat, lock dua tab, tab belakang, outage/mute. AudioContext fixture; bukan rekaman suara pesan pembeli asli.
- `tests/notifications-ui.cjs` dan `tests/workspace-comfort-ui.cjs` lulus. Contrast tersampel notifikasi minimal 15.60:1 dan workspace 12.96:1, bukan sertifikasi seluruh elemen lama. Tes final memakai account fixture terpisah dan mutation endpoint dimock.
- PHP/JS syntax, build CSS, whitespace check lulus. Screenshot diperiksa dan tetap ignored; payload mentah privat.

## UI UX Pro Max dan antislop

Design Read: inbox operasional untuk pengelola toko, memakai warna hangat, typography, Material Symbols dan shop logos Shopdash; ENERGY 2 / RHYTHM 1 / MOTION 1. History fokus halaman, link Shopee tindakan utama. Bell yang sama mengurangi tempat pemantauan; badge berasal dari DB. Tidak ada dekorasi/motion baru. Rekomendasi “Contextual Live Badge Updates” memakai satu atomic status pada lonceng tanpa memindahkan fokus saat polling.

| Gate | Hasil dan bukti |
| --- | --- |
| R-02/03/17/18 | PASS: copy faktual, hitungan DB, tanpa statistik/testimoni rekaan, overflow empat lebar diuji |
| R-23/24/26 | PASS: identitas yang ada, destination lokal/Shopee nyata, kontrol berubah diuji |
| R-25/27/28 | PASS: contrast terukur, loading/empty/error/recovery, tidak menambah FAQ |
| R-32/33/34/35 | PASS: keyboard/focus popup/bell, source lalu build, kedua tema, runtime terisolasi |
| R-36/37/38 | PASS: batas baca/akses/waktu tertulis, arah visual yang ada, fixture hanya dalam tes |
| R-01/04/06/07/08/09 | PASS: palette/font/icon lama, icon chat menandai sumber, satu badge hitungan nyata |
| R-10/12/13/14/19/22 | PASS: popup lama untuk ruang baca, tanpa glow/ilustrasi/motion dekoratif baru |
| Dials/focal point/whitespace/accent/motif/Design Read | PASS: history fokus, footer terpisah dari scroll, accent/identitas toko konsisten |
| C-1/2/3/4/5 | PASS dalam cakupan tes: alur baca konkret, kontrol diuji, gagal terlihat, klaim berbukti |
| R-05/11/15/16/20/21/29/30/31 | PASS: layout kerja Shopdash, tindakan spesifik, dua tema, tanpa template pemasaran baru |
