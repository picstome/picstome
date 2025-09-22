<?php

use App\Models\User;
use Facades\App\Services\StripeConnectService;
use Livewire\Volt\Volt;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('sets the onboardingUrl when StripeConnectService returns a URL', function () {
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

it('redirects to onboarding url on refresh page', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->currentTeam;
    $mockedUrl = 'https://stripe.test/onboarding-refresh';

    StripeConnectService::shouldReceive('createOnboardingLink')
        ->once()
        ->with($team)
        ->andReturn($mockedUrl);

    $response = $this->actingAs($user)->get('/stripe-connect/refresh');

    $response->assertRedirect($mockedUrl);
});
