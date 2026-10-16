# Named-modal mapping — Assignment 001

**Commit:** `6b6c5e7e14e979fd999fd6390b56254a5c2a07de`  
**Flux Pro source inspected:** No  
**Scope:** documentation only

## Method

- Definitions: `<flux:modal name="…">` in application Blade (not vendor).
- Triggers: `<flux:modal.trigger name="…">` (or parent-context triggers).
- Show/close: `Flux::modal('name')->show|close()` in Livewire/Blade PHP.
- Global: `Flux::modals()->close()` recorded separately.

## Global helpers

- Close-all: `resources/views/pages/signatures/⚡sign.blade.php:96` — `Flux::modals()->close();`

## Named modals (28)

### `(unnamed)`

- **Modal name:** `(unnamed)`
- **Blade view/component (definition):**
  - `resources/views/pages/contracts/⚡show.blade.php:246` attrs: `.close`
  - `resources/views/pages/⚡contract-templates.blade.php:42` attrs: `.trigger :name="auth()-`
  - `resources/views/pages/⚡contract-templates.blade.php:101` attrs: `.trigger :name="auth()-`
  - `resources/views/pages/⚡contracts.blade.php:63` attrs: `.trigger :name="auth()-`
  - `resources/views/pages/⚡contracts.blade.php:145` attrs: `.trigger :name="auth()-`
  - `resources/views/pages/⚡customers.blade.php:61` attrs: `.trigger :name="auth()-`
  - `resources/views/pages/⚡customers.blade.php:206` attrs: `.trigger :name="auth()-`
  - `resources/views/pages/⚡galleries.blade.php:52` attrs: `.trigger :name="auth()-`
  - `resources/views/pages/⚡galleries.blade.php:141` attrs: `.trigger :name="auth()-`
  - `resources/views/pages/⚡payments.blade.php:132` attrs: `.trigger :name="auth()-`
  - `resources/views/pages/⚡photoshoots.blade.php:51` attrs: `.trigger :name="auth()-`
  - `resources/views/pages/⚡photoshoots.blade.php:113` attrs: `.trigger :name="auth()-`
  - `resources/views/pages/⚡public-profile.blade.php:252` attrs: `.close`
  - `resources/views/pages/⚡public-profile.blade.php:282` attrs: `.close`
  - `resources/views/pages/⚡public-profile.blade.php:445` attrs: `.close`
- **PHP/Livewire caller:** _(no Flux::modal PHP show/close)_
- **Trigger:**
  - Parent-context `flux:modal.trigger` in same file(s) (name inferred by nesting):
    - `resources/views/pages/⚡contract-templates.blade.php:42`
    - `resources/views/pages/⚡contract-templates.blade.php:101`
    - `resources/views/pages/⚡contracts.blade.php:63`
    - `resources/views/pages/⚡contracts.blade.php:145`
    - `resources/views/pages/⚡customers.blade.php:61`
    - `resources/views/pages/⚡customers.blade.php:206`
    - `resources/views/pages/⚡galleries.blade.php:52`
    - `resources/views/pages/⚡galleries.blade.php:141`
    - `resources/views/pages/⚡payments.blade.php:132`
    - `resources/views/pages/⚡photoshoots.blade.php:51`
    - `resources/views/pages/⚡photoshoots.blade.php:113`
- **Show/close behavior:**
  - SHOW: _(none via `Flux::modal(...)->show()`)_
  - CLOSE: _(none via `Flux::modal(...)->close()`)_
- **Related event/state:** Livewire component state on the defining page; preserve wire models, validation, authorization, and any gallery/payment events already on that screen.
- **Proposed MaryUI/Livewire/Alpine direction:** MaryUI `x-modal` / Livewire state or Alpine dialog: keep the same named key `(unnamed)`; open via Livewire action or `wire:click` setting a boolean/`$dispatch`; close via Livewire action calling a shared modal helper (do not copy Flux internals).
- **Risk:** no explicit PHP close found (rely on modal UI close / navigation)
- **Required test:** Feature/browser: open `(unnamed)` from each trigger/PHP path; assert focus trap; Escape/backdrop close; submit success closes when applicable; no orphan overlays; Livewire validation errors keep modal open.

### `add-comment`

- **Modal name:** `add-comment`
- **Blade view/component (definition):**
  - `resources/views/pages/galleries/photos/⚡show.blade.php:309` attrs: `.trigger name="add-comment"`
  - `resources/views/pages/galleries/photos/⚡show.blade.php:360` attrs: `name="add-comment" class="w-full sm:max-w-lg"`
  - `resources/views/pages/shares/photos/⚡show.blade.php:419` attrs: `.trigger name="add-comment"`
  - `resources/views/pages/shares/photos/⚡show.blade.php:477` attrs: `name="add-comment" class="w-full sm:max-w-lg"`
- **PHP/Livewire caller:** _(no Flux::modal PHP show/close)_
- **Trigger:**
  - `resources/views/pages/galleries/photos/⚡show.blade.php:309` `name="add-comment"`
  - `resources/views/pages/shares/photos/⚡show.blade.php:419` `name="add-comment"`
- **Show/close behavior:**
  - SHOW: _(none via `Flux::modal(...)->show()`)_
  - CLOSE: _(none via `Flux::modal(...)->close()`)_
- **Related event/state:** Livewire component state on the defining page; preserve wire models, validation, authorization, and any gallery/payment events already on that screen.
- **Proposed MaryUI/Livewire/Alpine direction:** MaryUI `x-modal` / Livewire state or Alpine dialog: keep the same named key `add-comment`; open via Livewire action or `wire:click` setting a boolean/`$dispatch`; close via Livewire action calling a shared modal helper (do not copy Flux internals).
- **Risk:** trigger-driven open; no explicit PHP close found (rely on modal UI close / navigation)
- **Required test:** Feature/browser: open `add-comment` from each trigger/PHP path; assert focus trap; Escape/backdrop close; submit success closes when applicable; no orphan overlays; Livewire validation errors keep modal open.

### `add-link`

- **Modal name:** `add-link`
- **Blade view/component (definition):**
  - `resources/views/pages/⚡public-profile.blade.php:210` attrs: `.trigger name="add-link"`
  - `resources/views/pages/⚡public-profile.blade.php:221` attrs: `.trigger name="add-link"`
  - `resources/views/pages/⚡public-profile.blade.php:230` attrs: `name="add-link" class="md:w-96"`
- **PHP/Livewire caller:** _(no Flux::modal PHP show/close)_
- **Trigger:**
  - `resources/views/pages/⚡public-profile.blade.php:210` `name="add-link"`
  - `resources/views/pages/⚡public-profile.blade.php:221` `name="add-link"`
- **Show/close behavior:**
  - SHOW: _(none via `Flux::modal(...)->show()`)_
  - CLOSE: _(none via `Flux::modal(...)->close()`)_
- **Related event/state:** Livewire component state on the defining page; preserve wire models, validation, authorization, and any gallery/payment events already on that screen.
- **Proposed MaryUI/Livewire/Alpine direction:** MaryUI `x-modal` / Livewire state or Alpine dialog: keep the same named key `add-link`; open via Livewire action or `wire:click` setting a boolean/`$dispatch`; close via Livewire action calling a shared modal helper (do not copy Flux internals).
- **Risk:** trigger-driven open; no explicit PHP close found (rely on modal UI close / navigation)
- **Required test:** Feature/browser: open `add-link` from each trigger/PHP path; assert focus trap; Escape/backdrop close; submit success closes when applicable; no orphan overlays; Livewire validation errors keep modal open.

### `add-photos`

- **Modal name:** `add-photos`
- **Blade view/component (definition):**
  - `resources/views/pages/galleries/⚡show.blade.php:345` attrs: `.trigger name="add-photos"`
  - `resources/views/pages/galleries/⚡show.blade.php:441` attrs: `.trigger name="add-photos"`
  - `resources/views/pages/galleries/⚡show.blade.php:449` attrs: `name="add-photos" class="w-full sm:max-w-lg"`
- **PHP/Livewire caller:** _(no Flux::modal PHP show/close)_
- **Trigger:**
  - `resources/views/pages/galleries/⚡show.blade.php:345` `name="add-photos"`
  - `resources/views/pages/galleries/⚡show.blade.php:441` `name="add-photos"`
- **Show/close behavior:**
  - SHOW: _(none via `Flux::modal(...)->show()`)_
  - CLOSE: _(none via `Flux::modal(...)->close()`)_
- **Related event/state:** Livewire component state on the defining page; preserve wire models, validation, authorization, and any gallery/payment events already on that screen.
- **Proposed MaryUI/Livewire/Alpine direction:** MaryUI `x-modal` / Livewire state or Alpine dialog: keep the same named key `add-photos`; open via Livewire action or `wire:click` setting a boolean/`$dispatch`; close via Livewire action calling a shared modal helper (do not copy Flux internals).
- **Risk:** trigger-driven open; no explicit PHP close found (rely on modal UI close / navigation); gallery complexity: URL tabs, uploads, events, authorization
- **Required test:** Feature/browser: open `add-photos` from each trigger/PHP path; assert focus trap; Escape/backdrop close; submit success closes when applicable; no orphan overlays; Livewire validation errors keep modal open.

### `assign-photoshoot`

- **Modal name:** `assign-photoshoot`
- **Blade view/component (definition):**
  - `resources/views/pages/contracts/⚡show.blade.php:109` attrs: `.trigger name="assign-photoshoot"`
  - `resources/views/pages/contracts/⚡show.blade.php:227` attrs: `name="assign-photoshoot" class="md:w-96"`
- **PHP/Livewire caller:** `resources/views/pages/contracts/⚡show.blade.php`
- **Trigger:**
  - `resources/views/pages/contracts/⚡show.blade.php:109` `name="assign-photoshoot"`
- **Show/close behavior:**
  - SHOW: _(none via `Flux::modal(...)->show()`)_
  - CLOSE `resources/views/pages/contracts/⚡show.blade.php:65` — `Flux::modal('assign-photoshoot')->close();`
- **Related event/state:** Livewire component state on the defining page; preserve wire models, validation, authorization, and any gallery/payment events already on that screen.
- **Proposed MaryUI/Livewire/Alpine direction:** MaryUI `x-modal` / Livewire state or Alpine dialog: keep the same named key `assign-photoshoot`; open via Livewire action or `wire:click` setting a boolean/`$dispatch`; close via Livewire action calling a shared modal helper (do not copy Flux internals).
- **Risk:** trigger-driven open
- **Required test:** Feature/browser: open `assign-photoshoot` from each trigger/PHP path; assert focus trap; Escape/backdrop close; submit success closes when applicable; no orphan overlays; Livewire validation errors keep modal open.

### `available-galleries`

- **Modal name:** `available-galleries`
- **Blade view/component (definition):**
  - `resources/views/pages/⚡portfolio.blade.php:139` attrs: `.trigger name="available-galleries"`
  - `resources/views/pages/⚡portfolio.blade.php:150` attrs: `.trigger name="available-galleries"`
  - `resources/views/pages/⚡portfolio.blade.php:158` attrs: `name="available-galleries" class="md:w-[32rem]"`
- **PHP/Livewire caller:** _(no Flux::modal PHP show/close)_
- **Trigger:**
  - `resources/views/pages/⚡portfolio.blade.php:139` `name="available-galleries"`
  - `resources/views/pages/⚡portfolio.blade.php:150` `name="available-galleries"`
- **Show/close behavior:**
  - SHOW: _(none via `Flux::modal(...)->show()`)_
  - CLOSE: _(none via `Flux::modal(...)->close()`)_
- **Related event/state:** Livewire component state on the defining page; preserve wire models, validation, authorization, and any gallery/payment events already on that screen.
- **Proposed MaryUI/Livewire/Alpine direction:** MaryUI `x-modal` / Livewire state or Alpine dialog: keep the same named key `available-galleries`; open via Livewire action or `wire:click` setting a boolean/`$dispatch`; close via Livewire action calling a shared modal helper (do not copy Flux internals).
- **Risk:** trigger-driven open; no explicit PHP close found (rely on modal UI close / navigation)
- **Required test:** Feature/browser: open `available-galleries` from each trigger/PHP path; assert focus trap; Escape/backdrop close; submit success closes when applicable; no orphan overlays; Livewire validation errors keep modal open.

### `create-contract`

- **Modal name:** `create-contract`
- **Blade view/component (definition):**
  - `resources/views/pages/photoshoots/⚡show.blade.php:174` attrs: `.trigger name="create-contract"`
  - `resources/views/pages/photoshoots/⚡show.blade.php:415` attrs: `name="create-contract" class="w-full sm:max-w-lg"`
  - `resources/views/pages/⚡contracts.blade.php:153` attrs: `name="create-contract" class="w-full max-w-[794px]"`
- **PHP/Livewire caller:** _(no Flux::modal PHP show/close)_
- **Trigger:**
  - `resources/views/pages/photoshoots/⚡show.blade.php:174` `name="create-contract"`
- **Show/close behavior:**
  - SHOW: _(none via `Flux::modal(...)->show()`)_
  - CLOSE: _(none via `Flux::modal(...)->close()`)_
- **Related event/state:** Livewire component state on the defining page; preserve wire models, validation, authorization, and any gallery/payment events already on that screen.
- **Proposed MaryUI/Livewire/Alpine direction:** MaryUI `x-modal` / Livewire state or Alpine dialog: keep the same named key `create-contract`; open via Livewire action or `wire:click` setting a boolean/`$dispatch`; close via Livewire action calling a shared modal helper (do not copy Flux internals).
- **Risk:** trigger-driven open; no explicit PHP close found (rely on modal UI close / navigation)
- **Required test:** Feature/browser: open `create-contract` from each trigger/PHP path; assert focus trap; Escape/backdrop close; submit success closes when applicable; no orphan overlays; Livewire validation errors keep modal open.

### `create-customer`

- **Modal name:** `create-customer`
- **Blade view/component (definition):**
  - `resources/views/pages/⚡customers.blade.php:214` attrs: `name="create-customer" class="w-full sm:max-w-lg"`
- **PHP/Livewire caller:** _(no Flux::modal PHP show/close)_
- **Trigger:**
  - Parent-context `flux:modal.trigger` in same file(s) (name inferred by nesting):
    - `resources/views/pages/⚡customers.blade.php:61`
    - `resources/views/pages/⚡customers.blade.php:206`
- **Show/close behavior:**
  - SHOW: _(none via `Flux::modal(...)->show()`)_
  - CLOSE: _(none via `Flux::modal(...)->close()`)_
- **Related event/state:** Livewire component state on the defining page; preserve wire models, validation, authorization, and any gallery/payment events already on that screen.
- **Proposed MaryUI/Livewire/Alpine direction:** MaryUI `x-modal` / Livewire state or Alpine dialog: keep the same named key `create-customer`; open via Livewire action or `wire:click` setting a boolean/`$dispatch`; close via Livewire action calling a shared modal helper (do not copy Flux internals).
- **Risk:** no explicit PHP close found (rely on modal UI close / navigation)
- **Required test:** Feature/browser: open `create-customer` from each trigger/PHP path; assert focus trap; Escape/backdrop close; submit success closes when applicable; no orphan overlays; Livewire validation errors keep modal open.

### `create-gallery`

- **Modal name:** `create-gallery`
- **Blade view/component (definition):**
  - `resources/views/pages/photoshoots/⚡show.blade.php:167` attrs: `.trigger name="create-gallery"`
  - `resources/views/pages/photoshoots/⚡show.blade.php:308` attrs: `.trigger name="create-gallery"`
  - `resources/views/pages/photoshoots/⚡show.blade.php:371` attrs: `name="create-gallery" class="w-full sm:max-w-lg"`
  - `resources/views/pages/⚡galleries.blade.php:149` attrs: `name="create-gallery" class="w-full sm:max-w-lg"`
- **PHP/Livewire caller:** _(no Flux::modal PHP show/close)_
- **Trigger:**
  - `resources/views/pages/photoshoots/⚡show.blade.php:167` `name="create-gallery"`
  - `resources/views/pages/photoshoots/⚡show.blade.php:308` `name="create-gallery"`
- **Show/close behavior:**
  - SHOW: _(none via `Flux::modal(...)->show()`)_
  - CLOSE: _(none via `Flux::modal(...)->close()`)_
- **Related event/state:** Livewire component state on the defining page; preserve wire models, validation, authorization, and any gallery/payment events already on that screen.
- **Proposed MaryUI/Livewire/Alpine direction:** MaryUI `x-modal` / Livewire state or Alpine dialog: keep the same named key `create-gallery`; open via Livewire action or `wire:click` setting a boolean/`$dispatch`; close via Livewire action calling a shared modal helper (do not copy Flux internals).
- **Risk:** trigger-driven open; no explicit PHP close found (rely on modal UI close / navigation)
- **Required test:** Feature/browser: open `create-gallery` from each trigger/PHP path; assert focus trap; Escape/backdrop close; submit success closes when applicable; no orphan overlays; Livewire validation errors keep modal open.

### `create-photoshoot`

- **Modal name:** `create-photoshoot`
- **Blade view/component (definition):**
  - `resources/views/pages/⚡photoshoots.blade.php:121` attrs: `name="create-photoshoot" class="w-full sm:max-w-lg"`
- **PHP/Livewire caller:** _(no Flux::modal PHP show/close)_
- **Trigger:**
  - Parent-context `flux:modal.trigger` in same file(s) (name inferred by nesting):
    - `resources/views/pages/⚡photoshoots.blade.php:51`
    - `resources/views/pages/⚡photoshoots.blade.php:113`
- **Show/close behavior:**
  - SHOW: _(none via `Flux::modal(...)->show()`)_
  - CLOSE: _(none via `Flux::modal(...)->close()`)_
- **Related event/state:** Livewire component state on the defining page; preserve wire models, validation, authorization, and any gallery/payment events already on that screen.
- **Proposed MaryUI/Livewire/Alpine direction:** MaryUI `x-modal` / Livewire state or Alpine dialog: keep the same named key `create-photoshoot`; open via Livewire action or `wire:click` setting a boolean/`$dispatch`; close via Livewire action calling a shared modal helper (do not copy Flux internals).
- **Risk:** no explicit PHP close found (rely on modal UI close / navigation)
- **Required test:** Feature/browser: open `create-photoshoot` from each trigger/PHP path; assert focus trap; Escape/backdrop close; submit success closes when applicable; no orphan overlays; Livewire validation errors keep modal open.

### `create-template`

- **Modal name:** `create-template`
- **Blade view/component (definition):**
  - `resources/views/pages/⚡contract-templates.blade.php:109` attrs: `name="create-template" class="w-full max-w-[794px]"`
- **PHP/Livewire caller:** _(no Flux::modal PHP show/close)_
- **Trigger:**
  - Parent-context `flux:modal.trigger` in same file(s) (name inferred by nesting):
    - `resources/views/pages/⚡contract-templates.blade.php:42`
    - `resources/views/pages/⚡contract-templates.blade.php:101`
- **Show/close behavior:**
  - SHOW: _(none via `Flux::modal(...)->show()`)_
  - CLOSE: _(none via `Flux::modal(...)->close()`)_
- **Related event/state:** Livewire component state on the defining page; preserve wire models, validation, authorization, and any gallery/payment events already on that screen.
- **Proposed MaryUI/Livewire/Alpine direction:** MaryUI `x-modal` / Livewire state or Alpine dialog: keep the same named key `create-template`; open via Livewire action or `wire:click` setting a boolean/`$dispatch`; close via Livewire action calling a shared modal helper (do not copy Flux internals).
- **Risk:** no explicit PHP close found (rely on modal UI close / navigation)
- **Required test:** Feature/browser: open `create-template` from each trigger/PHP path; assert focus trap; Escape/backdrop close; submit success closes when applicable; no orphan overlays; Livewire validation errors keep modal open.

### `edit`

- **Modal name:** `edit`
- **Blade view/component (definition):**
  - `resources/views/pages/contract-templates/⚡show.blade.php:58` attrs: `.trigger name="edit"`
  - `resources/views/pages/contract-templates/⚡show.blade.php:72` attrs: `name="edit" class="w-full max-w-[794px]"`
  - `resources/views/pages/customers/⚡show.blade.php:133` attrs: `.trigger name="edit"`
  - `resources/views/pages/customers/⚡show.blade.php:359` attrs: `name="edit" class="w-full sm:max-w-lg"`
  - `resources/views/pages/galleries/⚡show.blade.php:302` attrs: `.trigger name="edit"`
  - `resources/views/pages/galleries/⚡show.blade.php:700` attrs: `name="edit" class="w-full sm:max-w-lg"`
  - `resources/views/pages/photoshoots/⚡show.blade.php:162` attrs: `.trigger name="edit"`
  - `resources/views/pages/photoshoots/⚡show.blade.php:499` attrs: `name="edit" class="w-full sm:max-w-lg"`
- **PHP/Livewire caller:** `resources/views/pages/contract-templates/⚡show.blade.php`
- **Trigger:**
  - `resources/views/pages/contract-templates/⚡show.blade.php:58` `name="edit"`
  - `resources/views/pages/customers/⚡show.blade.php:133` `name="edit"`
  - `resources/views/pages/galleries/⚡show.blade.php:302` `name="edit"`
  - `resources/views/pages/photoshoots/⚡show.blade.php:162` `name="edit"`
- **Show/close behavior:**
  - SHOW: _(none via `Flux::modal(...)->show()`)_
  - CLOSE `resources/views/pages/contract-templates/⚡show.blade.php:29` — `Flux::modal('edit')->close();`
- **Related event/state:** Livewire component state on the defining page; preserve wire models, validation, authorization, and any gallery/payment events already on that screen.
- **Proposed MaryUI/Livewire/Alpine direction:** MaryUI `x-modal` / Livewire state or Alpine dialog: keep the same named key `edit`; open via Livewire action or `wire:click` setting a boolean/`$dispatch`; close via Livewire action calling a shared modal helper (do not copy Flux internals).
- **Risk:** trigger-driven open
- **Required test:** Feature/browser: open `edit` from each trigger/PHP path; assert focus trap; Escape/backdrop close; submit success closes when applicable; no orphan overlays; Livewire validation errors keep modal open.

### `edit-link`

- **Modal name:** `edit-link`
- **Blade view/component (definition):**
  - `resources/views/pages/⚡public-profile.blade.php:260` attrs: `name="edit-link" class="md:w-96"`
- **PHP/Livewire caller:** _(no Flux::modal PHP show/close)_
- **Trigger:**
  - **No dedicated trigger or PHP show found** (may open via nested trigger without name).
- **Show/close behavior:**
  - SHOW: _(none via `Flux::modal(...)->show()`)_
  - CLOSE: _(none via `Flux::modal(...)->close()`)_
- **Related event/state:** Livewire component state on the defining page; preserve wire models, validation, authorization, and any gallery/payment events already on that screen.
- **Proposed MaryUI/Livewire/Alpine direction:** MaryUI `x-modal` / Livewire state or Alpine dialog: keep the same named key `edit-link`; open via Livewire action or `wire:click` setting a boolean/`$dispatch`; close via Livewire action calling a shared modal helper (do not copy Flux internals).
- **Risk:** no explicit PHP close found (rely on modal UI close / navigation)
- **Required test:** Feature/browser: open `edit-link` from each trigger/PHP path; assert focus trap; Escape/backdrop close; submit success closes when applicable; no orphan overlays; Livewire validation errors keep modal open.

### `edit-payment`

- **Modal name:** `edit-payment`
- **Blade view/component (definition):**
  - `resources/views/pages/⚡payments.blade.php:282` attrs: `name="edit-payment" variant="flyout"`
- **PHP/Livewire caller:** `resources/views/pages/⚡payments.blade.php`
- **Trigger:**
  - Parent-context `flux:modal.trigger` in same file(s) (name inferred by nesting):
    - `resources/views/pages/⚡payments.blade.php:132`
- **Show/close behavior:**
  - SHOW `resources/views/pages/⚡payments.blade.php:38` — `Flux::modal('edit-payment')->show();`
  - CLOSE `resources/views/pages/⚡payments.blade.php:47` — `Flux::modal('edit-payment')->close();`
  - CLOSE `resources/views/pages/⚡payments.blade.php:58` — `Flux::modal('edit-payment')->close();`
- **Related event/state:** Livewire component state on the defining page; preserve wire models, validation, authorization, and any gallery/payment events already on that screen.
- **Proposed MaryUI/Livewire/Alpine direction:** MaryUI `x-modal` / Livewire state or Alpine dialog: keep the same named key `edit-payment`; open via Livewire action or `wire:click` setting a boolean/`$dispatch`; close via Livewire action calling a shared modal helper (do not copy Flux internals).
- **Risk:** PHP-driven open (no Blade trigger)
- **Required test:** Feature/browser: open `edit-payment` from each trigger/PHP path; assert focus trap; Escape/backdrop close; submit success closes when applicable; no orphan overlays; Livewire validation errors keep modal open.

### `edit-user`

- **Modal name:** `edit-user`
- **Blade view/component (definition):**
  - `resources/views/pages/⚡users.blade.php:308` attrs: `name="edit-user" variant="flyout"`
- **PHP/Livewire caller:** `resources/views/pages/⚡users.blade.php`
- **Trigger:**
  - **PHP `->show()` only** (no `flux:modal.trigger` with this name).
- **Show/close behavior:**
  - SHOW `resources/views/pages/⚡users.blade.php:42` — `Flux::modal('edit-user')->show();`
  - CLOSE `resources/views/pages/⚡users.blade.php:48` — `Flux::modal('edit-user')->close();`
- **Related event/state:** Livewire component state on the defining page; preserve wire models, validation, authorization, and any gallery/payment events already on that screen.
- **Proposed MaryUI/Livewire/Alpine direction:** MaryUI `x-modal` / Livewire state or Alpine dialog: keep the same named key `edit-user`; open via Livewire action or `wire:click` setting a boolean/`$dispatch`; close via Livewire action calling a shared modal helper (do not copy Flux internals).
- **Risk:** PHP-driven open (no Blade trigger)
- **Required test:** Feature/browser: open `edit-user` from each trigger/PHP path; assert focus trap; Escape/backdrop close; submit success closes when applicable; no orphan overlays; Livewire validation errors keep modal open.

### `favorite-list`

- **Modal name:** `favorite-list`
- **Blade view/component (definition):**
  - `resources/views/pages/galleries/⚡show.blade.php:297` attrs: `.trigger name="favorite-list"`
  - `resources/views/pages/galleries/⚡show.blade.php:367` attrs: `.trigger name="favorite-list"`
  - `resources/views/pages/galleries/⚡show.blade.php:750` attrs: `name="favorite-list" class="w-full sm:max-w-lg" x-data="copyToClipboard"`
- **PHP/Livewire caller:** _(no Flux::modal PHP show/close)_
- **Trigger:**
  - `resources/views/pages/galleries/⚡show.blade.php:297` `name="favorite-list"`
  - `resources/views/pages/galleries/⚡show.blade.php:367` `name="favorite-list"`
- **Show/close behavior:**
  - SHOW: _(none via `Flux::modal(...)->show()`)_
  - CLOSE: _(none via `Flux::modal(...)->close()`)_
- **Related event/state:** Livewire component state on the defining page; preserve wire models, validation, authorization, and any gallery/payment events already on that screen.
- **Proposed MaryUI/Livewire/Alpine direction:** MaryUI `x-modal` / Livewire state or Alpine dialog: keep the same named key `favorite-list`; open via Livewire action or `wire:click` setting a boolean/`$dispatch`; close via Livewire action calling a shared modal helper (do not copy Flux internals).
- **Risk:** trigger-driven open; no explicit PHP close found (rely on modal UI close / navigation); gallery complexity: URL tabs, uploads, events, authorization
- **Required test:** Feature/browser: open `favorite-list` from each trigger/PHP path; assert focus trap; Escape/backdrop close; submit success closes when applicable; no orphan overlays; Livewire validation errors keep modal open.

### `generate-payment-link`

- **Modal name:** `generate-payment-link`
- **Blade view/component (definition):**
  - `resources/views/pages/handle/⚡show.blade.php:127` attrs: `name="generate-payment-link" x-init="if (new URL(window.location.href).searchParams.get('pay') === '1') { $nextTick(() =`
  - `resources/views/pages/photoshoots/⚡show.blade.php:543` attrs: `name="generate-payment-link" class="w-full sm:max-w-lg"`
  - `resources/views/pages/⚡payments.blade.php:207` attrs: `.trigger name="generate-payment-link"`
  - `resources/views/pages/⚡payments.blade.php:215` attrs: `name="generate-payment-link" class="w-full sm:max-w-lg"`
  - `resources/views/partials/social-links.blade.php:57` attrs: `.trigger name="generate-payment-link"`
  - `resources/views/partials/social-links.blade.php:66` attrs: `.trigger name="generate-payment-link"`
- **PHP/Livewire caller:** `resources/views/pages/⚡payments.blade.php`
- **Trigger:**
  - `resources/views/pages/⚡payments.blade.php:207` `name="generate-payment-link"`
  - `resources/views/partials/social-links.blade.php:57` `name="generate-payment-link"`
  - `resources/views/partials/social-links.blade.php:66` `name="generate-payment-link"`
- **Show/close behavior:**
  - SHOW: _(none via `Flux::modal(...)->show()`)_
  - CLOSE `resources/views/pages/⚡payments.blade.php:90` — `Flux::modal('generate-payment-link')->close();`
- **Related event/state:** Livewire component state on the defining page; preserve wire models, validation, authorization, and any gallery/payment events already on that screen.
- **Proposed MaryUI/Livewire/Alpine direction:** MaryUI `x-modal` / Livewire state or Alpine dialog: keep the same named key `generate-payment-link`; open via Livewire action or `wire:click` setting a boolean/`$dispatch`; close via Livewire action calling a shared modal helper (do not copy Flux internals).
- **Risk:** trigger-driven open
- **Required test:** Feature/browser: open `generate-payment-link` from each trigger/PHP path; assert focus trap; Escape/backdrop close; submit success closes when applicable; no orphan overlays; Livewire validation errors keep modal open.

### `login`

- **Modal name:** `login`
- **Blade view/component (definition):**
  - `resources/views/components/⚡login-modal.blade.php:24` attrs: `name="login" class="w-full sm:max-w-sm"`
  - `resources/views/components/⚡profile-dropdown.blade.php:40` attrs: `.trigger name="login"`
- **PHP/Livewire caller:** _(no Flux::modal PHP show/close)_
- **Trigger:**
  - `resources/views/components/⚡profile-dropdown.blade.php:40` `name="login"`
- **Show/close behavior:**
  - SHOW: _(none via `Flux::modal(...)->show()`)_
  - CLOSE: _(none via `Flux::modal(...)->close()`)_
- **Related event/state:** Livewire component state on the defining page; preserve wire models, validation, authorization, and any gallery/payment events already on that screen.
- **Proposed MaryUI/Livewire/Alpine direction:** MaryUI `x-modal` / Livewire state or Alpine dialog: keep the same named key `login`; open via Livewire action or `wire:click` setting a boolean/`$dispatch`; close via Livewire action calling a shared modal helper (do not copy Flux internals).
- **Risk:** trigger-driven open
- **Required test:** Feature/browser: open `login` from each trigger/PHP path; assert focus trap; Escape/backdrop close; submit success closes when applicable; no orphan overlays; Livewire validation errors keep modal open.

### `mark-favorites`

- **Modal name:** `mark-favorites`
- **Blade view/component (definition):**
  - `resources/views/pages/galleries/⚡show.blade.php:425` attrs: `.trigger name="mark-favorites"`
  - `resources/views/pages/galleries/⚡show.blade.php:877` attrs: `name="mark-favorites" class="w-full sm:max-w-lg"`
- **PHP/Livewire caller:** `resources/views/pages/galleries/⚡show.blade.php`
- **Trigger:**
  - `resources/views/pages/galleries/⚡show.blade.php:425` `name="mark-favorites"`
- **Show/close behavior:**
  - SHOW: _(none via `Flux::modal(...)->show()`)_
  - CLOSE `resources/views/pages/galleries/⚡show.blade.php:214` — `Flux::modal('mark-favorites')->close();`
- **Related event/state:** Livewire component state on the defining page; preserve wire models, validation, authorization, and any gallery/payment events already on that screen.
- **Proposed MaryUI/Livewire/Alpine direction:** MaryUI `x-modal` / Livewire state or Alpine dialog: keep the same named key `mark-favorites`; open via Livewire action or `wire:click` setting a boolean/`$dispatch`; close via Livewire action calling a shared modal helper (do not copy Flux internals).
- **Risk:** trigger-driven open; gallery complexity: URL tabs, uploads, events, authorization
- **Required test:** Feature/browser: open `mark-favorites` from each trigger/PHP path; assert focus trap; Escape/backdrop close; submit success closes when applicable; no orphan overlays; Livewire validation errors keep modal open.

### `payment-link`

- **Modal name:** `payment-link`
- **Blade view/component (definition):**
  - `resources/views/pages/⚡payments.blade.php:271` attrs: `name="payment-link" class="w-full sm:max-w-lg"`
- **PHP/Livewire caller:** `resources/views/pages/⚡payments.blade.php`
- **Trigger:**
  - Parent-context `flux:modal.trigger` in same file(s) (name inferred by nesting):
    - `resources/views/pages/⚡payments.blade.php:132`
- **Show/close behavior:**
  - SHOW `resources/views/pages/⚡payments.blade.php:92` — `Flux::modal('payment-link')->show();`
  - CLOSE: _(none via `Flux::modal(...)->close()`)_
- **Related event/state:** Livewire component state on the defining page; preserve wire models, validation, authorization, and any gallery/payment events already on that screen.
- **Proposed MaryUI/Livewire/Alpine direction:** MaryUI `x-modal` / Livewire state or Alpine dialog: keep the same named key `payment-link`; open via Livewire action or `wire:click` setting a boolean/`$dispatch`; close via Livewire action calling a shared modal helper (do not copy Flux internals).
- **Risk:** PHP-driven open (no Blade trigger); no explicit PHP close found (rely on modal UI close / navigation)
- **Required test:** Feature/browser: open `payment-link` from each trigger/PHP path; assert focus trap; Escape/backdrop close; submit success closes when applicable; no orphan overlays; Livewire validation errors keep modal open.

### `register`

- **Modal name:** `register`
- **Blade view/component (definition):**
  - `resources/views/components/⚡login-modal.blade.php:46` attrs: `.trigger name="register"`
  - `resources/views/components/⚡register-modal.blade.php:41` attrs: `name="register" class="w-full sm:max-w-sm"`
- **PHP/Livewire caller:** _(no Flux::modal PHP show/close)_
- **Trigger:**
  - `resources/views/components/⚡login-modal.blade.php:46` `name="register"`
- **Show/close behavior:**
  - SHOW: _(none via `Flux::modal(...)->show()`)_
  - CLOSE: _(none via `Flux::modal(...)->close()`)_
- **Related event/state:** Livewire component state on the defining page; preserve wire models, validation, authorization, and any gallery/payment events already on that screen.
- **Proposed MaryUI/Livewire/Alpine direction:** MaryUI `x-modal` / Livewire state or Alpine dialog: keep the same named key `register`; open via Livewire action or `wire:click` setting a boolean/`$dispatch`; close via Livewire action calling a shared modal helper (do not copy Flux internals).
- **Risk:** trigger-driven open
- **Required test:** Feature/browser: open `register` from each trigger/PHP path; assert focus trap; Escape/backdrop close; submit success closes when applicable; no orphan overlays; Livewire validation errors keep modal open.

### `search`

- **Modal name:** `search`
- **Blade view/component (definition):**
  - `resources/views/layouts/app.blade.php:47` attrs: `.trigger name="search" shortcut="cmd.k"`
  - `resources/views/layouts/app.blade.php:57` attrs: `name="search" variant="bare" class="my-[12vh] max-h-screen w-full max-w-[30rem] overflow-y-hidden px-2"`
  - `resources/views/pages/⚡dashboard.blade.php:286` attrs: `.trigger name="search" shortcut="cmd.k"`
- **PHP/Livewire caller:** _(no Flux::modal PHP show/close)_
- **Trigger:**
  - `resources/views/layouts/app.blade.php:47` `name="search" shortcut="cmd.k"`
  - `resources/views/pages/⚡dashboard.blade.php:286` `name="search" shortcut="cmd.k"`
- **Show/close behavior:**
  - SHOW: _(none via `Flux::modal(...)->show()`)_
  - CLOSE: _(none via `Flux::modal(...)->close()`)_
- **Related event/state:** Livewire component state on the defining page; preserve wire models, validation, authorization, and any gallery/payment events already on that screen.
- **Proposed MaryUI/Livewire/Alpine direction:** MaryUI `x-modal` / Livewire state or Alpine dialog: keep the same named key `search`; open via Livewire action or `wire:click` setting a boolean/`$dispatch`; close via Livewire action calling a shared modal helper (do not copy Flux internals).
- **Risk:** trigger-driven open
- **Required test:** Feature/browser: open `search` from each trigger/PHP path; assert focus trap; Escape/backdrop close; submit success closes when applicable; no orphan overlays; Livewire validation errors keep modal open.

### `share`

- **Modal name:** `share`
- **Blade view/component (definition):**
  - `resources/views/pages/galleries/⚡show.blade.php:333` attrs: `.trigger name="share"`
  - `resources/views/pages/galleries/⚡show.blade.php:340` attrs: `.trigger name="share"`
  - `resources/views/pages/galleries/⚡show.blade.php:595` attrs: `name="share" class="w-full sm:max-w-lg"`
- **PHP/Livewire caller:** `resources/views/pages/galleries/⚡show.blade.php`
- **Trigger:**
  - `resources/views/pages/galleries/⚡show.blade.php:333` `name="share"`
  - `resources/views/pages/galleries/⚡show.blade.php:340` `name="share"`
- **Show/close behavior:**
  - SHOW: _(none via `Flux::modal(...)->show()`)_
  - CLOSE `resources/views/pages/galleries/⚡show.blade.php:107` — `Flux::modal('share')->close();`
- **Related event/state:** Livewire component state on the defining page; preserve wire models, validation, authorization, and any gallery/payment events already on that screen.
- **Proposed MaryUI/Livewire/Alpine direction:** MaryUI `x-modal` / Livewire state or Alpine dialog: keep the same named key `share`; open via Livewire action or `wire:click` setting a boolean/`$dispatch`; close via Livewire action calling a shared modal helper (do not copy Flux internals).
- **Risk:** trigger-driven open; gallery complexity: URL tabs, uploads, events, authorization
- **Required test:** Feature/browser: open `share` from each trigger/PHP path; assert focus trap; Escape/backdrop close; submit success closes when applicable; no orphan overlays; Livewire validation errors keep modal open.

### `share-link`

- **Modal name:** `share-link`
- **Blade view/component (definition):**
  - `resources/views/pages/galleries/⚡show.blade.php:330` attrs: `.trigger name="share-link"`
  - `resources/views/pages/galleries/⚡show.blade.php:686` attrs: `name="share-link" class="w-full sm:max-w-lg"`
- **PHP/Livewire caller:** `resources/views/pages/galleries/⚡show.blade.php`
- **Trigger:**
  - `resources/views/pages/galleries/⚡show.blade.php:330` `name="share-link"`
- **Show/close behavior:**
  - SHOW `resources/views/pages/galleries/⚡show.blade.php:109` — `Flux::modal('share-link')->show();`
  - CLOSE: _(none via `Flux::modal(...)->close()`)_
- **Related event/state:** Livewire component state on the defining page; preserve wire models, validation, authorization, and any gallery/payment events already on that screen.
- **Proposed MaryUI/Livewire/Alpine direction:** MaryUI `x-modal` / Livewire state or Alpine dialog: keep the same named key `share-link`; open via Livewire action or `wire:click` setting a boolean/`$dispatch`; close via Livewire action calling a shared modal helper (do not copy Flux internals).
- **Risk:** no explicit PHP close found (rely on modal UI close / navigation); gallery complexity: URL tabs, uploads, events, authorization
- **Required test:** Feature/browser: open `share-link` from each trigger/PHP path; assert focus trap; Escape/backdrop close; submit success closes when applicable; no orphan overlays; Livewire validation errors keep modal open.

### `show-payment-link`

- **Modal name:** `show-payment-link`
- **Blade view/component (definition):**
  - `resources/views/pages/photoshoots/⚡show.blade.php:570` attrs: `name="show-payment-link" class="w-full sm:max-w-lg"`
- **PHP/Livewire caller:** _(no Flux::modal PHP show/close)_
- **Trigger:**
  - **No dedicated trigger or PHP show found** (may open via nested trigger without name).
- **Show/close behavior:**
  - SHOW: _(none via `Flux::modal(...)->show()`)_
  - CLOSE: _(none via `Flux::modal(...)->close()`)_
- **Related event/state:** Livewire component state on the defining page; preserve wire models, validation, authorization, and any gallery/payment events already on that screen.
- **Proposed MaryUI/Livewire/Alpine direction:** MaryUI `x-modal` / Livewire state or Alpine dialog: keep the same named key `show-payment-link`; open via Livewire action or `wire:click` setting a boolean/`$dispatch`; close via Livewire action calling a shared modal helper (do not copy Flux internals).
- **Risk:** no explicit PHP close found (rely on modal UI close / navigation)
- **Required test:** Feature/browser: open `show-payment-link` from each trigger/PHP path; assert focus trap; Escape/backdrop close; submit success closes when applicable; no orphan overlays; Livewire validation errors keep modal open.

### `sign`

- **Modal name:** `sign`
- **Blade view/component (definition):**
  - `resources/views/pages/signatures/⚡sign.blade.php:127` attrs: `.trigger name="sign"`
  - `resources/views/pages/signatures/⚡sign.blade.php:162` attrs: `name="sign" class="w-full sm:max-w-lg"`
- **PHP/Livewire caller:** _(no Flux::modal PHP show/close)_
- **Trigger:**
  - `resources/views/pages/signatures/⚡sign.blade.php:127` `name="sign"`
- **Show/close behavior:**
  - SHOW: _(none via `Flux::modal(...)->show()`)_
  - CLOSE: _(none via `Flux::modal(...)->close()`)_
- **Related event/state:** Livewire component state on the defining page; preserve wire models, validation, authorization, and any gallery/payment events already on that screen.
- **Proposed MaryUI/Livewire/Alpine direction:** MaryUI `x-modal` / Livewire state or Alpine dialog: keep the same named key `sign`; open via Livewire action or `wire:click` setting a boolean/`$dispatch`; close via Livewire action calling a shared modal helper (do not copy Flux internals).
- **Risk:** trigger-driven open; no explicit PHP close found (rely on modal UI close / navigation); signature canvas + Flux::modals()->close()
- **Required test:** Feature/browser: open `sign` from each trigger/PHP path; assert focus trap; Escape/backdrop close; submit success closes when applicable; no orphan overlays; Livewire validation errors keep modal open.

### `social-links`

- **Modal name:** `social-links`
- **Blade view/component (definition):**
  - `resources/views/pages/⚡public-profile.blade.php:355` attrs: `.trigger name="social-links"`
  - `resources/views/pages/⚡public-profile.blade.php:368` attrs: `.trigger name="social-links"`
  - `resources/views/pages/⚡public-profile.blade.php:376` attrs: `name="social-links" variant="flyout" class="md:w-[32rem]"`
- **PHP/Livewire caller:** _(no Flux::modal PHP show/close)_
- **Trigger:**
  - `resources/views/pages/⚡public-profile.blade.php:355` `name="social-links"`
  - `resources/views/pages/⚡public-profile.blade.php:368` `name="social-links"`
- **Show/close behavior:**
  - SHOW: _(none via `Flux::modal(...)->show()`)_
  - CLOSE: _(none via `Flux::modal(...)->close()`)_
- **Related event/state:** Livewire component state on the defining page; preserve wire models, validation, authorization, and any gallery/payment events already on that screen.
- **Proposed MaryUI/Livewire/Alpine direction:** MaryUI `x-modal` / Livewire state or Alpine dialog: keep the same named key `social-links`; open via Livewire action or `wire:click` setting a boolean/`$dispatch`; close via Livewire action calling a shared modal helper (do not copy Flux internals).
- **Risk:** trigger-driven open; no explicit PHP close found (rely on modal UI close / navigation)
- **Required test:** Feature/browser: open `social-links` from each trigger/PHP path; assert focus trap; Escape/backdrop close; submit success closes when applicable; no orphan overlays; Livewire validation errors keep modal open.

### `templates`

- **Modal name:** `templates`
- **Blade view/component (definition):**
  - `resources/views/pages/photoshoots/⚡show.blade.php:443` attrs: `.trigger name="templates"`
  - `resources/views/pages/photoshoots/⚡show.blade.php:470` attrs: `name="templates" class="w-full sm:max-w-lg"`
  - `resources/views/pages/⚡contracts.blade.php:191` attrs: `.trigger name="templates"`
  - `resources/views/pages/⚡contracts.blade.php:224` attrs: `name="templates" class="w-full sm:max-w-lg"`
- **PHP/Livewire caller:** _(no Flux::modal PHP show/close)_
- **Trigger:**
  - `resources/views/pages/photoshoots/⚡show.blade.php:443` `name="templates"`
  - `resources/views/pages/⚡contracts.blade.php:191` `name="templates"`
- **Show/close behavior:**
  - SHOW: _(none via `Flux::modal(...)->show()`)_
  - CLOSE: _(none via `Flux::modal(...)->close()`)_
- **Related event/state:** Livewire component state on the defining page; preserve wire models, validation, authorization, and any gallery/payment events already on that screen.
- **Proposed MaryUI/Livewire/Alpine direction:** MaryUI `x-modal` / Livewire state or Alpine dialog: keep the same named key `templates`; open via Livewire action or `wire:click` setting a boolean/`$dispatch`; close via Livewire action calling a shared modal helper (do not copy Flux internals).
- **Risk:** trigger-driven open; no explicit PHP close found (rely on modal UI close / navigation)
- **Required test:** Feature/browser: open `templates` from each trigger/PHP path; assert focus trap; Escape/backdrop close; submit success closes when applicable; no orphan overlays; Livewire validation errors keep modal open.

## Parent-context modal triggers (no `name=` on trigger)

These triggers inherit the surrounding modal name from Blade nesting. They are listed so none are lost during migration.

- `resources/views/pages/⚡contract-templates.blade.php:42` — `:name="auth()-`
- `resources/views/pages/⚡contract-templates.blade.php:101` — `:name="auth()-`
- `resources/views/pages/⚡contracts.blade.php:63` — `:name="auth()-`
- `resources/views/pages/⚡contracts.blade.php:145` — `:name="auth()-`
- `resources/views/pages/⚡customers.blade.php:61` — `:name="auth()-`
- `resources/views/pages/⚡customers.blade.php:206` — `:name="auth()-`
- `resources/views/pages/⚡galleries.blade.php:52` — `:name="auth()-`
- `resources/views/pages/⚡galleries.blade.php:141` — `:name="auth()-`
- `resources/views/pages/⚡payments.blade.php:132` — `:name="auth()-`
- `resources/views/pages/⚡photoshoots.blade.php:51` — `:name="auth()-`
- `resources/views/pages/⚡photoshoots.blade.php:113` — `:name="auth()-`

## Licensing

**No Flux Pro source, vendor CSS, vendor JavaScript, or proprietary implementation was inspected or copied.**

