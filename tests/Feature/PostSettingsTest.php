<?php

use App\Models\User;
use App\Models\Team;
use App\Services\StripeConnectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

it('updates stripe currency with valid value', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->currentTeam;
    expect($team->stripe_currency)->not->toBe('mxn');

    $component = Volt::actingAs($user)->test('pages.pos.settings')
        ->set('form.stripe_currency', 'mxn')
        ->call('save');

    $component->assertHasNoErrors();

    $team->refresh();

    expect($team->stripe_currency)->toEqual('mxn');
});

it('shows error for invalid stripe currency', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->currentTeam;
    $invalidCurrency = 'xxx';

    $component = Volt::actingAs($user)->test('pages.pos.settings')
        ->set('form.stripe_currency', $invalidCurrency)
        ->call('save')
        ->assertHasErrors(['form.stripe_currency' => 'in']);

    $team->refresh();

    expect($team->stripe_currency)->not->toEqual($invalidCurrency);
});
