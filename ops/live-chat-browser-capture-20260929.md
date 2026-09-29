# Rekaman langsung browser: Shopee Chat

29 September 2026, sekitar 23:09–23:15 WIB. Follow-up atas [audit awal](live-chat-audit-20260929.md). Worktree `fahra-kelola-chat-audit`, branch `audit/shopee-live-chat`.

## Yang benar-benar dilakukan

Chrome dikendalikan melalui computer use (`cua_repl`), menggunakan tab Seller Centre yang sudah login. Browser-tab provider tidak tersedia pada sesi ini; interaksi dilakukan melalui accessibility tree dan screenshot Chrome. XYZ Sniper extension diperiksa melalui toolbar: project aktif `seller shopee`, status **Audit aktif**. Rekaman dibaca melalui XYZ Sniper MCP project 1 menggunakan `get_projects`, `get_sessions`, `get_capture_stats`, `get_endpoints`, dan `get_payloads`.

1. Membuka Webchat penuh dari mini chat. Daftar menampilkan beberapa toko dan beberapa negara dalam akun yang sama.
2. Membuka satu percakapan toko Indonesia berstatus Ditutup. UI menampilkan histori dan tombol **Chat Lagi**, belum menampilkan composer.
3. Menekan Chat Lagi. Composer muncul, kapasitas berubah dari 3/100 menjadi 4/100, dan Shopee menambahkan notifikasi sistem bahwa agent bergabung. Ini merupakan perubahan status percakapan, bukan observasi read-only. Tidak ada teks pesan yang diketik pada langkah ini.
4. Kembali ke Seller Centre mini chat, mengubah filter Belum Dibaca menjadi Semua, membuka percakapan yang sama, dan membaca riwayat.
5. Menggulir daftar percakapan untuk memicu pengambilan halaman berikutnya. Menggulir histori pesan ke atas; sampel histori hanya 19 pesan, sehingga tidak membuktikan pagination histori pesan.
6. Setelah pengguna menjawab **“boleh pake mana aja bebas saya ijinkan”** atas permintaan izin penerima dan teks uji, mengirim tepat satu pesan melalui mini chat: **“Mohon abaikan pesan ini, kami sedang menguji fitur chat toko. Terima kasih.”**
7. Memastikan pesan muncul di mini chat, lalu memeriksa tab Webchat penuh. Pesan yang sama juga muncul di histori dan preview tab tersebut pada 23:13.

Tidak ada pengiriman melalui endpoint backend Shopdash, tidak ada pesan uji kedua/retry, dan tidak ada perubahan source aplikasi atau jadwal sync. Percakapan yang dibuka kembali dibiarkan aktif; tidak ditutup ulang. Identitas pembeli, nomor pesanan, token, cookie, signature/header values, dan remote IDs tidak dipublikasikan.

## Bukti MCP yang diambil sesudah interaksi

Metadata `last_seen` di bawah disalin sebagaimana MCP mengembalikannya. Endpoint `updated_at` tidak selalu maju walaupun hit baru masuk; gunakan payload `last_seen`, hit count, dan korelasi request/response. Capture ini masih memiliki `session_id=null`; sesi bernama baru tidak muncul pada `get_sessions`. Jangan menyajikannya sebagai trace dengan sequence ID lengkap.

| Aksi | Endpoint ID / payload ID | Bukti |
| --- | --- | --- |
| Daftar awal | 1250 / 3733, 13337 | POST `/webchat/api/v1.2/mini/subaccount/serving_mode/conversations`, HTTP 200; `last_seen=23:11:53`; masing-masing 50 percakapan |
| Daftar halaman berikutnya | 1250 / 13372 | POST endpoint sama, HTTP 200; `last_seen=23:13:12`; 50 percakapan, tidak tumpang tindih dengan halaman 13337 |
| Enter percakapan | 1252 / 13343 | POST `/webchat/api/v1.2/mini/conversation/enter`, HTTP 200; `last_seen=23:12:15`; query region GLOBAL |
| Open percakapan | 1254 / 13346 | POST `/webchat/api/v1.2/mini/conversation/open`, HTTP 200; `last_seen=23:12:15`; query region ID; `is_chat_availiable=true`, `conv_is_closed=false` |
| Riwayat | 16940 / 13351 | GET `/webchat/api/v1.2/mini/conversations/{conversation_id}/messages`, HTTP 200; `last_seen=23:12:15`; array top-level 19 pesan |
| Kirim uji | 1266 / 13382 | POST `/webchat/api/v1.2/mini/messages`, HTTP 200; `last_seen=23:13:32`; remote message ID tersedia, request ID dan teks cocok antara request dan response |

Project capture count bertambah dari 6.093 menjadi 6.213 payload dan dari 11.203 menjadi 11.345 hits selama jendela kerja. Angka ini mencakup traffic latar belakang dan endpoint pendukung, bukan jumlah request chat yang seluruhnya dapat diatribusikan pada aksi audit.

## Kontrak pagination daftar yang kini terbukti

Payload halaman awal 13337:

```json
{
  "direction": "older",
  "biz_id": 2,
  "on_message_received": true,
  "last_message_region": "ID",
  "next_timestamp_nano": "0"
}
```

Payload halaman berikutnya 13372:

```json
{
  "direction": "older",
  "biz_id": 2,
  "on_message_received": true,
  "last_message_region": "ID",
  "last_received_message_id": "<latest_message_id dari baris terakhir halaman sebelumnya>",
  "next_timestamp_nano": "0"
}
```

Perbandingan nilai mentah di memori menunjukkan cursor request kedua persis sama dengan `latest_message_id` indeks 49 pada halaman sebelumnya. Bukan `conversation.id`. Kedua halaman tidak mempunyai conversation ID yang sama dan masing-masing memuat tiga remote shop ID berbeda. Ini membuktikan satu langkah pagination dan cakupan lintas toko untuk sampel tersebut, bukan seluruh backfill lintas negara.

Implikasi untuk [ShopeeChat.php](../app/models/ShopeeChat.php): `listConversations()` sekarang otomatis mengganti direction menjadi latest ketika last-message ID terisi. Discovery older dan polling latest harus menjadi pilihan eksplisit; jangan memakai aturan itu untuk pagination histori daftar. Pertahankan cursor sebagai string. Field `next_timestamp_nano` pada sampel pagination ini tetap `"0"`, bukan maksimum timestamp halaman.

## Kontrak riwayat dan pembukaan

History 13351 memakai query `shop_id`, `offset=0`, `limit=20`, `direction=older`, `biz_id=2`, `on_message_received=true`, `_uid`, `_v=9.1.17`, `csrf_token`, `SPC_CDS_CHAT`, `x-shop-region=ID`, `_api_source=sc`.

Enter 13343 memiliki body `biz_id`, `conversation_id`, `to_id`, `entry_point`, `choice_info`. Open 13346 memiliki body `biz_id`, `oppside_user_id`, `shop_id`, `to_shop_id`, `conversation_id`. Region enter dan open berbeda pada capture yang sama; jangan menerapkan region GLOBAL atau ID untuk semua operasi tanpa membedakan endpoint.

**Batas bukti Chat Lagi:** perubahan UI Ditutup menjadi composer aktif teramati langsung pada Webchat penuh. Capture open di mini chat diambil sesudah perubahan itu dan menunjukkan `conv_is_closed=false`. Audit belum mengisolasi request HTTP atau frame socket yang khusus menyebabkan aktivasi pada Webchat penuh. Jangan menyimpulkan bahwa POST open saja pasti membuka ulang, atau bahwa urutan enter/open/status seluruhnya wajib, dari bukti ini.

Bootstrap fresh tidak teridentifikasi dalam jendela capture yang diperiksa; browser sudah mempunyai sesi. Keberhasilan login tetap bersumber dari probe audit awal, bukan klaim ada login baru pada rekaman ini.

## Kontrak send terbaru yang berhasil

Payload 13382 berasal dari pesan uji yang benar-benar dikirim setelah izin pengguna, bukan capture historis yang dipakai ulang.

```text
POST /webchat/api/v1.2/mini/messages
Authorization: Bearer <session chat>
x-shop-region: ID

query keys:
_uid, _v, csrf_token, SPC_CDS_CHAT, x-shop-region, _api_source, uuid

body keys:
request_id, to_id, type, content, shop_id, chat_send_option,
source_content, entry_point, choice_info, biz_id,
conversation_id, source, re_policy
```

Detail yang diperiksa:

- `type=text`, `source=minichat`, `entry_point=direct_chat_entry_point`, `biz_id=2`.
- `content` mempunyai `text` dan `uid`; `source_content=[]`; `choice_info.real_shop_id=null`.
- `request_id` panjang 36; query `uuid` berbeda dari `request_id`.
- `re_policy` mempunyai key `dfp_access_f`.
- Header yang tercatat: Accept, Content-Type, Authorization, x-shop-region, af-ac-enc-dat, x-sz-sdk-version, x-sap-ri, x-sap-sec, af-ac-enc-sz-token, user-agent. Nilai sensitif tidak disalin.
- Respons objek top-level memuat `id`, `request_id`, `to_id`, `type`, `content`, `conversation_id`, `created_timestamp`, `created_at`, `source_content`, `source_type`, `message_option`, `to_id_status`, `msg_tag`, `waring_tip_types`.
- HTTP 200 dan `id` nonkosong; `request_id` sama; `content.text` sama; pesan terlihat di dua tampilan Shopee. Ini bukti pesan diterima Shopee, bukan bukti pembeli telah membacanya.

Mismatch dengan source Shopdash tetap: send memakai region GLOBAL bawaan, tidak menyertakan query uuid, membuat request ID hex panjang 32, memakai `re_policy.dfp_access`, dan tidak menyertakan `source_content` pada body. Perbedaan teramati ini harus masuk fixture kontrak. Capture berhasil tidak membuktikan semua field/header tersebut wajib, maupun bahwa menyalinnya cukup membuat send via PHP berhasil. Signature temporer tidak boleh dijadikan nilai statis.

## Perubahan pada rencana perbaikan

1. Tetap prioritaskan pemetaan toko: capture dua halaman baru menegaskan feed lintas toko. Jangan mengaktifkan sync lama sebelum guard identitas tersedia.
2. Implementasikan pagination older dengan cursor message ID yang terverifikasi, terpisah dari latest polling. Verifikasi country/region selain sampel ID sebelum memperluasnya.
3. Implementasikan pengambilan detail pesan melalui queue. History pagination setelah 20 pesan masih memerlukan sampel lebih panjang.
4. Modelkan status Ditutup dan aksi Chat Lagi sebagai alur tersendiri. Isolasi kontrak aktivasi sebelum menggantinya dengan operasi yang diasumsikan.
5. Gunakan capture 13382 untuk fixture request/response send; tambahkan guard satu intent satu pengiriman serta rekonsiliasi ambiguous. Uji transport backend pada target terkontrol setelah perbaikan, bukan menyebut keberhasilan browser sebagai keberhasilan Shopdash.
6. Diagnosis 403 pada empat toko tetap terbuka. Rekaman ini memakai sesi browser yang tersedia dan tidak menguji satu per satu toko yang sebelumnya forbidden.

Dokumen dan semua rujukan lokal diperiksa; tidak ada token, raw payload pelanggan, atau screenshot pelanggan yang dikomit. Tidak ada implementasi yang dinyatakan selesai melalui follow-up perekaman ini.
