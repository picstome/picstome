<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;

use function Pest\Laravel\assertAuthenticated;

uses(RefreshDatabase::class);

it('allows guests to register with valid data', function () {
    $component = Volt::test('register-modal')
        ->set('name', 'Test User')
        ->set('email', 'test@example.com')
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->call('register');

    expect(User::count())->toBe(1);
});

it('authenticates the user after successful registration', function () {
    $component = Volt::test('register-modal')
        ->set('name', 'Auth User')
        ->set('email', 'authuser@example.com')
        ->set('password', 'password123')
        ->set('password_confirmation', 'password123')
        ->call('register');

    $user = User::where('email', 'authuser@example.com')->first();

    assertAuthenticated()->assertAuthenticatedAs($user);
});

it('creates a personal team for the user upon registration', function () {
    $component = Volt::test('register-modal')
        ->set('name', 'Team User')
        ->set('email', 'teamuser@example.com')
        ->set('password', 'password123')
        ->set('password_confirmation', 'password123')
        ->call('register');

    $user = User::where('email', 'teamuser@example.com')->first();
    $team = $user->currentTeam;

    expect($team)->not->toBeNull();
    expect($team->name)->toBe("{$user->name}'s Studio");
    expect($team->personal_team)->toBeTrue();
});

it('gives the personal team 1GB of storage upon creation', function () {
    $component = Volt::test('register-modal')
        ->set('name', 'Storage User')
        ->set('email', 'storageuser@example.com')
        ->set('password', 'password123')
        ->set('password_confirmation', 'password123')
        ->call('register');

    $user = User::where('email', 'storageuser@example.com')->first();
    $team = $user->currentTeam;
    expect($team->storage_limit)->toBe(10737418240); // 10GB in bytes
});
