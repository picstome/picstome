<?php

use App\Models\InvoiceSetting;
use App\Models\User;
use App\Services\InvoiceIssuanceService;
use App\Services\InvoiceMailtoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Invoice PDFs are written to the configured disk; without this the
    // suite writes to the real S3 bucket.
    Storage::fake(config('picstome.disk'));

    $this->user = User::factory()->withPersonalTeam()->create();
    $this->team = $this->user->currentTeam;
    $this->team->update(['monthly_invoice_limit' => 10]);
    InvoiceSetting::factory()->for($this->team)->create();
    $this->issuance = app(InvoiceIssuanceService::class);
    $this->mailto = app(InvoiceMailtoService::class);
});

it('builds a mailto link with the invoice data and links', function () {
    $draft = draftInvoice($this->team, [
        'due_date' => now()->addDays(30)->toDateString(),
    ]);

    $data = $draft->invoice_data;
    $data['customer']['email'] = 'client@example.com';
    $data['payment_terms'] = '30 days';
    $data['notes'] = 'Thank you for your business.';
    $draft->update(['invoice_data' => $data]);

    $invoice = $this->issuance->issue($draft->fresh(), null, 'INV-0042');

    $url = $this->mailto->url($invoice);

    parse_str(parse_url($url, PHP_URL_QUERY), $query);

    expect($url)->toStartWith('mailto:client%40example.com?')
        ->and($query['subject'])->toContain('INV-0042')
        ->and($query['body'])->toContain('INV-0042')
        ->and($query['body'])->toContain('121.00 EUR')
        ->and($query['body'])->toContain($invoice->due_date->format('Y-m-d'))
        ->and($query['body'])->toContain(route('invoices.public.show', $invoice->public_token))
        ->and($query['body'])->toContain(route('invoices.public.pdf', $invoice->public_token))
        ->and($query['body'])->toContain('30 days')
        ->and($query['body'])->toContain('Thank you for your business.');
});

it('does not build a link for a draft invoice', function () {
    expect($this->mailto->url(draftInvoice($this->team)))->toBeNull();
});

it('does not build a link when the public link is revoked', function () {
    $invoice = $this->issuance->issue(draftInvoice($this->team));

    $invoice->update([
        'public_token' => null,
        'public_token_revoked_at' => now(),
    ]);

    expect($this->mailto->url($invoice))->toBeNull();
});
