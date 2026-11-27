<?php

namespace App\Services;

use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Renders and stores the PDF of an invoice.
 *
 * The PDF is generated once, when the invoice is issued, and stored on the
 * team disk. It is never regenerated, so the document a client received
 * always matches the data of the invoice.
 */
class InvoicePdfService
{
    /**
     * Store a rendered invoice on the team disk.
     *
     * @return array{path: string, size: int, hash: string}
     */
    public function store(Invoice $invoice, string $contents): array
    {
        $path = "{$invoice->storage_path}/{$invoice->pdf_file_name}";

        Storage::disk(config('picstome.disk'))->put($path, $contents);

        return [
            'path' => $path,
            'size' => strlen($contents),
            'hash' => hash('sha256', $contents),
        ];
    }

    /**
     * Render the invoice PDF.
     */
    public function render(Invoice $invoice): string
    {
        return $this->withInvoiceLocale($invoice, fn (): string => Pdf::setOption([
            'isRemoteEnabled' => false,
            'isHtml5ParserEnabled' => true,
            'defaultFont' => 'DejaVu Sans',
        ])->loadView('pdf.invoice', $this->viewData($invoice))->output());
    }

    /**
     * Render the invoice document as HTML, so a draft can be previewed
     * before it is issued. The preview never stores a PDF.
     */
    public function renderHtml(Invoice $invoice): string
    {
        return $this->withInvoiceLocale(
            $invoice,
            fn (): string => view('pdf.invoice', $this->viewData($invoice))->render(),
        );
    }

    /**
     * Data shared by the PDF and the HTML preview.
     *
     * @return array<string, mixed>
     */
    private function viewData(Invoice $invoice): array
    {
        return [
            'invoice' => $invoice,
            'issuer' => $invoice->invoice_data['issuer'] ?? [],
            'customer' => $invoice->invoice_data['customer'] ?? [],
            'lineItems' => $invoice->invoice_data['line_items'] ?? [],
            'paymentTerms' => $invoice->invoice_data['payment_terms'] ?? null,
            'notes' => $invoice->invoice_data['notes'] ?? null,
            'accentColor' => $this->accentColor($invoice),
            'logo' => $this->logo($invoice),
            'publicUrl' => $invoice->public_token ? route('invoices.public.show', $invoice->public_token) : null,
        ];
    }

    /**
     * Run a rendering callback with the language of the team.
     *
     * @param  callable(): string  $callback
     */
    private function withInvoiceLocale(Invoice $invoice, callable $callback): string
    {
        $previousLocale = app()->getLocale();

        app()->setLocale($invoice->team->owner?->language ?? config('app.locale'));

        try {
            return $callback();
        } finally {
            app()->setLocale($previousLocale);
        }
    }

    /**
     * Accent color of the team branding.
     */
    public function accentColor(Invoice $invoice): string
    {
        $color = $invoice->team->brand_color;

        return config('invoices.colors.'.$color) ?? config('invoices.fallback_color');
    }

    /**
     * Team logo as a data URI, so the PDF never depends on a remote request.
     */
    public function logo(Invoice $invoice): ?string
    {
        $path = $invoice->team->brand_logo_path;

        if (blank($path)) {
            return null;
        }

        $disk = Storage::disk(config('picstome.disk'));

        if (! $disk->exists($path)) {
            return null;
        }

        $contents = $disk->get($path);

        if ($contents === null || $contents === '') {
            return null;
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = match ($extension) {
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            default => 'image/jpeg',
        };

        return 'data:'.$mime.';base64,'.base64_encode($contents);
    }

    /**
     * Random token used on the public verification URL.
     */
    public function newPublicToken(): string
    {
        return Str::random(40);
    }
}
