# Dashboard and Finance clarity

Implemented 2026-09-29 from the user's UI audit and explicit implementation request. The user confirmed all shops and the current month from day 1 as defaults. This document records the changed surfaces; earlier UI audit notes are historical observations.

## Reading the screen

- `/panel` and `/panel/finance` share the same monetary template, controller source, and client rendering. Pending and Released dominate; paid GMV and consumed advertising cost are secondary. Amounts are not combined into a fabricated wallet balance or profit.
- The shop picker uses existing logos and keyboard behavior. Its applied scope appears below the form. Draft selections explicitly require Terapkan. All-shop scope is encoded as an empty selection parameter so a new page includes newly added shops.
- Bulan ini means day 1 through today in WIB. Hari ini and custom dates are available. Presets advance on the next visible refresh at midnight/month rollover; custom dates and shop subsets survive reload and navigation to Finance. The date fields appear only for custom periods.
- Pending is the latest snapshot, independent of report dates. Released and the secondary figures carry the selected date range. Released is explicitly labelled as money released by Shopee, not bank withdrawal.
- Missing figures stay unavailable; verified zeroes stay zero. Partial totals have a prominent text-and-icon warning, with coverage per shop. No day or shop is silently imputed. Timestamps identify available snapshots, not a promise that every upstream source has finished updating.
- Preparation, pickup/verification, shipping, delivered-awaiting-release, return processing, and unknown statuses are displayed under Pending. Mixed-package stages appear when present and count each order once. Known buckets are labelled as identified amounts when classification is incomplete. Pending detail and overview differences remain visible; no cause is invented.
- The per-shop ledger uses actual logos and separate dates/coverage. Reconciliation and HPP explanations remain available through keyboard-accessible disclosures. Existing dated HPP editing, preview, concurrent-edit rejection, and history are retained.

## Dashboard sources and scope

- Monetary figures call `/procFinance/summary`; omset always uses `paid_gmv`. Historical daily rows combine with the latest verified same-day `paid_gmv.value`, with no duplicated date and a provisional today label. The independent activity `sales` field is not used as paid GMV.
- The independent activity section says Hari ini and explicitly excludes the report date filter. Its source is the existing Shopee realtime endpoint with `event=confirmed`; labels describe confirmed sales. It follows the same selected shops as the monetary overview.
- `DashboardMetrics` preserves per-metric and per-hour coverage. Missing values/timestamps do not become zero or now. The summary timestamp uses the oldest known included source; unknown source timestamps remain unknown. Product rows keep shop identity and do not merge identical names from different shops.
- `/procDashboard/overview` scopes all operational data to the selected local shop IDs. Orders use creation dates with inclusive WIB boundaries converted to UTC storage. Missing dates are disclosed outside period counts. Product/stock/connection data are latest positions; customer membership is scoped but covers all stored time, explicitly labelled.
- Customer membership deduplicates mapping and order-derived IDs. The selected buyers are materialized once to avoid a correlated scan per customer. One read-only measurement on the existing seven-shop dataset completed the operational summary in 0.050 seconds; this is an observation, not a performance guarantee.
- Shared authenticated team access follows the existing Finance/panel contract. The old owner-only realtime selection is aligned with that scope. No tenant/RBAC isolation is claimed and no cookies are sent to the browser.

## Refresh behavior

- Finance reads local snapshots every 30 seconds while visible, or every 5 seconds while finance imports are active. These are display reads, not new Shopee finance pulls. Returning to the tab refreshes it and reevaluates the WIB preset.
- Activity uses the existing direct realtime read every 30 seconds after the prior request completes, only while visible. Operational summaries read the local database every 30 seconds. All-shop source requests remain bounded by existing transport behavior; delays are not described as instant data.
- Perbarui saldo Shopee enqueues only Finance work for the applied shop/date scope. Background scheduler intervals and worker/extension processes are unchanged. The previous Dashboard pulse/reload snippet is replaced by display polling; the scheduler remains responsible for automatic imports.
- Filter changes clear the previous scope immediately. Request versions and aborts prevent late responses from relabelling old numbers under a new shop. Background reads preserve disclosure state and keyboard focus. Failed refreshes retain available timestamps with an explicit error and retry action.

## Design read

The existing Finance design direction is retained: ENERGY 1 / RHYTHM 2 / MOTION 1. Warm Shopdash surfaces, existing typography, and orange actions remain. The hierarchy alternates large paired balances, smaller supporting values, a grouped pending breakdown, and a shop ledger. No decorative illustration or new palette was introduced.

Icons encode meaning: hourglass for held money, wallet for released funds, receipt for paid orders, megaphone for advertising, truck for shipping, parcel for delivered orders, return arrow for returns, question mark for unknown classification. Existing shop logos identify ownership. Icons supplement readable text and are hidden from assistive technology when decorative.

Mobile custom-date inputs stay collapsed by default, redundant select-all actions are hidden when all shops are selected, and secondary Finance navigation sits beside the shop ledger. Product rows use a separate logo column and put amounts below long titles on narrow screens. The hourly chart has one time axis, fixed-size readable labels, a keyboard/touch hour selector, and a numeric table.

UI UX Pro Max searches were narrowed after off-topic first results. The applicable results were loading feedback (stable focus and explicit busy state) and time-series data access (clear time axis, direct values, table fallback). Antislop filters out decorative animation and generic filler. Existing bar encoding is retained for hourly amounts.

## Verification

- `php tests/dashboard.php`: isolated temporary tables exercise one/many/no shops, WIB boundaries, undated orders, missing values, shared customers, product identity, source timestamps, partial metrics and missing hourly values.
- `php tests/finance.php`: existing pagination/publication, source identity, effective-dated HPP, history, preview, and concurrency coverage; temporary tables and isolated locks.
- `node tests/dashboard-ui.cjs`: local authenticated reads, synthetic labelled realtime fixtures and mocked Finance writes. Covers global filters, delayed-response races, month rollover with a controlled browser clock, fixed custom dates, source failures/recovery, focus retention, and chart controls. Future clock fixtures never request future data from Shopee or the database.
- `node tests/finance-ui.cjs`: actual reads/previews, mocked saves/sync, HPP history/conflict, search, filters, reload, failure recovery, and keyboard navigation.
- Both browser suites check 320, 500, 999, and 1600px in light and dark themes. Screenshots remain ignored under `tmp/dashboard-ui/` and `tmp/finance-ui/`. Temporary test sessions are destroyed.
- A separate actual Dashboard read received HTTP 200 and all six activity metrics from 7/7 selected stores, with a source timestamp and no browser script errors. This is dated evidence, not a permanent health status. No user credentials or customer rows are reproduced here.
- Existing Ads tests verify 135 data assertions and 267 browser assertions. Generated CSS is rebuilt from source; finance/dashboard selectors are scoped to `#finance-page`.
- Contrast checks use the skill's checker on browser-resolved colors: primary text 17.57:1 light and 15.60:1 dark; partial warning 8.31:1 light and 10.75:1 dark; primary action 6.62:1 in both themes.

Historical importer gaps remain visible. The follow-up now reads matching-period ad aggregates and verified same-day paid GMV; it does not fabricate historical dates or reconcile Shopee overview/detail differences by assumption. The latest compact ledger and responsive verification are documented in [the follow-up review](finance-completeness-20260929.md).

## Specific coverage labels, 2026-09-30

Dashboard and Finance no longer use the generic `Total sementara · data belum lengkap` label. The warning beside each amount now distinguishes missing shops (`Baru 6 dari 7 toko terhitung`), a verified missing GMV date (`Data hari ini belum masuk`, or its actual date), and incomplete date coverage. Single-shop date coverage says how many days contribute; multi-shop coverage counts affected shops. If GMV dates differ, the exact dates remain in each shop's existing `Rincian & pembaruan` disclosure. Released funds and ad cost expose day counts only, so the UI never guesses which dates are missing.

Available current-day GMV has a separate explanation that today's amount can change. This does not hide historical gaps or mark otherwise covered dates as missing. Null remains unavailable and a verified zero remains Rp0. Topup notes use the existing failure flag, last update in WIB and history cutoff; a common known reason is also shown above the combined total. A partial total only includes the available source values. This change does not repair missing source data or change import schedules or money calculations.

The design remains ENERGY 1 / RHYTHM 2 / MOTION 1. The existing warning icon conveys coverage, stays beside the first text line on mobile, and supplements readable text. Repeated shop-count explanations were removed; detailed source notes stay collapsed. No new section, palette or animation was added. UI UX Pro Max searches were scoped to status/error clarity; the initial results mostly concerned forms, so only the general guidance about adjacent, readable feedback applies here. Antislop's copy and responsive checks were applied during work and final review.

Verification: the completeness browser suite covers seven shops, one shop, verified zeros, wholly unavailable data, missing today, common and different historical gaps, a partial shop total, topup refresh failure, history cutoff and an old WIB update. It asserts that current-day notes do not hide historical gaps and that dates are not invented for ad spend. The existing Dashboard suite checks month rollover and partial-data contrast; Finance and breakdown suites cover filters, HPP, source errors, pending drilldowns and topup separation. The revised labels were checked at 320/500/999/1600px in both themes, alongside the existing 684px content-width and 200% text checks. Source CSS is compiled into the tracked asset. These are UI tests and isolated fixtures, not evidence that live historical gaps have been filled.

All four browser suites passed. Build, JavaScript syntax and whitespace checks passed. The 320px dark and 1600px light screenshots were reviewed; the warning icon remains beside wrapped text. The final antislop gate below was reviewed again for this scoped change: R-02/R-09/R-17/R-27/R-36/R-38 are supported by the coverage cases, R-03/R-25/R-32/R-34/R-35 by browser verification and the build, and R-04/R-31 by the icon/wrapping purpose above. Other design choices are unchanged and retain the gate's documented rationale.

## Pending breakdown and advertising topups, 2026-09-29

The user approved this follow-up after a read-only audit. Pending subdivisions now show income amounts and order counts with `Lihat rincian` links. Links preserve the applied dates and selected shops; per-shop links narrow the same view to that shop. A visible `Bagian Pending` filter, search, and pagination use the same classification as the summary. The filter survives reload and is hidden for released funds.

The classifier now uses observed Shopee descriptions, not the usually empty local tooltip. Finance imports enrich pending rows from the verified five-order card endpoint; failures and unrecognized descriptions remain unknown. Amounts from a subset remain labelled `Belum lengkap`; an unclassified zero bucket says `Belum teridentifikasi`. Known empty snapshots still display zero. Total Pending is never added to its subdivisions. Reconciliation differences stay visible.

`Top up iklan berhasil` appears between paid GMV and consumed ad cost, both globally and per shop. It reads the existing Ads topup tables and includes VAT. Spend continues to use performance cost; the two are not added as one expense. Applied report dates, VAT basis, transaction count, source time, missing history, failures, and schedule state are disclosed. Automatic topup scheduling is unchanged (the audited seven-shop runtime uses 86400 seconds). Finance refresh remains a Finance-only action and now includes Pending status reads.

Design direction remains ENERGY 1 / RHYTHM 2 / MOTION 1. Three supporting figures form one desktop row; each store puts its two balances above the three period figures. Small screens stack the values. The existing `add_card` icon indicates adding advertising funds; text remains the primary label. No new brand assets or palette are introduced. UI UX Pro Max's relevant empty-state guidance informed the distinction between unavailable, incomplete, and verified empty data; antislop keeps these explanations factual. Pending drilldown links retain focus across polling.

Verification for this follow-up uses `php tests/finance.php` (temporary finance, order, topup and schedule tables) and `node tests/finance-breakdown-ui.cjs` (actual local reads plus isolated unknown/zero fixtures). It covers bounded status batches, unexpected IDs, English/Indonesian descriptions, source failures, publication, shop identity, VAT/rupiah units, WIB boundaries, cutoff coverage, topup/spend separation, counts, drilldowns, reload, keyboard, and 320/500/999/1600px in both themes. Existing Finance, Dashboard, and Ads regression suites remain applicable. Screenshots are ignored under `tmp/finance-breakdown-ui/`.

The follow-up passed Finance/ Dashboard PHP tests, 135 Ads data checks, the Finance and Dashboard browser suites, the new breakdown browser suite (including focus retention inside an open store disclosure), and 267 Ads browser checks. The Dashboard test now waits for its independent operations request before asserting its scope. Desktop/light and 320px/dark breakdown screenshots were inspected; no horizontal overflow or overlapping figures was observed. Existing theme contrast remains covered by the Dashboard browser assertions. Build, PHP/JavaScript syntax and whitespace checks passed. The final antislop gate below also applies to the revised hierarchy, factual missing-data copy, native drilldown links, shop scoping, and both themes.

Integrated concurrent Automation UI commit `4210d75` without modifying its source. The only generated-file conflict was resolved by rebuilding CSS from both sets of source styles. Breakdown, Dashboard, Automation, and AI connection browser tests passed on the combined revision. The separate test server used the existing runtime `AI_CONNECTION_KEY_FILE`; no key file or shared service was replaced.

## Saldo Penjual follow-up, 30 September 2026

Dashboard and Finance now share a third balance, **Saldo Penjual**, from the exact Saldo field on Shopee Saldo Saya. It follows the latest successful source read and selected shops, independently of report dates. Six figures per store form two compact rows at medium content widths. Source restrictions preserve the full amount and explain their reason only inside the existing disclosure. See [source, implementation, responsive checks and current antislop gate](finance-wallet-implementation-20260930.md). Historical evidence below remains scoped to its original change.

## Final antislop delivery gate

All results below concern the changed Dashboard/Finance surfaces. Browser suites, the Finance and Dashboard PHP suites, the Ads data/browser suites, source build, syntax checks, and whitespace review passed at delivery.

| Item | Result and evidence |
| --- | --- |
| R-01 | PASS: existing solid theme surfaces; orange actions and chart carry meaning. |
| R-02 | PASS: direct Indonesian labels; no introduced em dashes or filler headings. |
| R-03 | PASS: no page overflow at four widths; narrow product columns corrected and asserted. |
| R-04 | PASS: icons map to funds, shipping, advertising, or unknown status; purpose described above. |
| R-05 | PASS: balances, supporting amounts, pending breakdown, and ledger use distinct hierarchy. |
| R-06 | PASS: existing typography and tabular amounts; chart labels remain readable on mobile. |
| R-07 | PASS: no decorative grid or background pattern. |
| R-08 | PASS: no ornamental button arrows. |
| R-09 | PASS: warnings encode actual coverage with text and an information icon. |
| R-10 | PASS: no glass effects. |
| R-11 | PASS: existing 8/12px surfaces; compact icon containers serve grouping. |
| R-12 | PASS: inherited button/menu elevation, flat data surfaces. |
| R-13 | PASS: no glow. |
| R-14 | PASS: Pending/Released share emphasis; supporting figures and row details stay smaller. |
| R-15 | PASS: Perbarui saldo Shopee, Terapkan, Rincian & HPP describe real actions. |
| R-16 | PASS: operational copy without promotional claims. |
| R-17 | PASS: live numbers come from existing source contracts; synthetic tests are isolated fixtures. |
| R-18 | PASS: no testimonials. |
| R-19 | PASS: no new decorative animation; polling preserves focus and disclosures. |
| R-20 | PASS: Shopdash typography, warm colors, selectors, and actual shop logos retained. |
| R-21 | PASS: existing theme preference respected; light and dark tested. |
| R-22 | PASS: no unnecessary illustration. |
| R-23 | PASS: user requested icons/logos; actual existing shop identities are reused. |
| R-24 | PASS: new links resolve to existing Finance, Products, Shops, or a real pending section. |
| R-25 | PASS: text, warning, and action contrast measured in both themes, all at least 4.5:1. |
| R-26 | PASS: selectors, filters, retries, refresh, chart control, disclosures, and HPP flows exercised. |
| R-27 | PASS: missing/zero/partial, loading, delayed responses, errors and recovery tested. |
| R-28 | PASS: disclosures explain actual money definitions and reconciliation only. |
| R-29 | PASS: existing neutral palette, orange action/chart accent, semantic warning color. |
| R-30 | PASS: existing Shopdash identity, not an unrelated visual template. |
| R-31 | PASS: visual hierarchy, icon purpose, mobile layout, and chart access documented above. |
| R-32 | PASS: keyboard menu/Escape, visible focus, hour select and focus retention tested. |
| R-33 | PASS: authored source changes use patches; Tailwind generates the tracked stylesheet. |
| R-34 | PASS: both theme screenshots reviewed and responsive assertions passed. |
| R-35 | PASS: application served locally, built, and verified with actual reads and isolated edge cases. |
| R-36 | PASS: no unverified claim of complete data, profit, bank cash, or guaranteed source speed. |
| R-37 | PASS: inherited Finance design read and explicit user direction retained at 1/2/1. |
| R-38 | PASS: unavailable data stays unavailable; logos have existing storefront fallbacks. |
| Dials | PASS: calm surfaces, varied hierarchy, and no decorative motion match ENERGY 1 / RHYTHM 2 / MOTION 1. |
| Focal point | PASS: the paired balances dominate desktop and lead mobile data. |
| Spacing | PASS: separates filtering, reading balances, investigation, and operational detail. |
| Accent | PASS: orange identifies the source refresh action and chart amounts. |
| Motif | PASS: familiar Shopdash surfaces, monetary numerals, and branded shop rows. |
| C-1 | PASS: every revised group maps to the user's money/scope questions. |
| C-2 | PASS: controls have real behavior; failed or stale responses cannot masquerade as a new scope. |
| C-3 | PASS: sections represent real source data or required operations. |
| C-4 | PASS: themes, widths, keyboard, long names, unknown values, and failure states covered. |
| C-5 | PASS: source limits, test fixtures, measured contrast, and live verification are distinguished. |
