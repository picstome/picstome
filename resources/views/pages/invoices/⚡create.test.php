<?php

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceSetting;
use App\Models\User;
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

    $component->call('addLine')
        ->set('form.lineItems.0.description', 'Photo session')
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
        ->call('addLine')
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
        ->call('addLine')
        ->set('form.lineItems.0.description', '')
        ->call('saveDraft')
        ->assertHasErrors(['form.lineItems.0.description' => 'required']);
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
        ->set('customerForm.name', 'Nova Studio SL')
        ->set('customerForm.legal_name', 'Nova Studio SL')
        ->set('customerForm.tax_id', 'B87654321')
        ->set('customerForm.address', 'Calle Sol 2')
        ->set('customerForm.city', 'Valencia')
        ->set('customerForm.postal_code', '46001')
        ->set('customerForm.country', 'ES')
        ->call('addCustomer')
        ->assertHasNoErrors();

    expect($this->team->customers()->count())->toBe(2)
        ->and($this->team->customers()->where('legal_name', 'Nova Studio SL')->exists())->toBeTrue();
});

it('requires authentication', function () {
    $this->get(route('invoices.create'))->assertRedirect(route('login'));
});
