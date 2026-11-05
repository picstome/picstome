<?php

namespace App\Services;

use App\Models\Invoice;
use Illuminate\Support\Number;

/**
 * Builds the mailto link that opens the email client of the photographer.
 *
 * Nothing is sent from the server and no attachment is generated: the client
 * receives the public invoice link and the public PDF link in the body.
 */
class InvoiceMailtoService
{
    /**
     * Mailto link for an invoice, or null when the invoice cannot be shared.
     */
    public function url(Invoice $invoice): ?string
    {
        if (! in_array($invoice->status, [Invoice::STATUS_ISSUED, Invoice::STATUS_RECTIFIED], true)) {
            return null;
        }

        if (blank($invoice->public_token) || $invoice->public_token_revoked_at !== null) {
            return null;
        }

        $data = $invoice->invoice_data ?? [];
        $customer = $data['customer'] ?? [];
        $recipient = $customer['email'] ?? null;

        if (blank($recipient)) {
            $recipient = $invoice->customer?->email;
        }

        $subject = __('Invoice :number', ['number' => $invoice->full_number]);
        $body = $this->body($invoice, $customer);

        $query = http_build_query([
            'subject' => $subject,
            'body' => $body,
        ], '', '&', PHP_QUERY_RFC3986);

        return 'mailto:'.rawurlencode((string) $recipient).'?'.$query;
    }

    /**
     * Body of the email with the data the client needs to identify and pay
     * the invoice.
     *
     * @param  array<string, mixed>  $customer
     */
    private function body(Invoice $invoice, array $customer): string
    {
        $data = $invoice->invoice_data ?? [];
        $name = $customer['legal_name'] ?? $customer['name'] ?? null;
        $total = Number::format((float) $invoice->total, 2, null, app()->getLocale()).' '.$invoice->currency;

        $lines = [
            filled($name) ? __('Hello :name,', ['name' => $name]) : __('Hello,'),
            __('Please find your invoice :number for :total.', [
                'number' => $invoice->full_number,
                'total' => $total,
            ]),
        ];

        if ($invoice->due_date) {
            $lines[] = __('Due date: :date', ['date' => $invoice->due_date->format('Y-m-d')]);
        }

        $lines[] = __('Public invoice: :url', [
            'url' => route('invoices.public.show', $invoice->public_token),
        ]);
        $lines[] = __('PDF: :url', [
            'url' => route('invoices.public.pdf', $invoice->public_token),
        ]);

        if (filled($data['payment_terms'] ?? null)) {
            $lines[] = __('Payment terms: :terms', ['terms' => $data['payment_terms']]);
        }

        if (filled($data['notes'] ?? null)) {
            $lines[] = (string) $data['notes'];
        }

        return implode("\n\n", $lines);
    }
}
