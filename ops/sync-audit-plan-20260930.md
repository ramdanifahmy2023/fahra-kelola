# Audit sinkronisasi Shopee ke web

Tanggal audit: 2026-09-30 13:52 WIB  
Worktree: `Worktree-codex/singkronasi`  
Base: `main` / `76f04a8`  
Cakupan: scheduler, queue, worker, order, product, package, cookie/session, dan transport Shopee.

## Status yang terverifikasi

- `php tests/sync-recovery.php`: 27 checks lulus.
- `php tests/product-sync.php`: 15 checks lulus.
- `php tests/order-sync.php`: 19 checks lulus.
- `php tests/package-sync.php`: 6 checks lulus.
- Syntax PHP untuk helper/model worker lulus; `git diff --check` lulus.
- `php bin/sync-observe.php` adalah observasi read-only pada 2026-09-30 13:41 WIB. Saat sampel: 102.559 detail `done`, 4.185 `queued`, tanpa lease detail kedaluwarsa.
- Query lanjutan pada sampel yang sama menemukan order history `job 3869` (shop 4) masih `running`, page sudah selesai (`page_number=0`) tetapi memiliki 3.565 detail `queued` dan sudah 823 claim/turn. Job diff `15721` (shop 2) berstatus `queued` dengan `last_error = Worker order index error: PDOException` dan backoff aktif. Penyebab PDO perlu ditangkap lebih spesifik sebelum mengaitkannya ke satu bug.
- Service produksi yang terpasang menunjuk ke checkout utama `shopdash`; audit ini tidak menjalankan worker dari worktree.

## Temuan prioritas

### P0 — Risiko data toko tercampur saat cookie diganti

`app/controllers/back/ProcShops.php:181-193` menyimpan cookie mentah melalui `update_cookie` tanpa memanggil `ShopeeCurl::check`, tanpa memeriksa `shop_id` sumber, dan tanpa menandai sesi sebagai perlu verifikasi. Worker kemudian memakai cookie tersimpan untuk order/produk/package. Cookie invalid atau cookie toko lain dapat menulis data Shopee ke row toko lokal yang salah.

**Perbaikan:** satu service penggantian cookie yang selalu memverifikasi identitas (`source shop id`), menolak mismatch, menyimpan status `expired/pending_verification` saat gagal, dan menahan enqueue untuk sesi yang belum terverifikasi. Tambahkan audit log tanpa menyimpan cookie.

### P0 — Definisi schema `sync_pages` tidak konsisten

`app/models/SyncJob.php:62-80` dan `database/migrations/20260926_sync_optimization.sql:57-73` membuat/meningkatkan `sync_pages` tanpa kolom `cursor`, tetapi `SyncJob::enqueue()` dan `addPage()` menulis `sync_pages.cursor` pada `app/models/SyncJob.php:213` dan `:341`. `database/schema.sql` memiliki kolom tersebut, sehingga instalasi yang berasal dari schema penuh dapat terlihat normal sedangkan instalasi yang mengikuti migrasi incremental dapat gagal saat enqueue/order index.

**Perbaikan:** tetapkan satu kontrak schema canonical; tambah migrasi idempoten untuk `cursor`, `source_count`, `fetched_count`, `checksum`, serta indeks/unique key yang dipakai kode; jadikan enqueue satu transaksi dan hentikan worker dengan error yang terklasifikasi jika migrasi belum diterapkan. Tambahkan test dari schema legacy tanpa `cursor`.

### P1 — Detail order yang gagal dapat retry tanpa batas

`processOrder()` selalu memanggil `finishOrder(..., 'retry')` ketika detail gagal (`app/helpers/SyncWorkerTasks.php:152-159`). Tidak ada transisi ke status `failed` berdasarkan jumlah percobaan. Parent hanya selesai ketika tidak ada task queued/retry/running (`bin/sync-worker.php:123-141`), sehingga order yang ditolak permanen dapat menahan job selamanya. Field `failed_detail` dan status task `failed` tersedia, tetapi jalur produksi tidak pernah mengisinya.

**Perbaikan:** gunakan batas percobaan dan klasifikasi error; setelah batas, pindahkan task ke `failed`, simpan kode/kategori, lanjutkan task lain, dan selesaikan parent sebagai `partial/failed` dengan jumlah yang jelas. Sediakan retry manual untuk task gagal.

### P1 — Checkpoint produk dapat tertinggal sebagai `running` tanpa error

Beberapa guard di `app/helpers/ProductSynchronizer.php:75-78` mengembalikan gagal langsung ketika cursor tidak bergerak atau respons tidak lengkap, tanpa `saveCheckpoint(..., 'failed', ...)`. Checkpoint dapat tetap `running`, error terakhir kosong, dan percobaan berikutnya terlihat seperti resume normal.

**Perbaikan:** semua exit gagal harus menyimpan cursor/page/seen set dan kategori error secara atomik; status `failed` harus terlihat di API status; hanya cleanup setelah traversal lengkap dan total tervalidasi.

### P1 — Upsert produk/model tidak mengunci scope toko/channel

`ProductSynchronizer::upsertProduct()` mencari row hanya dengan `id` (`app/helpers/ProductSynchronizer.php:144-158`), dan model melakukan hal serupa (`:190-201`). Schema memiliki `shop_id`, `channel_id`, dan `external_id` sebagai scope (`database/schema.sql:567-600`), tetapi sinkronisasi hanya mengisi `id`/`shop_id`. Jika ID upstream tidak global antar toko, row toko lain dapat diperbarui atau insert dapat gagal karena primary key.

**Perbaikan:** gunakan identitas `(channel_id, shop_id, external_id)` sebagai kunci sinkronisasi, migrasikan data lama dengan aman, dan tambahkan test dua toko dengan ID upstream sama. Cleanup harus memakai run ID/scope yang sama.

### P1 — Endpoint sinkronisasi write belum konsisten melindungi CSRF dan ownership

`ProcSync::enqueue/configure/pulse` (`app/controllers/back/ProcSync.php:11-75`) dan `ProcOrders::sync_index/sync_detail` (`app/controllers/back/ProcOrders.php:15-68`) hanya memeriksa header AJAX pada sebagian route; token CSRF yang dikirim UI diabaikan kecuali `retry`. `sync_detail` juga tidak memastikan `order_id` dimiliki `shop_id`, dan `SyncJob::status($shopId, $jobId)` memakai job ID saja jika job ID dikirim (`app/models/SyncJob.php:292-308`).

**Perbaikan:** verifikasi CSRF untuk seluruh POST yang membuat/mengubah queue; validasi `shop_id` pada setiap job/order; ketika status memakai job ID, tetap tambahkan `WHERE shop_id = :shop_id`.

### P1 — Transport Shopee menonaktifkan verifikasi TLS

`ShopeeCurl::request()` menetapkan `CURLOPT_SSL_VERIFYPEER=false` dan `CURLOPT_SSL_VERIFYHOST=false` (`app/models/ShopeeCurl.php:70-78`). Semua request membawa cookie sesi Shopee, sehingga konfigurasi ini membuka risiko intersepsi sesi. `getPackage()` juga memakai jalur cURL sendiri sehingga timeout, diagnostics, dan klasifikasi error berbeda dari request lain.

**Perbaikan:** aktifkan verifikasi TLS dengan CA bundle sistem, satukan semua request melalui adapter, tambahkan timeout/status HTTP/API error yang konsisten, dan tutup handle cURL secara normal. Jangan mencatat cookie atau payload sensitif.

### P2 — Queue observability belum membedakan backlog yang sehat dan stuck

Worker serial memakai rate minimum 350 ms per detail dan berhenti setelah 100 loop (`bin/sync-worker.php:23-30, 145-250`). Runtime memperlihatkan backlog ribuan detail serta job history yang telah berjalan sejak 28 September. Observer sudah menghitung jumlah, tetapi belum memberi umur task tertua per toko, throughput per jam, retry age, atau alasan parent tidak selesai.

**Perbaikan:** tambahkan metrik backlog age/throughput/error category per shop dan dashboard status yang menyebut denominator; ukur kapasitas sebelum mengubah concurrency. Pertahankan satu runner resmi dan lease DB sebelum menambah paralelisme.

### P2 — Paket dan item order masih memakai update tanpa scope tambahan

`PackageSynchronizer::refresh()` mengubah `orders` berdasarkan `id` saja (`app/helpers/PackageSynchronizer.php:16-21`), dan `processOrder()` juga meng-update order/item berdasarkan ID (`app/helpers/SyncWorkerTasks.php:164-205, 221-247`). Jalur queue normal memang berasal dari shop tertentu, tetapi endpoint manual dapat memasukkan ID yang tidak dimiliki toko.

**Perbaikan:** validasi kepemilikan sebelum enqueue dan tambahkan `shop_id` pada semua update yang menerima konteks toko; validasi ID order pada respons detail Shopee sebelum menyimpan.

## Rencana pengerjaan

1. **Fondasi dan keselamatan data:** canonical schema + migrasi `sync_pages`; transaksi enqueue; validasi cookie/source shop; CSRF dan ownership pada route queue.
2. **Ketahanan queue:** batas retry detail, status `failed/partial`, retry manual, klasifikasi error Shopee, dan finalisasi parent yang deterministik.
3. **Rekonsiliasi produk/order:** scope kunci upstream per toko/channel, checkpoint produk yang selalu menyimpan failure, deduplikasi ID index, validasi identitas respons, dan cleanup berbasis run.
4. **Transport dan keamanan:** satu adapter HTTP, TLS verification, timeout/status API yang seragam, diagnostics tersanitasi, dan pengujian cookie tidak pernah masuk log.
5. **Observability dan kapasitas:** metrik backlog age, throughput, retry, per-shop fairness, serta observasi runtime minimal 24 jam. Setelah itu lakukan rekonsiliasi sampel dengan Seller Centre untuk jumlah produk, order, status, stok, dan paket.

## Gate verifikasi sebelum perubahan live

- Semua suite sinkronisasi yang ada tetap lulus.
- Test baru mencakup schema legacy, cookie mismatch, duplicate upstream IDs antar toko, permanent detail failure, checkpoint failure, CSRF, dan manual order ownership.
- PHP lint, `git diff --check`, dan smoke test worker satu job dengan fixture lokal.
- Tidak menjalankan `--once`, scheduler, installer, atau request Shopee dari worktree audit.
- Setelah kode masuk checkout runtime utama, pantau queue dan rekonsiliasi dengan timestamp UTC/WIB yang eksplisit; keberhasilan worker saja belum membuktikan data remote dan lokal sama.
