<?php

use App\Models\InvoiceSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->withPersonalTeam()->create();
    $this->team = $this->user->currentTeam;
});

it('creates the settings with the defaults on the first visit', function () {
    Livewire::actingAs($this->user)
        ->test('pages::invoices.settings')
        ->assertHasNoErrors()
        ->assertSet('form.currency', 'EUR')
        ->assertSet('form.vat_percent', '21')
        ->assertSet('form.series', 'F')
        ->assertSet('form.rectification_series', 'R');

    expect($this->team->invoiceSetting)->not->toBeNull();
});

it('saves the issuer fiscal data', function () {
    Livewire::actingAs($this->user)
        ->test('pages::invoices.settings')
        ->set('form.legal_name', 'Acme Studio SL')
        ->set('form.tax_id', 'B12345678')
        ->set('form.address', 'Calle Mayor 1')
        ->set('form.city', 'Madrid')
        ->set('form.postal_code', '28001')
        ->set('form.country', 'ES')
        ->call('save')
        ->assertHasNoErrors();

    $settings = $this->team->invoiceSetting->fresh();

    expect($settings->legal_name)->toBe('Acme Studio SL')
        ->and($settings->tax_id)->toBe('B12345678')
        ->and($settings->country)->toBe('ES')
        ->and($settings->hasCompleteIssuerData())->toBeTrue();
});

it('requires the fiscal data of the issuer', function () {
    Livewire::actingAs($this->user)
        ->test('pages::invoices.settings')
        ->set('form.legal_name', '')
        ->call('save')
        ->assertHasErrors(['form.legal_name' => 'required']);
});

it('rejects an invalid series', function () {
    Livewire::actingAs($this->user)
        ->test('pages::invoices.settings')
        ->set('form.legal_name', 'Acme Studio SL')
        ->set('form.tax_id', 'B12345678')
        ->set('form.address', 'Calle Mayor 1')
        ->set('form.city', 'Madrid')
        ->set('form.postal_code', '28001')
        ->set('form.country', 'ES')
        ->set('form.series', 'F 2026')
        ->call('save')
        ->assertHasErrors(['form.series' => 'regex']);
});

it('requires authentication', function () {
    $this->get(route('invoices.settings'))->assertRedirect(route('login'));
});

it('does not expose the settings of another team', function () {
    $otherTeam = User::factory()->withPersonalTeam()->create()->currentTeam;

    InvoiceSetting::factory()->for($otherTeam)->create(['legal_name' => 'Other Studio']);

    Livewire::actingAs($this->user)
        ->test('pages::invoices.settings')
        ->assertSet('form.legal_name', '');
});
