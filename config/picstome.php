<?php

return [

    'photo_resize' => 2048,

    'photo_thumb_resize' => 1000,

    'personal_team_storage_limit' => 1073741824, // 1GB in bytes

    'admin_emails' => env('PICSTOME_ADMIN_EMAILS') ? explode(',', env('PICSTOME_ADMIN_EMAILS')) : [],

];
