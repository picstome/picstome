<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * The team used its monthly booking allowance (free plan).
 */
class BookingLimitReachedException extends RuntimeException
{
    public function __construct(string $message = 'This photographer cannot accept more reservations this month.')
    {
        parent::__construct($message);
    }
}
