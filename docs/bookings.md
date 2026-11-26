# Bookings

Booking/reservation module for photographers: products, availability, public
booking pages, card and Bizum payments, invoices, reminders and agenda.

## Feature flags

| Variable | Default | Purpose |
|---|---|---|
| `PICSTOME_BOOKINGS_FEATURE_ENABLED` | `true` | Kill switch. `false` hides bookings from everyone, admins included. |
| `PICSTOME_BOOKINGS_FEATURE_ADMIN_ONLY` | `true` | Limits the feature to admin accounts while it is being tested. Non-admin users are sent from the agenda to `/bookings/coming-soon`. |
| `PICSTOME_BOOKINGS_PUBLIC_PROFILE` | `false` | Shows products on the public biolink. The direct URL (`/@handle/products`) works either way. |
| `PICSTOME_BOOKINGS_HOLD_MINUTES` | `15` | How long an unpaid card booking keeps its slot. |
| `PICSTOME_BOOKINGS_BIZUM_EXPIRY_HOURS` | `24` | How long a Bizum booking waits for the photographer. |

## Stripe webhooks

There are **two different endpoints**. Do not merge them:

| Endpoint | Secret | Stripe account | Handles |
|---|---|---|---|
| `POST /stripe/webhook` (Cashier) | `STRIPE_WEBHOOK_SECRET` | Platform | Subscriptions, lifetime purchases, invoices |
| `POST /stripe/connect/webhook` | `STRIPE_CONNECT_WEBHOOK_SECRET` | Connected accounts | Booking payments (`checkout.session.completed`) |

Booking payments are direct charges created with the `Stripe-Account` header,
so they arrive through a **Connect webhook**. Create it in the Stripe dashboard
under *Developers → Webhooks → Connected accounts* (Workbench: set **Events
from** to **Connected accounts**), subscribe it to
`checkout.session.completed` and copy its signing secret into
`STRIPE_CONNECT_WEBHOOK_SECRET`.

On devel, the Caddy basic-auth bypass must include `/stripe/connect/webhook`
as well as `/stripe/webhook`, or Stripe cannot reach the endpoint.

`STRIPE_WEBHOOK_SECRET` belongs to Cashier and already existed before bookings.
Reusing it for bookings would overwrite Cashier's route and silently stop
subscription billing updates. The regression test
`tests/Feature/StripeWebhookTest.php` asserts both routes stay separate.

The success page is only a safety net: it also confirms the booking if the
client closes the tab before Stripe calls the webhook.

## Scheduled tasks

`app/Console/ScheduledTasks.php` registers:

- `bookings:expire-pending` every five minutes: releases slots of unpaid
  bookings whose hold expired.
- `bookings:send-reminders` every 15 minutes: sends the 72 h and 24 h reminders
  toggled per photographer. Requires the scheduler to be running.

## Plan limit

Free plans use `teams.monthly_payment_limit` as a monthly allowance for public
bookings, the same counter as payment links. Only `source = public` bookings
count; manual bookings created by the photographer do not. `NULL` means
unlimited (paid and lifetime plans).

## Migrations

Booking tables are created by `2026_11_24_120000_create_booking_tables.php`;
the colour column of products by
`2026_11_25_100000_add_color_to_products_table.php`. Both have a working
`down()`. Run `php artisan migrate --no-interaction` on devel after changing
them.
