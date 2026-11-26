<?php

namespace App\Livewire\Forms\Concerns;

/**
 * Money fields are typed by hand, and a Spanish keyboard produces "60,00".
 * Normalise before validating so the decimal comma is not a validation error.
 */
trait NormalizesMoneyInput
{
    protected function normalizeDecimalInput(string $value): string
    {
        $value = str_replace(' ', '', trim($value));

        if (str_contains($value, ',')) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        }

        return $value;
    }
}
