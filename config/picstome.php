<?php

return [

    'photo_resize' => 2048,

    /**
     * Fake payments mode: skips Stripe completely so the payment flow can be
     * tested locally without a connected account. Never enabled in production.
     */
    'payments_fake' => (bool) env('PAYMENTS_FAKE', false)
        && env('APP_ENV', 'production') === 'local',

    'photo_thumb_resize' => 1000,

    'photo_small_thumb_resize' => 500,

    /**
     * Presigned URL lifetime for individual photo downloads. Videos use a
     * longer TTL because they are large and may need to be resumed.
     */
    'photo_download_url_ttl_minutes' => (int) env('PICSTOME_PHOTO_DOWNLOAD_URL_TTL_MINUTES', 10),

    'video_download_url_ttl_minutes' => (int) env('PICSTOME_VIDEO_DOWNLOAD_URL_TTL_MINUTES', 360),

    'personal_team_storage_limit' => 1073741824, // 1GB in bytes

    'subscription_storage_limit' => 1073741824000, // 1TB in bytes

    /**
     * Storage usage percentage that triggers the dashboard banner and the
     * "storage almost full" email. The email is sent once per plan.
     */
    'storage_warning_percent' => 95,

    'storage_limits_by_price' => [
        // 100 GB
        ...array_fill_keys(
            array_filter(explode(',', env('STRIPE_100GB_PRICES', ''))),
            107374182400,
        ),
        // 250 GB
        ...array_fill_keys(
            array_filter(explode(',', env('STRIPE_250GB_PRICES', ''))),
            268435456000,
        ),
        // 1000 GB
        ...array_fill_keys(
            array_filter(explode(',', env('STRIPE_1000GB_PRICES', ''))),
            1073741824000,
        ),
    ],

    /**
     * Maximum number of contracts a free team can create per month.
     */
    'personal_team_monthly_contract_limit' => 1,

    /**
     * Maximum number of invoices a free team can issue per month.
     * Rectificative invoices do not consume this limit.
     */
    'personal_team_monthly_invoice_limit' => 1,

    /**
     * Maximum allowed pixels for original size images (width * height).
     * This limit is set because wsrv.nl cannot process images larger than this.
     */
    'max_photo_pixels' => env('PICSTOME_MAX_PHOTO_PIXELS', 71000000),

    'admin_emails' => env('PICSTOME_ADMIN_EMAILS') ? explode(',', env('PICSTOME_ADMIN_EMAILS')) : [],

    /**
     * File extensions accepted by the gallery upload dialog. Single source of
     * truth for the dialog's accept attribute, the uploader's client-side
     * pre-filter, and the server-side extension gate.
     *
     * @var list<string>
     */
    'upload_extensions' => ['jpg', 'jpeg', 'png', 'tiff', 'mp4', 'webm', 'ogg', 'cr2', 'cr3', 'nef', 'arw', 'dng', 'orf', 'rw2', 'pef', 'srw', 'mos', 'mrw', '3fr', 'pdf'],

    /**
     * Enable file versioning. Disabling it hides version UI and blocks new
     * versions without deleting existing history.
     */
    'file_versions_enabled' => env('PICSTOME_FILE_VERSIONS_ENABLED', true),

    /**
     * How often an open gallery page checks for newly ingested photos.
     */
    'live_gallery_poll_seconds' => (int) env('PICSTOME_LIVE_GALLERY_POLL_SECONDS', 5),

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

    // The base URL for short links (e.g., payment links)
    'short_url_domain' => env('SHORT_URL_DOMAIN'),

    /**
     * The base URL for the photo CDN. Example: 'https://wsrv.nl/' or 'https://i0.wp.com/'.
     * Switch this to change the CDN provider for image delivery and resizing.
     */
    'photo_cdn_domain' => env('PICSTOME_PHOTO_CDN_DOMAIN', 'wsrv.nl'),

    /**
     * Timeout in seconds for ExifTool operations when extracting JPG from RAW files.
     */
    'exiftool_timeout' => env('PICSTOME_EXIFTOOL_TIMEOUT', 30),

    /**
     * Live camera uploads. Cameras upload via FTP/SFTP to a SFTPGo server
     * which writes files into the storage disk under the inbox prefix.
     */
    'ftp_uploads' => [
        'host' => env('FTP_UPLOADS_HOST', 'upload.picstome.com'),
        'sftp_port' => (int) env('FTP_UPLOADS_SFTP_PORT', 2222),
        'ftp_port' => (int) env('FTP_UPLOADS_FTP_PORT', 21),

        // Disk where SFTPGo writes uploads. Defaults to the photo disk.
        'disk' => env('FTP_UPLOADS_DISK'),

        // Folder prefix under which each camera user's uploads land.
        'inbox_prefix' => env('FTP_UPLOADS_INBOX_PREFIX', 'camera-uploads'),

        // Files younger than this are skipped to avoid ingesting
        // transfers that are still in progress.
        'stability_seconds' => (int) env('FTP_UPLOADS_STABILITY_SECONDS', 60),

        // The live scan runs from an open gallery page. S3 only lists
        // completed objects, so it can use a much shorter window.
        'live_scan_stability_seconds' => (int) env('PICSTOME_LIVE_SCAN_STABILITY_SECONDS', 2),

        // SFTPGo admin API. Leave empty to disable provisioning
        // (e.g. local development without a SFTPGo server).
        'sftpgo_url' => env('SFTPGO_URL'),
        'sftpgo_api_key' => env('SFTPGO_API_KEY'),

        // Shared secret for SFTPGo upload webhooks. Leave empty to
        // disable the webhook endpoint.
        'webhook_token' => env('SFTPGO_WEBHOOK_TOKEN'),
    ],
];
