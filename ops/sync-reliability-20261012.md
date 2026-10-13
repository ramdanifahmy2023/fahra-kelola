# Sync reliability changes, 2026-10-12

The durable sync queue now records a cursor on every order index page and
creates an order job, run, and first page in one transaction. The scheduler
claims due schedule rows with a short owner lease, while workers fence job and
detail updates with a lease owner token. Heartbeats extend the lease and write
the run heartbeat timestamp.

Order, product, model, package, and customer worker writes are constrained to
the local shop. A duplicate remote ID observed under another shop is ignored
instead of moving the existing row. Worker jobs verify the cookie's remote
shop identity before publishing data. Job and detail retries are bounded by
`max_attempts`; exhausted rows are marked failed with `permanent_at`. Finance
busy locks return a queued retry signal, and topup page checkpoints reset when
the remote shop identity changes.

The additive migration is
`database/migrations/20261012_sync_reliability.sql`. Existing installations
may apply it before restarting scheduler and worker services. Legacy queue
tables remain readable while the migration is pending; the worker dynamically
omits new lease columns until they exist.

Verification on this branch:

- `php tests/order-sync.php` — 23 checks
- `php tests/sync-recovery.php` — 27 checks
- `php tests/product-sync.php` — 15 checks
- `php tests/package-sync.php` — 6 checks

These tests use local fixtures and do not make external Shopee mutations.
