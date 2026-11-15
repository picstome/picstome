<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Analytics enabled
    |--------------------------------------------------------------------------
    |
    | Tracking is opt-in per environment. It stays off in local, testing and
    | devel so no events ever reach Google from a non-production environment.
    |
    */

    'enabled' => (bool) env('ANALYTICS_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Google Analytics 4
    |--------------------------------------------------------------------------
    |
    | The web stream is shared by picstome.com and app.picstome.com. The API
    | secret is only used by the server-side purchase backup (Measurement
    | Protocol) when a browser blocks the client-side event.
    |
    */

    'ga4' => [
        'measurement_id' => env('GA4_MEASUREMENT_ID'),
        'api_secret' => env('GA4_API_SECRET'),
    ],

    /*
    |--------------------------------------------------------------------------
    | First-party cookies
    |--------------------------------------------------------------------------
    |
    | WordPress writes both cookies on the .picstome.com domain. The app only
    | reads them; it never overwrites the original campaign.
    |
    */

    'attribution_cookie' => 'ps_attr',

    'consent_cookie' => 'ps_consent',

    'attribution_param' => 'ps_aid',

    /*
    |--------------------------------------------------------------------------
    | Consent fallback
    |--------------------------------------------------------------------------
    |
    | ps_attr is only written after analytics consent, so its presence can be
    | used as a fallback signal until WordPress writes ps_consent.
    |
    */

    'treat_attribution_cookie_as_consent' => (bool) env('ANALYTICS_TREAT_ATTR_AS_CONSENT', false),

];
