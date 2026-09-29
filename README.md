# Fahra Kelola

Shopdash local dashboard for multi-store Shopee operations.

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
with `php -S 127.0.0.1:8123 router.php` from this directory.

Dashboard hanya meminta health-check toko. Scheduler dan worker berjalan sebagai satu-satunya
proses background melalui LaunchAgent, jadi status toko tidak bergantung pada membuka
`/panel/shops` dan tidak ada worker tambahan dari request halaman.

```cron
* * * * * cd /path/to/shopdash && /usr/bin/php bin/sync-daemon.php >/dev/null 2>&1
```

## Git workflow

Commit each completed change with a clear message, then push the branch to the private GitHub repository:

```sh
git add .
git commit -m "Describe the change"
git push
```

The local `.env`, dependencies, macOS metadata, and captured session payloads are intentionally excluded from version control.

## Performa iklan

Mapping harian, mingguan, bulanan, rincian per tanggal, dan batasan akses Seller Centre
didokumentasikan di [ops/ads-data-mapping.md](ops/ads-data-mapping.md).
Jalankan `php tests/ads-performance.php` untuk memeriksa konversi metrik terhadap capture Sniper.
Halaman `/panel/ads` menyediakan filter periode, jenis iklan, toko, serta rincian harian.
Uji browser: `node tests/ads-ui.cjs` dengan Playwright tersedia di lingkungan pengujian
(atau arahkan `PLAYWRIGHT_MODULE` ke modul Playwright yang sudah terpasang).
