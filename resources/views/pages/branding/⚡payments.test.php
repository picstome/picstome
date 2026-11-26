<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('allows users to see the payments branding page', function () {
    $user = User::factory()->withPersonalTeam()->create();

    $response = actingAs($user)->get('/branding/payments');

    $response->assertStatus(200);
});

it('updates stripe currency with valid value', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->currentTeam;
    expect($team->stripe_currency)->not->toBe('mxn');

    $component = Livewire::actingAs($user)->test('pages::branding.payments')
        ->submit([
            'form.stripe_currency' => 'mxn',
        ], 'save');

    $component->assertHasNoErrors();

    $team->refresh();

    expect($team->stripe_currency)->toEqual('mxn');
});

it('shows error for invalid stripe currency', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->currentTeam;
    $invalidCurrency = 'xxx';

    $component = Livewire::actingAs($user)->test('pages::branding.payments')
        ->submit([
            'form.stripe_currency' => $invalidCurrency,
        ], 'save')
        ->assertHasErrors(['form.stripe_currency' => 'in']);

    $team->refresh();

    expect($team->stripe_currency)->not->toEqual($invalidCurrency);
});

it('can update the show_pay_button setting', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->currentTeam;
    expect($team->show_pay_button)->toBeTrue();

    $component = Livewire::actingAs($user)->test('pages::branding.payments')
        ->submit([
            'form.show_pay_button' => false,
        ], 'save');

    $component->assertHasNoErrors();

    $team->refresh();
    expect($team->show_pay_button)->toBeFalse();

    $component = Livewire::actingAs($user)->test('pages::branding.payments')
        ->submit([
            'form.show_pay_button' => true,
        ], 'save');

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

    $component = Livewire::actingAs($user)->test('pages::branding.payments')
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

    $component = Livewire::actingAs($user)->test('pages::branding.payments')
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

    $component = Livewire::actingAs($user)->test('pages::branding.payments')
        ->submit([
            'form.stripe_test_mode' => true,
        ], 'save');

    $component->assertHasNoErrors();
    $team->refresh();
    expect($team->stripe_test_mode)->toBeTrue();

    $component = Livewire::actingAs($user)->test('pages::branding.payments')
        ->submit([
            'form.stripe_test_mode' => false,
        ], 'save');

    $component->assertHasNoErrors();
    $team->refresh();
    expect($team->stripe_test_mode)->toBeFalse();
});

it('saves Bizum settings independently from the booking feature flags', function () {
    config([
        'picstome.bookings_feature_enabled' => false,
        'picstome.bookings_feature_admin_only' => true,
    ]);

    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->currentTeam;

    Livewire::actingAs($user)->test('pages::branding.payments')
        ->set('form.bizum_enabled', true)
        ->set('form.bizum_phone', '600123123')
        ->set('form.bizum_instructions', 'Send the Bizum and wait for confirmation.')
        ->call('save')
        ->assertHasNoErrors();

    $team->refresh();

    expect($team->canAcceptBizumPayments())->toBeTrue()
        ->and($team->bizum_phone)->toBe('600123123')
        ->and($team->bizum_instructions)->toBe('Send the Bizum and wait for confirmation.');
});

it('requires a phone when Bizum is enabled', function () {
    $user = User::factory()->withPersonalTeam()->create();

    Livewire::actingAs($user)->test('pages::branding.payments')
        ->set('form.bizum_enabled', true)
        ->set('form.bizum_phone', '')
        ->call('save')
        ->assertHasErrors(['form.bizum_phone']);
});

it('uses the same Bizum configuration as the booking settings', function () {
    config([
        'picstome.bookings_feature_enabled' => true,
        'picstome.bookings_feature_admin_only' => true,
        'picstome.admin_emails' => ['admin@example.com'],
    ]);

    $user = User::factory()->withPersonalTeam()->create(['email' => 'admin@example.com']);
    $team = $user->currentTeam;
    $team->update([
        'bizum_enabled' => true,
        'bizum_phone' => '600123123',
        'bizum_instructions' => 'Send the Bizum.',
        'phone_required' => true,
    ]);

    Livewire::actingAs($user)->test('pages::branding.payments')
        ->assertSee('These details are shared by bookings and payment links. Change them here for both.')
        ->assertSet('form.bizum_enabled', true)
        ->assertSet('form.bizum_phone', '600123123')
        ->assertSet('form.phone_required', true);

    Livewire::actingAs($user)->test('pages::bookings.settings')
        ->call('setTab', 'payments')
        ->assertSee('These details are shared by bookings and payment links. Change them here for both.')
        ->assertSet('settingsForm.bizum_enabled', true)
        ->assertSet('settingsForm.bizum_phone', '600123123')
        ->assertSet('settingsForm.phone_required', true);
});
