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
 *
 * The internal series/year/number keep driving the fingerprint chain. The
 * full number printed to the client is a separate, editable value stored in
 * `invoices.full_number`.
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

    /**
     * Last invoice generated for the series, used to suggest the next full
     * number and to show it on the form.
     */
    public function lastIssued(InvoiceSetting $settings, string $series, ?int $ignoreInvoiceId = null): ?Invoice
    {
        return Invoice::query()
            ->where('team_id', $settings->team_id)
            ->where('series', $series)
            ->whereIn('status', [Invoice::STATUS_ISSUED, Invoice::STATUS_RECTIFIED])
            ->when($ignoreInvoiceId, fn ($query) => $query->whereKeyNot($ignoreInvoiceId))
            ->latest('issued_at')
            ->latest('id')
            ->first();
    }

    /**
     * Suggest the next full number from the last invoice of the series.
     *
     * The trailing number is incremented preserving its padding. When the
     * last invoice belongs to another exercise, its year is replaced too:
     * F-2026-0042 becomes F-2027-0043.
     */
    public function suggestFullNumber(InvoiceSetting $settings, string $series, int $year, ?int $ignoreInvoiceId = null): string
    {
        $last = $this->lastIssued($settings, $series, $ignoreInvoiceId);

        if (! $last) {
            return $this->defaultFullNumber($settings, $series, $year);
        }

        $candidate = $this->incrementFullNumber((string) $last->full_number, (int) $last->year, $year);

        if ($candidate === null) {
            return $this->defaultFullNumber($settings, $series, $year);
        }

        return $this->ensureUniqueFullNumber($settings, $candidate, $ignoreInvoiceId);
    }

    /**
     * Standard number used when there is no previous invoice to follow.
     */
    private function defaultFullNumber(InvoiceSetting $settings, string $series, int $year): string
    {
        $number = $this->nextNumber($settings, $series, $year);

        return sprintf('%s-%d-%s', $series, $year, str_pad((string) $number, 4, '0', STR_PAD_LEFT));
    }

    /**
     * Increase the trailing number and, when the exercise changes, update
     * the year token of the full number.
     */
    private function incrementFullNumber(string $fullNumber, int $lastYear, int $year): ?string
    {
        if (! preg_match('/(\d+)(\D*)$/u', $fullNumber, $matches)) {
            return null;
        }

        $padding = strlen($matches[1]);
        $incremented = str_pad((string) ((int) $matches[1] + 1), $padding, '0', STR_PAD_LEFT);

        $result = preg_replace(
            '/(\d+)(\D*)$/u',
            $incremented.'$2',
            $fullNumber,
            1
        );

        if ($result === null) {
            return null;
        }

        if ($lastYear !== $year) {
            $result = $this->replaceYear($result, $lastYear, $year);
        }

        return $result;
    }

    /**
     * Replace the exercise of the number, falling back to any four-digit year
     * when the invoice number uses a different year than the internal one.
     */
    private function replaceYear(string $fullNumber, int $lastYear, int $year): string
    {
        $replaced = preg_replace(
            '/\b'.preg_quote((string) $lastYear, '/').'\b/u',
            (string) $year,
            $fullNumber,
            1,
            $count
        );

        if ($replaced !== null && $count > 0) {
            return $replaced;
        }

        if (preg_match('/\b(?:19|20)\d{2}\b/u', $fullNumber, $matches)) {
            return (string) preg_replace(
                '/\b'.preg_quote($matches[0], '/').'\b/u',
                (string) $year,
                $fullNumber,
                1
            );
        }

        return $fullNumber;
    }

    /**
     * Keep the suggestion usable even when a previous lower manual number
     * makes the next suffix collide with an older invoice.
     */
    private function ensureUniqueFullNumber(InvoiceSetting $settings, string $candidate, ?int $ignoreInvoiceId = null): string
    {
        for ($attempt = 0; $attempt < 100; $attempt++) {
            if (! $this->fullNumberExists($settings, $candidate, $ignoreInvoiceId)) {
                return $candidate;
            }

            $next = $this->incrementFullNumber($candidate, (int) now()->year, (int) now()->year);

            if ($next === null) {
                return $candidate;
            }

            $candidate = $next;
        }

        return $candidate;
    }

    /**
     * Whether another invoice of the team already uses the full number.
     */
    public function fullNumberExists(InvoiceSetting $settings, string $fullNumber, ?int $ignoreInvoiceId = null): bool
    {
        return Invoice::query()
            ->where('team_id', $settings->team_id)
            ->where('full_number', $fullNumber)
            ->when($ignoreInvoiceId, fn ($query) => $query->whereKeyNot($ignoreInvoiceId))
            ->exists();
    }
}
