<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertAuthenticated;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

it('allows guests to view the registration page', function () {
    get(route('register'))->assertStatus(200);
});

it('allows guests to register with valid data', function () {
    $component = Volt::test('pages.register')
        ->set('name', 'Test User')
        ->set('email', 'test@example.com')
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->set('terms', true)
        ->call('register');

    expect(User::count())->toBe(1);
});

it('authenticates the user after successful registration', function () {
    $component = Volt::test('pages.register')
        ->set('name', 'Auth User')
        ->set('email', 'authuser@example.com')
        ->set('password', 'password123')
        ->set('password_confirmation', 'password123')
        ->set('terms', true)
        ->call('register');

    $user = User::where('email', 'authuser@example.com')->first();

    assertAuthenticated()->assertAuthenticatedAs($user);
});

it('redirects authenticated users away from the registration page', function () {
    $user = User::factory()->create();

    actingAs($user)->get('/register')->assertRedirect();
});

it('creates a personal team for the user upon registration', function () {
    $component = Volt::test('pages.register')
        ->set('name', 'Team User')
        ->set('email', 'teamuser@example.com')
        ->set('password', 'password123')
        ->set('password_confirmation', 'password123')
        ->set('terms', true)
        ->call('register');

    $user = User::where('email', 'teamuser@example.com')->first();
    $team = $user->currentTeam;

    expect($team)->not->toBeNull();
    expect($team->name)->toBe("{$user->name}'s Studio");
    expect($team->personal_team)->toBeTrue();
});

it('gives the personal team 1GB of storage upon creation', function () {
    $component = Volt::test('pages.register')
        ->set('name', 'Storage User')
        ->set('email', 'storageuser@example.com')
        ->set('password', 'password123')
        ->set('password_confirmation', 'password123')
        ->set('terms', true)
        ->call('register');

    $user = User::where('email', 'storageuser@example.com')->first();
    $team = $user->currentTeam;
    expect($team->storage_limit)->toBe(1073741824); // 1GB in bytes
});
