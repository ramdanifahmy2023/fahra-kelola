# Audit dan rencana peningkatan UI/UX Shopdash

Tanggal: 12 Oktober 2026
Branch: `audit/ui-ux-20261012`
Worktree: `Worktree-codex/ui-ux-audit-20261012`

## Tujuan

Membuat seluruh panel Shopdash terasa sebagai satu produk operasional yang mudah dipindai, cepat dipahami, dan aman dipakai pada layar kecil maupun desktop. Rencana ini menjaga perilaku bisnis, identitas toko, tema terang/gelap, dan komponen yang sudah terbukti di halaman Finance, Automation, Boost, Ads, Notifications, serta picker toko.

## Cara audit dan batasan

Audit ini membaca source branch `main` pada worktree baru. Bukti utama berasal dari template PHP, JavaScript halaman, CSS sumber, arsitektur panel, serta audit dan tes UI yang sudah tersimpan di `ops/` dan `tests/`. Browser suite belum dijalankan dalam audit ini, jadi ukuran aktual, kontras seluruh halaman, perilaku dengan data produksi, dan pengalaman perangkat sentuh tetap menjadi gerbang verifikasi pada fase implementasi.

Panduan UI UX Pro Max menghasilkan arah **Minimalism & Swiss Style** untuk dashboard operasional: hierarki jelas, grid stabil, kontras tinggi, motion ringan, dan kepadatan standar. Arah ini diterapkan sebagai filter terhadap visual Shopdash yang sudah ada, bukan sebagai alasan untuk mengganti identitasnya. Antislop menjaga agar teks, warna, ikon, dan dekorasi tetap punya fungsi yang dapat dijelaskan.

## Ringkasan temuan

Shopdash sudah memiliki fondasi yang baik: tema hangat dengan oranye sebagai aksen, tema gelap, logo toko nyata, Material Symbols, disclosure native, status dinamis, dan beberapa halaman dengan pengujian responsif yang kuat. Masalah terbesar muncul karena pola lama dan pola baru hidup berdampingan.

1. **Bahasa visual belum satu sistem.** Panel memakai kombinasi `card`, `rounded-xl`, `rounded-2xl`, border opacity, dan ukuran teks yang berbeda antar halaman. Dashboard, Produk, Pesanan, Toko, Promosi, Laporan, dan halaman operasional tidak memiliki satu pola header, toolbar, surface, status, dan empty state.
2. **Shell global terlalu padat pada ruang kecil.** Sidebar memiliki label grup 9px dan item navigasi 12px (`app/views/panel/templates/sidebar.php:5,18,35,40`), navbar memuat banyak kontrol sekaligus (`navbar.php:1-62`), dan area utama memakai padding yang berubah-ubah (`header.php:89-94`).
3. **Kepadatan teks terlalu tinggi.** Ukuran 9 sampai 11px dan opacity rendah tersebar pada metadata, status sinkronisasi, label kartu, tabel, dan navbar. Contohnya `products.php:47`, `orders.php:51`, `order_row.php:18,31,67,89,115,190`, `promotions.php:20-23`, dan `reports.php:21,37,41,45`.
4. **Kontrol kecil belum konsisten dengan target sentuh.** Beberapa kontrol Orders berukuran 32px (`orders.php:31-44`), tombol pagination dan select kecil berulang di Produk, Pesanan, Pelanggan, dan Laporan. Target umum untuk kontrol operasional adalah minimal 44px tinggi atau area klik 44px.
5. **Selector toko adalah jalur kerja utama, tetapi kontraknya belum konsisten.** Shared picker menampilkan logo dan memiliki keyboard dasar (`templates/shop-picker.php:18-42`, `public/assets/js/shop-select.js:1-102`), tetapi nama terpilih dipaksa `truncate` (`shop-select.js:55`, `reports.php:60`) walau kontrak proyek meminta nama panjang dapat dibaca dan opsi tetap satu kolom vertikal.
6. **Tabel masih menjadi sumber tekanan horizontal.** Products, Orders, Shops, Customers, Reports, Ads, dan Product Movement memakai tabel lebar atau `min-width`; beberapa sudah punya wrapper scroll, tetapi tidak semua memberi pola mobile yang sama. Reports sengaja memakai `min-w-[820px]` dan grafik `min-w-[680px]` (`reports.php:37,42,45`), sedangkan Customers menaruh petunjuk geser kecil 11px (`customers.php:87-88`).
7. **Detail teknis dan status normal sering tampil bersamaan.** Pola ini sudah diperbaiki pada sebagian Ads/Boost/Automation, tetapi masih terlihat pada halaman produk, pesanan, sinkronisasi, promosi, serta beberapa kartu Dashboard. Bahasa seperti `background`, `worker`, `database lokal`, dan job ID perlu menjadi detail yang bisa dibuka ketika relevan.
8. **Aksesibilitas global belum selesai.** Sidebar hanya memberi `aria-current` pada sebagian item (`sidebar.php:20-22,30,37,43`), tooltip global hanya muncul lewat `pointerover` (`header.php:63-86`), dan trigger akun berupa `div role="button"` (`navbar.php:53`) membutuhkan perilaku keyboard yang eksplisit. Beberapa tabel belum memiliki caption atau nama yang jelas.
9. **Status visual belum sepenuhnya semantik.** Sebagian halaman sudah memberi label teks dan ikon, tetapi halaman Reports memakai palet chart yang ditulis langsung di JavaScript (`reports.php:109`) dan mengandalkan warna untuk membedakan seri. Warna harus dipetakan ke token, tetap terbaca di tema gelap, dan memiliki bentuk atau label yang cukup.
10. **Verifikasi lintas halaman belum memiliki satu gerbang.** Tes per halaman sudah cukup kuat pada Finance, Ads, Boost, Automation, Extensions, Notifications, Shop Picker, dan Workspace Comfort. Belum ada matriks audit tunggal untuk seluruh panel dengan state kosong, gagal, stale, nama panjang, zoom 200 persen, reduced motion, keyboard, dua tema, dan empat sampai lima lebar viewport.

## Prinsip desain yang dipakai

- Pertahankan palet Shopdash: `#e98425` sebagai aksen utama, paper hangat untuk terang, cokelat gelap untuk stage, dan hijau sebagai status sukses. Jangan menambah warna produk baru tanpa token dan alasan status.
- Gunakan satu skala type: body 16px sebagai dasar, label minimum 12px, metadata 12 sampai 14px, heading halaman 28 sampai 36px, angka utama 24 sampai 32px. Ukuran 9 sampai 11px hanya untuk metadata teknis yang memang sekunder dan tidak menjadi satu-satunya cara membaca status.
- Gunakan surface dan spacing yang konsisten: radius 12px untuk panel, 8px untuk kontrol, gap 16px antar blok, gap 24px antar section, border 1px, shadow ringan hanya untuk surface yang perlu terangkat.
- Tampilkan satu tindakan utama per konteks. Aksi sekunder memakai tombol netral dengan label jelas. Aksi icon-only wajib memiliki accessible name dan tooltip yang juga dapat dibuka lewat keyboard atau diganti label pada mobile.
- Semua status memiliki teks, ikon bila membantu, dan warna semantik. Warna tidak boleh menjadi satu-satunya pembeda.
- Gunakan progressive disclosure untuk definisi metrik, detail teknis, dan prosedur pemulihan. Status yang membutuhkan tindakan tetap terlihat.
- Gunakan native button, link, `details`, `dialog`, dan form control. Hindari `div role="button"` bila elemen native dapat dipakai.
- Pertahankan motion ringan, sekitar 150 sampai 250ms untuk state UI. Hormati `prefers-reduced-motion` dan jangan menganimasikan layout yang membuat fokus berpindah.

## Prioritas temuan dan tindakan

| Prioritas | Temuan berbasis source | Dampak | Rencana tindakan |
| --- | --- | --- | --- |
| P0 | Shell global memiliki navigasi, tooltip, account trigger, theme menu, notifications, progress sync, dan drawer dalam satu lapisan (`templates/header.php`, `navbar.php`, `sidebar.php`). | Fokus keyboard, orientasi, dan ruang mobile tidak konsisten. | Buat kontrak shell: skip link, landmark yang bernama, `aria-current` lengkap, trigger native, focus return, overlay focus trap, sticky offset, dan state drawer yang dapat diprediksi. |
| P0 | Selector toko adalah kontrol lintas Products, Orders, Customers, Reports, Ads, Finance, Sync, Boost, dan Promosi. Nama aktif di-truncate dan perilaku dynamic/static berbeda (`shop-picker.php`, `shop-select.js`, `reports.php:51-70`). | Pengguna dapat salah mengenali toko atau tidak dapat membaca nama panjang. | Jadikan picker satu komponen dengan logo, nama wrap, daftar satu kolom, tinggi minimum 44px, scroll vertikal, keyboard Arrow/Home/End/Escape, focus return, loading/disabled state, dan pelestarian filter. |
| P0 | Kontrol kecil 32px dan teks 9 sampai 11px masih tersebar (`orders.php:31-44`, `sidebar.php:5,18`, `order_row.php`, `promotions.php`). | Sulit disentuh, dibaca, dan dipindai terutama pada layar kecil atau zoom tinggi. | Naikkan target sentuh, normalisasi type tokens, dan audit kontras berdasarkan computed style di dua tema. |
| P0 | Tabel dan kartu data memakai pola mobile yang berbeda. Reports dan beberapa halaman memakai `min-width` besar, sementara Orders punya desktop/mobile render terpisah. | Risiko overflow, kehilangan konteks kolom, dan scroll horizontal yang membingungkan. | Tetapkan pola per tipe data: tabel scroll dengan sticky first column untuk data komparatif, card disclosure untuk baris operasional, dan `data-label` untuk tabel yang benar-benar reflow. |
| P0 | Status loading/error/empty/stale belum diseragamkan. Beberapa halaman memakai overlay `page-loader`, teks teknis permanen, atau pesan error mentah. | Pengguna sulit membedakan belum memuat, belum pernah ada data, data lama, dan gagal mengambil. | Buat state contract global: loading skeleton atau reserved space, empty dengan next action, stale dengan timestamp, error dengan recovery, dan status live yang tidak menggantikan konten. |
| P1 | Hierarki header dan toolbar berbeda antar halaman: Products/Orders/Shops memakai judul 2xl, Reports/Promotions memakai eyebrow, Finance/Ads memakai surface khusus. | Navigasi visual terasa berpindah produk. | Buat komponen pola halaman: `page-header`, `page-toolbar`, `page-scope`, `surface`, `metric-card`, `status-badge`, `empty-state`, `data-table`, `disclosure`. Terapkan bertahap tanpa mengubah endpoint. |
| P1 | Detail teknis tetap terlihat pada alur utama, terutama sinkronisasi, metadata Ads, instruksi Promosi, dan status background. | Beban baca tinggi dan keputusan penting tenggelam. | Ringkas status normal menjadi satu baris; pindahkan rumus, sumber, job ID, dan cara kerja ke disclosure “Detail data” atau “Cara kerja”. |
| P1 | Chart Reports menggunakan warna hardcoded dan hanya memiliki `role="img"` (`reports.php:109-123`). | Seri sulit dibedakan pada tema tertentu dan tidak memberi alternatif data bagi pembaca layar. | Tokenize chart palette, tambahkan marker/label seri, tabel data tersembunyi atau disclosure, dan state no-data yang menjelaskan periode. |
| P1 | Modal dan dialog ada di Shops, Customers, Products, Finance, Boost, dan Automation dengan pola header/footer berbeda. | Focus return, ukuran layar, dan aksi utama tidak konsisten. | Satu dialog contract: labelledby/describedby, close icon berlabel, Escape, backdrop, scroll region, action footer, focus return, dan safe area. |
| P1 | Beberapa icon-only button atau icon dekoratif belum konsisten `aria-hidden`, alt, dan label (`shops.php:8-10,118`, Products, Reports). | Pembaca layar dapat menerima teks ganda atau nama aksi yang hilang. | Audit semua Material Symbols dan gambar; decorative icon `aria-hidden`, logo decorative `alt=""` saat nama ada, gambar produk diberi alt bermakna bila menjadi informasi. |
| P2 | Motion dan state feedback tersebar di CSS/JS. Ada transition dan animation page-specific, tetapi tidak ada kontrak global selain sebagian login. | State terasa tidak seragam dan dapat mengganggu pengguna reduced-motion. | Tambahkan token duration/easing, `prefers-reduced-motion`, dan aturan bahwa animasi menyampaikan perubahan status saja. |
| P2 | Copy campur Indonesia, Inggris, dan istilah implementasi: `Sync sekarang`, `Promotion operations`, `Customer directory`, `background`, `worker`, `job`. | Menambah beban pemahaman dan membuat navigasi tidak konsisten. | Pilih istilah Indonesia untuk alur utama: Sinkronisasi, Pelanggan, Promosi, Perbarui. Simpan istilah teknis hanya dalam detail yang relevan. |
| P2 | Asset CSS memakai Tailwind build, tetapi pola halaman juga menaruh `<style>` inline di view (`products.php:17-27`). | Token dan responsive rule sulit dirawat. | Pindahkan aturan reusable ke `resources/css/input.css`, gunakan class pattern yang terdokumentasi, lalu build asset dari source. |

## Audit per halaman

| Area | Bukti saat ini | Fokus UX | Hasil yang dituju |
| --- | --- | --- | --- |
| Dashboard dan Finance | Dashboard dirender melalui Finance (`app/views/panel/index.php:1`), dengan activity/operations terpisah dan banyak status data. | Pisahkan angka utama, freshness, peringatan, dan detail. Pertahankan partial-data warning serta periode WIB. | Satu ringkasan yang menjawab kondisi hari ini, satu jalur tindakan, dan disclosure untuk metodologi. |
| Produk | Tabel 6 kolom, thumbnail 48px, sync status 11px, detail dialog (`products.php:28-191`). | Shop scope, stock critical, movement, pagination, nama panjang, gambar gagal, dan mobile card. | Toolbar ringkas dengan scope toko jelas; baris produk tetap terbaca pada 320px; status stok punya label dan aksi. |
| Pesanan | Desktop table dan mobile list terpisah, kontrol bulan 32px, metadata kecil (`orders.php:23-120`, `order_row.php`). | Konsistensi periode, status pembayaran/pengiriman, detail order, dan pemulihan sync. | Filter periode dan toko mudah dipindai; mobile card memprioritaskan nomor, produk, total, status, aksi. |
| Pelanggan | Header campur label Inggris, tabel scroll, alamat memakai tooltip/truncate, modal history (`customers.php:1-241`). | Pencarian identitas, alamat panjang, scope lintas toko, history, dan tooltip keyboard. | Identitas dan jumlah order dominan; alamat dibuka lewat disclosure atau dialog, bukan hover-only. |
| Toko | Tabel saldo/kredit/status, modal cookie, auto-sync script (`shops.php:1-180`). | Status koneksi, update cookie, konfirmasi hapus, dan data saldo yang bisa stale. | Status koneksi actionable; modal cookie memiliki petunjuk keamanan yang singkat dan error dekat field. |
| Laporan | 5 metric cards, ranking table `min-w-[820px]`, SVG chart, daily table (`reports.php:14-45`). | Periode, pembanding, chart accessibility, data coverage, dan scroll mobile. | Periode selalu terlihat; chart dapat dibaca tanpa warna; tabel mobile punya struktur yang jelas. |
| Iklan | Kartu per toko, metric grid, warning, daily details, source details (`ads.php:25-130`, `ads.js`). | Kurangi catatan normal, pertahankan stale/session warning, dan jaga channel/period semantics. | Kartu menjawab status, freshness, metrik, lalu detail data. |
| Promosi | Kartu per toko dengan 4 metrics, voucher/flash sale list, warning session (`promotions.php:1-90`). | Hapus eyebrow teknis dan label 10px; bedakan aktif, kosong, expired, gagal. | Kartu promosi mudah dipindai dan memiliki empty state per section. |
| Naikkan Produk | Sudah memiliki visual state, logo, thumbnail, disclosure, dan tes kuat (`boost.php`, `boost.js`, `boost-visual-ui.md`). | Jadikan ini referensi pola status, dialog, logo, dan responsive behavior untuk halaman lain. | Reuse kontrak yang sudah terbukti, jangan membuat pola baru. |
| Chat | Shell 3 kolom desktop, mode popup, detail mobile (`input.css:1-46`, `chat.php`, `chat.js`). | Focus trap popup, unread state, tinggi viewport, back behavior, dan read-only send state. | Chat tetap nyaman pada 320px dan popup mengembalikan fokus ke trigger. |
| Sinkronisasi | Header, filter toko, status live, dashboard dinamis (`sync.php`, `sync-status.js`). | Ganti bahasa implementasi dengan status pengguna, tampilkan progress dan recovery. | Pengguna memahami apa yang berjalan, toko mana yang terdampak, dan tindakan berikutnya. |
| Automation | Sudah memakai tiga tab dan aturan aksesibilitas yang kuat (`automation-visual-ui.md`). | Jadikan referensi untuk tabs, dirty state, dialog, dan progressive disclosure. | Pola tab/dialog dipakai ulang pada halaman yang relevan. |
| Keuangan | Surface, tabs, metric cards, pending breakdown, HPP dialogs (`finance.php`, `finance.js`). | Pertahankan kontrak angka/period, rapikan density dan shared control. | Finance menjadi benchmark untuk status data, disclosure, dan dialog. |
| Ekstensi | Section guide, download state, changelog details (`extensions.php`). | Hierarki download utama, error package, checksum, dan mobile. | Aksi versi terbaru dominan; riwayat tetap tersedia tanpa memenuhi layar. |
| Pergerakan Produk | Filter cards dan tabel dengan mobile reflow (`product-movement.php`). | Nama status, periode, table/card behavior, dan empty state. | Filter dapat dipahami tanpa jargon dan tabel tidak memotong nama produk. |

## Rencana implementasi

### Fase 0, baseline dan kontrak, P0

1. Simpan audit ini sebagai acuan branch.
2. Buat token UI di `resources/css/input.css`: type scale, surface, radius, border, focus ring, spacing, status tones, control height, dan motion.
3. Dokumentasikan kontrak komponen bersama: page header, toolbar, picker toko, status, empty/error/loading, table, disclosure, dialog, dan toast/live region.
4. Tetapkan fixture audit dengan nama toko panjang, nama produk panjang, gambar gagal, 0 data, data stale, sesi expired, partial data, error jaringan, dan nominal besar.

### Fase 1, shell panel dan navigasi, P0

1. Tambahkan skip link ke `main`, heading level yang konsisten, dan landmark bernama.
2. Ubah account trigger ke `<button>` dengan menu yang dapat dibuka Enter/Space, ditutup Escape, dan mengembalikan fokus.
3. Lengkapi `aria-current="page"` pada semua nav item; berikan state hover, active, focus, dan drawer mobile yang sama.
4. Rapikan navbar agar theme, search, notification, sync progress, dan account tidak saling menekan pada 320px.
5. Ganti tooltip hover-only dengan pola yang memiliki accessible description; jangan jadikan tooltip sebagai satu-satunya tempat label.
6. Uji sidebar dan navbar pada 320, 500, 999, dan 1600px, tema terang/gelap, keyboard, zoom 200 persen, dan reduced motion.

### Fase 2, selector toko dan shared controls, P0

1. Satukan server picker dan dynamic picker pada satu markup contract.
2. Pertahankan logo 24px di picker, nama wrap pada option, dan menu vertikal dengan max-height serta scroll.
3. Sediakan keyboard ArrowDown, ArrowUp, Home, End, Enter, Escape, focus return, dan state disabled/loading.
4. Pastikan perubahan toko benar-benar memuat data toko yang dipilih, mempertahankan filter yang relevan, dan reset pagination sesuai kontrak.
5. Normalisasi kontrol ke minimum 44px, termasuk date/select, pagination, icon-only button, dan action row.

### Fase 3, data surfaces, P0 sampai P1

1. Terapkan page header dan toolbar pada Produk, Pesanan, Pelanggan, Toko, Promosi, Laporan, Iklan, dan Sinkronisasi.
2. Terapkan state contract global dan hilangkan overlay loading yang menutupi konten bila reserved space atau skeleton sudah cukup.
3. Ringkas teks normal; pindahkan definisi metrik, sumber data, job ID, dan prosedur panjang ke disclosure.
4. Pilih pola mobile per halaman. Gunakan card disclosure untuk Orders dan Customers bila kolom terlalu banyak; gunakan scroll region dengan petunjuk singkat untuk data komparatif Reports dan Ads.
5. Tampilkan freshness dan scope sekali di tempat yang konsisten. Bedakan `diperbarui`, `belum tersedia`, `data lama`, dan `gagal dimuat`.

### Fase 4, workflow dan dialog, P1

1. Satukan dialog Products, Shops, Customers, Finance, Boost, dan Automation.
2. Pastikan validasi form dekat field, error memiliki `role="alert"`, input memiliki label yang terlihat, dan fokus pindah ke konteks yang gagal.
3. Tinjau destructive action, cookie update, HPP, koneksi AI, dan aksi Boost dengan copy yang konkret.
4. Tinjau Chat popup, notification panel, search dialog, dan sync progress untuk focus trap, safe area, Escape, dan focus return.

### Fase 5, data visualization dan final polish, P1 sampai P2

1. Tokenize chart palette Reports dan Ads. Tambahkan marker/label yang tidak bergantung pada warna.
2. Beri alternatif data tabel atau disclosure untuk chart.
3. Terapkan reduced motion, state transition, hover, active, disabled, dan pending secara konsisten.
4. Audit icon, image alt, label, heading order, and live region pada seluruh panel.
5. Bersihkan class dan style inline yang duplikatif setelah komponen bersama stabil.

## Kriteria penerimaan

Semua fase UI dinyatakan selesai bila seluruh kriteria berikut terpenuhi pada halaman yang berubah:

- Tidak ada horizontal page overflow pada 320, 500, 768, 999, dan 1600px. Scroll horizontal hanya berada di region data yang memang lebih lebar dan memiliki petunjuk.
- Tema terang dan gelap memakai token yang sama secara semantik. Teks biasa minimal 4.5:1, teks besar minimal 3:1, focus ring terlihat, dan status dapat dipahami tanpa warna.
- Semua kontrol operasional dapat dipakai dengan keyboard. Tab order mengikuti urutan visual; Enter/Space/Arrow/Escape bekerja sesuai peran; fokus tidak tertutup sticky header/footer.
- Kontrol sentuh memiliki tinggi atau area klik minimal 44px. Nama toko, produk, order, dan SKU panjang wrap atau memiliki disclosure operasional, bukan tooltip hover-only.
- Setiap halaman memiliki state loading, empty, stale, error, dan success yang berbeda bila state tersebut mungkin terjadi. Error menyediakan recovery yang nyata.
- Dialog memiliki nama, deskripsi bila perlu, Escape, backdrop, focus return, scroll region, dan aksi utama yang tetap terjangkau pada 320px dan zoom 200 persen.
- `prefers-reduced-motion: reduce` mematikan motion yang tidak perlu. Tidak ada layout shift yang membuat fokus atau aksi berpindah.
- Status sumber dan periode data tidak dicampur. Angka kosong, unavailable, stale, dan nol tetap berbeda.
- Perubahan CSS dilakukan di `resources/css/input.css`, hasil `npm run build` dilacak, dan URL asset versioning dipertahankan.
- Browser test memakai mock untuk endpoint mutasi. Tidak ada tes UI yang mengirim Boost, pembelian, atau tindakan bisnis nyata.

## Matriks verifikasi

| Skenario | Lebar | Tema | Interaksi |
| --- | --- | --- | --- |
| Jalur normal dengan satu dan banyak toko | 320, 500, 999, 1600 | terang, gelap | pointer, keyboard |
| Nama toko/produk/order sangat panjang | 320, 500 | terang, gelap | wrap, scroll, focus |
| Tidak ada toko atau tidak ada data | 320, 999 | terang, gelap | empty action |
| Sesi expired, data stale, partial data | 500, 1600 | terang, gelap | recovery link, live status |
| Error jaringan dan retry | 320, 999 | terang, gelap | alert, focus to error |
| Dialog dan popup | 320, 500, 1600 | terang, gelap | Escape, focus trap, focus return |
| Zoom atau text scale 200 persen | 320, 500 | terang, gelap | no clipped action |
| Reduced motion | 320, 1600 | terang, gelap | no nonessential animation |
| Chart dan tabel | 320, 999, 1600 | terang, gelap | keyboard, screen-reader alternative |

## Urutan sprint yang disarankan

1. **Sprint 1:** token UI, shell keyboard/accessibility, skip link, nav state, control height, shared status contract.
2. **Sprint 2:** shared shop picker, page header/toolbar, Products, Orders, Customers, Shops.
3. **Sprint 3:** Reports, Ads, Promosi, Sinkronisasi, chart/data disclosure, stale/error copy.
4. **Sprint 4:** dialog contract, Chat popup, Notifications, Finance and Automation alignment.
5. **Sprint 5:** final responsive and accessibility pass, visual regression snapshots, build, lint, and documentation update.

Setiap sprint sebaiknya menghasilkan perubahan yang bisa dibuka dan diuji sendiri. Mulai dari shell dan shared controls karena perbaikannya akan mengurangi duplikasi pada semua halaman berikutnya.

## Referensi source dan dokumentasi

- `app/views/panel/templates/header.php`, `navbar.php`, `sidebar.php`, `shop-picker.php`
- `public/assets/js/shop-select.js`, `workspace.js`, `notifications.js`
- `resources/css/input.css`
- `app/views/panel/products.php`, `orders.php`, `customers.php`, `shops.php`, `reports.php`, `ads.php`, `promotions.php`, `sync.php`, `chat.php`, `finance.php`, `automation.php`, `boost.php`, `extensions.php`, `product-movement.php`
- `ops/ui-ux-audit-20260929.md`
- `ops/automation-visual-ui.md`
- `ops/boost-visual-ui.md`
- `ops/dashboard-finance-ux.md`
- `ops/workspace-comfort.md`
- `AGENTS.md`, `tools.md`, `ops/agent-architecture.md`, `ops/agent-testing.md`
- UI UX Pro Max: `references/quick-reference.md`, `references/pro-rules.md`
- Antislop: `antislop.md`, `antislop-ui/SKILL.md`, `antislop-human/SKILL.md`, `antislop-layoutmobile/SKILL.md`, `antislop-copywriting/SKILL.md`
