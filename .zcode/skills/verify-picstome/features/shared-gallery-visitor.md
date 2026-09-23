# Visit a shared gallery

A photographer's client opens the public share link in a logged-out browser, sees the shared gallery (and its photos when sharing allows), unlocks a password-protected gallery when one is set, and can download photos only when the photographer enabled downloads.

## Sub-features

- `share-open` renders the shared gallery at `/shares/{ulid}/{slug}` for anonymous visitors (image tiles from the CDN; PDFs as document-icon tiles with an `Open PDF` action).
- `share-slug-redirect` forwards the bare `/shares/{ulid}` URL to the slug URL. (A regression that embedded the whole Gallery model JSON in the redirect was found on 2026-09-22 and fixed in #227 — the live proof after the fix: bare URL 302s to `/shares/{ulid}/{slug}` and resolves 200.)
- `share-password-unlock` gates a password-protected gallery behind `/shares/{ulid}/unlock`; one correct password unlocks every same-customer gallery sharing it for the session (see [clients.md](./clients.md)).
- `share-download` serves a zip of the gallery (PDFs included) when downloads are enabled. Per-photo `Open PDF` viewing is NOT gated on the download switch.
- `share-disabled` disappears (404) once sharing is stopped.

## How to get to it (user POV)

- Open the `Share URL` the photographer copied from the `Gallery shared` dialog.
- If the photographer set a password, the same URL redirects to an unlock form.

## Driving it with browser-use

Preconditions:

- A gallery named `verify-<label>` created and shared per [galleries.md](./galleries.md), with at least one processed photo, and the recorded share URL.
- A logged-out tab (fresh context or after logout).

- **Slug redirect.** `browser: goto https://app.picstome.com.test/shares/{ulid}` (bare, no slug). The URL settles on `/shares/{ulid}/{slug}` (fixed in #227; before that it 302'd to a model-JSON URL that 404'd).
- **Open shared gallery.** `browser: goto <share URL>`. The gallery name heading and photo tiles render without any login: images as thumbnail tiles, PDFs as document-icon tiles showing the filename. Save `01-share-open.png`.
- **PDF viewing is not download-gated.** With `Visitors can download photos` OFF, the page shows no Download button and `/shares/{ulid}/download` returns `401` — but the PDF detail's `Open PDF` (`/shares/{ulid}/photos/{photo}/pdf`) still returns `200 application/pdf` `inline`. Prove both sides in one state.
- **Download enabled.** With `Visitors can download photos` on, the download route is `/shares/{ulid}/download`: `curl -sIL "https://app.picstome.com.test/shares/{ulid}/download"` returns `200` with `attachment; filename="<slug>.zip"` (PDFs are inside the zip); with downloads off it returns `401`. Save headers as evidence.
- **Password unlock.** The seeded team is unsubscribed, so password protection cannot be enabled from the UI (`Protect with a password` is disabled). Do not fake a subscription row for this; record the entry point as skipped with that precondition, unless a subscribed team already exists on the instance. When exercisable: one password now unlocks ALL of that customer's galleries sharing it for the session (`GalleryUnlockService` session list), so unlocking a second gallery does not re-lock the first.
- **Stop sharing.** As the photographer (logged-in tab), open the gallery and `browser: click button "Stop sharing"`. Then the share URL and its download route both return `404`. Save `02-share-stopped.png`.
- **Cleanup.** Delete `verify-<label>` as in [galleries.md](./galleries.md) and drain the queue.

## Gotchas

- `Watermark photos` and `Visitors can download photos` are mutually exclusive in the share dialog; watermarking force-disables download.
- The download route streams a zip built on the fly; a large gallery can take a long time and is not worth driving with more than one photo.
- The slug comes from the gallery name; URLs with the wrong slug 404 — always use the recorded `Share URL` verbatim.
- Photo tiles are served through an external image CDN chosen by `PICSTOME_PHOTO_CDN_DOMAIN` — this instance uses Bunny (`picstome.b-cdn.net`; the config default is `wsrv.nl`). Tiles may lag or fail without internet; that is the CDN, not the app.
