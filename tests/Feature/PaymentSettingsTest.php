<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('updates stripe currency with valid value', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->currentTeam;
    expect($team->stripe_currency)->not->toBe('mxn');

    $component = Livewire::actingAs($user)->test('pages.branding.payments')
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

    $component = Livewire::actingAs($user)->test('pages.branding.payments')
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

    $component = Livewire::actingAs($user)->test('pages.branding.payments')
        ->set('form.show_pay_button', false)
        ->call('save');

    $component->assertHasNoErrors();

    $team->refresh();
    expect($team->show_pay_button)->toBeFalse();

    $component = Livewire::actingAs($user)->test('pages.branding.payments')
        ->set('form.show_pay_button', true)
        ->call('save');

    $component->assertHasNoErrors();

    $team->refresh();
    expect($team->show_pay_button)->toBeTrue();
});

it('can disconnect the Stripe account', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->currentTeam;
    $team->update([
        'stripe_account_id' => 'acct_123456789',
        'stripe_onboarded' => true,
    ]);
    expect($team->stripe_account_id)->not->toBeNull();
    expect($team->stripe_onboarded)->toBeTrue();

    $component = Livewire::actingAs($user)->test('pages.branding.payments')
        ->call('disconnectStripe');

    $component->assertHasNoErrors();

    $team->refresh();
    expect($team->stripe_account_id)->toBeNull();
    expect($team->stripe_onboarded)->toBeFalse();
});

it('can disconnect the Stripe test account when test mode is enabled', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->currentTeam;
    $team->update([
        'stripe_test_mode' => true,
        'stripe_test_account_id' => 'acct_test_987654321',
        'stripe_test_onboarded' => true,
    ]);
    expect($team->stripe_test_mode)->toBeTrue();
    expect($team->stripe_test_account_id)->not->toBeNull();
    expect($team->stripe_test_onboarded)->toBeTrue();

    $component = Livewire::actingAs($user)->test('pages.branding.payments')
        ->call('disconnectStripe');

    $component->assertHasNoErrors();

    $team->refresh();
    expect($team->stripe_test_account_id)->toBeNull();
    expect($team->stripe_test_onboarded)->toBeFalse();
});

it('can toggle stripe_test_mode', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->currentTeam;
    expect($team->stripe_test_mode)->toBeFalse();

    $component = Livewire::actingAs($user)->test('pages.branding.payments')
        ->set('form.stripe_test_mode', true)
        ->call('save');

    $component->assertHasNoErrors();
    $team->refresh();
    expect($team->stripe_test_mode)->toBeTrue();

    $component = Livewire::actingAs($user)->test('pages.branding.payments')
        ->set('form.stripe_test_mode', false)
        ->call('save');

    $component->assertHasNoErrors();
    $team->refresh();
    expect($team->stripe_test_mode)->toBeFalse();
});
