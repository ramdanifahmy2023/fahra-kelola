# Dashboard and Finance readability audit

Date: 2026-10-14. User requested a simpler, fresh, professional presentation inspired by the supplied BantuSeller Google Apps Script application. Reference inspected through the signed-in desktop UI: Dashboard and Katalog Produk. Credentials and business records are intentionally excluded. Reference mobile behavior, calculation accuracy, and other reference modules were not audited.

## Findings and application

| Priority | Observation | Applied change |
| --- | --- | --- |
| High | Shopdash placed the entire per-shop ledger ahead of today's activity. More shops pushed the chart and daily sales farther down. | Actual DOM reading order is now funds, daily activity/chart, pending breakdown, store ledger, operations. Keyboard order follows the same sequence. |
| High | Source explanations and repeated dates competed with amounts. The reference used short metric labels and compact top-level filters. | Keep amounts, scope, unknown/partial/stale warnings visible. Put source timestamps and definitions in native disclosures. Remove the repeated period paragraph from each supporting metric; the applied period remains above the group. |
| Medium | A long dashboard had no short route to its major sections. | Add working anchor links for funds, today's activity, stores, and operations. Sections reserve space below panel chrome when scrolled to. |
| Medium | The main balances shared one large container while daily metrics had little visual grouping. | Separate balance surfaces; subtly tint the actual wallet balance. Give daily metrics aligned separators and reflowing two/three-column tracks. |
| Medium | Pending categories could form five columns with one orphaned category on a wide screen. | Use three columns when the content area has enough room. Preserve single-column reflow on narrow screens. |

The reference's useful patterns are concise labels, clear grouping, restrained table framing, and actions grouped near their data. Its mixed English accounting terms and repeated small uppercase labels were not adopted. Shopdash does not gain a profit or ROAS claim without the corresponding source contract. The reference displayed zero values during this audit; that observation is not evidence about its financial accuracy.

## Design decisions

Design read: operational dashboard for Indonesian shop owners, calm and readable within the existing warm Shopdash identity. ENERGY 1 / RHYTHM 2 / MOTION 1.

- Color: retain the warm neutrals and orange actions; a 7% orange tint distinguishes the actual wallet balance without implying that all balances can be added.
- Typography: retain existing type and tabular numerals for familiarity and amount comparison; increase supporting amounts instead of adding decorative headings.
- Layout: activity precedes the unbounded shop list because daily activity must remain reachable as shops grow.
- Spacing: 12px between balances separates distinct financial meanings; grouped daily metrics use compact internal spacing for quick comparison.
- Surfaces: balances are separate because they describe different stages of funds; six supporting activity values share one frame because they describe one daily report.
- Icons: retain existing wallet, receipt, shipping, shop and chart meanings. No new icon family, imagery, gradients, animation, or decorative badges.
- Disclosure: native `details` provides keyboard/touch access. Critical warnings, restrictions, missing values, and the warning against summing balances remain visible.

UI UX Pro Max's initial design-system search returned a marketing showcase, unsuitable for an internal dashboard. A narrower UX search yielded heading hierarchy guidance. The concrete layout decisions follow the observed reference and existing Shopdash contracts, not the unrelated generated marketing recommendation. Antislop was applied throughout per the standing user instruction.

## Verification and limits

`tests/finance-visual-ui.cjs` renders the real PHP views and runs the real Finance, Dashboard, logo, and shop-selector JavaScript against intercepted synthetic responses. It uses no database, real session, external image request, or upstream business mutation. The fixture is explicitly labelled as synthetic. Screenshots remain ignored under `tmp/finance-visual-ui/`.

The existing `tests/dashboard-ui.cjs` cannot reach its loaded state using the current synthetic panel session: tenant scoping correctly returns no shops for that synthetic account. Do not relax ownership checks or assign real stores to a fixture account to make that suite pass. This is a pre-existing test setup limitation after the tenant-isolation changes. The new visual suite covers the changed presentation independently; it does not replace live integration or tenant-isolation verification.

No Finance calculation, HPP import/write contract, worker schedule, shop ownership, authentication, or data source was changed. This implementation covers Dashboard and shared Finance presentation; Products, Orders, Reports, Ads and other modules retain their existing presentation. Extending the same treatment should start with each module's real task and existing tests, not a blanket stylesheet override.

Completed checks:

- `npm run build`, PHP syntax checks, JavaScript syntax checks and `git diff --check`: PASS.
- `php tests/dashboard.php`: PASS for date/shop scope, WIB, missing values and hourly coverage.
- `php tests/finance.php`: PASS for wallet, imports, identity, HPP history, impact and concurrent edits using temporary fixtures.
- `php tests/frontend-comfort-render.php`: PASS, 15 existing database-free checks.
- `node tests/finance-visual-ui.cjs`: PASS, 16 page/width/theme combinations, keyboard disclosures and picker, selected-shop request and values, null versus zero, failure/retry, chart selection, anchor destinations, Finance tabs, HPP dialog Escape, 200% text reflow and measured text contrast of at least 4.5:1 on changed balance/detail/action surfaces.
- Desktop light and narrow dark Dashboard screenshots, plus 500px light Finance, were visually reviewed. This is synthetic-data visual verification, not a claim of fresh live store data or a full-panel redesign.

## Antislop delivery gate, changed surfaces

| Gate | Result and evidence |
| --- | --- |
| Hard gate | PASS: real view/scripts built and tested; no introduced fictional business values, dead anchors or horizontal page overflow. Keyboard, missing/error states and text contrast are asserted. Test values are explicitly labelled and isolated. |
| Purpose gate | PASS: balance grouping, wallet tint, typography, spacing and disclosure purposes are stated above. Existing meaningful icons retained; no new decorative effects. |
| Liveliness | PASS: ENERGY 1 / RHYTHM 2 / MOTION 1 reflected in calm surfaces, dominant monetary values, varied activity/ledger sections and no new animation. Orange remains the deliberate action/chart accent. |
| Craftsmanship and consistency | PASS: DOM and visual order agree; actual data contracts, scope, theme choices, warnings and real destinations are retained. Responsive screenshots and interaction tests cover the changed content. |

## Dashboard-only follow-up: critical audit and review branch

Date: 2026-10-14. The user judged the interface too text-heavy and dated, then explicitly selected Dashboard as the first page to improve. Other pages wait for their visual feedback. Work started at `1ba78bc` in `Worktrees/shopdash-ui-ux-review-20261014`, branch `codex/ui-ux-review-20261014`, and incorporated the subsequent committed main revision `19710b2` before delivery. Main integration requires explicit user approval.

This audit used current source and rendered baseline screenshots with labelled synthetic data. The native browser's public URL returned a connection error during the live inspection; no live-data or production-verification claim is made. The tests subsequently rendered the actual panel header, navigation, Dashboard templates and scripts on an isolated loopback preview.

| Priority | Baseline finding | Dashboard change |
| --- | --- | --- |
| High | Six financial figures, six daily activity figures, explanations and shop ledgers compete for attention. The chart comes after the entire financial overview. | Put today's confirmed sales first, with orders, buyers and visitors supporting it. Put traffic detail behind a disclosure. Keep the financial report as its own labelled section. |
| High | The strongest orange action requests a Finance sync although the screen is a general Dashboard. | Make applying the displayed scope the primary action. Keep the separately labelled activity and Finance refresh controls near their own data. |
| High | Stock and connection issues appear after lengthy money details. | A compact condition row above sales links to the actual stock and connection sections. A failed refresh replaces this row with an explicit failure state. |
| High | Repeated success labels and source prose reduce the distinction between a useful figure and its explanation. | Omit redundant successful-coverage badges visually on Dashboard. Keep partial warnings visible. Put source explanations in keyboard-accessible disclosures, preserving open state and focus during polling. |
| Medium | The financial report period can be mistaken for today's sales period. | Label the filter as the funds-report period. The daily view explicitly states its date, shops and independence from that period. |
| Medium | The chart has low prominence, while a missing hour leaves the same empty space as zero sales. | Place the chart before the product list in DOM order, make it wider on desktop, and mark missing hours with dashes. Keep exact values, coverage and the numeric table accessible by keyboard/touch. |
| Medium | Pending categories add a permanent block of labels, definitions and repeated links. | Make the category block expandable. Unknown classification and overview/detail differences remain visible in the closed disclosure summary. Existing links automatically reveal the block. |
| Medium | Mobile inherits lengthy navigation and auxiliary controls. | Reflow section navigation into one row, use a labelled icon control for refresh on the smallest width, and put optional shop actions in their own disclosure. The desktop options popover closes with Escape and restores focus. |
| Engineering | Dashboard's layout is embedded in the Finance view and a large shared stylesheet. | Introduce a dedicated Dashboard view and scoped `resources/css/dashboard.css`, imported by the normal build. Leave financial calculations, authentication, ownership and business endpoints intact. |

### Design decisions

Reading this as a daily operations dashboard for Indonesian shop owners, using Shopdash's existing warm surfaces, orange accent and system typography. ENERGY 1 / RHYTHM 2 / MOTION 1.

- Color: retain the product's palette; darken the chart accent in light mode for legible bars. Reserve strong orange for the main filter action, alongside the existing active navigation.
- Layout: put the daily sales decision first, then financial context and operational details. A chart and ranked product list answer different questions and therefore have different widths.
- Typography: keep the existing family and tabular numerals. Make daily sales the focal number; source text has lower emphasis without reducing contrast below the measured threshold.
- Spacing: compact the desktop toolbar into one row; use space to separate source scopes and reserve larger spacing for section boundaries.
- Surfaces: group the daily report on one surface. Separate the three balances because they represent different money stages and must not be summed.
- Icons: reuse existing material symbols for refresh, stores, financial stages and status. No new logos, avatars, decorative illustration, invented trend percentage or ranking performance claim.
- Disclosure: reduce permanent explanation text while preserving uncertainty in view. Refresh never silently closes a source disclosure or changes its keyboard focus.

UI UX Pro Max's design-system query returned a marketing showcase, so it was rejected. The narrower style query returned `Data-Dense Dashboard`; only its data hierarchy, state coverage and readability guidance was adopted. Its maximum-density recommendation and the alternate bento layout did not fit the user's request. The disclosure searches did not return a relevant verified match, and the HTML/Tailwind stack search plus its retry returned no results. Responsive implementation therefore follows the inspected project and general accessibility guidance, not an asserted database match.

The reflow check uses a 320 CSS-pixel width and also checks enlarged text; this is scoped UI verification, not a full WCAG certification. See [W3C reflow guidance](https://www.w3.org/WAI/WCAG22/Understanding/reflow.html). Text and chart colors were measured against their actual theme backgrounds using the luminance method described in [W3C contrast guidance](https://www.w3.org/WAI/WCAG22/Understanding/contrast-minimum.html).

### Preview and verification

From this worktree, run `php -S 127.0.0.1:59171 -t public tests/fixtures/dashboard-preview.php`. Open `http://127.0.0.1:59171/panel`. This router uses labelled examples and the real panel shell. It never loads the application bootstrap, database or Shopee transport. Other-page links explain the Dashboard-only preview scope. Do not expose this fixture server as production.

Checks completed for this review:

- `npm run build`, changed PHP/JavaScript syntax checks and `git diff --check`: PASS.
- `tests/finance-visual-ui.cjs`: PASS, 16 page/width/theme combinations using real Dashboard and Finance views. Covers shop choice, null/zero, retry, refresh, existing Finance tabs/HPP dialog and focus preservation. Optional Dashboard shop actions are opened before use.
- `tests/dashboard-review-ui.cjs`: PASS, actual panel shell at 320/500/999/1600px in both themes. Covers 44px control heights, theme selection, mobile menu, bounded picker/options, Escape, polling focus, pending anchor disclosure, missing/zero/partial/empty/failed data, recovery, shop/date scope, mock sync and 200% text reflow.
- Measured sampled text contrast: at least 6.39:1 light and 6.62:1 dark. Chart bars: 4.39:1 light and 8.40:1 dark. Test output records the individual selectors and ratios under ignored `tmp/dashboard-review/checks.json`.
- No JavaScript page errors or external requests in the full-shell suite. All tested mutations were intercepted. No business write, worker run or database migration was performed.
- Desktop light/dark and 320px dark screenshots were visually inspected. Baseline screenshots are in ignored `tmp/dashboard-before/`; updated screenshots are in `tmp/dashboard-review/` and `tmp/finance-visual-ui/`.

### Integration boundary and remaining recommendations

The user explicitly authorized merging to main on 2026-10-14. The main checkout still contained twelve unrelated, uncommitted Finance/HPP paths. Those changes were snapshotted and combined with this branch in the separate `shopdash-dashboard-merge-check-20261014` worktree before touching main. They remain separate from the Dashboard commit.

The newer Finance script requires the P&L controls on Dashboard. Dashboard therefore accepts the Finance-owned, server-rendered `$financeProfitPanel` fragment. In the combined working tree, Finance renders that fragment before its Dashboard early return and reuses it at the existing Finance panel location. This keeps one P&L markup contract, its basis control and retry behavior, without duplicating financial calculations. Dashboard shows the matching report/HPP links only when the fragment is supplied. Preserve this initialization when committing the separate Finance feature. The committed baseline without that feature still works and does not show a nonfunctional P&L panel.

Combined verification: CSS regenerated from both source changes; `tests/finance-visual-ui.cjs` passed all sixteen width/theme views, including the additional Finance profit tab and SKU dialog. `tests/dashboard-review-ui.cjs` passed all eight shell views with P&L present, cash/accrual selection, explicit unknown versus zero values, retry after failure, and shop/date propagation into P&L and HPP links. The combined screenshots were inspected; P&L shop rows use two readable columns on wide Dashboard and one on small screens. All endpoint data and actions were mocked. This verifies UI integration, not the separate feature's backend formulas, SKU persistence, or Shopee reconciliation.

After the user accepts the Dashboard direction, the next UI priority is to reuse the hierarchy on data tables: a concise summary, clear status and next action, with long definitions outside the default view. Audit sidebar grouping and small section labels separately; the shared navigation is preserved in this phase. Do not restyle other pages as part of this first Dashboard review.

### Antislop gate for the Dashboard revision

| Gate | Result and evidence |
| --- | --- |
| Hard gate | PASS within the tested Dashboard scope: real controls and destinations, no introduced fabricated business claims, labelled fixture data, responsive themes, measured contrast, error/empty states and keyboard tests. |
| Purpose gate | PASS: color, type, hierarchy, spacing, chart, symbols and disclosures each have a stated content purpose above. The options-menu shadow identifies an overlay. |
| Liveliness | PASS: the primary sales amount is the focal point; the chart uses the Shopdash accent; different source scopes have different section compositions; no decorative animation. |
| Craft and consistency | PASS for the committed view and the combined Finance/P&L UI: DOM reading order, source semantics, filters, uncertainty, focus and full-shell interactions are verified. The unrelated backend feature remains outside this UI verification. |
