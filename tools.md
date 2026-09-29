# Tools and command reference

Verified against source on 2026-09-29. Run commands from the Git repository root (`shopdash`). Paths contain spaces on the current Mac; quote absolute paths. This file documents tools; it does not grant extra permissions or require installing new tools.

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
