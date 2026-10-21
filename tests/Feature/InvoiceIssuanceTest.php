<?php

use App\Models\Invoice;
use App\Models\InvoiceEvent;
use App\Models\InvoiceSetting;
use App\Models\User;
use App\Services\InvoiceIssuanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->withPersonalTeam()->create();
    $this->team = $this->user->currentTeam;
    $this->settings = InvoiceSetting::factory()->for($this->team)->create();
    $this->issuance = app(InvoiceIssuanceService::class);
});

it('issues a draft invoice', function () {
    $invoice = $this->issuance->issue(draftInvoice($this->team), $this->user);

    expect($invoice->status)->toBe(Invoice::STATUS_ISSUED)
        ->and($invoice->issued_at)->not->toBeNull()
        ->and($invoice->number)->toBe(1)
        ->and($invoice->fingerprint)->toHaveLength(64);

    $invoice->recordEvent(InvoiceEvent::ISSUED, $this->user);

    expect($invoice->events()->count())->toBe(2)
        ->and($invoice->events()->latest('id')->first()->event)->toBe(InvoiceEvent::ISSUED);
});

it('does not issue an invoice twice', function () {
    $invoice = $this->issuance->issue(draftInvoice($this->team));

    expect(fn () => $this->issuance->issue($invoice))
        ->toThrow(ValidationException::class);
});

it('requires the issuer fiscal data', function () {
    $this->settings->update(['legal_name' => null]);

    expect(fn () => $this->issuance->issue(draftInvoice($this->team)))
        ->toThrow(ValidationException::class, 'Complete your fiscal data');
});

it('requires the customer fiscal data for a complete invoice', function () {
    $invoice = draftInvoice($this->team, [
        'invoice_data' => [
            'line_items' => [],
            'issuer' => [],
            'customer' => ['legal_name' => 'Acme Studio SL'],
        ],
    ]);

    expect(fn () => $this->issuance->issue($invoice))
        ->toThrow(ValidationException::class, 'The customer fiscal data is incomplete');
});

it('issues a simplified invoice without customer fiscal data', function () {
    $invoice = $this->issuance->issue(draftInvoice($this->team, [
        'invoice_type' => Invoice::TYPE_SIMPLIFIED,
        'invoice_data' => [
            'line_items' => [],
            'issuer' => [],
            'customer' => [],
        ],
    ]));

    expect($invoice->status)->toBe(Invoice::STATUS_ISSUED);
});

it('rejects a simplified invoice above the legal limit', function () {
    $invoice = draftInvoice($this->team, [
        'invoice_type' => Invoice::TYPE_SIMPLIFIED,
        'total' => 5000,
        'base_amount' => 4132.23,
        'vat_amount' => 867.77,
        'invoice_data' => [
            'line_items' => [],
            'issuer' => [],
            'customer' => [],
        ],
    ]);

    expect(fn () => $this->issuance->issue($invoice))
        ->toThrow(ValidationException::class, 'A simplified invoice cannot exceed');
});

it('requires an exchange rate when the invoice is not in euros', function () {
    $invoice = draftInvoice($this->team, ['currency' => 'USD']);

    expect(fn () => $this->issuance->issue($invoice))
        ->toThrow(ValidationException::class, 'The exchange rate is required');

    $issued = $this->issuance->issue($invoice->fresh()->fill([
        'exchange_rate' => 0.92,
        'exchange_rate_date' => now()->toDateString(),
    ]));

    expect($issued->status)->toBe(Invoice::STATUS_ISSUED);
});

it('requires a reason when the invoice has no VAT', function () {
    $invoice = draftInvoice($this->team, [
        'vat_percent' => 0,
        'vat_amount' => 0,
        'total' => 100,
    ]);

    expect(fn () => $this->issuance->issue($invoice))
        ->toThrow(ValidationException::class, 'Explain why the invoice has no VAT');
});

it('limits free teams to one invoice per month', function () {
    config(['picstome.personal_team_monthly_invoice_limit' => 1]);

    $this->issuance->issue(draftInvoice($this->team));

    expect(fn () => $this->issuance->issue(draftInvoice($this->team)))
        ->toThrow(ValidationException::class, 'monthly invoice limit');
});

it('does not limit subscribed teams', function () {
    config(['picstome.personal_team_monthly_invoice_limit' => 1]);

    $this->team->update(['lifetime_at' => now()]);

    $this->issuance->issue(draftInvoice($this->team));
    $second = $this->issuance->issue(draftInvoice($this->team));

    expect($second->status)->toBe(Invoice::STATUS_ISSUED);
});

it('does not count rectificative invoices against the limit', function () {
    config(['picstome.personal_team_monthly_invoice_limit' => 1]);

    $original = $this->issuance->issue(draftInvoice($this->team));

    $rectificative = $this->issuance->issue(draftInvoice($this->team, [
        'rectifies_invoice_id' => $original->id,
        'rectification_type' => Invoice::RECTIFICATION_SUBSTITUTION,
        'rectification_reason' => 'Wrong client name',
    ]));

    expect($rectificative->status)->toBe(Invoice::STATUS_ISSUED)
        ->and($original->fresh()->status)->toBe(Invoice::STATUS_RECTIFIED)
        ->and($original->fresh()->rectification?->id)->toBe($rectificative->id);
});

it('marks the original invoice as rectified in the audit trail', function () {
    $original = $this->issuance->issue(draftInvoice($this->team), $this->user);

    $this->issuance->issue(draftInvoice($this->team, [
        'rectifies_invoice_id' => $original->id,
        'rectification_type' => Invoice::RECTIFICATION_SUBSTITUTION,
        'rectification_reason' => 'Wrong client name',
    ]), $this->user);

    $events = $original->fresh()->events()->pluck('event');

    expect($events)->toContain(InvoiceEvent::RECTIFIED);
});
