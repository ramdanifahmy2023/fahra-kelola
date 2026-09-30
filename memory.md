# Project memory

Last reviewed: 2026-09-30 (Boost worker handoff). This is durable project context, not a live status dashboard. Read together with [AGENTS.md](AGENTS.md). Update existing entries when decisions change rather than accumulating contradictory instructions.

## User preferences and standing decisions

- Communicate in Indonesian for this user. Explain the result, verification, and any actual blocker concisely.
- Complete authorized work; avoid repeated confirmation for routine reversible changes.
- UI work uses UI UX Pro Max and antislop during implementation and final review. The user has already selected this workflow.
- Keep the current Shopdash visual identity. The Products shop dropdown is the reference for shop selection throughout the panel.
- Shop lists open downward in one scrollable column, with the correct shop logo. Headers on Promotions, Ads, and Boost also display shop logos.
- Prefer shared components over page-specific copies. Preserve filters, independent selection state, keyboard access, and both themes.
- For Automation, prefer compact task-focused layouts with meaningful icons and shop logos. Keep lengthy help behind disclosures while leaving errors, unsaved state, and consequential warnings visible. The requested compact Automation layout was implemented on 2026-09-29; see the visual implementation note below.
- For Boost, the user requested a modern operational UI with recognizable status colors, action icons, shop logos and product thumbnails. Pair every color with a label/icon, preserve the Shopdash theme, and keep critical status visible. See [Boost visual implementation](ops/boost-visual-ui.md).
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
| Boost automation | Fixed selected products per shop; independent CLI service `com.fahra.shopdash.boost`. See [Boost operations](ops/boost-automation.md) and [commands](tools.md#boost-worker-commands). |
| Asset delivery | CSS compiled from source and tracked; filemtime-based URLs prevent ordinary stale asset reuse |
| Background sync | Durable scheduler/worker queue; ordinary data pages read local data, not a new full upstream sync |
| Runtime settings | Database schedule rows and queue state are authoritative; old incident notes are historical evidence |
| Extension downloads | `/panel/extensions`; `ExtensionRelease.php` reads per-version JSON in `resources/extensions/releases/`; immutable ZIPs live in `public/downloads/extensions/` |
| Finance / HPP | `/panel/finance`; pending/latest overview, released overview or custom-date details with explicit reconciliation, multi-shop filters, dated per-variant cost and signed impact preview. See [finance contract](ops/finance.md). |
| Saldo Penjual | Exact Shopee **Saldo**, `wallet_available_balance` in raw rupiah, latest position per cookie-verified shop. Independent of report dates. Ignore Saldo Aktif; no held-money arithmetic or withdrawal eligibility claim. Explicit source restrictions do not reduce totals. Same Finance lock/cadence; [implementation and verification](ops/finance-wallet-implementation-20260930.md). |
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

- Visual interface: Aturan toko / Uji aturan / Koneksi AI tabs, compact persona/rule editors, meaningful icons, draft retention and indicators, and hidden-field validation reveal. See [implementation and verification](ops/automation-visual-ui.md), based on the [visual plan](ops/automation-visual-plan.md). Saving settings still does not activate a sender.
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

## Boost automation and worker

- The user requested and enabled real product Boost repetition. The implementation is separate from the AI/rating foundation described above; the AI sender's implementation status must not be used to infer Boost status.
- `/panel/boost` saves up to five fixed products per shop. **Pilih rekomendasi** selects the highest synced sales among active, in-stock products with positive sales in that shop; the user reviews and saves. Recommendations never save, enable or send by themselves. Selected products are not automatically replaced when temporarily unavailable.
- The official runtime is the main `shopdash` checkout. macOS LaunchAgent `com.fahra.shopdash.boost` runs `bin/boost-worker.php --once --limit=5` every 60 seconds, including when the browser is closed. Avoid another runner from a test worktree and leave AI/Finance/sync services untouched. The host must remain awake and the service loaded; real sending also needs network access.
- Sending requires both the server switch `BOOST_SEND_ENABLED=1` and an enabled shop profile. Process environment takes precedence over the local config file, including explicit `0`. Preserve the user's live activation choices; default-off is for new installations. Inspect current runtime before changing either switch.
- Database shop locks serialize manual/automatic sending. Cooldown is conservatively 255 minutes per product; local capacity is an estimate. Unknown POST outcomes block that shop until inspected and reconciled, without automatic resend. Do not delete ledger/history or shorten cooldown to bypass a wait.
- Evidence at **2026-09-30 00:07 WIB**: sender enabled, worker heartbeat fresh (`alive=1`), service interval 60 seconds and last exit code 0; seven profiles enabled. The database recorded five automatic runs with 23 accepted items, zero failed and zero unknown. This is dated evidence, not a perpetual status. Accepted means the API accepted the request, not proof of ranking/sales improvement; a complete repeat after cooldown was not yet observed in this check.
- Read [Boost operations and production evidence](ops/boost-automation.md) and [Boost command effects](tools.md#boost-worker-commands) before maintenance. Recheck `boost_worker_health`, `boost_profiles`, recent `product_boost_runs/items`, the service definition and server sender setting each session. Read-only inspection is sufficient for routine health checks; kickstart/`--once` can send real requests.

## Notifications

- Phase one is implemented: stock lifecycle, shop/module connection issues, stalled orders/products sync, and verified shipping deadlines. Read state is per account/revision; read never means resolved. See [notification operations and verification](ops/notifications.md) for thresholds, schema, API and source limitations.
- Operational evaluation is driven by authenticated panel polling (30-second shared throttle). Shipping requires recent detail and an explicit unshipped status; stale data cannot prove recovery. Read-only Shopee chat notifications use the existing chat sync worker and the same center/badge/group/receipt/snooze system. Sound requires browser activation and uses a chat-only event cursor plus a same-origin tab lock. See [read-only chat contract and rollout](ops/chat-readonly-notifications-20260930.md). Ad balance, returns and rating/AI-worker detectors remain future work from the [priority audit](ops/notification-priority-audit.md).

## Things that must be rechecked each session

- Chat task branch `audit/shopee-live-chat` (2026-09-30): user changed the product to **Chat Shopee**, read-only history plus shared bell/badge/sound; replies must use Shopee. HTTP send/activation routes are disabled without an upstream attempt. Historical PHP rejection `90309999` is not claimed fixed. Probe verified reads for local shops 1/2/5; 3/4/6/7 remain forbidden. The new request authorizes periodic read notifications for verified shops; preserve other paused schedules. Recheck [rollout evidence and limits](ops/chat-readonly-notifications-20260930.md), never add a worker from a second checkout, and do not infer seven-shop support.

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

## Workspace comfort

- Per-account filters/shop/position, navbar local search, mobile order cards/details, honest sync stages and grouped revision-specific reminders are implemented. Read [workspace comfort operations](ops/workspace-comfort.md) before extending these flows.
- Explicit destination filters override preferences. Preserve timestamp-based stale-tab rejection, current-job sync denominators and per-account/revision snoozes. Do not infer new tenant isolation from preference storage.
- Closed shared shop menus must not intercept pointer input. Browser tests use synthetic sessions and mocked operational writes; never test preference saves with a real account session.

## Frontend comfort follow-up

- On 2026-09-30 the user explicitly requested main integration after being informed that browser verification and remote push were blocked. Products now have stored-data details and labelled mobile summaries; Shops have mobile summaries and accessible connection actions; desktop order names/status reasons wrap. Existing chat/notification, Finance and worker logic is preserved. See [scope, checks and remaining verification](ops/frontend-comfort-followup.md).
- Build, syntax, 15 database-free render checks and product-detail/action unit checks passed. Native browser layout, theme contrast, Escape/focus and full-panel regression remain pending; integration is not evidence that those checks passed. Retry browser verification and normal GitHub push when the environment allows them. Do not infer deployment success from local main integration.
