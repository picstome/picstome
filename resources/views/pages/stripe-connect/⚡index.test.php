<?php

use App\Models\User;
use Facades\App\Services\StripeConnectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('sets the onboarding url', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->currentTeam;
    $mockedUrl = 'https://stripe.test/onboarding';

    StripeConnectService::shouldReceive('createOnboardingLink')
        ->once()
        ->with($team)
        ->andReturn($mockedUrl);

    $component = Livewire::actingAs($user)->test('pages::stripe-connect.index')->assertOk();

    expect($component->onboardingUrl)->toBe($mockedUrl);
});
