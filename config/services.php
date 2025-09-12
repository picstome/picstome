<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'stripe' => [
        'en_pricing_table_id' => env('STRIPE_EN_PRICING_TABLE_ID'),
        'es_pricing_table_id' => env('STRIPE_ES_PRICING_TABLE_ID'),
    ],

    'acumbamail' => [
        'auth_token' => env('ACUMBAMAIL_AUTH_TOKEN'),
        'list_id' => env('ACUMBAMAIL_LIST_ID'),
        'list_id_es' => env('ACUMBAMAIL_LIST_ID_ES'),
    ],

];
