# Automation Engine: configuration foundation

Implemented 2026-09-29 after the initial audit, following the user's request to build a configurable foundation. Navigation is **AI Agent → Automation**, at `/panel/automation`.

Follow-up: [9Router connection CRUD](9router-connections.md) now adds encrypted DB connections, per-shop selection, catalog loading, and synthetic generation tests. The rule preview described here still makes no AI call. Legacy provider settings below describe the original foundation, not a fallback for the new connection manager.

## Available behavior

- Choose a shop using the shared logo picker. Profiles are keyed by the local `shops.id` and never copied across shops implicitly.
- Choose new ratings, a date range, or all ratings; select stars 1–5 independently. The user owns these choices.
- Store persona name/style, support policy, model ID, and an editorial reply-length limit. The 50–1,000 character setting is an internal bound, not a verified Shopee maximum.
- For each star, choose AI, manual review, or skip and customize instructions. Low ratings can use any supported action.
- Save validated configuration with an optimistic version. A stale tab receives HTTP 409 without overwriting another save. Audit rows retain actor, shop, version, and time; they are not full historical snapshots.
- Check a synthetic rating locally: replied status, selected stars, date range, and rule action. AI rules show the constructed system/user messages, not a generated reply. Review text stays in the user message as untrusted content.
- Show server configuration presence for 9Router, separately from verification. No network request is made to the provider.

New-only scope has no activation timestamp yet. Preview explicitly reports that its date eligibility is pending, rather than claiming a sample will run. Saving a profile never enables automation.

## Persistence and endpoints

`database/migrations/20260929_automation_profiles.sql` creates `automation_profiles` and `automation_profile_events` with `CREATE TABLE IF NOT EXISTS`. `AutomationProfile::ensureSchema()` applies these additive statements when opening/saving a profile; the database account needs table creation privileges. Do not import the destructive bootstrap schema.

Authenticated JSON endpoints use the exact controller casing supported by this router:

- `POST /procAutomation/save`: CSRF header, existing local `shop_id`, expected `version`, validated `config`; transaction updates profile and audit event.
- `POST /procAutomation/preview`: CSRF header, existing local `shop_id`, `config`, synthetic `sample`; evaluates locally without saving.

Bodies are bounded to 32 KiB. The server returns generic failures rather than database details. Browser code renders returned prompt text with `textContent`, preserves edits on failures, and warns before leaving unsaved edits.

Provider keys belong only in ignored `config/.env`: `NINE_ROUTER_BASE_URL`, `NINE_ROUTER_API_KEY`, `NINE_ROUTER_MODEL`. Empty examples are tracked in `config/.env.example`; credentials and base URL are not serialized into the page. Connection presence is not a successful provider test.

## UI decisions

Applied UI UX Pro Max and antislop within Shopdash's existing theme. The form follows the actual workflow: targets, persona, then per-star rules. On wide screens the provider and example checker sit alongside the editor; mobile stacks them. Native disclosures keep five rule editors manageable. Shared shop logos, downward menus, theme colors, visible focus, and 44px controls preserve existing conventions. No queue counters, active status, or future feature controls are fabricated.

## Verification

Run from the checkout serving the test URL:

```sh
php tests/automation-profile.php
AUTOMATION_TEST_URL=http://127.0.0.1:8131 PLAYWRIGHT_MODULE=/absolute/path/to/playwright node tests/automation-ui.cjs
npm run build
git diff --check
```

The PHP suite covers validation, boundaries, local preview, profile isolation, persistence, optimistic conflicts, and successful-save audit events using temporary tables. The browser suite mocks persistence calls, tests the real local preview and CSRF/validation responses, stale/failure recovery, shop switching, keyboard use, and 320/500/999/1600px in light/dark themes. Screenshots stay in ignored `tmp/automation-ui/`. No production profile fixtures, AI calls, or rating submissions are part of these tests.

## Next phase

The [API audit](rating-api-audit-20260929.md) and [engine proposal](automation-engine-plan.md) remain the roadmap. Still needed: verified rating pagination and authenticated PHP transport, discovery checkpoints, AI rating draft generation/output validation, draft review, durable task leases/deduplication, pre-send readback, outcome reconciliation, explicit activation, and operational limits. Provider catalog and synthetic tests use the new 9Router adapter. Do not treat the saved action `ai` as authorization to start sending.

## Concurrent development

This foundation was built in the separate `feature/automation-foundation` worktree because three Codex sessions were active. Preserve other branches and worktree changes; merge shared sidebar/controller/CSS changes additively and regenerate compiled CSS. Never stage another session's files or force-push to resolve publication races.
