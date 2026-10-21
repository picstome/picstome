<?php

namespace App\Services;

use App\Models\Invoice;
use Illuminate\Support\Facades\Cache;

/**
 * Builds and verifies the fingerprint chain of the invoices.
 *
 * Every issued invoice stores the hash of its own canonical content chained
 * with the fingerprint of the previous invoice of the same series and
 * exercise. Any modification of a stored invoice breaks the chain, and the
 * last fingerprint stored in the numbering state detects a truncated chain.
 */
class InvoiceFingerprintService
{
    /**
     * Canonical content of an invoice. It only contains immutable data.
     *
     * @return array<string, mixed>
     */
    public function canonicalPayload(Invoice $invoice): array
    {
        $data = $invoice->invoice_data ?? [];

        $payload = [
            'team_id' => $invoice->team_id,
            'series' => $invoice->series,
            'year' => $invoice->year,
            'number' => $invoice->number,
            'invoice_type' => $invoice->invoice_type,
            'issue_date' => $invoice->issue_date?->format('Y-m-d'),
            'currency' => $invoice->currency,
            'exchange_rate' => $invoice->exchange_rate,
            'issuer' => $data['issuer'] ?? [],
            'customer' => $data['customer'] ?? [],
            'line_items' => $data['line_items'] ?? [],
            'subtotal' => (float) $invoice->subtotal,
            'discount_type' => $invoice->discount_type,
            'discount_total' => (float) $invoice->discount_total,
            'base_amount' => (float) $invoice->base_amount,
            'vat_percent' => (float) $invoice->vat_percent,
            'vat_amount' => (float) $invoice->vat_amount,
            'irpf_percent' => (float) $invoice->irpf_percent,
            'irpf_amount' => (float) $invoice->irpf_amount,
            'total' => (float) $invoice->total,
            'amount_due' => (float) $invoice->amount_due,
            'rectifies_invoice_id' => $invoice->rectifies_invoice_id,
            'rectification_type' => $invoice->rectification_type,
        ];

        $original = $invoice->isRectificative() ? $invoice->rectifiesInvoice : null;

        if ($original) {
            $payload['rectified_invoice'] = [
                'series' => $original->series,
                'number' => $original->number,
                'issue_date' => $original->issue_date?->format('Y-m-d'),
                'fingerprint' => $original->fingerprint,
            ];
        }

        return $payload;
    }

    /**
     * Chained fingerprint of a canonical payload.
     *
     * @param  array<string, mixed>  $payload
     */
    public function compute(?string $previousFingerprint, array $payload): string
    {
        $canonical = json_encode(
            $this->canonicalize($payload),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        return hash('sha256', ($previousFingerprint ?? '').(string) $canonical);
    }

    /**
     * Sort the keys of the payload so the fingerprint never depends on the
     * order in which the data is stored.
     *
     * MySQL normalizes the objects of a JSON column, reordering their keys,
     * so the payload has to be canonicalized before hashing it.
     *
     * @param  array<mixed>  $data
     * @return array<mixed>
     */
    protected function canonicalize(array $data): array
    {
        $canonical = [];

        foreach ($data as $key => $value) {
            $canonical[$key] = is_array($value) ? $this->canonicalize($value) : $value;
        }

        if (! array_is_list($canonical)) {
            ksort($canonical);
        }

        return $canonical;
    }

    /**
     * Fingerprint of an invoice chained with the previous one.
     */
    public function fingerprintFor(Invoice $invoice, ?string $previousFingerprint): string
    {
        return $this->compute($previousFingerprint, $this->canonicalPayload($invoice));
    }

    /**
     * Verify the chain of the series and exercise of an invoice.
     *
     * The integrity of the chain is cached, because hashing every invoice of
     * the series on each page view does not scale. The position and the
     * total are always counted live, since a new invoice changes them.
     *
     * @return array{valid: bool, position: int, total: int, broken_at: ?int}
     */
    public function verify(Invoice $invoice): array
    {
        $chain = Cache::remember(
            'invoice-chain:'.$invoice->id,
            now()->addMinutes(10),
            fn () => $this->verifyChain($invoice),
        );

        $query = Invoice::query()
            ->where('team_id', $invoice->team_id)
            ->where('series', $invoice->series)
            ->where('year', $invoice->year)
            ->whereNotNull('number');

        $position = (int) (clone $query)->where('number', '<=', $invoice->number)->count();
        $total = (int) $query->count();

        return [
            'valid' => $chain['valid'],
            'position' => $position,
            'total' => $total,
            'broken_at' => $chain['broken_at'],
        ];
    }

    /**
     * Walk the chain of the series and check every fingerprint.
     *
     * @return array{valid: bool, broken_at: ?int}
     */
    protected function verifyChain(Invoice $invoice): array
    {
        $invoices = Invoice::query()
            ->where('team_id', $invoice->team_id)
            ->where('series', $invoice->series)
            ->where('year', $invoice->year)
            ->whereNotNull('number')
            ->orderBy('number')
            ->get();

        $previous = null;
        $brokenAt = null;

        foreach ($invoices as $current) {
            if ($current->previous_fingerprint !== $previous) {
                $brokenAt = (int) $current->number;

                break;
            }

            $expected = $this->fingerprintFor($current, $previous);

            if (! hash_equals($expected, (string) $current->fingerprint)) {
                $brokenAt = (int) $current->number;

                break;
            }

            $previous = $current->fingerprint;
        }

        // The last fingerprint stored in the numbering state detects a
        // truncated chain: the last invoice was removed from the database.
        $anchor = $invoice->team->invoiceSetting
            ?->sequenceState((string) $invoice->series, (int) $invoice->year)['last_fingerprint'] ?? null;

        $lastFingerprint = $invoices->last()?->fingerprint;

        $valid = $brokenAt === null && ($anchor === null || $anchor === $lastFingerprint);

        return [
            'valid' => $valid,
            'broken_at' => $brokenAt,
        ];
    }
}
