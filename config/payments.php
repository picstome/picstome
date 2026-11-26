<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Bizum checkout window
    |--------------------------------------------------------------------------
    |
    | A payment link waiting for a Bizum transfer stays actionable for this
    | many hours. After that it is marked as expired so the pending list does
    | not grow forever. This is independent from the booking settings.
    |
    */

    'bizum_expiry_hours' => (int) env('PICSTOME_PAYMENTS_BIZUM_EXPIRY_HOURS', 48),

];
