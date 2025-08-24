<?php

return [

    'photo_resize' => 2048,

    'photo_thumb_resize' => 1000,

    'personal_team_storage_limit' => 10737418240, // 10GB in bytes

    'personal_team_monthly_contract_limit' => 5,

    'admin_emails' => env('PICSTOME_ADMIN_EMAILS') ? explode(',', env('PICSTOME_ADMIN_EMAILS')) : [],

    'disk' => 's3',

    // Number of days before expiration to send gallery reminder
    'gallery_expiration_reminder_days' => env('GALLERY_EXPIRATION_REMINDER_DAYS', 3),

];
