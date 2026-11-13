# Plan limits and gallery retention

Free and lifetime plans keep every feature unlocked. The plan only changes
**capacity**, **retention** and **branding**.

## Plan matrix

| | Free | Lifetime | Subscription |
| --- | --- | --- | --- |
| Storage | 1 GB | Purchased amount | 100 GB – 1 TB |
| Invoices per month | 1 | Unlimited | Unlimited |
| Contracts per month | 1 | Unlimited | Unlimited |
| Payments per month | 1 | Unlimited | Unlimited |
| Private gallery expiration | 1 year from creation | 1 year from creation | None |
| Public gallery (portfolio) | Never expires | Never expires | Never expires |
| Sharing, versions, selection limit, Stripe, camera upload | Yes | Yes | Yes |
| White label (`Powered by Picstome`) | No | Yes | Yes |

Limits live on the team row (`monthly_contract_limit`,
`monthly_invoice_limit`, `monthly_payment_limit`). A `null` limit means
unlimited; the defaults come from `config/picstome.php` at registration and
are reset/cleared by the Stripe webhook when a subscription starts or ends.

## Retention

Free and lifetime galleries expire `created_at + 1 year`. The cap is anchored
to the gallery creation, not to "now", so editing the date cannot push it
forward indefinitely: teams may move the date both ways while it stays inside
the cap.

- `Team::hasUnlimitedRetention()` is the single source of truth and is
  deliberately **not** `subscribed()`: lifetime returns true from
  `subscribed()` because it keeps unlimited invoices, contracts and payments,
  but its galleries still expire.
- `GalleryForm::maxExpirationDate()` enforces the cap.
- Galleries created before this rule may already expire later than the cap.
  Their current date becomes the ceiling, so opening the edit modal and
  saving the name never forces a destructive change.
- `Gallery::makePrivate()` clamps the one-month default to the cap, so
  toggling public → private cannot reset the clock every month.
- `makePublic()` clears the date: public galleries are portfolio, not client
  delivery, and they still count against the storage quota.

When a subscription ends, `StripeEventListener::handleSubscriptionDeleted()`
gives every private gallery without a date a year from its creation, or one
month from now if that already passed, so nothing is deleted overnight.

## Reminders and deletion

- `galleries:send-expiration-reminders` runs daily at 09:00 and emails the
  owner three days before the date.
- `reminder_sent_at` is reset whenever `expiration_date` changes, so moving
  the date re-arms the reminder. Without it the owner was warned once and
  never again.
- `galleries:delete-expired` runs daily at 00:00 and deletes galleries whose
  date is more than one day in the past.

## Payments

`App\Services\PaymentPlanLimitService` counts only **completed** payments
(`payments.completed_at` inside the current month). An abandoned Stripe
checkout does not consume the quota.

The limit is enforced in four places:

- `PaymentLinkForm::generatePaymentLink()` throws a validation error.
- `pages::pay.show::mount()` renders the "cannot be processed" notice instead
  of redirecting to Stripe.
- `pages::handle.show` hides the pay button and `checkout()` refuses to run.
- `pages::payments` disables the generate button and shows the counter.

Clients see a generic message, never the photographer's billing state:
`Payment cannot be processed at the moment. Please contact us.`

## Camera uploads and the storage wall

Camera upload is available on every plan. The real wall is storage, and the
camera upload path makes it explicit instead of silent.

- `IngestFtpUpload` deletes the rejected file from the inbox. It used to stay
  on S3 forever, unbilled and invisible.
- The account is paused (`ftp_accounts.quota_paused_at`) and the inbox is
  purged, so a burst does not leave unbilled files behind.
- `FtpInboxIngestor` skips paused accounts, and the SFTPGo webhook deletes
  incoming objects while the account is paused.
- `CameraUploadsPaused` emails the owner once per pause with the number of
  discarded shots. Some tethering setups delete the card after upload, so the
  count matters.
- Uploads resume automatically when `StorageUsageService::forTeamFresh()`
  drops below the limit.
- The open photographer gallery gets the pause through
  `gallery-live-watcher` (5 s poll): it dispatches `ftp-quota-paused` and the
  gallery shows the banner. The shared client page removes the live indicator
  instead of revealing the photographer's storage state.
- `ftp:cleanup-inbox --force` runs daily at 00:35 and removes stale inbox
  files (interrupted transfers, deactivated accounts, pre-pause leftovers).
  It uses the same stability window as the ingestor so it never deletes a
  transfer in progress. Manual runs require `--dry-run` or `--force`.

## Testing locally

- `PaymentPlanLimitTest` covers the monthly quota and the client-facing
  message.
- `GalleryRetentionTest` covers the cap, the lifetime exception, the
  public → private clamp and the reminder re-arm.
- `FtpQuotaPauseTest` covers the delete-on-reject, pause, single email,
  resume, webhook and cleanup paths.
