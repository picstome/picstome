# Invoicing

This document describes how invoices work inside Picstome: the data model, the
numbering, the fingerprint chain, the PDF, the public verification page, the
rectification flow and the retention rules.

The feature is the first phase of the roadmap described in #165 and replaces
the external `picstome.com/invoice-generator/` tool (#238).

## Legal notice

Picstome is **not** a certified invoicing system. Until the software is
homologated:

- The invoices never print «VERIFACTU» nor «Factura verificable en la sede
  electrónica de la AEAT».
- The QR code points to the Picstome verification page and is labelled
  «Picstome verification». It is not the official «QR tributario» of the
  Agencia Tributaria.
- The verification offered by Picstome is its own: a fingerprint chain plus a
  public page. The link and the label are prepared to be replaced by the
  official ones when the homologation arrives.

References used by this feature:

- **RD 1619/2012** — Reglamento por el que se regulan las obligaciones de
  facturación. Article 4 (simplified invoices, up to 400 € and up to 3.000 € for
  photography studios), article 15 (corrective invoices, issued as soon as the
  error is known and never more than four years after the tax accrual) and
  article 12 (the VAT amount must be expressed in euros).
- **RD 1007/2023** and **Orden HAC/1177/2024** — requirements of the invoicing
  systems (SIF) and the official QR. Not implemented yet.

## Data model

| Table | Purpose |
|---|---|
| `invoice_settings` | One row per team: issuer fiscal data, tax defaults, currency, series and the numbering state. |
| `invoices` | The invoices, with their amounts, the snapshot of the issuer and the client, the fingerprint and the stored PDF. |
| `invoice_events` | Append only audit trail of every relevant operation. |

`customers` gains the fiscal fields used to issue a complete invoice:
`legal_name`, `tax_id`, `address`, `city`, `state`, `postal_code` and
`country`. The `tax_id` is unique per team.

### Snapshot

Every invoice stores a copy of the issuer and the client data in
`invoice_data`, together with the line items, the payment terms, the notes and
the locale. Editing the client or the settings later never changes an invoice
that has already been issued.

## Numbering

Invoices are numbered by **series + exercise + consecutive number**, for
example `F-2026-0001`.

- The number is assigned when the invoice is **issued**. Drafts never consume a
  number, so there are no gaps.
- The state of the numbering lives in `invoice_settings.invoice_sequence_state`,
  with one entry per series and exercise:

```json
{
    "F": { "2026": { "last_number": 12, "last_fingerprint": "…" } },
    "R": { "2026": { "last_number": 3, "last_fingerprint": "…" } }
}
```

- The row of `invoice_settings` is locked with `lockForUpdate()` while the
  invoice is issued, so two simultaneous emissions never get the same number.
  The unique index `(team_id, series, year, number)` is the second barrier.
- The state is only a cache: the highest number already stored for the series
  is always taken into account, so a lost or corrupted state can never reuse a
  number.
- Numbers are never reused. A rectified invoice keeps its own number.
- The corrective invoices use their own series (`R` by default), so they have
  their own sequence and their own chain.

## Fingerprint chain

Each issued invoice stores a SHA-256 fingerprint of its canonical content,
chained with the fingerprint of the previous invoice of the same series and
exercise:

```
fingerprint = sha256(canonical_json + previous_fingerprint)
```

The canonical payload only contains immutable data: team, series, exercise,
number, invoice type, issue date, currency, exchange rate, the issuer and
client snapshots, the line items, the amounts and the reference to the
rectified invoice. The status, the public token and the PDF path are never part
of it.

The public page recomputes the whole chain of the series, which detects any
modification of a stored invoice. The last fingerprint kept in the numbering
state also detects a truncated chain, when the last invoice of a series is
removed from the database.

## Inalterability

The `Invoice` model is the first barrier:

- An issued or rectified invoice cannot be modified. Only the rectification
  status, the revocation of the public link and the stored PDF metadata can be
  written.
- Only draft invoices can be deleted.

The fingerprint chain and the stored PDF hash are the second barrier, at the
data level.

## Amounts

The discount is applied **before** the taxes:

```
subtotal   = Σ (quantity × price)
discount   = percentage | fixed, limited to the subtotal
base       = subtotal − discount
VAT        = base × vat%
total      = base + VAT
IRPF       = base × irpf%   (only when the withholding is applied)
amount due = total − IRPF
```

Amounts are calculated in cents and rounded half up to two decimals. The
invoice shows the amount the client pays. The net amount before VAT
(`base − IRPF`) is only shown on screen.

## Complete and simplified invoices

- A **complete invoice** requires the legal name, the tax ID and the address
  of the client, and the fiscal data of the issuer.
- A **simplified invoice** does not identify the recipient and is limited by
  amount: 400 € in general and up to 3.000 € for photography studios (article
  4 of RD 1619/2012). The limit is configured in the invoice settings and is
  checked in euros, converting the amount with the exchange rate when the
  invoice is not in euros.

## Currency

The currency is configured in the invoice settings and copied to the invoice
when it is issued. The fiscal logic of this phase is the Spanish one, so when
the issuer is Spanish and the invoice is not in euros:

- The exchange rate and its date are required.
- `vat_amount_eur` and `irpf_amount_eur` store the taxes in euros, as required
  by article 12 of RD 1619/2012.

## PDF

The PDF is rendered once, when the invoice is issued, and stored on the team
disk (`teams/{team}/invoices/{ulid}/{number}.pdf`). It is never regenerated,
so the document a client received always matches the data of the invoice.

- `pdf_path`, `pdf_size` and `pdf_hash` are stored with the invoice.
- The size of the PDF counts in the storage usage of the team
  (`StorageUsageService`). When the team has no space left, the invoice is not
  issued.
- The PDF contains the logo of the team as a data URI, the accent color of the
  branding, the QR code, the public link and the fingerprint.

## Public verification

Every issued invoice gets a public link `/i/{token}` with a random token of 40
characters. The link can be revoked, and the page and the PDF return a 404
afterwards. The public pages are not indexed (`X-Robots-Tag`) and do not leak
the link through the referrer (`Referrer-Policy: no-referrer`).

The public page shows the data of the invoice, the breakdown of the VAT and
the IRPF, the fingerprint, the position in the chain, the state and the
corrective invoice when there is one.

## Rectification

An issued invoice is never voided: it is **rectified** (article 15 of RD
1619/2012).

- The rectification creates a new invoice that references the original, with
  the same client and line items and a mandatory reason.
- The corrective invoice is issued in the rectification series and continues
  its own chain. The original keeps its number, its PDF and its chain, and
  moves to the `rectified` state.
- The original and the corrective invoice are linked in both the app and the
  public page.
- Corrective invoices never consume the monthly plan limit, so a team can
  always correct an invoice it already issued.
- The deadline is four years since the tax accrual. The app warns when the
  original invoice belongs to a previous exercise.

## Audit trail

`invoice_events` is append only: the model forbids updating and deleting the
rows. The recorded events are `created`, `updated`, `issued`, `rectified`,
`pdf_downloaded`, `public_viewed`, `public_pdf_downloaded` and `link_revoked`.

## Retention

The invoices and their PDFs are kept as long as the team account is active.
Deleting the account is a destructive operation: it removes the invoices, the
audit trail and the stored PDFs, so the app warns before doing it.

Spanish law requires keeping the invoices for at least four years, and the
VeriFactu and SII deadlines reach six years. Teams that need to keep their
invoices for longer must keep the account open or export them before closing
it.

## Roadmap

- **Phase 2** — SII, Facturae 3.2+, XAdES, FACe and the official verification
  on the AEAT website.
- **Phase 3** — Compliance panel, retries, alerts and certificate management.
