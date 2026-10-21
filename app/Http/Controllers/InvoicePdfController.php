<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\InvoiceEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

/**
 * Downloads the PDF of an invoice by the photographer who issued it.
 */
class InvoicePdfController extends Controller
{
    public function __invoke(Request $request, Invoice $invoice)
    {
        Gate::authorize('view', $invoice);

        abort_if(blank($invoice->pdf_path), 404);

        $disk = Storage::disk(config('picstome.disk'));

        abort_unless($disk->exists($invoice->pdf_path), 404);

        $invoice->recordEvent(InvoiceEvent::PDF_DOWNLOADED, $request->user());

        return $disk->download($invoice->pdf_path, $invoice->pdf_file_name);
    }
}
