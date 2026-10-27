<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Translation overrides
    |--------------------------------------------------------------------------
    |
    | Admin edits are written to JSON files in this directory instead of the
    | repository lang/ files. The directory is registered with the translation
    | loader after lang/, so its values win at runtime without dirtying the
    | working tree. Keep it on persistent storage and include it in backups.
    |
    */

    'path' => env('TRANSLATION_OVERRIDES_PATH', storage_path('app/translations')),

    /*
    |--------------------------------------------------------------------------
    | Editable locales
    |--------------------------------------------------------------------------
    |
    | Keep this list in sync with the language options exposed in
    | Settings > Appearance.
    |
    */

    'locales' => ['en', 'es'],

];
