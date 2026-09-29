# Workspace comfort — 2026-09-30

Five improvements are implemented for the authenticated panel. They use the existing Shopdash palette, shop logos and Material Symbols. No new upstream sender or worker is introduced.

## Account preferences

`account_workspace` stores validated page filters and scroll position per authenticated account. Supported scopes are products, orders, customers, ads, reports, automation, chat, sync, boost, finance and dashboard. Only allowlisted fields are stored. Explicit URL filters take precedence; moving to another shop resets pagination. Focused search destinations ignore saved pagination. Scroll restoration applies to bare navigation, not explicit links or browser history restoration.

Single-shop pages share the latest selected shop. Ads monitoring and topup keep their separate controls. Finance/dashboard retain their existing multiple-shop filters. Client timestamps and conditional SQL updates reject late writes from older tabs. Storage failures show a retry action. This is account preference storage, not additional shop access isolation.

The additive migration is `database/migrations/20260930_workspace.sql`; model initialization also ensures the table and timestamp column. Never import the destructive base schema to apply it.

## Search and orders

The navbar search opens with its button or Ctrl/Cmd+K. Search reads local synced order numbers/tracking, product names/parent or variant SKU, and customer usernames. Results are scoped by shop, capped at six per category, and linked to exact local destinations. Queries use bound parameters and literal wildcard escaping. Customer addresses and upstream credentials are not returned. Product/customer focused destinations bypass pagination and provide a route back to the full list.

Below 640px orders use cards; desktop keeps the table. Native disclosures show items, courier, payment, tracking and the last detail sync. Desktop detail buttons open the same content in a keyboard accessible dialog. Shipping deadlines appear only for explicit open statuses with valid source timestamps. Copy tracking has success/failure feedback.

## Sync and notifications

Sync presents discovery, detail updates, queued, paused, retry and failed stages. Counts come from the latest job's actual `sync_job_orders` rows, not cached counters or historical totals. Percentage is shown only after order discovery closes with a known denominator. ETA requires an active error-free job, at least five recent completions, a sample of at least sixty seconds and a completion within 120 seconds. It uses the last five minutes and remains an estimate. Unknown totals never produce invented percentages. Status failures preserve the previous cards; mutation failures retain their error and re-enable controls.

Notifications group by shop/type, with detail navigation and an urgent-only filter. “Ingatkan 1 jam” stores an account/revision-specific reminder in additive `notification_snoozes`. Other accounts remain unaffected. Revision changes, escalation and reopening bypass an old reminder; expiry restores visibility without changing read state. Read does not resolve an incident. Existing panel polling still drives evaluation; this feature does not add an unattended detector worker.

## Verification and design review

UI UX Pro Max and antislop guided the implementation and final review: preserve the established identity, use restrained motion, concise actionable copy, visible focus, real logos, 44px controls and vertical shop menus. Closed shared shop menus now leave hit testing correctly. The browser suite checks widths 320, 500, 999 and 1600 in light/dark themes, keyboard search/dialog flows, grouped reminders, preferences and failure recovery. Sampled changed-surface text contrast was at least 12.96:1; this is not a claim about every legacy element.

Verified: `tests/workspace-comfort.php` (21 checks), `tests/notifications.php` (43 checks), customer shop filtering and product sync (15 checks). Browser suites: workspace comfort, notifications, shared shop picker, products and ads (267 checks). Dashboard/finance regression ran an isolated copy using a synthetic session to avoid modifying a real account's preferences. Notification/sync writes in browser tests are mocked; no real upstream sends, retries or worker activation were performed.

UI test sessions use nonexistent synthetic account IDs and clean their own preferences/session. Tests must not reuse production account sessions. A repository push is not evidence of public deployment.
