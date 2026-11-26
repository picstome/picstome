<?php

namespace App\Notifications\Concerns;

/**
 * Shared formatting for the booking emails: bold labels keep the summary easy
 * to scan, which the plain lines did not.
 */
trait FormatsBookingEmail
{
    protected function row(string $label, string $value): string
    {
        return '**'.$label.':** '.$value;
    }
}
