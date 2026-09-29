# Audit penolakan kirim Shopee dan rencana transport

30 September 2026, sekitar 00.27–00.38 WIB. Audit pada branch `audit/shopee-live-chat`, setelah deployment `80fc69c`. Implementasi utama dan batas sebelumnya ada di [hasil implementasi](live-chat-implementation-20260929.md).

## Kesimpulan berdasarkan pengujian

Pengiriman melalui UI mini chat Shopee di Chrome yang sudah login berhasil pada percakapan uji yang sama. Client PHP berhasil login dan membuka percakapan, tetapi endpoint send mengembalikan HTTP 403 dengan `error=90309999`, `is_login=true`, `action_type=2`, tanpa ID pesan. Mengganti CTOKEN dengan nilai terbaru pada request browser dan menyamakan User-Agent belum mengubah hasil.

Ada kandidat solusi yang layak diuji: penghubung yang menjalankan alur kirim asli di sesi browser, atau Chat API resmi bila akun mempunyai izin. Belum ada penghubung otomatis yang dibangun atau bukti bahwa `fetch()` biasa dari browser akan lolos. Keberhasilan UI tidak dipakai sebagai klaim bahwa sender Shopdash sudah pulih.

## Aksi dan bukti baru

- Chrome dikendalikan melalui `cua_repl` computer use, termasuk navigasi halaman, reload Seller Centre, membuka mini chat, memilih percakapan uji, Chat Lagi, dan mengirim satu teks pemberitahuan uji sesuai izin sebelumnya. Provider browser-tab tidak tersedia (`browsers=[]`), sehingga interaksi browser memakai accessibility tree/screenshot native Chrome.
- Popup XYZ Sniper menunjukkan project `seller shopee` dan Audit aktif. Rekaman diambil lewat MCP `tools/list`, `get_endpoints`, `get_payloads`, `get_capture_stats`, dan `get_sessions` pada project 1.
- **Payload send 14023, endpoint 1266**, `last_seen=2026-09-30 00:30:35`: HTTP 200, ID pesan tersedia, request ID, conversation ID dan teks cocok dengan respons. Pesan terlihat di UI. Ini satu pesan tambahan yang berhasil lewat browser pada audit ini; tidak ada retry browser.
- Open terbaru teragregasi pada payload 13346, `last_seen=00:30:16`, HTTP 200 dan `conv_is_closed=false`. Capture status lama 13531 tidak maju, sehingga request aktivasi mini chat pada uji baru tidak dinyatakan telah terisolasi kembali.
- Probe PHP aktual memakai cookie toko lokal 1, owner percakapan terverifikasi, dan tabel sementara per koneksi. Hasil lokal probe tidak ditulis ke tabel bisnis. Urutan yang diamati: bootstrap HTTP 200; open HTTP 200, `is_chat_availiable=true`, `conv_is_closed=false`; send HTTP 403, `90309999`, `is_login=true`, `action_type=2`. Tidak ada ID pesan dan status failed yang pasti, bukan ambiguous.
- Tiga request send PHP dilakukan pada audit ini, seluruhnya ditolak tanpa ID: baseline; eksperimen menambahkan User-Agent browser pada header; kemudian kontrol bersih dengan tepat satu User-Agent browser yang diperiksa melalui `CURLINFO_HEADER_OUT`. Eksperimen tengah tidak digunakan untuk menyimpulkan pengaruh User-Agent karena default transport juga menetapkan header tersebut. Kontrol terakhir memakai CTOKEN terbaru dari query capture dan satu User-Agent yang cocok, tetapi cookie lain tetap berasal dari konfigurasi toko. Ini **bukan** pengujian ekspor ulang seluruh cookie browser.
- Login, probe transport, dan hasil send disimpan privat di `/tmp/shopdash-chat-audit/`; dokumentasi hanya memuat metadata dan kesimpulan. Source/running service Shopdash, cookie produksi, jadwal, dan schema tidak diubah pada audit ini. Percakapan uji yang dibuka kembali dibiarkan pada status hasil aksi browser.

## Perbedaan request yang diperiksa

Pengujian kontrak menggunakan bootstrap nyata dan transport send penangkap di memori, tanpa request send upstream. Dibandingkan dengan capture browser, semua field berikut cocok: `type`, `source`, `biz_id`, `entry_point`, `choice_info`, `chat_send_option`, `source_content`, shop/recipient/conversation. Nama query dan region juga cocok. UUID sudah memakai format yang diperbaiki pada implementasi sebelumnya.

| Bagian | Browser berhasil | PHP saat audit | Makna / batas |
| --- | --- | --- | --- |
| Header keamanan | `af-ac-enc-dat`, `x-sz-sdk-version`, `x-sap-ri`, `x-sap-sec`, `af-ac-enc-sz-token` tersedia pada snapshot endpoint | Tidak ada kelima key pada cookie toko atau header keluaran client | Jalur mengambil header dari nama cookie tidak memasok konteks ini |
| `re_policy` | `dfp_access_f` tersedia; nilainya berbeda pada body payload lama dan baru | Cookie/session tidak mempunyai `dfp_access_f`, sehingga field policy tidak dikirim | Ini perbedaan nyata; belum dibuktikan sebagai penyebab tunggal |
| User-Agent | Chrome 153 | Default client menetapkan Chrome 120; ekspor `_uafec` toko sebenarnya cocok dengan browser | Perbaikan konsistensi UA tetap berguna, tetapi kontrol UA cocok masih ditolak |
| CSRF | CTOKEN query capture terbaru berbeda dari cookie konfigurasi | CTOKEN dari konfigurasi | Kontrol CTOKEN terbaru + UA tetap ditolak; ekspor penuh cookie terbaru belum diuji |
| Cookie chat | Query `SPC_CDS_CHAT` | Nilai konfigurasi sama dengan capture terbaru | Ketidaksamaan cookie chat ini tidak ditemukan |
| Izin membuka chat | UI dan open mengizinkan | Open mengizinkan, conversation tidak closed | Closed/izin open bukan penjelasan penolakan send pada sampel ini |

Inferensi: penolakan berhubungan dengan konteks koneksi/request kirim yang berbeda dari browser. Belum terisolasi apakah penyebabnya policy, salah satu header, cookie lain, session binding, karakteristik transport, atau kombinasi. Tidak ada dokumentasi resmi yang diperoleh yang mendefinisikan `90309999` untuk endpoint internal ini. Tidak ada bukti bahwa mengganti IP, menambah delay, atau menyalin signature statis menyelesaikannya.

## Keterbatasan penting rekaman Sniper

Audit source Sniper dilakukan read-only pada checkout layanan port 8000, `/Volumes/DATA KERJA /Project Dev/sniper/xyzsniper`:

- `app/models/CaptureModel.php:38` menyimpan `request_header` di tabel `endpoints` dan menimpa nilainya saat capture berikutnya.
- `app/controllers/back/Mcp.php:292` mengembalikan `e.request_header AS request_header` untuk setiap row payload. Header yang ditampilkan bukan snapshot per event.
- Pengambilan ulang payload 13382 setelah send baru mempertahankan body dan timestamp lama, tetapi empat nilai header berubah dibanding file snapshot sebelum send. Semua row send yang diambil bersamaan menampilkan header endpoint yang sama.

Karena itu header payload 3767/13382 tidak boleh dipakai untuk membuktikan signature yang menyertai request historis. Snapshot sebelum dan sesudah send menunjukkan perubahan konteks endpoint; tidak membuktikan TTL atau rumus signature. Body/request ID/response tiap payload tetap dapat diperiksa. Capture chat masih `session_id=null`; dua session terdaftar hanya mempunyai satu event masing-masing, sehingga bukan trace urutan chat lengkap.

Perbaikan alat audit yang disarankan sebagai pekerjaan terpisah: simpan immutable request headers per event/payload, pisahkan header ringkasan endpoint, dan jangan mengisi row lama dengan header terkini. Tampilkan provenance jika row lama tidak memiliki snapshot. Tambahkan session/sequence pada capture baru. Jangan mencoba merekonstruksi signature lama dari data agregasi.

## Rencana perbaikan yang dapat diuji

### 1. Tetapkan baseline dan cek pilihan API resmi

- Pertahankan ownership guard, outbox, dan receipt validation yang sudah ada. Baca chat tetap melalui worker PHP yang telah berhasil.
- Periksa apakah akun mempunyai app Shopee Open Platform dengan izin Chat API. Repository saat audit tidak mempunyai integrasi partner/access token Chat API; kondisi akun Open Platform belum diperiksa karena browser dokumentasi tidak login.
- Dokumentasi resmi yang benar-benar dibaca di Chrome: [How to apply Chat API document permission](https://open.shopee.com/faq/56), last updated 2026-01-12. Halaman menyatakan aplikasi baru Customer Service dari individual third party/third-party partner ditutup sejak 18 November 2024; seller yang disetujui dapat memakai app Seller In House System. FAQ mengarahkan pengajuan melalui ticket, review kualifikasi, dan pembukaan akses bila disetujui. Jangan menganggap seller ini sudah disetujui atau semua syarat pasar terpenuhi. Tidak ada ticket/account/app yang dibuat pada audit.
- Bila izin sudah tersedia, pilih transport resmi untuk reply manual dan rekonsiliasi message ID. Kontrak endpoint harus dibaca dari dokumentasi akun yang mempunyai akses sebelum implementasi. API resmi tidak memakai replay header endpoint mini chat internal.
- Konsistensi UA dari ekspor cookie dapat dibenahi di `ShopeeChat::http`, tetapi tidak dijual sebagai solusi `90309999`. Satu probe dengan ekspor **seluruh** cookie terbaru masih dapat digunakan sebagai diagnostik sebelum mengakhiri evaluasi cURL.

### 2. Prototipe pengiriman melalui sesi browser asli

Ini kandidat paling dekat dengan bukti berhasil yang tersedia saat ini. Bangun dahulu di worktree terpisah dan batasi satu toko/percakapan uji.

- Worker browser memakai profil Chrome Shopee yang login. Verifikasi shop/session dan owner percakapan setiap kali memilih target; akun yang menampilkan beberapa toko harus mengikat request pada remote shop ID, bukan label atau tab aktif saja.
- Uji adapter alur kirim halaman yang menghasilkan request asli Shopee. Jika API/SDK halaman yang dapat dipanggil dengan aman belum teridentifikasi, prototipe dapat memakai alur UI yang terbukti berhasil dengan kontrol target yang tegas. `fetch()` biasa, sekadar menjalankan cURL di Node, atau menyalin header capture belum terbukti cukup.
- Penghubung hanya menerima pekerjaan reply dari Shopdash yang terautentikasi, untuk domain/operasi chat yang diizinkan. Jangan membuat proxy URL arbitrer atau memasukkan bearer/cookie/signature browser ke database bisnis/log. Heartbeat harus memperlihatkan browser siap, logout, salah toko, atau tidak tersedia.
- Pisahkan interface transport dari normalisasi/penyimpanan di `ShopeeChat`/`ChatMonitor`. Calon file baru: `app/helpers/ChatDeliveryTransport.php`, worker browser, serta controller bridge terbatas. Nama dan teknologi runner ditentukan setelah PoC; belum ada service/extension baru pada mesin.
- Outbox mengikat satu **intent lokal** ke satu dispatch browser. Klaim secara atomik sebelum interaksi; simpan status queued/claimed/sending/sent/failed/ambiguous dan lease. Setelah restart pada sending yang belum pasti, rekonsiliasi; jangan klik Kirim lagi otomatis.
- **Request ID harus dirancang ulang untuk adapter UI:** Shopee UI dapat menghasilkan UUID upstream sendiri. Tambah mapping `local_intent_id → remote_request_id → remote_message_id`; jangan memaksa semua receipt memakai UUID lokal atau melemahkan pemeriksaan scope/teks. Rekonsiliasi history memakai remote request ID yang terekam. Jika PoC tidak dapat mengaitkan satu dispatch dengan receipt tertentu, transport belum layak rollout.
- Pertahankan draft, error dekat composer, dan fallback buka Shopee saat bridge tidak tersedia. Send melalui bridge hanya untuk intent balasan manual pengguna; jangan memperluasnya menjadi bot/broadcast.

### 3. Kriteria lulus sebelum deploy sender

1. Kirim satu intent uji dari Shopdash melalui adapter, memperoleh remote message ID, serta memastikan pesan muncul pada riwayat Shopee dan cache Shopdash dengan shop/conversation/text yang sama.
2. Submit UUID lokal yang sama dua kali menghasilkan satu dispatch browser dan receipt yang sama.
3. Simulasikan putus koneksi setelah dispatch: intent menjadi ambiguous lalu direkonsiliasi dari receipt/history; tidak ada kirim ulang otomatis.
4. Verifikasi salah toko, logout, browser berhenti, closed/reopen, pindah percakapan saat respons terlambat, dan restart worker. Scope gagal harus menghentikan dispatch.
5. Pastikan prioritas worker chat, jobs pesanan/saldo/finance, serta jadwal yang dijeda tetap berjalan sesuai pengaturan. Deploy pilot satu toko dahulu; perluas setelah bukti live tersedia.

Mock dapat menguji antrean/race/error, tetapi kriteria pertama wajib probe Shopee nyata. UI yang berubah tetap ditinjau dengan antislop dan UI UX Pro Max, empat lebar dan dua tema seperti tes chat yang tersedia. Sampai kriteria lulus, status sender tetap **belum pulih**.
