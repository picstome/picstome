<?php

namespace App\Notifications\Concerns;

/**
 * Kept for the booking emails, which now share the generic row formatter.
 */
trait FormatsBookingEmail
{
    use FormatsEmailRows;
}
