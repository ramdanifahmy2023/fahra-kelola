# Testing and verification

Reviewed 2026-09-29. Run from repository root. Choose checks by changed behavior; documentation-only edits need link/path review and `git diff --check`, not business operations or a full application test run.

## Test selection

| Changed area | Relevant checks |
| --- | --- |
| Shared shop selectors | `node tests/shop-picker-ui.cjs`, `node tests/products-ui.cjs`, `node tests/ads-ui.cjs` |
| Logo headers / Report selector | `node tests/shop-branding-ui.cjs` |
| Boost UI / selection | `node tests/boost-ui.cjs` |
| Boost product eligibility | `php tests/boost-products.php` |
| Customer shop scoping | `php tests/customer-shop-filter.php` |
| Queue recovery | `php tests/sync-recovery.php` |
| Live Chat | `php tests/chat.php`, `node tests/chat-ui.cjs` (`CHAT_TEST_URL`, default 8147); temporary tables and mocked chat mutations. Live transport limits: [implementation](live-chat-implementation-20260929.md). |
| Order/product/package sync | `php tests/order-sync.php`, `php tests/product-sync.php`, `php tests/package-sync.php` |
| Ads metric mapping | `php tests/ads-performance.php` |
| Ads campaign traversal | `php tests/ads-campaign-reports.php` |
| Browser report import | `php tests/ads-browser-import.php` |
| Automation profiles and rules | `php tests/automation-profile.php`, `node tests/automation-ui.cjs` |
| Finance / HPP / multi-shop picker | `php tests/finance.php`, `node tests/finance-ui.cjs` (default 8133; override `FINANCE_TEST_URL`; browser saves/sync mocked, DB fixtures use temporary tables and isolated locks) |
| Saldo Penjual / wallet | `php tests/finance.php` includes wallet parser/transport and temporary snapshot cases; `node tests/finance-wallet-ui.cjs` checks both pages, selected shops, period independence, null/zero/failure/restriction, responsive themes and keyboard. Uses isolated panel test sessions and synthetic wallet values; no withdrawal. |
| Dashboard / Finance clarity and defaults | `php tests/dashboard.php`, `node tests/dashboard-ui.cjs` (same `FINANCE_TEST_URL`; temporary DB fixtures, mocked realtime/write responses and browser clock for rollover). Includes scope, partial/null/zero, request races, focus, all four widths and both themes. |
| 9Router connections | `php tests/ai-connections.php`, `php tests/ai-http.php`, `node tests/ai-connections-ui.cjs`; temporary tables, local HTTP fixture and mocked browser mutations |
| Extension catalog and immutable archives | `php tests/extension-releases.php` (isolated temporary release roots; also verifies published ZIP checksums) |
| Extension page, downloads, and mobile navbar | `node tests/extensions-ui.cjs` (local authenticated page, mocked background endpoints, download checksums and responsive checks) |

Also run syntax checks on changed PHP/JS files, `npm run build` for CSS/template class changes, and `git diff --check`. Do not assume an `npm test` command exists; package scripts currently provide CSS dev/build.

## Prerequisites and effects

- Most integration tests require an initialized local database with schema. Queue/domain fixture tests use connection-local temporary tables or stubs; inspect the test before running it against a sensitive environment, especially after modifying fixtures.
- `ads-performance.php` and `ads-campaign-reports.php` use fixtures/stubs without requiring a live Shopee request for their normal checks.
- Browser tests require a running local app, a local account, Playwright, and a compatible installed Chromium. Tests create a temporary PHP session from a local account and clean it up; this is authorized local test setup, not credentials to expose or transfer to another host.
- Some browser tests use live local read endpoints; Boost mutation requests are mocked. Do not remove those mocks or click live business-action buttons during visual verification.
- Some suites require at least two connected shops. Missing data/environment is not a product failure; report the exact limitation and use isolated fixtures where suitable.
- `PLAYWRIGHT_MODULE` can point to an existing installed module. Per-suite URL overrides differ; inspect the file. Do not assume all suites support one shared URL variable.
- Screenshots/artifacts belong under ignored `tmp/`. They may contain real shop/customer information; do not commit them.

## UI verification

Automation browser tests use `AUTOMATION_TEST_URL` (default `http://127.0.0.1:8131`), mock saves, and exercise the real read-only preview. The PHP suite uses connection-local temporary profile tables. Neither test calls AI or sends Shopee replies. See [foundation verification](automation-foundation.md).

1. Check loading, empty, failure, disabled, and selected states relevant to the change.
2. Check 320px, 500px, 999px, and 1600px where layout changes apply, both themes, and long names. Open menus and scroll to the final option; a closed menu screenshot is insufficient.
3. Verify downward placement, vertical-only option arrangement, viewport bounds, and reserved image dimensions. Test missing/broken images.
4. Exercise keyboard focus/selection and Escape for custom dynamic selectors. Verify the backing select and visible trigger agree after reloads and option replacement.
5. Assert actual data/requests change when a shop is selected. Check preservation of dates, period/channel, page-size, and reset of page index as appropriate. Ads and Topup must stay independent.
6. Inspect screenshots, not just numeric assertions. Disable animations during screenshot capture to avoid mistaking transitional sidebar frames for settled layouts.
7. Confirm no JavaScript page errors. Scope unrelated pre-existing issues accurately rather than claiming the entire app is flawless.

## Honest results

Report which commands passed and any test not run due to a concrete prerequisite. A source syntax pass is not a browser test; a local mock test is not Shopee reconciliation; a successful Git push is not a deployment test. Do not reuse historical passing totals as a current result.

Automation visual regression coverage also checks task tabs, separate draft retention, unsaved indicators, hidden-field validation focus, closed shop-picker hit testing, and the connection action menu. See [visual verification](automation-visual-ui.md).
