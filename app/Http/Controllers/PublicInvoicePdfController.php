<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\InvoiceEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Downloads the PDF of an invoice from its public verification link.
 */
class PublicInvoicePdfController extends Controller
{
    public function __invoke(Request $request, string $token)
    {
        $invoice = Invoice::query()
            ->where('public_token', $token)
            ->whereNull('public_token_revoked_at')
            ->first();

        abort_if(
            ! $invoice || ! in_array($invoice->status, [Invoice::STATUS_ISSUED, Invoice::STATUS_RECTIFIED], true),
            404
        );

        abort_if(blank($invoice->pdf_path), 404);

        $invoice->recordEvent(InvoiceEvent::PUBLIC_PDF_DOWNLOADED);

        return Storage::disk(config('picstome.disk'))
            ->download($invoice->pdf_path, $invoice->pdf_file_name);
    }
}
