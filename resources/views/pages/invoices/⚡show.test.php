<?php

use App\Models\Invoice;
use App\Models\InvoiceEvent;
use App\Models\InvoiceSetting;
use App\Models\Payment;
use App\Models\User;
use App\Services\InvoiceFromPaymentService;
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

it('generates a new link after deactivating it', function () {
    $invoice = $this->issuance->issue(draftInvoice($this->team));
    $oldToken = $invoice->public_token;

    Livewire::actingAs($this->user)
        ->test('pages::invoices.show', ['invoice' => $invoice])
        ->call('revokeLink')
        ->assertHasNoErrors();

    expect($invoice->fresh()->public_token)->toBeNull();

    Livewire::actingAs($this->user)
        ->test('pages::invoices.show', ['invoice' => $invoice->fresh()])
        ->call('regenerateLink')
        ->assertHasNoErrors();

    $invoice = $invoice->fresh();

    expect($invoice->public_token)->toHaveLength(40)
        ->and($invoice->public_token)->not->toBe($oldToken)
        ->and($invoice->public_token_revoked_at)->toBeNull()
        ->and($invoice->events()->pluck('event'))->toContain(InvoiceEvent::LINK_REGENERATED);

    $this->get('/i/'.$invoice->public_token)->assertOk();
    $this->get('/i/'.$oldToken)->assertNotFound();
});

it('issues the corrective invoice with the reason', function () {
    $invoice = $this->issuance->issue(draftInvoice($this->team));

    Livewire::actingAs($this->user)
        ->test('pages::invoices.show', ['invoice' => $invoice])
        ->set('rectificationReason', 'Wrong client name')
        ->call('rectify')
        ->assertHasNoErrors();

    $rectificative = $this->team->invoices()->where('rectifies_invoice_id', $invoice->id)->first();

    expect($rectificative)->not->toBeNull()
        ->and($rectificative->status)->toBe(Invoice::STATUS_ISSUED)
        ->and($rectificative->series)->toBe('R')
        ->and($rectificative->rectification_type)->toBe(Invoice::RECTIFICATION_SUBSTITUTION)
        ->and($rectificative->rectification_reason)->toBe('Wrong client name')
        ->and($rectificative->customer_id)->toBe($invoice->customer_id)
        ->and($rectificative->pdf_path)->not->toBeNull()
        ->and($invoice->fresh()->status)->toBe(Invoice::STATUS_RECTIFIED);
});

it('can review the corrective invoice before issuing it', function () {
    $invoice = $this->issuance->issue(draftInvoice($this->team));

    Livewire::actingAs($this->user)
        ->test('pages::invoices.show', ['invoice' => $invoice])
        ->set('rectificationReason', 'Wrong amount')
        ->call('rectify', true)
        ->assertHasNoErrors()
        ->assertRedirect(route('invoices.create', [
            'invoice' => $this->team->invoices()->where('rectifies_invoice_id', $invoice->id)->first()->ulid,
        ]));

    $rectificative = $this->team->invoices()->where('rectifies_invoice_id', $invoice->id)->first();

    expect($rectificative->status)->toBe(Invoice::STATUS_DRAFT)
        ->and($invoice->fresh()->status)->toBe(Invoice::STATUS_ISSUED);
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

it('does not offer to rectify an invoice that is already rectified', function () {
    $invoice = $this->issuance->issue(draftInvoice($this->team));

    Livewire::actingAs($this->user)
        ->test('pages::invoices.show', ['invoice' => $invoice])
        ->set('rectificationReason', 'Wrong client name')
        ->call('rectify')
        ->assertHasNoErrors();

    expect($invoice->fresh()->status)->toBe(Invoice::STATUS_RECTIFIED)
        ->and($this->team->invoices()->where('rectifies_invoice_id', $invoice->id)->count())->toBe(1);
});

it('does not rectify an invoice twice while a corrective draft is pending', function () {
    $invoice = $this->issuance->issue(draftInvoice($this->team));

    Livewire::actingAs($this->user)
        ->test('pages::invoices.show', ['invoice' => $invoice])
        ->set('rectificationReason', 'Wrong client name')
        ->call('rectify', true)
        ->assertHasNoErrors();

    Livewire::actingAs($this->user)
        ->test('pages::invoices.show', ['invoice' => $invoice->fresh()])
        ->set('rectificationReason', 'Another correction')
        ->call('rectify')
        ->assertHasErrors(['invoice' => 'This invoice has already been rectified.']);

    expect($this->team->invoices()->where('rectifies_invoice_id', $invoice->id)->count())->toBe(1);
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

it('does not show the public link of a draft invoice', function () {
    $invoice = draftInvoice($this->team);

    Livewire::actingAs($this->user)
        ->test('pages::invoices.show', ['invoice' => $invoice])
        ->assertDontSee('Public link')
        ->assertDontSee('Generate a new link');
});

it('duplicates an issued invoice and opens the form on its line items', function () {
    $invoice = $this->issuance->issue(draftInvoice($this->team));

    $component = Livewire::actingAs($this->user)
        ->test('pages::invoices.show', ['invoice' => $invoice])
        ->assertSee('wire:click="duplicate"', false)
        ->call('duplicate')
        ->assertHasNoErrors();

    $draft = $this->team->invoices()->where('status', Invoice::STATUS_DRAFT)->first();

    expect($draft)->not->toBeNull()
        ->and($draft->customer_id)->toBe($invoice->customer_id);

    $component->assertRedirect(route('invoices.create', ['invoice' => $draft->ulid]));
});

it('does not offer to duplicate a draft invoice', function () {
    $invoice = draftInvoice($this->team);

    Livewire::actingAs($this->user)
        ->test('pages::invoices.show', ['invoice' => $invoice])
        ->assertDontSee('wire:click="duplicate"', false);
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

it('shows the payments of the invoice and the pending amount', function () {
    $payment = Payment::factory()->for($this->team)->create([
        'amount' => 15000,
        'currency' => 'eur',
    ]);

    $invoice = app(InvoiceFromPaymentService::class)->createFromPayment($payment, $this->user);

    app(InvoiceIssuanceService::class)->issue($invoice, $this->user);

    Livewire::actingAs($this->user)
        ->test('pages::invoices.show', ['invoice' => $invoice])
        ->assertHasNoErrors()
        ->assertSee('Payments')
        ->assertSee('Paid')
        ->assertSee('Pending')
        ->assertSee('0.00');
});
