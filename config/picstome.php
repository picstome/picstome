<?php

return [

    'photo_resize' => 2048,

    'photo_thumb_resize' => 1000,

    'personal_team_storage_limit' => 1073741824, // 1GB in bytes

    'subscription_storage_limit' => 1073741824000, // 1TB in bytes

    'personal_team_monthly_contract_limit' => 5,

    /**
     * Maximum allowed pixels for original size images (width * height).
     * This limit is set because wsrv.nl cannot process images larger than this.
     */
    'max_photo_pixels' => env('PICSTOME_MAX_PHOTO_PIXELS', 71000000),

    'admin_emails' => env('PICSTOME_ADMIN_EMAILS') ? explode(',', env('PICSTOME_ADMIN_EMAILS')) : [],

    'disk' => 's3',

    // Number of days before expiration to send gallery reminder
    'gallery_expiration_reminder_days' => env('GALLERY_EXPIRATION_REMINDER_DAYS', 3),

    // Subscription notification intervals (days before expiration)
    'subscription_warning_days' => [15, 7, 1],

    // Days after expiration to send deletion warning
    'subscription_expired_warning_days' => 1,

    // Grace period in days after expiration before deleting data
    'subscription_grace_period_days' => 7,

    'stripe_commission_percent' => env('STRIPE_COMMISSION_PERCENT', 1),
];
