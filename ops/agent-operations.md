# Operations and troubleshooting

Reviewed from source on 2026-09-29. Runtime state must be checked again before acting. Read [tools](../tools.md) for command effects and [memory](../memory.md) for durable decisions.

## Existing installation versus fresh setup

For an existing installation, inspect services, configuration availability, relevant logs, and database state first. **Do not run `ops/setup-local.sh` or import `database/schema.sql` as a repair:** the schema includes `DROP TABLE` and can erase data. Fresh setup is only for an explicitly chosen new/disposable database. The setup script targets `shopdash_db`; review it before assuming a different database name is supported.

Credentials remain in `config/.env` and database records. Record only non-secret configuration facts needed for a task. The default example port is 3306; actual host/port may differ. CLI bootstrap examples must change directory to `public` before requiring `app/init.php`, matching existing scripts' relative paths.

## Services on this Mac

| Service | Source configuration | Behavior / log prefix in `/tmp/` |
| --- | --- | --- |
| Web | `com.fahra.shopdash.web.plist` | PHP on 8123, KeepAlive; `shopdash-web` |
| Scheduler | `com.fahra.shopdash.scheduler.plist` | Every 60 seconds, enqueue limit 50; `shopdash-sync-scheduler` |
| Worker | `com.fahra.shopdash.worker.plist` | Every 5 seconds, batch 10, rate-ms 350; `shopdash-sync-worker` |

Each prefix has `.log` and `.error.log` files. These are template settings, not proof the service is currently loaded. Use `launchctl print` with the labels in [tools](../tools.md). Log tails can contain sensitive upstream errors; inspect selectively and summarize without copying credentials/payloads.

The worker has a process lock derived from the repository path. Do not launch repeated workers to bypass a busy one. `sync-daemon.php` combines scheduler/worker for an alternative runner; do not layer it on top of the existing LaunchAgents. Installing/reloading agents restarts services and can interrupt active work, so do it only when needed for the authorized task.

## Sync status, percentage, and ETA

Start with the read-only observer:

```sh
php bin/sync-observe.php
```

It reports queue aggregates, expired detail leases, recent completions, schedule timestamps, and sanitized error categories. Optional `--watch-seconds` / `--interval` create a continuing observation; do not leave an unrequested long-running process behind.

For an order-history estimate, choose the relevant full jobs per shop, then inspect their detail rows. Useful read-only SQL patterns:

```sql
SELECT shop_id, MAX(id) AS latest_full_job
FROM sync_jobs
WHERE sync_type = 'orders' AND mode = 'full'
GROUP BY shop_id;

SELECT status, COUNT(*) AS total
FROM sync_job_orders
WHERE job_id = :job_id
GROUP BY status;

SELECT COUNT(*) AS completed_last_hour
FROM sync_job_orders
WHERE job_id = :job_id AND status = 'done'
  AND completed_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR);
```

Bind placeholders through the database wrapper; never interpolate arbitrary user SQL. Check parent status and discovery state too: in the current order worker, `page_number = 0` marks discovery finished. Validate this against `SyncWorkerTasks.php` if implementation changes.

- Detail progress = completed detail tasks / scoped discovered detail tasks. State that denominator; it is not necessarily unique orders or all Seller Centre orders.
- While discovery continues, the denominator can grow. Do not call that a final total.
- Do not calculate aggregate history progress from every job ever created; repeat/diff jobs duplicate work.
- Compare completed counts over several windows (for example 1h and 3h). Remaining tasks / observed tasks per hour gives an ETA range, not a guarantee.
- If throughput is zero, do not invent an ETA. Check leases, retry deadlines, errors, and worker health.
- State observation time/timezone. Observer output is UTC; UI commonly uses WIB. Verify field semantics before timezone conversion.
- A schedule's last success or a completed parent alone does not prove remote/local counts match. Failed detail tasks and incomplete discovery must remain visible.

## Defaults versus saved schedules

Source defaults in `BackgroundSync.php`: orders 180s with full 12h; products 900s with full 24h; chat 30s; promotions 600s; ads 900s; ads topups 24h; performance/customers 1800s; shops/packages 3600s. Saved `sync_schedules` rows decide actual enabled state and interval. Do not restore defaults or re-enable paused channels just because a historical note or checkout differs.

## Troubleshooting paths

| Symptom | First checks | Avoid |
| --- | --- | --- |
| Old UI after an update | Current served JS/CSS, versioned URLs, actual DOM, fresh feedback capture | Rewriting an already-correct layout based only on an old snippet |
| Menu opens sideways | Flex wrapping, constrained height, shared selector CSS and compiled build | Page-specific overflow hacks that hide options |
| Shop label changes but rows do not | Link navigation/request shop ID and preserved filter values | Updating only `history.replaceState` or the label |
| Wrong/missing logo | Local ID mapping, stored image URL, image error fallback | Matching by array position or using another store's image |
| Orders seem stuck | Worker service, pending/running detail counts, leases, retry dates, parent cursor | Resetting all jobs or starting duplicate workers |
| Ads unavailable | Channel/period identity, report availability, source/error category; existing ads docs | Treating a successful identity call as report success or filling unknown values with zero |
| Chat forbidden | Channel-specific access state | Marking the entire shop expired solely from `user_is_forbidden` |
| Local page fails | PHP error log, DB connectivity, migrations/schema availability, route casing | Fresh-install schema import over existing data |

Relevant evidence: [queue recovery](sync-recovery-20260929.md), [sync improvements](sync-improvements-20260929.md), [ads campaign sync](ads-campaign-sync.md), [ads data mapping](ads-data-mapping.md), [browser import pilot](ads-browser-pilot.md). These notes describe their observation dates, not guaranteed current restrictions.

## Release and recovery

Before delivery, review scope, run relevant checks, build changed styles, and stage only intended files. Push to the intended branch under the user's standing instruction. Verify success and working-tree state; leave unrelated files unchanged.

For a regression on a shared branch, prefer a reviewed `git revert` of the responsible commit or a focused forward fix. Do not hard-reset or force-push shared history. Runtime database changes, queue progress, schedules, and locally installed services are not reverted by Git. Any data restore requires a verified suitable backup and explicit scope; a schema-only export is not a data backup.
