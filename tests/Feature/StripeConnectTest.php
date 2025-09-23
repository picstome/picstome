<?php

use App\Models\User;
use Facades\App\Services\StripeConnectService;
use Livewire\Volt\Volt;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('sets the onboarding url', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->currentTeam;
    $mockedUrl = 'https://stripe.test/onboarding';

    StripeConnectService::shouldReceive('createOnboardingLink')
        ->once()
        ->with($team)
        ->andReturn($mockedUrl);

    $component = Volt::actingAs($user)->test('pages.stripe-connect.index')->assertOk();

    expect($component->onboardingUrl)->toBe($mockedUrl);
});

it('sets onboarding complete when team has completed onboarding', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->currentTeam;
    $team->markOnboarded();

    $component = Volt::actingAs($user)->test('pages.stripe-connect.return')->assertOk();

    expect($component->onboardingComplete)->toBeTrue();
    expect($component->onboardingUrl)->toBeNull();
});

it('marks onboarding complete when onboarding is complete', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->currentTeam;

    StripeConnectService::shouldReceive('isOnboardingComplete')
        ->once()
        ->with($team)
        ->andReturn(true);
    StripeConnectService::shouldReceive('createOnboardingLink')->never();

    $component = Volt::actingAs($user)->test('pages.stripe-connect.return')->assertOk();

    expect($component->onboardingComplete)->toBeTrue();
    expect($component->onboardingUrl)->toBeNull();
});

it('sets onboarding not complete when onboarding is incomplete', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->currentTeam;
    $mockedUrl = 'https://stripe.test/onboarding-return';

    StripeConnectService::shouldReceive('isOnboardingComplete')
        ->once()
        ->with($team)
        ->andReturn(false);
    StripeConnectService::shouldReceive('createOnboardingLink')
        ->once()
        ->with($team)
        ->andReturn($mockedUrl);

    $component = Volt::actingAs($user)->test('pages.stripe-connect.return')->assertOk();

    expect($component->onboardingComplete)->toBeFalse();
    expect($component->onboardingUrl)->toBe($mockedUrl);
});

it('redirects to onboarding url on refresh page', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->currentTeam;
    $mockedUrl = 'https://stripe.test/onboarding-refresh';

    StripeConnectService::shouldReceive('createOnboardingLink')
        ->once()
        ->with($team)
        ->andReturn($mockedUrl);

    $response = actingAs($user)->get('/stripe-connect/refresh');

    $response->assertRedirect($mockedUrl);
});
