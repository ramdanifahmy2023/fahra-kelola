# Shopdash audit implementation — 2026-10-12

All changes in this audit are isolated on branch `audit/implement-20261012`.
The pre-audit Git bundle is stored outside the repository at
`../backups/shopdash-pre-audit-20261012.bundle`.

## Implemented areas

- Tenant ownership checks for shops, orders, products, customers, chat,
  notifications, reports, Finance/HPP, AI connections, automation profiles,
  search, Boost, Ads, Promotions, and workspace data.
- CSRF and HTTP method enforcement for state-changing routes, safe redirect
  handling, session hardening, login throttling, response headers, and generic
  database error responses.
- Shopee request validation: exact HTTPS hosts, port restrictions, redirect
  refusal, cookie control rejection, and TLS verification.
- Durable sync reliability: transactional enqueue, cursors, scheduler and
  worker leases, fencing, bounded retries, permanent failure state, and
  shop-scoped writes.
- Accessibility and UI resilience: skip links, focus restoration, dialog
  semantics, keyboard picker support, live status announcements, reduced
  motion, touch targets, and fetch error states.
- Fresh schema and additive reliability migration for existing installations.

## Verification

- `npm run build`
- PHP lint across the repository
- JavaScript syntax check across `public/assets/js`
- `git diff --check`
- 17 local regression suites covering sync, Finance, Ads, Boost, AI,
  customers, chat, releases, and workspace behavior

No merge or push is performed by this branch. After manual validation, merge
this branch into `main`; the bundle can restore the pre-audit commit if a
rollback is needed.

## Deployment notes

Apply `database/migrations/20261012_sync_reliability.sql` before restarting
the scheduler and worker services. Existing installations must backfill
`shops.account_id` before enforcing the fresh-schema `NOT NULL` and foreign
key definition. Cookie encryption at rest remains a separate migration because
the deployment does not yet provide a stable key rotation contract.
