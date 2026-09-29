# Background sync and web service

Shopdash now separates page rendering from Shopee synchronization:

- `sync-scheduler.php` runs every minute and enqueues due work per toko.
- `sync-worker.php` consumes the durable queue with leases and rate limiting.
- Local pages read the database; the dashboard only requests a health-check schedule.
- The LaunchAgent scheduler and worker are the only background processes.
- Product cursors are checkpointed in `sync_checkpoints`, so an interrupted page run resumes.
- Products run a full reconciliation every 24 hours; orders run a full reconciliation every 12 hours.
- The `/panel/sync` page shows per-toko health and lets the operator change intervals or pause a schedule.
- Scheduler initialization preserves saved intervals and pause settings.
- The worker recovers expired order-detail leases and rotates runnable jobs between shops. A process lock prevents overlapping local workers.
- Access failures use a minimum 15-minute retry interval. Failed ads reports remain failed even when account metadata was saved; chat failures do not change overall shop health.

On macOS, install or reload the agents with:

```sh
./ops/install-background-sync.sh
```

The web server runs on `127.0.0.1:8123` and is managed by
`com.fahra.shopdash.web`. It starts at login and restarts when it exits.

Logs are written to `/tmp/shopdash-sync-scheduler.log` and
`/tmp/shopdash-sync-worker.log`. The current intervals are stored in
`sync_schedules`, so each toko can be inspected through `/procsync/status`.

Recovery verification for September 29, 2026 is recorded in [sync-recovery-20260929.md](sync-recovery-20260929.md).
