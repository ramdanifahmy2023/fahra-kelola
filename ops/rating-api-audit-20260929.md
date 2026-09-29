# Audit API rating Shopee

Tanggal audit: 2026-09-29. Status: audit capture baca-saja, bukan implementasi atau pengujian pengiriman baru.

## Sumber dan batas audit

- Sumber: XYZ Sniper MCP di `http://localhost:8000/mcp`, server mengidentifikasi diri sebagai versi 1.1.0.
- Project dipilih lewat `get_projects`: ID 1, `seller shopee`, domain `https://seller.shopee.co.id`.
- Tools yang digunakan: `get_projects`, `get_endpoints`, `get_sessions`, `get_payloads`. Semua merupakan pembacaan capture.
- Halaman referensi: Seller Centre `/portal/settings/shop/rating`, termasuk filter bintang 4 dan 5 dari URL pengguna.
- Ditemukan 1 payload dashboard, 10 payload daftar rating, dan 2 payload pengiriman balasan. Capture berada pada 2026-09-29 sekitar 12:54–12:56 menurut timestamp server capture; timezone timestamp tersebut belum diverifikasi.
- Payload rating memiliki `session_id`, `sequence_no`, dan identitas navigasi bernilai null. Sesi browser yang ditemukan tidak terkait capture rating ini. Karena capture dapat menggabungkan hit, timestamp/urutan ID tidak cukup untuk membuktikan urutan browser yang lengkap.
- Token MCP, nilai parameter autentikasi, header keamanan, identitas pembeli, teks ulasan, nomor pesanan, dan isi balasan asli tidak disalin ke dokumen ini.
- Tidak ada request balasan yang di-replay. Tidak ada cookie toko dipakai untuk uji transport server-side baru. Tidak ada status rating yang diubah oleh audit ini.

## Endpoint teramati

Host seluruh endpoint berikut: `seller.shopee.co.id`.

| Fungsi | Method dan path | Endpoint ID Sniper | Bukti |
| --- | --- | --- | --- |
| Ringkasan rating | `GET /api/v3/settings/get_rating_dashboard/` | 15124 | Capture 12289, HTTP 200, `code: 0` |
| Daftar rating | `GET /api/v3/settings/search_shop_rating_comments_new/` | 15133 | 10 payload, HTTP 200, `code: 0` |
| Balas rating | `POST /api/v3/settings/reply_shop_rating/` | 15171 | Capture 12320 dan 12326, HTTP 200, `code: 0` |

Ini adalah endpoint internal Seller Centre yang teramati, bukan klaim kontrak API publik atau jaminan stabilitas.

## Query daftar dan filter

| Parameter API | Nilai teramati / makna |
| --- | --- |
| `rating_star` | String dipisahkan koma: `5`, `5,4`, `5,4,3`, `5,4,3,2`, `5,4,3,2,1`; filter juga pernah tidak dikirim |
| `reply_status=1` | UI `replied=TO_REPLY`, capture 12300/12306/12309 dan lainnya |
| `reply_status=2` | UI `replied=REPLIED`, capture 12329; seluruh 20 item sampel memiliki reply |
| `reply_status` tidak dikirim | UI `replied=ALL`, capture 12331; sampel memuat campuran item dengan/tanpa reply |
| `page_number` | Semua capture yang tersedia bernilai `1` |
| `page_size` | Semua capture yang tersedia bernilai `20` |
| `cursor` | Semua capture yang tersedia bernilai `0` |
| `from_page_number` | Semua capture yang tersedia bernilai `1` |
| `language` | `id` |
| `time_start`, `time_end` | Timestamp Unix pada capture 12316; semantik batas inklusif dan zona waktu perlu diverifikasi |
| `SPC_CDS`, `SPC_CDS_VER` | Ada pada query autentikasi; nilainya tidak dicatat di sini |

Parameter URL halaman berbeda dengan API: UI memakai `ratingStar` berulang dan `replied`, sedangkan API memakai `rating_star` string koma serta `reply_status` numerik. Jangan menyalin query URL halaman langsung ke endpoint API.

Belum ada bukti halaman 2, pergantian cursor, lompat halaman, akhir pagination, atau ukuran halaman selain 20. Jangan menebak cursor dari `comment_id`, waktu, atau offset. Audit lanjutan harus menangkap navigasi tersebut sebelum backfill dinyatakan lengkap.

## Bentuk respons

Dashboard menyediakan `data.metric` dengan jumlah rating, review rate, good rating rate, dan pembanding sebelumnya; juga `unreplied_bad_ratings`, `new_ratings_received`, `last_visit_time`. Rentang/perumusan metrik tidak disimpulkan hanya dari nama field.

Daftar menyediakan:

- `data.page_info.total`; `page_number` dan `page_size` pada sampel respons bernilai null. Jangan mengandalkan kedua field respons itu sebagai checkpoint.
- `data.list[]` berisi `comment_id`, `order_id`, `order_sn`, `rating_star`, `comment`, `images`, `ctime`, `mtime`, `submit_time`.
- Identitas produk: `product_id`, `item_id`, `model_id`, `product_name`, `model_name`, `product_cover`.
- Identitas pembeli: `user_id`, `user_name`, `user_portrait`; minimalkan penggunaannya dan jangan kirim ke AI jika tidak diperlukan.
- Status tambahan: `is_hidden`, `status`, `can_follow_up`, `follow_up`, `low_rating_reasons`. Makna kode `status` dan aturan follow-up belum terverifikasi.
- `reply` bisa null atau object dengan `comment`, `comment_id`, `ctime`, `is_hidden`.
- `data.counts`: `all_count`, `to_reply_count`, `replied_count`, dan jumlah per bintang. Cakupan count terhadap filter/tanggal belum terbukti.

**Anomali yang mempengaruhi rancangan:** capture 12316 memakai `reply_status=1`, tetapi satu dari 20 item memiliki object reply. Penyebab belum diketahui: perubahan data, penggabungan capture, atau perilaku endpoint mungkin berpengaruh. Worker wajib memeriksa field reply tiap item dan melakukan pembacaan ulang sebelum mengirim. Filter server bukan pengganti pemeriksaan tersebut.

## Payload balasan

Kedua capture POST menggunakan tiga field JSON berikut. Contoh ini memakai placeholder, bukan payload siap dikirim:

```json
{
  "order_id": "<ID numerik pesanan dari item rating>",
  "comment_id": "<ID numerik rating induk dari item rating>",
  "comment": "<teks balasan yang sudah lolos validasi>"
}
```

Pada capture asli, kedua ID berupa angka dan `comment` berupa string. Jangan memakai `reply.comment_id` sebagai ID rating induk. Pertahankan ketepatan integer; simpan remote ID sebagai BIGINT/string desimal di batas JSON/JavaScript sesuai kebutuhan.

Respons sukses teramati:

```json
{"code": 0, "message": "success", "user_message": "success"}
```

Kedua POST menargetkan rating berbeda, keduanya bintang 5. Panjang teks sampel masing-masing 36 dan 71 karakter, **bukan** bukti batas panjang platform. Pada capture daftar berikutnya, kedua rating memiliki teks reply yang cocok dengan request POST. Ini mendukung pola rekonsiliasi setelah pengiriman, tetapi tidak membuktikan operasi idempotent atau dukungan edit.

Belum ada bukti pengiriman pada bintang 1–4, reply duplikat, edit/hapus reply, batas umur rating, karakter maksimum, emoji, atau kegagalan API. Implementasi harus memvalidasi hal-hal tersebut sebelum mengaktifkan cakupan penuh.

## Autentikasi dan transport

Capture browser membawa parameter sesi dan header konteks/keamanan, termasuk keluarga `X-Sap-*`, `sc-fe-*`, serta header tambahan pada POST. Kehadirannya tidak membuktikan setiap header wajib, dan audit ini tidak membuktikan cookie-only PHP cukup.

`ShopeeCurl::request` saat ini menyediakan cookie, JSON, user agent, dan custom header. Jangan menyalin header keamanan dinamis dari capture menjadi konfigurasi statis atau membuat nilai pengganti. Validasi transport terautentikasi harus menjadi tahap tersendiri.

Tidak terlihat parameter `shop_id` pada payload reply. Identitas toko bergantung pada sesi yang dipakai. Sebelum pengiriman kelak, cocokkan identitas remote shop dari sesi dengan toko lokal yang dijadwalkan. Audit ini belum membuktikan perilaku lintas toko.

XYZ Sniper dipakai sebagai sumber bukti audit, bukan dependensi runtime Automation Engine. Server MCP yang mati tidak boleh menghentikan automasi produksi yang sudah diimplementasikan melalui transport terverifikasi.

## Audit berikutnya yang diperlukan

1. Capture baca-saja: halaman 2/3, kembali halaman 1, akhir daftar, filter tiap bintang, dan urutan data saat ada item baru.
2. Konfirmasi batas reply/eligibility dari UI atau validasi yang dapat diamati tanpa mengirim.
3. Uji baca melalui transport backend pada toko yang identitasnya sudah cocok. Jangan menyimpulkan write berhasil hanya dari GET sukses.
4. Capture contoh reply bintang rendah yang sudah ada, kegagalan, dan edit bila pengguna memang memakai fitur tersebut. Jangan membuat reply publik hanya demi mendapatkan capture.
5. Pilot pengiriman baru hanya sebagai fase implementasi berikutnya yang secara jelas diaktifkan pengguna, dengan teks dan target dapat ditinjau.

Rencana implementasi ada di [Automation Engine](automation-engine-plan.md).
