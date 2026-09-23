# Client link

Every customer has one permanent public URL (`/clients/{ulid}`) listing all of that customer's shared galleries in one place. The photographer copies it from the customer detail page; the client opens it logged-out, sees a card per shared gallery, and clicks through to each share page. Password-protected galleries stay invisible there until a password unlocks them.

## Sub-features

- `client-link-modal` copies the public `/clients/{ulid}` URL from a "Client link" modal on the customer detail page.
- `client-index` renders the client's shared galleries (cards → share pages) at `/clients/{customer:ulid}` for anonymous visitors.
- `client-empty` shows "No galleries yet" for a customer with no shared galleries.
- `client-unknown-404` 404s for an unknown ULID.
- `client-password-unlock` hides password-protected galleries behind a "Protected galleries" form whose one password unlocks every same-customer gallery sharing it for the session.

## How to get to it (user POV)

- Sidebar → `Customers` → open a customer → `Client link` button next to the name → readonly "Share URL" (`.../clients/{26-char ulid}`).
- The client receives that URL and opens it logged-out; each card links to `/shares/{gallery ulid}/{slug}`.
- Galleries appear on the client page only when they are shared AND belong to a photoshoot of this customer (`galleries.photoshoot_id → photoshoots.customer_id`). Unshared galleries never appear.

## Driving it with browser-use

Preconditions:

- Logged in as `test@example.com`.
- A customer with a shared gallery, creatable fully from the UI: `Photoshoots` → `Create photoshoot` (name `verify-<label>-ps`, customer picker defaults to "New customer", fill `Customer Name` = `verify-<label>-client`; leave email empty — blank stores as NULL) → on the photoshoot page `Create gallery` (`verify-<label>-gallery`) → `Share` → Save. Verify the linkage: `php artisan tinker --execute="dump(App\Models\Gallery::where('name','verify-<label>-gallery')->first(['photoshoot_id'])->toArray());"`.

- **Client link modal.** `browser: goto /customers/{id}`, `browser: click button "Client link"`. A dialog shows a readonly input with `https://app.picstome.com.test/clients/{ulid}`. Save `01-client-link-modal.png`. The ULID is also readable via tinker: `App\Models\Customer::where('name','verify-<label>-client')->value('ulid')`.
- **Client index.** In a logged-out tab, `browser: goto <client URL>`. The page shows the team's brand (linking to `/@handle`), the "Your galleries" heading, and one card per shared gallery (cover or placeholder, name, "N photos", date) linking to the share URL. `Powered by Picstome` renders for unsubscribed teams. Save `02-client-index.png`. Clicking a card opens `/shares/{ulid}/{slug}` without login.
- **Empty client.** Create a bare customer (`Customers` → create `verify-<label>-empty`), copy its Client link, open it logged-out: exactly "No galleries yet". Save `03-client-empty.png`.
- **Unknown ULID.** `browser: goto /clients/01AAAAAAAAAAAAAAAAAAAAAAAA` → 404.
- **Password unlock.** Skipped with precondition: gallery password protection is subscription-gated and the seeded team is unsubscribed (the switch is disabled and `share()` forces it off). If a subscribed team exists on the instance, set a share password on two galleries of the same customer, then on the client page expect: both invisible + "Protected galleries" form; wrong password → inline `These credentials do not match our records.` error, session untouched; correct password → both cards appear and the direct `/shares/...` URLs open without re-entering the password (`GalleryUnlockService` accumulates `unlocked_gallery_ulids` in the session — shared with the per-gallery unlock page).

## Gotchas

- A gallery only shows on the client page through its photoshoot: create the gallery FROM the photoshoot page (or set the photoshoot in the gallery's Edit dialog). A standalone shared gallery never appears.
- `Make public` is irrelevant here — the client page lists *shared* galleries, not public ones.
- Locked galleries are completely invisible (name included); do not expect a "locked" card.
- The customer picker in the photoshoot dialog defaults to "New customer"; an existing customer can be picked instead — its client URL immediately covers the new photoshoot's galleries.
- Cleanup must remove photoshoots and customers too: `Photoshoot::where('name','like','verify-<label>%')->delete()` and the customer rows, alongside the gallery cascade in [galleries.md](./galleries.md).
