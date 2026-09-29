# Operational notifications

Implemented 2026-09-29 after the [priority audit](notification-priority-audit.md). This is the first phase: stock lifecycle, shop/module connection issues, operational sync health, and shipping deadlines. Chat response time, ad balance, returns, rating discovery and AI worker incidents remain future work. Finance/HPP code is outside this change.

## Detection and data contract

| Type | Trigger and recovery | Action |
| --- | --- | --- |
| Stock | Existing product sync reconciles active products below 15; zero is urgent. Recovery uses the existing product reconciliation. | Products with shop and product highlight |
| Connection | Missing cookie, or an enabled schedule's explicit session/access error. Network timeouts are not classified as expired credentials; 403/permission errors are module access problems. One incident per shop lists affected modules. Recovery requires subsequent successful sync for affected modules with no remaining error. | Shop connection, or shop-scoped sync for access restrictions |
| Stalled sync | Enabled orders/products schedule has no success and no actual completed detail/page progress within `max(15 min, 3 × interval)`; urgent at `max(60 min, 6 × interval)` since success/first job. Heartbeat alone is not progress. Known connection issues suppress duplicate sync alerts for the affected modules. | Shop-scoped sync status |
| Shipping | Verified unshipped status, valid UNIX `ship_by_date`, detail no older than 30 minutes. Warning within 24 hours, urgent within 6 hours, another unread revision when overdue. Fresh shipped/cancelled/non-shipping status or extended deadline resolves it. Unknown or stale data preserves existing alerts with a stale label; it cannot raise or resolve a shipping alert. | Exact local order in its shop, with a link back to all shop orders |

Thresholds are internal defaults in `NotificationPolicy.php`, not Shopee SLA rules. No settings UI for them is included yet. Observed local status `Perlu Dikirim` was checked against detail storage; `sync_status=active` is deliberately not used as a shipment status. Source sync times are interpreted as UTC and rendered as WIB. Implausible future detail/progress timestamps are not trusted as current evidence.

Operational evaluation runs from the authenticated notification summary/list request, throttled to once per 30 seconds across users with a database advisory lock. The browser polls every 30 seconds while visible. **There is no new independent notification worker**: without panel traffic, new operational alerts are evaluated on the next request. Stock reconciliation continues through the existing product sync path. Evaluation reads local records only; it does not call Shopee, enqueue sync, send messages, or mutate orders.

The evaluator persists results transactionally. An evaluation failure keeps stored alerts and the response sets `evaluation_available=false`, which the interface discloses. Missing source evidence does not imply recovery. Schedule error classification depends on available local errors; it is not a live authentication probe. Permission-only incidents are warning; explicit session failure is urgent.

## Lifecycle and API

- Stable fingerprint: type + shop + entity. Repeated polls update source state without duplicating incidents or resetting read state.
- `revision` increases on reopen, warning-to-urgent escalation, or deadline stage escalation. Read receipts are per authenticated account and revision. Stale clicks cannot acknowledge a newer revision.
- Reading does not resolve an incident. The former shared `acknowledged_at` state is retained as a compatibility fallback for existing alerts; new receipts never mark other accounts read. Escalation clears that fallback.
- Badge and list use the same active, unsilenced scope. The unread urgent count uses unread incidents only. All-active view retains read incidents. The empty-unread state explicitly says problems may remain active.
- GET `/procnotifications/summary` (or `/list`) accepts `unread_only`, `limit` (10–100), and `offset`, returning summary, notifications, `has_more`, and evaluation availability. The old optional shop filter is not part of the new bell API.
- POST `/procnotifications/acknowledge` (or `/acknowledge_all`) requires CSRF and JSON `items: [{id, revision}]`, 1–100 entries. The UI's “Tandai daftar dibaca” applies only to the displayed unread snapshot, not hidden pages or future incidents.
- All authenticated accounts currently manage all shops; per-account reads do not introduce tenant/RBAC isolation. No customer address, cookie, raw upstream error or provider key is included in notification payloads.

## Schema and files

`StockAlert::ensureSchema()` additively adds `revision`, `changed_at`, and `payload` columns to existing alerts, tolerating concurrent duplicate-column initialization. `NotificationCenter::ensureSchema()` applies `database/migrations/20260929_notifications.sql` to create receipt/check tables. Normal authenticated access initializes these tables; the database user needs CREATE/ALTER permissions. Existing stock fingerprints are retained. Do not import the destructive base schema to deploy this change.

Policy: `app/helpers/NotificationPolicy.php`. Evaluation/queries: `app/models/NotificationCenter.php`. API: `app/controllers/back/ProcNotifications.php`. UI: shared navbar, `public/assets/js/notifications.js`, and scoped source styles in `resources/css/input.css`. Existing navbar drawer/theme behavior is retained.

## Verification

- `php tests/notifications.php`: 38 checks, using temporary tables that shadow runtime tables; covers read scope, escalation, stale acknowledgements, deduplication, reopen, legacy reads, silence consistency, deadline boundaries, status allowlist, data freshness, connection grouping/recovery and sync progress.
- `php tests/product-sync.php`: 15 checks passed after integration with the latest Boost changes.
- `tests/notifications-ui.cjs`: mocked positive notification writes; tests read/filter/paging behavior, failure and recovery, safe text/action URLs, failed logo fallback, keyboard dismissal/focus, 44px controls, authentication/CSRF/validation, and the exact order destination. Order/sync browser endpoints are intercepted during destination testing.
- Light/dark screenshots and no-overflow assertions at 320, 500, 999 and 1600px. A 320×480 check verifies the panel stays in the viewport and retains a usable list. Minimum sampled notification text contrast: 15.60:1. This is Chromium simulation, not physical-device or comprehensive assistive-technology certification.
- One real local evaluator run completed in 0.198 seconds and materialized notification records successfully. It used local data only and did not acknowledge incidents or call upstream services. Timing is one observation, not a performance guarantee.
- PHP/JS syntax checks, generated CSS build and whitespace checks passed. Screenshots are ignored in `tmp/notifications-ui/`.

Run the browser suite against an isolated server (default port 8131):

```sh
php -S 127.0.0.1:8131 -t public router.php
PLAYWRIGHT_MODULE=/absolute/path/to/playwright node tests/notifications-ui.cjs
```

Use `NOTIFICATION_TEST_URL` to override its origin. The suite creates and deletes its own PHP session; no business mutations are sent. Re-run the PHP suite against a database user allowed to create temporary tables.

## Design review and antislop gate

UI UX Pro Max and antislop were applied. Direction: Shopdash operational inbox, ENERGY 1 / RHYTHM 2 / MOTION 1. Retain the existing warm palette, font and Material Symbols. A bounded vertical list supports scanning; logos identify shops; inventory/shipping/connection/sync icons distinguish actionable problems. Urgency has text and a small edge marker rather than color alone. No decorative animation was added. Footer copy explains read versus resolved because that distinction affects the user's action.

| Gate | Result and evidence |
| --- | --- |
| R-02/03/17/18 | PASS: concise factual copy, no em dash/testimonials/invented statistics, viewport and overflow assertions |
| R-23/24/26 | PASS: existing identity/icons, scoped working destination links, filter/read/paging/reload/close interactions exercised |
| R-25/27/28 | PASS: measured text contrast, loading/empty/error/stale states, no generic FAQ |
| R-32/33/34/35 | PASS: keyboard and focus assertions, source CSS plus rebuilt output, both themes, isolated runtime verification |
| R-36/37/38 | PASS: data limitations visible, direction stated above, synthetic fixtures only in tests |
| R-01/04/06/07/08/09 | PASS: no decorative gradients/patterns; existing type/icon family; chevrons paginate; badge reports actual unread count |
| R-10/12/13/14/19/22 | PASS: solid panel, modest popup separation, no glow/illustrations or ornamental repeated cards/motion |
| Dials, rhythm, focal point, whitespace, accent, motif, design read | PASS: calm operational list, one destination per incident, grouped shop identity and actions, spacing protects touch targets |
| C-1/2/3/4/5 | PASS within stated test scope: choices justified above, functional controls, actual incident content, responsive/error resilience, reproducible evidence |
| R-05/11/15/16/20/21/29/30/31 | PASS: task-specific inbox, existing radii/palette/themes, concrete Indonesian action labels, no borrowed product styling or generic marketing copy |

## Integration boundaries

Built in an isolated worktree. Integrated Boost PR #3 (`c0a1c3f`) before delivery; source CSS merged and generated CSS rebuilt from the combined source. No changes to Boost, Chat or Finance/HPP implementation files. Subsequent agents should preserve the notification API/revision contract and use temporary-table/mocked tests before extending detectors.
