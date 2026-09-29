# Fahra Kelola

Shopdash local dashboard for multi-store Shopee operations.

## Panduan agent AI dan pemeliharaan

Mulai dari [AGENTS.md](AGENTS.md), lalu baca [tools.md](tools.md) untuk perintah dan
efeknya, serta [memory.md](memory.md) untuk keputusan proyek. Panduan detail tersedia
di [arsitektur](ops/agent-architecture.md), [operasional](ops/agent-operations.md), dan
[pengujian](ops/agent-testing.md). Catatan runtime lama bukan status sistem saat ini.

## Local setup

On a new Mac, install PHP, Node.js, and MariaDB/MySQL first. Then clone the repository and run:

```sh
cd shopdash
./ops/setup-local.sh
./ops/install-background-sync.sh
```

`setup-local.sh` creates a local `config/.env`, imports `database/schema.sql`, installs the
frontend dependencies, builds CSS, and creates a new admin account. The schema is intentionally
empty: customer, order, shop, Shopee cookie, session, and API credential data never enter Git.

For manual setup, copy `config/.env.example` to `config/.env`, import `database/schema.sql`, run
`npm ci && npm run build`, create an account with `php bin/create-admin.php`, then start the app
with `php -S 127.0.0.1:8123 -t public router.php` from this directory.

Fresh setup only: `database/schema.sql` contains `DROP TABLE`. Do not run the setup
script or reimport the base schema over an existing database to troubleshoot it.

Dashboard hanya meminta health-check toko. Scheduler dan worker berjalan sebagai satu-satunya
proses background melalui LaunchAgent, jadi status toko tidak bergantung pada membuka
`/panel/shops` dan tidak ada worker tambahan dari request halaman.

`bin/sync-daemon.php` is an alternative scheduler/worker runner. Do not install a
second cron runner alongside the existing LaunchAgents.

## Git workflow

### Penempatan file

Ikuti struktur proyek yang sudah ada sebelum membuat file. Simpan dokumentasi teknis,
audit, dan catatan verifikasi di `ops/`, pengujian di `tests/`, serta hasil sementara
dan screenshot pengujian di `tmp/` yang tidak masuk Git. Gunakan nama file deskriptif
dengan huruf kecil dan tanda hubung. Perbarui dokumen yang relevan bila sudah ada;
jangan membuat catatan lepas di root proyek atau folder induknya. Pengecualian yang
diminta pengguna adalah `AGENTS.md`, `tools.md`, dan `memory.md` sebagai pintu masuk agent.

Commit each completed change with a clear message, then push the branch to the private GitHub repository:

```sh
git add path/to/task-file
git commit -m "Describe the change"
git push
```

The local `.env`, dependencies, macOS metadata, and captured session payloads are intentionally excluded from version control.

## Performa iklan

Sinkronisasi melalui cookie dan daftar campaign dijelaskan di
[ops/ads-campaign-sync.md](ops/ads-campaign-sync.md). Jalankan
`php tests/ads-campaign-reports.php` untuk memeriksa pagination, kegagalan parsial,
dan integrasi pengambilan laporan.

Mapping harian, mingguan, bulanan, rincian per tanggal, dan batasan akses Seller Centre
didokumentasikan di [ops/ads-data-mapping.md](ops/ads-data-mapping.md).
Jalankan `php tests/ads-performance.php` untuk memeriksa konversi metrik terhadap capture Sniper.
Halaman `/panel/ads` menyediakan filter periode, jenis iklan, toko, serta rincian harian.
Pilot import laporan dari browser, kontrak input, dan batasannya ada di
[ops/ads-browser-pilot.md](ops/ads-browser-pilot.md).
Jalankan `php tests/ads-browser-import.php` untuk validasi identitas dan penyimpanan
terpisah per toko, channel, serta periode (memerlukan database lokal).
Uji browser: `node tests/ads-ui.cjs` dengan Playwright tersedia di lingkungan pengujian
(atau arahkan `PLAYWRIGHT_MODULE` ke modul Playwright yang sudah terpasang).
