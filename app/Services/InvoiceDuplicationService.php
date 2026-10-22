<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceEvent;
use App\Models\InvoiceSetting;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Creates a new draft from an issued invoice, so recurring invoices do not
 * have to be written again.
 *
 * The copy never inherits the number, the fingerprint, the PDF or the public
 * link of the original: it is a new document that is issued from scratch.
 */
class InvoiceDuplicationService
{
    public function __construct(
        private readonly InvoiceService $snapshots,
        private readonly InvoiceTotalsService $totals,
    ) {}

    /**
     * Duplicate an issued invoice as a draft.
     *
     * @throws ValidationException
     */
    public function duplicate(Invoice $original, ?User $user = null): Invoice
    {
        if (! $original->isIssued()) {
            throw ValidationException::withMessages([
                'invoice' => __('Only issued invoices can be duplicated.'),
            ]);
        }

        $settings = $original->team->invoiceSetting
            ?? InvoiceSetting::firstOrCreate(
                ['team_id' => $original->team_id],
                config('invoices.defaults')
            );

        $data = $original->invoice_data ?? [];

        $lineItems = collect($data['line_items'] ?? [])
            ->map(fn (array $lineItem) => [...$lineItem, 'id' => (string) Str::uuid()])
            ->all();

        $applyIrpf = (bool) ($data['apply_irpf'] ?? false);

        $vatPercent = (float) ($original->vat_percent ?? 0);
        $irpfPercent = (float) ($original->irpf_percent ?? 0);
        $discountAmount = (float) ($original->discount_amount ?? 0);

        $totals = $this->totals->calculate(
            $lineItems,
            $original->discount_type,
            $discountAmount,
            $vatPercent,
            $irpfPercent,
            $applyIrpf,
        );

        // The invoice follows the currency of the team settings, like any
        // other invoice created in the app. The exchange rate is only reused
        // when the currency has not changed.
        $sameCurrency = $original->currency === $settings->currency;
        $exchangeRate = $sameCurrency ? $original->exchange_rate : null;
        $needsEuros = $settings->country === 'ES' && $settings->currency !== 'EUR';

        $draft = $original->team->invoices()->create([
            'customer_id' => $original->customer_id,
            'invoice_type' => $original->invoice_type,
            'issue_date' => now()->toDateString(),
            'due_date' => null,
            'currency' => $settings->currency,
            'exchange_rate' => $exchangeRate,
            'exchange_rate_date' => filled($exchangeRate) ? now()->toDateString() : null,
            'vat_percent' => $vatPercent,
            'vat_amount' => $totals['vat_amount'],
            'vat_amount_eur' => $needsEuros && filled($exchangeRate)
                ? round($totals['vat_amount'] * (float) $exchangeRate, 2)
                : null,
            'vat_exemption_reason' => $original->vat_exemption_reason,
            'irpf_percent' => $irpfPercent,
            'irpf_amount' => $totals['irpf_amount'],
            'irpf_amount_eur' => $needsEuros && filled($exchangeRate)
                ? round($totals['irpf_amount'] * (float) $exchangeRate, 2)
                : null,
            'discount_type' => $original->discount_type,
            'discount_amount' => $discountAmount,
            'discount_total' => $totals['discount_total'],
            'subtotal' => $totals['subtotal'],
            'base_amount' => $totals['base_amount'],
            'total' => $totals['total'],
            'amount_due' => $totals['amount_due'],
            'invoice_data' => [
                'line_items' => $lineItems,
                'issuer' => $this->snapshots->issuerSnapshot($settings),
                'customer' => $this->snapshots->customerSnapshot($original->customer),
                'payment_terms' => $data['payment_terms'] ?? null,
                'notes' => $data['notes'] ?? null,
                'apply_irpf' => $applyIrpf,
                'locale' => app()->getLocale(),
            ],
        ]);

        $draft->recordEvent(InvoiceEvent::CREATED, $user, [
            'duplicated_from' => [
                'id' => $original->id,
                'number' => $original->full_number,
                'issue_date' => $original->issue_date?->format('Y-m-d'),
            ],
        ]);

        return $draft;
    }
}
