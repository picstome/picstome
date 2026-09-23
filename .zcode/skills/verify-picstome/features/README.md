# Picstome verification map

This directory is the maintained source for verifying the user-facing behavior of Picstome. Read this index before driving the app, then use the matching feature file as the recipe.

## Baseline preconditions

- The shared Herd instance answers at `https://app.picstome.com.test` and `bash .zcode/skills/verify-picstome/helpers/doctor.sh` prints `DOCTOR OK`.
- The seeded login exists: `test@example.com` / `password` (verified, team `Test User's Studio`, handle `@testuser`, unsubscribed). Never log in as `oliver@example.com` or `chema@example.com` — that is the developer's data.
- Mailpit answers at `http://127.0.0.1:8025` when a recipe needs email evidence.
- No gallery, contract, or uploaded file is already named with the run's `verify-` label.

## Driving conventions

- Drive through the browser-use harness (see the Drive section of [`../SKILL.md`](../SKILL.md)). Browser work is main-agent-only; do not delegate it to a subagent.
- Recipes use the notation `browser: <action on a stable handle>` (e.g. `browser: click button "Create gallery"`). Stable handles are visible text, input labels, element types, and URL paths — never coordinates or tab order.
- Every mutating recipe names its data `verify-<label>` and cleans it up per the Cleanup section of `../SKILL.md`.
- After any action that enqueues work (photo upload, contract execution, deletion), run `bash .zcode/skills/verify-picstome/helpers/queue-drain.sh` before asserting the side effect.
- Screenshots and other proof artifacts go to `.zcode/verify-picstome/evidence/<label>/` and survive cleanup.

## Proof and skip reporting

- Capture the user action and the resulting state, not only the final screen.
- DB-side proof: `php artisan tinker --execute="..."` output saved next to the screenshots.
- Email-side proof: `curl -s http://127.0.0.1:8025/api/v1/messages` JSON saved as a file.
- External boundaries (Stripe checkout, real S3 beyond the `picstome-test` bucket) are never crossed; record the boundary redirect as the proof.
- Report a skipped entry point with the attempted handle and the unmet precondition. Do not report a skipped entry point as verified through a different path.

## Feature entry contract

Each feature file starts with an H1 title and one paragraph describing the user-visible behavior. It then uses exactly four H2 sections in this order.

1. `Sub-features` lists short IDs with one line for each behavior.
2. `How to get to it (user POV)` lists every user entry point.
3. `Driving it with browser-use` starts with `Preconditions:` and uses labeled bullets that pair each user action with an exact handle and an observable result.
4. `Gotchas` lists traps that can waste or invalidate a verification run.

## Features

- [Log in and out](./auth.md) covers login success/failure, guest redirect, intended redirect, and logout.
- [Manage galleries](./galleries.md) covers creating a gallery, uploading media, sharing it, reading the share link, and deleting it.
- [Visit a shared gallery](./shared-gallery-visitor.md) covers the client-facing share URL, password unlock, and the download boundary.
- [Contracts and signatures](./contracts.md) covers creating a contract, signing it at the public signer URL, execution, PDF, and the executed emails.
- [Client link](./clients.md) covers the per-customer public gallery index at `/clients/{ulid}` and the copy-link modal on the customer page.
- [Public profile](./public-profile.md) covers the `@handle` page, portfolio, and pay links up to the Stripe boundary.
