<?php

use App\Models\Payment;
use App\Models\User;
use App\Services\FakePaymentService;
use Facades\App\Services\StripeConnectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->withPersonalTeam()->create();
    $this->team = $this->user->currentTeam;
    $this->team->update(['handle' => 'tester', 'stripe_currency' => 'eur']);
    $this->team->markOnboarded();
});

it('creates the payment without Stripe when the fake mode is enabled', function () {
    config(['picstome.payments_fake' => true]);

    Livewire::test('pages::pay.show', [
        'handle' => $this->team->handle,
        'amount' => 150,
        'description' => 'Sesión de prueba',
    ])
        ->assertOk()
        ->set('email', 'cliente@example.com')
        ->call('pay')
        ->assertRedirect(route('handle.pay.success', [
            'handle' => $this->team->handle,
            'fake' => 1,
        ]));

    $payment = $this->team->payments()->first();

    expect($payment)->not->toBeNull();
    expect($payment->amount)->toBe(15000);
    expect($payment->currency)->toBe('eur');
    expect($payment->description)->toBe('Sesión de prueba');
    expect($payment->customer_email)->toBe('cliente@example.com');
    expect($payment->stripe_payment_intent_id)->toStartWith('fake_');
    expect($payment->completed_at)->not->toBeNull();

    expect($this->team->customers()->where('email', 'cliente@example.com')->exists())->toBeTrue();
});

it('shows the success page for fake payments', function () {
    config(['picstome.payments_fake' => true]);

    Livewire::test('pages::pay.success', [
        'handle' => $this->team->handle,
        'fake' => true,
    ])->assertOk()->assertSee('Payment Successful!');
});

it('creates a payment for the team when paying from the public profile', function () {
    config(['picstome.payments_fake' => true]);

    Livewire::test('pages::handle.show', ['handle' => $this->team->handle])
        ->assertOk()
        ->set('amount', 80)
        ->set('description', 'Bono')
        ->call('checkout')
        ->assertRedirect(route('handle.pay.success', [
            'handle' => $this->team->handle,
            'fake' => 1,
        ]));

    expect(Payment::where('team_id', $this->team->id)->count())->toBe(1);
});

it('never enables the fake mode outside the local environment', function () {
    // The config only reads the flag when APP_ENV=local, and the runtime
    // guard refuses to run it in production.
    $config = require config_path('picstome.php');

    expect($config['payments_fake'])->toBeFalse();

    config(['picstome.payments_fake' => true]);
    app()->detectEnvironment(fn () => 'production');

    expect(FakePaymentService::enabled())->toBeFalse();

    app()->detectEnvironment(fn () => 'testing');

    // The debug flag is an independent signal: production always disables it.
    config(['app.debug' => false]);

    expect(FakePaymentService::enabled())->toBeFalse();

    config(['app.debug' => true]);
});

it('uses Stripe when the fake mode is disabled', function () {
    config(['picstome.payments_fake' => false]);

    StripeConnectService::shouldReceive('createCheckoutSession')
        ->once()
        ->andReturn('https://checkout.stripe.test/session');

    Livewire::test('pages::pay.show', [
        'handle' => $this->team->handle,
        'amount' => 150,
        'description' => 'Sesión de prueba',
    ])->assertRedirect('https://checkout.stripe.test/session');

    expect(Payment::where('team_id', $this->team->id)->count())->toBe(0);
});
