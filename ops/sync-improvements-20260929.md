# Shopee sync improvements, September 29, 2026

## Implemented behavior

- Products run at most two pages per turn and resume from the saved cursor. Short pages with a cursor continue. Repeated cursors, malformed responses, and incomplete totals fail without catalog cleanup. Resumed product and variant ID sets retain the IDs from all earlier pages.
- Product jobs remain running until traversal finishes. Catalog cleanup and stock alerts run only after a completed traversal. Unchanged product and variant fields do not trigger database updates.
- Recent orders (`diff`) and history (`full`) have separate active jobs. Recent discovery continues through a 48-hour overlap until a completely known, detailed, older page. Mixed known/new pages continue. History retains the existing three-month scope.
- Each recent job also queues up to 100 oldest-due local active orders, independent of which index page contains them. Active detail freshness is three minutes; terminal orders use 24 hours. Fresh details already fetched by another job are skipped before making another request. History completion does not postpone the recent schedule.
- Recent orders and chat receive priority, while jobs waiting at least two minutes are promoted to prevent history starvation. This is a scheduling policy, not a maximum delay guarantee: an in-flight request can still take time.
- Packages run at most five items per turn with a durable order-ID cursor. Failures accumulate across turns and cause the final job to fail. Valid empty package responses get a one-hour cooldown; failed responses do not update `package_synced_at`. Package requests have a five-second connection timeout and a 15-second total timeout.
- Ads and chat schedules are disabled for all seven shops on this installation, following the user's request to focus on orders and other shop data. No queued or running ads/chat jobs remained when this pause was verified. Orders, products, packages, promotions, performance, customers, and shop health schedules remain enabled. These are database settings; source checkout alone does not reproduce the pause.

## Verification

Database integration tests use temporary tables on their own connection, with stubbed upstream responses. They never write fixture data into production tables.

```sh
php tests/sync-recovery.php
php tests/product-sync.php
php tests/order-sync.php
php tests/package-sync.php
php tests/ads-performance.php
```

At implementation: 27 recovery/outcome checks, 15 product checks, 19 order checks, six package checks, and 135 ads regression checks passed. Tests cover resumed cleanup, partial status, cursor stalls, mixed index pages, old active orders, priority aging, duplicate detail avoidance, package failures across turns, and retry preservation.

The existing browser suite also passed 83 checks, for 285 passing checks in total. PHP syntax checks and `git diff --check` passed. Index exceptions release the job for retry; failed terminal detail tasks cannot produce a successful orders job.

The worker resumed at 07:57 WIB. The first runtime check afterward confirmed a completed recent-order job and a completed two-page product job while three history jobs remained in progress. No expired detail leases were found, and the local web returned HTTP 200. Four chat jobs still failed on upstream access restrictions; these were not treated as recovered authentication.

## Observation

`php bin/sync-observe.php` prints one read-only JSON sample. For a 24-hour observation:

```sh
php bin/sync-observe.php --watch-seconds=86400 --interval=60 > /tmp/shopdash-sync-observation.jsonl
```

Samples contain aggregate queue counts, per-shop schedule timestamps, error categories, and recent completion counts. They exclude cookies, customer payloads, and raw upstream errors. The observer does not change synchronization settings or trigger upstream requests. Completed 24-hour validation is not claimed at deployment time.

On this Mac, `com.fahra.shopdash.observe` started at 07:58 WIB on September 29. It writes one sample per minute to `/tmp/shopdash-sync-observation.jsonl` and exits after 24 hours. The local LaunchAgent has no restart or repeating schedule. This observer installation is machine-local, separate from the repository's worker/scheduler installer.

Check that recent-order jobs complete while full jobs progress, no expired leases accumulate, and the historical detail backlog shrinks after discovery ends. A six-minute pre-change observation is insufficient for a before/after performance guarantee. Matching actual Seller Centre order counts/statuses remains a separate validation task.

## Rollback

Changes are pushed separately to `origin/main`. The checkpoint before these improvements is `6411069`; local database-port support is preserved in its parent `449796c`. Product changes are `0000aa7`, recent/history order changes are `ad1cd0a`, and package changes are `71222a5`.

Use `git revert` on the relevant commits, starting with the newest dependent commit, then restart the worker. Do not force-push or reset shared history. There are no new production database columns or table migrations. Existing jobs/checkpoints and synchronized business data remain in the database; a code rollback does not undo those rows. Full and diff order jobs may coexist until drained, including after a rollback.
