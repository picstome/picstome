<?php

use App\Models\Invoice;
use App\Models\InvoiceSetting;
use App\Models\User;
use App\Services\InvoiceIssuanceService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->withPersonalTeam()->create();
    $this->team = $this->user->currentTeam;
    $this->team->update(['monthly_invoice_limit' => 100]);
    $this->settings = InvoiceSetting::factory()->for($this->team)->create();
    $this->issuance = app(InvoiceIssuanceService::class);
});

it('assigns consecutive numbers per series and exercise', function () {
    $first = $this->issuance->issue(draftInvoice($this->team));
    $second = $this->issuance->issue(draftInvoice($this->team));

    expect($first->number)->toBe(1)
        ->and($second->number)->toBe(2)
        ->and($first->full_number)->toBe('F-'.now()->year.'-0001')
        ->and($second->full_number)->toBe('F-'.now()->year.'-0002');
});

it('does not consume a number while the invoice is a draft', function () {
    $draft = draftInvoice($this->team);

    expect($draft->number)->toBeNull()
        ->and($draft->full_number)->toBeNull();

    $issued = $this->issuance->issue($draft);

    expect($issued->number)->toBe(1);
});

it('keeps a separate sequence for the rectification series', function () {
    $this->issuance->issue(draftInvoice($this->team));

    $rectificative = $this->issuance->issue(draftInvoice($this->team, [
        'rectifies_invoice_id' => Invoice::where('team_id', $this->team->id)->first()->id,
    ]));

    expect($rectificative->series)->toBe('R')
        ->and($rectificative->number)->toBe(1);
});

it('never reuses a number of a rectified invoice', function () {
    $original = $this->issuance->issue(draftInvoice($this->team));
    $rectificative = $this->issuance->issue(draftInvoice($this->team, [
        'rectifies_invoice_id' => $original->id,
    ]));

    $next = $this->issuance->issue(draftInvoice($this->team));

    expect($original->number)->toBe(1)
        ->and($next->number)->toBe(2)
        ->and($rectificative->number)->toBe(1);
});

it('stores the numbering state per series and exercise', function () {
    $this->issuance->issue(draftInvoice($this->team));

    $state = $this->settings->fresh()->invoice_sequence_state;

    expect($state['F'][(string) now()->year]['last_number'])->toBe(1)
        ->and($state['F'][(string) now()->year]['last_fingerprint'])->toBeString();
});

it('falls back to the highest stored number when the state is lost', function () {
    $this->issuance->issue(draftInvoice($this->team));
    $this->issuance->issue(draftInvoice($this->team));

    $this->settings->update(['invoice_sequence_state' => null]);

    $invoice = $this->issuance->issue(draftInvoice($this->team));

    expect($invoice->number)->toBe(3);
});

it('starts a new sequence on a new exercise', function () {
    $this->issuance->issue(draftInvoice($this->team));

    $lastYear = $this->issuance->issue(draftInvoice($this->team, [
        'issue_date' => now()->subYear()->toDateString(),
    ]));

    expect($lastYear->year)->toBe((int) now()->subYear()->year)
        ->and($lastYear->number)->toBe(1);
});

it('rejects a duplicated number for the same series and exercise', function () {
    $this->issuance->issue(draftInvoice($this->team));

    expect(fn () => Invoice::create([
        'team_id' => $this->team->id,
        'invoice_type' => Invoice::TYPE_FULL,
        'currency' => 'EUR',
        'status' => Invoice::STATUS_ISSUED,
        'series' => 'F',
        'year' => (int) now()->year,
        'number' => 1,
    ]))->toThrow(QueryException::class);
});
