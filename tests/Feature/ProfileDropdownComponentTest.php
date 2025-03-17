<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertGuest;

uses(RefreshDatabase::class);

test('users can logout', function () {
    $user = User::factory()->withPersonalTeam()->create();

    actingAs($user);

    $component = Volt::test('profile-dropdown');

    $component->call('logout');

    $component
        ->assertHasNoErrors()
        ->assertRedirect('/');

    assertGuest();
});
