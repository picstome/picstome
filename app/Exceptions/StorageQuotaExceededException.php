<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a gallery write would exceed the team's storage quota.
 *
 * Callers map it to a user-facing message or an HTTP status instead of
 * matching on an exception message.
 */
class StorageQuotaExceededException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Not enough storage');
    }
}
