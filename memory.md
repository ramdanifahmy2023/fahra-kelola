# Project memory

Last reviewed: 2026-09-29. This is durable project context, not a live status dashboard. Read together with [AGENTS.md](AGENTS.md). Update existing entries when decisions change rather than accumulating contradictory instructions.

## User preferences and standing decisions

- Communicate in Indonesian for this user. Explain the result, verification, and any actual blocker concisely.
- Complete authorized work; avoid repeated confirmation for routine reversible changes.
- UI work uses UI UX Pro Max and antislop during implementation and final review. The user has already selected this workflow.
- Keep the current Shopdash visual identity. The Products shop dropdown is the reference for shop selection throughout the panel.
- Shop lists open downward in one scrollable column, with the correct shop logo. Headers on Promotions, Ads, and Boost also display shop logos.
- Prefer shared components over page-specific copies. Preserve filters, independent selection state, keyboard access, and both themes.
- For Automation, prefer compact task-focused layouts with meaningful icons and shop logos. Keep lengthy help behind disclosures while leaving errors, unsaved state, and consequential warnings visible. The user requested a visual plan on 2026-09-29; proposed changes are not implemented yet.
- Every completed task is committed and pushed to GitHub after appropriate verification. Do not force-push or include unrelated user work.
- Extension releases stay downloadable as backups. Never overwrite or delete a published ZIP or its release metadata when updating; every release and changelog item must have a name. Use the release workflow in [extension maintenance](ops/sellerio-extension.md).
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
| Extension downloads | `/panel/extensions`; `ExtensionRelease.php` reads per-version JSON in `resources/extensions/releases/`; immutable ZIPs live in `public/downloads/extensions/` |
| Finance / HPP | `/panel/finance`; pending/latest overview, released overview or custom-date details with explicit reconciliation, multi-shop filters, dated per-variant cost and signed impact preview. See [finance contract](ops/finance.md). |
| Dashboard defaults and clarity | All shops, first day of the current month through today in WIB; presets roll forward while custom ranges remain fixed. Shared Finance monetary overview, shop-scoped operational data, separately labelled intraday activity, visible partial-data warnings and pending status breakdown. See [UI contract](ops/dashboard-finance-ux.md). |

## Decision history and evidence

| Date | Change | Evidence |
| --- | --- | --- |
| 2026-09-29 | Replaced floated Boost product images with responsive grid | Commit `a918634`; `tests/boost-ui.cjs` |
| 2026-09-29 | Unified shop pickers, added customer shop filtering and shop header logos | Commit `ca27ce6`; selector, branding, customer-filter tests |
| 2026-09-29 | Aligned Ads and Topup selectors; documented UI conventions | Commit `469266e`; 267 browser checks passed at that revision |
| 2026-09-29 | Added agent operating knowledge | This documentation set; reviewed against repository source |

Passing counts above are historical, not a promise about the current revision. Record new test results only after running them.

## Automation Engine foundation

- Visual follow-up: [Automation visual plan](ops/automation-visual-plan.md) audits the current page and proposes Aturan toko / Uji aturan / Koneksi AI tabs, compact editors, and meaningful icons. This is a plan only, not an implemented UI contract or an active sender.
- On 2026-09-29 the user initially requested an audit, then requested the foundation for rating automation. Users choose target stars and scope themselves; do not hard-code all stars or automatically exclude low ratings.
- `/panel/automation` stores independent per-shop target filters, persona, support policy, model ID, and actions/instructions for stars 1–5. Actions are AI draft, manual review, or skip. These are saved intentions, not an active worker.
- Provider selected by the user: 9Router, OpenAI-compatible. CRUD connections and synthetic model testing are implemented; the user's actual provider endpoint/credentials still need configuration and verification. No review submission has been performed.
- Sidebar: **AI Agent > Automation**, separate from **Manajemen > Toko / Ekstensi**. Page title: **Automation Engine**.
- The local rule preview evaluates filters and constructs messages without calling AI or Shopee. New-only scope cannot be resolved until a future activation timestamp exists. Saves use optimistic versions to reject stale concurrent edits.
- 9Router connections live in `ai_connections` with encrypted keys and optimistic versions. A shop selects `automation_profiles.connection_id`; its existing `config.model` overrides the connection default. Deletion is blocked while shops use a connection. Catalog and generation tests are separate and tied to connection versions.
- Master keyring defaults to ignored `storage/ai-connection-keys.json`, initialized explicitly with `php bin/ai-connection-key.php --init`. Preserve it across checkouts sharing a database. `AI_CONNECTION_KEY_FILE` overrides the path; `AI_CONNECTION_ALLOWED_ORIGINS` permits exact local/LAN origins. Legacy `NINE_ROUTER_*` settings are informational only, with no automatic fallback.
- Follow-up implementation on 2026-09-29: [9Router CRUD operations](ops/9router-connections.md), based on the [audit plan](ops/9router-connection-plan.md). CRUD manages Shopdash connection records, not the internal 9Router combo chain. All authenticated panel accounts currently manage shared connections; no tenant/RBAC isolation is claimed.
- See [foundation](ops/automation-foundation.md), [API capture audit](ops/rating-api-audit-20260929.md), and [future implementation proposal](ops/automation-engine-plan.md). Rating discovery, AI rating drafts, sending, queues, and schedules remain unimplemented. Provider transport currently serves catalog and synthetic connection tests only.
- XYZ Sniper MCP was used read-only to inspect project 1. Never persist its authentication token or captured credentials. Pagination beyond page 1 and backend write transport remain unverified.
- Next phase: verify 9Router capabilities and rating pagination/transport, then implement discovery and AI drafts before an explicitly enabled sender. Users configure tone, support policies, target dates, and per-star handling in the foundation page.

## Things that must be rechecked each session

- Live Chat task branch `audit/shopee-live-chat` (2026-09-29): ownership guards, queued details/backfill and protected send intents implemented/tested. A real PHP send was rejected with `90309999`; integrated replies are **not verified working**. Read [implementation and remaining transport blocker](ops/live-chat-implementation-20260929.md) before rollout. Do not enable paused schedules or deploy a second worker checkout automatically.

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
