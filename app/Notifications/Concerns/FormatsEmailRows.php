<?php

namespace App\Notifications\Concerns;

/**
 * Shared bold "Label: value" lines for transactional emails.
 */
trait FormatsEmailRows
{
    protected function row(string $label, string $value): string
    {
        return '**'.$label.':** '.$value;
    }
}
