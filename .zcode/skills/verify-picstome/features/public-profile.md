# Public profile

Every team has a public `@handle` page (branding, bio, public galleries, and a payment modal) that anyone can visit logged-out, plus a portfolio listing of public galleries and direct-pay links.

## Sub-features

- `handle-page` renders the studio's public page at `/@{handle}` with its public galleries and a `View Portfolio` link (only when at least one gallery is public).
- `handle-portfolio` lists public galleries at `/@{handle}/portfolio` (no empty-state copy when there are none).
- `handle-pay` collects an amount + description in a modal and hands off to Stripe. Requires the team to have completed Stripe Connect onboarding (`hasCompletedOnboarding()` + `show_pay_button`) — on the seeded, not-onboarded team this whole surface is absent.

## How to get to it (user POV)

- Visit `https://app.picstome.com.test/@testuser` (the seeded team's handle).
- Follow `View Portfolio` from the handle page, or visit `/@testuser/portfolio` directly.
- Open the payment modal via the credit-card avatar in the social-links row (or add `?pay=1` to the handle URL), or open `/@testuser/pay/{amount}/{description}` directly.

## Driving it with browser-use

Preconditions:

- `doctor.sh` prints `DOCTOR OK`.
- A logged-out tab.

- **Handle page.** `browser: goto https://app.picstome.com.test/@testuser`. The page shows the studio branding (`Test User's Studio`) on the guest layout. With no public galleries there is NO `View Portfolio` button and NO empty-state substitute — make a `verify-` gallery public first (per [galleries.md](./galleries.md)) to prove the button and the portfolio card in one state. Save `01-handle-page.png`.
- **Unknown handle.** `browser: goto /@nosuchuser-verify` returns `404`.
- **Handle case.** Only the handle page lowercases the handle (`strtolower` in `pages::handle.show`): `/@TESTUSER` renders, but `/@TESTUSER/portfolio` and the pay/success/cancel routes match exactly and 404 on uppercase. Assert both.
- **Portfolio.** `browser: goto /@testuser/portfolio`. With a public gallery: one card linking to the portfolio detail. With none: the page still renders (branding, social links, `Powered by`) but the grid block is simply absent — there is no "no galleries" copy. Save `02-portfolio.png`.
- **Pay modal (Stripe boundary).** On the handle page the payment form is a Flux modal (`Send a Payment to …`) opened by the credit-card avatar in the social-links row or via `?pay=1` — it is NOT an inline form on the page. Fill `Amount (in EUR)` with `1` and `Note or Description` with `verify-<label>`, then `browser: click button "Continue to Payment"`. The app calls the Stripe API to create a checkout session and redirects away to Stripe. **Stop at the redirect**: record the destination URL as evidence and go back. Never proceed into checkout or enter card details. Save `03-pay-redirect.png`.
- **Pay prerequisites.** The modal and avatar only render when the team completed Stripe onboarding (`hasCompletedOnboarding()`) and `show_pay_button` is on. The seeded team is NOT onboarded (sidebar shows `Connect with Stripe`): record `handle-pay` as skipped with that precondition — the avatar/modal is absent and the direct pay link `/@testuser/pay/1/verify-<label>` returns `404` (it aborts unless onboarding is complete). Do not configure Stripe to force it.
- **Direct pay link.** `/@testuser/pay/{amount}/{description}` (with a subscribed+onboarded team) auto-creates a session and redirects on load. Treat it with the same boundary: assert the redirect only.

## Gotchas

- The pay flow creates real Stripe checkout sessions via the team's Connect account — the boundary for verification is the outbound redirect, not a completed payment.
- Handle lowercasing applies ONLY to `/@{handle}` itself; portfolio/pay/success/cancel use exact matches, so mixed-case variants 404 there.
- Only galleries marked public appear on the public pages; a freshly created gallery is private by default. Portfolio detail pages list images only (`->filter(fn ($p) => $p->isImage())`), so a PDF-only public gallery shows a placeholder card and an image-less detail page.
- The handle page inherits the team's branding (font/colors from Branding); a visual mismatch there is branding, not breakage.
