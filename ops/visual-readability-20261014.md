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
