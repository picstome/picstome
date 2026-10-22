<?php

use App\Models\User;
use App\Services\StripeConnectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->withPersonalTeam()->create();
    $this->team = $this->user->currentTeam;
});

it('reads the email registered in the connected account', function () {
    $this->team->update(['stripe_account_id' => 'acct_123']);

    Http::fake([
        'api.stripe.com/v1/accounts/acct_123' => Http::response(['email' => 'old@example.com']),
    ]);

    expect(app(StripeConnectService::class)->accountEmail($this->team))->toBe('old@example.com');
});

it('returns no email when the account cannot be read', function () {
    $this->team->update(['stripe_account_id' => 'acct_123']);

    Http::fake([
        'api.stripe.com/v1/accounts/acct_123' => Http::response(['error' => ['message' => 'nope']], 403),
    ]);

    expect(app(StripeConnectService::class)->accountEmail($this->team))->toBeNull();
});

it('returns no email when the team has no connected account', function () {
    Http::fake();

    expect(app(StripeConnectService::class)->accountEmail($this->team))->toBeNull();

    Http::assertNothingSent();
});

it('marks the team as onboarded when Stripe already has the account ready', function () {
    $this->team->update(['stripe_account_id' => 'acct_123']);

    Http::fake([
        'api.stripe.com/v1/accounts/acct_123' => Http::response([
            'email' => 'owner@example.com',
            'charges_enabled' => true,
        ]),
    ]);

    expect(app(StripeConnectService::class)->syncOnboardingStatus($this->team))->toBeTrue();

    expect($this->team->fresh()->stripe_onboarded)->toBeTrue();
});

it('keeps the team as not onboarded while Stripe is still pending', function () {
    $this->team->update(['stripe_account_id' => 'acct_123']);

    Http::fake([
        'api.stripe.com/v1/accounts/acct_123' => Http::response([
            'email' => 'owner@example.com',
            'charges_enabled' => false,
        ]),
    ]);

    expect(app(StripeConnectService::class)->syncOnboardingStatus($this->team))->toBeFalse();

    expect($this->team->fresh()->stripe_onboarded)->toBeFalse();
});

it('warns when Stripe is still using an old email', function () {
    $this->team->update(['stripe_account_id' => 'acct_123']);

    Http::fake([
        'api.stripe.com/v1/account_links' => Http::response(['url' => 'https://stripe.test/onboarding']),
        'api.stripe.com/v1/accounts/acct_123' => Http::response(['email' => 'old@example.com']),
    ]);

    $this->actingAs($this->user)
        ->get('/stripe-connect')
        ->assertOk()
        ->assertSee('old@example.com');
});
