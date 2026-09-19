# Public profile

Every team has a public `@handle` page (bio, public galleries, an embedded payment form) that anyone can visit logged-out, plus a portfolio listing of public galleries and direct-pay links.

## Sub-features

- `handle-page` renders the studio's public page at `/@{handle}` with its public galleries.
- `handle-portfolio` lists public galleries at `/@{handle}/portfolio`.
- `handle-pay` collects an amount + description and hands off to Stripe.

## How to get to it (user POV)

- Visit `https://app.picstome.com.test/@testuser` (the seeded team's handle).
- Follow `Portfolio` from the handle page, or visit `/@testuser/portfolio` directly.
- Open `/@testuser/pay/{amount}/{description}` from a photographer's bio link.

## Driving it with browser-use

Preconditions:

- `doctor.sh` prints `DOCTOR OK`.
- A logged-out tab.

- **Handle page.** `browser: goto https://app.picstome.com.test/@testuser`. The page title and heading read `Test User's Studio`; the payment form (amount and description inputs) renders on the guest layout. Save `01-handle-page.png`.
- **Unknown handle.** `browser: goto /@nosuchuser-verify` returns `404`.
- **Portfolio.** `browser: goto /@testuser/portfolio`. The page renders; with no public galleries it shows the empty state (galleries only appear here after `Make public` on a gallery, per [galleries.md](./galleries.md) — to prove the non-empty state, make a `verify-` gallery public first, then revisit). Save `02-portfolio.png`.
- **Pay form (Stripe boundary).** On the handle page, in the `Send a Payment to Test User's Studio` form fill the `Amount` input with `1` and `Note or Description` with `verify-<label>`, then `browser: click button "Continue to Payment"`. The app calls the Stripe API to create a checkout session and redirects away to Stripe. **Stop at the redirect**: record the destination URL as evidence and go back. Never proceed into checkout or enter card details. Save `03-pay-redirect.png`.
- **Direct pay link.** `/@testuser/pay/1/verify-<label>` auto-creates a session and redirects on load. Treat it with the same boundary: assert the redirect only. If the seeded team has no Stripe Connect account the redirect may fail — record that outcome; do not configure Stripe to force it.

## Gotchas

- The pay flow creates real Stripe checkout sessions via the team's Connect account — the boundary for verification is the outbound redirect, not a completed payment.
- Handles are lowercase (`Team::where('handle', strtolower($handle))`); `/@TestUser` 404s.
- Only galleries marked public appear on the public pages; a freshly created gallery is private by default.
- The handle page inherits the team's branding (font/colors from Branding); a visual mismatch there is branding, not breakage.
