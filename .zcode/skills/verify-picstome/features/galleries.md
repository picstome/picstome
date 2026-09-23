# Manage galleries

A photographer creates a gallery from the Galleries page, uploads photos, videos, and PDFs into it, turns on client sharing with per-share options, copies the public share link, and can delete the gallery when done.

## Sub-features

- `gallery-create` creates a named gallery and lands on its page.
- `gallery-upload` uploads media through the Add media modal: images get thumbnail tiles after queue processing; PDFs render as document-icon tiles immediately; unsupported extensions are rejected client-side.
- `gallery-share` enables sharing with the share options and reveals the share link.
- `gallery-delete` removes the gallery and its photos.

## How to get to it (user POV)

- Sidebar → `Galleries` (`/galleries`) → `Create gallery` button (also offered in the empty state).
- Open a gallery card from the grid, then `Add media`, `Share`, or the `⋯` dropdown (`Download`, `Edit`, `Make public`/`Make private`, `Delete`).

## Driving it with browser-use

Preconditions:

- Logged in as `test@example.com` (see [auth.md](./auth.md)).
- Fixtures on disk: a **real, decodable** JPEG named `verify-<label>.jpg` (generate with PHP GD — `php -r 'imagejpeg(imagecreatetruecolor(64,64), "path.jpg", 80);'`; a hand-crafted minimal JPEG fails Imagick in `ProcessPhoto` and lands in `failed_jobs`), a tiny valid PDF (`%PDF-` magic) named `verify-<label>.pdf`, and a `verify-<label>.exe` for the rejection step.
- No existing gallery named `verify-<label>`: `php artisan tinker --execute="dump(App\Models\Gallery::where('name','verify-<label>')->exists());"`.

- **Create gallery.** `browser: goto /galleries`, `browser: click button "Create gallery"`. A dialog opens containing the input labeled `Gallery name`; the expiration date is prefilled about a month out (unsubscribed teams cannot clear it; the created row's `expiration_date` ≈ +1 month proves it server-side). `browser: fill input labeled "Gallery name" = verify-<label>`, `browser: click button "Save"` inside the dialog. The browser lands on `/galleries/{id}` with the heading `verify-<label>` and the empty state `No photos`. Save `01-gallery-created.png`.
- **Create side effect.** `php artisan tinker --execute="dump(App\Models\Gallery::where('name','verify-<label>')->first(['id','team_id','is_shared'])->toArray());"` shows a row owned by team `Test User's Studio`. Save the output.
- **Upload media.** On the gallery page, `browser: click button "Add media"`; the file input's accept list spans `.jpg,.jpeg,.png,.tiff,.mp4,.webm,.ogg,…raw extensions,.pdf` (config `picstome.upload_extensions`). Inject all three fixtures at once (see the Drive section of `../SKILL.md` for the DataTransfer technique — the file-chooser API is unsupported here). Expect: an `Unsupported file type` callout naming the `.exe`, and upload rows with progress for the JPEG and PDF; the dialog closes itself when both are `completed`. Run `bash .zcode/skills/verify-picstome/helpers/queue-drain.sh` (`ProcessPhoto` is queued for the JPEG; the PDF is marked `skipped` without processing). Reload: the header reads `2 photos` (PDFs count), the JPEG renders a thumbnail tile (CDN-backed), the PDF a document-icon tile with its filename. Save `02-media-uploaded.png`.
- **Upload side effects.** `php artisan tinker --execute="dump(App\Models\Photo::where('name','like','verify-<label>%')->get(['name','path','status'])->toArray());"` shows S3 paths for both; statuses `processed` (JPEG) and `skipped` (PDF). Save the output.
- **Open PDF.** Click the PDF tile → the detail page shows name, size, and an `Open PDF` link to `/galleries/{gallery}/photos/{photo}/pdf` serving `200 application/pdf` `inline; filename=...`. Save headers as evidence.
- **Duplicate name.** Re-inject the same JPEG in a fresh Add media session: the row errors with `A photo with this name already exists.` and no third row is created (assert `Photo::where('gallery_id', …)->count()` is unchanged).
- **Share gallery.** `browser: click button "Share"`. The `Share gallery` dialog shows `<ui-switch>` toggles: `Watermark photos`, `Visitors can download photos`, `Visitors can select photos` (+ limit); `Protect with a password`, `Add description`, `Enable photo comments` are disabled for the unsubscribed seeded team behind an `Unlock more sharing features` callout. Toggling `Watermark photos` on force-disables `Visitors can download photos` (mutual exclusivity — assert it). With downloads on, `browser: click button "Save"` in the dialog. The `Gallery shared` dialog appears with a readonly input labeled `Share URL`; record its value (shape: `https://app.picstome.com.test/shares/{ulid}/{slug}`) and save `03-share-link.png`. A lime `Sharing` badge now shows next to the gallery heading.
- **Share side effect.** `php artisan tinker --execute="dump(App\Models\Gallery::where('name','verify-<label>')->first(['is_shared','is_share_downloadable'])->toArray());"` shows `is_shared => true`.
- **Delete gallery.** `browser: click the ⋯ (ellipsis) dropdown button` (a `ui-dropdown` in the header), then `browser: click menuitem "Delete"`. The action pops a native `confirm()` which this browser backend cannot complete (the Livewire action is lost even after accepting) — so for cleanup use the tinker cascade: `App\Models\Gallery::where('name','verify-<label>')->first()->deletePhotos()->delete();` followed by `queue-drain.sh`, and assert the count is `0`. The browser steps still prove the menu and confirm render.

## Gotchas

- Playwright locator clicks stall on Flux buttons in this app (actionability never settles); switch to a page-context synthetic click or `form.requestSubmit()` when a click times out — see the Drive section of `../SKILL.md`.
- Flux switches are `<ui-switch>` custom elements (no checkbox/role) — toggle them via their `[data-flux-field]` + `[data-flux-label]` container; `dialog[open]` is unreliable (the Add media dialog renders with `open=false` while visible), so locate dialogs by text.
- `wire:confirm` deletes cannot be completed through the browser backend: the native confirm blocks the page thread and the action is lost after accepting. Assert the menu/confirm render, then clean up via the tinker cascade.
- Photos are stored on the S3-compatible **test** bucket `picstome-test` (hardcoded `picstome.disk`), not the local disk — keep uploads tiny and `verify-`-named, and delete through the app so `DeleteFromDisk` jobs clean the bucket.
- Without `queue-drain.sh`, image uploads stay `pending`: the thumbnail tile never settles even though the row exists. PDF tiles appear without processing (`status => skipped`); the drain only matters for images and for ending the tile's pending-poll.
- Upload validation beyond the duplicate-name rule: `This file type is not supported.` (server) / `Unsupported file type` callout (client pre-filter), `The file name contains invalid characters.`, and `This file is not a valid PDF.` (magic-byte check — the PDF fixture must start with `%PDF-`).
- PDFs never get thumbnails; `Set as Cover` is hidden for them in the grid tile's ⋯ menu but is (inconsistently) still present on the fullscreen detail menu — known product quirk, not a verifier mistake.
- The share options `Protect with a password`, `Add description`, `Enable photo comments` are paywalled (disabled without a subscription). Asserting them as broken on the seeded team would be a false finding.
- Expiration dates: unsubscribed teams must keep one (defaulted to +1 month); public galleries cannot expire.
