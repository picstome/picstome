<?php

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceEvent;
use App\Models\InvoiceSetting;
use App\Models\User;
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
        ->submit([
            'form.lineItems.0.quantity' => 2,
            'form.lineItems.0.unit_price' => 150,
        ], 'nextStep')
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

it('issues an invoice with a custom full number', function () {
    $component = Livewire::actingAs($this->user)->test('pages::invoices.create');

    $component->set('form.customer_id', $this->customer->id)
        ->call('nextStep')
        ->submit([
            'form.lineItems.0.description' => 'Photo session',
            'form.lineItems.0.quantity' => 1,
            'form.lineItems.0.unit_price' => 150,
        ], 'nextStep')
        ->submit([
            'form.full_number' => 'INV-0042',
        ], 'issue')
        ->assertHasNoErrors();

    $invoice = $this->team->invoices()->first();

    expect($invoice->full_number)->toBe('INV-0042')
        ->and($invoice->number)->toBe(1);
});

it('shows the suggested full number and the last generated invoice', function () {
    $this->issuance = app(InvoiceIssuanceService::class);
    $this->issuance->issue(draftInvoice($this->team), null, 'INV-0042');

    $component = Livewire::actingAs($this->user)->test('pages::invoices.create');

    $component->set('form.customer_id', $this->customer->id)
        ->call('nextStep')
        ->submit([
            'form.lineItems.0.description' => 'Photo session',
            'form.lineItems.0.quantity' => 1,
            'form.lineItems.0.unit_price' => 150,
        ], 'nextStep')
        ->assertSee('Suggested: INV-0043')
        ->assertSee('Last invoice: INV-0042')
        ->assertSee('edit-invoice-number', false)
        ->assertSee(route('invoices.show', Invoice::where('full_number', 'INV-0042')->first()), false);
});

it('warns about lower numbers and gaps without blocking the issuance', function () {
    $this->issuance = app(InvoiceIssuanceService::class);
    $this->issuance->issue(draftInvoice($this->team), null, 'INV-0042');

    $component = Livewire::actingAs($this->user)->test('pages::invoices.create');

    $component->set('form.customer_id', $this->customer->id)
        ->call('nextStep')
        ->submit([
            'form.lineItems.0.description' => 'Photo session',
            'form.lineItems.0.quantity' => 1,
            'form.lineItems.0.unit_price' => 150,
        ], 'nextStep')
        ->set('form.full_number', 'INV-0040')
        ->assertSee('This number is lower than the last generated invoice. You can still issue it.')
        ->call('issue')
        ->assertHasNoErrors();

    expect($this->team->invoices()->where('full_number', 'INV-0040')->exists())->toBeTrue();
});

it('warns about gaps without blocking the issuance', function () {
    $this->issuance = app(InvoiceIssuanceService::class);
    $this->issuance->issue(draftInvoice($this->team), null, 'INV-0042');

    $component = Livewire::actingAs($this->user)->test('pages::invoices.create');

    $component->set('form.customer_id', $this->customer->id)
        ->call('nextStep')
        ->submit([
            'form.lineItems.0.description' => 'Photo session',
            'form.lineItems.0.quantity' => 1,
            'form.lineItems.0.unit_price' => 150,
        ], 'nextStep')
        ->set('form.full_number', 'INV-0044')
        ->assertSee('This number leaves a gap after the last generated invoice. You can still issue it.')
        ->call('issue')
        ->assertHasNoErrors();

    expect($this->team->invoices()->where('full_number', 'INV-0044')->exists())->toBeTrue();
});

it('rejects a duplicated full number', function () {
    $this->issuance = app(InvoiceIssuanceService::class);
    $this->issuance->issue(draftInvoice($this->team), null, 'INV-0042');

    $component = Livewire::actingAs($this->user)->test('pages::invoices.create');

    $component->set('form.customer_id', $this->customer->id)
        ->call('nextStep')
        ->submit([
            'form.lineItems.0.description' => 'Photo session',
            'form.lineItems.0.quantity' => 1,
            'form.lineItems.0.unit_price' => 150,
        ], 'nextStep')
        ->submit([
            'form.full_number' => 'INV-0042',
        ], 'issue')
        ->assertHasErrors(['form.full_number' => 'This invoice number is already used by another invoice.']);
});

it('saves a draft without consuming a number', function () {
    $component = Livewire::actingAs($this->user)->test('pages::invoices.create');

    $component->set('form.customer_id', $this->customer->id)
        ->submit([
            'form.lineItems.0.description' => 'Photo session',
            'form.lineItems.0.quantity' => 1,
            'form.lineItems.0.unit_price' => 150,
        ], 'saveDraft')
        ->assertHasNoErrors();

    $invoice = $this->team->invoices()->first();

    expect($invoice->status)->toBe(Invoice::STATUS_DRAFT)
        ->and($invoice->number)->toBeNull()
        ->and($invoice->pdf_path)->toBeNull();
});

it('previews the invoice as a draft before issuing it', function () {
    $component = Livewire::actingAs($this->user)->test('pages::invoices.create');

    $component->set('form.customer_id', $this->customer->id)
        ->submit([
            'form.lineItems.0.description' => 'Photo session',
            'form.lineItems.0.quantity' => 1,
            'form.lineItems.0.unit_price' => 150,
        ], 'nextStep')
        ->call('nextStep')
        ->assertSet('step', 3)
        ->call('preview')
        ->assertHasNoErrors()
        ->assertSee('Invoice preview')
        ->assertSee('This is a preview. The invoice is still a draft and has not been issued.');

    $draft = $this->team->invoices()->first();

    expect($draft->status)->toBe(Invoice::STATUS_DRAFT)
        ->and($draft->number)->toBeNull()
        ->and($draft->full_number)->toBeNull()
        ->and($draft->pdf_path)->toBeNull()
        ->and($component->get('previewUrl'))->toContain(route('invoices.preview', $draft));

    $this->actingAs($this->user)
        ->get($component->get('previewUrl'))
        ->assertOk()
        ->assertSee('Acme Studio SL')
        ->assertSee('Photo session');
});

it('does not block saving a draft with a full number that is already used', function () {
    $this->issuance = app(InvoiceIssuanceService::class);
    $this->issuance->issue(draftInvoice($this->team), null, 'INV-0042');

    $component = Livewire::actingAs($this->user)->test('pages::invoices.create');

    $component->set('form.customer_id', $this->customer->id)
        ->submit([
            'form.lineItems.0.description' => 'Photo session',
            'form.lineItems.0.quantity' => 1,
            'form.lineItems.0.unit_price' => 150,
            'form.full_number' => 'INV-0042',
        ], 'saveDraft')
        ->assertHasNoErrors();

    $draft = $this->team->invoices()->where('status', Invoice::STATUS_DRAFT)->first();

    expect($draft)->not->toBeNull()
        ->and($draft->full_number)->toBeNull();
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
        ->submit([
            'form.customer_id' => $this->customer->id,
            'form.lineItems.0.description' => '',
        ], 'saveDraft')
        ->assertHasErrors(['form.lineItems.0.description' => 'required']);
});

it('does not leave the line items step with an invalid line', function () {
    Livewire::actingAs($this->user)
        ->test('pages::invoices.create')
        ->submit([
            'form.customer_id' => $this->customer->id,
        ], 'nextStep')
        ->assertSet('step', 2)
        ->call('nextStep')
        ->assertHasErrors(['form.lineItems.0.description' => 'required'])
        ->assertSet('step', 2)
        ->assertSee('field is required');
});

it('labels the zero vat rate as not subject to vat', function () {
    expect(Blade::render('<x-vat-rate-label :rate="0" />'))->toContain('Not subject to VAT (0%)');

    app()->setLocale('es');

    expect(Blade::render('<x-vat-rate-label :rate="0" />'))->toContain('No sujeta a IVA (0%)');
});

it('asks for the exemption reason when the VAT is zero', function () {
    $component = Livewire::actingAs($this->user)
        ->test('pages::invoices.create')
        ->submit([
            'form.customer_id' => $this->customer->id,
            'form.lineItems.0.description' => 'Sesión de fotos',
            'form.lineItems.0.quantity' => 1,
            'form.lineItems.0.unit_price' => 100,
        ], 'nextStep')
        ->call('nextStep')
        ->assertSet('step', 3);

    $component->set('form.vat_percent', '21')
        ->assertDontSee('Reason for the exemption');

    $component->set('form.vat_percent', '0')
        ->assertSee('Reason for the exemption')
        ->assertSee('An exempt invoice must state why it has no VAT.');
});

it('requires the exemption reason when the VAT is zero', function () {
    Livewire::actingAs($this->user)
        ->test('pages::invoices.create')
        ->submit([
            'form.customer_id' => $this->customer->id,
            'form.lineItems.0.description' => 'Sesión de fotos',
            'form.lineItems.0.quantity' => 1,
            'form.lineItems.0.unit_price' => 100,
            'form.vat_percent' => '0',
        ], 'saveDraft')
        ->assertHasErrors(['form.vat_exemption_reason' => 'required']);
});

it('does not allow a client from another team', function () {
    $otherCustomer = Customer::factory()->create();

    Livewire::actingAs($this->user)
        ->test('pages::invoices.create')
        ->submit([
            'form.customer_id' => $otherCustomer->id,
        ], 'nextStep')
        ->assertHasErrors(['form.customer_id' => 'exists']);
});

it('creates a client from the invoice', function () {
    Livewire::actingAs($this->user)
        ->test('pages::invoices.create')
        ->call('addCustomer')
        ->submit([
            'customerForm.name' => 'Nova Studio SL',
            'customerForm.legal_name' => 'Nova Studio SL',
            'customerForm.tax_id' => 'B87654321',
            'customerForm.address' => 'Calle Sol 2',
            'customerForm.city' => 'Valencia',
            'customerForm.postal_code' => '46001',
            'customerForm.country' => 'ES',
        ], 'saveCustomer')
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
        ->submit([
            'form.lineItems.0.unit_price' => 200,
        ], 'saveDraft')
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
        ->submit([
            'form.customer_id' => $customer->id,
        ], 'editCustomer');

    $component
        ->submit([
            'customerForm.legal_name' => 'Nova Studio SL',
            'customerForm.tax_id' => 'B87654321',
            'customerForm.address' => 'Calle Sol 2',
            'customerForm.city' => 'Valencia',
            'customerForm.postal_code' => '46001',
            'customerForm.country' => 'ES',
        ], 'saveCustomer')
        ->assertHasNoErrors();

    $customer = $customer->fresh();

    expect($customer->legal_name)->toBe('Nova Studio SL')
        ->and($customer->tax_id)->toBe('B87654321')
        ->and($customer->country)->toBe('ES');
});

it('records the creation and the update of a draft in the audit trail', function () {
    $component = Livewire::actingAs($this->user)
        ->test('pages::invoices.create')
        ->submit([
            'form.customer_id' => $this->customer->id,
            'form.lineItems.0.description' => 'Sesión de fotos',
            'form.lineItems.0.quantity' => 1,
            'form.lineItems.0.unit_price' => 100,
        ], 'saveDraft');

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

it('pre-fills the invoice with the client of the request', function () {
    $customer = Customer::factory()->for($this->team)->create([
        'name' => 'Acme Studio SL',
        'email' => 'acme@example.com',
        'legal_name' => 'Acme Studio SL',
        'tax_id' => 'B87654321',
        'address' => 'Calle Mayor 1',
        'city' => 'Madrid',
        'postal_code' => '28001',
        'country' => 'ES',
    ]);

    Livewire::withQueryParams(['customer' => $customer->ulid])
        ->actingAs($this->user)
        ->test('pages::invoices.create')
        ->assertHasNoErrors()
        ->assertSet('form.customer_id', $customer->id)
        ->assertSet('form.invoice_type', Invoice::TYPE_FULL)
        ->assertSet('step', 2);

    expect(Invoice::count())->toBe(0);
});

it('pre-fills a simplified invoice for a client without fiscal data', function () {
    $customer = Customer::factory()->for($this->team)->create([
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'legal_name' => null,
        'tax_id' => null,
    ]);

    Livewire::withQueryParams(['customer' => $customer->ulid])
        ->actingAs($this->user)
        ->test('pages::invoices.create')
        ->assertHasNoErrors()
        ->assertSet('form.customer_id', $customer->id)
        ->assertSet('form.invoice_type', Invoice::TYPE_SIMPLIFIED);
});

it('lets a corrective invoice be issued after reaching the monthly limit', function () {
    $original = app(InvoiceIssuanceService::class)->issue(draftInvoice($this->team));

    $rectificative = $this->team->invoices()->create([
        'customer_id' => $original->customer_id,
        'invoice_type' => $original->invoice_type,
        'issue_date' => now()->toDateString(),
        'currency' => $original->currency,
        'vat_percent' => $original->vat_percent,
        'subtotal' => 100,
        'base_amount' => 100,
        'vat_amount' => 21,
        'total' => 121,
        'amount_due' => 121,
        'rectifies_invoice_id' => $original->id,
        'rectification_type' => Invoice::RECTIFICATION_SUBSTITUTION,
        'rectification_reason' => 'Wrong amount',
        'invoice_data' => $original->invoice_data,
    ]);

    $this->team->update(['monthly_invoice_limit' => 1]);

    Livewire::withQueryParams(['invoice' => $rectificative->ulid])
        ->actingAs($this->user)
        ->test('pages::invoices.create')
        ->assertSet('canIssue', true)
        ->submit([
            'form.lineItems.0.unit_price' => 50,
        ], 'issue')
        ->assertHasNoErrors();

    expect($rectificative->fresh()->status)->toBe(Invoice::STATUS_ISSUED)
        ->and((float) $rectificative->fresh()->total)->toBe(60.5);
});

it('jumps back to the line items from the review of a corrective invoice', function () {
    $original = app(InvoiceIssuanceService::class)->issue(draftInvoice($this->team));

    $rectificative = $this->team->invoices()->create([
        'customer_id' => $original->customer_id,
        'invoice_type' => $original->invoice_type,
        'issue_date' => now()->toDateString(),
        'currency' => $original->currency,
        'vat_percent' => $original->vat_percent,
        'subtotal' => 100,
        'base_amount' => 100,
        'vat_amount' => 21,
        'total' => 121,
        'amount_due' => 121,
        'rectifies_invoice_id' => $original->id,
        'rectification_type' => Invoice::RECTIFICATION_SUBSTITUTION,
        'rectification_reason' => 'Wrong amount',
        'invoice_data' => $original->invoice_data,
    ]);

    Livewire::withQueryParams(['invoice' => $rectificative->ulid])
        ->actingAs($this->user)
        ->test('pages::invoices.create')
        ->assertSet('step', 2)
        ->call('nextStep')
        ->assertSet('step', 3)
        ->assertSee('Change the amounts')
        ->call('editLineItems')
        ->assertSet('step', 2)
        ->assertSee('The original invoice totals');
});
