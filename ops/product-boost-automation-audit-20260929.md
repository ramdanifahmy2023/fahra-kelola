# Audit dan rancangan automation Naikkan Produk

Tanggal: 2026-09-29 (WIB). Baseline: `f75c9c8`. Branch: `audit/product-boost-automation`. Status: audit selesai, rancangan untuk pembahasan; implementasi dan aktivasi belum dilakukan.

Pembaruan setelah pengguna meminta implementasi: perilaku yang sudah dibuat, verifikasi, instruksi runtime dan batas pilot dicatat di [kontrak implementasi Boost](boost-automation.md). Isi audit di bawah tetap merekam kondisi baseline, bukan status implementasi terkini.

Keputusan pengguna: **ulangi produk pilihan per toko saat slot tersedia**. Pemilihan otomatis dari seluruh katalog dan penggantian produk lewat rotasi berada di luar cakupan.

Arahan tambahan pengguna pada 2026-09-29: implementasi juga harus meningkatkan visual dan UX halaman Boost, menggunakan UI UX Pro Max serta antislop selama pengerjaan dan review akhir. Perbaikan UI menjadi bagian hasil implementasi, dengan lingkup tetap pada Boost dan komponen yang benar-benar diperlukan.

## Kesimpulan

Fondasi Boost manual dapat dipakai kembali, tetapi belum layak langsung dijalankan dengan cron. Penghambat utama adalah pemulihan setelah proses terputus, validasi produk yang berbeda antara daftar dan aksi, pemeriksaan cooldown di luar transaksi reservasi, serta belum adanya bukti status slot remote yang cukup untuk penjadwalan otomatis.

Rancangan yang direkomendasikan: konfigurasi Boost tersendiri per toko, pilihan produk persisten, perhitungan jadwal di server, serta satu executor untuk manual dan otomatis yang berbagi reservasi dan riwayat. Fitur ini tidak memerlukan AI/9Router atau data finance.

## Isolasi pekerjaan

| Area | Batas pekerjaan audit ini |
| --- | --- |
| Worktree baru | `../fahra-kelola-boost-audit`, dibuat dari commit baseline tanpa memindahkan branch worktree lain |
| AI agent automation | Worktree `../fahra-kelola-automation`, branch `docs/automation-visual-plan`; konfigurasi, worker usulan, UI, dan dokumennya dibaca sebagai referensi, tidak diedit |
| Finance | Worktree `../fahra-kelola-finance`, branch `feature/dashboard-finance-clarity`; tidak diedit |
| Hasil terlacak | Hanya dokumen audit ini |
| Eksperimen | `tmp/boost-audit-probe.php`, diabaikan Git; SQL hanya menulis tabel temporary pada koneksi sendiri |
| Runtime | Tidak menjalankan scheduler/worker, mengubah jadwal, migrasi, service, atau mengirim Boost ke Shopee |
| Git | Dokumen dikirim ke branch audit sendiri sesuai instruksi repository; tidak merge ke `main` |

Worktree memisahkan file, bukan database atau proses layanan. Pekerjaan implementasi nanti wajib memakai database pengujian sendiri untuk migrasi/concurrency dan tidak menjalankan worker baru terhadap database operasional dari checkout kedua. Daftar branch di atas merupakan pengamatan saat audit, bukan jaminan kepemilikan file di masa mendatang.

## Perilaku yang terverifikasi dari source

| Bagian | Implementasi saat ini |
| --- | --- |
| UI | `/panel/boost`, satu kartu per toko; menampilkan 10 produk terlaris halaman pertama |
| Pilihan produk | Checkbox di DOM; hilang saat reload; setelah aksi selesai pilihan dihapus |
| Daftar | `Product::findForBoost()` memfilter toko, `status = 1`, `deleted_at IS NULL`, lalu urut `sold_count DESC, id DESC` |
| Aksi | `ProcProducts::boost()` memvalidasi jumlah maksimal 5, kepemilikan toko, cooldown lokal, sesi dan identitas remote shop, reservasi, lalu status Boost dari Shopee sebelum POST per produk |
| Baca status remote | `ShopeeCurl::getBoostInfo()` memakai `GET /api/v3/opt/mpsku/list/get_boost_info` untuk ID yang diminta |
| Tulis remote | `ShopeeCurl::boostProduct()` memakai `POST /api/v3/opt/product/boost_product/`, payload `id` |
| Catatan lokal | `product_boost_runs` dan `product_boost_items`; mode selalu `manual` |
| Kuota lokal | Maksimal 5 item berstatus `success` atau `unknown` dalam 4 jam terakhir |
| Cooldown produk | Default 255 menit sejak `success` atau `unknown` terakhir |
| Pengunci batch | Run `running` selama 15 menit; reservasi memakai row lock toko dalam transaksi |
| Background | `BackgroundSync`, `SyncQueue`, dan `processGenericJob()` belum menyediakan tipe Boost di baseline |
| Otorisasi | Login dijaga di `public/index.php`; aksi Boost memeriksa header AJAX, belum memakai token CSRF seperti konfigurasi Automation |

Angka 5, 4 jam, 255 menit, dan 15 menit di atas adalah perilaku kode pada baseline. Materi edukasi Shopee menyebut hingga 5 produk setiap 4 jam, tetapi dokumen tersebut tidak membuktikan kontrak endpoint, rate limit, atau kondisi akun saat ini. Lihat [materi Berjualan di Shopee, halaman PDF 29](https://cdngarenanow-a.akamaihd.net/shopee/seller/seller_cms/15504c403610cf5ae2adaec0ea0bc82e/Berjualan%20di%20Shopee.pdf#page=29), diakses 2026-09-29. Tambahan 15 menit pada cooldown produk belum ditemukan penjelasannya dalam source yang diaudit.

## Temuan menurut prioritas

P1 perlu ditutup sebelum sender otomatis diaktifkan. P2 perlu masuk kontrak MVP dan pengujiannya. Temuan source tidak otomatis berarti insiden tersebut sudah terjadi di toko nyata.

| ID | Prioritas | Temuan dan dampak | Bukti baseline / verifikasi |
| --- | --- | --- | --- |
| B01 | P1 | Tidak ada status durable yang membedakan item belum dikirim dengan item sedang dikirim. Jika proses berhenti sesudah POST tetapi sebelum `recordItem`, item tetap `pending`. Setelah 15 menit, parent bisa ditandai gagal dan item itu tidak dihitung dalam kuota/cooldown. Pengiriman berikutnya dapat diizinkan walau hasil sebelumnya belum jelas. | `ProductBoostMonitor.php:43,105,122,158`; `ProcProducts.php:144`. Fixture mereproduksi parent expired, pending tersisa, kuota 5 dan reservasi ulang diterima. Crash remote tidak dilakukan. |
| B02 | P1 | Pemeriksaan cooldown produk dilakukan sebelum pengecekan sesi/network dan sebelum transaksi. `reserveRun()` hanya memeriksa kuota dan run aktif. Request lain dapat selesai di sela pemeriksaan sehingga produk yang sama direservasi kembali; status remote masih dapat menolak, tetapi local invariant belum terjamin. | `ProcProducts.php:88,99,108`; `ProductBoostMonitor.php:122`. Fixture menunjukkan produk pada menit ke-250 masih cooldown, tetapi reservasi menerima produk itu. Interleaving dua koneksi belum diuji. |
| B03 | P1 | Query aksi tidak memfilter `deleted_at` dan `total_stock > 0`. UI menyaring stok; query daftar menyaring deleted. Caller langsung dapat melewati filter lokal tersebut. Preflight remote masih menjadi pemeriksaan berikutnya, sehingga temuan ini bukan bukti Shopee menerima produk itu. | `Product.php:6,24`; `boost.js:18`. Fixture mengembalikan kedua produk deleted dan stok nol pada query aksi. |
| B04 | P1 | Kegagalan GET preflight membuat semua item `unknown` dan memakai kuota/cooldown, padahal belum ada POST. Sebaliknya, respons JSON tidak sesuai bentuk setelah POST dapat jatuh ke `failed`. Tidak ada rekonsiliasi setelah POST; `code` juga di-cast ke integer. | `ProcProducts.php:111,145`; `ShopeeCurl.php:94,487`. Analisis source, belum ada fixture transport. Pisahkan `not_sent`, penolakan pasti, diterima API, dan hasil belum pasti; validasi bentuk/type respons. |
| B05 | P1 | Kuota hanya mencatat aksi Shopdash. Aksi Seller Centre/perangkat lain tidak masuk ledger lokal. Endpoint yang ada membaca status ID terpilih, belum terbukti memberi seluruh slot toko atau waktu reset akurat. | `ProductBoostMonitor.php:52`; `ShopeeCurl.php:487`; belum ada capture Boost tersanitasi dalam audit ini. Label dan scheduler tidak boleh menganggap kuota lokal sebagai slot remote yang pasti. |
| B06 | P1 | Transport bersama menonaktifkan verifikasi TLS dan mengikuti redirect. Payload respons mentah, termasuk HTML non-JSON, dapat masuk `response_payload`. Ini ketergantungan nyata untuk pengiriman otomatis berulang. | `ShopeeCurl.php:74`; `ProductBoostMonitor.php:158`. Perlu adapter Boost dengan validasi sertifikat, batas endpoint/redirect, allowlist diagnostic dan retensi; perubahan transport global harus dikoordinasikan. |
| B07 | P2 | `finishRun()` dapat melaporkan `completed` ketika item masih pending, atau ketika hasil campuran sukses/gagal. Total status tidak memaksa semua item memiliki hasil terminal. | `ProductBoostMonitor.php:168`. Dua fixture mereproduksi completed dengan nol hasil serta completed dengan 1 sukses/1 gagal. |
| B08 | P2 | Endpoint pembacaan bukan murni read-only: `summary()` memperbarui run lama; metode monitor memanggil `ensureSchema()`. Refresh halaman dapat memicu recovery. | `ProductBoostMonitor.php:5,43,79,196`. Jangan memakai endpoint ini untuk health check read-only; pindahkan migrasi dan recovery ke operasi eksplisit. |
| B09 | P2 | Tidak ada konfigurasi persisten, enable/pause/version, jadwal due, lease owner, rekam aktor atau deduplikasi antar-request. Constraint item unik hanya dalam satu run. | Migrasi `20260927_product_boost.sql`; `createRun()`; `boost.js:132,158`. Infrastruktur baru diperlukan sebelum pengulangan otomatis. |
| B10 | P2 | UI hanya menampilkan 10 terlaris; pilihan di luar daftar tidak dapat dipertahankan. Timer 1 detik hanya menjalankan `controls()`, tidak memperbarui kuota/batch dari server. Riwayat tidak menampilkan alasan error item dan label `partial` belum diterjemahkan. Waktu memakai timezone browser, bukan WIB eksplisit. | `boost.js:12,85,102,114,152` dan akhir file; `boost.php:25`. Tinjauan source, bukan audit visual browser. R-27, R-36, C-2 antislop relevan untuk kejelasan status. |
| B11 | P2 | Aksi belum memvalidasi CSRF/metode secara eksplisit dan belum menyimpan aktor. Header AJAX serta SameSite sudah memberi perlindungan tertentu; temuan ini tidak menyatakan bypass autentikasi terbukti. | `ProcProducts.php:12,66`; bandingkan `ProcAutomation.php:10`. Gunakan pola auth/CSRF existing dan optimistic version untuk konfigurasi Boost. |
| B12 | P2 | Pengecekan sesi yang gagal karena network disamakan dengan sesi kedaluwarsa dan menulis `shops.sync_status`. Status connected ditulis sebelum kecocokan shop ID diperiksa. Ini berpotensi memengaruhi modul lain yang membaca status toko. | `ProcProducts.php:99-105`; `ShopeeCurl::check()`. Pisahkan gangguan transport, sesi habis, dan identitas salah; hindari menandai seluruh toko expired karena gangguan sementara. |

Hal yang sudah baik dan perlu dipertahankan: scoping ID lokal toko, pencocokan remote shop dengan sesi, validasi jumlah pilihan, preflight sebelum aksi, row lock pada reservasi, pencatatan hasil per produk, perlakuan konservatif pada timeout cURL, serta mock endpoint mutasi pada tes UI.

## Kontrak perilaku MVP yang diusulkan

1. Pengguna menyimpan 1 sampai 5 produk tetap per toko. Batas ini usulan MVP mengikuti kapasitas lokal sekarang, bukan keputusan pengguna tentang jumlah maksimum. Produk dapat dicari dari seluruh katalog aktif toko, tidak dibatasi 10 terlaris.
2. Menyimpan pilihan tidak mengaktifkan automation. Default toko baru adalah nonaktif. Aktivasi menampilkan toko, daftar produk, perilaku pengulangan dan status kesiapan yang benar-benar diperiksa.
3. Saat slot tersedia, proses hanya produk pilihan yang eligible. Jika dari lima pilihan hanya dua tersedia, keduanya boleh diproses; sisanya menunggu dan tidak diganti produk lain. Di antara pilihan yang eligible, dahulukan yang paling lama belum berhasil, dengan urutan pilihan sebagai tie-breaker.
4. Produk stok kosong, nonaktif, terhapus, atau belum terverifikasi tetap terlihat dalam pilihan dengan alasan. Pengecualian tidak diam-diam menghapus pilihan. Produk dapat diproses lagi sesudah eligible, kecuali pengguna menghapusnya.
5. Kuota, cooldown produk, eligibility remote, sesi, dan pause semuanya harus lolos. Kuota lokal yang longgar tidak mengesampingkan penolakan remote. Data yang tidak cukup menghasilkan status menunggu verifikasi.
6. Browser boleh ditutup. Server menghitung pemeriksaan berikutnya dari due produk, slot, dan backoff. Jadwal tidak berupa `setInterval` pengiriman di browser dan tidak menjanjikan eksekusi tepat pada detik slot terbuka.
7. Manual dan otomatis berbagi reservasi serta ledger yang sama. Boost manual terhadap produk lain dapat membuat automation menunggu slot; pilihan automation tidak berubah.
8. Jeda berlaku untuk pengiriman berikutnya dan dicek lagi sebelum setiap POST. Request yang sudah dikirim tetap diselesaikan pencatatan/rekonsiliasinya. Edit pilihan menaikkan versi sehingga antrean lama harus dibatalkan atau dievaluasi ulang.
9. Setelah restart, hitung ulang kondisi saat ini; jangan memutar semua jadwal yang terlewat. Hasil unknown ditahan untuk rekonsiliasi, tidak menjadi antrean POST ulang hanya karena waktu lease habis.
10. Usulan awal berjalan sepanjang hari setelah diaktifkan, dengan waktu status dalam WIB. Jam operasi khusus bukan kebutuhan yang sudah diputuskan dan dapat ditambahkan kemudian bila diperlukan.

## Pembagian komponen dan titik integrasi

| Komponen usulan | Tanggung jawab |
| --- | --- |
| `BoostPolicy` | Eligibility deterministik, perhitungan due, pemilihan subset dari daftar tetap, klasifikasi hasil; clock dapat diganti di pengujian |
| `BoostAutomationProfile` | Konfigurasi/versi per toko dan pilihan persisten; terpisah dari `AutomationProfile` untuk rating |
| `BoostExecutor` | Jalur tunggal untuk manual dan worker; cek identitas, preflight, reservasi, pencatatan sending, POST, klasifikasi dan rekonsiliasi |
| `BoostTransport` | Adapter endpoint Boost dengan respons tervalidasi dan diagnostic minimum; tidak mengubah transport finance/AI secara terselubung |
| `BoostQueue` / runner domain | Claim atomik, lease token, heartbeat, recovery, fairness per toko, dan due calculation |
| `ProcBoostAutomation` | Read lokal murni, save/preview/enable/pause dengan login, CSRF, validasi dan optimistic version |
| Halaman Boost existing | Pilihan tersimpan, status automation dan riwayat, memakai gaya panel saat ini |

Pola queue dari proyek dapat dipakai sebagai referensi. Jangan memasukkan aksi mutasi ini ke worker order/finance atau mengandalkan `flock` berdasarkan path checkout sebagai satu-satunya pengunci. Kepemilikan lease harus berada di database dan berlaku antarproses/worktree.

Rencana AI existing juga mengusulkan worker automation terpisah. Sebelum implementasi runner, tetapkan satu kontrak dengan pekerjaan AI: gunakan runner umum hanya bila claim/lease/pause/task registration-nya sudah tersedia dan kompatibel; kalau belum, runner Boost tetap domain-spesifik dengan nama service sendiri. Jangan membangun framework automation kedua, mengubah `automation_profiles`, atau menyunting dispatcher agen lain saat bersamaan. Semua batas request lintas modul yang benar-benar dibagi perlu desain tersendiri; pengunci Boost saja tidak membatasi traffic finance/sync/AI.

File calon milik pekerjaan Boost: model/helper/controller baru berawalan Boost, migrasi incremental Boost baru, `boost.php`, `boost.js`, dan tes Boost. `ProductBoostMonitor.php`, bagian Boost pada `ProcProducts.php`, dan query Boost `Product.php` perlu perubahan terbatas yang direview. `ShopeeCurl.php`, `Panel.php`, sidebar, CSS/build, schema bootstrap, installer layanan, dan runner umum adalah titik integrasi bersama: periksa perubahan branch lain dahulu dan gabungkan melalui satu pemilik saat implementasi. Audit ini tidak mengubah file-file tersebut.

## Penyimpanan dan konsistensi yang dibutuhkan

Nama final tabel belum dikunci; berikut kontrak datanya.

| Penyimpanan | Field/invariant minimum |
| --- | --- |
| Profil Boost | Unique local shop; enabled, version, updated_by, updated_at, activated_at, paused_at, next_check_at, last_checked_at, pause_reason |
| Pilihan produk | Unique `(shop_id, product_id)`; urutan pilihan, waktu disimpan; validasi maksimal pilihan dalam transaksi profil |
| State kerja per toko | Satu row unik per toko; active_run, owner token, lease expiry, retry deadline; manual dan otomatis harus mematuhi state yang sama |
| Run dan item | Pertahankan histori lama; tambah mode/aktor/profile version/request key, hasil item dan rekonsiliasi; status partial/unknown harus konsisten |
| Attempt | Intent durable, product/shop, attempt ID unik, sending_at, received_at, HTTP/API outcome tervalidasi, verified_at, reason_code; jangan menyimpan cookie, header autentikasi atau HTML mentah |

Manual request key mencegah dua klik membuat dua run; siklus automation memakai generasi jadwal yang dicatat secara atomik, bukan sekadar bucket waktu yang dapat bergeser. Perubahan profil tidak menghapus riwayat cooldown atau unknown. UI tidak menerima cookie/credential sebagai bagian payload profil.

State item yang diusulkan:

```text
ready -> reserved -> sending -> accepted -> verified
          |           |           |
       not_sent     unknown     unknown
          |
       retry_wait (hanya untuk kegagalan sebelum pengiriman)

Penolakan eksplisit: rejected / wait_eligibility
Unknown: rekonsiliasi read-only, lalu verified / needs_review
```

Transaksi reservasi mengunci state toko, membaca ulang profil/versi, eligibility lokal, cooldown, slot lokal dan active lease, lalu menulis run/items sebelum commit. Jangan menahan row lock selama network call. Tepat sebelum POST, validasi ulang token lease, pause/versi dan tulis `sending` secara durable. Worker lama tidak boleh menyimpan hasil sebagai owner baru; hasil terlambat tetap dicatat sebagai bukti attempt. Lease lebih lama daripada timeout transport dan diperpanjang antar-item; lease yang habis saat sending masuk rekonsiliasi, bukan langsung diberi sender baru.

Slot remote tetap dapat berubah akibat tindakan Seller Centre di sela preflight dan POST. Klaim exactly-once remote tidak dapat dibuat tanpa dukungan idempotency dari upstream yang sudah terbukti. Hasil ambigu dipertahankan sebagai unknown. Pending yang terbukti belum dikirim boleh dilepas; data pending lama yang tidak punya jejak pengiriman harus diperlakukan konservatif.

Timestamps disimpan UTC secara eksplisit. Pisahkan `attempted_at`, hasil, verifikasi, dan `next_eligible_at`; jangan menimpa satu timestamp untuk semua peristiwa. Pisahkan pula waktu perkiraan slot tersedia, cooldown produk, lease expiry, dan pemeriksaan berikutnya.

## Jadwal dan penanganan gagal

- Runner memeriksa row yang due; usulan tick 60 detik, bukan interval POST 60 detik. Nilai ini parameter operasional awal, bukan limit Shopee. Batasi batch dan beri giliran toko lain.
- Jika seluruh pilihan masih cooldown, jadwalkan pemeriksaan mendekati waktu eligible paling awal. Untuk kandidat tertentu, gunakan batas paling akhir dari cooldown lokal, waktu eligible remote yang tervalidasi, waktu slot tersedia, dan backoff.
- Slot tersedia tetapi kandidat belum eligible tetap menunggu. Satu slot tersedia tidak berarti seluruh lima pilihan boleh dikirim.
- GET gagal dapat diulang dengan backoff terbatas dan jitter. Usulan 30 detik, 2 menit, 10 menit untuk uji; honor `Retry-After` bila valid. Batasi frekuensi pemeriksaan bila upstream tidak memberi waktu eligible.
- Sesi kedaluwarsa atau identitas berbeda menahan toko dan memberi tindakan perbarui koneksi. Network timeout tidak otomatis mengubah seluruh status toko menjadi expired. 429/access denial harus menghentikan pengiriman batch dan memicu penundaan yang sesuai.
- POST timeout/malformed/5xx setelah request mungkin terkirim menjadi unknown. Jangan retry otomatis. GET preflight gagal sebelum POST menjadi not_sent dan tidak memakai cooldown konsumsi.
- Kegagalan satu item tidak mengulang item yang sudah berhasil. Gangguan toko tidak menahan toko lain, dengan batas total request yang disepakati pada integrasi.

## Audit alur UI dan arah rancangan

Pembacaan desain: panel operasional untuk pengelola banyak toko, mengikuti identitas Shopdash; ENERGY 1 / RHYTHM 1 / MOTION 1. Struktur berulang per toko membantu membandingkan status; fokus setiap kartu adalah pilihan produk dan keadaan eksekusinya. Palet, tipografi, logo toko, tema, dan pola grid produk yang sudah ada dipertahankan.

UI UX Pro Max: pencarian `background task status feedback` pada domain UX memberi rekomendasi Submit Feedback yang relevan; hasil haptic tidak dipakai untuk panel ini. Pencarian stack `html-tailwind` menguatkan label kontrol dan konteks screen reader. Antislop menyaring penambahan chart/statistik/dekorasi yang tidak membantu keputusan Boost.

- Tampilkan pilihan tersimpan terpisah dari checkbox aksi manual sementara. Produk tetap muncul ketika peringkat terlarisnya berubah atau stok habis. Cari/paginasi katalog tanpa kehilangan pilihan di halaman lain.
- Tampilkan status Nonaktif, Aktif menunggu, Memproses, Dijeda, Koneksi perlu diperbarui, dan Hasil belum pasti, dengan alasan serta waktu pemeriksaan terakhir.
- Bedakan Simpan pilihan, Aktifkan pengulangan, Jeda, dan Naikkan sekarang. Menyimpan tidak melakukan POST Boost.
- Nyatakan kuota lokal sebagai perkiraan sampai status remote tersedia; tampilkan waktu dan sumber pengamatan. Jangan mengklaim peningkatan penjualan atau performa tanpa data.
- Status hasil menjelaskan berhasil/gagal/belum pasti per item. Unknown menawarkan pemeriksaan hasil, bukan tombol kirim ulang langsung. Alasan dilewati harus bisa dibaca tanpa menebak warna.
- Pertahankan checkbox, thumbnail 48px, dan judul dalam kolom terpisah; logo toko 40px. Target kontrol minimal 44px, keyboard/focus, light/dark, dan 320/500/999/1600px menjadi acceptance UI.
- Read status boleh polling terbatas saat halaman terlihat, tetapi polling tidak membuat task, melakukan recovery, atau mengirim Boost. Hentikan fetch yang tidak diperlukan saat tab tersembunyi dan tampilkan kondisi stale/error.

Review antislop untuk dokumen: PASS R-17/R-36/R-38, angka existing dibedakan dari usulan dan keterbatasan; PASS R-02/R-16, bahasa tindakan spesifik tanpa klaim pemasaran. Gate visual, contrast, browser interaction dan responsive belum dinilai: belum ada UI baru yang diimplementasikan. Tidak ada klaim UI existing lulus audit visual.

### Perbaikan visual dan UX yang wajib masuk implementasi

Tujuan layar: pengguna dapat mengetahui toko yang perlu ditangani, memastikan produk yang akan diulang, lalu mengaktifkan atau menjeda pengulangan dengan cakupan yang jelas. Identitas Shopdash dipertahankan, tetapi hierarki, kepadatan, jarak, dan penempatan kontrol Boost boleh diperbaiki untuk tujuan tersebut.

| Bagian | Perbaikan yang direncanakan | Bukti penerimaan |
| --- | --- | --- |
| Ringkasan toko | Logo/nama, status pengulangan, jumlah pilihan tersimpan, pemeriksaan berikutnya dan masalah yang perlu ditangani terlihat sebelum membuka detail produk | Pengguna dapat menemukan toko bermasalah saat daftar produk tertutup; status tidak bergantung pada warna |
| Hierarki visual | Perjelas pembagian identitas toko, status, pilihan produk, tindakan dan riwayat melalui ukuran teks serta jarak; gunakan aksen utama untuk tindakan sesuai state | Screenshot menunjukkan prioritas tindakan tanpa seluruh tombol/badge memakai penekanan yang sama |
| Editor pilihan | Pencarian katalog, jumlah pilihan dan daftar tersimpan mudah ditemukan; tampilkan penanda perubahan belum disimpan | Pilihan lintas halaman tetap utuh; polling tidak menimpa draft; navigasi dengan perubahan tersisa memberi kesempatan menyimpan atau membatalkan |
| Baris produk | Judul panjang dapat dibaca, stok dan alasan tidak eligible berada dekat produk; pilihan tersimpan tetap terlihat walau produk sedang cooldown | Uji judul panjang, teks tanpa spasi, stok nol, gambar gagal dan produk di luar 10 terlaris |
| Tindakan | Pisahkan pengaturan pengulangan dari Boost sekali jalan; beri label jelas pada simpan, aktifkan, jeda dan aksi manual | Menyimpan tidak mengaktifkan/mengirim; aktivasi memakai versi pilihan tersimpan yang ditampilkan, bukan draft yang berbeda |
| Feedback | Tampilkan proses dan hasil di dekat aksi; jelaskan error beserta langkah berikutnya; refresh tidak memindahkan fokus pengguna | Uji loading, save conflict, koneksi gagal, partial dan unknown; pembaca layar mendapat pengumuman status yang bermakna |
| Riwayat | Ringkasan waktu WIB, asal manual/otomatis dan hasil; detail per produk menampilkan alasan gagal/dilewati dan status verifikasi | Pengguna dapat membedakan pengiriman gagal, belum dikirim dan belum pasti tanpa membaca pesan teknis mentah |
| Mobile dan tema | Kontrol bertumpuk sesuai ruang, judul dan metadata mengalir, detail panjang dapat dibuka; warna mengikuti token tema Shopdash | Lulus 320/500/999/1600px pada light/dark, target sentuh minimal 44px, zoom 200%, keyboard dan fokus terlihat |

Pelaksanaan UI dimulai dengan screenshot baseline dari fixture terisolasi, dilanjutkan rancangan alur berdasarkan state backend, implementasi, lalu pembandingan screenshot dan pengujian interaksi. Riset UI UX Pro Max difokuskan pada masalah yang ditemukan dan stack PHP/plain JavaScript/html-tailwind. Antislop diterapkan sejak rancangan, bukan hanya saat selesai; rekomendasi yang bertentangan dengan konteks Shopdash tidak digunakan.

Gate penerimaan UI: verifikasi kontras teks normal minimal 4.5:1 dan batas/fokus kontrol yang relevan minimal 3:1; periksa tampilan aktual dan console; uji semua tindakan dengan mutasi dimock; dokumentasikan hasil antislop beserta bukti. Perubahan CSS ditulis pada `resources/css/input.css`, dibangun dengan `npm run build`, dan mempertahankan asset versioning. Bila CSS bersama sedang disentuh agen lain, gunakan perubahan Boost yang terlingkup dan integrasikan setelah diff bersama diperiksa. Tidak ada redesign AI automation, finance, atau navigasi global dalam lingkup ini.

## Tahap menuju implementasi

| Tahap | Hasil yang diperlukan | Gerbang selesai |
| --- | --- | --- |
| 0. Lengkapi kontrak | Capture GET Boost yang disanitasi untuk available/active/cooldown/no-slot/missing-info serta pemetaan identitas/error; sepakati kepemilikan runner dengan pekerjaan AI | Bentuk respons/type, sumber slot remote, cara rekonsiliasi, dan hubungan 240/255 menit jelas; baca status tidak memanggil endpoint lokal yang menulis |
| 1. Benahi jalur manual | Executor bersama, eligibility konsisten, transaksi/lease, sending intent, outcome classifier, CSRF, recovery eksplisit, transport aman | Fixture race/crash/invalid response lulus; perilaku manual tetap terjaga |
| 2. Simpan pilihan dan preview | Profil versioned, pilihan katalog, preview tanpa kirim, default nonaktif, migrasi incremental; perbaikan visual ringkasan toko dan editor pilihan dengan UI UX Pro Max/antislop | Save/edit konflik versi, shop isolation, pilihan lintas halaman, draft tidak tertimpa polling, dan preview teruji |
| 3. Scheduler tanpa kirim | Due calculation, queue/lease/fairness, pause/restart, dry-run yang nyata dan teruji | Dua worker di database uji tidak menggandakan klaim; tidak ada POST Boost di dry-run |
| 4. Sender dan UI status | Aktivasi eksplisit per toko, pengiriman lewat executor, rekonsiliasi; penyelesaian visual/UX status, tindakan dan riwayat | Seluruh penghambat P1 tertutup; tes browser memock mutasi; observabilitas dan pause bekerja; gate visual, responsive, aksesibilitas dan antislop memiliki bukti |
| 5. Pilot terbatas | Satu toko dan pilihan produk yang disepakati, observasi minimal melewati satu pengulangan | Hasil remote dan ledger cocok, pause menghentikan attempt berikutnya, unknown tidak diulang otomatis |

Tahap 1 sampai 5 adalah rencana, bukan izin implementasi/aktivasi dalam sesi audit ini. Pilot yang menulis ke Shopee memerlukan cakupan toko/produk dan izin pengiriman yang jelas. Penerbitan dokumen ke GitHub tidak menjalankan migrasi atau service.

## Matriks verifikasi implementasi nanti

| Kelompok | Kasus yang wajib dicakup |
| --- | --- |
| Pilihan | 0/1/5/6 pilihan, duplikat, ID invalid, toko lain, produk deleted/inactive/stok nol, pencarian/paginasi, reload, versi konflik |
| Jadwal | Boundary 4 jam dan 255 menit, slot staggered, kurang dari 5 pilihan, waktu UTC/WIB, clock uji, restart tanpa catch-up flood |
| Concurrency | Dua worker, manual vs otomatis, dua checkout terhadap DB uji, cooldown berubah sebelum reservasi, pause/edit ketika claimed, lease expiry dan stale owner |
| Crash | Sebelum reservasi, setelah reserved, setelah sending sebelum network, setelah remote menerima sebelum pencatatan, setelah accepted sebelum verifikasi |
| Transport | GET gagal tidak memakai kuota, identitas salah, info hilang, array/list berbeda, scalar/null/HTML, code dengan type salah, API rejection, 401/403/429/5xx, timeout POST |
| Remote | Boost dari luar Shopdash, slot penuh, status cooldown aktual, bukti read-back cukup/tidak cukup, unknown ditahan |
| Operasional | Default off, pause global/per toko, budget dan fairness, heartbeat/lag/error tersanitasi, recovery tanpa page load, tidak mengganggu sync/finance/AI |
| UI | Loading/empty/error/stale/partial/unknown, pilihan persisten, alasan per item, keyboard, logo, tema dan empat lebar; semua endpoint mutasi dimock |

Rollback operasional: nonaktifkan Boost otomatis, hentikan runner Boost setelah attempt in-flight tercatat, pertahankan ledger dan unknown untuk rekonsiliasi. Jangan menghapus histori atau mereset kuota. Rollback kode/migrasi harus tetap dapat membaca data additive; jangan menjalankan schema bootstrap yang berisi DROP TABLE.

## Verifikasi yang benar-benar dijalankan

Pada 2026-09-29, `php tmp/boost-audit-probe.php` selesai exit 0 dengan enam reproduksi: lookup menerima deleted/stok nol; stale run membebaskan kuota dengan item pending; pending dapat direservasi lagi; `finishRun` completed dengan item pending; reserve mengabaikan cooldown 255 menit; hasil campuran diberi completed.

Probe memakai source baseline dari worktree audit, koneksi DB lokal dengan tabel temporary yang menutupi `shops`, `product_boost_runs`, `product_boost_items`, serta fixture produk sendiri. `ensureSchema()` dioverride no-op. Semua tabel temporary dibersihkan; tidak ada panggilan Shopee atau penulisan tabel bisnis permanen. File eksperimen tetap lokal di `tmp/` dan tidak diterbitkan.

Untuk mereproduksi B02, buat satu attempt sukses lalu geser `attempted_at` pada fixture ke 250 menit lalu. `productCooldowns()` masih mengembalikan aktif, sedangkan `reserveRun()` menerima ID tersebut karena perhitungan kuotanya hanya 4 jam dan tidak memeriksa cooldown produk. Untuk B01, buat run/item pending, geser `started_at` parent ke 16 menit lalu, panggil `summary()`, lalu coba reservasi ID yang sama. Ini reproduksi celah local state; tidak membuktikan pengiriman duplikat berhasil di Shopee.

Tes existing `tests/boost-products.php` diaudit: cakupannya daftar/count/empty shop, belum jalur pengiriman atau monitor. `tests/boost-ui.cjs` diaudit: mutasi dimock. Kedua suite tidak dijalankan ulang pada sesi dokumentasi ini. Pemeriksaan whitespace dan tautan/path dilakukan sebelum commit. Tidak ada pengukuran runtime toko, aktivasi layanan, tes visual browser, atau verifikasi transport live Boost; hal tersebut tetap menjadi pekerjaan lanjutan yang tercantum di tahap 0 dan matriks pengujian.
