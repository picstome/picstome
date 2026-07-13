# Assignment 001 — Migration Batches

> **Inventory status (6b6c5e7e14e979fd999fd6390b56254a5c2a07de):** 58 Blade files, 1158 `<flux:` tags, 84 unique components, 28 named modals. Details: `01-flux-inventory.md`, `05-named-modal-mapping.md`, `06-planning-mismatches.md`.

**Repository:** `picstome/picstome`  
**Upstream base commit:** `6b6c5e7e14e979fd999fd6390b56254a5c2a07de`  
**Planning only:** No patch or replacement code is included.

## Batch rules

- Each coding batch changes **3–6 application files**.
- Dependency/configuration files may accompany a batch when necessary, but application-file scope remains bounded.
- Preserve Livewire properties, events, validation, authorization, routes, loading states and backend behavior.
- Every batch gets its own patch and handoff in the coding phase.
- Run the exact Flux inventory commands before beginning and after every batch.

## Batch 001 — Compatibility foundation

**Application files (3):**

- `resources/views/layouts/app.blade.php`
- `resources/views/layouts/guest.blade.php`
- `resources/views/components/settings/layout.blade.php`

**Supporting files to inspect/update only when approved:** `composer.json`, `composer.lock`, `package.json`, Tailwind/Vite entrypoints, app providers/configuration.

**Dependencies:** maryUI, daisyUI/Tailwind 4 compatibility, Livewire 4, a documented modal-state convention, icon strategy.

**Acceptance criteria:**

- maryUI renders in authenticated and guest layouts.
- Existing route shells, slots, `wire:navigate`, dark-mode classes and responsive containers remain intact.
- Flux remains installed temporarily; no application screen is forced to migrate in this batch.
- A neutral compatibility layer exists for shared button/link/text semantics without copying Flux source.
- Asset build completes and no duplicate Alpine/Livewire initialization occurs.

**Suggested local tests:**

```bash
composer install
npm ci
npm run build
php artisan test --compact
php artisan route:list
rg -n '<flux:|use Flux\\Flux|Flux::' resources app
```

**Rollback:** Revert the three layout files and dependency/config changes as one atomic patch; Flux remains available throughout the rollback.

## Batch 002 — Low-risk typography and buttons A

**Files (5):**

- `resources/views/partials/powered-by.blade.php`
- `resources/views/partials/social-links.blade.php`
- `resources/views/partials/settings-heading.blade.php`
- `resources/views/pages/pay/⚡cancel.blade.php`
- `resources/views/pages/pay/⚡success.blade.php`

**Dependencies:** Batch 001 shared primitives.

**Acceptance criteria:** semantic text and links match existing routes/labels; no Livewire state changes; focus, hover and dark mode remain readable.

**Tests:** targeted route/render tests; `npm run build`; keyboard smoke test.

**Rollback:** revert only these five files.

## Batch 003 — Low-risk typography and buttons B

**Files (5):**

- `resources/views/pages/⚡verify-email.blade.php`
- `resources/views/pages/stripe-connect/⚡index.blade.php`
- `resources/views/pages/stripe-connect/⚡return.blade.php`
- `resources/views/partials/public-branding.blade.php`
- `resources/views/partials/branding-header.blade.php`

**Dependencies:** Batches 001–002.

**Acceptance criteria:** links and actions preserve navigation, POST/action semantics and status messaging.

**Tests:** authentication/Stripe-connect feature tests if present; route smoke tests; browser focus check.

**Rollback:** revert these five files.

## Batch 004 — Forms and validation: authentication

**Files (6):**

- `resources/views/pages/⚡login.blade.php`
- `resources/views/pages/⚡register.blade.php`
- `resources/views/pages/⚡forgot-password.blade.php`
- `resources/views/pages/reset-password/⚡token.blade.php`
- `resources/views/pages/shares/⚡unlock.blade.php`
- `resources/views/components/⚡login-modal.blade.php`

**Dependencies:** shared input/button/error primitives; modal convention for login modal.

**Acceptance criteria:** exact `wire:model` paths, submit methods, validation messages, password types, session status and `wire:navigate` behavior remain unchanged.

**Tests:**

```bash
php artisan test --compact --filter=Auth
php artisan test --compact --filter=Password
php artisan test --compact --filter=Share
npm run build
```

Add Pest browser keyboard/focus coverage if upstream lacks it.

**Rollback:** revert six views; no form classes or backend actions should change.

## Batch 005 — Forms and validation: account and branding

**Files (6):**

- `resources/views/pages/settings/⚡profile.blade.php`
- `resources/views/pages/settings/⚡password.blade.php`
- `resources/views/pages/branding/⚡general.blade.php`
- `resources/views/pages/branding/⚡styling.blade.php`
- `resources/views/pages/branding/⚡payments.blade.php`
- `resources/views/pages/branding/⚡watermark.blade.php`

**Dependencies:** Batch 004 input/error conventions; upload primitive may remain deferred.

**Acceptance criteria:** validation bags, save actions, dirty/loading state, preview state and authorization remain unchanged.

**Tests:** targeted Livewire tests for settings/branding; browser validation and responsive checks.

**Rollback:** revert view-only patch; preserve stored settings schema.

## Batch 006 — Feedback and badges

**Files (5):**

- `resources/views/pages/⚡dashboard.blade.php`
- `resources/views/components/⚡storage-usage-indicator.blade.php`
- `resources/views/pages/handle/⚡show.blade.php`
- `resources/views/pages/⚡subscribe.blade.php`
- `resources/views/pages/⚡public-profile.blade.php`

**Dependencies:** badge, alert, progress and tooltip replacements.

**Acceptance criteria:** semantic status/color meaning, subscription/storage states and tooltip accessibility are preserved.

**Tests:** component renders for each state; contrast/dark-mode check; tooltip keyboard/touch check.

**Rollback:** revert five views.

## Batch 007 — Navigation and layout

**Files (5):**

- `resources/views/partials/branding-nav.blade.php`
- `resources/views/components/⚡profile-dropdown.blade.php`
- `resources/views/pages/portfolio/⚡index.blade.php`
- `resources/views/pages/⚡portfolio.blade.php`
- `resources/views/pages/portfolio/⚡show.blade.php`

**Dependencies:** menu/dropdown and active-navigation primitives.

**Acceptance criteria:** current-route state, responsive collapse, `wire:navigate`, click-away/Escape and focus return work.

**Tests:** browser navigation at mobile/desktop widths; keyboard-only menu test.

**Rollback:** revert five files and retain old shared primitives until all consumers migrate.

## Batch 008 — Modals and dropdowns A

**Files (5):**

- `resources/views/pages/⚡galleries.blade.php`
- `resources/views/pages/⚡customers.blade.php`
- `resources/views/pages/⚡contracts.blade.php`
- `resources/views/pages/⚡photoshoots.blade.php`
- `resources/views/pages/⚡contract-templates.blade.php`

**Dependencies:** named modal state adapter; dropdown/menu primitive.

**Acceptance criteria:** create/edit/delete actions, confirms, loading states, authorization and list refresh behavior remain unchanged.

**Tests:** targeted Livewire CRUD tests; browser modal focus/Escape/return-focus tests.

**Rollback:** revert five list screens.

## Batch 009 — Modals and dropdowns B

**Files (4):**

- `resources/views/pages/customers/⚡show.blade.php`
- `resources/views/pages/contracts/⚡show.blade.php`
- `resources/views/pages/photoshoots/⚡show.blade.php`
- `resources/views/pages/contract-templates/⚡show.blade.php`

**Dependencies:** Batch 008 patterns.

**Acceptance criteria:** action menus, destructive confirmations, authorization and state refreshes remain unchanged.

**Tests:** model-policy cases; CRUD actions; keyboard menu tests.

**Rollback:** revert four detail screens.

## Batch 010 — Tables

**Files (4):**

- `resources/views/pages/⚡users.blade.php`
- `resources/views/pages/⚡payments.blade.php`
- `resources/views/pages/⚡customers.blade.php`
- `resources/views/pages/⚡contracts.blade.php`

**Dependencies:** maryUI table strategy; responsive overflow pattern.

**Acceptance criteria:** headers, row actions, empty states, pagination/sorting/filtering and mobile reading order remain correct.

**Tests:** dataset-driven Livewire tests; browser table test at mobile and desktop; accessibility tree check.

**Rollback:** table markup only; do not change queries.

## Batch 011 — Date and time pickers

**Files (3):**

- `resources/views/pages/⚡payments.blade.php`
- `resources/views/pages/photoshoots/⚡show.blade.php`
- `resources/views/pages/galleries/⚡show.blade.php`

**Dependencies:** agreed date serialization contract, timezone policy and picker primitive.

**Acceptance criteria:** stored values, locale display, min/max constraints, nullable values and validation match Flux behavior.

**Tests:** timezone matrix; DST boundary cases; invalid/empty date cases; browser keyboard input.

**Rollback:** restore prior picker markup; no database or cast changes in this batch.

## Batch 012 — Command interfaces

**Files (3):**

- `resources/views/components/⚡search.blade.php`
- `resources/views/layouts/app.blade.php`
- `resources/views/components/⚡profile-dropdown.blade.php`

**Dependencies:** custom Alpine command palette; focus/keyboard utility.

**Acceptance criteria:** shortcut/open/close, query binding, result selection, active descendant and focus restoration work.

**Tests:** Pest browser keyboard sequence; no-JS graceful behavior where applicable.

**Rollback:** keep legacy trigger available behind a temporary feature flag or revert all three files.

## Batch 013 — Upload interfaces

**Files (4):**

- `resources/views/pages/branding/⚡logos.blade.php`
- `resources/views/pages/branding/⚡watermark.blade.php`
- `resources/views/pages/galleries/⚡show.blade.php`
- `resources/views/components/⚡register-modal.blade.php`

**Dependencies:** Livewire 4 upload/dropzone primitive; progress and error announcer.

**Acceptance criteria:** exact `wire:model`, multiple-file behavior, progress, cancellation, temporary preview, validation and storage-capacity errors remain correct.

**Tests:** Livewire upload tests with valid/invalid/duplicate/oversize files; browser progress and cancel tests.

**Rollback:** restore Flux upload UI; uploaded data must remain untouched.

## Batch 014 — Complex gallery components

**Files (5):**

- `resources/views/components/⚡photo-item.blade.php`
- `resources/views/components/⚡shared-photo-item.blade.php`
- `resources/views/pages/galleries/photos/⚡show.blade.php`
- `resources/views/pages/portfolio/photos/⚡show.blade.php`
- `resources/views/pages/shares/photos/⚡show.blade.php`

**Dependencies:** modal/menu/tooltip primitives, event contract inventory, responsive gallery CSS.

**Acceptance criteria:** favorite/comment/navigation/download actions, authorization, lazy behavior, focus and mobile gestures remain unchanged.

**Tests:** Livewire event tests; policy tests; browser gallery navigation and responsive checks.

**Rollback:** revert all five as a unit because event contracts cross component boundaries.

## Batch 015 — Complex gallery screen

**Files (3):**

- `resources/views/pages/galleries/⚡show.blade.php`
- `resources/views/pages/shares/⚡show.blade.php`
- `resources/views/pages/signatures/⚡sign.blade.php`

**Dependencies:** every prior UI primitive; PHP modal adapter removed or replaced; upload/date/tab/menu behavior proven.

**Acceptance criteria:** no change to authorization, uploads, cache keys, computed properties, URL-backed tab state, sharing events, favorite events, signing behavior or responsive gallery layout.

**Tests:** full targeted Livewire suite; Pest browser end-to-end flows for upload, share, favorite, comment, tab switching and signing.

**Rollback:** revert the full batch; do not partially roll back modal state without the view markup.

## Batch 016 — Final Flux-removal gate

**Application files (3):**

- `resources/views/layouts/app.blade.php`
- `resources/views/layouts/guest.blade.php`
- `resources/views/components/settings/layout.blade.php`

**Supporting files:** `composer.json`, `composer.lock`, documentation/configuration references.

**Dependencies:** all previous batches merged and verified; exact inventory count reaches zero.

**Acceptance criteria:**

```bash
rg -n '<flux:|use Flux\\Flux|Flux::|livewire/flux|livewire/flux-pro|composer\.fluxui\.dev'   app resources tests config composer.json composer.lock package.json
```

returns no unintended Flux runtime references; all tests and production asset builds pass; AGPL notices and upstream attribution remain.

**Tests:**

```bash
composer validate --strict
npm ci
npm run build
php artisan test --compact
vendor/bin/pint --test
```

Run the complete Pest browser suite locally.

**Rollback:** restore Flux dependencies and the three layout integration files together; never leave a half-removed Composer package or stale compiled asset.
