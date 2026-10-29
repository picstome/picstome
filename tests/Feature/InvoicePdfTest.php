<?php

use App\Models\InvoiceEvent;
use App\Models\InvoiceSetting;
use App\Models\User;
use App\Services\InvoiceIssuanceService;
use App\Services\InvoicePdfService;
use App\Services\StorageUsageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->withPersonalTeam()->create();
    $this->team = $this->user->currentTeam;
    $this->team->update(['monthly_invoice_limit' => 10]);
    $this->settings = InvoiceSetting::factory()->for($this->team)->create();
    $this->issuance = app(InvoiceIssuanceService::class);
});

it('stores the pdf of an issued invoice', function () {
    $invoice = $this->issuance->issue(draftInvoice($this->team));

    expect($invoice->pdf_path)->not->toBeNull()
        ->and($invoice->pdf_size)->toBeGreaterThan(0)
        ->and($invoice->pdf_hash)->toHaveLength(64)
        ->and($invoice->pdf_generated_at)->not->toBeNull();

    Storage::disk(config('picstome.disk'))->assertExists($invoice->pdf_path);

    $contents = Storage::disk(config('picstome.disk'))->get($invoice->pdf_path);

    expect($contents)->toStartWith('%PDF')
        ->and(hash('sha256', $contents))->toBe($invoice->pdf_hash);
});

it('keeps generated invoice pdfs small by subsetting embedded fonts', function () {
    $invoice = $this->issuance->issue(draftInvoice($this->team));

    expect($invoice->pdf_size)->toBeLessThan(200_000);
});

it('counts the pdf of the invoices in the storage usage', function () {
    $invoice = $this->issuance->issue(draftInvoice($this->team));

    expect(StorageUsageService::forTeam($this->team))->toBe((int) $invoice->pdf_size)
        ->and(StorageUsageService::total())->toBe((int) $invoice->pdf_size);
});

it('does not issue an invoice when there is no storage space', function () {
    $this->team->update(['custom_storage_limit' => 10]);

    expect(fn () => $this->issuance->issue(draftInvoice($this->team)))
        ->toThrow(ValidationException::class, 'not enough storage space');

    expect($this->team->invoices()->where('status', 'issued')->count())->toBe(0)
        ->and($this->team->invoices()->first()->number)->toBeNull();
});

it('downloads the pdf of an issued invoice', function () {
    $invoice = $this->issuance->issue(draftInvoice($this->team));

    $this->actingAs($this->user)
        ->get(route('invoices.pdf', $invoice))
        ->assertOk()
        ->assertDownload($invoice->pdf_file_name);

    expect($invoice->events()->pluck('event'))->toContain(InvoiceEvent::PDF_DOWNLOADED);
});

it('returns 404 when the pdf file is missing', function () {
    $invoice = $this->issuance->issue(draftInvoice($this->team));

    Storage::disk(config('picstome.disk'))->delete($invoice->pdf_path);

    $this->actingAs($this->user)
        ->get(route('invoices.pdf', $invoice))
        ->assertNotFound();

    $this->get('/i/'.$invoice->public_token.'/pdf')->assertNotFound();
});

it('embeds the team logo in the pdf', function () {
    $logo = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAFgwJ/lw0M9wAAAABJRU5ErkJggg==');

    $draft = draftInvoice($this->team->fresh());

    Storage::disk(config('picstome.disk'))->put('teams/logo.png', $logo);

    $this->team->update(['brand_logo_path' => 'teams/logo.png']);

    $pdf = app(InvoicePdfService::class);
    $invoice = $this->issuance->issue($draft);

    expect($pdf->logo($invoice))->toStartWith('data:image/png;base64,')
        ->and(Storage::disk(config('picstome.disk'))->get($invoice->pdf_path))->toStartWith('%PDF');
});

it('does not allow downloading the pdf of an invoice of another team', function () {
    $otherTeam = User::factory()->withPersonalTeam()->create()->currentTeam;

    $invoice = $this->issuance->issue(draftInvoice($otherTeam));

    $this->actingAs($this->user)
        ->get(route('invoices.pdf', $invoice))
        ->assertForbidden();
});

it('downloads the pdf from the public link', function () {
    $invoice = $this->issuance->issue(draftInvoice($this->team));

    $this->get('/i/'.$invoice->public_token.'/pdf')
        ->assertOk()
        ->assertDownload($invoice->pdf_file_name);

    expect($invoice->events()->pluck('event'))->toContain(InvoiceEvent::PUBLIC_PDF_DOWNLOADED);
});

it('does not download the pdf from a revoked link', function () {
    $invoice = $this->issuance->issue(draftInvoice($this->team));

    $invoice->update([
        'public_token' => null,
        'public_token_revoked_at' => now(),
    ]);

    $this->get('/i/'.str_repeat('c', 40).'/pdf')->assertNotFound();
});
