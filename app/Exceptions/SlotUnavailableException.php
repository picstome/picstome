<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * The requested slot is no longer free: another booking, a calendar block, a
 * guard rule or a concurrent hold took it between the render and the click.
 */
class SlotUnavailableException extends RuntimeException
{
    public function __construct(string $message = 'The selected time is no longer available.')
    {
        parent::__construct($message);
    }
}
