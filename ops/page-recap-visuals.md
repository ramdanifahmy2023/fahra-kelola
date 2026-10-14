# Page recap visuals

Implemented 2026-10-14 in the isolated `codex/visual-recaps-20261014` worktree. This note records the recap contract added across operational pages.

## Reading the summaries

- Shared layout: `app/views/panel/templates/page-recap.php` renders one lead value, supporting metrics, a status breakdown, optional daily trend and per-shop comparison, and an explanatory note. Links in a breakdown lead to the matching filtered list; shop comparisons on Chat and Ads also select the related shop. Keyboard focus remains visible, and data labels accompany color.
- Pesanan counts use the selected shop and date range. Orders without `created_at` remain in the existing order list and total, and are called out separately. The daily trend and prior-period comparison use dated orders only. “Pesanan kilat” means `order_type = 'advance'`. Cancellation is an order state; a failed sync attempt appears on Sinkronisasi and does not mean the order failed.
- Product counts use the selected shop and exclude soft-deleted products. Stock buckets separate empty (Shopee out-of-stock status or active known stock at or below zero), low (active stock 1–14), healthy (active stock 15+), unknown stock, and archived products. Unknown stock is never silently reported as zero.
- Customer groups describe lifetime locally stored order history: no orders, one order, and repeat (two or more). The page does not label a customer “new” by date because the stored customer creation timestamp is not a reliable first-purchase date.
- Sync status counts the latest job state per shop and sync type. The headline describes jobs whose latest attempt needs attention; it is not a count of every historical retry. Filters narrow the visible schedule cards.
- Ads totals include only shops with an available report for the selected period and channel. Each metric shows coverage; combined ROAS uses only shops where both cost and attributed sales exist. Top-up is kept separate from advertising cost.
- Promotion totals include shops with available summaries, with stale, expired, failed, and pending shops shown separately. Chat unread counts use the latest locally stored shop snapshots; shops may have different refresh times. The Shops page shows connection state and locally stored product totals.

## Verification evidence

On 2026-10-14 the app ran from this worktree on `127.0.0.1:59159`, a port verified free before startup. `tests/page-recap-ui.cjs` exercised the shared recap template, synthetic partial-data states for Chat / Sync / Ads / Promotions, keyboard drilldowns, shop-filter preservation, four viewport widths (320, 500, 999, 1600), both Shopdash themes, contrast checks, and eight authenticated local empty-account routes. Browser API mutations were mocked or unused. `tests/page-recap-data.php`, `tests/customer-shop-filter.php`, `tests/order-sync.php`, `tests/product-sync.php`, `tests/ads-performance.php`, and `tests/chat.php` also passed.

The browser fixture uses synthetic data and does not validate Shopee reconciliation or current live shop freshness. A local account with no shops was used for route smoke checks; populated real-shop review remains useful after the change reaches the shared environment.
