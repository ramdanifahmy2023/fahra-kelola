# Project memory

Last reviewed: 2026-09-29. This is durable project context, not a live status dashboard. Read together with [AGENTS.md](AGENTS.md). Update existing entries when decisions change rather than accumulating contradictory instructions.

## User preferences and standing decisions

- Communicate in Indonesian for this user. Explain the result, verification, and any actual blocker concisely.
- Complete authorized work; avoid repeated confirmation for routine reversible changes.
- UI work uses UI UX Pro Max and antislop during implementation and final review. The user has already selected this workflow.
- Keep the current Shopdash visual identity. The Products shop dropdown is the reference for shop selection throughout the panel.
- Shop lists open downward in one scrollable column, with the correct shop logo. Headers on Promotions, Ads, and Boost also display shop logos.
- Prefer shared components over page-specific copies. Preserve filters, independent selection state, keyboard access, and both themes.
- Every completed task is committed and pushed to GitHub after appropriate verification. Do not force-push or include unrelated user work.
- Keep maintenance knowledge in the repository so subsequent Codex/AI sessions can explicitly read it. `AGENTS.md` is the entrypoint; `tools.md` and this file are intentional root documentation exceptions to the normal `ops/` placement rule.

## Established implementation decisions

| Area | Decision / source |
| --- | --- |
| Server shop selector | `app/views/panel/templates/shop-picker.php`, shared `.shop-picker` CSS |
| Dynamic shop selector | `public/assets/js/shop-select.js` enhances a native select, keeping its value/change contract while rendering a branded menu |
| Ads selectors | `ads-shop` and `ads-topup-shop` are independent backing selects; visible trigger IDs end in `-trigger` |
| Reports selector | Shared markup; hidden `report-shop` value drives AJAX summary/compare filtering |
| Logo identity | `shop-logos.php` serializes only local ID/logo mappings; `shop-logos.js` renders actual logo or storefront fallback |
| Customer scoping | Optional local shop ID; membership from `customer_shops` or matching orders; counts/history scoped to the selected shop; zero means all shops |
| Orders navigation | Real navigation loads the selected shop, preserves date/limit filters, and resets pagination |
| Boost product layout | Separate checkbox, 48px thumbnail, and title grid tracks; metadata reflows by container width |
| Asset delivery | CSS compiled from source and tracked; filemtime-based URLs prevent ordinary stale asset reuse |
| Background sync | Durable scheduler/worker queue; ordinary data pages read local data, not a new full upstream sync |
| Runtime settings | Database schedule rows and queue state are authoritative; old incident notes are historical evidence |

## Decision history and evidence

| Date | Change | Evidence |
| --- | --- | --- |
| 2026-09-29 | Replaced floated Boost product images with responsive grid | Commit `a918634`; `tests/boost-ui.cjs` |
| 2026-09-29 | Unified shop pickers, added customer shop filtering and shop header logos | Commit `ca27ce6`; selector, branding, customer-filter tests |
| 2026-09-29 | Aligned Ads and Topup selectors; documented UI conventions | Commit `469266e`; 267 browser checks passed at that revision |
| 2026-09-29 | Added agent operating knowledge | This documentation set; reviewed against repository source |

Passing counts above are historical, not a promise about the current revision. Record new test results only after running them.

## Planned work: Automation Engine

- On 2026-09-29 the user requested audit/planning only for a new Automation Engine page, initially for rating replies.
- Confirmed preference: AI automatically replies under rules for all star ratings, with different rules per star. This is a future feature decision, not permission to send replies during the audit.
- Provider selected by the user: 9Router, OpenAI-compatible. Base URL, model, credentials, capabilities, and budget still need verification/configuration. No provider connection or review submission has been performed.
- Proposed sidebar grouping: **AI Agent > Automation**, separate from **Manajemen > Toko / Ekstensi**. Keep page title **Automation Engine** and rating replies as its first module. Planning only; do not create placeholder navigation before implementing the page.
- See [API capture audit](ops/rating-api-audit-20260929.md) and [implementation proposal](ops/automation-engine-plan.md). The proposed worker, tables, UI, provider, and schedules are not implemented.
- XYZ Sniper MCP was used read-only to inspect project 1. Never persist its authentication token or captured credentials. Pagination beyond page 1 and backend write transport remain unverified.
- Next discussion: 9Router connection/model/budget, tone and support policies, backlog start date, operating limits, and content exceptions.

## Things that must be rechecked each session

- Working tree changes, intended branch, remote, available browser connection, and local service availability.
- Current queue backlog, detail completion rate, failed tasks, schedule enabled flags, and upstream access state.
- Ads/chat access restrictions described in older notes may have changed. Do not reapply a historical pause automatically.
- The public URL may serve local checkout changes, but deployment/tunnel configuration must be verified before making a deployment claim.
- Number of connected shops, active account, product/order counts, and credentials are runtime data, not constants.

## Known traps

- An unchanged old DOM snippet can come from a stale tab or an old feedback capture. Compare served assets and current DOM before rewriting a working fix.
- A menu with constrained height and flex wrapping can spill into sideways columns. Keep `flex-wrap: nowrap` and test the last option is reachable.
- Changing the displayed shop label or browser URL alone does not reload shop data.
- `sync_jobs.total_detail` / `processed_detail` can be zero for jobs whose detail rows are progressing. Inspect `sync_job_orders` before computing percentages.
- Mixing all historical queue rows overcounts repeated jobs. Scope progress to the intended batch/latest full job per shop and state the denominator.
- Schema-only exports are safe to review but not safe to import over an existing installation: the base schema includes `DROP TABLE`.
- Do not blindly stage newly appearing folders. An untracked file is not authorization to publish it.

## Handoff format for unfinished work

Use an ignored `tmp/` note for transient/private details. Persist a sanitized `ops/` note only when it has continuing value. Include: objective, branch/base commit, changed files, completed behavior, tests actually run, remaining work, current blocker, and exact next verification. Identify assumptions separately from verified facts. Never store cookies, session tokens, passwords, or customer payloads.
