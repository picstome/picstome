<?php

use App\Models\Customer;
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
    $this->customer = Customer::factory()->for($this->team)->create([
        'legal_name' => 'Acme Studio SL',
        'tax_id' => 'B12345678',
        'address' => 'Calle Mayor 1',
        'city' => 'Madrid',
        'postal_code' => '28001',
        'country' => 'ES',
    ]);
});

it('creates and issues an invoice from the guided flow', function () {
    $component = Livewire::actingAs($this->user)->test('pages::invoices.create');

    $component->set('form.customer_id', $this->customer->id)
        ->call('nextStep')
        ->assertSet('step', 2);

    $component->set('form.lineItems.0.description', 'Photo session')
        ->set('form.lineItems.0.quantity', 2)
        ->set('form.lineItems.0.unit_price', 150)
        ->call('nextStep')
        ->assertSet('step', 3);

    $component->call('issue')->assertHasNoErrors();

    $invoice = $this->team->invoices()->first();

    expect($invoice->status)->toBe(Invoice::STATUS_ISSUED)
        ->and($invoice->number)->toBe(1)
        ->and((float) $invoice->subtotal)->toBe(300.0)
        ->and((float) $invoice->vat_amount)->toBe(63.0)
        ->and((float) $invoice->total)->toBe(363.0)
        ->and($invoice->pdf_path)->not->toBeNull()
        ->and($invoice->public_token)->toHaveLength(40);
});

it('saves a draft without consuming a number', function () {
    $component = Livewire::actingAs($this->user)->test('pages::invoices.create');

    $component->set('form.customer_id', $this->customer->id)
        ->set('form.lineItems.0.description', 'Photo session')
        ->set('form.lineItems.0.quantity', 1)
        ->set('form.lineItems.0.unit_price', 150)
        ->call('saveDraft')
        ->assertHasNoErrors();

    $invoice = $this->team->invoices()->first();

    expect($invoice->status)->toBe(Invoice::STATUS_DRAFT)
        ->and($invoice->number)->toBeNull()
        ->and($invoice->pdf_path)->toBeNull();
});

it('starts a new invoice with one line item', function () {
    $component = Livewire::actingAs($this->user)->test('pages::invoices.create');

    $lineItems = $component->get('form.lineItems');

    expect($lineItems)->toHaveCount(1)
        ->and($lineItems[0]['description'])->toBe('')
        ->and($lineItems[0]['quantity'])->toBe(1)
        ->and($lineItems[0]['unit_price'])->toBe(0)
        ->and($lineItems[0]['id'])->not->toBeEmpty();
});

it('requires a client', function () {
    Livewire::actingAs($this->user)
        ->test('pages::invoices.create')
        ->call('nextStep')
        ->assertHasErrors(['form.customer_id' => 'required']);
});

it('requires at least one line item', function () {
    Livewire::actingAs($this->user)
        ->test('pages::invoices.create')
        ->set('form.customer_id', $this->customer->id)
        ->set('form.lineItems.0.description', '')
        ->call('saveDraft')
        ->assertHasErrors(['form.lineItems.0.description' => 'required']);
});

it('does not leave the line items step with an invalid line', function () {
    Livewire::actingAs($this->user)
        ->test('pages::invoices.create')
        ->set('form.customer_id', $this->customer->id)
        ->call('nextStep')
        ->assertSet('step', 2)
        ->call('nextStep')
        ->assertHasErrors(['form.lineItems.0.description' => 'required'])
        ->assertSet('step', 2)
        ->assertSee('field is required');
});

it('does not allow a client from another team', function () {
    $otherCustomer = Customer::factory()->create();

    Livewire::actingAs($this->user)
        ->test('pages::invoices.create')
        ->set('form.customer_id', $otherCustomer->id)
        ->call('nextStep')
        ->assertHasErrors(['form.customer_id' => 'exists']);
});

it('creates a client from the invoice', function () {
    Livewire::actingAs($this->user)
        ->test('pages::invoices.create')
        ->call('addCustomer')
        ->set('customerForm.name', 'Nova Studio SL')
        ->set('customerForm.legal_name', 'Nova Studio SL')
        ->set('customerForm.tax_id', 'B87654321')
        ->set('customerForm.address', 'Calle Sol 2')
        ->set('customerForm.city', 'Valencia')
        ->set('customerForm.postal_code', '46001')
        ->set('customerForm.country', 'ES')
        ->call('saveCustomer')
        ->assertHasNoErrors();

    expect($this->team->customers()->count())->toBe(2)
        ->and($this->team->customers()->where('legal_name', 'Nova Studio SL')->exists())->toBeTrue();
});

it('requires authentication', function () {
    $this->get(route('invoices.create'))->assertRedirect(route('login'));
});

it('edits a draft invoice', function () {
    $draft = draftInvoice($this->team, [
        'invoice_data' => [
            'line_items' => [['description' => 'Photo session', 'quantity' => 1, 'unit_price' => 150]],
            'issuer' => [],
            'customer' => [],
        ],
    ]);

    Livewire::withQueryParams(['invoice' => $draft->ulid])
        ->actingAs($this->user)
        ->test('pages::invoices.create')
        ->assertHasNoErrors()
        ->assertSet('form.invoice.id', $draft->id)
        ->assertSet('form.customer_id', $this->customer->id)
        ->assertSet('form.lineItems.0.description', 'Photo session');
});

it('updates a draft invoice without consuming a number', function () {
    $draft = draftInvoice($this->team, [
        'invoice_data' => [
            'line_items' => [['description' => 'Photo session', 'quantity' => 1, 'unit_price' => 150]],
            'issuer' => [],
            'customer' => [],
        ],
    ]);

    Livewire::withQueryParams(['invoice' => $draft->ulid])
        ->actingAs($this->user)
        ->test('pages::invoices.create')
        ->set('form.lineItems.0.unit_price', 200)
        ->call('saveDraft')
        ->assertHasNoErrors();

    $draft = $draft->fresh();

    expect($draft->status)->toBe(Invoice::STATUS_DRAFT)
        ->and($draft->number)->toBeNull()
        ->and((float) $draft->total)->toBe(242.0);
});

it('does not allow editing an issued invoice', function () {
    $invoice = app(InvoiceIssuanceService::class)->issue(draftInvoice($this->team));

    Livewire::withQueryParams(['invoice' => $invoice->ulid])
        ->actingAs($this->user)
        ->test('pages::invoices.create')
        ->assertSet('form.invoice', null);
});

it('warns when the selected client has no fiscal data', function () {
    $customer = Customer::factory()->for($this->team)->create([
        'name' => 'Client without data',
        'legal_name' => null,
        'tax_id' => null,
    ]);

    Livewire::actingAs($this->user)
        ->test('pages::invoices.create')
        ->set('form.customer_id', $customer->id)
        ->assertSee('The client is missing fiscal data')
        ->assertSee('Missing fiscal data');
});

it('completes the fiscal data of the client from the invoice', function () {
    $customer = Customer::factory()->for($this->team)->create([
        'name' => 'Client without data',
        'legal_name' => null,
        'tax_id' => null,
    ]);

    $component = Livewire::actingAs($this->user)
        ->test('pages::invoices.create')
        ->set('form.customer_id', $customer->id)
        ->call('editCustomer');

    $component
        ->set('customerForm.legal_name', 'Nova Studio SL')
        ->set('customerForm.tax_id', 'B87654321')
        ->set('customerForm.address', 'Calle Sol 2')
        ->set('customerForm.city', 'Valencia')
        ->set('customerForm.postal_code', '46001')
        ->set('customerForm.country', 'ES')
        ->call('saveCustomer')
        ->assertHasNoErrors();

    $customer = $customer->fresh();

    expect($customer->legal_name)->toBe('Nova Studio SL')
        ->and($customer->tax_id)->toBe('B87654321')
        ->and($customer->country)->toBe('ES');
});

it('records the creation and the update of a draft in the audit trail', function () {
    $component = Livewire::actingAs($this->user)
        ->test('pages::invoices.create')
        ->set('form.customer_id', $this->customer->id)
        ->set('form.lineItems.0.description', 'Sesión de fotos')
        ->set('form.lineItems.0.quantity', 1)
        ->set('form.lineItems.0.unit_price', 100)
        ->call('saveDraft');

    $draft = $this->team->invoices()->first();

    expect($draft->events()->pluck('event'))->toContain(InvoiceEvent::CREATED);

    $component->set('form.lineItems.0.unit_price', 120)->call('saveDraft');

    expect($draft->fresh()->events()->pluck('event'))->toContain(InvoiceEvent::UPDATED);
});

it('binds the client form of the invoice to the client form', function () {
    Livewire::actingAs($this->user)
        ->test('pages::invoices.create')
        ->assertSee('wire:model="customerForm.name"', false)
        ->assertDontSee('wire:model="form.name"', false);
});
