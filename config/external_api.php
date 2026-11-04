<?php

return [

    /**
     * Lifetime of the one-time code returned by the browser authorization
     * flow. The desktop client has five minutes to exchange it.
     */
    'code_ttl_minutes' => (int) env('EXTERNAL_CODE_TTL', 5),

    /**
     * Lifetime of the signed photo URLs returned in the manifest.
     */
    'url_ttl_minutes' => (int) env('EXTERNAL_URL_TTL', 15),

    /**
     * Lifetime of Sanctum personal access tokens issued to external clients.
     */
    'token_days' => (int) env('EXTERNAL_TOKEN_DAYS', 365),

    /**
     * Maximum multipart upload size in kilobytes (500 MB by default).
     */
    'max_upload_kb' => (int) env('EXTERNAL_MAX_UPLOAD_KB', 512000),

];
