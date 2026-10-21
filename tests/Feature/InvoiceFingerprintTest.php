<?php

use App\Models\Invoice;
use App\Models\InvoiceSetting;
use App\Models\User;
use App\Services\InvoiceFingerprintService;
use App\Services\InvoiceIssuanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['picstome.personal_team_monthly_invoice_limit' => 100]);

    $this->user = User::factory()->withPersonalTeam()->create();
    $this->team = $this->user->currentTeam;
    $this->settings = InvoiceSetting::factory()->for($this->team)->create();
    $this->issuance = app(InvoiceIssuanceService::class);
    $this->fingerprints = app(InvoiceFingerprintService::class);
});

it('chains every invoice with the fingerprint of the previous one', function () {
    $first = $this->issuance->issue(draftInvoice($this->team));
    $second = $this->issuance->issue(draftInvoice($this->team));

    expect($first->previous_fingerprint)->toBeNull()
        ->and($second->previous_fingerprint)->toBe($first->fingerprint)
        ->and($first->fingerprint)->toHaveLength(64);
});

it('verifies a complete chain', function () {
    $this->issuance->issue(draftInvoice($this->team));
    $invoice = $this->issuance->issue(draftInvoice($this->team));

    $verification = $this->fingerprints->verify($invoice->fresh());

    expect($verification['valid'])->toBeTrue()
        ->and($verification['position'])->toBe(2)
        ->and($verification['total'])->toBe(2)
        ->and($verification['broken_at'])->toBeNull();
});

it('reports the position of the invoice in the chain', function () {
    $this->issuance->issue(draftInvoice($this->team));
    $second = $this->issuance->issue(draftInvoice($this->team));

    $verification = $this->fingerprints->verify($second->fresh());

    expect($verification['position'])->toBe(2)
        ->and($verification['total'])->toBe(2)
        ->and($verification['valid'])->toBeTrue();
});

it('detects a manipulated invoice', function () {
    $this->issuance->issue(draftInvoice($this->team));
    $invoice = $this->issuance->issue(draftInvoice($this->team));

    // The model guard is bypassed on purpose to simulate a direct database
    // manipulation.
    Invoice::withoutEvents(function () use ($invoice) {
        Invoice::whereKey($invoice->id)->update(['total' => 1]);
    });

    $verification = $this->fingerprints->verify($invoice->fresh());

    expect($verification['valid'])->toBeFalse()
        ->and($verification['broken_at'])->toBe(2);
});

it('detects a truncated chain', function () {
    $first = $this->issuance->issue(draftInvoice($this->team));
    $invoice = $this->issuance->issue(draftInvoice($this->team));

    Invoice::withoutEvents(fn () => Invoice::whereKey($invoice->id)->delete());

    $verification = $this->fingerprints->verify($first->fresh());

    expect($verification['valid'])->toBeFalse();
});

it('does not include mutable data in the fingerprint', function () {
    $invoice = $this->issuance->issue(draftInvoice($this->team));

    $payload = $this->fingerprints->canonicalPayload($invoice);

    expect($payload)->not->toHaveKey('status')
        ->and($payload)->not->toHaveKey('public_token')
        ->and($payload)->not->toHaveKey('pdf_path')
        ->and($payload['number'])->toBe(1)
        ->and($payload['line_items'])->toHaveCount(1);
});

it('verifies the chain when the database reorders the json keys', function () {
    $invoice = $this->issuance->issue(draftInvoice($this->team));

    // MySQL normalizes the objects of a JSON column and reorders their keys.
    $data = $invoice->invoice_data;

    $reordered = [];

    foreach (array_reverse(array_keys($data)) as $key) {
        $reordered[$key] = is_array($data[$key]) ? array_reverse($data[$key], true) : $data[$key];
    }

    Invoice::withoutEvents(function () use ($invoice, $reordered) {
        Invoice::whereKey($invoice->id)->update(['invoice_data' => json_encode($reordered)]);
    });

    $verification = $this->fingerprints->verify($invoice->fresh());

    expect($verification['valid'])->toBeTrue()
        ->and($verification['broken_at'])->toBeNull();
});
