<?php

use App\Models\Invoice;
use App\Models\InvoiceEvent;
use App\Models\InvoiceSetting;
use App\Models\Payment;
use App\Models\User;
use App\Services\InvoiceFromPaymentService;
use App\Services\InvoiceIssuanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Invoice PDFs are written to the configured disk; without this the
    // suite writes to the real S3 bucket.
    Storage::fake(config('picstome.disk'));

    $this->user = User::factory()->withPersonalTeam()->create();
    $this->team = $this->user->currentTeam;
    $this->team->update(['monthly_invoice_limit' => 10]);
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
        ->assertSee('Online invoice')
        ->assertSee('Download PDF')
        ->assertSee(route('customers.show', $invoice->customer), false)
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

it('opens the corrective invoice on the line items so the amounts can be changed', function () {
    $invoice = $this->issuance->issue(draftInvoice($this->team));

    Livewire::actingAs($this->user)
        ->test('pages::invoices.show', ['invoice' => $invoice])
        ->submit([
            'rectificationReason' => 'Wrong amount',
        ], 'rectify')
        ->assertHasNoErrors()
        ->assertRedirect(route('invoices.create', [
            'invoice' => $this->team->invoices()->where('rectifies_invoice_id', $invoice->id)->first()->ulid,
        ]));

    $rectificative = $this->team->invoices()->where('rectifies_invoice_id', $invoice->id)->first();

    expect($rectificative)->not->toBeNull()
        ->and($rectificative->status)->toBe(Invoice::STATUS_DRAFT)
        ->and($rectificative->rectification_type)->toBe(Invoice::RECTIFICATION_SUBSTITUTION)
        ->and($rectificative->rectification_reason)->toBe('Wrong amount')
        ->and($rectificative->customer_id)->toBe($invoice->customer_id)
        ->and((float) $rectificative->total)->toBe((float) $invoice->total)
        ->and($invoice->fresh()->status)->toBe(Invoice::STATUS_ISSUED);
});

it('opens a corrective draft on the line items step', function () {
    $invoice = $this->issuance->issue(draftInvoice($this->team));

    Livewire::actingAs($this->user)
        ->test('pages::invoices.show', ['invoice' => $invoice])
        ->submit([
            'rectificationReason' => 'Wrong amount',
        ], 'rectify');

    $rectificative = $this->team->invoices()->where('rectifies_invoice_id', $invoice->id)->first();

    Livewire::withQueryParams(['invoice' => $rectificative->ulid])
        ->actingAs($this->user)
        ->test('pages::invoices.create')
        ->assertSet('step', 2)
        ->assertSet('form.lineItems.0.unit_price', 100)
        ->assertSee('The original invoice totals');
});

it('issues the corrective invoice with a changed amount', function () {
    $invoice = $this->issuance->issue(draftInvoice($this->team));

    Livewire::actingAs($this->user)
        ->test('pages::invoices.show', ['invoice' => $invoice])
        ->submit([
            'rectificationReason' => 'Wrong amount',
        ], 'rectify');

    $rectificative = $this->team->invoices()->where('rectifies_invoice_id', $invoice->id)->first();

    Livewire::withQueryParams(['invoice' => $rectificative->ulid])
        ->actingAs($this->user)
        ->test('pages::invoices.create')
        ->submit([
            'form.lineItems.0.unit_price' => 50,
        ], 'nextStep')
        ->assertSet('step', 3)
        ->assertSee('Difference')
        ->call('issue')
        ->assertHasNoErrors();

    $rectificative = $rectificative->fresh();

    expect((float) $rectificative->total)->toBe(60.5)
        ->and($rectificative->status)->toBe(Invoice::STATUS_ISSUED)
        ->and($rectificative->series)->toBe('R')
        ->and($rectificative->full_number)->toBe('R-'.now()->year.'-0001')
        ->and($rectificative->pdf_path)->not->toBeNull()
        ->and((float) $rectificative->rectified_amount)->toBe(-60.5)
        ->and($invoice->fresh()->status)->toBe(Invoice::STATUS_RECTIFIED);
});

it('issues the corrective invoice with the same amounts on demand', function () {
    $invoice = $this->issuance->issue(draftInvoice($this->team));

    Livewire::actingAs($this->user)
        ->test('pages::invoices.show', ['invoice' => $invoice])
        ->submit([
            'rectificationReason' => 'Wrong client name',
        ], 'rectifyWithoutChanges')
        ->assertHasNoErrors();

    $rectificative = $this->team->invoices()->where('rectifies_invoice_id', $invoice->id)->first();

    expect($rectificative->status)->toBe(Invoice::STATUS_ISSUED)
        ->and((float) $rectificative->total)->toBe((float) $invoice->total)
        ->and($rectificative->pdf_path)->not->toBeNull()
        ->and($invoice->fresh()->status)->toBe(Invoice::STATUS_RECTIFIED);
});

it('lets the user edit the full number of a corrective invoice', function () {
    $invoice = $this->issuance->issue(draftInvoice($this->team));

    Livewire::actingAs($this->user)
        ->test('pages::invoices.show', ['invoice' => $invoice])
        ->submit([
            'rectificationReason' => 'Wrong amount',
        ], 'rectify')
        ->assertHasNoErrors();

    $rectificative = $this->team->invoices()->where('rectifies_invoice_id', $invoice->id)->first();

    expect($rectificative->full_number)->toBeNull();

    Livewire::withQueryParams(['invoice' => $rectificative->ulid])
        ->actingAs($this->user)
        ->test('pages::invoices.create')
        ->submit([
            'form.full_number' => 'R-'.now()->year.'-0099',
        ], 'issue')
        ->assertHasNoErrors();

    expect($rectificative->fresh()->full_number)->toBe('R-'.now()->year.'-0099')
        ->and($rectificative->fresh()->status)->toBe(Invoice::STATUS_ISSUED);
});

it('copies the exchange rate to the corrective invoice', function () {
    $invoice = $this->issuance->issue(draftInvoice($this->team, [
        'currency' => 'USD',
        'exchange_rate' => 0.92,
        'exchange_rate_date' => now()->toDateString(),
    ]));

    Livewire::actingAs($this->user)
        ->test('pages::invoices.show', ['invoice' => $invoice])
        ->submit([
            'rectificationReason' => 'Wrong amount',
        ], 'rectify')
        ->assertHasNoErrors();

    $rectificative = $this->team->invoices()->where('rectifies_invoice_id', $invoice->id)->first();

    expect((float) $rectificative->exchange_rate)->toBe(0.92)
        ->and($rectificative->exchange_rate_date->toDateString())->toBe(now()->toDateString());
});

it('does not offer to rectify an invoice that is already rectified', function () {
    $invoice = $this->issuance->issue(draftInvoice($this->team));

    Livewire::actingAs($this->user)
        ->test('pages::invoices.show', ['invoice' => $invoice])
        ->submit([
            'rectificationReason' => 'Wrong client name',
        ], 'rectifyWithoutChanges')
        ->assertHasNoErrors();

    expect($invoice->fresh()->status)->toBe(Invoice::STATUS_RECTIFIED)
        ->and($this->team->invoices()->where('rectifies_invoice_id', $invoice->id)->count())->toBe(1);
});

it('does not rectify an invoice twice while a corrective draft is pending', function () {
    $invoice = $this->issuance->issue(draftInvoice($this->team));

    Livewire::actingAs($this->user)
        ->test('pages::invoices.show', ['invoice' => $invoice])
        ->submit([
            'rectificationReason' => 'Wrong client name',
        ], 'rectify')
        ->assertHasNoErrors();

    Livewire::actingAs($this->user)
        ->test('pages::invoices.show', ['invoice' => $invoice->fresh()])
        ->submit([
            'rectificationReason' => 'Another correction',
        ], 'rectify')
        ->assertHasErrors(['invoice' => 'This invoice has already been rectified.']);

    Livewire::actingAs($this->user)
        ->test('pages::invoices.show', ['invoice' => $invoice->fresh()])
        ->submit([
            'rectificationReason' => 'Another correction',
        ], 'rectifyWithoutChanges')
        ->assertHasErrors(['invoice' => 'This invoice has already been rectified.']);

    expect($this->team->invoices()->where('rectifies_invoice_id', $invoice->id)->count())->toBe(1);
});

it('requires a reason to rectify an invoice', function () {
    $invoice = $this->issuance->issue(draftInvoice($this->team));

    Livewire::actingAs($this->user)
        ->test('pages::invoices.show', ['invoice' => $invoice])
        ->call('rectify')
        ->assertHasErrors(['rectificationReason' => 'required']);

    Livewire::actingAs($this->user)
        ->test('pages::invoices.show', ['invoice' => $invoice])
        ->call('rectifyWithoutChanges')
        ->assertHasErrors(['rectificationReason' => 'required']);

    expect($this->team->invoices()->where('rectifies_invoice_id', $invoice->id)->count())->toBe(0);
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
        ->assertDontSee('Online invoice')
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

it('marks an issued invoice as paid and back as pending', function () {
    $invoice = $this->issuance->issue(draftInvoice($this->team));

    $component = Livewire::actingAs($this->user)
        ->test('pages::invoices.show', ['invoice' => $invoice])
        ->assertSee('Payment pending')
        ->call('markAsPaid')
        ->assertHasNoErrors()
        ->assertSee('Paid');

    expect($invoice->fresh()->paid_at)->not->toBeNull()
        ->and($invoice->events()->pluck('event'))->toContain(InvoiceEvent::MARKED_PAID);

    $component->call('markAsPending')
        ->assertHasNoErrors()
        ->assertSee('Payment pending');

    expect($invoice->fresh()->paid_at)->toBeNull()
        ->and($invoice->events()->pluck('event'))->toContain(InvoiceEvent::MARKED_UNPAID);
});

it('marks a rectificative invoice as paid', function () {
    $original = $this->issuance->issue(draftInvoice($this->team));

    $rectificative = $this->issuance->issue(draftInvoice($this->team, [
        'rectifies_invoice_id' => $original->id,
        'rectification_type' => Invoice::RECTIFICATION_SUBSTITUTION,
        'rectification_reason' => 'Wrong amount',
    ]));

    Livewire::actingAs($this->user)
        ->test('pages::invoices.show', ['invoice' => $rectificative])
        ->assertSee('Mark as paid')
        ->assertSee('Payment pending')
        ->call('markAsPaid')
        ->assertHasNoErrors()
        ->assertSee('Paid')
        ->assertDontSee('Payment pending');

    expect($rectificative->fresh()->paid_at)->not->toBeNull()
        ->and($rectificative->events()->pluck('event'))->toContain(InvoiceEvent::MARKED_PAID);
});

it('does not offer the paid action for a rectified invoice', function () {
    $original = $this->issuance->issue(draftInvoice($this->team));

    $this->issuance->issue(draftInvoice($this->team, [
        'rectifies_invoice_id' => $original->id,
        'rectification_type' => Invoice::RECTIFICATION_SUBSTITUTION,
        'rectification_reason' => 'Wrong amount',
    ]));

    Livewire::actingAs($this->user)
        ->test('pages::invoices.show', ['invoice' => $original->fresh()])
        ->assertDontSee('Mark as paid')
        ->assertDontSee('Payment pending')
        ->call('markAsPaid')
        ->assertStatus(403);

    expect($original->fresh()->paid_at)->toBeNull();
});

it('does not offer the paid action for a draft invoice', function () {
    $invoice = draftInvoice($this->team);

    Livewire::actingAs($this->user)
        ->test('pages::invoices.show', ['invoice' => $invoice])
        ->assertDontSee('Mark as paid')
        ->assertDontSee('Payment pending');
});

it('shows the email action of an issued invoice', function () {
    $invoice = $this->issuance->issue(draftInvoice($this->team));

    Livewire::actingAs($this->user)
        ->test('pages::invoices.show', ['invoice' => $invoice])
        ->assertSee('Email invoice')
        ->assertSee('mailto:', false);
});

it('does not show the email action of a draft invoice', function () {
    $invoice = draftInvoice($this->team);

    Livewire::actingAs($this->user)
        ->test('pages::invoices.show', ['invoice' => $invoice])
        ->assertDontSee('Email invoice')
        ->assertDontSee('mailto:', false);
});

it('does not show the email action when the public link is revoked', function () {
    $invoice = $this->issuance->issue(draftInvoice($this->team));

    Livewire::actingAs($this->user)
        ->test('pages::invoices.show', ['invoice' => $invoice])
        ->call('revokeLink')
        ->assertDontSee('mailto:', false);
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
