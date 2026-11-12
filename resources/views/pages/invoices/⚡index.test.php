<?php

use App\Models\Invoice;
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
    $this->settings = InvoiceSetting::factory()->for($this->team)->create();
});

it('lists the invoices of the team', function () {
    $invoice = draftInvoice($this->team);

    Livewire::actingAs($this->user)
        ->test('pages::invoices')
        ->assertHasNoErrors()
        ->assertSee('Invoices')
        ->assertSee('Draft')
        ->assertSee(route('customers.show', $invoice->customer), false);
});

it('does not show the status or actions labels in the table header', function () {
    draftInvoice($this->team);

    Livewire::actingAs($this->user)
        ->test('pages::invoices')
        ->assertDontSee('Status')
        ->assertDontSee('Actions');
});

it('does not show the invoices of another team', function () {
    $otherTeam = User::factory()->withPersonalTeam()->create()->currentTeam;

    draftInvoice($otherTeam);

    Livewire::actingAs($this->user)
        ->test('pages::invoices')
        ->assertDontSee('Acme Studio SL');
});

it('warns when the issuer data is incomplete', function () {
    $this->settings->update(['legal_name' => null]);

    Livewire::actingAs($this->user)
        ->test('pages::invoices')
        ->assertSee('Complete your invoicing settings');
});

it('does not warn when the issuer data is complete', function () {
    Livewire::actingAs($this->user)
        ->test('pages::invoices')
        ->assertDontSee('Complete your invoicing settings');
});

it('requires authentication', function () {
    $this->get(route('invoices'))->assertRedirect(route('login'));
});

it('shows only the view and duplicate actions for an issued invoice', function () {
    $this->team->update(['monthly_invoice_limit' => 10]);

    $invoice = app(InvoiceIssuanceService::class)->issue(draftInvoice($this->team));

    Livewire::actingAs($this->user)
        ->test('pages::invoices')
        ->assertSee(route('invoices.show', $invoice), false)
        ->assertSee('confirmDuplicate('.$invoice->id.')', false)
        ->assertDontSee(route('invoices.pdf', $invoice), false)
        ->assertDontSee(route('invoices.public.show', $invoice->public_token), false)
        ->assertDontSee('mailto:', false);
});

it('duplicates an issued invoice from the list after confirmation', function () {
    $this->team->update(['monthly_invoice_limit' => 10]);

    $invoice = app(InvoiceIssuanceService::class)->issue(draftInvoice($this->team));

    $component = Livewire::actingAs($this->user)
        ->test('pages::invoices')
        ->call('confirmDuplicate', $invoice->id)
        ->assertSet('duplicateInvoiceId', $invoice->id)
        ->assertSet('duplicateInvoiceNumber', $invoice->full_number)
        ->call('duplicate')
        ->assertHasNoErrors();

    $draft = $this->team->invoices()->where('status', Invoice::STATUS_DRAFT)->first();

    expect($draft)->not->toBeNull()
        ->and($draft->customer_id)->toBe($invoice->customer_id);

    $component->assertRedirect(route('invoices.create', ['invoice' => $draft->ulid]));
});

it('shows the payment status of an issued invoice', function () {
    $this->team->update(['monthly_invoice_limit' => 10]);

    $invoice = app(InvoiceIssuanceService::class)->issue(draftInvoice($this->team));

    Livewire::actingAs($this->user)
        ->test('pages::invoices')
        ->assertSee('Payment pending')
        ->assertDontSee('mailto:', false);

    $invoice->update(['paid_at' => now()]);

    app()->setLocale('es');

    Livewire::actingAs($this->user)
        ->test('pages::invoices')
        ->assertSee('Pagada');
});

it('shows the rectificative and issued tags without a payment status', function () {
    $this->team->update(['monthly_invoice_limit' => 10]);

    $original = app(InvoiceIssuanceService::class)->issue(draftInvoice($this->team));

    $original->update(['paid_at' => now()]);

    app(InvoiceIssuanceService::class)->issue(draftInvoice($this->team, [
        'rectifies_invoice_id' => $original->id,
        'rectification_type' => Invoice::RECTIFICATION_SUBSTITUTION,
        'rectification_reason' => 'Wrong amount',
    ]));

    app()->setLocale('es');

    Livewire::actingAs($this->user)
        ->test('pages::invoices')
        ->assertSee('Rectificativa')
        ->assertSee('Emitida')
        ->assertDontSee('Pago pendiente');
});

it('does not show the view action of an invoice of another team', function () {
    $otherTeam = User::factory()->withPersonalTeam()->create()->currentTeam;

    InvoiceSetting::factory()->for($otherTeam)->create();

    $invoice = app(InvoiceIssuanceService::class)->issue(draftInvoice($otherTeam));

    Livewire::actingAs($this->user)
        ->test('pages::invoices')
        ->assertDontSee(route('invoices.show', $invoice), false);
});

it('does not show the duplicate action for a draft invoice', function () {
    $draft = draftInvoice($this->team);

    Livewire::actingAs($this->user)
        ->test('pages::invoices')
        ->assertDontSee('confirmDuplicate('.$draft->id.')', false);
});

it('does not show the links for a draft invoice', function () {
    draftInvoice($this->team);

    Livewire::actingAs($this->user)
        ->test('pages::invoices')
        ->assertDontSee(route('invoices.pdf', ['invoice' => 'x']), false)
        ->assertDontSee('mailto:', false)
        ->assertSee('Draft');
});
