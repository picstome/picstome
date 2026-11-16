# Analytics (Google Analytics 4)

Picstome tracks the path **picstome.com → app.picstome.com → free account →
paid plan** with GA4 only. Meta and Google Ads are out of scope for now.

## How it works

- The WordPress site writes two first-party cookies on `.picstome.com`:
  - `ps_attr`: the original campaign (first touch and last campaign touch).
  - `ps_consent`: whether the visitor accepted analytics.
- The app reads them, but never writes them. No cookie banner is shown in the
  app: visitors who did not consent on picstome.com are not tracked.
- On registration the first touch is stored on the user (`users.attr_*`) and
  kept in `users.attr_json`, including the GA4 client id.
- `/register`, `/login` and `/subscribe` load `gtag.js` only when analytics is
  enabled and consent is present.
- A verified purchase on the Stripe success page sends a `purchase` event.
  Stripe webhooks also send the same purchase through the Measurement Protocol
  as a backup; GA4 deduplicates by `transaction_id`.
- The admin report is at **Admin → Usage stats → Acquisition**.

## Stripe metadata

Every checkout carries the acquisition data so a payment can be traced back
without joining tables by hand:

- **Checkout Session** metadata: `ps_source`, `ps_medium`, `ps_campaign`,
  `ps_content`, `ps_at`, `ps_touch`, `ps_user_id` (plus the existing `tier`,
  `interval`, `coupon` and `promotion`).
- **Subscription** metadata: the same fields, via Cashier `withMetadata()`.
- **Payment** metadata:
  - lifetime purchases receive it through `payment_intent_data`;
  - subscription payments (first invoice and renewals) are stamped by the
    `invoice.paid` webhook handler.

`client_reference_id` stays as the **team id**, because the success page uses
it to verify the session. `ps_user_id` is included in the metadata for direct
user lookup.

Enable the `invoice.paid` event on the Stripe webhook endpoint. Without it,
subscription payments keep the subscription metadata but the payment itself
will not show the `ps_*` fields.

No names or emails are written to Stripe metadata; only these pseudonymous
campaign fields.

## Environment

Keep tracking off everywhere except production:

```env
ANALYTICS_ENABLED=false          # true only in production
GA4_MEASUREMENT_ID=G-PTGLC1WXYY
GA4_API_SECRET=                  # production only, from GA4 → Data streams
```

`ANALYTICS_ENABLED=false` in local, testing and `devel`. The first-party
attribution capture still works there for QA; nothing is sent to Google.

## Cookie contract

All cookies are first-party on `domain=.picstome.com`, `path=/`, `Secure`,
`SameSite=Lax`, without `HttpOnly` (JavaScript writes them).

### `ps_attr`

Written on the first visit and on every later campaign click, only after
analytics consent:

```json
{
  "v": 1,
  "first": {
    "s": "google",
    "m": "cpc",
    "c": "black-friday",
    "k": "hero",
    "l": "https://picstome.com/pricing/",
    "r": "https://www.google.com/",
    "gclid": "…",
    "fbclid": "…",
    "ts": 1700000000000
  },
  "last": { "s": "facebook", "m": "paid", "c": "retargeting", "fbclid": "…", "ts": 1700000000000 }
}
```

The app always uses `first`. The short keys (`s`, `m`, `c`, `k`, `l`, `r`,
`ts`) and the long ones (`utm_source`, `utm_medium`, `utm_campaign`,
`utm_content`, `landing`, `referrer`) are both accepted. `last` is kept in
`attr_json` for future ad-platform work.

### `ps_consent`

Written by the CMP on accept and on reject, so the app can tell the difference:

```json
{ "analytics": true, "ads": false, "ts": 1700000000000 }
```

### `ps_aid`

Fallback for browsers that block the cookie (Safari caps JavaScript cookies at
7 days). It is the base64url of the `first` object, appended to every link that
points to `https://app.picstome.com`:

```js
function b64url(value) {
  var bytes = new TextEncoder().encode(JSON.stringify(value))
  var binary = ''
  bytes.forEach(function (byte) { binary += String.fromCharCode(byte) })
  return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '')
}
```

## WordPress setup

1. Create a Google Tag Manager container and install it on picstome.com.
   Exclude `googletagmanager.com` from LiteSpeed's JS delay/combine settings.
2. Configure Consent Mode v2 with everything `denied` by default. The CMP
   (CookieYes, Complianz…) must update consent and write `ps_consent`.
3. Add a GA4 configuration tag with `G-PTGLC1WXYY`. Remove or disable the old
   Site Kit analytics tag so page views are not counted twice.
4. Add one Custom HTML tag, trigger **DOM Ready – All Pages**, and require
   `analytics_storage` consent. It writes `ps_attr` and decorates the app links:

```html
<script>
(function () {
  var KEYS = ['utm_source','utm_medium','utm_campaign','utm_content','utm_term','gclid','fbclid','msclkid'];
  var SHORT = { utm_source:'s', utm_medium:'m', utm_campaign:'c', utm_content:'k', utm_term:'t' };
  var qs = new URLSearchParams(location.search);
  var params = {};
  KEYS.forEach(function (key) { var value = qs.get(key); if (value) params[key] = value; });
  var campaign = ['utm_source','utm_medium','utm_campaign','gclid','fbclid','msclkid'].some(function (key) { return params[key]; });

  var stored = null;
  var match = document.cookie.match(/(?:^|;\s*)ps_attr=([^;]*)/);
  if (match) { try { stored = JSON.parse(decodeURIComponent(match[1])); } catch (e) {} }

  var touch = Object.assign({ ts: Date.now(), l: location.origin + location.pathname, r: document.referrer || '' }, params);
  var attr = stored && stored.v === 1 ? stored : { v: 1, first: touch };
  if (campaign) attr.last = touch;

  document.cookie = 'ps_attr=' + encodeURIComponent(JSON.stringify(attr)) +
    '; domain=.picstome.com; path=/; max-age=' + (60 * 60 * 24 * 90) + '; Secure; SameSite=Lax';

  var source = attr.first || attr.last || {};
  var aid = {};
  Object.keys(SHORT).forEach(function (key) {
    var short = SHORT[key];
    var value = source[key] || source[short];
    if (value) aid[short] = value;
  });
  ['gclid','fbclid','msclkid'].forEach(function (key) { if (source[key]) aid[key] = source[key]; });
  if (!Object.keys(aid).length) return;

  function b64url(value) {
    var bytes = new TextEncoder().encode(JSON.stringify(value));
    var binary = '';
    bytes.forEach(function (byte) { binary += String.fromCharCode(byte); });
    return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
  }

  var encoded = b64url(Object.assign(aid, { l: source.l, r: source.r, ts: source.ts }));
  document.querySelectorAll('a[href^="https://app.picstome.com"]').forEach(function (link) {
    var url = new URL(link.href);
    url.searchParams.set('ps_aid', encoded);
    link.href = url.toString();
  });
})();
</script>
```

5. All “Start free” and pricing CTAs must be real `<a href="https://app.picstome.com/register">`
   links, not JavaScript redirects.
6. Update the privacy policy so the banner covers `picstome.com` and
   `app.picstome.com`, and document GA4, the campaign cookie and Stripe.

The exact CMP callback depends on the plugin. With CookieYes, for example:

```js
function writeConsent(analytics, ads) {
  document.cookie = 'ps_consent=' + encodeURIComponent(JSON.stringify({
    analytics: !!analytics, ads: !!ads, ts: Date.now()
  })) + '; domain=.picstome.com; path=/; max-age=' + (60 * 60 * 24 * 180) + '; Secure; SameSite=Lax';
}

document.addEventListener('cookieyes_consent_update', function (event) {
  var accepted = (event.detail && event.detail.accepted) || [];
  writeConsent(accepted.indexOf('analytics') !== -1, accepted.indexOf('advertisement') !== -1);
});
```

## Testing

1. In incognito, open `https://picstome.com/?utm_source=qa&utm_medium=test&utm_campaign=prueba`.
2. Accept analytics and check DevTools → Application → Cookies → `.picstome.com`:
   `ps_attr` and `ps_consent` must exist.
3. Click “Start free”: the app URL should include `ps_aid`.
4. Register with a test email. In the database:

```sql
select attr_source, attr_medium, attr_campaign, attr_landing, attr_at
from users where email = 'qa@example.com';
```

`attr_source` must be `qa`; without UTMs and from Google, `google`/`organic`;
without UTMs and without referrer, `direct`. Logging in as an existing user
must not write anything.

5. With consent denied, `/register` must not load `googletagmanager.com`.
6. In GA4 DebugView: `page_view` on `/register`, `sign_up` after registering,
   `begin_checkout` when clicking a plan and `purchase` on the success page.
7. Admin → Usage stats → Acquisition: the signup appears under `qa` / `test`.

## Deploy and rollback

Production needs `ANALYTICS_ENABLED=true`, `GA4_MEASUREMENT_ID` and (optional)
`GA4_API_SECRET`. Run `php artisan migrate --force` and verify the new columns
and the Acquisition report.

Rollback: set `ANALYTICS_ENABLED=false` and clear config cache. This stops all
tracking immediately. `php artisan migrate:rollback --step=1` removes the
attribution columns and loses the collected data; do it only if the feature is
discarded.

## Limitations

- Consent is required; visitors who reject it are not tracked.
- Safari limits JavaScript cookies to 7 days; `ps_aid` covers the gap.
- Ad blockers may drop the client-side purchase; the Measurement Protocol job
  is the backup.
- Existing users before this release have no attribution and are excluded from
  the report.
- Meta and Google Ads are not implemented. The stored `last` touch and click
  ids are kept so they can be added later without a new migration.
