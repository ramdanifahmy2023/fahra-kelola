# Saldo Penjual implementation, 30 September 2026

The shared Dashboard/Finance view now shows **Saldo Penjual** globally and per selected shop. It follows Shopee's exact **Saldo**, with the source contract and corrections preserved in the [approved audit](finance-wallet-plan-20260930.md). Saldo Aktif, blocked amounts, withdrawal eligibility and bank operations are excluded. A restriction never reduces the balance. Pending, released funds and wallet balances are distinct figures and are not added together.

## Source and synchronization

- `FinanceWalletApi` makes a TLS-verified GET to the audited wallet endpoint with provider 0 / bank account 0. It verifies remote shop identity before and after, and rejects HTTP/envelope/missing/malformed amount failures. The amount is integer rupiah with no income scaling. Only the balance, explicit boolean withdrawal-maintenance flag, and sanitized plain-text explanation are returned.
- `finance_wallet` is additive, keyed by local and remote shop IDs. `Finance` also checks that the saved cookie/source did not change during the read. No bank details, cookies or complete payloads are retained. A failure preserves the previous amount and successful timestamp, with a safe error message.
- Wallet refresh is independent of income pagination, under the existing per-shop Finance lock. It uses the Finance interval, checked before each income turn. Manual refresh is debounced for 60 seconds and queues a Finance job even when income has no new import. Queue deduplication and CSRF remain in place. No new worker or extension changes.
- `--wallet-only` allows a scoped initial fill under the same lock and interval, without traversing income. The initial fill succeeded for all seven stored shops at **01:17:26–01:17:30 WIB**, with zero recorded wallet failures. One shop had an explicit source restriction message; its balance was retained unchanged. No withdrawals were called. Exact amounts and shop-specific messages are deliberately omitted from this document.
- Existing Finance schedules remained enabled for seven shops at 600 seconds when checked. Background jobs can delay the actual source read. The installed worker runs from the main checkout, and picks up changes on its next process cycle; no shared service was restarted. The UI polls stored data every 30 seconds, or 5 seconds with pending work. The timestamp is the successful source read, not the browser poll time.

## UI decisions

Reading this as an existing multi-store operations dashboard for a shared shop team, in Shopdash's warm light/dark visual language: **ENERGY 1 / RHYTHM 2 / MOTION 1**. This retains the approved Finance direction. UI UX Pro Max's text-reflow guidance informed container-based sizing; antislop kept explanatory text in the existing disclosures.

- Color: existing neutral surfaces and orange action accent preserve the application identity; warnings use existing semantic colors and words.
- Layout: three monetary balances share a row only when the actual content width permits. At medium widths wallet has its own full row; small screens stack. Each store groups the three balances above the three period metrics.
- Typography: existing font and tabular numerals keep money comparable. Main amounts scale with their own card width, so a large amount does not break into a stray digit on desktop.
- Spacing: inherited surface padding separates filters, money and investigation; compact per-store metadata appears only when relevant. Seven-shop ledger measured 2438px high at 684px content after initial wallet fill, below the existing 2800px limit.
- Grouping: balance cards compare distinct sources, while store rows repeat the same six labels for comparison. They are not six separate decorative cards.
- Icon: the existing Material `wallet` glyph accompanies the text Saldo Penjual; shop logos and the existing fallback stay unchanged.
- Copy: wallet and Pending explicitly say latest position, independent of report dates. Missing values stay unavailable; verified zero stays Rp0. Partial totals name the available shop count. Failed/stale reads disclose that the last balance is retained.
- Restriction: a compact source-status note appears by the store balance. The main link opens the affected store disclosures and focuses the first summary. Reasons are escaped text; absent reasons are explicitly unavailable. No positive withdrawal claim is inferred from a false flag.

## Verification

- PHP syntax, JavaScript syntax, `git diff --check`, and `npm run build` passed.
- `php tests/finance.php`: temporary tables and isolated advisory locks, including the new wallet cases. Verified raw rupiah, irrelevant fields, zero/missing/invalid values, source errors, identity before/after, cookie changes during read, shop isolation, date independence, cadence, manual debounce, old data and error retention. Wallet publication precedes income completion; wallet failure does not block valid income publication.
- `php tests/dashboard.php` and `php tests/finance-ad-cost.php` passed.
- `node tests/finance-wallet-ui.cjs`: both routes; totals and selected stores; restricted balance unchanged; escaped/missing reason; missing/zero/failure data; timestamps; report date independence; loading/error/reset and recovery; keyboard disclosure/focus; 320/500/999/1600px; light/dark; 684px available content; long names, large amounts and 200% text reflow. Large fixture amounts stay on one line at the standard tested widths.
- Finance, breakdown, completeness and Dashboard browser regression suites passed. Their sessions now use `panel-test-session.cjs`, so saved workspace preferences belong to a disposable test identity. The breakdown fixture explicitly opens the summary tab because the application now remembers tabs. Business mutation endpoints remain mocked.
- Dashboard contrast assertions passed in both themes, at least 4.5:1 for normal text, warning and action colors reused by wallet. Screenshots of desktop/light, 320px/dark and 684px ledger were visually inspected. Screenshots remain ignored in `tmp/`, with no customer data committed.

## Final antislop gate, changed Finance surfaces

| Item | Result and evidence |
| --- | --- |
| R-01 | PASS: existing solid surfaces; no gradient/glow added. |
| R-02 | PASS: introduced Indonesian labels contain no em dash or filler headings. |
| R-03 | PASS: wallet/completeness assertions cover overflow, bounds and 200% reflow. |
| R-04 | PASS: wallet icon identifies the wallet source, alongside a text label. |
| R-05 | PASS: source balances, period figures and store ledger have distinct roles. |
| R-06 | PASS: existing typeface; tabular amounts and container-sized money aid reading. |
| R-07 | PASS: no background patterns introduced. |
| R-08 | PASS: no ornamental arrows added. |
| R-09 | PASS: status text reports observed coverage or source restrictions. |
| R-10 | PASS: no glass effects. |
| R-11 | PASS: inherited surface radii; no new capsule controls. |
| R-12 | PASS: flat data surfaces; no new shadows. |
| R-13 | PASS: no glow. |
| R-14 | PASS: equal source balances compare money; subordinate store figures stay compact. |
| R-15 | PASS: refresh, apply, retry and reason link describe their actions. |
| R-16 | PASS: factual operational copy, no promotional buzzwords. |
| R-17 | PASS: source contract verified by captures, UI and seven successful server reads. |
| R-18 | PASS: no testimonials. |
| R-19 | PASS: no decorative motion; disclosure/focus survives polling. |
| R-20 | PASS: Shopdash typography, palette, shop logos and controls retained. |
| R-21 | PASS: theme preference respected; both themes exercised. |
| R-22 | PASS: no invented illustration. |
| R-23 | PASS: existing logos and user-authorized icons; no invented identity assets. |
| R-24 | PASS: reason link opens actual store disclosures. |
| R-25 | PASS: reused text/warning/action palette measured at least 4.5:1 in Dashboard tests. |
| R-26 | PASS: filters, refresh/retry, reason link and disclosures exercised. |
| R-27 | PASS: loading, missing, zero, partial, old-data, error and recovery tested. |
| R-28 | PASS: disclosures explain real data/status; no generic FAQ added. |
| R-29 | PASS: inherited neutrals, orange action and semantic warnings only. |
| R-30 | PASS: preserves Shopdash rather than introducing another product's template. |
| R-31 | PASS: color, layout, type, spacing, grouping and icon reasons documented above. |
| R-32 | PASS: Enter opens reasons, focuses native summary; existing visible focus retained. |
| R-33 | PASS: source authored with patches; Tailwind generated the stylesheet. |
| R-34 | PASS: light/dark responsive assertions and screenshot inspection passed. |
| R-35 | PASS: served locally, built and tested; live upstream fill succeeded separately. |
| R-36 | PASS: no guarantee of withdrawal, freshness or historical wallet balance. |
| R-37 | PASS: approved existing direction explicitly retained at 1/2/1. |
| R-38 | PASS: live values originate from Shopee; synthetic edge cases are test-only. |
| Dials | PASS: calm money-first layout, varied data hierarchy, no decorative animation. |
| Focal point | PASS: source amounts dominate each balance card; details remain subordinate. |
| Whitespace | PASS: padding and separators distinguish source comparisons and store rows. |
| Accent | PASS: inherited orange identifies the source refresh action. |
| Motif | PASS: repeated shop identity rows and tabular currency fit the existing panel. |
| Design read | PASS: approved Finance direction applied; inherited palette and density retained. |
| C-1 | PASS: each new element serves source balance, scope or explanation. |
| C-2 | PASS: reason link and recovery flows have exercised behavior. |
| C-3 | PASS: wallet is the requested source figure; no filler section added. |
| C-4 | PASS: themes, narrow content, keyboard, large values and failure states tested. |
| C-5 | PASS: source limits and synthetic test data are explicitly distinguished. |
