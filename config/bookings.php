<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Checkout holds
    |--------------------------------------------------------------------------
    |
    | A slot is reserved while the client pays. If the payment never arrives,
    | the hold expires and the slot is released. Bizum is confirmed by the
    | photographer, so it gets a longer window.
    |
    */

    'hold_minutes' => (int) env('PICSTOME_BOOKINGS_HOLD_MINUTES', 15),

    'bizum_expiry_hours' => (int) env('PICSTOME_BOOKINGS_BIZUM_EXPIRY_HOURS', 24),

    /*
    |--------------------------------------------------------------------------
    | Geocoding
    |--------------------------------------------------------------------------
    |
    | Photon (OpenStreetMap) is free and needs no API key. The photographer
    | types a place and the app resolves its coordinates, so the sunset
    | recommendation works without asking anyone to find a latitude by hand.
    |
    */

    'geocoding_url' => env('PICSTOME_GEOCODING_URL', 'https://photon.komoot.io/api/'),

    'geocoding_reverse_url' => env('PICSTOME_GEOCODING_REVERSE_URL', 'https://photon.komoot.io/reverse'),

    /*
    |--------------------------------------------------------------------------
    | Defaults for a new booking configuration
    |--------------------------------------------------------------------------
    |
    | Used when a team opens the booking settings for the first time. The
    | photographer can change every value afterwards. Deposit values are
    | stored as a whole percent or as an amount in minor units depending on
    | the deposit type.
    |
    */

    'defaults' => [
        'timezone' => 'Europe/Madrid',
        'slot_step_minutes' => 30,
        'buffer_minutes' => 15,
        'min_notice_hours' => 24,
        'horizon_days' => 90,
        'max_sessions_per_day' => null,
        'default_deposit_type' => 'none',
        'default_deposit_value' => 0,
        'auto_issue_invoices' => false,
        'reminder_first_enabled' => true,
        'reminder_first_hours' => 72,
        'reminder_second_enabled' => true,
        'reminder_second_hours' => 24,
        'card_enabled' => true,
    ],

];
