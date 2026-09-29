# Audit prioritas notifikasi

Tanggal: 2026-09-29. Basis source: `41339e0`. Lingkup: audit source dan SELECT agregat lokal; tanpa perubahan aplikasi, pengiriman pesan, atau pemanggilan Shopee. Ini rekomendasi, belum persetujuan implementasi. Tidak ada aturan tenggat/SLA Shopee yang diasumsikan; seluruh ambang contoh di bawah merupakan usulan pengaturan internal.

## Kondisi sekarang

- Lonceng menggunakan `StockAlert`, bukan pusat notifikasi lintas modul. Hanya tipe `low_stock` ditemukan pada source dan tabel lokal. Produk aktif dengan total stok <15 menghasilkan warning; stok nol menjadi urgent.
- Model sudah menyediakan fingerprint, severity, acknowledged/resolved timestamps dan silenced_until. Polling frontend 30 detik menampilkan maksimal delapan alert belum dibaca.
- Renderer masih khusus produk: judul produk, label Habis/Kritis, dan tautan ke Products. Menambahkan tipe baru memerlukan kontrak judul, alasan, tindakan dan tujuan yang sesuai, bukan hanya menambah baris database.

Temuan yang perlu dibereskan sebelum memperluas jenis:

1. Daftar belum dibaca kosong ditampilkan sebagai “Semua stok aman”. Ini salah ketika semua alert telah dibaca tetapi belum selesai. Pisahkan tidak ada notifikasi baru dari tidak ada masalah aktif.
2. Kenaikan warning ke urgent tidak menghapus acknowledged_at jika alert belum pernah resolved. Produk yang sebelumnya ditandai dibaca dapat habis tanpa kembali muncul sebagai belum dibaca.
3. Hitungan urgent berasal dari seluruh alert aktif, sedangkan badge/daftar menghitung yang belum dibaca. Ringkasan mencampurkan dua denominator.
4. unreadCount mengecualikan silenced_until yang masih berlaku, tetapi listActive tidak. Jika snooze dipakai nanti, badge dan daftar dapat berbeda.
5. Acknowledgement tersimpan di alert global, bukan per pengguna. Dibaca oleh satu operator berarti dibaca bagi semua operator; perlu keputusan produk sebelum notifikasi multioperator.
6. last_seen_at diperbarui setiap rekonsiliasi, sehingga urutan bukan umur insiden. occurrence_count belum ditambah pada rekonsiliasi. Gunakan first_seen/last_state_change untuk prioritas dan umur masalah.

Sumber: [model alert](../app/models/StockAlert.php), [controller](../app/controllers/back/ProcNotifications.php), [navbar](../app/views/panel/templates/navbar.php).

## Rekomendasi menurut urgensi bisnis dan kesiapan

| Prioritas | Notifikasi dan pemicu | Tindakan pengguna | Kesiapan / batasan |
| --- | --- | --- | --- |
| 1 | Pesanan belum diserahkan mendekati/melewati batas kirim | Buka pesanan dan proses pengiriman | ship_by_date tersimpan sebagai integer; status mentah dan detail_synced_at tersedia. Harus validasi semantik deadline dan status belum dikirim; jangan memakai sync_status=active karena mencakup shipped. |
| 1 | Sesi toko perlu diperbarui atau akses modul terblokir | Perbarui koneksi / periksa akses modul | Status/error tersedia di shop dan snapshot. Bedakan expired dari forbidden/error jaringan; akses Ads gagal tidak selalu berarti seluruh toko terputus. |
| 1 | Sinkronisasi pesanan/produk berhenti atau data operasional terlalu lama | Buka Sinkronisasi dan lihat penyebab | Jadwal, enabled, last_success, last_error, antrean/retry tersedia. Evaluasi terhadap interval yang berlaku dan kemajuan aktual; jangan melaporkan antrean besar yang masih maju sebagai macet. |
| 2, naik bila mendekati batas internal | Percakapan pembeli belum dijawab terlalu lama | Buka percakapan | Unread dan timestamp tersedia, juga pesan direction. Unread bukan unanswered; perlu hitung pesan masuk pertama yang belum dijawab dan cocokkan pesan keluar yang lebih baru. Cakupan daftar/paginasi serta jam layanan perlu diverifikasi. |
| 2, naik bila saldo habis dan iklan aktif terkonfirmasi | Saldo iklan menipis/habis | Buka Iklan dan periksa saldo | Adapter memetakan ads_credit.total, is_low_balance, low_balance_status, ads_toggle/has_ads. Data tidak tersedia sekarang dinormalisasi ke nol oleh adapter, sehingga nol tidak boleh otomatis dianggap saldo habis. Verifikasi field sumber, freshness dan iklan aktif. |
| 1 bila ada tenggat dekat, fase setelah mapping | Retur/refund/pembatalan menunggu respons penjual | Buka kasus terkait | Data Finance tentang return tidak membuktikan adanya tugas respons. Endpoint kasus, pemilik tindakan, status, deadline, dan tautan perlu dipetakan dahulu. |
| 2, fase rating berikutnya | Rating rendah yang perlu tinjauan sesuai aturan toko | Buka rating / tinjau respons | Target dan aturan sudah ada, tetapi discovery rating dan sender belum tersedia. Jangan mengirim notifikasi dari konfigurasi persona saja. |
| Saat worker nanti aktif | Automation gagal berulang atau job lewat batas tunggu | Periksa koneksi/model dan job gagal | Sekarang hanya konfigurasi dan tes sintetis. Belum ada worker yang pantas dilaporkan macet; keberhasilan tes bukan jaminan automation aktif. |

Urutan implementasi yang disarankan: rapikan lifecycle alert, lalu sesi/akses dan kesehatan sinkronisasi; lanjut tenggat pengiriman setelah status/deadline diverifikasi, kemudian chat dan saldo iklan. Urgensi bisnis tenggat kirim tinggi, tetapi alert harus menunggu sumber datanya benar.

## Bukti kesiapan data, bukan jumlah masalah aktual

SELECT agregat saat audit menunjukkan 15.522 order lokal, 9.756 memiliki ship_by_date >0. Dalam jendela tujuh hari menurut created_at dibanding UTC_TIMESTAMP, 1.270 dari 1.353 memiliki nilai tersebut. Angka ini menunjukkan cakupan field saja; tidak membuktikan deadline valid, status terkini, atau jumlah order yang terancam terlambat. Timestamp created_at warisan juga harus dinormalisasi sebelum produksi.

162 percakapan lokal memiliki latest_message_at. Ini tidak membuktikan semua percakapan telah diambil atau mengetahui durasi belum dijawab. Tabel alert hanya memiliki tipe low_stock saat SELECT dilakukan. Tidak ada data pelanggan atau secret dimasukkan ke catatan.

Sumber kesiapan: [sinkron detail order](../app/helpers/SyncWorkerTasks.php), [schema](../database/schema.sql), [jadwal sinkronisasi](../app/models/BackgroundSync.php), [chat](../app/models/ChatMonitor.php), [adapter iklan](../app/models/ShopeeCurl.php). Rating foundation: [memory](../memory.md).

## Aturan agar lonceng tetap berguna

- Urgent berarti ada dampak nyata dan pengguna perlu bertindak segera. Pesanan masuk normal, sinkron berhasil, rating positif biasa, atau jadwal promosi mendatang cukup aktivitas/ringkasan.
- Satu insiden per toko, jenis dan entitas dengan fingerprint stabil. Gabungkan beberapa pesanan/chat dalam ringkasan toko tanpa menghilangkan akses ke entitas rinci.
- Dibaca bukan selesai. Selesaikan otomatis hanya ketika sumber yang cukup baru mengonfirmasi kondisi pulih; kegagalan fetch tidak berarti pulih.
- Pengingat ulang muncul karena naik tingkat, tenggat mendekat, atau interval pengingat yang disepakati; bukan setiap polling.
- Jika sesi expired menyebabkan beberapa modul stale, kelompokkan sebagai satu akar masalah dengan daftar dampak. Jangan membuat spam alert turunan.
- Ambang internal dapat diatur per toko. Contoh diskusi: tenggat kirim warning <=24 jam/urgent <=6 jam; chat warning >=15 menit/urgent >=60 menit pada jam layanan. Ini bukan aturan Shopee dan belum ditetapkan sebagai default.
- Alert saldo memerlukan sumber yang tersedia, baru, dan iklan aktif. Nilai null/unknown tidak boleh menjadi nol; masalah freshness menjadi alert data, bukan saldo habis.
- Setiap item: identitas toko, masalah, alasan urgensi atau sisa waktu, waktu pembaruan, satu aksi yang membuka toko/entitas yang benar. Batasi isi chat/order dan jangan tampilkan token atau error provider mentah.
- HPP kosong dan selisih Finance adalah peringatan kualitas data lebih dahulu; jangan menyatakan rugi atau saldo hilang tanpa basis biaya/rekonsiliasi yang lengkap. Perubahan HPP tidak termasuk lingkup audit ini.

## Langkah audit lanjutan sebelum coding

1. Sampling tersanitasi untuk memvalidasi ship_by_date, zona waktu, dan status belum diserahkan; pastikan pembaruan status cukup cepat.
2. Tetapkan klasifikasi error sesi versus akses terbatas, serta batas freshness tiap sumber. Gunakan enabled dan retry/backoff yang berlaku.
3. Verifikasi kelengkapan percakapan dan arah pesan; verifikasi field saldo hadir pada payload iklan sebelum menormalisasi.
4. Pilih kebijakan dibaca per pengguna atau acknowledgement tim; tentukan snooze/escalation dan reset unread.
5. Uji duplikasi, eskalasi alert yang sudah dibaca, sumber stale, resolve/reopen, waktu batas, dan link entitas. Pada implementasi UI, gunakan UI UX Pro Max dan antislop seperti aturan proyek.

Verifikasi audit ini: source review, SELECT agregat saja, tautan relatif dokumen, dan git diff --check. Belum ada detector baru atau perubahan notifikasi yang diimplementasikan.
