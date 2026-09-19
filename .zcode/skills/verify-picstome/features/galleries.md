# Manage galleries

A photographer creates a gallery from the Galleries page, uploads photos and videos into it, turns on client sharing with per-share options, copies the public share link, and can delete the gallery when done.

## Sub-features

- `gallery-create` creates a named gallery and lands on its page.
- `gallery-upload` uploads an image through the Add media modal and shows it after queue processing.
- `gallery-share` enables sharing with the share options and reveals the share link.
- `gallery-delete` removes the gallery and its photos.

## How to get to it (user POV)

- Sidebar → `Galleries` (`/galleries`) → `Create gallery` button (also offered in the empty state).
- Open a gallery card from the grid, then `Add media`, `Share`, or the `⋯` dropdown (`Download`, `Edit`, `Make public`/`Make private`, `Delete`).

## Driving it with browser-use

Preconditions:

- Logged in as `test@example.com` (see [auth.md](./auth.md)).
- A tiny JPEG named `verify-<label>.jpg` on disk for the upload step.
- No existing gallery named `verify-<label>`: `php artisan tinker --execute="dump(App\Models\Gallery::where('name','verify-<label>')->exists());"`.

- **Create gallery.** `browser: goto /galleries`, `browser: click button "Create gallery"`. A `<dialog>` opens containing the input labeled `Gallery name`; the `Expiration date` input is prefilled with a date about a month out (unsubscribed teams cannot clear it). `browser: fill input labeled "Gallery name" = verify-<label>`, `browser: click button "Save"` inside the dialog. The browser lands on `/galleries/{id}` with the heading `verify-<label>` and the empty state `No photos`. Save `01-gallery-created.png`.
- **Create side effect.** `php artisan tinker --execute="dump(App\Models\Gallery::where('name','verify-<label>')->first(['id','team_id','is_shared'])->toArray());"` shows a row owned by team `Test User's Studio`. Save the output.
- **Upload photo.** On the gallery page, `browser: click button "Add media"`, `browser: set file input (type=file, accept list starts with .jpg) = verify-<label>.jpg`. The upload row shows progress, then the dialog closes itself when every file is `completed`. Run `bash .zcode/skills/verify-picstome/helpers/queue-drain.sh` (photo processing is queued). Reload: the page shows `1 photo` and a thumbnail tile. Save `02-photo-uploaded.png`.
- **Upload side effect.** `php artisan tinker --execute="dump(App\Models\Photo::where('name','verify-<label>.jpg')->first(['path','thumb_path'])->toArray());"` shows non-null S3 paths on the `picstome-test` bucket. Save the output.
- **Share gallery.** `browser: click button "Share"`. The `Share gallery` dialog shows switches: `Watermark photos`, `Visitors can download photos`, `Visitors can select photos`; `Protect with a password`, `Add description`, `Enable photo comments` are disabled for the unsubscribed seeded team. `browser: click button "Save"` in the dialog. The `Gallery shared` dialog appears with a readonly input labeled `Share URL`; record its value (shape: `https://app.picstome.com.test/shares/{ulid}/{slug}`) and save `03-share-link.png`. A lime `Sharing` badge now shows next to the gallery heading.
- **Share side effect.** `php artisan tinker --execute="dump(App\Models\Gallery::where('name','verify-<label>')->first(['is_shared'])->toArray());"` shows `is_shared => true`.
- **Delete gallery.** `browser: click the ⋯ (ellipsis) dropdown button` (it is the first `ui-dropdown` in the header's `div.flex.gap-4`, left of the `Share`/`Stop sharing` button group), then `browser: click menuitem "Delete"`. The action pops a native `confirm()` which this browser backend cannot complete (the Livewire action is lost even after accepting) — so for cleanup use the tinker cascade: `App\Models\Gallery::where('name','verify-galleries-proof')->first()->deletePhotos()->delete();` followed by `queue-drain.sh`, and assert the count is `0`. The browser steps still prove the menu and confirm render.

## Gotchas

- Playwright locator clicks stall on Flux buttons in this app (actionability never settles); switch to a page-context synthetic click or `form.requestSubmit()` when a click times out — see the Drive section of `../SKILL.md`.
- `wire:confirm` deletes cannot be completed through the browser backend: the native confirm blocks the page thread and the action is lost after accepting. Assert the menu/confirm render, then clean up via the tinker cascade.
- Photos are stored on the S3-compatible **test** bucket `picstome-test` (hardcoded `picstome.disk`), not the local disk — keep uploads tiny and `verify-`-named, and delete through the app so `DeleteFromDisk` jobs clean the bucket.
- Without `queue-drain.sh`, uploads stay unprocessed: the tile/thumbnail never appears even though the row exists.
- Re-uploading a file with the same name fails validation with `A photo with this name already exists.`
- The share options `Protect with a password`, `Add description`, `Enable photo comments` are paywalled (disabled without a subscription). Asserting them as broken on the seeded team would be a false finding.
- Expiration dates: unsubscribed teams must keep one (defaulted to +1 month); public galleries cannot expire.
