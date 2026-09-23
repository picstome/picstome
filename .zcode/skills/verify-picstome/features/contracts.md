# Contracts and signatures

A photographer creates a contract (title, location, shooting date, terms, number of signatures), sends each signer their public signing URL, and once every signer has signed, the contract auto-executes: an `Executed` badge appears, a PDF is generated, and signers receive an email copy.

## Sub-features

- `contract-create` creates a contract and lands on its page with N unsigned signature rows.
- `contract-sign` lets an anonymous signer complete the public signing form (identity fields + signature image) at `/signatures/{ulid}/sign`.
- `contract-execute` flips the contract to `Executed` automatically when the last signature lands (an `Execute` button also exists for the edge where signatures were pre-completed server-side).
- `contract-pdf` exposes a working `Download` button for the executed contract PDF — the button only appears once the queued PDF job has set `pdf_file_path` (after `queue-drain.sh`).
- `contract-email` emails the executed copy to signers (observable in Mailpit).

## How to get to it (user POV)

- Sidebar → `Contracts` (`/contracts`) → `Create contract`.
- On a contract page, each unsigned row has a `Sign` button opening `/signatures/{ulid}/sign` in a new tab — that link is what gets sent to the signer.
- After execution, `Download` fetches the PDF.

## Driving it with browser-use

Preconditions:

- Logged in as `test@example.com`.
- Mailpit reachable at `http://127.0.0.1:8025`. No file is needed — the signature is drawn on a canvas pad.

- **Create contract.** `browser: goto /contracts`, `browser: click button "Create contract"`. In the dialog fill the inputs (addressable by `name`): `form.title` = `verify-<label>`, `form.description`, `form.location`, `form.shootingDate` (date), `form.signature_quantity` = `1`, and load `Terms` into the `trix-editor` (`editor.loadHTML('<div>…</div>')`). `browser: click button "Save"`. The browser lands on `/contracts/{id}` showing the title, an `N/M signatures` counter, and one signature row with `Unsigned` and a `Sign` button. The `Waiting signatures` badge lives on the `/contracts` **index** row, not the detail page (the detail page shows a badge only once `Executed`). Save `01-contract-created.png`.
- **Create side effect.** `php artisan tinker --execute="dump(App\Models\Contract::where('title','verify-<label>')->first(['id','executed_at'])->toArray());"` — `executed_at` is null. Save the output.
- **Open signer URL.** `browser: click button "Sign"` (opens a new tab at `/signatures/{ulid}/sign`). In that tab — logged out — the page shows the contract details and a `Sign contract` button; no form is visible yet. Save `02-sign-page.png`.
- **Sign.** `browser: click button "Sign contract"`. The `Submit signature` dialog opens: select `role` (e.g. `Client`), fill `legalName`, `documentNumber`, `nationality`, `birthday` (date), and `email` = `verify-<label>-signer@example.com`. Draw on the signature pad: dispatch pointer events (down → moves → up) across the `canvas` inside the dialog; a `Clear` button appearing confirms the pad captured strokes. `browser: click button "Submit"`. The modal closes and the page shows a `Signed` badge.
- **Executed, before the drain.** Back on the contract page (photographer tab), reload: the signature row shows `Signed` with a `View signature` link and the badge reads `Executed` — but the `Download` button is **absent** until `pdf_file_path` is set by the queued job. Assert its absence now, then run `bash .zcode/skills/verify-picstome/helpers/queue-drain.sh` (PDF generation and notification emails are queued). Save `03-contract-executed.png` at whichever step you prove.
- **Queued side effects.** After the drain:
  - `php artisan tinker --execute="dump(App\Models\Contract::where('title','verify-<label>')->first(['executed_at','pdf_file_path'])->toArray());"` — both non-null. Save the output.
  - `curl -s http://127.0.0.1:8025/api/v1/messages` contains a message to `verify-<label>-signer@example.com` with subject `Signed: {title}`. Save the JSON.
  - Reload the contract page: the `Download` button has appeared. `browser: click button "Download"` and capture the download event (`waitForEvent("download")`, copy from `path()`) — keep the PDF as evidence `04-contract.pdf`. The button is a `wire:click` action, not a link; `/contracts/{id}/download` does not exist as a URL.
- **Cleanup.** The UI `Delete contract` menu item sits behind `wire:confirm`, which this browser backend cannot complete (see `../SKILL.md` Drive). Use the tinker cascade instead: `App\Models\Contract::where('title','verify-<label>')->get()->each(fn($c) => $c->deleteFromDisk()->deleteSignatures()->delete());` then `queue-drain.sh` so S3 deletions run. Confirm the count is `0`.

## Gotchas

- Unsubscribed personal teams are limited to 5 contracts per month (`picstome.personal_team_monthly_contract_limit`); always clean up verification contracts, and treat a refusal as the paywall, not a bug.
- The signature is drawn on a canvas (signature_pad), not uploaded; with no strokes the `Submit` fails validation on the signature field — a `Clear` button appears once strokes exist.
- Signing also creates/updates a `Customer` row on the team. Since commit 77d2424 the resolution order is: an existing team customer with the signer email is reused; otherwise, if the contract has exactly 2 signatures and is linked to a photoshoot with a customer, signing **claims** that photoshoot customer (stamping the signer email/birthdate onto it) instead of creating a row; otherwise a new customer is created (name = legal name). With the simple 1-signature recipe above, expect one new customer keyed by the signer email with the birthdate backfilled — check `App\Models\Customer::where('email','verify-<label>-signer@example.com')` and remove it in cleanup.
- The signer `email` field is required, so the empty-email normalization (blank customer emails stored as NULL, commit 7d4c6f4) is never exercised by signing; it shows on the customers pages (a customer saved with a blank email has `email => null`, and multiple no-email customers coexist).
- Signature images and PDFs live on the `picstome-test` S3 bucket; deleting via the UI `Delete contract` queues the disk cleanup — drain the queue or the files linger.
- Emails are queued: without `queue-drain.sh` nothing reaches Mailpit and the run must not claim `contract-email` verified.
- The `Execute` button only renders when `signaturesRemaining() === 0` and the contract is not yet executed — with a 1-signature contract the last signature auto-executes, so you may never see it.
