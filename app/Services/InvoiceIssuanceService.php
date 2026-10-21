<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceEvent;
use App\Models\InvoiceSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Issues an invoice: assigns the number, seals the fingerprint chain and
 * freezes the document.
 */
class InvoiceIssuanceService
{
    public function __construct(
        private readonly InvoiceNumberingService $numbering,
        private readonly InvoiceFingerprintService $fingerprints,
        private readonly InvoicePlanLimitService $limits,
        private readonly InvoicePdfService $pdf,
    ) {}

    /**
     * Issue a draft invoice.
     *
     * The number is assigned at this moment, so drafts never consume a
     * number and never leave a gap in the sequence.
     */
    public function issue(Invoice $invoice, ?User $user = null): Invoice
    {
        $settings = $this->settings($invoice);

        $this->guard($invoice, $settings);

        try {
            return DB::transaction(function () use ($invoice, $user) {
                /** @var InvoiceSetting $locked */
                $locked = InvoiceSetting::where('team_id', $invoice->team_id)->lockForUpdate()->firstOrFail();

                $year = (int) $invoice->issue_date->year;
                $series = $invoice->isRectificative() ? $locked->rectification_series : $locked->series;

                $original = $invoice->isRectificative() ? $invoice->rectifiesInvoice()->first() : null;

                if ($original) {
                    $invoice->rectified_amount = round((float) $invoice->total - (float) $original->total, 2);
                }

                $number = $this->numbering->nextNumber($locked, $series, $year);
                $previousFingerprint = $locked->sequenceState($series, $year)['last_fingerprint'];

                $invoice->series = $series;
                $invoice->year = $year;
                $invoice->number = $number;

                if (blank($invoice->public_token)) {
                    $invoice->public_token = $this->pdf->newPublicToken();
                }

                $fingerprint = $this->fingerprints->fingerprintFor($invoice, $previousFingerprint);

                $invoice->fingerprint = $fingerprint;
                $invoice->previous_fingerprint = $previousFingerprint;

                $this->storePdf($invoice);

                $invoice->status = Invoice::STATUS_ISSUED;
                $invoice->issued_at = now();

                $invoice->save();

                $locked->rememberSequence($series, $year, $number, $fingerprint);
                $locked->save();

                $invoice->recordEvent(InvoiceEvent::ISSUED, $user, [
                    'number' => $invoice->full_number,
                    'fingerprint' => $fingerprint,
                    'total' => (float) $invoice->total,
                    'currency' => $invoice->currency,
                ]);

                $this->markOriginalAsRectified($invoice, $original, $user);

                return $invoice;
            });
        } catch (\Throwable $exception) {
            if (filled($invoice->pdf_path)) {
                Storage::disk(config('picstome.disk'))->delete($invoice->pdf_path);
            }

            throw $exception;
        }
    }

    /**
     * Render and store the PDF of the invoice.
     *
     * @throws ValidationException
     */
    private function storePdf(Invoice $invoice): void
    {
        $contents = $this->pdf->render($invoice);

        if (! $invoice->team->canStoreFile(strlen($contents))) {
            throw ValidationException::withMessages([
                'invoice' => __('There is not enough storage space to store the PDF of this invoice.'),
            ]);
        }

        $stored = $this->pdf->store($invoice, $contents);

        $invoice->pdf_path = $stored['path'];
        $invoice->pdf_size = $stored['size'];
        $invoice->pdf_hash = $stored['hash'];
        $invoice->pdf_generated_at = now();
    }

    /**
     * Settings of the team, created with the defaults when missing.
     */
    public function settings(Invoice $invoice): InvoiceSetting
    {
        return InvoiceSetting::firstOrCreate(
            ['team_id' => $invoice->team_id],
            []
        );
    }

    /**
     * The original invoice becomes rectified once the rectificative is issued.
     */
    private function markOriginalAsRectified(Invoice $invoice, ?Invoice $original, ?User $user): void
    {
        if (! $original) {
            return;
        }

        $original->status = Invoice::STATUS_RECTIFIED;
        $original->save();

        $original->recordEvent(InvoiceEvent::RECTIFIED, $user, [
            'rectified_by' => $invoice->full_number,
            'rectified_by_id' => $invoice->id,
            'reason' => $invoice->rectification_reason,
        ]);
    }

    /**
     * Check the invoice can be issued.
     *
     * @throws ValidationException
     */
    private function guard(Invoice $invoice, InvoiceSetting $settings): void
    {
        if (! $invoice->isDraft()) {
            throw ValidationException::withMessages([
                'invoice' => __('Only draft invoices can be issued.'),
            ]);
        }

        if (blank($invoice->issue_date)) {
            throw ValidationException::withMessages([
                'issue_date' => __('The issue date is required.'),
            ]);
        }

        if (! $settings->hasCompleteIssuerData()) {
            throw ValidationException::withMessages([
                'invoice' => __('Complete your fiscal data in the invoicing settings before issuing an invoice.'),
            ]);
        }

        if (! $invoice->isRectificative() && ! $this->limits->canIssue($invoice->team)) {
            throw ValidationException::withMessages([
                'invoice' => __('You have reached the monthly invoice limit of your plan.'),
            ]);
        }

        if ($settings->country === 'ES' && $invoice->currency !== 'EUR' && blank($invoice->exchange_rate)) {
            throw ValidationException::withMessages([
                'exchange_rate' => __('The exchange rate is required when the invoice is not in euros.'),
            ]);
        }

        if ((float) $invoice->vat_percent === 0.0 && blank($invoice->vat_exemption_reason)) {
            throw ValidationException::withMessages([
                'vat_exemption_reason' => __('Explain why the invoice has no VAT.'),
            ]);
        }

        if ($invoice->isSimplified()) {
            $this->guardSimplifiedLimit($invoice, $settings);

            return;
        }

        $customer = $invoice->invoice_data['customer'] ?? [];

        $missing = app(InvoiceService::class)->missingCustomerFields($customer);

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'customer' => __('The customer fiscal data is incomplete: :fields', [
                    'fields' => implode(', ', $missing),
                ]),
            ]);
        }
    }

    /**
     * Simplified invoices are limited by amount, converted to euros.
     *
     * @throws ValidationException
     */
    private function guardSimplifiedLimit(Invoice $invoice, InvoiceSetting $settings): void
    {
        $total = (float) $invoice->total;

        if ($invoice->currency !== 'EUR' && filled($invoice->exchange_rate)) {
            $total = round($total * (float) $invoice->exchange_rate, 2);
        }

        if ($total > (float) $settings->simplified_invoice_limit) {
            throw ValidationException::withMessages([
                'invoice' => __('A simplified invoice cannot exceed :limit euros. Issue a complete invoice instead.', [
                    'limit' => number_format((float) $settings->simplified_invoice_limit, 2, ',', '.'),
                ]),
            ]);
        }
    }
}
