# Automation visual implementation

Implemented 2026-09-29 from base `b06b25b`, following the [visual plan](automation-visual-plan.md). This changes the interface, not the rating sender or provider transport.

## Layout and interaction contract

- Three accessible tabs: Aturan toko, Uji aturan, Koneksi AI. Arrow keys, Home and End select tabs. Hashes support direct entry, including the existing `#ai-connections` link.
- Shop selection is shown on the two shop-scoped tabs. Connections explicitly belong to all shops. Switching tabs preserves both drafts; a dot and accessible tab name identify unsaved changes.
- The settings form groups target/rules and persona/provider. Persona and advanced options open inline. Rating rows show the actual action and identify stars outside the target without erasing their instructions.
- Profile save remains separate from connection save. The save area sticks to the viewport bottom, reverting to normal flow while an input is focused or the viewport is short. Controls have scroll margins to stay reachable above the bar.
- Native invalid events reveal the relevant panel and closed disclosure before focusing an invalid input. Request failures reveal their error context. Connection save restores focus to Add after controls are enabled.
- Rule testing uses the current form values, including unsaved values. Result labels identify the selected star, configured action and actual evaluation reason. It does not generate an AI reply.
- Connection rows show name, model, generation-test state and shop count. Details contain URL, shop names and separate catalog/generation diagnostics. Edit and Test are direct actions; Load models and Delete are in a downward menu, with Escape/outside-click dismissal.
- Empty, loading, validation, provider failure, conflict, in-use deletion and stale-test states retain their underlying behavior. Test-model quota confirmation remains explicit.
- Automation scopes a closed-shop-menu fix: the shared flex style could override daisyUI's `display:none`, leaving an invisible menu intercepting clicks. A page-scoped closed state now removes it from hit testing without changing other pages.

## Design read and reasons

Reading this as: an operational settings workspace for multi-shop sellers, using Shopdash's warm visual language, ENERGY 1 / RHYTHM 2 / MOTION 1. These dials were established in the plan before implementation.

| Decision | Reason |
| --- | --- |
| Existing palette and typography | Keep identity and theme consistency with the rest of the panel |
| Three task tabs | Remove testing and global connection administration from the settings scroll |
| Bordered sections with 16/20px spacing | Group related controls while retaining readable touch targets |
| Compact rule rows and persona disclosure | Make settings scannable while retaining editable instructions |
| Material Symbols | Reuse the existing family; star means rating, tune means rules, chat means persona, link means connection, science means test |
| Orange selected tab and primary action | Identify location and the main action without adding decorative graphics |
| Store logo and 9Router text name | Preserve real identity without inventing provider artwork |
| Minimal motion | Editing controls need stable targets; no decorative animation was added |

## Verification performed

- `npm run build`, PHP lint on both changed templates, JavaScript syntax checks, and `git diff --check` passed.
- `tests/automation-ui.cjs` passed: profile save, local rule preview, CSRF/validation, conflict/failure recovery, separate shops, keyboard tabs, retained profile/connection drafts, dirty-tab indicators, hidden persona/advanced validation reveal, target-star state, touch sizing, and closed-picker hit testing.
- `tests/ai-connections-ui.cjs` passed: CRUD with mocked mutations, key masking, URL/key validation, model picker keyboard/overflow, shop model overrides, conflicts, provider failures, deletion guards, More-menu keyboard dismissal, focus restoration, and authentication/CSRF checks.
- Both suites capture light/dark UI at 320, 500, 999 and 1600px. No horizontal page overflow was observed. Model options remain a downward vertical list. Screenshots were reviewed for the default settings, local preview and connection editor states.
- A computed-color audit across visible headings, labels, help, controls and actions in all three tabs, with editors open, found a minimum text contrast of 5.49:1 in each theme. This measures sampled rendered elements, not a comprehensive assistive-technology certification.
- Default page height was measured at 1008px viewport height, with no stored profile or connections, after loading completed:

| Width | Previous single page | New initial settings tab |
| --- | ---: | ---: |
| 500px | 3790px | 1700px |
| 999px | 3259px | 1625px |
| 1600px | 2328px | 1143px |

The reduction reflects moving tasks to tabs and collapsing detail. It does not claim faster task completion. Expanded forms can still be long. Screenshots and transient measurement scripts remain in ignored `tmp/`; they contain no saved credentials. Browser mutation/provider calls were mocked; no actual rating was sent or real provider generation invoked. Mobile checks used Chromium viewport simulation, not a physical phone keyboard.

## Antislop delivery gate

The following checks apply to the changed Automation surface, not unrelated panel pages.

| Check | Result and evidence |
| --- | --- |
| R-02 copy punctuation | PASS: changed user-facing copy contains no em dash |
| R-03 layout | PASS: all tabs checked at four widths; browser assertions reject horizontal overflow |
| R-17 statistics | PASS: connection counts and results come from returned records; no performance KPI invented |
| R-18 testimonials | PASS: none added |
| R-23 assets/navigation | PASS: authorized tab plan implemented; existing shop logos and icon family reused |
| R-24 links | PASS: tab anchors, connection link and existing shop route have destinations |
| R-25 contrast | PASS: computed text audit minimum 5.49:1 in sampled states |
| R-26 controls | PASS: tabs, disclosures, save, preview, CRUD, menu and catalog controls exercised |
| R-27 states | PASS: empty, loading, failures and conflicts retained; browser suites exercise recovery |
| R-28 FAQ | PASS: no generic FAQ added; help describes the actual local test and provider test |
| R-32 keyboard | PASS: tabs, validation focus, More menu, model picker and shop navigation covered |
| R-33 source | PASS: templates/JS/CSS source edited; tracked stylesheet rebuilt |
| R-34 themes | PASS: screenshots and overflow checks cover light and dark |
| R-35 runtime | PASS: built and exercised through the isolated PHP server on port 8131 |
| R-36 claims | PASS: pengiriman belum aktif remains explicit; rule test never claims generated replies |
| R-37 direction | PASS: existing identity and explicit dials documented above and in the original plan |
| R-38 fabricated content | PASS: no simulated customer data in shipped UI; fixtures remain test-only |
| R-01 gradients/glows | PASS: no new gradients or glows |
| R-04 icons | PASS: functional mapping documented above; icons are supplementary to text |
| R-06 typography | PASS: existing panel font and heading hierarchy retained |
| R-07 patterns | PASS: no decorative background pattern |
| R-08 arrows | PASS: disclosure chevrons indicate expansion; no decorative CTA arrows |
| R-09 badges | PASS: dirty dot indicates actual changes; no marketing badges |
| R-10 glass | PASS: solid theme surfaces |
| R-12 shadows | PASS: lightweight popup separation only; no floating-card treatment |
| R-13 glow spread | PASS: none added |
| R-14 repeated cards | PASS: section boundaries correspond to editable settings; rows and connection cards serve different tasks |
| R-19 motion | PASS: stable controls, no added reveal/scroll animation |
| R-22 illustrations | PASS: none added |
| Dials and rhythm | PASS: calm settings, compact repeated rating rows, task-specific panels, existing orange emphasis |
| Focal point | PASS: Save settings, Check rules, or Add/save connection according to the active task |
| Whitespace | PASS: gaps separate task groups; detailed editor fields only occupy space when open |
| Accent and motif | PASS: Shopdash orange, storefront identity and rating-star row pattern retained |
| Design read | PASS: declared in the pre-implementation plan and repeated above |
| C-1 intention | PASS: major visual decisions have a reason in the table above |
| C-2 functionality | PASS: browser suites exercise the modified interactive controls |
| C-3 content | PASS: sections derive from existing profiles, preview and connection CRUD |
| C-4 resilience | PASS: themes, widths, errors, drafts, keyboard and focus checks passed within the stated test scope |
| C-5 evidence | PASS: measurements and test results recorded with scope and limitations |
| R-05 composition | PASS: settings workspace, without marketing sections or fake KPI cards |
| R-11 radii | PASS: section and control radii retain existing hierarchy; no all-pill treatment |
| R-15 actions | PASS: Simpan pengaturan, Periksa aturan, Tambah koneksi, Ubah and Uji model describe their effects |
| R-16 language | PASS: no added AI marketing buzzwords |
| R-20 identity | PASS: Shopdash styling, shop identity and per-rating workflow preserved |
| R-21 theme | PASS: existing theme choice retained, neither mode forced |
| R-29 palette | PASS: existing neutral surfaces and orange accent |
| R-30 imitation | PASS: no external product styling introduced |
| R-31 reasons | PASS: layout, typography, spacing, boundaries and icons justified above |

## Boundaries

No Finance/HPP implementation, database schema, rating worker or sender was changed. Backend connection encryption, optimistic versions, model inheritance and deletion checks retain their existing contracts. Public deployment must be verified separately from Git push.
