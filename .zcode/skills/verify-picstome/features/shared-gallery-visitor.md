# Visit a shared gallery

A photographer's client opens the public share link in a logged-out browser, sees the shared gallery (and its photos when sharing allows), unlocks a password-protected gallery when one is set, and can download photos only when the photographer enabled downloads.

## Sub-features

- `share-open` renders the shared gallery at `/shares/{ulid}/{slug}` for anonymous visitors.
- `share-slug-redirect` forwards the bare `/shares/{ulid}` URL to the slug URL.
- `share-password-unlock` gates a password-protected gallery behind `/shares/{ulid}/unlock`.
- `share-download` serves a zip of the gallery when downloads are enabled.
- `share-disabled` disappears (404) once sharing is stopped.

## How to get to it (user POV)

- Open the `Share URL` the photographer copied from the `Gallery shared` dialog.
- If the photographer set a password, the same URL redirects to an unlock form.

## Driving it with browser-use

Preconditions:

- A gallery named `verify-<label>` created and shared per [galleries.md](./galleries.md), with at least one processed photo, and the recorded share URL.
- A logged-out tab (fresh context or after logout).

- **Slug redirect.** `browser: goto https://app.picstome.com.test/shares/{ulid}` (bare, no slug). The URL settles on `/shares/{ulid}/{slug}`.
- **Open shared gallery.** `browser: goto <share URL>`. The gallery name heading and photo tiles render without any login. Save `01-share-open.png`.
- **Download enabled.** With `Visitors can download photos` on, the download route is `/shares/{ulid}/download`: `curl -sIL "https://app.picstome.com.test/shares/{ulid}/download"` returns `200` with a zip attachment; with downloads off it returns `401`. Save headers as evidence.
- **Password unlock.** The seeded team is unsubscribed, so password protection cannot be enabled from the UI (`Protect with a password` is disabled). Do not fake a subscription row for this; record the entry point as skipped with that precondition, unless a subscribed team already exists on the instance.
- **Stop sharing.** As the photographer (logged-in tab), open the gallery and `browser: click button "Stop sharing"`. Then in the visitor tab, `browser: reload` the share URL: the response is `404`. Save `02-share-stopped.png`.
- **Cleanup.** Delete `verify-<label>` as in [galleries.md](./galleries.md) and drain the queue.

## Gotchas

- `Watermark photos` and `Visitors can download photos` are mutually exclusive in the share dialog; watermarking force-disables download.
- The download route streams a zip built on the fly; a large gallery can take a long time and is not worth driving with more than one photo.
- The slug comes from the gallery name; URLs with the wrong slug 404 — always use the recorded `Share URL` verbatim.
- Photo thumbnails are served through the external CDN `wsrv.nl` — tiles may lag or fail without internet; that is the CDN, not the app.
