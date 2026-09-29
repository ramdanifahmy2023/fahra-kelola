# Tools and command reference

General commands verified against source on 2026-09-29; Boost commands reviewed on 2026-09-30. Run commands from the Git repository root (`shopdash`). Paths contain spaces on the current Mac; quote absolute paths. This file documents tools; it does not grant extra permissions or require installing new tools.

## Environment

| Item | Project contract |
| --- | --- |
| Backend | PHP, custom MVC, PDO MySQL driver |
| Database | MariaDB/MySQL; connection settings in untracked `config/.env` |
| Frontend | PHP templates, plain JavaScript, Tailwind CSS 4, daisyUI 5 |
| Dependencies | `package.json` and `package-lock.json`; use `npm ci` for reproducible installation |
| Browser tests | Node.js plus Playwright/Chromium, provided separately from package dependencies |
| Local web | `http://127.0.0.1:8123` |
| Public URL used by this installation | `https://shopee.fahra.my.id`; do not assume Git push deploys it |
| Service manager on this Mac | Per-user macOS LaunchAgents |

Check availability without printing credentials:

```sh
php --version
php -m
node --version
npm --version
command -v mariadb
command -v mysql
git status --short
git branch --show-current
```

For configuration keys and example values, read `config/.env.example`. Do not paste the real `.env`, Shopee cookies, session IDs, account rows, or raw response captures into chat or commits.

## Routine development

| Command | Effect / use |
| --- | --- |
| `rg --files app tests ops` | Locate project files |
| `rg -n 'pattern' app public/assets/js resources/css` | Search readable sources; avoid minified CSS noise |
| `npm ci` | Installs dependencies and changes local `node_modules`; not a read-only check |
| `npm run build` | Regenerates tracked `public/assets/css/style.css` from `resources/css/input.css` |
| `php bin/finance-sync.php --schema-only` | Creates additive finance tables only; [finance operations](ops/finance.md) documents scoped import and pending-only options. |
| `php bin/finance-sync.php --repair-pending --shop=1` | Reads unresolved current pending statuses and today's paid GMV for one shop, under its existing Finance worker lock. Writes Finance records only; does not enqueue full imports or run other workers. Omit shop for all stores. |
| `npm run dev` | Long-running CSS watcher; stop when no longer needed |
| `php bin/release-extension.php --notes=tmp/release-notes.json` | Publishes a new extension ZIP and named changelog from the source manifest version; refuses to overwrite existing versions. Read `ops/sellerio-extension.md` first. |
| `php -l path/to/file.php` | PHP syntax check without executing the file |
| `node --check public/assets/js/ads.js` | JavaScript syntax check |
| `git diff --check` | Detect whitespace errors |
| `git diff --stat` | Review scope before staging |
| `php bin/ai-connection-key.php --init` | Creates an ignored server-side encryption keyring once; refuses overwrite. Read [9Router operations](ops/9router-connections.md) before deploying to a new checkout. |
| `php bin/ai-connection-key.php --rotate` | Adds an active master key while preserving previous keys; does not re-encrypt every database row immediately. |

Do not edit minified CSS directly. New CSS selectors/classes must be covered by the configured Tailwind sources or explicit source CSS. Panel CSS and changed JavaScript use file modification timestamps in asset URLs; preserve versioning.

## Web and background services

Only start a manual server if no intended service is already serving the port:

```sh
php -S 127.0.0.1:8123 -t public router.php
```

`-t public` matters for static assets. `router.php` routes application requests and lets existing static files pass through. The command stays running; it is not a deployment tool.

Read-only service inspection on macOS:

```sh
launchctl print "gui/$(id -u)/com.fahra.shopdash.web"
launchctl print "gui/$(id -u)/com.fahra.shopdash.scheduler"
launchctl print "gui/$(id -u)/com.fahra.shopdash.worker"
curl -I http://127.0.0.1:8123/
php bin/sync-observe.php
```

`./ops/install-background-sync.sh` rewrites local LaunchAgent definitions and unloads/reloads web, scheduler, and worker. It changes runtime state; do not run it for a documentation or ordinary CSS change. Service definitions and logs are detailed in [operations](ops/agent-operations.md).

## Boost worker commands

Read [Boost operations](ops/boost-automation.md) before runtime changes. The installed macOS service is `com.fahra.shopdash.boost`, running `bin/boost-worker.php --once --limit=5` from the main `shopdash` checkout every 60 seconds. Its plist is `~/Library/LaunchAgents/com.fahra.shopdash.boost.plist`; logs are `/tmp/shopdash-boost.log` and `/tmp/shopdash-boost.error.log`. Verify these paths/settings in the current service definition rather than assuming another checkout serves production.

| Command | Effect / use |
| --- | --- |
| `launchctl print "gui/$(id -u)/com.fahra.shopdash.boost"` | Read-only service definition, run count and last exit status. `not running` between scheduled invocations is normal. |
| `php bin/boost-worker.php --dry-run --shop=1` | Local read-only preview for an example local shop ID; replace `1` with the intended shop. No Shopee request, heartbeat, recovery or schedule writes. |
| `php bin/boost-worker.php --dry-run` | Read-only preview of due active profiles. Empty output may simply mean no profile is due. No flags also defaults to dry-run. |
| `php bin/boost-worker.php --migrate` | Applies only additive Boost tables; does not enable profiles or call Shopee. |
| `./ops/install-boost-worker.sh --install` | Installs only the Boost service and starts its first tick. Refuses an existing service/plist. Can send immediately if the server sender and due shop profiles are already enabled. |
| `launchctl kickstart "gui/$(id -u)/com.fahra.shopdash.boost"` | Triggers the existing service; writes heartbeat and may send real Boosts for due profiles. It is not a read-only health check. Do not add `-k` to kill an in-flight sender. |
| `php bin/boost-worker.php --once --limit=5` | Processes up to five due active shops; writes state and may call Shopee. Use as an alternative runner, not an additional loop beside the installed service. |
| `launchctl bootout "gui/$(id -u)/com.fahra.shopdash.boost"` | Unloads only the Boost service; inspect in-flight runs first. Does not disable manual sending or erase profiles/history. |

Read-only SQL checks through the configured database connection:

```sql
SELECT last_tick_at, sender_enabled,
       last_tick_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 3 MINUTE) AS alive
FROM boost_worker_health WHERE id = 1;
SELECT shop_id, enabled, next_check_at, last_checked_at, last_message
FROM boost_profiles ORDER BY shop_id;
SELECT id, shop_id, mode, status, success_count, failed_count, unknown_count,
       started_at, completed_at
FROM product_boost_runs ORDER BY id DESC LIMIT 10;
```

Database times are UTC; convert to WIB for user-facing updates. A fresh heartbeat proves the worker ticked, not that any product was sent. Check profiles and run results too. `BOOST_SEND_ENABLED` in the process environment overrides `config/.env`, including an explicit `0`; the switch is checked before every POST. New installations default off, but do not reset an existing enabled installation from that default. Per-shop activation is stored separately in `boost_profiles.enabled` and controlled from the panel. Global stop uses `BOOST_SEND_ENABLED=0`; pause individual shops through the panel. Requests already sent cannot be recalled. Preserve ledger/cooldown and resolve unknown outcomes through the documented inspection flow.

## Commands that modify data or call Shopee

| Command | Actual effect |
| --- | --- |
| `./ops/setup-local.sh` | Fresh installation only: imports destructive base schema, runs migrations, installs/builds frontend, creates admin |
| `php bin/create-admin.php` | Creates a login account; inspect arguments before use and do not expose passwords in output |
| `php bin/sync-scheduler.php --limit=50` | Enqueues due database work; may initialize schedule/schema state |
| `php bin/sync-worker.php --once --shop=1` | Example for local shop ID 1: claims and processes work, writes data, can call Shopee; not a health check |
| `php bin/sync-daemon.php` | Runs scheduler then worker; alternative runner, not an additional runner beside LaunchAgents |
| `php bin/export-schema.php tmp/schema-review.sql` | Reads database structure and writes a schema-only file; create `tmp/` first and review before committing |
| `php bin/ads-import-browser.php` | Imports reports; inspect source and `ops/ads-browser-pilot.md` before use |
| `php bin/ads-diagnose.php` | Diagnostic entrypoint; inspect arguments and network/data effects before running |

Worker flags are defined in `bin/sync-worker.php`: `--job`, `--shop`, `--once`, `--batch`, `--rate-ms`, `--packages`, and `--limit`. `--packages` requires a shop and performs enrichment. Do not invent a dry-run flag. A worker that exits immediately may have failed to acquire the existing process lock, not completed all work.

## Browser tooling

Use available browser tools for the user's existing tab. Do not assume a supplied tab ID can be controlled when the browser connection is unavailable. Use the repository's isolated Playwright tests for reproducible UI verification; report that distinction accurately.

Playwright is not listed in this project's package dependencies. First use an existing installed module. If Node cannot resolve it, point `PLAYWRIGHT_MODULE` to the actual package directory:

```sh
PLAYWRIGHT_MODULE='/absolute/path/to/node_modules/playwright' node tests/ads-ui.cjs
```

The path above is a placeholder. Do not bake a machine-specific npm cache hash into project code. Do not install packages or browser binaries merely because one hardcoded path stopped working.

## Git delivery

Current intended remote/branch: `origin` / `main`; verify each session. User authorizes committing and pushing completed, tested work. Stage explicit task files, inspect the staged diff, commit, push without force, and verify the result. Leave unrelated work untouched. A successful push confirms repository publication, not a separate deployment or database migration.

## Notification verification

- `php tests/notifications.php`: policy/lifecycle/per-account receipts and detector checks using temporary tables.
- `node tests/notifications-ui.cjs`: mocked notification writes, responsive bell and scoped order links; defaults to isolated server port 8131. Use `PLAYWRIGHT_MODULE` for an external Playwright install and `NOTIFICATION_TEST_URL` to change origin.
- Read [notification operations](ops/notifications.md) before extending detectors; schema initialization is additive, operational evaluation reads local sources, and read receipts must preserve revision checks.
