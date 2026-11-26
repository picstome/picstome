# Payment links

Payment links let a photographer request a one-off payment from a client. The
flow supports card (Stripe Connect) and, since issue #394, Bizum.

## Bizum on payment links

Bizum is configured per team and shared with bookings. Edit it from
**Payment settings** or **Bookings → Settings → Payments**; both screens write
the same team fields and the text explains that it applies to both. It is
independent from the `PICSTOME_BOOKINGS_*` flags:

- `teams.bizum_enabled`
- `teams.bizum_phone`
- `teams.bizum_instructions`

When Bizum is enabled, `/@handle/pay` stops redirecting straight to Stripe and
shows both payment methods. A team can use Bizum without Stripe onboarding;
the card button is hidden when the connected account is not ready.

The client fills in name, email and optional phone. The app creates a pending
payment with a `PAY-XXXXXXXX` reference and shows the Bizum phone and
instructions. The request is public at:

```
/@handle/pay/request/{reference}
```

The photographer confirms or cancels it from **Payments**. Confirming creates
the completed payment, the customer, the optional photoshoot and a draft
invoice through `InvoiceFromPaymentService`. Card payments also create their
draft invoice automatically on the success page.

Pending requests expire after `PICSTOME_PAYMENTS_BIZUM_EXPIRY_HOURS` (48 by
default). The scheduler task `payments:expire-pending-bizum` marks them as
expired every five minutes.


## Dated payment links

When a payment link carries a session date and time, completing the payment
also creates a `Booking` with `source = payment_link`. It appears in the agenda
for that day, blocks the slot and stays linked to the `Photoshoot`. Payments
without a date only record the payment and its invoice.

For links paid before this behaviour existed, run:

```bash
php artisan payments:backfill-link-bookings --dry-run
php artisan payments:backfill-link-bookings --force
```

## Bizum reference and phone

The reference sent with the transfer is the client first name plus the day of
the session: `Bea-27`. If that reference is already used, a suffix is added
(`Bea-27-2`). The same format is used by bookings.

The Bizum phone and reference are rendered as large values on the payment page
so the client can copy them without reading a paragraph. The public page also
shows the session date and time when the link carries them.

The "require phone" option lives on the team and is shared with bookings; it is
edited from either settings screen.

## Data

The payment row keeps the checkout state in `payments`:

- `status`: `pending_bizum`, `completed`, `cancelled` or `expired`
- `payment_method`: `card` or `bizum`
- `reference`: public Bizum reference
- `expires_at`, `metadata`, `customer_name`, `customer_phone`

Existing payments are migrated as `completed`, so revenue, plan limits and the
completed-payments table are unaffected. Pending Bizum requests do not count
as received payments until the photographer confirms them, but they do use the
monthly payment allowance while pending.

## Migrations

- `2026_11_26_120000_add_bizum_to_teams_table.php`
- `2026_11_26_120100_add_payment_link_fields_to_payments_table.php`
- `2026_11_26_120200_move_bizum_settings_from_booking_settings.php`
- `2026_11_26_120300_add_bizum_reference_to_bookings_table.php`
- `2026_11_26_120400_move_phone_required_to_teams.php`

All five are additive (the move migrations copy data and remove the duplicate
booking columns) and have a working `down()`.

## Deployment

No new Stripe configuration is needed for Bizum. Card payment links keep using
the existing Stripe Connect setup and the platform webhook only handles
subscriptions.

Run `php artisan migrate --force`, then `php artisan config:clear` if the
configuration was cached. The scheduler must be running for the expiry task.
