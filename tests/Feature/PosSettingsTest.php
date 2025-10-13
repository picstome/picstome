<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;

uses(RefreshDatabase::class);

it('updates stripe currency with valid value', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->currentTeam;
    expect($team->stripe_currency)->not->toBe('mxn');

    $component = Volt::actingAs($user)->test('pages.branding.pos')
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

    $component = Volt::actingAs($user)->test('pages.branding.pos')
        ->set('form.stripe_currency', $invalidCurrency)
        ->call('save')
        ->assertHasErrors(['form.stripe_currency' => 'in']);

    $team->refresh();

    expect($team->stripe_currency)->not->toEqual($invalidCurrency);
});

it('can update the show_pay_button setting', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->currentTeam;
    expect($team->show_pay_button)->toBeTrue();

    $component = Volt::actingAs($user)->test('pages.branding.pos')
        ->set('form.show_pay_button', false)
        ->call('save');

    $component->assertHasNoErrors();

    $team->refresh();
    expect($team->show_pay_button)->toBeFalse();

    $component = Volt::actingAs($user)->test('pages.branding.pos')
        ->set('form.show_pay_button', true)
        ->call('save');

    $component->assertHasNoErrors();

    $team->refresh();
    expect($team->show_pay_button)->toBeTrue();
});
