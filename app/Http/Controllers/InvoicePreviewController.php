<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\InvoiceSetting;
use App\Services\InvoiceNumberingService;
use App\Services\InvoicePdfService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Renders the invoice document as HTML for the pre-issuance preview.
 *
 * The preview is never stored and does not generate a PDF: the PDF is
 * rendered once, only when the invoice is issued.
 */
class InvoicePreviewController extends Controller
{
    public function __invoke(
        Request $request,
        Invoice $invoice,
        InvoicePdfService $pdf,
        InvoiceNumberingService $numbering,
    ): Response {
        Gate::authorize('view', $invoice);

        if ($invoice->isDraft()) {
            $invoice->setAttribute('full_number', $this->previewNumber($request, $invoice, $numbering));
        }

        return response($pdf->renderHtml($invoice), 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => 'no-store, private',
            'X-Robots-Tag' => 'noindex',
        ]);
    }

    /**
     * Number shown on the preview.
     *
     * It is display-only: drafts never store a number until they are issued,
     * so the model is not saved with it.
     */
    private function previewNumber(Request $request, Invoice $invoice, InvoiceNumberingService $numbering): string
    {
        $number = mb_substr($request->string('number')->trim()->toString(), 0, 100);

        if ($number !== '') {
            return $number;
        }

        $settings = InvoiceSetting::firstOrCreate(
            ['team_id' => $invoice->team_id],
            config('invoices.defaults')
        );

        $series = $invoice->isRectificative() ? $settings->rectification_series : $settings->series;
        $issueDate = $invoice->getAttribute('issue_date');
        $year = $issueDate instanceof \DateTimeInterface ? (int) $issueDate->format('Y') : now()->year;

        return $numbering->suggestFullNumber($settings, $series, $year, $invoice->id);
    }
}
