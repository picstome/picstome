---
name: verify-picstome
description: Drive the real Picstome web app (Laravel/Livewire galleries and contracts for photographers, served by Herd at https://app.picstome.com.test) in a browser to prove feature behavior end to end. Use when asked to verify, prove, or reproduce behavior in the running app, capture evidence (screenshots, DB state, emails), or confirm a feature works the way a user sees it.
---

# Verify Picstome

Picstome is a web app for photographers: team-scoped photo galleries (upload, share with clients, favorites, downloads), contracts with e-signature flows, photoshoots, customers, and public `@handle` profile pages. The primary surface is the web UI. This skill drives that UI in a real browser against the developer's Herd-served instance and captures proof.

## The instance you are driving

- **URL:** `https://app.picstome.com.test` — served by Laravel Herd. It is always up; per `AGENTS.md` never run commands to start or stop serving.
- **Login:** `test@example.com` / `password` (seeded, email-verified, owns team `Test User's Studio`, handle `@testuser`, NOT subscribed).
- **Database:** SQLite at the repo-root file `picstome` (WAL mode — the `sqlite3` CLI cannot open it read-only; always inspect through `php artisan tinker --execute` or Artisan, never raw sqlite3).
- **Queue:** `database` driver. No worker runs by default; drain queued jobs on demand with the `queue-drain.sh` helper. Photo processing, PDF generation, executed-contract emails, and disk deletions are all queued.
- **Mail:** SMTP to Mailpit at `127.0.0.1:1025`; read/clear messages via the Mailpit API at `http://127.0.0.1:8025/api/v1/messages`.
- **Files:** photos and signature images are stored on the S3-compatible **test** bucket `picstome-test` (Mega S4) behind a Bunny CDN — `config/picstome.php` hardcodes `'disk' => 's3'`. Shared-storage `public/storage` symlink is stale (points at a deleted path); the local disk is `storage/app/private`, framework-served.
- **Frontend:** Livewire 4 single-file pages at `resources/views/pages/⚡*.blade.php` using Flux components. Modals are native `<dialog>` elements (`flux:modal`); navigation uses `wire:navigate` (client-side SPA transitions — wait for the new page content, don't assume a full load).

## Isolation

There is one shared instance (Herd + one SQLite file + one test bucket) and it belongs to the developer. Do not attempt to run a second copy or double-drive it concurrently. Every verification run must:

- Create only data it can identify as its own: name galleries, contracts, and uploaded files with a `verify-` prefix.
- Delete only rows/files it created. The DB already contains real dev data (as of writing: 3 users, 1 gallery, 1 contract belonging to `oliver@example.com`'s team). Never run `migrate:fresh`, `migrate:rollback`, `db:wipe`, or delete anything not `verify-`-prefixed.
- Never kill Herd, php-fpm, or Vite processes.

## Doctor

Run this first, and again whenever anything looks off:

```bash
bash .zcode/skills/verify-picstome/helpers/doctor.sh
```

Healthy output ends with `DOCTOR OK` and prints: site HTTP 200 with the login form present, `public/build/manifest.json` exists, DB reachable with the seeded login present and verified, pending job count, and Mailpit reachable (`WARN` on Mailpit is tolerable unless you are proving email flows — it means only email evidence is unavailable).

If the Vite manifest is missing, run `npm run build` (a `ViteException` about the manifest means exactly this).

## Drive (browser-use)

The harness is the browser-use plugin through the `mcp__node_repl__js` tool. Browser work is **main-agent-only — do not delegate it to a subagent**. Every `js` call runs in a fresh kernel, so start every call with this bootstrap, then select the backend and reuse the same one for the whole run:

```js
const browserPluginRoot =
    process.env.ZCODE_PLUGIN_ROOT ?? process.env.CLAUDE_PLUGIN_ROOT;
const { join } = await import("node:path");
const { pathToFileURL } = await import("node:url");
const { setupBrowserRuntime } = await import(
    pathToFileURL(join(browserPluginRoot, "scripts", "browser-client.mjs")).href
);
await setupBrowserRuntime({ globals: globalThis });
const browser = await agent.browsers.getForUrl("https://app.picstome.com.test/");
```

In the first browser call of a session also emit `await browser.documentation()` and follow the tab/page API it returns (it is a Playwright-style surface; treat that documentation as the source of truth for method names). Recover an existing tab by listing tabs and matching URL — never reuse a tab id from memory without validating it.

Stable handles to build on (labels and text render in English for the seeded account):

- **Login page** (`/login`): `input[type="email"]`, `input[type="password"]`, button named `Log in`. Success lands on the intended page (when login followed a guest redirect) or `/` → `/dashboard` — never with `?verified=1` (that query only comes from the email-verification routes and targets `/galleries`); wrong credentials re-render with an error summary. `/dashboard` requires an email-verified user.
- **App sidebar** (after login): links `Dashboard`, `Galleries`, `Photoshoots`, `Contracts`, `Contract templates`, `Customers`, `Payments`, `Portfolio`, `Branding`, `Public profile`, plus Tools (`/tools/calculator`, `/tools/invoice-generator`).
- **Profile dropdown** (top right): menu item `Logout` (wire action, not a link).
- **Modals:** open them via their visible trigger buttons (`Create gallery`, `Create contract`, `Share`, `Add media`, `Save`). Flux modals are `<dialog>` elements; inputs inside them are addressable by label text (e.g. `Gallery name`).
- **Livewire feedback:** after clicking a `wire:` control, wait for the DOM to reflect the action (badge appears, modal opens/closes, URL changes) rather than trusting the click alone.

**Known harness friction on this app (proven 2026-09-18, re-proven and extended 2026-09-22):**

- **Playwright locator clicks stall on Flux buttons.** `click()` on Flux-rendered buttons times out in actionability even when the button is visible, enabled, and unobstructed (`force: true` does not help). When a locator click times out, do not retry it — dispatch a synthetic click in the page instead:
  - buttons/triggers: `tab.playwright.evaluate("(() => { const b = [...document.querySelectorAll('button')].find(x => x.textContent.trim() === '<visible text>'); b.click(); })()")`;
  - `wire:submit` forms: find the open `<dialog>` containing a known heading and call `form.requestSubmit()` — clicking the submit button is not enough. Livewire round trips take a moment: wait on the expected effect (`getByRole("dialog")`, `getByText(...)`, `waitForURL`), not a fixed sleep.
- **`wire:confirm` actions cannot be driven reliably in this browser backend.** Destructive actions (gallery/contract Delete) pop a native `confirm()` that blocks the page thread; accepting it via `tab.getJsDialog().accept()` frees the page but the Livewire action is silently lost (verified: gallery count unchanged after accept). For destructive cleanup use the tinker cascades in Cleanup below; treat UI Delete as a click-only assertion (menu opens, confirm appears), not a completed action.
- Flux dropdown menus and the profile dropdown work fine via synthetic clicks; the snapshot then shows `menuitem` roles (e.g. `Logout` — verified working).
- **File uploads: the Playwright file-chooser API is `capability_unsupported` in this backend.** Inject files from page context instead — build a `File` from base64, assign via `DataTransfer` to `input[type=file]`, and dispatch a bubbling `change` event; the app's Alpine uploader reacts exactly as to a real picker (verified). `evaluate()` must receive a **self-invoking** string (`(() => …)(payloadJson)`); the arrow-function-with-argument form returns `{}` silently. Upload fixtures must be real files: a hand-crafted minimal JPEG fails Imagick ("Invalid JPEG file structure: missing SOS marker") and the `ProcessPhoto` job lands in `failed_jobs` — generate JPEGs with PHP GD.
- **Flux switches are `<ui-switch>` custom elements** (no `role=switch`, no checkbox). To read/toggle one: find its `[data-flux-field]` container by its `[data-flux-label]` text, then click the `ui-switch`'s inner `button`. Verified on the share dialog (Watermark forces the download switch `disabled`).
- **`dialog[open]` is not reliable for Flux modals** — the Add media dialog renders with `open=false` while visible. Locate dialogs by `textContent` (e.g. the dialog containing "Share gallery" and "Watermark"), not the open attribute.
- **Downloads surface as `waitForEvent("download")`** whose object only exposes `path()` in this backend — copy the file from that path into the evidence directory (verified with the contract PDF).
- **wire:navigate can bounce history after programmatic clicks** (a tab was once found on an unrelated photo page mid-run). Before every action batch, assert `location.pathname` matches the expected page and re-navigate if not.

Screenshots: capture via the tab/page screenshot API into the evidence directory (below), one per meaningful state change you intend to prove.

## Evidence

Proof artifacts go in `.zcode/verify-picstome/evidence/<label>/` (create it; `<label>` names the claim, e.g. `gallery-create-share`). They are never deleted by cleanup.

Proof standards:

- Drive the real user path in the browser — never prove a feature only through `Livewire::test`, `tinker` mutations, or test-only endpoints. Those may support, not replace, browser proof.
- Capture the action and the resulting state, not just the final screen: screenshot before/after key transitions.
- Verify side effects beyond the screen:
  - DB rows: `php artisan tinker --execute="..."` (e.g. `App\Models\Gallery::where('name','like','verify-%')->get(['id','name','is_shared'])` — expect the deprecation warnings about `PDO::MYSQL_ATTR_SSL_CA` on stderr; they are noise).
  - Queued work: run `bash .zcode/skills/verify-picstome/helpers/queue-drain.sh`, then check the effect (PDF exists, emails sent, `jobs` table empty of your jobs).
  - Emails: `curl -s http://127.0.0.1:8025/api/v1/messages | jq` and save the JSON as evidence; filter by recipient/subject.
- External boundaries: Stripe is production — on `/subscribe`, `/billing-portal`, or handle pay links, assert the redirect to Stripe only and never proceed into checkout or enter card data. The `picstome-test` S3 bucket is a test bucket; small `verify-*` image uploads are acceptable, deleted through the app's own delete flows (which queue `DeleteFromDisk` — drain afterward).
- A dry-run/test mode claim must be verified by observing files, network, and DB — never by the mode's name alone.

## Cleanup

After every run (including failed attempts):

1. Log out via the profile dropdown `Logout` item (or close the browser tab/context) so no session is left behind.
2. Delete `verify-`-prefixed data with the model's own cascades via tinker — galleries: `App\Models\Gallery::where('name','like','verify-%')->get()->each(fn($g) => $g->deletePhotos()->delete());`, contracts: `$contract->deleteFromDisk()->deleteSignatures()->delete();`. UI Delete buttons sit behind `wire:confirm`, which this browser backend cannot complete (see Drive); use the UI path only when a human is watching. Then `bash .zcode/skills/verify-picstome/helpers/queue-drain.sh` so S3 deletions actually run.
3. Confirm nothing `verify-`-prefixed remains: `php artisan tinker --execute="..."` over galleries, contracts, photos.
4. Optionally clear Mailpit if you filled it: `curl -s -X DELETE http://127.0.0.1:8025/api/v1/messages` (skip if the developer may want to inspect the messages).
5. `queue-drain.sh` self-terminates (`--stop-when-empty --max-time=120`); nothing else was started, so nothing else needs stopping. Never kill Herd/PHP processes by name.
6. Verify `.zcode/verify-picstome/evidence/<label>/` still exists and contains the artifacts.

## Helpers

```bash
bash .zcode/skills/verify-picstome/helpers/doctor.sh      # read-only health check, prints DOCTOR OK when drivable
bash .zcode/skills/verify-picstome/helpers/queue-drain.sh # runs queued jobs until empty (max 120s), then exits
```

Both are safe to run any time; neither mutates app data.

## Feature map

Before driving a feature, read `features/README.md` and the file for that feature. A proof that drives only one convenient entry point is incomplete when the map lists others.
