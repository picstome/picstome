# Assignment 001 — Migration Risk Register

> **Inventory status (6b6c5e7e14e979fd999fd6390b56254a5c2a07de):** 58 Blade files, 1158 `<flux:` tags, 84 unique components, 28 named modals. Details: `01-flux-inventory.md`, `05-named-modal-mapping.md`, `06-planning-mismatches.md`.

**Repository:** `picstome/picstome`  
**Upstream base commit:** `6b6c5e7e14e979fd999fd6390b56254a5c2a07de`  
**Status:** Planning artifact; no GitHub changes made.

| ID | Risk | Evidence / trigger | Impact | Likelihood | Mitigation | Verification / exit criterion | Owner stage |
|---|---|---|---|---|---|---|---|
| R-01 | Livewire binding regressions | Flux inputs wrap `wire:model` paths such as `form.email`, `form.password`; many pages are single-file Livewire components | High | High | Preserve exact binding expressions and modifiers; do not rename properties; migrate one form family at a time | Livewire tests assert hydration, validation, dirty/loading and submit actions | Forms batches |
| R-02 | Modal and focus behavior | PHP-side `Flux\Flux` named modal calls and `<flux:modal.trigger>` couple server actions to client overlays | Critical | High | Define one named-modal adapter using Livewire/Alpine state; document open/close API; test initial focus, trap, Escape and return focus | Pest browser tests pass for every named modal and destructive confirmation | Foundation + modal batches |
| R-03 | Date/time formatting | Flux date picker found in payments; gallery displays formatted expiration dates; other date fields likely exist in detail screens | High | Medium | Inventory model casts, timezone and locale; preserve serialized wire value; avoid changing DB format | Tests cover null, invalid, locale, timezone and DST boundaries | Date/time batch |
| R-04 | Upload state | Gallery uses `WithFileUploads`, duplicate-name validation and storage-capacity checks | Critical | High | Preserve exact `wire:model`, multiple-file array indexing, progress, errors, cancellation and temporary preview lifecycle | Valid, invalid, duplicate, oversize, cancel and retry upload tests pass | Upload + gallery batches |
| R-05 | Validation presentation | Flux fields may automatically render labels/errors/descriptions | High | High | Explicitly map error bags and `aria-describedby`; keep validation methods/rules unchanged | Error text, focus and invalid state verified in Livewire and browser tests | Forms batches |
| R-06 | Authorization bypass | Gallery/photo actions call policies/authorize; UI visibility may mirror permissions | Critical | Medium | Do not move authorization client-side; preserve server action authorization; test allowed and denied actors | Policy tests assert 403/hidden actions for unauthorized users | Every action batch |
| R-07 | Responsive layout drift | Gallery grids, navbar overflow, menus and public views have mobile-specific classes | High | High | Capture baseline screenshots; preserve Tailwind breakpoints; avoid broad class rewrites | Browser checks at 375, 768, 1024 and 1440 px | Navigation/gallery batches |
| R-08 | Dark-mode regressions | Existing views include explicit dark classes and separate light/dark logos | Medium | High | Keep current dark-mode source of truth; map maryUI/daisyUI theme tokens deliberately | Contrast and visual checks in both modes; no flash of wrong theme | Foundation + every visual batch |
| R-09 | Accessibility regressions | Flux supplies semantics/focus behavior that plain replacements may not | Critical | High | Prefer semantic HTML; implement WCAG keyboard/focus patterns; use accessible names, labels, roles and live regions | Keyboard-only flows, axe/accessibility-tree review, visible focus and contrast pass | Every batch |
| R-10 | Flux Pro licensing contamination | Project depends on `livewire/flux-pro` from a private Composer repository | Critical | Medium | Never inspect/copy vendor source, generated CSS/JS or proprietary implementation details; reimplement behavior from public API/observed app behavior | Handoff confirms no Flux Pro source copied; review diff for vendor-derived strings/structure | All batches |
| R-11 | AGPL compliance | Upstream is AGPL-3.0 | Critical | Medium | Preserve LICENSE, copyright and attribution; document modifications; ensure distribution/source obligations are reviewed by project owner | License files remain; handoffs identify upstream commit and modifications | Foundation/final gate |
| R-12 | Missing upstream tests | Search and package metadata show Pest tooling, but coverage for each UI behavior is not guaranteed | High | High | Inventory tests by feature before each batch; add targeted Livewire/browser tests before replacing complex controls | Every acceptance criterion has a named test or documented manual check | Before each batch |
| R-13 | Asset double initialization | Flux, maryUI, Alpine and Livewire can each inject scripts/styles | High | Medium | Introduce maryUI while Flux remains; verify one Alpine and one Livewire boot path; remove Flux assets only at final gate | No duplicate listener warnings, modal double-open or hydration errors | Foundation/final gate |
| R-14 | Icon mismatch | Flux icon names may not map one-to-one to maryUI/Heroicons | Medium | High | Build an icon mapping inventory; preserve accessible names and sizes; flag missing icons | No missing SVGs; icon-only buttons have accessible labels | Foundation/low-risk batches |
| R-15 | Menu semantics regression | Dropdown menus include links, Livewire actions, separators and destructive items | High | High | Create a single menu-item contract supporting href, wire:click, disabled and danger | Keyboard, click-away, Escape and action tests pass | Navigation/modal batches |
| R-16 | URL-backed state drift | Gallery uses `#[Url] public $activeTab` and Alpine updates | High | Medium | Keep URL property and values unchanged; replace only tab rendering/state bridge | Deep link, back/forward and refresh preserve selected tab | Gallery batch |
| R-17 | Event/cache side effects | Gallery dispatches events and invalidates specific cache keys | Critical | Medium | Do not alter PHP action bodies during UI migration; assert emitted events and refreshed counts | Event assertions and post-action UI state pass | Gallery batches |
| R-18 | Subscription/feature gating drift | Sharing options and branding/payment screens may vary by subscription | High | Medium | Preserve conditional branches and server checks exactly | Tests cover subscribed/unsubscribed and enabled/disabled feature states | Feedback/forms/gallery |
| R-19 | Table mobile usability | Advanced table replacement can alter overflow and action reachability | Medium | High | Preserve headers and DOM order; use accessible horizontal scrolling or responsive rows | Mobile keyboard/touch actions remain reachable | Table batch |
| R-20 | Premature dependency removal | Removing Flux before all PHP/view references are gone breaks runtime | Critical | Medium | Keep both Flux packages until zero-reference gate; remove in final isolated batch | `rg` returns zero runtime references and full suite/build pass | Final gate |

## Missing-test inventory required before coding

The local lead should map existing tests to the following behavior groups:

- authentication and password reset;
- settings/profile/branding validation;
- subscription and Stripe Connect states;
- list/detail CRUD for galleries, customers, contracts, photoshoots and templates;
- policy-denied actions;
- modal focus and destructive confirms;
- dropdown/menu keyboard navigation;
- date/time serialization and timezone boundaries;
- upload progress, validation, cancellation and storage limits;
- gallery favorite/comment/share events and URL-backed tabs;
- signing flow;
- responsive and dark-mode browser coverage.

Any group without an upstream test should be recorded in the relevant batch handoff and covered before or within that batch. Do not claim a behavior is tested merely because the global suite passes.

## Licensing guardrails

1. Do not open or copy `vendor/livewire/flux-pro` source.
2. Do not port vendor JavaScript, CSS, Blade markup, class names or internal APIs.
3. Use public maryUI/daisyUI/Livewire/Alpine documentation and the application’s observable behavior.
4. Preserve Picstome AGPL attribution and license notices.
5. Every coding-batch handoff must affirm: **No Flux Pro source was copied.**

## Final risk acceptance gate

Flux removal is permitted only when:

- exact `<flux:` count is zero;
- `Flux\Flux` PHP references are zero;
- Flux scripts/styles are absent;
- both Composer packages and private repository entry can be removed cleanly;
- build, feature tests and browser tests pass locally;
- dark mode, responsive behavior and accessibility checks pass;
- AGPL and attribution review is complete.
