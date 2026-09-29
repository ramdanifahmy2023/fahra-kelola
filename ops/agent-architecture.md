# Architecture map for maintainers

Source review: 2026-09-29. Start with [AGENTS.md](../AGENTS.md), [memory](../memory.md), and [tools](../tools.md).

## Request lifecycle

1. Local PHP server serves `public/` with root `router.php`.
2. Router passes application routes to `public/index.php`; entrypoint initializes session, bootstrap, and authentication guard.
3. `app/init.php` loads core classes, `config/define.php`, and authentication helpers.
4. `core/App.php` resolves controller/method from the route; `core/Controller.php` loads models and views.
5. `app/controllers/front/Panel.php` prepares page data and renders header, page view, and footer. Back controllers provide action/JSON endpoints.
6. `core/Database.php` provides PDO queries/binding and transactions. `core/BaseModel.php` provides shared persistence methods; domain models implement queries and synchronization.

This is not Laravel or a Node backend. Do not introduce framework commands or an assumed ORM/migration runner.

`config/define.php` reads the local INI-style `.env`. Web URLs derive from the request origin, including forwarded scheme handling; CLI uses configured `APP_URL`. Preserve this distinction so public pages never send visitors to the server's loopback address. PHP routing currently uses `ucfirst` controller lookup; verify case behavior on case-sensitive filesystems rather than assuming macOS behavior transfers unchanged.

## Feature map

| Panel page | Primary source / related endpoint |
| --- | --- |
| Products | `app/views/panel/products.php`, `Product.php`, `ProcProducts.php` |
| Orders | `orders.php`, `order_row.php`, `Order.php`, `OrderItem.php`, `ProcOrders.php` |
| Customers | `customers.php`, `Customer.php`, `ProcCustomers.php` |
| Reports | `reports.php`, `ShopPerformance.php`, `ProcReports.php` |
| Ads and topups | `ads.php`, `public/assets/js/ads.js`, `AdsMonitor.php`, `ProcAds.php`, Ads helper classes |
| Promotions | `promotions.php`, `PromotionMonitor.php`, `ProcPromotions.php` |
| Boost | `boost.php`, `public/assets/js/boost.js`, `ProductBoostMonitor.php`, `ProcProducts.php` |
| Sync | `sync.php`, `BackgroundSync.php`, `SyncJob.php`, `ProcSync.php` |
| Shops/session management | `shops.php`, `Shop.php`, `ProcShops.php` |
| Chat | `chat.php`, `ChatMonitor.php`, `ShopeeChat.php`, `ProcChat.php` |
| Automation configuration | `automation.php`, `public/assets/js/automation.js`, `AutomationProfile.php`, `app/helpers/AutomationPolicy.php`, `ProcAutomation.php` |

View paths in the table are under `app/views/panel/`, model paths under `app/models/`, and back controllers under `app/controllers/back/` unless otherwise specified.

## Shared UI

- Panel chrome: `app/views/panel/templates/header.php`, `navbar.php`, `sidebar.php`, `footer.php`.
- Theme behavior is available through `window.shopdashTheme`; browser tests exercise both modes.
- Styles: `resources/css/input.css`; compiled asset: `public/assets/css/style.css`.
- Server selector: `templates/shop-picker.php`. It supplies real links for Products/Orders/Customers; Reports intercepts selection for AJAX.
- Dynamic selector: `public/assets/js/shop-select.js`. Keep backing select IDs stable because `ads.js` reads values and dispatches/listens for `change`. Rebuild menu content after updating options or disabled state using the returned sync function.
- Logo serialization/rendering: `templates/shop-logos.php` and `public/assets/js/shop-logos.js`. Load renderer before consumers; use local `shops.id`, not Shopee's remote ID.
- Boost uses a PHP template cloned by JavaScript. Changes to generated rows belong in `boost.js`, not only the PHP template.

## Data identity and state

- `shops.id` is the local key used by panel filters, schedules, and jobs. `shops.shop_id` is the remote Shopee shop ID. Do not interchange them.
- Customer membership spans shops. Selecting a shop must constrain both aggregate order counts and modal history, while preserving the all-shop view.
- Orders and products have local details/status/freshness fields; queue completion and freshness are separate concepts.
- `sync_jobs` stores parent work; `sync_job_orders` stores detail work; `sync_schedules` stores per-shop schedules. `sync_runs`, `sync_pages`, and checkpoints provide processing context.
- Ads distinguish channel and period. Do not sum incompatible attribution totals or replace unavailable metrics with invented zeroes. See [ads mapping](ads-data-mapping.md).

## Synchronization lifecycle

`bin/sync-scheduler.php` calls `BackgroundSync::enqueueDue`. `bin/sync-worker.php` claims eligible jobs with leases, dispatches tasks, and records outcomes. `SyncQueue`, `SyncOutcome`, `OrderSyncPolicy`, `SyncWorkerTasks`, `ProductSynchronizer`, and `PackageSynchronizer` hold queue/domain behavior. Worker locks, retry deadlines, fairness, and recovery matter; do not bypass them with page-triggered workers.

Use [operations](agent-operations.md) for progress measurement and recovery, and the dated [recovery](sync-recovery-20260929.md) / [improvements](sync-improvements-20260929.md) notes for rationale. Their runtime observations are historical.

## Database evolution

Automation currently persists per-shop profiles and version audit events only. `/procAutomation/save` requires authentication/CSRF and rejects stale versions; `/procAutomation/preview` evaluates example data locally. No rating worker or sender exists. See [foundation contract](automation-foundation.md) before extending it.

`database/schema.sql` is schema-only bootstrap, not a safe incremental update. `database/migrations/` contains explicit SQL changes; some models also ensure their schemas. Inspect both before changing structure. Keep migration review, backups, and runtime application separate from Git publication. Never commit a data dump or run fresh-install bootstrap as a migration shortcut.
