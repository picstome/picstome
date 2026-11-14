# Black Friday 2026 subscriptions

This document covers deployment, verification, rollback and post-campaign
cleanup for the custom pricing page and the Black Friday 2026 campaign.

## What changed

- `/subscribe` is now the custom pricing page. It is public for guests and
  adapts to free, monthly, annual and lifetime teams.
- `/black-friday` redirects to `/subscribe`.
- Stripe Checkout sessions are created from the backend with the campaign
  coupon already applied. The Stripe Customer Portal remains the place for
  invoices, payment methods, cancellation and billing management.
- Monthly teams can schedule a monthly → annual switch at their next renewal.
  No charge is made today; the 20% coupon is applied to the first annual
  invoice.
- The sidebar account card shows the plan, storage usage, renewal date and a
  single **Subscription** button. The account menu shows
  **Subscription (Plan)**.
- New subscription columns: `billing_interval`, `current_period_end`,
  `stripe_schedule_id`, `scheduled_tier`, `scheduled_price_id`,
  `scheduled_change_at`.

## Production prerequisites

### Stripe catalog

Production must have these prices (VAT included):

| Plan | Monthly | Annual | One-time |
|---|---:|---:|---:|
| 100 GB | 7 € | 60 € | 179 € |
| 250 GB | 15 € | 120 € | 359 € |
| 1 TB | 30 € | 240 € | 699 € |

Campaign coupons:

- 100 GB monthly: 6 € off, duration once.
- 250 GB monthly: 14 € off, duration once.
- 1 TB monthly: 25 € off, duration once.
- Annual and one-time: 20% off, duration once.

The annual/one-time coupon must not have a short `redeem_by`, because scheduled
monthly → annual upgrades receive it on their next annual invoice.

Run this with the live keys before deploying:

```bash
php artisan stripe:sync-plans --dry-run
```

It lists the prices and coupons it would create or keep. Use `--force` only if
something is missing. Copy the printed `STRIPE_*_PRICE` and
`BLACK_FRIDAY_COUPON_*` values into the production environment.

### Environment

Production needs the campaign values:

```env
BLACK_FRIDAY_ENABLED=true
BLACK_FRIDAY_FORCE=false
BLACK_FRIDAY_STARTS_AT="2026-11-16 00:00:00"
BLACK_FRIDAY_ENDS_AT="2026-12-01 00:00:00"
BLACK_FRIDAY_TIMEZONE=Europe/Madrid
```

Plus the price IDs and coupon IDs printed by `stripe:sync-plans`. The values in
`.env.example` are placeholders.

### Webhook

Production already has a working webhook endpoint. No endpoint or secret change
is required. Confirm the endpoint is enabled and includes:

- `customer.subscription.created`
- `customer.subscription.updated`
- `customer.subscription.deleted`
- `invoice.payment_succeeded`
- `invoice.payment_action_required`
- `checkout.session.completed` (one-time purchases)

The devel-only Caddy bypass for `/stripe/webhook` is not part of the production
deploy.

## Deploy

1. Back up the production database and `storage/app/translations`.
2. Deploy the code.
3. Run the migrations:

   ```bash
   php artisan migrate --force
   ```

4. Backfill the renewal dates for existing subscriptions:

   ```bash
   php artisan subscriptions:sync-billing-intervals --dry-run
   php artisan subscriptions:sync-billing-intervals --force
   ```

   Subscriptions that no longer exist in Stripe are skipped. Without this step,
   existing paid teams show no renewal date until the next webhook.

5. Rebuild configuration and assets:

   ```bash
   php artisan config:cache
   npm ci
   npm run build
   ```

6. Restart queue workers.

## Verify after deploy

- `https://app.picstome.com/subscribe` returns 200 in Spanish and English.
- `https://app.picstome.com/black-friday` redirects to `/subscribe`.
- The free, monthly, annual and lifetime states show the correct card.
- A monthly team sees the 20% annual offer and can schedule the change.
- The account card shows the plan, storage and renewal date.
- The account menu shows **Subscription (Plan)**.
- Stripe Dashboard → Webhooks shows recent deliveries with a 200 response.
- For a new paid subscription, confirm the local row has `billing_interval` and
  `current_period_end`, and the sidebar shows the next renewal date.
- Confirm the Customer Portal shows invoice history and payment method settings.

Do not use test cards in production.

## Campaign end

The banner and the offer disappear automatically at
`BLACK_FRIDAY_ENDS_AT` (1 December 2026, 00:00 Europe/Madrid). The page falls
back to regular pricing. Existing subscriptions keep the discounts they already
received according to Stripe coupon duration.

After the campaign, follow the cleanup issue (#357):

- Remove the banner and campaign configuration.
- Archive campaign coupons/promotion codes in Stripe.
- Keep the custom pricing page, `billing_interval`, `current_period_end`, the
  scheduled plan change and generic billing tracking.

## Rollback

- Set `BLACK_FRIDAY_ENABLED=false` to disable the campaign without a code
  rollback. The page keeps working at regular prices.
- If the code must be rolled back, revert the deploy. The new columns are
  additive and do not need to be dropped immediately.
- Archive the campaign coupons in Stripe to stop new redemptions. Existing
  subscriptions keep their discounts.
- `/black-friday` can remain as a redirect to `/subscribe`.

## Notes

- Prices are VAT-inclusive; Stripe Checkout/Invoices show the tax breakdown.
- The app never handles card data and never calculates VAT.
- The 20% annual/one-time coupon is applied server-side only. Do not expose it
  as a public promotion code: monthly and annual prices share products.
- The monthly → annual change uses a Stripe Subscription Schedule. It does not
  charge today and applies the 20% to the first annual invoice.
