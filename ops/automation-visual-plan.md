# Rencana visual Automation Engine

Tanggal: 2026-09-29. Status: **diimplementasikan pada 2026-09-29**; lihat [hasil dan verifikasi](automation-visual-ui.md). Isi berikut mencatat rencana awal sebelum implementasi. Basis audit: `f75c9c8`, halaman lokal `/panel/automation` pada server 8123. Permintaan pengguna: kurangi tulisan yang selalu terlihat, rapikan dashboard, gunakan ikon/logo/visual yang membantu pemahaman.

## Temuan audit

- Target, persona, aturan, provider, uji aturan, dan CRUD koneksi tampil dalam satu halaman. Pengaturan harian bercampur dengan pengelolaan koneksi yang lebih jarang digunakan.
- Dalam kondisi toko belum menyimpan konfigurasi dan belum ada koneksi, tinggi halaman terukur 3.790px pada lebar 500px, 3.259px pada 999px, dan 2.328px pada 1600px; tinggi viewport 1008px. Tidak ditemukan overflow horizontal pada ketiga ukuran tersebut. Ini observasi keadaan awal, bukan benchmark seluruh keadaan aplikasi.
- Pada mobile, pemilihan provider berada setelah tombol simpan profil. Urutan ini menyulitkan pengguna memahami bahwa koneksi merupakan bagian pengaturan toko.
- Banyak paragraf bantuan selalu terlihat, sementara judul bagian dan kotak memiliki bobot visual hampir sama. Aturan sudah menggunakan disclosure, tetapi aturan pertama langsung terbuka.
- Selector toko sudah menggunakan logo. Identitas ini perlu dipertahankan, bukan diganti avatar AI generik.
- Audit memeriksa source dan screenshot lokal. Keadaan koneksi terisi ditinjau dari source, belum diuji secara visual dalam audit ini. Tidak ada penyimpanan konfigurasi, tes provider, atau pengiriman rating selama audit.

## Arah yang disarankan

Pertahankan warna hangat, tipografi, sidebar, dan tema Shopdash. Ubah susunan informasi menjadi tiga tab: **Aturan toko**, **Uji aturan**, dan **Koneksi AI**. Jangan menambahkan tab Ringkasan tersendiri yang hanya mengulang pengaturan.

```text
Automation Engine                      [logo + pilih toko v]
Pengiriman belum aktif                 [Tentang fitur]

[Aturan toko]       [Uji aturan]       [Koneksi AI]

Target rating                         Persona & model
[1 ★] [2 ★] [3 ★] [4 ★] [5 ★]         Nama / ringkasan persona [Ubah]
[Cakupan waktu v]                      [Koneksi toko v]
                                      Model efektif [Opsi lanjutan]
Aturan per bintang
★ 1     [Tindakan sesuai data]      >
★ 2     [Tindakan sesuai data]      >
★ 3     [Tindakan sesuai data]      >
★ 4     [Tindakan sesuai data]      >
★ 5     [Tindakan sesuai data]      >

Belum disimpan                         [Simpan pengaturan]
```

Wireframe menunjukkan struktur desktop, bukan data atau status produksi. Pada mobile seluruh isi menjadi satu kolom. Label tindakan pada setiap baris harus berasal dari konfigurasi aktual, bukan contoh yang ditetapkan ke semua toko.

### 1. Aturan toko

- Header ringkas: judul, selector toko berlogo, dan status **Pengiriman belum aktif** yang selalu terlihat. Penjelasan panjang tersedia melalui disclosure “Tentang fitur”. Status ini tidak berubah menjadi aktif setelah simpan atau tes koneksi.
- Target bintang berupa checkbox chip `1 ★` hingga `5 ★`. Nama aksesibel tetap “Targetkan rating 1 bintang”, dan seterusnya. Gunakan tanda centang serta warna untuk status terpilih.
- Cakupan waktu tetap eksplisit. Tampilkan tanggal hanya saat rentang dipilih. Untuk rating baru, tetap tampilkan keterangan pendek bahwa awal periode menunggu aktivasi.
- Persona tampil sebagai nama dan ringkasan maksimal dua baris, dengan tombol “Ubah persona”. Tombol membuka editor inline berisi gaya bicara dan kebijakan bantuan. Kebijakan lengkap tetap dapat dibaca sebelum diubah. Tampilan ringkas tidak memotong nilai yang disimpan.
- Koneksi toko dan model efektif berada di bagian pengaturan yang sama, sebelum simpan. Override model dan batas internal karakter masuk “Opsi lanjutan”; indikator override tetap terlihat saat digunakan. Jika belum ada koneksi, tampilkan satu tindakan “Tambah koneksi” menuju tab Koneksi AI.
- Aturan berupa lima baris dengan ikon bintang, label tindakan, dan chevron. Awalnya tertutup; buka satu baris untuk mengedit tindakan/instruksi. Jangan memaksakan perlakuan tertentu untuk rating 1–3. Jika bintang tidak ditargetkan, tandai “Di luar target” sambil mempertahankan aturan tersimpannya.
- Satu area simpan profil dengan status perubahan. Pada mobile gunakan sticky bottom dalam konteks editor, ruang bawah yang memadai, dan safe-area. Tidak menutupi input, pesan kesalahan, atau keyboard layar. Pada desktop sticky hanya bila ruang mencukupi.

### 2. Uji aturan

- Pindahkan formulir contoh ulasan ke tab ini. Label singkat tetap jelas: bintang, tanggal, ulasan, sudah dibalas.
- Hasil menggunakan urutan visual **Target → Tindakan → Alasan**. Tampilkan berdasarkan hasil evaluasi aktual; jika aturan tidak cocok, jelaskan penyebabnya.
- Selalu tampilkan “Memeriksa aturan saja; tidak membuat atau mengirim balasan.” Jangan membuat gelembung percakapan yang menyiratkan balasan AI sungguhan.
- Detail prompt ada dalam disclosure. Pertahankan perilaku memakai nilai formulir terbaru, termasuk perubahan belum disimpan, dengan indikator yang jelas.
- “Uji aturan” berbeda dari “Uji model” pada koneksi. Yang pertama lokal; yang kedua memanggil 9Router dan dapat memakai kuota.

### 3. Koneksi AI

- Judul dan keterangan pendek **Koneksi bersama untuk semua toko**. Selector toko hanya tampil di dua tab yang memang memiliki konteks toko; jangan menyiratkan bahwa CRUD koneksi dibatasi toko terpilih.
- Daftar koneksi menonjolkan nama, model default, jumlah toko pemakai, dan status uji generasi aktual. Base URL lengkap, daftar toko, waktu pengujian dan katalog tersedia dalam detail.
- Aksi utama per koneksi: “Ubah” dan “Uji model”. Aksi “Muat model” dan “Hapus” tersedia melalui menu “Lainnya” yang dapat diakses keyboard. Kegagalan, hasil kedaluwarsa, atau larangan hapus koneksi terpakai tetap mudah ditemukan.
- Form tambah/ubah dibuka inline hanya ketika diperlukan: nama, base URL, API key, model/combo. Label terlihat, bukan hanya placeholder. Bantuan key/URL muncul dekat field saat relevan. Pertahankan CRUD dan semua validasi yang ada.
- Sebelum Uji model, pertahankan informasi pemakaian kuota dan contoh sintetis. Keberhasilan katalog tidak berarti tes generasi berhasil. Jangan menggunakan badge “AI aktif” untuk koneksi yang berhasil diuji.
- Saat daftar kosong: ikon koneksi, “Belum ada koneksi”, tombol “Tambah koneksi”. Jangan menampilkan kartu metrik nol atau ilustrasi besar yang menambah panjang halaman.

## Bahasa dan visual

| Elemen | Perubahan yang direncanakan | Alasan |
| --- | --- | --- |
| Angka dekoratif 01/02/03 | Ikon sesuai fungsi dengan judul | Lebih mudah mengenali target, persona, dan koneksi |
| Paragraf bantuan berulang | Satu kalimat dekat keputusan; detail dibuka sesuai kebutuhan | Mengurangi beban baca tanpa menghilangkan informasi penting |
| “Model toko (opsional)” | “Model khusus” di Opsi lanjutan; model efektif tetap terlihat | Menjelaskan override tanpa memaksa setiap pengguna mengisinya |
| “Simpan konfigurasi” | “Simpan pengaturan” | Bahasa yang lebih dekat dengan tugas pengguna |
| Identitas toko | Logo aktual; fallback storefront | Konsisten dengan halaman lain dan mengurangi salah toko |
| Status dan hasil | Ikon + teks, warna sebagai penguat | Tetap dipahami tanpa mengandalkan persepsi warna |

Gunakan keluarga Material Symbols yang sudah tersedia: `star` untuk rating, `tune` untuk aturan, `chat_bubble` untuk persona, `link` untuk koneksi, dan `science` untuk pengujian. Ikon dekoratif memakai `aria-hidden`; tombol ikon saja wajib nama aksesibel. Utamakan ikon + label pada tindakan utama. Logo 9Router hanya digunakan jika aset resminya tersedia dan terverifikasi; sementara gunakan nama teks 9Router. Tidak perlu robot, sparkle, grafik, progress bar, atau angka kinerja karena sender belum berjalan.

Design dials: ENERGY 1/3, RHYTHM 2/3, MOTION 1/3. Energi rendah menjaga fokus; ritme berasal dari baris aturan dan hierarki bagian; gerak hanya umpan balik singkat, menghormati reduced motion. Ini usulan desain, bukan hasil pengujian pengguna. Pencarian UX skill tentang progressive disclosure tidak menghasilkan panduan yang relevan; keputusan disclosure didasarkan pada audit isi halaman. Panduan aksesibilitas ikon dari UI UX Pro Max dan batasan antislop tetap diterapkan.

## Interaksi dan kontrak yang harus dipertahankan

- Tab dapat diakses keyboard dengan semantik tablist/tab/tabpanel, fokus terlihat, tombol panah serta Home/End. Gunakan label pendek agar muat pada 320px; jika perlu ikon disembunyikan, bukan teks label.
- Pergantian tab tidak menghapus draft profil atau editor koneksi. Hindari membangun ulang form saat pindah tab. Simpan profil dan simpan koneksi tetap tindakan terpisah.
- Shop switch mempertahankan perlindungan perubahan belum disimpan. Memuat tab Koneksi tidak mengubah toko terpilih di belakangnya. Koneksi yang baru disimpan memperbarui pilihan provider tanpa menimpa draft profil.
- Jika validasi gagal pada bagian tertutup atau tab tersembunyi, buka bagian/tab tersebut dan fokus ke kesalahan pertama. Pesan loading, kosong, gagal, konflik versi, perubahan belum disimpan dan hasil pengujian diumumkan secara aksesibel.
- Logo 24px pada picker, dropdown satu kolom ke bawah, target sentuh minimal 44px, kontras teks minimal 4,5:1, dan nama toko/model panjang tidak menyebabkan overflow.
- Enkripsi key, key kosong berarti tetap, kewajiban key baru saat URL berubah, optimistic versions, larangan hapus koneksi terpakai, dan model override tidak berubah.
- Tidak ada perubahan schema, endpoint, worker, pengiriman rating, Finance/HPP, atau navigasi global yang diperlukan untuk rencana ini.

## Tahap implementasi berikutnya

1. Tata ulang template Automation dan koneksi menjadi tiga panel; pertahankan ID/form contract yang masih diperlukan. Implementasikan navigasi tab dan draft state sebelum menambahkan detail visual.
2. Ringkas editor persona dan aturan, pindahkan opsi lanjutan, susun provider sebelum simpan, tambahkan ikon dan area simpan yang responsif.
3. Rapikan daftar/form koneksi, hasil uji aturan, serta seluruh keadaan kosong/gagal/konflik. Jangan menunda error handling sebagai pemolesan akhir.
4. Ubah CSS hanya dalam scope `#automation-page`, jalankan build, lalu uji interaksi dan visual. Batasi file ke `automation.php`, partial `ai-connections.php`, kedua JS terkait, CSS sumber/hasil build, dan pengujian terkait. Koordinasikan perubahan stylesheet hasil build dengan agent lain; jangan menyalin versi stylesheet lama dari worktree.

## Kriteria penerimaan

- Pada tab Aturan toko, pengguna mengenali toko, target, tindakan per bintang, persona dan model tanpa membaca paragraf bantuan. Uji aturan dan CRUD koneksi tidak lagi memperpanjang tab awal.
- Penjelasan nonkritis tertutup pada tampilan awal, tetapi status pengiriman, perubahan belum disimpan, error dan peringatan biaya tetap terlihat pada konteks yang tepat.
- Bandingkan screenshot dan tinggi tab awal dengan baseline yang sama setelah implementasi. Pengurangan scroll tidak boleh dicapai dengan mengecilkan teks, target sentuh, atau menyembunyikan kontrol penting. Belum ada klaim persentase pengurangan atau peningkatan produktivitas.
- Periksa 320/500/999/1600px, light/dark, keyboard, nama panjang, logo gagal, daftar kosong/terisi, profil baru/tersimpan, dan sticky footer saat input fokus.
- Jalankan serta sesuaikan `tests/automation-ui.cjs` dan `tests/ai-connections-ui.cjs` untuk tab, disclosure, draft preservation, fokus error tersembunyi, dan scoping. Mock mutasi dan panggilan provider dalam tes; jangan mengirim rating atau memakai kuota provider nyata untuk tes visual.
- Audit antislop akhir: satu tindakan utama per konteks, ikon memiliki fungsi, tidak ada status/angka palsu, bantuan singkat, tidak ada fitur semu.

Dokumen terkait: [fondasi Automation](automation-foundation.md), [kontrak koneksi 9Router](9router-connections.md), [pengujian proyek](agent-testing.md). Pemeriksaan tahap rencana hanya audit baca, tautan dokumen, dan `git diff --check`; UI baru belum dibuat maupun diuji.
