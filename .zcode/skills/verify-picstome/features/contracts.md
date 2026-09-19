# Contracts and signatures

A photographer creates a contract (title, location, shooting date, terms, number of signatures), sends each signer their public signing URL, and once every signer has signed, the contract auto-executes: an `Executed` badge appears, a PDF is generated, and signers receive an email copy.

## Sub-features

- `contract-create` creates a contract and lands on its page with N unsigned signature rows.
- `contract-sign` lets an anonymous signer complete the public signing form (identity fields + signature image) at `/signatures/{ulid}/sign`.
- `contract-execute` flips the contract to `Executed` automatically when the last signature lands (an `Execute` button also exists for the edge where signatures were pre-completed server-side).
- `contract-pdf` exposes a working `Download` button for the executed contract PDF.
- `contract-email` emails the executed copy to signers (observable in Mailpit).

## How to get to it (user POV)

- Sidebar → `Contracts` (`/contracts`) → `Create contract`.
- On a contract page, each unsigned row has a `Sign` button opening `/signatures/{ulid}/sign` in a new tab — that link is what gets sent to the signer.
- After execution, `Download` fetches the PDF.

## Driving it with browser-use

Preconditions:

- Logged in as `test@example.com`.
- Mailpit reachable at `http://127.0.0.1:8025`. No file is needed — the signature is drawn on a canvas pad.

- **Create contract.** `browser: goto /contracts`, `browser: click button "Create contract"`. In the dialog fill the inputs labeled `Title` = `verify-<label>`, `Description`, `Location`, the `Shooting date` date input, set `Signatures required` = `1`, and type a sentence of `Terms` into the `trix-editor` rich-text element. `browser: click button "Save"`. The browser lands on `/contracts/{id}` showing the title, a `Waiting signatures` badge, and one signature row with `Unsigned` and a `Sign` button. Save `01-contract-created.png`.
- **Create side effect.** `php artisan tinker --execute="dump(App\Models\Contract::where('title','verify-<label>')->first(['id','executed_at'])->toArray());"` — `executed_at` is null. Save the output.
- **Open signer URL.** `browser: click button "Sign"` (opens a new tab at `/signatures/{ulid}/sign`). In that tab — logged out — the page shows the contract details and a `Sign contract` button; no form is visible yet. Save `02-sign-page.png`.
- **Sign.** `browser: click button "Sign contract"`. The `Submit signature` dialog opens: select `Role` (e.g. `Client`), fill `Legal name`, `Document number`, `Nationality`, the `Birthday` date input, and `Email` = `verify-<label>-signer@example.com`. Draw on the signature pad: dispatch pointer events (down → moves → up) across the `canvas` inside the dialog; the pad converts the strokes into the signature image on stroke end. `browser: click button "Submit"`. The modal closes and the page shows a `Signed` badge.
- **Executed.** Back on the contract page (photographer tab), reload: the signature row shows `Signed` with a `View signature` link, the badge reads `Executed`, and a `Download` button has appeared. Save `03-contract-executed.png`.
- **Queued side effects.** Run `bash .zcode/skills/verify-picstome/helpers/queue-drain.sh` (PDF generation and notification emails are queued). Then:
  - `php artisan tinker --execute="dump(App\Models\Contract::where('title','verify-<label>')->first(['executed_at','pdf_file_path'])->toArray());"` — both non-null. Save the output.
  - `curl -s http://127.0.0.1:8025/api/v1/messages` contains a message to `verify-<label>-signer@example.com` about the executed contract. Save the JSON.
  - `browser: click button "Download"` — the PDF downloads; keep it as evidence `04-contract.pdf`.
- **Cleanup.** The UI `Delete contract` menu item sits behind `wire:confirm`, which this browser backend cannot complete (see `../SKILL.md` Drive). Use the tinker cascade instead: `App\Models\Contract::where('title','verify-<label>')->get()->each(fn($c) => $c->deleteFromDisk()->deleteSignatures()->delete());` then `queue-drain.sh` so S3 deletions run. Confirm the count is `0`.

## Gotchas

- Unsubscribed personal teams are limited to 5 contracts per month (`picstome.personal_team_monthly_contract_limit`); always clean up verification contracts, and treat a refusal as the paywall, not a bug.
- The signature is drawn on a canvas (signature_pad), not uploaded; with no strokes the `Submit` fails validation on the signature field — a `Clear` button appears once strokes exist.
- Signing also creates/updates a `Customer` row on the team keyed by the signer email — check `App\Models\Customer::where('email','verify-<label>-signer@example.com')` when proving that side effect, and remove it in cleanup.
- Signature images and PDFs live on the `picstome-test` S3 bucket; deleting via the UI `Delete contract` queues the disk cleanup — drain the queue or the files linger.
- Emails are queued: without `queue-drain.sh` nothing reaches Mailpit and the run must not claim `contract-email` verified.
- The `Execute` button only renders when `signaturesRemaining() === 0` and the contract is not yet executed — with a 1-signature contract the last signature auto-executes, so you may never see it.
