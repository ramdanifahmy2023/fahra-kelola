# Finance completeness and compact shop ledger

Implemented after the user's approval on 2026-09-29. The user identified the 684px-wide shop summary as too verbose and poorly arranged. This follow-up applies UI UX Pro Max and antislop during implementation and review; it preserves the existing Shopdash identity.

## Verified data corrections

- A direct, identity-verified audit of 37 unknown pending orders found 32 requiring shipping arrangement, four waiting for courier verification, and one recorded pickup. Live card-list reads returned `package_level_order_card`, with descriptions under `package_list[].status_info`. The old parser only read `order_card`. Both are now accepted; descriptions, requested IDs and the verified cookie identity remain the basis, not the ambiguous short status label.
- Preparation and pickup/verification are separate from shipping and arrival. Individual pickup rows explain whether pickup was recorded or courier verification is pending. Mixed package stages occupy a separate bucket and contribute the order's income once. Missing, unfamiliar, failed, budget-deferred and mixed responses retain reason codes, not raw customer records.
- `--repair-pending` successfully identified all 37 audited records using the current snapshot and existing worker lock. It only rereads unresolved statuses; ordinary imports still refresh statuses for all their pending orders. Old already-running worker processes may publish their prior parser results until they finish; do not restart unrelated shared workers to force adoption. Recheck the current snapshot after integration.
- All seven shops had verified matching-month ad aggregates for product, shop and live. Finance previously ignored those aggregates and counted only one day. The new reader uses the freshest valid exact-period total for each channel, including existing browser captures, or genuine complete daily data. A channel/day/period is never added twice. Arbitrary custom dates cannot inherit or proportionally divide a monthly amount. Identity, mapping version, currency, timezone, dates and timestamps are checked.
- MCP endpoint 992, payload 12494 verifies today's key-metrics request using `period=real_time`, midnight WIB and the current whole-hour cutoff. It includes `paid_gmv.value` and hourly points. Direct reads confirmed the source. Finance saves this paid metric separately; it never substitutes the confirmed-sales activity endpoint. One store returned whole rupiah with binary floating noise (`915200.0000000001`); rounding only accepts noise within 0.00001 of an integer.
- A live follow-up read found today's paid GMV available for all seven shops and the selected month covered for all seven ad totals. These are observations at the audit time, not a promise of permanent completeness. Today's paid value is provisional through the explicit report hour. After midnight, historical days must come from the daily importer; the incomplete intraday observation is not relabelled as a final day.

## UI decisions

Design read: a multi-store finance view for the shop's shared team, using Shopdash's warm surfaces, existing typography and orange actions; ENERGY 1 / RHYTHM 2 / MOTION 1.

- The two primary balances keep the strongest hierarchy because Pending and Released are the user's first questions. Supporting figures retain separate paid-GMV, topup and consumed-cost definitions.
- Each store starts with its actual logo/name and five amounts. Repeated timestamps, source explanations, transaction counts and schedules move into the keyboard-accessible `Rincian & pembaruan` disclosure. Missing data, delayed updates and reconciliation discrepancies remain visible outside it.
- At narrow widths, each metric is a label/value row. At medium content width, the two balances sit above the three period values. At wide content width, all five values align in one row. Container queries respond to the actual available space, including when the sidebar reduces it, rather than assuming viewport width equals content width.
- Pending subdivisions use existing meaningful parcel, truck, arrival, return and help symbols. The mixed-stage bucket appears only when it contains orders. No illustrative assets, gradients, ornamental motion or unrelated palette were added.
- Sparse source failures say what is missing. True zero remains Rp0. Today's available omset says it is provisional; exact missing dates, source cutoffs and failed updates remain in store details. Totals never become a fabricated wallet balance or profit.
- Touch controls retain at least 44px, visible focus and native disclosure behavior. Tables reflow on small content widths; long names and amounts wrap without hiding their contents.

UI UX Pro Max's targeted `responsive table mobile reflow` search returned Table Handling, Mobile First and Viewport Meta guidance. Its detected `html-tailwind` stack search returned compact label overflow, touch target and grid-gap guidance. The applicable recommendations support content reflow and 44px controls; antislop excludes decorative template additions.

## Verification

Tests use temporary database tables or read-only local page requests; browser writes are mocked. No real advertising, boosting, price changes or customer messages are sent.

- `php tests/finance.php`: both card shapes, pickup reasons, mixed/unrecognized packages, scoped repair, unchanged amounts, source failures, paid-GMV identity/overlap/date coverage/floating noise, existing imports and HPP regressions.
- `php tests/finance-ad-cost.php`: exact-period totals without daily detail, fresh browser source, overlap rejection, wrong identity/mapping/currency/timezone, custom dates, real daily intersections, missing channels, verified zero and stale reports.
- `php tests/dashboard.php` and `php tests/ads-performance.php`: existing scope and metric regressions.
- `node tests/finance-completeness-ui.cjs`: exact 684px content width; 320/500/768/999/1280/1600px in both themes; open disclosures; long names and large values; controls, HPP, 200% text reflow, amount/label containment and overlap assertions.
- Existing Finance, breakdown and Dashboard browser suites cover scope, filters, dates, selection, focus retention, source errors, HPP previews, custom ranges and WIB rollover. The Dashboard contrast test uses an isolated warning probe when live data happens to be complete, rather than requiring a real data failure.
- The measured seven-shop ledger at 684px was 2343px high before final data repair, versus the user's supplied 3966px context. Screenshots were inspected at 684px/light and 320px/dark. Artifacts stay ignored under `tmp/finance-completeness-ui/`.
- Browser-resolved text contrast, measured with the antislop checker: 17.57:1 light and 15.60:1 dark. Existing button and warning pairs are exercised by Dashboard browser tests.
- Integrated Boost commit `c0a1c3f` while preserving its source styles. The shared source-CSS insertion conflict retained both feature blocks; the compiled stylesheet was rebuilt. Finance data/ad-cost, completeness UI, Dashboard UI and Boost UI tests passed on the combined revision. The later 684px measurement was 2285px after source repair; changing source warnings can change this height. No Boost sender or shared service was restarted.
- A subsequent concurrent Boost recommendation update `b1b2e33` was integrated too. Its source styles merged unchanged, the generated CSS was rebuilt, and the expanded Boost browser suite plus Finance completeness UI passed again.

## Antislop delivery review

| Item | Result and evidence for the changed surfaces |
| --- | --- |
| R-01 | PASS: existing warm theme and orange action accent; no new gradient. |
| R-02 | PASS: concise Indonesian data labels and action copy. |
| R-03 | PASS: content-width reflow, 684px regression and narrow layout assertions. |
| R-04 | PASS: icons encode parcel, delivery, source status or disclosure behavior. |
| R-05 | PASS: balances, supporting figures, pending stages and compact store rows follow distinct tasks. |
| R-06 | PASS: existing typeface and tabular monetary values aid comparison. |
| R-07 | PASS: no ornamental background. |
| R-08 | PASS: the chevron indicates the actual disclosure state. |
| R-09 | PASS: warnings describe real missing coverage or source failures. |
| R-10 | PASS: no added glass effects. |
| R-11 | PASS: existing radii retained. |
| R-12 | PASS: no extra card shadows. |
| R-13 | PASS: no glow. |
| R-14 | PASS: primary balances dominate; compact rows defer to them. |
| R-15 | PASS: refresh, disclosure, status filters and HPP actions describe their actual effects. |
| R-16 | PASS: no promotional claims. |
| R-17 | PASS: source observations and test fixtures are distinguished. |
| R-18 | PASS: no testimonials. |
| R-19 | PASS: native disclosures and existing focus retention; no decorative motion. |
| R-20 | PASS: existing Shopdash shell, logos, typography and surfaces. |
| R-21 | PASS: both existing themes tested. |
| R-22 | PASS: no added illustration. |
| R-23 | PASS: actual existing shop logos and requested meaningful icons. |
| R-24 | PASS: drilldowns use the existing Finance route and selected shop/date scope. |
| R-25 | PASS: browser-resolved contrast measured in both themes. |
| R-26 | PASS: disclosures, selection, filtering, HPP and retry flows exercised. |
| R-27 | PASS: missing/partial/zero/loading/error states remain distinct. |
| R-28 | PASS: help explains actual finance definitions only. |
| R-29 | PASS: existing neutral surfaces, action accent and semantic warning. |
| R-30 | PASS: no unrelated product imitation. |
| R-31 | PASS: hierarchy, spacing, typography, icon and disclosure purposes recorded above. |
| R-32 | PASS: keyboard disclosure/selection, focus preservation and modal Escape tested. |
| R-33 | PASS: direct source patches; tracked CSS compiled with Tailwind. |
| R-34 | PASS: both themes and expanded components verified. |
| R-35 | PASS: local app, backend fixtures, actual reads and browser tests run. |
| R-36 | PASS: no fabricated complete history, profit, bank balance or instant-refresh claim. |
| R-37 | PASS: declared inherited direction at 1/2/1. |
| R-38 | PASS: unavailable data remains unavailable; test fixtures stay isolated. |
| Dials / design read | PASS: calm finance hierarchy and existing identity declared before the revised UI. |
| Focal point | PASS: Pending and Released lead the view. |
| Spacing | PASS: separates balances from period values; source detail is disclosed on demand. |
| Accent | PASS: existing orange identifies actions; no additional decorative accents. |
| Identity motif | PASS: monetary rows and actual shop branding remain specific to Shopdash. |
| C-1 | PASS: decisions follow the user's money questions and compactness feedback. |
| C-2 | PASS: controls have real behavior; source repair preserves financial amounts. |
| C-3 | PASS: no section added without supporting source data or user task. |
| C-4 | PASS: responsive themes, text reflow, keyboard and data failure cases exercised. |
| C-5 | PASS: verification separates API evidence, local fixtures and visual measurements. |

## Remaining source limits

An exact custom report period still requires actual stored daily coverage or a matching aggregate. Missing historical source dates are not fabricated. Current-day paid GMV follows Shopee's whole-hour report cutoff and the existing Finance refresh cadence. Overview/detail differences and unrecognized future status descriptions remain visible.
