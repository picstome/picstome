<?php

use App\Models\Invoice;
use App\Models\InvoiceSetting;
use App\Models\User;
use App\Services\InvoiceIssuanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
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
        ->assertSee('Draft');
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

it('shows the links to the pdf and the public page of an issued invoice', function () {
    $this->team->update(['monthly_invoice_limit' => 10]);

    $invoice = app(InvoiceIssuanceService::class)->issue(draftInvoice($this->team));

    Livewire::actingAs($this->user)
        ->test('pages::invoices')
        ->assertSee(route('invoices.pdf', $invoice), false)
        ->assertSee(route('invoices.public.show', $invoice->public_token), false);
});

it('duplicates an issued invoice from the list', function () {
    $this->team->update(['monthly_invoice_limit' => 10]);

    $invoice = app(InvoiceIssuanceService::class)->issue(draftInvoice($this->team));

    $component = Livewire::actingAs($this->user)
        ->test('pages::invoices')
        ->assertSee('duplicate('.$invoice->id.')', false)
        ->call('duplicate', $invoice->id)
        ->assertHasNoErrors();

    $draft = $this->team->invoices()->where('status', Invoice::STATUS_DRAFT)->first();

    expect($draft)->not->toBeNull()
        ->and($draft->customer_id)->toBe($invoice->customer_id);

    $component->assertRedirect(route('invoices.create', ['invoice' => $draft->ulid]));
});

it('does not duplicate an invoice of another team', function () {
    $otherTeam = User::factory()->withPersonalTeam()->create()->currentTeam;

    InvoiceSetting::factory()->for($otherTeam)->create();

    $invoice = app(InvoiceIssuanceService::class)->issue(draftInvoice($otherTeam));

    Livewire::actingAs($this->user)
        ->test('pages::invoices')
        ->call('duplicate', $invoice->id)
        ->assertStatus(404);

    expect($this->team->invoices()->count())->toBe(0);
});

it('does not show the duplicate action for a draft invoice', function () {
    $draft = draftInvoice($this->team);

    Livewire::actingAs($this->user)
        ->test('pages::invoices')
        ->assertDontSee('duplicate('.$draft->id.')', false);
});

it('does not show the links for a draft invoice', function () {
    draftInvoice($this->team);

    Livewire::actingAs($this->user)
        ->test('pages::invoices')
        ->assertDontSee(route('invoices.pdf', ['invoice' => 'x']), false)
        ->assertSee('Draft');
});
