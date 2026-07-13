# Assignment 001 — Flux Inventory (measured)

**Repository:** `picstome/picstome`  
**Upstream base commit:** `6b6c5e7e14e979fd999fd6390b56254a5c2a07de`  
**Assignment type:** Analysis and migration planning only  
**Repository changes made:** Documentation under `docs/planning/` only  
**Flux Pro source copied:** No  
**Flux Pro / vendor source inspected:** No (`vendor/` and `node_modules/` excluded from all scans)

## Verification status and scope

This inventory was measured on a local checkout of `6b6c5e7e14e979fd999fd6390b56254a5c2a07de` at `D:\Program\Codex\Picstome\repo`.

### Measured totals

| Metric | Value |
|---|---:|
| Application Blade files containing `<flux:` | **58** |
| Total `<flux:…` opening tags | **1158** |
| Unique Flux component tags (including nested names) | **84** |
| Named modals (`name=` / PHP show\|close) | **28** |
| Files with `use Flux\Flux;` | **12** |
| Lines matching `use Flux\Flux` or `Flux::` | **33** |

### Authoritative local commands (re-run evidence)

```powershell
cd D:\Program\Codex\Picstome\repo
git rev-parse HEAD  # expect 6b6c5e7e14e979fd999fd6390b56254a5c2a07de
python docs/planning/build_inventory.py
python docs/planning/generate_docs.py
```

Raw machine output: `docs/planning/inventory-raw.json`

## Dependency and integration findings (measured)

- `composer.json` requires `livewire/flux` `^2.0` and `livewire/flux-pro` `^2.2`.
- Composer private repo `flux-pro` → `https://composer.fluxui.dev`.
- Locked versions (from `composer.lock` references only): Flux free **v2.12.2**, Flux Pro **2.12.2**. **Package contents were not opened.**
- `package.json` has **no** Flux npm dependency.
- `resources/css/app.css` references a vendor Flux CSS import path (path noted only; vendor tree not inspected for Pro implementation).
- Application code uses `use Flux\Flux;` plus `Flux::modal(...)->show|close()`, `Flux::modals()->close()`, and `Flux::toast(...)`.

### Dependency / asset reference hits (path + line)

| File:line | Snippet |
|---|---|
| `composer.json:15` | `"livewire/flux": "^2.0",` |
| `composer.json:16` | `"livewire/flux-pro": "^2.2",` |
| `composer.json:85` | `"flux-pro": {` |
| `composer.json:87` | `"url": "https://composer.fluxui.dev"` |
| `composer.lock:2839` | `"name": "livewire/flux",` |
| `composer.lock:2843` | `"url": "https://github.com/livewire/flux.git",` |
| `composer.lock:2848` | `"url": "https://api.github.com/repos/livewire/flux/zipball/68a3b06b62b23bae82e02d6be39722cf2a8770ff",` |
| `composer.lock:2871` | `"Flux\\FluxServiceProvider"` |
| `composer.lock:2899` | `"issues": "https://github.com/livewire/flux/issues",` |
| `composer.lock:2900` | `"source": "https://github.com/livewire/flux/tree/v2.12.2"` |
| `composer.lock:2905` | `"name": "livewire/flux-pro",` |
| `composer.lock:2909` | `"url": "https://composer.fluxui.dev/download/a129da01-0b6c-4769-9430-e9156c8da2ba/flux-pro-2.12.2.zip",` |
| `composer.lock:2918` | `"livewire/flux": "2.12.2\|dev-main",` |
| `README.md:27` | `composer require livewire/flux-pro` |
| `AGENTS.md:16` | `- livewire/flux (FLUXUI_FREE) - v2` |
| `AGENTS.md:17` | `- livewire/flux-pro (FLUXUI_PRO) - v2` |
| `resources/css/app.css:2` | `@import '../../vendor/livewire/flux/dist/flux.css';` |
| `resources/views/layouts/app.blade.php:19` | `@fluxAppearance` |
| `resources/views/layouts/app.blade.php:188` | `@fluxScripts` |
| `resources/views/layouts/guest.blade.php:47` | `@fluxAppearance` |
| `resources/views/layouts/guest.blade.php:58` | `@fluxScripts` |

## Every file containing `<flux:` (measured occurrence counts)

| # | File | Occurrence count |
|---:|---|---:|
| 1 | `resources/views/components/settings/layout.blade.php` | **7** |
| 2 | `resources/views/components/⚡login-modal.blade.php` | **7** |
| 3 | `resources/views/components/⚡photo-item.blade.php` | **16** |
| 4 | `resources/views/components/⚡profile-dropdown.blade.php` | **13** |
| 5 | `resources/views/components/⚡register-modal.blade.php` | **8** |
| 6 | `resources/views/components/⚡search.blade.php` | **14** |
| 7 | `resources/views/components/⚡shared-photo-item.blade.php` | **5** |
| 8 | `resources/views/components/⚡storage-usage-indicator.blade.php` | **2** |
| 9 | `resources/views/layouts/app.blade.php` | **36** |
| 10 | `resources/views/layouts/guest.blade.php` | **1** |
| 11 | `resources/views/pages/branding/⚡general.blade.php` | **7** |
| 12 | `resources/views/pages/branding/⚡logos.blade.php` | **10** |
| 13 | `resources/views/pages/branding/⚡payments.blade.php` | **21** |
| 14 | `resources/views/pages/branding/⚡styling.blade.php` | **6** |
| 15 | `resources/views/pages/branding/⚡watermark.blade.php` | **17** |
| 16 | `resources/views/pages/contract-templates/⚡show.blade.php` | **15** |
| 17 | `resources/views/pages/contracts/⚡show.blade.php` | **31** |
| 18 | `resources/views/pages/customers/⚡show.blade.php` | **34** |
| 19 | `resources/views/pages/galleries/photos/⚡show.blade.php` | **34** |
| 20 | `resources/views/pages/galleries/⚡show.blade.php` | **137** |
| 21 | `resources/views/pages/handle/⚡show.blade.php` | **9** |
| 22 | `resources/views/pages/pay/⚡cancel.blade.php` | **3** |
| 23 | `resources/views/pages/pay/⚡success.blade.php` | **3** |
| 24 | `resources/views/pages/photoshoots/⚡show.blade.php` | **92** |
| 25 | `resources/views/pages/portfolio/photos/⚡show.blade.php` | **3** |
| 26 | `resources/views/pages/portfolio/⚡index.blade.php` | **6** |
| 27 | `resources/views/pages/portfolio/⚡show.blade.php` | **7** |
| 28 | `resources/views/pages/reset-password/⚡token.blade.php` | **5** |
| 29 | `resources/views/pages/settings/⚡appearance.blade.php` | **8** |
| 30 | `resources/views/pages/settings/⚡password.blade.php` | **4** |
| 31 | `resources/views/pages/settings/⚡profile.blade.php` | **8** |
| 32 | `resources/views/pages/shares/photos/⚡show.blade.php` | **25** |
| 33 | `resources/views/pages/shares/⚡show.blade.php` | **10** |
| 34 | `resources/views/pages/shares/⚡unlock.blade.php` | **5** |
| 35 | `resources/views/pages/signatures/⚡sign.blade.php` | **20** |
| 36 | `resources/views/pages/stripe-connect/⚡index.blade.php` | **7** |
| 37 | `resources/views/pages/stripe-connect/⚡return.blade.php` | **10** |
| 38 | `resources/views/pages/⚡contract-templates.blade.php` | **18** |
| 39 | `resources/views/pages/⚡contracts.blade.php` | **37** |
| 40 | `resources/views/pages/⚡customers.blade.php` | **22** |
| 41 | `resources/views/pages/⚡dashboard.blade.php` | **37** |
| 42 | `resources/views/pages/⚡forgot-password.blade.php` | **5** |
| 43 | `resources/views/pages/⚡galleries.blade.php` | **25** |
| 44 | `resources/views/pages/⚡login.blade.php` | **8** |
| 45 | `resources/views/pages/⚡payments.blade.php` | **55** |
| 46 | `resources/views/pages/⚡photoshoots.blade.php` | **24** |
| 47 | `resources/views/pages/⚡portfolio.blade.php` | **32** |
| 48 | `resources/views/pages/⚡public-profile.blade.php` | **140** |
| 49 | `resources/views/pages/⚡register.blade.php` | **13** |
| 50 | `resources/views/pages/⚡subscribe.blade.php` | **17** |
| 51 | `resources/views/pages/⚡users.blade.php` | **47** |
| 52 | `resources/views/pages/⚡verify-email.blade.php` | **5** |
| 53 | `resources/views/partials/branding-header.blade.php` | **3** |
| 54 | `resources/views/partials/branding-nav.blade.php` | **6** |
| 55 | `resources/views/partials/powered-by.blade.php` | **2** |
| 56 | `resources/views/partials/public-branding.blade.php` | **1** |
| 57 | `resources/views/partials/settings-heading.blade.php` | **3** |
| 58 | `resources/views/partials/social-links.blade.php` | **12** |

**Sum of per-file counts:** 1158 (matches total opening tags).

## Unique Flux component inventory (measured)

| Flux component | Total occurrence count |
|---|---:|
| `flux:button` | **168** |
| `flux:input` | **111** |
| `flux:heading` | **92** |
| `flux:text` | **75** |
| `flux:subheading` | **55** |
| `flux:modal.trigger` | **45** |
| `flux:modal` | **36** |
| `flux:menu.item` | **34** |
| `flux:spacer` | **34** |
| `flux:separator` | **33** |
| `flux:error` | **30** |
| `flux:badge` | **29** |
| `flux:label` | **27** |
| `flux:field` | **27** |
| `flux:navlist.item` | **23** |
| `flux:callout` | **20** |
| `flux:callout.heading` | **19** |
| `flux:callout.text` | **19** |
| `flux:avatar` | **19** |
| `flux:menu` | **14** |
| `flux:select.option` | **14** |
| `flux:dropdown` | **13** |
| `flux:textarea` | **13** |
| `flux:select` | **12** |
| `flux:description` | **12** |
| `flux:link` | **12** |
| `flux:switch` | **11** |
| `flux:tooltip` | **10** |
| `flux:radio` | **9** |
| `flux:table.cell` | **8** |
| `flux:icon.check` | **8** |
| `flux:icon.photo` | **7** |
| `flux:navlist.group` | **6** |
| `flux:navbar.item` | **6** |
| `flux:pagination` | **6** |
| `flux:table.column` | **6** |
| `flux:navlist` | **5** |
| `flux:command.item` | **5** |
| `flux:tab` | **5** |
| `flux:tab.panel` | **5** |
| `flux:icon.heart` | **4** |
| `flux:input.group` | **4** |
| `flux:input.group.suffix` | **4** |
| `flux:modal.close` | **4** |
| `flux:checkbox` | **3** |
| `flux:radio.group` | **3** |
| `flux:profile` | **2** |
| `flux:menu.group` | **2** |
| `flux:sidebar.toggle` | **2** |
| `flux:main` | **2** |
| `flux:tooltip.content` | **2** |
| `flux:button.group` | **2** |
| `flux:navbar` | **2** |
| `flux:card` | **2** |
| `flux:breadcrumbs.item` | **2** |
| `flux:time-picker` | **2** |
| `flux:icon.bars-2` | **2** |
| `flux:table` | **2** |
| `flux:table.rows` | **2** |
| `flux:table.row` | **2** |
| `flux:icon.arrow-top-right-on-square` | **2** |
| `flux:command` | **1** |
| `flux:command.input` | **1** |
| `flux:command.items` | **1** |
| `flux:icon.server` | **1** |
| `flux:sidebar` | **1** |
| `flux:header` | **1** |
| `flux:toast` | **1** |
| `flux:icon.pencil-square` | **1** |
| `flux:icon.calendar` | **1** |
| `flux:menu.separator` | **1** |
| `flux:tab.group` | **1** |
| `flux:tabs` | **1** |
| `flux:breadcrumbs` | **1** |
| `flux:icon.clipboard-document-list` | **1** |
| `flux:icon.document-text` | **1** |
| `flux:icon.user` | **1** |
| `flux:icon.credit-card` | **1** |
| `flux:date-picker` | **1** |
| `flux:icon.camera` | **1** |
| `flux:icon.x-mark` | **1** |
| `flux:icon.plus` | **1** |
| `flux:avatar.group` | **1** |
| `flux:table.columns` | **1** |

## Files using `use Flux\Flux;` (measured)

- `resources/views/pages/branding/⚡general.blade.php`
- `resources/views/pages/branding/⚡logos.blade.php`
- `resources/views/pages/branding/⚡payments.blade.php`
- `resources/views/pages/branding/⚡styling.blade.php`
- `resources/views/pages/branding/⚡watermark.blade.php`
- `resources/views/pages/contract-templates/⚡show.blade.php`
- `resources/views/pages/contracts/⚡show.blade.php`
- `resources/views/pages/galleries/⚡show.blade.php`
- `resources/views/pages/signatures/⚡sign.blade.php`
- `resources/views/pages/⚡payments.blade.php`
- `resources/views/pages/⚡public-profile.blade.php`
- `resources/views/pages/⚡users.blade.php`

## All `use Flux\Flux` / `Flux::` lines (measured)

| File:line | Snippet |
|---|---|
| `resources/views/pages/branding/⚡general.blade.php:5` | `use Flux\Flux;` |
| `resources/views/pages/branding/⚡general.blade.php:24` | `Flux::toast(__('Your changes have been saved.'), variant: 'success');` |
| `resources/views/pages/branding/⚡general.blade.php:33` | `Flux::toast(__('Setup steps have been reset.'), variant: 'success');` |
| `resources/views/pages/branding/⚡logos.blade.php:5` | `use Flux\Flux;` |
| `resources/views/pages/branding/⚡logos.blade.php:24` | `Flux::toast(__('Your changes have been saved.'), variant: 'success');` |
| `resources/views/pages/branding/⚡payments.blade.php:4` | `use Flux\Flux;` |
| `resources/views/pages/branding/⚡payments.blade.php:25` | `Flux::toast(__('Your changes have been saved.'), variant: 'success');` |
| `resources/views/pages/branding/⚡styling.blade.php:5` | `use Flux\Flux;` |
| `resources/views/pages/branding/⚡styling.blade.php:24` | `Flux::toast(__('Your changes have been saved.'), variant: 'success');` |
| `resources/views/pages/branding/⚡watermark.blade.php:5` | `use Flux\Flux;` |
| `resources/views/pages/branding/⚡watermark.blade.php:24` | `Flux::toast(__('Your changes have been saved.'), variant: 'success');` |
| `resources/views/pages/contract-templates/⚡show.blade.php:5` | `use Flux\Flux;` |
| `resources/views/pages/contract-templates/⚡show.blade.php:29` | `Flux::modal('edit')->close();` |
| `resources/views/pages/contracts/⚡show.blade.php:4` | `use Flux\Flux;` |
| `resources/views/pages/contracts/⚡show.blade.php:65` | `Flux::modal('assign-photoshoot')->close();` |
| `resources/views/pages/galleries/⚡show.blade.php:8` | `use Flux\Flux;` |
| `resources/views/pages/galleries/⚡show.blade.php:107` | `Flux::modal('share')->close();` |
| `resources/views/pages/galleries/⚡show.blade.php:109` | `Flux::modal('share-link')->show();` |
| `resources/views/pages/galleries/⚡show.blade.php:214` | `Flux::modal('mark-favorites')->close();` |
| `resources/views/pages/signatures/⚡sign.blade.php:4` | `use Flux\Flux;` |
| `resources/views/pages/signatures/⚡sign.blade.php:96` | `Flux::modals()->close();` |
| `resources/views/pages/⚡payments.blade.php:6` | `use Flux\Flux;` |
| `resources/views/pages/⚡payments.blade.php:38` | `Flux::modal('edit-payment')->show();` |
| `resources/views/pages/⚡payments.blade.php:47` | `Flux::modal('edit-payment')->close();` |
| `resources/views/pages/⚡payments.blade.php:58` | `Flux::modal('edit-payment')->close();` |
| `resources/views/pages/⚡payments.blade.php:90` | `Flux::modal('generate-payment-link')->close();` |
| `resources/views/pages/⚡payments.blade.php:92` | `Flux::modal('payment-link')->show();` |
| `resources/views/pages/⚡public-profile.blade.php:8` | `use Flux\Flux;` |
| `resources/views/pages/⚡public-profile.blade.php:33` | `Flux::toast(__('Your changes have been saved.'), variant: 'success');` |
| `resources/views/pages/⚡public-profile.blade.php:46` | `Flux::toast(__('Your changes have been saved.'), variant: 'success');` |
| `resources/views/pages/⚡users.blade.php:5` | `use Flux\Flux;` |
| `resources/views/pages/⚡users.blade.php:42` | `Flux::modal('edit-user')->show();` |
| `resources/views/pages/⚡users.blade.php:48` | `Flux::modal('edit-user')->close();` |

## Named modals (summary)

Full mapping: `docs/planning/05-named-modal-mapping.md`

| Modal name | Definitions | Triggers (`modal.trigger`) | PHP show | PHP close |
|---|---:|---:|---:|---:|
| `(unnamed)` | 15 | 0 | 0 | 0 |
| `add-comment` | 4 | 2 | 0 | 0 |
| `add-link` | 3 | 2 | 0 | 0 |
| `add-photos` | 3 | 2 | 0 | 0 |
| `assign-photoshoot` | 2 | 1 | 0 | 1 |
| `available-galleries` | 3 | 2 | 0 | 0 |
| `create-contract` | 3 | 1 | 0 | 0 |
| `create-customer` | 1 | 0 | 0 | 0 |
| `create-gallery` | 4 | 2 | 0 | 0 |
| `create-photoshoot` | 1 | 0 | 0 | 0 |
| `create-template` | 1 | 0 | 0 | 0 |
| `edit` | 8 | 4 | 0 | 1 |
| `edit-link` | 1 | 0 | 0 | 0 |
| `edit-payment` | 1 | 0 | 1 | 2 |
| `edit-user` | 1 | 0 | 1 | 1 |
| `favorite-list` | 3 | 2 | 0 | 0 |
| `generate-payment-link` | 6 | 3 | 0 | 1 |
| `login` | 2 | 1 | 0 | 0 |
| `mark-favorites` | 2 | 1 | 0 | 1 |
| `payment-link` | 1 | 0 | 1 | 0 |
| `register` | 2 | 1 | 0 | 0 |
| `search` | 3 | 2 | 0 | 0 |
| `share` | 3 | 2 | 0 | 1 |
| `share-link` | 2 | 1 | 1 | 0 |
| `show-payment-link` | 1 | 0 | 0 | 0 |
| `sign` | 2 | 1 | 0 | 0 |
| `social-links` | 3 | 2 | 0 | 0 |
| `templates` | 4 | 2 | 0 | 0 |

### Global modal close

- `resources/views/pages/signatures/⚡sign.blade.php:96` — `Flux::modals()->close();`

## Free versus Pro classification rule

Classification remains **planning guidance only**. This inventory does **not** open Flux Pro vendor source to prove Free vs Pro. Implementers must consult public Flux 2.x docs and treat advanced controls as high-risk replacements without reverse-engineering proprietary code.

## Mismatches vs prior planning draft

See `docs/planning/06-planning-mismatches.md`.

## Licensing / AGPL

- Picstome project license: AGPL-3.0 (`LICENSE.md` present).
- This work product is documentation only.
- **No Flux Pro source, vendor CSS, vendor JavaScript, or proprietary implementation was inspected or copied.**

