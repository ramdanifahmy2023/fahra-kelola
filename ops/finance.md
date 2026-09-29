# Keuangan dan HPP

Implemented and verified 2026-09-29. Route: `/panel/finance`. Shared team access follows the existing authenticated panel. Filters use local `shops.id`; upstream identity remains the remote ID verified against that row's saved cookie.

## User contract

- Default filters, confirmed by the user on 2026-09-29: **Semua toko**, with **Bulan ini** meaning the first day of the current month through today, inclusive, in Asia/Jakarta. Explicit shop/custom-date choices remain available. A fresh default view follows the current month; never hard-code the audit month or silently replace a user's custom range.
- Dashboard and Finance now share the monetary overview, source, filters, and explanatory states through `app/views/panel/finance.php` and `public/assets/js/finance.js`. The Dashboard's separate intraday feed is explicitly labelled Hari ini and Penjualan terkonfirmasi, not monthly paid GMV. See [Dashboard and Finance UI verification](dashboard-finance-ux.md).
- "Realtime" means the latest successfully synchronized data with an honest update timestamp and coverage. A current date range does not prove fresh or complete data. Pending remains the latest position; the month filter applies to paid GMV, consumed advertising cost, and released funds. An open default view advances in WIB on its next visible refresh, including month rollover. Custom ranges stay fixed across refresh and reload.
- Pending is the latest successful Shopee overview snapshot. Changing dates does not turn it into historical pending.
- Saldo Penjual (2026-09-30) follows the exact **Saldo** in Shopee Saldo Saya, `wallet_available_balance` in raw rupiah. It is a latest position, independent of report dates, scoped by selected shops. It is not Saldo Aktif or an estimate of withdrawable money. Restricted shops still contribute their full Saldo. Only explicit Shopee restriction messages are displayed; no held amount is inferred. See [wallet source audit](finance-wallet-plan-20260930.md) and [implementation verification](finance-wallet-implementation-20260930.md).
- Released uses the Shopee overview when selected dates exactly match its current week or month through the snapshot date. Otherwise it sums income details by actual release date, inclusive, WIB.
- Overview and detail totals are separate facts. Live reconciliation found differences for both pending and released. The page displays the source and difference; do not silently substitute a guessed adjustment or claim exact reconciliation.
- Missing data is null. A verified empty response is zero. Partial coverage, queued work, failed imports and timestamps remain visible per shop. All-shop totals sum only available selected-shop values and disclose coverage.
- Omset combines `shop_performance_daily` paid GMV with verified `paid_gmv.value` for today from the same Shopee key-metrics endpoint (`period=real_time`). Each date contributes once; today's newer observation replaces an overlapping daily row and remains provisional with its report cutoff. Intraday snapshots are never treated as final historical days after midnight. Missing dates stay explicit. Ads is consumed cost, separate from topups.
- Pending subdivisions cover preparation, pickup/courier verification, shipping, delivered awaiting release, return processing, mixed-package stages, and unknown. Both order-level and package-level cards are read in five-order batches. Multiple packages contribute one income amount per order, including mixed-stage orders. Missing or failed reads retain a sanitized reason. Return processing is not a confirmed refund deduction.
- Top up and consumed advertising cost are separate figures for the selected shops/period. Top ups reuse `ad_balance_topups.actual_price` in whole rupiah, including VAT, from the existing Ads importer. Date boundaries use the source transaction's `create_time` converted to WIB, matching Ads history, not an inferred payment-completion time. History starts 2026-08-01. Unsynced empty data is null; a fully backfilled, covered empty period is zero. Source identity, backfill state, sync error, actual update time, and each shop's schedule remain visible. This feature does not change the Ads importer or its schedule.
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
| Pending delivery stage | POST `/api/v3/order/get_order_list_card_list`, `order_list_tab:100`, `need_count_down_desc:true`, `order_param_list:[{order_id,shop_id,region_id:"ID"}]` | MCP project 1, endpoint 1329, captured payloads 10101–10109; five-order requests verified live. Read `order_card.order_ext_info.order_id` and its status description, or `package_level_order_card.order_ext_info.order_id` and `package_list[].status_info.status_description.description_value`. Ignore unsolicited IDs; do not multiply money by package count. No buyer/address data is stored. |
| Delivery wording | Indonesian `Pesanan sedang dikirimkan ke Pembeli` / `Pesanan telah tiba di Pembeli`; English `Order is being shipped to buyer` / `Order has been delivered to buyer` | Confirmed against captures and current order responses. The short label `Telah Dikirim` alone is ambiguous. The old local field was populated from `status_tooltip`, often empty; legacy fallback now reads the actual nested description and retains its 15-minute freshness gate. |
| Ad top ups | Existing GET `/api/valueadded/v1/search_order_list/`, `biz_id:1,status:completed` | MCP endpoint 10513: observed `actual_price:27750,original_price:25000,tax_amount:2750`. Topup amount already includes VAT and is not scaled by 100000 or substituted for consumed cost. |
| Omset | Existing `/api/mydata/v3/dashboard/key-metrics/`, `paid_gmv` | Historical import uses daily points. MCP endpoint 992 / capture 12494 verifies `period=real_time`, midnight WIB through the current whole hour, and `paid_gmv.value`. Live reads confirmed this for today's data. Only floating noise within 0.00001 of whole rupiah is rounded. |
| Ads | Existing `/api/pas/v1/report/get_time_graph/`, `raw_metrics.cost` | Scaled by 100000. `FinanceAdCost` selects the freshest verified report per channel for the exact selected period, including browser captures. Alternatively use complete genuine daily coverage. Require product+shop+live; never add overlapping daily/weekly/monthly totals or divide an aggregate into invented daily amounts. |

## Storage and synchronization

`Finance`, `FinanceCost`, `FinanceApi`, `FinancePolicy`, and `ProcFinance` own this feature. `database/migrations/20260929_finance.sql` only creates new tables. Models ensure the schema without running the destructive bootstrap.

- `finance_imports` and `finance_income_rows`: staged resumable pages. A failed new import leaves the previous complete data visible.
- `finance_current`: latest complete pending import per shop. Its associated `finance_overview_totals` stores the overview amounts and actual calendar boundaries.
- `finance_wallet`: latest successful wallet amount, optional source restriction message, update/attempt/request timestamps and safe error, keyed by local and remote shop identity. Additive migration `20260930_finance_wallet.sql`. `FinanceWalletApi` verifies identity before/after a TLS-verified GET; `Finance` rechecks stored cookie/source before publication. Only whitelisted fields are stored. Failure retains the previous successful snapshot, including its original timestamp.
- `finance_pending_states`: per-import, per-order status snapshots, published alongside the income rows. Failed/missing reads record unknown without hiding valid income. Return processing from the income source takes precedence. A new import never borrows a status snapshot from a different import/shop. No destructive migration or changes to the shared order/extension pipeline are needed.
- `finance_pending_diagnostics`: sanitized reason codes for unread, missing, unrecognized or mixed-package status and pickup detail. No raw customer payload is retained.
- `finance_paid_intraday`: identity-scoped daily observations of provisional paid GMV with hour cutoff, successful fetch time and failure state. Refresh after a completed pending import; source failure retains the previous amount. At midnight wait for the first report hour. This uses the existing Finance cadence, not a new worker or extension change.
- `finance_days`: latest complete released import covering each day, including verified empty days. Newer overlapping imports replace pointers, not add duplicate totals.
- `finance_cost_heads`, `finance_cost_versions`, `finance_cost_events`: product snapshots, dated costs, append-only cost audit. No cascading product foreign key.
- Finance job type defaults to 600 seconds. One turn handles at most three pages, respects the worker rate delay and yields through the existing queue. Per-shop advisory locks also coordinate the dedicated CLI.
- Wallet due checks run inside the same Finance lock before income work/early return. They use the shop's Finance interval (600 seconds default), independently of income completion/failure. Manual Finance sync requests both wallet and income, with a 60-second wallet debounce and existing queue deduplication. A wallet failure does not fail valid income work. Staleness starts at twice the interval. Browser polling reads stored snapshots every 30 seconds, or 5 seconds while work is pending; it does not call Shopee per visitor.
- Each pending page can additionally read up to ten five-order batches, paced with the worker delay. Status enrichment stops on transport/API failure or once 30 seconds have elapsed before starting another batch; an in-flight request retains the transport's 30-second timeout. Unread statuses remain unknown and are retried with the next pending import. Released pages do not make status requests. Status and income update times are shown separately.
- Initial scheduled import covers the current month; once previous days are covered, scheduled refreshes cover the last three days plus all pending. Manual selected-period refreshes support older custom ranges, split by month. Older-period adjustments need a manual refresh of that period.
- Ordinary page reads never call Shopee. `POST /procFinance/sync` queues work. Cost preview/save and manual sync require session CSRF. No bank withdrawal, payment, product-price update or Shopee business mutation is performed.

Commands from repository root:

```sh
php bin/finance-sync.php --schema-only
php bin/finance-sync.php --shop=1 --start=2026-09-01 --end=2026-09-29 --pages=100
php bin/finance-sync.php --pending-only --pages=100
php bin/finance-sync.php --repair-pending --shop=1
php bin/finance-sync.php --wallet-only --shop=1
```

The first only applies additive finance schema. The others read Shopee and write Finance data; they do not claim or run other job types. Omitted shop means all stores. `--pages` bounds import work per shop (maximum 2000); inspect `ok` and `complete` separately. A subsequent normal run resumes queued/running imports. `--pending-only` queues only new pending work, but existing released work for that shop is still resumed. `--repair-pending` takes the same per-shop worker lock, checks up to 100 unresolved current pending orders, updates their status/diagnostic records without changing amounts, and refreshes today's paid GMV. It does not enqueue or process full income imports. A busy lock reports `busy:true`; retry later without starting another shared worker.

`--wallet-only` reads only wallet/identity endpoints and writes the wallet snapshot, using the same Finance shop lock and due interval. It neither queues income imports nor starts a background runner. Omit `--shop` for all stores. `updated:false` means not due; `busy:true` means the existing worker owns the lock. Inspect `ok` for source failures. It performs no withdrawal.

## Verification and limits

- Additive migration applied to the local runtime. Seven stored shops completed current-month release imports and pending/overview imports, including actual empty stores. One busy shop required 82 released pages, exercising real cursor traversal.
- `php tests/finance.php`: temporary tables and isolated advisory-lock names. Covers publication after final page, failed/repeated cursor, deduplication, overlapping windows, shop identity, null versus zero, WIB, overview precedence/differences, unresolved import errors, duplicate SKU variants, scoped order HPP, dated changes, stale preview/version, audit and product deletion survival.
- `node tests/finance-ui.cjs`: authenticated local reads/preview, mocked saves/sync. Tests store subsets, preserved dates/reload, detail searches, missing results, preview invalidation, concurrent-edit feedback, CSRF/invalid input, failure recovery and keyboard. Screenshots/assertions at 320, 500, 999, 1600 in both themes. Screenshots remain ignored under `tmp/finance-ui/`.
- Existing `php tests/sync-recovery.php` passed 27 checks, `php tests/ads-performance.php` passed 135 checks, `node tests/ads-ui.cjs` passed 267 browser checks, and the Automation browser suite passed. The shared single-shop Ads/Topup selectors retain their behavior.
- Integrated upstream 9Router commit `ad070b1` without changing its feature code. Both source styles were retained and CSS rebuilt. Finance, 9Router connection UI and Automation UI suites passed after integration. The isolated server used the existing runtime keyring path; no master key was replaced or generated.
- Omset and ads historical completeness still depend on genuine source coverage. Today's paid GMV and exact-period ads aggregates are now supported; custom gaps remain explicit. See [follow-up evidence and responsive review](finance-completeness-20260929.md). The Ads importer, extension and unrelated workers are unchanged.
- Saldo Penjual is implemented; Saldo Aktif, withdrawal eligibility/estimates, bank withdrawals, finalized refund deductions and historical shipping positions remain outside scope. Current shipping classification is implemented for verified order-card descriptions; unknown states remain visible. Do not present unclassified zero buckets as confirmed zero shipping or return losses.
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
