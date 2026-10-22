<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceEvent;
use App\Models\InvoiceSetting;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Creates a draft invoice from a payment, so a photographer can settle the
 * invoice of a payment with a couple of clicks.
 *
 * The invoice is always a draft: it is reviewed and issued by the user. The
 * type is chosen automatically, a complete invoice when the client has its
 * fiscal data and a simplified one when it only has a name and an email.
 */
class InvoiceFromPaymentService
{
    public function __construct(
        private readonly InvoiceService $snapshots,
        private readonly InvoiceTotalsService $totals,
    ) {}

    /**
     * @throws ValidationException
     */
    public function createFromPayment(Payment $payment, ?User $user = null): Invoice
    {
        if ($payment->invoice) {
            throw ValidationException::withMessages([
                'payment' => __('This payment is already invoiced.'),
            ]);
        }

        $team = $payment->team;

        $settings = $team->invoiceSetting ?? InvoiceSetting::firstOrCreate(
            ['team_id' => $team->id],
            config('invoices.defaults')
        );

        $customer = $this->customer($payment);
        $customerSnapshot = $this->snapshots->customerSnapshot($customer);

        // The payment is the amount the client charged, taxes included, so
        // it becomes the total of the invoice and the line holds the net
        // amount the VAT is calculated from.
        $gross = $payment->amount / 100;
        $vatPercent = (float) $settings->vat_percent;
        $net = $vatPercent > 0 ? $gross / (1 + $vatPercent / 100) : $gross;

        $lineItems = [[
            'id' => (string) Str::uuid(),
            'description' => $payment->description,
            'quantity' => 1,
            'unit_price' => round($net, 2),
        ]];

        $applyIrpf = (bool) $settings->apply_irpf;

        $totals = $this->totals->calculate(
            $lineItems,
            null,
            0,
            $vatPercent,
            (float) $settings->irpf_percent,
            $applyIrpf,
        );

        $invoice = $team->invoices()->create([
            'customer_id' => $customer?->id,
            'invoice_type' => $this->snapshots->missingCustomerFields($customerSnapshot) === []
                ? Invoice::TYPE_FULL
                : Invoice::TYPE_SIMPLIFIED,
            'issue_date' => now()->toDateString(),
            'due_date' => null,
            'currency' => strtoupper($payment->currency ?: $settings->currency),
            'vat_percent' => $settings->vat_percent,
            'vat_amount' => $totals['vat_amount'],
            'irpf_percent' => $settings->irpf_percent,
            'irpf_amount' => $totals['irpf_amount'],
            'subtotal' => $totals['subtotal'],
            'base_amount' => $totals['base_amount'],
            'total' => $totals['total'],
            'amount_due' => $totals['amount_due'],
            'invoice_data' => [
                'line_items' => $lineItems,
                'issuer' => $this->snapshots->issuerSnapshot($settings),
                'customer' => $customerSnapshot,
                'payment_terms' => $settings->payment_terms,
                'notes' => $settings->notes,
                'apply_irpf' => $applyIrpf,
                'locale' => app()->getLocale(),
            ],
        ]);

        $payment->update(['invoice_id' => $invoice->id]);

        $invoice->recordEvent(InvoiceEvent::CREATED, $user, [
            'payment_id' => $payment->id,
            'payment_amount' => $payment->amount,
            'payment_currency' => $payment->currency,
            'payment_date' => $payment->completed_at?->format('Y-m-d'),
        ]);

        return $invoice;
    }

    /**
     * The client of the payment, created by the platform when it was paid.
     */
    protected function customer(Payment $payment): ?Customer
    {
        if (blank($payment->customer_email)) {
            return null;
        }

        return $payment->team->customers()->where('email', $payment->customer_email)->first();
    }
}
