<?php

use App\Models\Payment;
use App\Models\User;
use App\Services\PaymentPlanLimitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Cashier\Subscription;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->withPersonalTeam()->create();
    $this->team = $this->user->currentTeam;
});

it('lets a free team receive one payment per month', function () {
    $this->team->update(['monthly_payment_limit' => 1]);

    $limits = app(PaymentPlanLimitService::class);

    expect($limits->canReceive($this->team))->toBeTrue();
    expect($limits->remainingThisMonth($this->team))->toBe(1);

    Payment::factory()->for($this->team)->create(['completed_at' => now()]);

    expect($limits->canReceive($this->team->fresh()))->toBeFalse();
    expect($limits->remainingThisMonth($this->team->fresh()))->toBe(0);
});

it('does not count abandoned payments or payments from previous months', function () {
    $this->team->update(['monthly_payment_limit' => 1]);

    Payment::factory()->for($this->team)->create(['completed_at' => null]);
    Payment::factory()->for($this->team)->create(['completed_at' => now()->subMonthNoOverflow()]);

    expect(app(PaymentPlanLimitService::class)->canReceive($this->team->fresh()))->toBeTrue();
});

it('never limits subscribed teams', function () {
    $this->team->update(['monthly_payment_limit' => 1]);

    Subscription::factory()->for($this->team, 'owner')->create();
    Payment::factory()->for($this->team)->create(['completed_at' => now()]);

    expect(app(PaymentPlanLimitService::class)->canReceive($this->team->fresh()))->toBeTrue();
    expect(app(PaymentPlanLimitService::class)->remainingThisMonth($this->team->fresh()))->toBeNull();
});

it('never limits lifetime teams', function () {
    $this->team->update(['monthly_payment_limit' => 1, 'lifetime_at' => now()]);

    Payment::factory()->for($this->team)->create(['completed_at' => now()]);

    expect(app(PaymentPlanLimitService::class)->canReceive($this->team->fresh()))->toBeTrue();
});

it('treats a null limit as unlimited', function () {
    $this->team->update(['monthly_payment_limit' => null]);

    Payment::factory()->for($this->team)->count(5)->create(['completed_at' => now()]);

    expect(app(PaymentPlanLimitService::class)->canReceive($this->team->fresh()))->toBeTrue();
});

it('blocks generating a payment link once the monthly limit is reached', function () {
    $this->team->update(['monthly_payment_limit' => 1]);
    Payment::factory()->for($this->team)->create(['completed_at' => now()]);

    Livewire::actingAs($this->user)
        ->test('pages::payments')
        ->set('linkForm.amount', 100)
        ->set('linkForm.description', 'Session')
        ->call('generatePaymentLink')
        ->assertSet('paymentLink', null);
});

it('generates a payment link while the team still has quota', function () {
    $this->team->update(['monthly_payment_limit' => 1]);

    Livewire::actingAs($this->user)
        ->test('pages::payments')
        ->set('linkForm.amount', 100)
        ->set('linkForm.description', 'Session')
        ->call('generatePaymentLink')
        ->assertNotSet('paymentLink', null);
});

it('tells the client the payment cannot be processed when the quota is exhausted', function () {
    $this->team->update([
        'monthly_payment_limit' => 1,
        'handle' => 'studio',
        'stripe_onboarded' => true,
    ]);

    Payment::factory()->for($this->team)->create(['completed_at' => now()]);

    Livewire::test('pages::pay.show', [
        'handle' => 'studio',
        'amount' => 100,
        'description' => 'Session',
    ])->assertSet('paymentUnavailable', true)
        ->assertSee('Payment cannot be processed at the moment. Please contact us.');
});

it('hides the pay button on the public profile when the quota is exhausted', function () {
    $this->team->update([
        'monthly_payment_limit' => 1,
        'handle' => 'studio',
        'show_pay_button' => true,
    ]);

    Payment::factory()->for($this->team)->create(['completed_at' => now()]);

    Livewire::test('pages::handle.show', ['handle' => 'studio'])
        ->assertSet('paymentsAvailable', false)
        ->assertDontSee('generate-payment-link');
});
