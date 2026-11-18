<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A browser upload was rejected by the gallery upload rules.
 *
 * The reason is a stable code so HTTP callers can respond with a meaningful
 * status (quota errors are 413, the rest are validation errors) while keeping
 * the translated message for the uploader UI.
 */
class GalleryUploadException extends RuntimeException
{
    public const UNSUPPORTED_TYPE = 'unsupported_type';

    public const INVALID_FILENAME = 'invalid_filename';

    public const INVALID_PDF = 'invalid_pdf';

    public const VERSIONS_DISABLED = 'versions_disabled';

    public const STORAGE_QUOTA = 'storage_quota';

    public const FILE_TOO_LARGE = 'file_too_large';

    public function __construct(
        public readonly string $reason,
        string $message,
    ) {
        parent::__construct($message);
    }
}
