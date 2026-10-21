<?php

use App\Models\Invoice;
use App\Models\InvoiceEvent;
use App\Models\InvoiceSetting;
use App\Models\User;
use App\Services\InvoiceIssuanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->withPersonalTeam()->create();
    $this->team = $this->user->currentTeam;
    $this->settings = InvoiceSetting::factory()->for($this->team)->create();
    $this->issuance = app(InvoiceIssuanceService::class);
});

it('does not allow editing an issued invoice', function () {
    $invoice = $this->issuance->issue(draftInvoice($this->team));

    expect(function () use ($invoice) {
        $invoice->total = 500;
        $invoice->save();
    })->toThrow(LogicException::class);
});

it('does not allow changing the status of a rectified invoice', function () {
    $original = $this->issuance->issue(draftInvoice($this->team));

    $this->issuance->issue(draftInvoice($this->team, [
        'rectifies_invoice_id' => $original->id,
        'rectification_type' => Invoice::RECTIFICATION_SUBSTITUTION,
        'rectification_reason' => 'Wrong client name',
    ]));

    expect(function () use ($original) {
        $original->fresh()->forceFill(['status' => Invoice::STATUS_ISSUED])->save();
    })->toThrow(LogicException::class);
});

it('does not allow deleting an issued invoice', function () {
    $invoice = $this->issuance->issue(draftInvoice($this->team));

    expect(fn () => $invoice->delete())->toThrow(LogicException::class);
});

it('allows deleting a draft invoice', function () {
    $invoice = draftInvoice($this->team);

    $invoice->delete();

    expect(Invoice::whereKey($invoice->id)->exists())->toBeFalse();
});

it('allows revoking the public link of an issued invoice', function () {
    $invoice = $this->issuance->issue(draftInvoice($this->team));

    $invoice->update([
        'public_token' => null,
        'public_token_revoked_at' => now(),
    ]);

    expect($invoice->fresh()->public_token)->toBeNull()
        ->and($invoice->fresh()->public_token_revoked_at)->not->toBeNull();
});

it('keeps the audit trail append only', function () {
    $invoice = $this->issuance->issue(draftInvoice($this->team));

    $event = $invoice->events()->first();

    expect(fn () => $event->update(['event' => InvoiceEvent::UPDATED]))
        ->toThrow(LogicException::class);

    expect(fn () => $event->delete())->toThrow(LogicException::class);
});
