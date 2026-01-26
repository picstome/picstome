<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

use function Pest\Laravel\assertAuthenticated;
use function Pest\Laravel\assertGuest;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

test('login screen can be rendered', function () {
    $response = get('/login');

    $response->assertStatus(200);
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->withPersonalTeam()->create([
        'email' => 'test@example.com',
        'password' => Hash::make('password'),
    ]);

    $component = Livewire::test('pages.login')
        ->set('form.email', 'test@example.com')
        ->set('form.password', 'password');

    $component->call('login');

    $component
        ->assertHasNoErrors()
        ->assertRedirect('/');

    assertAuthenticated();
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->withPersonalTeam()->create([
        'email' => 'test@example.com',
        'password' => Hash::make('password'),
    ]);

    $component = Livewire::test('pages.login')
        ->set('form.email', 'test@example.com')
        ->set('form.password', 'invalid-password');

    $component->call('login');

    $component
        ->assertHasErrors()
        ->assertNoRedirect();

    assertGuest();
});
