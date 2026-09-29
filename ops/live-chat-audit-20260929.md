# Audit dan rencana perbaikan Shopee Live Chat

Tanggal observasi: 29 September 2026, sekitar 22:58–23:02 WIB. Audit kode pada `b06b25b`, worktree `fahra-kelola-chat-audit`, branch `audit/shopee-live-chat`. Dokumen ini adalah hasil audit dan rencana, bukan implementasi perbaikan.

**Pembaruan:** [rekaman browser langsung 23:09–23:15 WIB](live-chat-browser-capture-20260929.md) melengkapi audit ini. Pengguna kemudian mengizinkan satu pesan uji dengan penerima/teks bebas; kirim lewat mini chat berhasil dan responsnya diambil melalui MCP. Pagination daftar halaman kedua juga terverifikasi. Pernyataan “tidak mengirim pesan” dan batas bukti pagination di bawah merujuk pada audit awal, sebelum follow-up tersebut. Sender Shopdash sendiri tetap belum diperbaiki atau diuji kirim.

## Kesimpulan

Chat tidak masuk mempunyai beberapa penyebab yang terbukti: seluruh jadwal chat nonaktif, worker tidak mengisi riwayat pesan, dan empat toko ditolak endpoint daftar chat walaupun bootstrap berhasil. Balasan terhambat oleh penguncian UI berdasarkan `closed`; request kirim juga berbeda dari capture berhasil. Ada masalah lebih mendasar: daftar lintas toko disimpan tanpa memeriksa identitas toko pada setiap percakapan.

Urutan perbaikan harus dimulai dari identitas toko, dilanjutkan transport dan izin chat, pengisian pesan, lalu perilaku UI dan pengaktifan sinkronisasi. Mengaktifkan jadwal saja akan menjalankan kembali importer yang masih mencampur percakapan.

Tidak ada pesan pelanggan yang dikirim, status baca/percakapan yang diubah, jadwal yang diaktifkan, atau data database yang diperbaiki selama audit. Probe upstream hanya membuat sesi chat dan membaca daftar/riwayat menggunakan client yang ada. Proses web pada port 8123 terverifikasi memakai checkout `shopdash`; audit tidak mengganti proses tersebut.

## Bukti dan batas observasi

- XYZ Sniper MCP `initialize`, `tools/list`, `get_projects`, `get_sessions`, `get_endpoints`, dan `get_payloads`; project **1, seller shopee**. Token tidak disimpan di repo.
- Payload chat yang diperiksa tidak mempunyai `session_id`/`sequence_no`. Dua sesi yang terdaftar hanya memiliki satu event masing-masing dan tidak memetakan workflow chat. Karena itu urutan antar-request merupakan korelasi waktu dan identitas percakapan, bukan trace sesi lengkap.
- Capture bertanggal 26–29 September; contoh send berhasil hanya satu, berasal dari 27 September. Capture adalah bukti kontrak yang pernah berhasil, bukan jaminan replay server saat ini.
- SELECT langsung database pada 15:58:37 UTC (22:58:37 WIB), dilengkapi pembacaan agregat pemetaan toko sesudahnya. Angka merupakan snapshot saat audit.
- Probe client asli terhadap tujuh toko tanpa pemanggilan `ChatMonitor::syncShop`, sehingga tidak melakukan upsert lokal. Satu probe riwayat pada percakapan dengan toko yang cocok.
- Chromium menjalankan template PHP chat dari worktree dengan respons sintetis dan seluruh request dicegat. Temuan UI di bawah direproduksi tanpa mengirim ke Shopee. Ini bukan uji visual penuh halaman produksi.
- Payload mentah dan harness sementara berada di direktori privat `/tmp/shopdash-chat-audit`, tidak dikomit. Dokumen hanya memuat agregat, nama field, dan ID rekaman MCP; tidak memuat cookie, token, isi chat, atau identitas pelanggan.

## Temuan menurut prioritas

### F1. P0: percakapan lintas toko masuk ke toko yang salah

**Terbukti melalui kode, data lokal, dan probe upstream.** Endpoint `mini/subaccount/serving_mode/conversations` dapat mengembalikan percakapan beberapa toko dalam cakupan subaccount. `ChatMonitor::syncShop` menyimpan setiap hasil menggunakan `$shopId` pemanggil, tanpa memeriksa `conversation.shop_id`. Sumber: [ChatMonitor.php](../app/models/ChatMonitor.php), baris 193–215 dan 119–151.

Probe halaman pertama mengembalikan 50 percakapan untuk toko lokal 1 dan 2. Hanya 37 baris cocok dengan remote shop toko 1, dan 12 cocok dengan remote shop toko 2. Bootstrap keduanya cocok dengan identitas toko yang diminta; masalah terjadi pada cakupan daftar dan importer, bukan bootstrap tertukar dalam probe ini.

Database mengandung **56 remote conversation ID yang sama pada kedua toko lokal**:

| Disimpan pada toko lokal | Milik toko lokal 1 menurut payload | Milik toko lokal 2 menurut payload | Remote shop belum terpetakan |
| --- | ---: | ---: | ---: |
| 1 | 44 | 11 | 1 |
| 2 | 44 | 11 | 1 |
| 5 | 0 | 0 | 0 |

Toko 5 memiliki 50 baris dan semuanya cocok dengan toko 5. Total mismatch terhadap toko penyimpan adalah 12 pada toko 1 dan 45 pada toko 2, termasuk masing-masing satu remote shop belum terpetakan. Baris yang belum terpetakan tidak boleh diasumsikan sebagai toko tertentu atau percakapan sistem.

**Dampak:** filter toko dan jumlah chat tidak dapat dipercaya. `send()` memakai cookie/bootstrap toko lokal dan buyer/conversation dari baris tersimpan tanpa validasi remote shop. Ada risiko request balasan membawa pasangan toko dan percakapan yang salah. Audit tidak membuktikan pesan pernah terkirim ke pihak yang salah.

**Rencana:** validasi tiga identitas: `shops.id` lokal, `shops.shop_id` remote, dan `conversation.shop_id`. Tolak pengiriman jika pemetaan tidak cocok. Untuk importer, pilih satu strategi eksplisit: filter per toko dari feed subaccount, atau ingest satu feed lalu distribusikan menggunakan remote shop ID dan cakupan otorisasi. Cursor harus mengikuti cakupan feed yang dipakai. Karantina baris ambigu; siapkan rekonsiliasi idempotent atas data lama sebelum mengaktifkan sender.

### F2. P1: sinkronisasi chat nonaktif, tombol Perbarui hanya membaca cache

**Terbukti pada runtime.** Semua tujuh `sync_schedules` chat memiliki `enabled=0`; semua jadwal orders `enabled=1`. Chat toko 1, 2, 5 terakhir sukses 01:11:05 UTC; toko 3, 4, 6, 7 mencatat error sekitar 00:58 UTC. Tidak ada job chat queued/running saat pemeriksaan agregat.

Ini bukan bukti scheduler rusak: [catatan sebelumnya](sync-improvements-20260929.md) menjelaskan pause berdasarkan instruksi pengguna untuk memprioritaskan pesanan. Audit mempertahankan keputusan itu.

`loadOverview`, tombol Perbarui, dan interval 30 detik hanya meminta `/overview` dan `/conversations` tanpa `refresh=1`. Controller default-nya membaca lokal. Uji Chromium mengonfirmasi pola request yang sama pada tombol dan interval. Sumber: [chat.php](../app/views/panel/chat.php) baris 174–184, 200, 213; [ProcChat.php](../app/controllers/back/ProcChat.php) baris 22–40.

**Rencana:** tampilkan status jadwal nonaktif secara eksplisit. Aksi sinkronisasi harus mengantrekan pekerjaan terbatas melalui queue yang ada, mengembalikan status queued/running/failed, dan tidak menjalankan seluruh sync di request halaman. Pengaktifan ulang menjadi langkah rollout terpisah setelah F1, F3, F4 ditangani.

### F3. P1: tidak ada jalur aktif untuk mengisi dan memperbarui riwayat pesan masuk

**Terbukti melalui call chain.** Worker chat hanya menjalankan `syncShop`, yang mengisi `chat_conversations` dan snapshot. `ProcChat::messages()` memanggil `messages(..., false)`, mematikan bagian pengambilan riwayat. Polling UI tidak memanggil `loadMessages()` untuk percakapan yang terbuka.

Sumber: [SyncWorkerTasks.php](../app/helpers/SyncWorkerTasks.php) baris 283–286; [ProcChat.php](../app/controllers/back/ProcChat.php) baris 43–50; [ChatMonitor.php](../app/models/ChatMonitor.php) baris 369–393; [chat.php](../app/views/panel/chat.php) baris 186–191, 213.

Database hanya memiliki 18 pesan: 9 outgoing di toko 1, 4 incoming + 5 outgoing di toko 2. Toko 5 memiliki 50 percakapan tanpa riwayat pesan lokal. Angka ini tidak membuktikan jumlah pesan sebenarnya di Shopee. Probe riwayat pada satu percakapan milik toko 1 berhasil HTTP 200 dengan array berisi 15 pesan.

**Rencana:** pekerjaan detail per `(local_shop_id, remote_conversation_id)`, deduplikasi message ID, fetch saat conversation terbaru berubah serta prioritas percakapan terbuka, checkpoint/freshness tersendiri. UI membaca hasil lokal dan menjadwalkan refresh yang diperlukan. Daftar percakapan dan riwayat pesan harus mempunyai status sinkronisasi berbeda.

### F4. P1: akses daftar chat ditolak pada empat toko, bukan seluruh sesi toko kedaluwarsa

**Terbukti melalui probe saat audit.** Bootstrap chat berhasil dan remote shop cocok pada semua tujuh toko. Daftar chat toko 3, 4, 6, 7 tetap mengembalikan HTTP 403, `user_is_forbiddenUser is forbidden for this action`. Daftar toko 1, 2, 5 berhasil. Orders pada ketujuh toko mempunyai data detail dan keberhasilan sync yang lebih baru pada hari audit.

**Yang belum diketahui:** penyebab spesifik 403, misalnya cakupan izin subaccount atau konteks serving-mode. Respons tidak cukup untuk memilih penyebab. Keberhasilan order tidak membuktikan izin chat; bootstrap chat juga tidak membuktikan izin list/send.

**Rencana:** pisahkan status authenticated, list allowed, history allowed, dan send capability. Cocokkan toko serta subaccount yang sama di Seller Centre dan capture gagal/berhasil yang relevan. Jangan menandai semua fitur toko expired atau mengulang login untuk forbidden tanpa klasifikasi. Jangan mengganti transport atau menambah header atas dugaan.

### F5. P1: UI menganggap `closed` berarti tidak boleh membalas

**Penguncian terbukti di Chromium.** `renderMessages()` men-disable textarea dan tombol hanya dari `conversation.status === 'closed'`. Sumber: [chat.php](../app/views/panel/chat.php), baris 155–163.

MCP payload 3733 mencatat sebuah percakapan sebagai `closed`; payload open 3752 untuk percakapan yang sama memiliki `is_chat_availiable=true`, `conv_is_closed=true`; payload send 3767 berhasil HTTP 200 dan mengembalikan ID pesan; payload 3769 menampilkan percakapan tersebut sebagai `activated`. Ada capture `enter`, `open`, dan PUT status di sekitar alur itu. Ketiadaan sequence sesi membatasi klaim urutan dan kebutuhan setiap langkah.

**Rencana:** jangan menjadikan status daftar sebagai satu-satunya izin membalas. Pisahkan status inbox, ketersediaan chat, izin, blocked, dan closed reason. Verifikasi kebutuhan open/activate/enter pada alur yang benar sebelum mengimplementasikannya; jangan membuka/menandai dibaca semua percakapan secara otomatis. `markRead()` sekarang hanya mengubah unread lokal, bukan status lokal menjadi activated, sehingga refresh lokal tetap dapat mengunci composer.

### F6. P1: kontrak kirim/status berbeda dari capture berhasil

**Mismatch terbukti; penyebab pasti kegagalan send belum direproduksi.** Audit tidak mengirim pesan sungguhan.

| Bagian | Capture send 3767 / status 3762 | Kode saat ini |
| --- | --- | --- |
| Region query dan header | `ID` | Send dan markRead mewarisi `GLOBAL` dari bootstrap; hanya getMessages mengganti ke message region |
| Query send | Ada `uuid`, format panjang 36 | Tidak dikirim |
| Request identifiers | `request_id` dan `content.uid` panjang 36 | Hex acak panjang 32 |
| `re_policy` | `dfp_access_f` | `dfp_access`, opsional dari cookie dengan nama itu |
| Header konteks send | `af-ac-enc-dat`, `x-sz-sdk-version`, `x-sap-ri`, `x-sap-sec`, `af-ac-enc-sz-token` | Dicari sebagai nama cookie |

Sumber: [ShopeeChat.php](../app/models/ShopeeChat.php), baris 76–105, 111–135, 176–218. Pada tujuh bootstrap audit, `security_headers` kosong dan `dfp_access` tidak tersedia. [Ekstensi](../Sellerio%20Get%20Cookies/popup.js) baris 44–61 mengekspor cookies dan user-agent; jalur itu tidak menangkap header per-request tersebut.

**Rencana:** kontrak request terpusat per operasi, region dari conversation/session yang sesuai, identifier dan nama field berdasarkan bukti. Catat nama/header presence saja. Jangan menganggap semua header capture wajib, jangan membuat signature palsu, dan jangan menyimpan/replay header temporer sebagai konfigurasi permanen. Kebutuhan `uuid`, anti-fraud context, masa berlaku, serta transport backend-vs-browser harus dipastikan dengan pengujian terkendali sebelum memilih solusi. Pertahankan syarat remote message ID sebelum status sent.

### F7. P1: respons balapan dan submit ganda

**Terbukti dengan respons sintetis di Chromium.** Memilih A lalu B, sementara respons A terlambat, membuat UI menampilkan A walaupun selection state sudah B. Pada fixture A closed, composer ikut terkunci. Tidak ada request generation guard atau abort terhadap respons lama. Handler send membaca selection state saat submit; draft juga tidak dipisahkan per percakapan.

Menekan Enter dua kali sebelum respons send selesai menghasilkan **dua POST send** pada mock. Disable tombol tidak cukup karena keydown memanggil `requestSubmit()` dan handler tidak memeriksa `sending`. Backend membuat request ID baru pada setiap request lokal. Sumber: [chat.php](../app/views/panel/chat.php) baris 149, 186–198, 210–211; [ChatMonitor.php](../app/models/ChatMonitor.php) baris 404–410.

**Rencana:** selection key `(shop_id, conversation_id)`, generation/abort guard, draft per key, state sending yang dicek semua jalur, idempotency key stabil dari satu intent kirim sampai rekonsiliasi. Respons dari percakapan lama tidak boleh menimpa detail, mark-read target, composer, atau draft baru. Timeout ambigu harus direkonsiliasi sebelum retry; kesamaan request ID pada satu retry internal belum memberikan idempotensi lintas request UI.

### F8. P2: status UI menyatakan data baru walaupun stale/gagal

**Terbukti di Chromium.** Fixture dengan `status=error`, `stale=true`, dan error forbidden tetap menampilkan “Data live chat diperbarui” dengan jam browser. Banner hanya menampilkan `session_expired`, bukan error channel atau schedule nonaktif. Model sudah menyediakan `last_sync_at`, `error_message`, `stale`, tetapi UI tidak memakainya. Label “Toko aktif” memakai `totals.active_count`, yang sebenarnya jumlah percakapan activated.

Sumber: [chat.php](../app/views/panel/chat.php) baris 40, 113, 118–121, 176; [ChatMonitor.php](../app/models/ChatMonitor.php) baris 291–333. `last_sync_at` juga diubah saat error oleh `saveError()`, sehingga perlu memisahkan last attempt dan last success untuk freshness yang benar.

**Rencana UX:** loading, empty yang benar, cache lama, sinkronisasi dijeda, izin ditolak, riwayat belum dimuat, sending, confirmed sent, failed, dan ambiguous harus dibedakan. Tampilkan sebab dan tindakan pemulihan dekat panel/composer. Pertahankan draft saat gagal. Jangan mengarahkan semua error menjadi “perbarui cookie”.

### F9. P2: pagination dan validasi respons belum lengkap

`syncShop()` meminta satu halaman initial older lalu beralih latest dari nilai maksimum ID/time; tidak ada traversal older atau checkpoint histori. Capture initial 3733 berisi 50 baris, sehingga semua histori belum dapat dinyatakan terambil. `messages()` membaca 200 pesan paling awal dengan ASC LIMIT 200; thread panjang berisiko tidak menampilkan pesan terbaru. Daftar lokal dibatasi 100 tanpa pagination.

`ShopeeChat::result()` menganggap HTTP 2xx JSON sebagai sukses tanpa validasi bentuk/error aplikasi; respons yang tidak memiliki `conversations` menjadi array kosong dan snapshot ok. Ini terbukti dari kode, tetapi audit tidak menemukan contoh 2xx error tersebut dalam capture yang diperiksa. `messages()` juga dapat menyembunyikan kegagalan refresh non-expired dengan mengembalikan cache success.

Sumber: [ChatMonitor.php](../app/models/ChatMonitor.php) baris 205–228, 351, 369–393; [ShopeeChat.php](../app/models/ShopeeChat.php) baris 144–173, 221–231. Rencana: validasi schema per endpoint, cursor string tanpa konversi Number JavaScript, histori dan delta checkpoint terpisah, guard cursor tidak maju, page terbaru dahulu lalu pagination older. Kontrak halaman kedua Shopee masih perlu capture; jangan mengarang sentinel dari API orders.

## Mapping endpoint dan payload

Prefix chat biasa: `https://seller.shopee.co.id/webchat/api/v1.2`. Prefix bootstrap: `https://seller.shopee.co.id/webchat/api/coreapi/v1.2`.

| Operasi / bukti MCP | Request teramati | Respons teramati / pemetaan |
| --- | --- | --- |
| Bootstrap, endpoint 784; payload 1401, 11679, 11680 | POST `/mini/login/sc`; query `csrf_token`, `source=sc`, `_api_source=sc`; varian 11679 menyertakan konteks sesi | Objek top-level `token`, `user.id`, `user.uid`, `user.type=subaccount`, `shop.id`, `shop.user_id`, `shop.country`; bukan wrapper `data` |
| Daftar, endpoint 1250; payload 3733, 3769, 4489 | POST `/mini/subaccount/serving_mode/conversations`; bearer, region GLOBAL; initial `{direction:older,biz_id:2,on_message_received:true,next_timestamp_nano:"0"}`; delta latest + `last_message_region`, `last_received_message_id`, `next_timestamp_nano` | `conversations[]`, `attributions`, `ShopIds`; `id` remote conversation string; `shop_id` toko pemilik; `to_id` lawan bicara; `latest_message_*` preview, bukan histori |
| Filter unread, endpoint 1250; payload 13084, 13085 | Initial older dengan `type=unread`; varian lanjutan menyertakan last-message fields | 3 dan 0 conversations. Tidak digunakan sebagai bukti total semua percakapan |
| Riwayat, endpoint 1260; payload 3758 | GET `/mini/conversations/{id}/messages`; region ID; `shop_id`, `offset=0`, `limit=20`, `direction=older`, `biz_id=2`, `on_message_received=true` | Array pesan top-level; `id`, `conversation_id`, `shop_id`, `from_id`, `to_id`, `from_shop_id`, `to_shop_id`, `type`, `content`, `created_at`, `status` |
| Enter, endpoint 1252; payload 3750 | POST `/mini/conversation/enter`; `biz_id`, `conversation_id`, `to_id`, `entry_point`, `choice_info` | Konteks session/chatbot; kebutuhan operasionalnya belum dibuktikan dengan isolasi |
| Open, endpoint 1254; payload 3752, 3801 | POST `/mini/conversation/open`; `biz_id`, `oppside_user_id`, `shop_id`, `to_shop_id`, `conversation_id` | `is_chat_availiable` (ejaan upstream), `conv_is_closed`, `conv_closed_reason`, `show_close_conv_btn` |
| Kirim, endpoint 1266; payload 3767 | POST `/mini/messages`; region ID; query `uuid`; body lihat F6 | HTTP 200, objek top-level `id`, `request_id`, `to_id`, `type`, `content`, `conversation_id`, `created_at`. Contoh ini tidak memuat `from_id`; normalization outgoing perlu konteks sender tervalidasi |
| Status, endpoint 1263; payload 3762 | PUT `/mini/conversations/{id}/status`; region ID; `{shop_id,status:activated,biz_id:2}` | HTTP 204, tanpa body. Jangan menggantinya dengan asumsi endpoint read-receipt lain |

Query bersama pada operasi chat mencakup `_uid`, `_v=9.1.17` pada capture, `csrf_token`, `SPC_CDS_CHAT`, `x-shop-region`, `_api_source=sc`. Nilai sensitif tidak dicantumkan. `uuid` pada capture berbeda dari `request_id`; jangan menyamakan keduanya tanpa bukti.

## Pelajaran dari alur pesanan yang berjalan

MCP endpoint 1326, payload 10089, cocok dengan `ShopeeCurl::getOrderIndexList`: POST `/api/v3/order/search_order_list_index`, query `SPC_CDS` dan `SPC_CDS_VER=2`, body `order_list_tab=100`, `entity_type=1`, pagination page size 40, filter fulfillment/action, sort type 3 descending. Respons menggunakan `code`, `data.index_list`, `data.pagination`.

`processIndex()` memvalidasi respons, menyimpan halaman/sentinel, menolak cursor tidak maju, dan membuat pekerjaan detail. `processOrder()` mengambil `/api/v3/order/get_one_order`, menyimpan hasil, dan menyediakan retry/freshness per detail. Endpoint detail ini ada di source dan runtime detail orders bergerak; pencarian MCP project 1 tidak menemukan capture `get_one_order`, sehingga bentuk detail tidak diklaim diverifikasi melalui MCP.

Yang perlu diadopsi chat: discovery dan detail terpisah, identitas eksplisit, queue durable, cursor/checkpoint, retry terklasifikasi, status keberhasilan nyata, serta UI yang membaca database. Yang tidak boleh disalin: cookie/query auth order sebagai pengganti bearer chat, wrapper `data`, sentinel order, atau asumsi satu response hanya berisi satu toko. Sumber: [ShopeeCurl.php](../app/models/ShopeeCurl.php) baris 541–576, 610–622; [SyncWorkerTasks.php](../app/helpers/SyncWorkerTasks.php) baris 88–163.

## Rencana implementasi berurutan

| Tahap | Pekerjaan | Syarat selesai |
| --- | --- | --- |
| 1. Lindungi identitas | Guard read/send/mark-read; normalizer feed subaccount; pemetaan remote shop; laporan rekonsiliasi data lama | Fixture campuran tiga toko tidak masuk ke toko pemanggil secara membabi buta; mismatch ditolak; remote shop tidak dikenal dikarantina |
| 2. Bekukan kontrak dan diagnosis | Fixture tersanitasi dari MCP; request builder per operasi; klasifikasi bootstrap/list/history/send; pemeriksaan izin toko 3/4/6/7 | Request field/region cocok bukti; read probe berhasil pada toko yang diizinkan; forbidden tidak berubah menjadi expired global; kebutuhan header dinyatakan terbukti atau belum diketahui |
| 3. Rekonsiliasi dan sinkronisasi detail | Backup terkontrol, dry-run pemetaan, perbaikan data deterministik; discovery/detail jobs; histori/delta cursors; freshness | Tidak ada baris lintas toko; rerun idempotent; percakapan dan pesan baru masuk lokal; halaman kedua terverifikasi sebelum backfill penuh |
| 4. Balasan dan state UI | Capability-aware composer; open/activate sesuai hasil verifikasi; draft per thread; guard respons lama; idempotensi dan rekonsiliasi ambiguous | Closed-list tidak otomatis dianggap tidak boleh kirim; Enter ganda satu intent; respons lambat tidak mengubah thread lain; draft bertahan saat gagal |
| 5. UX dan observabilitas | Status paused/stale/error terpisah, jam sukses asli, perbaikan label metrik; structured diagnostics tanpa payload sensitif | Operator mengetahui apakah data lama, izin ditolak, atau pesan gagal; tidak ada klaim berhasil palsu |
| 6. Rollout terbatas | Satu toko yang lolos identitas/list/history, aktifkan jadwal secara sadar; pantau queue dan delta; perluas setelah lolos | Bukti pesan masuk baru, detail terbaca, dan balasan terkonfirmasi untuk target uji yang ditentukan; orders tidak mengalami regresi |

Perubahan data dan aktivasi jadwal tidak termasuk pekerjaan audit ini. Pengiriman uji sungguhan memerlukan target dan teks yang secara eksplisit diotorisasi; capture sukses lama atau mock bukan bukti send produksi sudah pulih.

## Verifikasi yang harus menyertai perbaikan

- PHP fixture tanpa Shopee: feed campuran toko, bootstrap mismatch, remote shop unknown, ID/nanotime string panjang, 200 malformed/error, 403 forbidden, 401 expired, 204 kosong, pagination tidak maju, retry/detail checkpoint.
- Temporary-table integration: perbaikan data idempotent, isolasi toko, deduplikasi message ID, sender direction memakai konteks toko yang benar, 201+ pesan menampilkan halaman terbaru.
- Browser mock: initial/loading/empty/stale/paused/forbidden, closed versus capability, rapid A/B switch, draft per thread, Enter ganda, timeout ambiguous, polling thread terbuka, mark-read failure, refresh queue status, label metrik.
- UI mengikuti identitas Shopdash, menggunakan antislop R-26/R-27/R-32/R-36 serta UI UX Pro Max. Pencarian lokal `async error feedback --domain ux` menghasilkan Loading States, Error Recovery, Error Feedback yang relevan. Fokus perbaikan adalah umpan balik tindakan dan keadaan data, bukan redesign dekoratif.
- Sesudah implementasi UI: 320/500/999/1600 px, light/dark, keyboard/focus, popup Escape dan pengembalian fokus, target sentuh 44 px, keyboard mobile tidak menutup composer. Audit ini belum menjalankan matriks visual tersebut dan tidak menyatakan UI lolos seluruh gate.
- Probe live bertahap dan terukur, dengan per-shop error/status/count teredaksi. Jangan menyalakan semua chat untuk membuktikan satu perbaikan. Jangan mengulang send ambigu secara otomatis.

## Hasil verifikasi audit

1. MCP discovery dan pembacaan payload berhasil; field dipetakan dari rekaman, bukan dokumentasi API yang diasumsikan.
2. SELECT runtime mengonfirmasi pause jadwal, sedikitnya riwayat lokal, dan duplikasi lintas toko.
3. Bootstrap tujuh toko berhasil; list tiga toko HTTP 200 dan empat toko HTTP 403; satu history probe HTTP 200.
4. Chromium terisolasi mengonfirmasi closed lock, status freshness menyesatkan, refresh/poll cache-only, respons A menimpa B, dan dua POST mock dari dua Enter; tidak ada JavaScript page error dalam skenario itu.
5. Tidak ada klaim bahwa send produksi telah diperbaiki atau berhasil diuji. Tidak ada perubahan source aplikasi, schema, runtime, jadwal, maupun penggabungan branch agent lain.
