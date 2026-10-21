<?php

use App\Models\Invoice;
use App\Models\InvoiceEvent;
use App\Models\InvoiceSetting;
use App\Models\User;
use App\Services\InvoiceIssuanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['picstome.personal_team_monthly_invoice_limit' => 10]);

    $this->user = User::factory()->withPersonalTeam()->create();
    $this->team = $this->user->currentTeam;
    $this->settings = InvoiceSetting::factory()->for($this->team)->create();
    $this->issuance = app(InvoiceIssuanceService::class);
});

it('shows an issued invoice', function () {
    $invoice = $this->issuance->issue(draftInvoice($this->team));

    Livewire::actingAs($this->user)
        ->test('pages::invoices.show', ['invoice' => $invoice])
        ->assertHasNoErrors()
        ->assertSee($invoice->full_number)
        ->assertSee('Issued')
        ->assertSee('Verified');
});

it('does not show an invoice of another team', function () {
    $otherTeam = User::factory()->withPersonalTeam()->create()->currentTeam;

    $invoice = $this->issuance->issue(draftInvoice($otherTeam));

    $this->actingAs($this->user)
        ->get(route('invoices.show', $invoice))
        ->assertForbidden();
});

it('revokes the public link', function () {
    $invoice = $this->issuance->issue(draftInvoice($this->team));

    Livewire::actingAs($this->user)
        ->test('pages::invoices.show', ['invoice' => $invoice])
        ->call('revokeLink')
        ->assertHasNoErrors();

    $invoice = $invoice->fresh();

    expect($invoice->public_token)->toBeNull()
        ->and($invoice->public_token_revoked_at)->not->toBeNull()
        ->and($invoice->events()->pluck('event'))->toContain(InvoiceEvent::LINK_REVOKED);
});

it('creates a corrective invoice with the reason', function () {
    $invoice = $this->issuance->issue(draftInvoice($this->team));

    Livewire::actingAs($this->user)
        ->test('pages::invoices.show', ['invoice' => $invoice])
        ->set('rectificationReason', 'Wrong client name')
        ->call('rectify')
        ->assertHasNoErrors();

    $rectificative = $this->team->invoices()->where('rectifies_invoice_id', $invoice->id)->first();

    expect($rectificative)->not->toBeNull()
        ->and($rectificative->status)->toBe(Invoice::STATUS_DRAFT)
        ->and($rectificative->rectification_type)->toBe(Invoice::RECTIFICATION_SUBSTITUTION)
        ->and($rectificative->rectification_reason)->toBe('Wrong client name')
        ->and($rectificative->customer_id)->toBe($invoice->customer_id);
});

it('copies the exchange rate to the corrective invoice', function () {
    $invoice = $this->issuance->issue(draftInvoice($this->team, [
        'currency' => 'USD',
        'exchange_rate' => 0.92,
        'exchange_rate_date' => now()->toDateString(),
    ]));

    Livewire::actingAs($this->user)
        ->test('pages::invoices.show', ['invoice' => $invoice])
        ->set('rectificationReason', 'Wrong amount')
        ->call('rectify')
        ->assertHasNoErrors();

    $rectificative = $this->team->invoices()->where('rectifies_invoice_id', $invoice->id)->first();

    expect((float) $rectificative->exchange_rate)->toBe(0.92)
        ->and($rectificative->exchange_rate_date->toDateString())->toBe(now()->toDateString());
});

it('requires a reason to rectify an invoice', function () {
    $invoice = $this->issuance->issue(draftInvoice($this->team));

    Livewire::actingAs($this->user)
        ->test('pages::invoices.show', ['invoice' => $invoice])
        ->call('rectify')
        ->assertHasErrors(['rectificationReason' => 'required']);
});

it('deletes a draft invoice', function () {
    $invoice = draftInvoice($this->team);

    Livewire::actingAs($this->user)
        ->test('pages::invoices.show', ['invoice' => $invoice])
        ->call('delete')
        ->assertHasNoErrors();

    expect(Invoice::whereKey($invoice->id)->exists())->toBeFalse();
});

it('allows editing a draft invoice', function () {
    $invoice = draftInvoice($this->team);

    Livewire::actingAs($this->user)
        ->test('pages::invoices.show', ['invoice' => $invoice])
        ->assertSee(route('invoices.create', ['invoice' => $invoice->ulid]), false);
});

it('does not allow editing an issued invoice', function () {
    $invoice = $this->issuance->issue(draftInvoice($this->team));

    Livewire::actingAs($this->user)
        ->test('pages::invoices.show', ['invoice' => $invoice])
        ->assertDontSee(route('invoices.create', ['invoice' => $invoice->ulid]), false);
});
