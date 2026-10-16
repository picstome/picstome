# Planning mismatches — Assignment 001

**Commit:** `6b6c5e7e14e979fd999fd6390b56254a5c2a07de`

Measured results compared to the pre-measurement planning artifacts under `D:\Program\Codex\Picstome\`.

## Confirmed matches

- Blade file count with `<flux:`: planning said **58**; local measure **58** — **MATCH**.
- Pinned commit `6b6c5e7e14e979fd999fd6390b56254a5c2a07de` present and checked out — **MATCH**.
- `livewire/flux` + `livewire/flux-pro` required in `composer.json` with private `composer.fluxui.dev` repo — **MATCH**.

## Mismatches / gaps

1. **Unique component tags:** planning table listed **~24** components; local measure **84** distinct `flux:*` tags.
2. **Components present locally but absent from original planning table:**

   - `flux:avatar` (**19**)
   - `flux:avatar.group` (**1**)
   - `flux:breadcrumbs` (**1**)
   - `flux:breadcrumbs.item` (**2**)
   - `flux:callout` (**20**)
   - `flux:callout.heading` (**19**)
   - `flux:callout.text` (**19**)
   - `flux:card` (**2**)
   - `flux:checkbox` (**3**)
   - `flux:command.input` (**1**)
   - `flux:command.item` (**5**)
   - `flux:command.items` (**1**)
   - `flux:description` (**12**)
   - `flux:error` (**30**)
   - `flux:field` (**27**)
   - `flux:header` (**1**)
   - `flux:heading` (**92**)
   - `flux:icon.arrow-top-right-on-square` (**2**)
   - `flux:icon.bars-2` (**2**)
   - `flux:icon.calendar` (**1**)
   - `flux:icon.camera` (**1**)
   - `flux:icon.check` (**8**)
   - `flux:icon.clipboard-document-list` (**1**)
   - `flux:icon.credit-card` (**1**)
   - `flux:icon.document-text` (**1**)
   - `flux:icon.heart` (**4**)
   - `flux:icon.pencil-square` (**1**)
   - `flux:icon.photo` (**7**)
   - `flux:icon.plus` (**1**)
   - `flux:icon.server` (**1**)
   - `flux:icon.user` (**1**)
   - `flux:icon.x-mark` (**1**)
   - `flux:input.group` (**4**)
   - `flux:input.group.suffix` (**4**)
   - `flux:label` (**27**)
   - `flux:main` (**2**)
   - `flux:menu.group` (**2**)
   - `flux:modal.close` (**4**)
   - `flux:navlist` (**5**)
   - `flux:navlist.group` (**6**)
   - `flux:navlist.item` (**23**)
   - `flux:pagination` (**6**)
   - `flux:profile` (**2**)
   - `flux:radio` (**9**)
   - `flux:radio.group` (**3**)
   - `flux:select` (**12**)
   - `flux:select.option` (**14**)
   - `flux:separator` (**33**)
   - `flux:sidebar` (**1**)
   - `flux:sidebar.toggle` (**2**)
   - `flux:spacer` (**34**)
   - `flux:subheading` (**55**)
   - `flux:switch` (**11**)
   - `flux:table.cell` (**8**)
   - `flux:table.column` (**6**)
   - `flux:table.columns` (**1**)
   - `flux:table.row` (**2**)
   - `flux:table.rows` (**2**)
   - `flux:textarea` (**13**)
   - `flux:time-picker` (**2**)
   - `flux:toast` (**1**)

3. **Components listed in planning table but not found as tags:**

   - `flux:file-upload` — **0 occurrences** at this commit
   - Note: planning assumed `flux:file-upload`; **no `<flux:file-upload` tags** found. Galleries use Livewire `WithFileUploads` with other Flux chrome, not a Flux file-upload tag.

4. **Per-file / per-component counts:** planning used `VERIFY LOCALLY` everywhere; now replaced with measured integers.
5. **Named modal inventory:** planning mentioned a few examples; local measure finds **28** named modals — see `05-named-modal-mapping.md`.
6. **PHP integration surface** includes `Flux::toast` and `Flux::modals()->close()` in addition to named modal show/close.
7. **`docs/planning/HANDOFF-CODEX.md`** was referenced in the agent brief but **does not exist** in upstream or the planning source folder; claim/handoff used GitHub issue #214 + this planning set.

## Not invented

No Free/Pro classification was asserted from vendor source. No counts were estimated when a scan returned zero.

