# Rencana Automation Engine

Tanggal: 2026-09-29. Status: **rencana untuk diskusi; belum diimplementasikan atau diaktifkan**.

## Kebutuhan yang sudah diputuskan

- Halaman baru bernama **Automation Engine**, usulan route `/panel/automation`.
- Modul pertama adalah membalas **rating/ulasan pesanan**, bukan chat atau pesan status pesanan.
- Pengguna memilih **AI membalas otomatis berdasarkan aturan**, untuk **semua bintang dengan aturan berbeda**.
- Tetap mendukung banyak toko dengan aturan dan identitas masing-masing.
- Tahap saat ini hanya audit dan perencanaan. Tidak membuat halaman, tabel, scheduler, worker, atau mengirim reply baru.
- UI mengikuti UI UX Pro Max dan antislop serta gaya Shopdash yang sudah disepakati. Dokumentasi rencana dipush sesuai instruksi Git yang berlaku.

Bukti endpoint, field, dan keterbatasan ada di [audit API](rating-api-audit-20260929.md). Semua nama file, tabel, route, interval, dan batas operasional di bawah adalah **usulan**, bukan fitur yang sudah tersedia.

## Rekomendasi arsitektur

Gunakan **antrean persisten di database dan worker automasi terpisah** dari worker sinkronisasi pesanan. Tidak perlu Redis atau framework baru untuk tahap awal.

Alur:

```text
Scheduler automasi per toko
  -> ambil rating dan checkpoint
  -> deduplikasi serta evaluasi aturan
  -> AI menyusun reply
  -> validasi isi dan kebijakan
  -> baca ulang rating / identitas toko
  -> kirim sekali
  -> rekonsiliasi hasil dari Shopee
  -> catat hasil dan alasan di Automation Engine
```

Alasannya: panggilan AI dan pengiriman reply tidak boleh menahan antrean pesanan yang masih berjalan. Gunakan pola lease, checkpoint, retry, lock, dan log yang sudah dikenal proyek, tetapi pisahkan lifecycle task automasi dari `sync_job_orders`.

- Satu scheduler/runner resmi untuk automasi, tanpa proses duplikat. Implementasi awal dapat memakai CLI PHP dan LaunchAgent, mengikuti lingkungan proyek.
- Jadwal automasi disimpan di database. UI membaca status lokal dan tidak menjalankan loop selama halaman terbuka.
- Worker automasi punya batas waktu eksekusi, lease/heartbeat, dan pemerataan antar toko.
- Awalnya satu pengiriman aktif per toko; koordinasikan anggaran request dengan sinkronisasi yang ada supaya tetap adil.
- Poll setiap 10 menit dan batas batch kecil dapat menjadi konfigurasi awal untuk pilot; ini usulan internal, **bukan** limit resmi Shopee. Angka final menunggu pengujian transport dan volume backlog.
- Mesin inti mengenal tipe task, namun MVP hanya `rating_reply`. Jangan membangun editor node/flow umum sebelum ada kebutuhan automasi kedua.

## Aturan balasan AI

| Bintang | Tujuan balasan | Batasan |
| --- | --- | --- |
| 5 | Terima kasih secara spesifik jika isi review mendukung; ramah dan ringkas | Jangan mengarang detail pengalaman, identitas, atau manfaat produk |
| 4 | Apresiasi dan tanggapi masukan yang benar-benar disebutkan | Jangan menyimpulkan adanya kerusakan bila pembeli tidak menyebutkannya |
| 3 | Akui pengalaman yang belum memuaskan dan arahkan langkah bantuan yang diizinkan toko | Jangan menyalahkan pembeli/kurir atau menjanjikan solusi yang belum disetujui |
| 2 | Empati, akui keluhan, ajak pembeli ke kanal bantuan yang sesuai | Tidak menjanjikan refund, ganti barang, atau kompensasi tanpa kebijakan eksplisit |
| 1 | Tanggapan tenang dan relevan, dengan jalur bantuan yang jelas | Tidak membantah secara agresif, meminta rating diubah, atau membuka data pesanan pribadi |

Bintang bukan satu-satunya sinyal: review bintang 5 bisa berisi keluhan; review bintang 1 bisa tanpa teks. Aturan mempertimbangkan isi, bahasa, dan kebijakan toko. Review tanpa teks mendapat balasan singkat sesuai bintang tanpa menciptakan detail.

Konfigurasi per toko: gaya bahasa, sapaan, panjang internal, bahasa balasan, kebijakan dukungan yang boleh disebut, aturan setiap bintang, frasa terlarang, pengecualian, jadwal, dan batas pengiriman/biaya AI. Semua bintang termasuk cakupan; pengecualian berdasarkan isi/kondisi tetap memungkinkan.

Kasus konflik, data pribadi yang harus disunting, ancaman, atau permintaan keputusan di luar kebijakan dapat masuk **Perlu ditinjau**, bukan otomatis mengirim respons yang tidak aman. Ini usulan aturan pengecualian yang harus disepakati; rating rendah tidak otomatis selalu ditahan.

## Kontrak dan validasi AI

- Provider/model belum dipilih. Buat adapter konfigurasi server-side agar tidak mengikat desain awal pada vendor yang belum disetujui.
- Input minimum: bintang, teks review yang relevan, nama produk bila diperlukan, bahasa/tone toko, dan kebijakan balasan. Tidak perlu user ID, alamat, nomor pesanan, cookie, token, atau gambar pembeli.
- Teks review adalah data tidak tepercaya: instruksi seperti “abaikan aturan” tidak boleh mengubah prompt sistem, memanggil tools, memilih toko, atau menentukan endpoint.
- Output terstruktur yang diusulkan: `action` (`reply`/`review`), `reply_text`, `reason_code`, `language`. Worker menentukan ID dan target; AI hanya menyusun isi dan rekomendasi.
- Validasi deterministik: teks tidak kosong, panjang sesuai batas yang sudah diverifikasi, tidak membocorkan data pribadi, tidak mengandung janji/tautan/frasa terlarang, dan kebijakan/version task masih berlaku.
- Kegagalan format dapat mendapat satu regenerasi terbatas; kegagalan berulang masuk tinjauan. Jangan mengirim output mentah provider.
- Confidence AI saja tidak cukup sebagai izin kirim. Persetujuan aktivasi, aturan, freshness, dan validasi tetap menjadi gerbang.
- Simpan model, versi prompt/aturan, penggunaan token/biaya bila tersedia, serta hasil validasi. Batasi akses/retensi teks review dan respons; jangan log credential atau payload autentikasi.

## Deduplikasi, race, dan hasil tidak pasti

- Kunci deduplikasi lokal: `(local_shop_id, source_comment_id, action_type)` untuk first reply. Jangan memasukkan versi aturan ke kunci tersebut sehingga perubahan aturan tidak membalas rating yang sama dua kali.
- Pastikan rating belum memiliki reply sebelum menyiapkan task dan sebelum pengiriman. Anomali filter `reply_status=1` sudah ditemukan dalam capture.
- Cocokkan `order_id` dengan rating sumber dan identitas remote shop dengan sesi toko. Jangan mengandalkan label UI.
- Catat intent/attempt secara durable sebelum POST. Crash sesudah kirim harus dapat ditemukan kembali.
- HTTP 200 saja bukan sukses; periksa `code`, kemudian lakukan pembacaan ulang untuk memastikan reply yang diharapkan tercatat.
- Timeout/putus koneksi setelah POST menjadi **Belum pasti**, tidak otomatis kembali ke antrean kirim. Rekonsiliasi dulu; bila belum dapat dipastikan, tahan untuk tinjauan.
- POST duplikat dari worker bersamaan dicegah dengan unique constraint dan atomic claim/lease. Namun tidak ada bukti API Shopee menyediakan idempotency key atau operasi compare-and-set; **exactly-once di sisi remote tidak dapat dijanjikan**.
- Balasan manual bersamaan masih berpotensi race setelah preflight. Status tersebut harus terdeteksi dalam rekonsiliasi, bukan ditimpa diam-diam.
- Retry GET/AI memakai backoff terbatas. Retry write hanya jika bukti menunjukkan belum terkirim dan kebijakan retry mengizinkan; 429/access error tidak boleh memicu loop cepat.
- Pause global/per toko dicek lagi sebelum POST. Request yang sudah terkirim tidak dapat ditarik kembali oleh tombol Pause; UI menjelaskan kondisi tersebut.

## Discovery, backlog, dan checkpoint

- Pisahkan discovery/backfill dari pengiriman. Membalas sambil berjalan pada pagination “belum dibalas” dapat menggeser halaman dan melewatkan rating.
- Prioritas: scan daftar dengan filter stabil/ALL dalam jendela terukur, simpan ID dan status lokal, lalu kirim lewat antrean. Pilihan final menunggu audit pagination.
- Checkpoint tidak boleh dianggap selesai sebelum seluruh halaman relevan terverifikasi; gunakan overlap waktu dan deduplikasi untuk menangkap perubahan/rating baru.
- Ruang lingkup awal perlu diputuskan: rating baru setelah aktivasi atau backlog sejak tanggal tertentu. Jangan membalas seluruh riwayat lama tanpa konfigurasi yang jelas.
- Batas usia rating dan kemampuan membalas rating lama belum diketahui. Task yang tidak eligible ditandai dengan alasan, tidak dipaksa lewat API.

## Penyimpanan yang diusulkan

| Tabel | Tanggung jawab |
| --- | --- |
| `automation_rules` | Toko, tipe automasi, enabled/mode, filter, aturan per bintang, versi, jadwal, batas biaya/volume |
| `shop_ratings` | Identitas rating dan toko, order ID sumber, bintang, teks minimum, status reply, waktu, freshness; unique toko+rating |
| `automation_checkpoints` | Progress discovery per toko/filter/jendela; cursor hanya setelah semantiknya terverifikasi |
| `automation_tasks` | Task unik, versi aturan, reply tervalidasi, state, lease, retry, target, deadline |
| `automation_attempts` | Attempt ID, request intent/hash, waktu, outcome/diagnostic tersanitasi, hasil rekonsiliasi |
| `automation_runs` | Ringkasan tiap siklus: ditemukan, disiapkan, berhasil, dilewati, ditinjau, belum pasti, error |

State task usulan: `discovered -> generating -> ready -> sending -> verifying -> succeeded`, dengan cabang `skipped`, `needs_review`, `retry_wait`, `unknown`, dan `failed`. Recovery untuk `sending` yang lease-nya habis harus rekonsiliasi dulu, bukan langsung POST ulang.

## Rancangan halaman Automation Engine

Gunakan bahasa UI Indonesia dan shell panel yang ada. Riset UI UX Pro Max tentang status/feedback dipakai untuk kejelasan proses; rekomendasi landing page/tema baru yang tidak cocok diabaikan lewat antislop dan aturan proyek.

- Header: **Automation Engine**, pemilih toko berlogo dengan menu vertikal, status aktif/jeda, waktu scan terakhir, jadwal berikutnya.
- Modul **Balas rating**: ringkasan aturan bintang 1–5, status provider, cakupan tanggal, dan mode. Jangan menampilkan modul automasi fiktif yang belum dibuat.
- Tampilan **Aturan**, **Antrean**, dan **Riwayat**; pada layar kecil navigasi tetap dapat disentuh tanpa overflow.
- Antrean menampilkan toko, rating, cuplikan review, usulan/hasil reply, status, dan alasan. Detail dibuka saat diperlukan; data pribadi tidak ditampilkan tanpa manfaat operasional.
- Aksi sesuai state: **Simulasikan aturan**, **Aktifkan balasan otomatis**, **Jeda**, **Tinjau**, **Cek hasil** untuk hasil belum pasti. Tidak ada tombol “ulang semua” yang mengirim kembali task unknown.
- Aktivasi menampilkan cakupan toko/bintang/tanggal, batas operasional, dan contoh keluaran aktual. Aktivasi produksi tidak termasuk izin audit sekarang.
- Status menampilkan angka berdasarkan database dan waktu pengamatan. Hindari persentase total jika discovery belum selesai.
- Empty/loading/error/stale/credential-expired/paused diberi penjelasan dan tindakan yang sesuai. Gaya terang/gelap, focus, 44px target, serta 320/500/999/1600px mengikuti aturan proyek.

## Tahap implementasi setelah rencana disepakati

1. **Lengkapi kontrak baca dan transport.** Capture pagination, eligibility, limit teks, identitas sesi; uji baca backend. Kriteria selesai: discovery tidak melewatkan halaman, sesi cocok, batas yang belum diketahui tercatat.
2. **Halaman dan simulasi.** Schema/adapter read-only, aturan semua bintang, preview AI, ledger task; mode kirim belum aktif. Kriteria: tidak ada POST reply selama simulasi.
3. **Worker dan rekonsiliasi.** Lease, dedup, preflight, bounded retry, unknown handling, pause, budget. Uji dengan stub tanpa mengirim pesan ke pembeli.
4. **Pilot terkontrol yang diaktifkan pengguna.** Satu toko, backlog/tanggal dan volume kecil yang disepakati, contoh teks dapat ditinjau. Uji write tidak tersirat dari audit ini.
5. **Perluas lintas toko.** Setelah hasil remote cocok, pantau sukses/error/unknown, latensi antrean, dan biaya. Semua bintang tetap didukung oleh aturan yang disetujui, bukan diluncurkan tanpa observasi.

Usulan source baru: `RatingMonitor`, `RatingReplyPolicy`, `AutomationEngine`, adapter AI/provider, CLI scheduler/worker, controller `ProcAutomation`, view `automation.php`, dan browser script yang sesuai. Nama final mengikuti struktur repo; jangan menambah dependency antrean eksternal sebelum diperlukan.

## Pengujian yang dibutuhkan

- Kontrak daftar: seluruh bintang, null/non-null reply, item tersembunyi/follow-up, respons malformed, cursor macet, batas halaman, perubahan daftar saat scan.
- Pemisahan toko: sesi toko salah, ID rating/order tidak cocok, aturan/credential satu toko tidak dipakai untuk toko lain.
- AI: review kosong, sindiran, keluhan pada bintang tinggi, prompt injection, janji terlarang, data pribadi, output invalid, rate limit/provider timeout, batas biaya.
- Antrean: dua worker, crash sebelum/selepas POST, duplicate enqueue, lease kedaluwarsa, reply manual dari luar, rules berubah saat task menunggu, pause saat task berjalan.
- Rekonsiliasi: HTTP 200 dengan code error, sukses dengan read-back tertunda, timeout dengan balasan sudah tersimpan, reply berbeda dari intent.
- UI: filter/pagination/status, aturan per bintang, semua tema dan ukuran, keyboard, empty/error states. Mock seluruh POST balasan dalam browser test.
- Operasional: antrean pesanan tidak kelaparan karena AI; rollback kode tidak menghapus ledger attempt atau me-reset task unknown.

## Keputusan yang masih terbuka

1. Provider/model AI dan batas biaya harian/bulanan. Pilihan ini menentukan tujuan pengiriman teks review dan perlu disepakati sebelum integrasi.
2. Tone/sapaan tiap toko, batas panjang internal, dan kebijakan bantuan yang boleh disebut AI.
3. Backlog: hanya rating baru atau mulai tanggal tertentu; urutan prioritas rating lama/baru.
4. Jam operasi, batas reply per toko, serta pengecualian isi yang harus masuk tinjauan.
5. Transport write yang terbukti didukung dan kebutuhan konteks browser. Jika cookie-only tidak cukup, opsi integrasi perlu diaudit lagi; jangan memakai replay token keamanan statis.

Keputusan ini tidak mengubah pilihan pengguna: auto-reply AI untuk seluruh bintang tetap targetnya. Dokumen ini tidak mengklaim fungsi tersebut sudah siap produksi.
