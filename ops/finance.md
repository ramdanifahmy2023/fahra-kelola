# Keuangan dan HPP

Implemented and verified 2026-09-29. Route: `/panel/finance`. Shared team access follows the existing authenticated panel. Filters use local `shops.id`; upstream identity remains the remote ID verified against that row's saved cookie.

## User contract

- Pending is the latest successful Shopee overview snapshot. Changing dates does not turn it into historical pending.
- Released uses the Shopee overview when selected dates exactly match its current week or month through the snapshot date. Otherwise it sums income details by actual release date, inclusive, WIB.
- Overview and detail totals are separate facts. Live reconciliation found differences for both pending and released. The page displays the source and difference; do not silently substitute a guessed adjustment or claim exact reconciliation.
- Missing data is null. A verified empty response is zero. Partial coverage, queued work, failed imports and timestamps remain visible per shop. All-shop totals sum only available selected-shop values and disclose coverage.
- Omset is paid GMV from the existing `shop_performance_daily` homepage source. Ads is consumed cost, not topups. Existing daily snapshots provide only the dates actually present; custom filters do not invent history.
- Shipping/return/delivered buckets are subsets of the pending details. The current source has many orders without usable local status descriptions, so those amounts stay under unknown. Return processing is not a confirmed refund deduction.
- HPP is one integer rupiah amount per unit per shop/product/variant. SKU is a searchable label, not a globally unique key. Duplicate and empty SKUs cannot mix shops or variants.
- HPP effective dates follow **order creation date in WIB**, because the existing orders lack a reliable paid-at timestamp. Per-order HPP in income details uses quantity times applicable version. Missing item detail or cost remains unknown. This is not a profit calculation.
- Retroactive changes require a signed preview. It states affected locally stored orders/units, previously known cost and missing cost; it includes stored cancelled/returned orders and does not claim an entire Shopee-history calculation. Preview changes/expiry and stale cost versions are rejected. Cost changes preserve dated versions and append audit events. Product resync/deletion does not delete HPP history.

## Verified upstream mapping

The source mapping was audited with the user's local XYZ Sniper MCP captures and Seller Centre UI, then exercised using the existing server-side cookie transport. No MCP token, cookie, buyer record or raw payload belongs in this document.

| Read | Method / fields | Meaning |
| --- | --- | --- |
| `/api/v4/accounting/pc/seller_income/income_overview/get_income_overviews` | GET, `list[].type/amount` | 9 pending; 6 current week released; 7 current month released; 8 all-time released. Divide monetary integers by 100000. |
| `/api/v4/accounting/pc/seller_income/income_overview/get_income_detail` | POST `source_type:0`, `income_category:1` or `2`, `pagination_info:{direction:0,limit:50}` | Category 1 pending is undated. Category 2 uses `local_query_condition:{start_date,end_date}`. |
| Pagination | `data.next_page` cursor/direction | Follow the supplied cursor, deduplicate order IDs within the import, reject repeated cursors. Actual empty stores return `code:0,data:[]`; absent/malformed data or nonzero code is failure. |
| Detail | `data.list[].local_income_detail` | Whitelist order ID/SN, product name/count, income, adjustment, net amounts and timestamps. Buyer/contact payloads are not retained. |
| Amounts | `income_amount`, `adjustment_income_amount`, `net_income_amount` | Store scaled integers separately. Pending's net can be zero, so it cannot replace income. Do not use adjustment to force a match with overview. |
| Dates | `income_released_time`, `income_estimated_escrow_time` | UTC storage; released calendar day in WIB. Estimated release remains explicitly estimated. |
| Return | `order_status_transify_key=ps_content_return_processing` | Pending return processing. Generic numeric status 2 does not prove shipping. |
| Omset | Existing `/api/mydata/v3/dashboard/key-metrics/` import, `paid_gmv.value` | Whole IDR, existing local daily rows. |
| Ads | Existing `/api/pas/v1/report/get_time_graph/`, daily `raw_metrics.cost` | Scaled by 100000. Choose freshest report per day/channel, require product+shop+live for a complete daily total, never sum overlapping daily/weekly/monthly reports. |

## Storage and synchronization

`Finance`, `FinanceCost`, `FinanceApi`, `FinancePolicy`, and `ProcFinance` own this feature. `database/migrations/20260929_finance.sql` only creates new tables. Models ensure the schema without running the destructive bootstrap.

- `finance_imports` and `finance_income_rows`: staged resumable pages. A failed new import leaves the previous complete data visible.
- `finance_current`: latest complete pending import per shop. Its associated `finance_overview_totals` stores the overview amounts and actual calendar boundaries.
- `finance_days`: latest complete released import covering each day, including verified empty days. Newer overlapping imports replace pointers, not add duplicate totals.
- `finance_cost_heads`, `finance_cost_versions`, `finance_cost_events`: product snapshots, dated costs, append-only cost audit. No cascading product foreign key.
- Finance job type defaults to 600 seconds. One turn handles at most three pages, respects the worker rate delay and yields through the existing queue. Per-shop advisory locks also coordinate the dedicated CLI.
- Initial scheduled import covers the current month; once previous days are covered, scheduled refreshes cover the last three days plus all pending. Manual selected-period refreshes support older custom ranges, split by month. Older-period adjustments need a manual refresh of that period.
- Ordinary page reads never call Shopee. `POST /procFinance/sync` queues work. Cost preview/save and manual sync require session CSRF. No bank withdrawal, payment, product-price update or Shopee business mutation is performed.

Commands from repository root:

```sh
php bin/finance-sync.php --schema-only
php bin/finance-sync.php --shop=1 --start=2026-09-01 --end=2026-09-29 --pages=100
php bin/finance-sync.php --pending-only --pages=100
```

The first only applies additive finance schema. The others read Shopee and write finance imports; they do not claim or run other job types. Omitted shop means all stores. `--pages` bounds work per shop (maximum 2000); inspect `ok` and `complete` separately. A subsequent run resumes queued/running imports. `--pending-only` queues only new pending work, but existing released work for that shop is still resumed. No service reinstall or second shared worker is needed.

## Verification and limits

- Additive migration applied to the local runtime. Seven stored shops completed current-month release imports and pending/overview imports, including actual empty stores. One busy shop required 82 released pages, exercising real cursor traversal.
- `php tests/finance.php`: temporary tables and isolated advisory-lock names. Covers publication after final page, failed/repeated cursor, deduplication, overlapping windows, shop identity, null versus zero, WIB, overview precedence/differences, unresolved import errors, duplicate SKU variants, scoped order HPP, dated changes, stale preview/version, audit and product deletion survival.
- `node tests/finance-ui.cjs`: authenticated local reads/preview, mocked saves/sync. Tests store subsets, preserved dates/reload, detail searches, missing results, preview invalidation, concurrent-edit feedback, CSRF/invalid input, failure recovery and keyboard. Screenshots/assertions at 320, 500, 999, 1600 in both themes. Screenshots remain ignored under `tmp/finance-ui/`.
- Existing `php tests/sync-recovery.php` passed 27 checks, `php tests/ads-performance.php` passed 135 checks, `node tests/ads-ui.cjs` passed 267 browser checks, and the Automation browser suite passed. The shared single-shop Ads/Topup selectors retain their behavior.
- Omset and ads historical completeness depend on their existing importers. The finance page discloses date coverage; it does not backfill these domains or interfere with another agent's ads/extension work.
- Wallet cash balance, bank withdrawals, finalized refund deductions and full historical shipping classification are outside this initial release. The user's primary Pending/Released and HPP flows are implemented. Do not present unknown statuses as confirmed zero shipping or return losses.
- No raw import retention cleanup is enabled yet. Complete and failed import snapshots are preserved for reconciliation; a future retention policy must preserve every import referenced by `finance_current`/`finance_days` and the cost audit.

## UI direction and final antislop review

Design read: existing warm Shopdash themes and product selector; users first compare pending versus released, then investigate a shop or edit its product cost. ENERGY 1 / RHYTHM 2 / MOTION 1. Two prominent balances share a surface; supplementary metrics and per-shop rows stay smaller. Orange marks the refresh/save actions. Existing typography, currency tabular numerals, 8/12px surfaces and real logos keep the product identity. Dropdown motion is disabled so closing with Escape actually hides it immediately.

| Gate | Result and evidence |
| --- | --- |
| R-01 | PASS: existing theme tokens, no new gradient. |
| R-02 | PASS: direct Indonesian labels describe the data and action. |
| R-03 | PASS: four tested widths, no horizontal page overflow. |
| R-04 | PASS: wallet navigation icon and storefront fallback carry meaning; real shop logos. |
| R-05 | PASS: paired balances, shop ledger, detail table, cost form follow their respective tasks. |
| R-06 | PASS: existing typography; tabular monetary numbers for comparison. |
| R-07 | PASS: existing solid surfaces, no decorative background pattern. |
| R-08 | PASS: no decorative action arrows. |
| R-09 | PASS: textual status/coverage, no promotional badges. |
| R-10 | PASS: no glass effect. |
| R-11 | PASS: existing radius scale, no pill-shaped page. |
| R-12 | PASS: inherited button/menu elevation only. |
| R-13 | PASS: no added glow. |
| R-14 | PASS: two primary values emphasize the user's stated priority. |
| R-15 | PASS: Perbarui data, Terapkan, Periksa dampak, Simpan HPP. |
| R-16 | PASS: concrete copy without marketing claims. |
| R-17 | PASS: real imports; nulls, partial periods and differences disclosed. |
| R-18 | PASS: no testimonials. |
| R-19 | PASS: no ornamental animation; immediate dropdown close verified. |
| R-20 | PASS: existing Shopdash shell, palette, logos and selectors. |
| R-21 | PASS: inherited theme preference; both modes exercised. |
| R-22 | PASS: no decorative illustration. |
| R-23 | PASS: supplied brief and existing identity guide layout; no invented assets. |
| R-24 | PASS: new sidebar route and all three content sections work. |
| R-25 | PASS: contrast checker measured primary text/light surface 17.57:1, dark surface pair 15.60:1, primary-button pair 6.62:1; visible input outlines/focus. |
| R-26 | PASS: actual reads/preview, isolated database saves and mocked browser write states tested. |
| R-27 | PASS: loading, no match, missing data, error/retry and disabled save exercised. |
| R-28 | PASS: explanatory disclosure serves financial definitions; no filler FAQ. |
| R-29 | PASS: inherited neutral surfaces and orange action accent. |
| R-30 | PASS: follows existing Shopdash identity and the supplied Shopee terminology. |
| R-31 | PASS: visual decisions and their purposes recorded above. |
| R-32 | PASS: dropdown keyboard selection/Escape and native dialog Escape tested. |
| R-33 | PASS: authored source edits through apply_patch; only Tailwind compiles CSS. |
| R-34 | PASS: light/dark viewport assertions and inspected screenshots. |
| R-35 | PASS: real local UI/API plus isolated database failure cases, not syntax alone. |
| R-36 | PASS: no fabricated performance/security claims; source differences preserved. |
| R-37 | PASS: user priority and existing visual direction carried into final design read. |
| R-38 | PASS: empty fields remain empty; examples/tests are separate from live finance. |
| Dials | PASS: declared 1/2/1 and reflected in calm paired totals and task-specific sections. |
| Focal point | PASS: Pending and Sudah dilepas dominate the first data surface. |
| Spacing | PASS: separates filters, balances, investigation and editing. |
| Accent | PASS: orange denotes primary refresh/save actions. |
| Motif | PASS: existing Shopdash typography, ledger values and branded shop selection. |
| C-1 | PASS: hierarchy follows the user's stated money questions. |
| C-2 | PASS: each control has tested behavior and failed writes preserve input. |
| C-3 | PASS: no chart or extra template section without supporting data. |
| C-4 | PASS: themes, widths, keyboard, missing/error/conflict states verified. |
| C-5 | PASS: explicit real-source coverage and documented verification limits. |
