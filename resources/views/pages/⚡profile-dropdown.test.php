<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertGuest;

uses(RefreshDatabase::class);

test('account menu links to branding', function () {
    $user = User::factory()->withPersonalTeam()->create();

    actingAs($user);

    Livewire::test('profile-dropdown')
        ->assertSee(route('branding'), false)
        ->assertDontSee('Install the app');
});

test('users can logout', function () {
    $user = User::factory()->withPersonalTeam()->create();

    actingAs($user);

    $component = Livewire::test('profile-dropdown');

    $component->call('logout');

    $component
        ->assertHasNoErrors()
        ->assertRedirect('/');

    assertGuest();
});
