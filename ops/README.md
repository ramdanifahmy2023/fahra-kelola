# Background sync

Shopdash now separates page rendering from Shopee synchronization:

- `sync-scheduler.php` runs every minute and enqueues due work per toko.
- `sync-worker.php` consumes the durable queue with leases and rate limiting.
- Local pages read the database and only enqueue when the user presses **Sync sekarang**.
- Product cursors are checkpointed in `sync_checkpoints`, so an interrupted page run resumes.
- Products run a full reconciliation every 24 hours; orders run a full reconciliation every 12 hours.
- The `/panel/sync` page shows per-toko health and lets the operator change intervals or pause a schedule.

On macOS, install or reload the agents with:

```sh
./ops/install-background-sync.sh
```

Logs are written to `/tmp/shopdash-sync-scheduler.log` and
`/tmp/shopdash-sync-worker.log`. The current intervals are stored in
`sync_schedules`, so each toko can be inspected through `/procsync/status`.
