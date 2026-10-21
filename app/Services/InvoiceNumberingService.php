<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceSetting;

/**
 * Assigns the consecutive number of an invoice.
 *
 * The numbering state lives in `invoice_settings.invoice_sequence_state`,
 * with one entry per series and exercise. The state is only a fast cache:
 * the highest number already stored for the series is always taken into
 * account so a corrupted or missing state can never reuse a number.
 */
class InvoiceNumberingService
{
    /**
     * @return array{last_number: int, last_fingerprint: ?string}
     */
    public function state(InvoiceSetting $settings, string $series, int $year): array
    {
        return $settings->sequenceState($series, $year);
    }

    /**
     * Next number for the given series and exercise.
     */
    public function nextNumber(InvoiceSetting $settings, string $series, int $year): int
    {
        $state = $settings->sequenceState($series, $year);

        $highestStored = (int) Invoice::query()
            ->where('team_id', $settings->team_id)
            ->where('series', $series)
            ->where('year', $year)
            ->max('number');

        return max($state['last_number'], $highestStored) + 1;
    }
}
