<?php

use App\Models\InvoiceSetting;
use App\Models\User;
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
    config(['picstome.personal_team_monthly_invoice_limit' => 10]);

    $invoice = app(App\Services\InvoiceIssuanceService::class)->issue(draftInvoice($this->team));

    Livewire::actingAs($this->user)
        ->test('pages::invoices')
        ->assertSee(route('invoices.pdf', $invoice), false)
        ->assertSee(route('invoices.public.show', $invoice->public_token), false);
});

it('does not show the links for a draft invoice', function () {
    draftInvoice($this->team);

    Livewire::actingAs($this->user)
        ->test('pages::invoices')
        ->assertDontSee(route('invoices.pdf', ['invoice' => 'x']), false)
        ->assertSee('Draft');
});
