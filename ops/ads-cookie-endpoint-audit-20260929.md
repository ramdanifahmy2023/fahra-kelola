# Audit jalur cookie untuk laporan iklan

Hasil audit ini sudah diterapkan; lihat [sinkronisasi campaign](ads-campaign-sync.md)
untuk alur produksi, jadwal aktif, hasil pengujian, dan batasan rincian tanggal.

29 September 2026, 11:03–11:08 WIB. Tujuan: memeriksa apakah pola pengambilan
pesanan dan produk dapat digunakan untuk monitoring iklan tanpa browser worker.

## Hasil

**Bisa untuk jalur yang diuji:** `POST /api/pas/v1/homepage/query/` berhasil
mengembalikan laporan per campaign lewat PHP/cURL dan cookie tersimpan pada
ketujuh toko. Semua halaman Iklan Produk / 29 September berhasil dibaca.
Ini berbeda dari `report/get_time_graph/`, yang tetap ditolak pada uji pembanding.

Tidak ada perubahan kode runtime, snapshot dashboard, cookie, campaign,
anggaran, atau jadwal dalam audit ini. Respons yang diproyeksikan disimpan
lokal di `storage/ads-audit-20260929/`, diabaikan Git, tanpa cookie atau token.

## Perbandingan dengan pesanan dan produk

`ShopeeCurl::request()` adalah transport bersama ketiga fitur. Pesanan memakai
`getOrderIndexList()` lalu `getOneOrder()`. Produk memakai `getProductsPage()`
dengan pagination cursor. Laporan grafik iklan juga sudah menggunakan kelas
yang sama; mengganti worker saja tidak mengubah hasil akses endpoint.

Uji baru pada hiban.store dan Royal Abiya memakai cookie masing-masing record
toko dan proses PHP yang sama. Identitas masing-masing cocok dengan shop ID
tersimpan sebelum request laporan dilakukan.

| Request | hiban.store | Royal Abiya |
| --- | --- | --- |
| Identitas toko | HTTP 200/code 0, cocok | HTTP 200/code 0, cocok |
| Produk halaman pertama | HTTP 200/code 0, 24 produk | HTTP 200/code 0, 24 produk |
| Indeks pesanan halaman pertama | HTTP 200/code 0, 40 pesanan | HTTP 200/code 0, 40 pesanan |
| Meta saldo iklan | HTTP 200/code 0 | HTTP 200/code 0 |
| Grafik laporan iklan | HTTP 403/error 90309999 | HTTP 403/error 90309999 |
| Daftar campaign beserta laporan | HTTP 200/code 0 | HTTP 200/code 0 |

Produk, pesanan, dan uji daftar campaign menggunakan header default yang sama:
Cookie, Content-Type, Accept, User-Agent. Daftar campaign tidak memakai header
keamanan hasil salinan browser atau fingerprint buatan. Query SPC_CDS dan
SPC_CDS_VER dibentuk dari cookie seperti pengambilan pesanan/produk.

Parameter produk `need_ads=true` tidak menghasilkan field laporan iklan pada
produk pertama yang diperiksa di kedua toko. Statistik produk tersebut bukan
bukti tayangan/penjualan beratribusi iklan.

## Referensi MCP Sniper

Project 1, endpoint 745: `/api/pas/v1/homepage/query/`. Payload 11451 merekam
request Iklan Produk untuk 29 September. Respons mempunyai `entry_list`,
`total`, dan `has_report_failure`. Setiap entry mempunyai campaign dan report.

Payload bisnis yang dipakai pada uji backend:

```json
{
  "start_time": 1790614800,
  "end_time": 1790701199,
  "filter_list": [{
    "campaign_type": "product_homepage_v3",
    "state": "all",
    "search_term": "",
    "is_valid_rebate_only": false
  }],
  "offset": 0,
  "limit": 20
}
```

Offset bertambah 20 sampai jumlah campaign unik sama dengan `total`. Perhatikan
`product_homepage_v3` pada daftar campaign; jangan menyalin campaign_type v2
dari endpoint grafik ke endpoint ini. Satuan uang tetap raw / 100000.

Endpoint 818 `/api/pas/v1/sc_pc_homepage/adopter/get_report/` juga berhasil pada
kedua toko, tetapi hanya membawa broad_gmv, broad_roi, cost. Endpoint ini tidak
dipakai untuk mengisi metrik yang tidak dikembalikannya.

## Kelengkapan tujuh toko

Identitas diverifikasi per toko. Semua halaman menghasilkan HTTP 200/code 0,
`has_report_failure=false`. Campaign ID unik, jumlah akhir cocok dengan total,
dan enam metrik dasar berupa integer nonnegatif. Tidak ada halaman gagal yang
ditafsirkan sebagai nol.

| Toko | Campaign dibaca / total | Halaman |
| --- | ---: | ---: |
| hiban.store | 216 / 216 | 11 |
| Royal Abiya | 50 / 50 | 3 |
| Haviel | 5 / 5 | 1 |
| Hiban Signature | 22 / 22 | 2 |
| Hermosa Brand Fashion | 144 / 144 | 8 |
| elfuad.store | 45 / 45 | 3 |
| Safariana | 11 / 11 | 1 |

Pembacaan berlangsung sekitar 11:06–11:08 WIB, berurutan dengan jeda 350 ms
antarhalaman. Data hari berjalan dapat berubah saat pagination berlangsung;
kelengkapan ID tidak menjamin seluruh halaman berasal dari satu snapshot waktu.

## Rekonsiliasi dengan Seller Centre

Untuk mengurangi perbedaan waktu pengamatan hari berjalan, perbandingan memakai
Iklan Produk tanggal **28 September**. Browser normal hanya dipakai membaca
sumber pembanding, bukan menjalankan pengambilan campaign dari backend.

Seller Centre menampilkan hiban.store dan rentang timestamp 1790528400 hingga
1790614799. Respons grafik CDP `80352.559` menghasilkan HTTP 200/code 0.
Seluruh 216 campaign tanggal tersebut dibaca melalui PHP pada 11:07:39–11:07:45
WIB. Enam metrik penjumlahan cocok persis:

| Metrik raw | Aggregate grafik | Jumlah report campaign |
| --- | ---: | ---: |
| impression | 7424 | 7424 |
| click | 411 | 411 |
| checkout | 32 | 32 |
| broad_order_amount | 40 | 40 |
| broad_gmv | 173643899998 | 173643899998 |
| cost | 17309606536 | 17309606536 |

CTR dan ROAS harus dihitung ulang dari total, bukan menjumlahkan rasio tiap
campaign. Pesanan tetap mengikuti `checkout`; broad_order pada grafik adalah
35 dan tidak sama dengan Pesanan 32.

## Batas bukti dan penerapan

Keberhasilan ini membuktikan akses backend saat pengujian untuk Iklan Produk,
bukan jaminan bebas penolakan atau sesi berlaku selamanya. Penyebab internal
penolakan endpoint grafik belum diketahui. Perbandingan tepat dengan grafik
baru dilakukan untuk satu toko dan satu tanggal. Iklan Toko/Live serta rentang
mingguan/bulanan belum diuji melalui backend pada audit ini.

Jalur implementasi yang sesuai:

1. Tambahkan pengambil laporan campaign ke worker cookie yang sudah ada.
2. Verifikasi identitas, baca seluruh halaman, periksa code/error dan
   has_report_failure, serta tolak hasil parsial/ID duplikat/total berubah.
3. Simpan laporan dengan sumber endpoint, toko, channel, tanggal dan waktu
   pengambilan; pertahankan laporan sukses ketika upaya berikutnya gagal.
4. Hitung metrik ringkasan dari total dan kumpulkan laporan per tanggal untuk
   rincian. Jangan membuat rincian harian dari total satu periode.
5. Uji jenis iklan dan rentang tambahan sebelum mengaktifkannya. Aktifkan
   jadwal bertahap setelah integrasi dashboard dan perilaku kegagalan teruji.

Jadwal belum diaktifkan dan hasil audit belum diimpor sebagai laporan dashboard.
Pilot browser sebelumnya tetap merupakan bukti tahap terdahulu, bukan satu-satunya
jalur pengambilan yang sekarang diketahui berhasil.
