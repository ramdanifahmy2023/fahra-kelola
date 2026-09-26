# Fahra Kelola

Shopdash local dashboard for multi-store Shopee operations.

## Local setup

1. Copy `config/.env.example` to `config/.env` and set the local database values.
2. Install frontend dependencies with `npm install`.
3. Ensure MariaDB is running and the `shopdash_db` database exists.
4. Start the PHP application with `php -S 127.0.0.1:8123 router.php` from this directory.

## Git workflow

Commit each completed change with a clear message, then push the branch to the private GitHub repository:

```sh
git add .
git commit -m "Describe the change"
git push
```

The local `.env`, dependencies, macOS metadata, and captured session payloads are intentionally excluded from version control.
