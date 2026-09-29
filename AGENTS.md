# Project instructions

## UI/UX workflow

- Use UI UX Pro Max and antislop for UI design, implementation, and final review. The user has already chosen to apply antislop throughout; do not ask again.
- Read `/Users/fahra/.codex/skills/ui-ux-pro-max/SKILL.md` and `/Users/fahra/.codex/skills/antislop/SKILL.md`, plus the relevant `antislop-ui`, `antislop-human`, `antislop-layoutmobile`, and `antislop-copywriting` companion skills in that directory. On another machine, locate the installed equivalents.
- Follow the existing Shopdash colors, typography, light/dark themes, and component styles. Explicit user feedback takes precedence over general skill recommendations.

## Shop selectors and identity

- The shop dropdown on Products is the visual reference for shop selectors across the panel, including Customers, Orders, Reports, Ads, and advertising topup history.
- Reuse `app/views/panel/templates/shop-picker.php` for server-rendered selectors and `public/assets/js/shop-select.js` for dynamic select controls. Shared styles live in `resources/css/input.css` under `.shop-picker` and `.shop-picker-list`.
- Open the menu below its trigger. Keep shops in one vertical column with `flex-wrap: nowrap`, bounded height, and vertical scrolling. Never wrap options into sideways columns or let the menu extend beyond the viewport.
- Show each shop's actual logo alongside its name in both options and the selected value. Allow long option names to wrap. Keep controls at least 44px high and usable with keyboard navigation and visible focus.
- Preserve existing filters when switching shops, reset pagination where appropriate, and actually load the chosen shop's data. Keep independent selectors independent, such as Ads and Topup. Retain “Semua toko” where supported.
- Shop headers on Voucher & Flash Sale, Ads, and Boost must show the matching shop logo. Use `templates/shop-logos.php` and `public/assets/js/shop-logos.js`, keyed by the local shop ID. Use a storefront fallback for missing or failed images; never substitute another shop's logo.
- Use 24px logos in selectors and 40px logos in shop headers. Keep logo dimensions reserved and prevent names from overlapping them.

## Responsive product rows

- In Boost, keep checkbox, product thumbnail, and title in separate grid columns. Do not return to floated images inside text.
- Product thumbnails are 48px square. Long titles must wrap without clipping or horizontal overflow. Metadata reflows below according to available container width.

## Verification and delivery

- Edit CSS in `resources/css/input.css`, then run `npm run build` to regenerate the tracked stylesheet. Keep asset versioning so browsers receive updated CSS and JavaScript.
- Check changed UI at narrow/mobile and desktop widths, including 320px, 500px, 999px, and 1600px when relevant, in light and dark themes. Verify real selection behavior, scrolling, long names, image fallbacks, and keyboard use.
- Run relevant existing tests. Shop selector coverage is in `tests/shop-picker-ui.cjs`, `tests/shop-branding-ui.cjs`, `tests/products-ui.cjs`, and `tests/ads-ui.cjs`; Boost coverage is in `tests/boost-ui.cjs`. Customer shop scoping is covered by `php tests/customer-shop-filter.php` using temporary tables.
- UI tests must not execute real boosts, purchases, or other business mutations. Mock mutation endpoints when testing those flows.
- The user has requested that completed work be committed and pushed to GitHub after verification. Push the task's changes to the current intended branch (currently `main` on `origin`) without asking again. Include generated assets and relevant tests. Do not include unrelated changes, secrets, or test artifacts; never force-push. Report the commit and any blocker honestly.
