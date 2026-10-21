<?php

namespace App\Traits;

use Illuminate\Support\Number;

/**
 * Formats monetary amounts with the current locale.
 */
trait FormatsMoney
{
    protected function money(mixed $value): string
    {
        return Number::format((float) $value, 2, null, app()->getLocale());
    }
}
