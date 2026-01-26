<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertAuthenticated;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

it('allows guests to view the registration page', function () {
    get(route('register'))->assertStatus(200);
});

it('allows guests to register with valid data', function () {
    $component = Livewire::test('pages::register')
        ->set('name', 'Test User')
        ->set('email', 'test@example.com')
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->set('terms', true)
        ->call('register');

    expect(User::count())->toBe(1);
});

it('authenticates the user after successful registration', function () {
    $component = Livewire::test('pages::register')
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
    $component = Livewire::test('pages::register')
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
    $component = Livewire::test('pages::register')
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

it('creates a personal team with a handle upon registration', function () {
    $component = Livewire::test('pages::register')
        ->set('name', 'Handle User')
        ->set('email', 'handleuser@example.com')
        ->set('password', 'password123')
        ->set('password_confirmation', 'password123')
        ->set('terms', true)
        ->call('register');

    $user = User::where('email', 'handleuser@example.com')->first();
    $team = $user->currentTeam;

    expect($team->handle)->not->toBeNull();
    expect($team->handle)->toBe('handleuser');
});

it('generates unique handles when registering users with similar names', function () {
    Livewire::test('pages::register')
        ->set('name', 'Test User')
        ->set('email', 'testuser1@example.com')
        ->set('password', 'password123')
        ->set('password_confirmation', 'password123')
        ->set('terms', true)
        ->call('register');

    Livewire::test('pages::register')
        ->set('name', 'Test User')
        ->set('email', 'testuser2@example.com')
        ->set('password', 'password123')
        ->set('password_confirmation', 'password123')
        ->set('terms', true)
        ->call('register');

    $user1 = User::where('email', 'testuser1@example.com')->first();
    $user2 = User::where('email', 'testuser2@example.com')->first();

    expect($user1->currentTeam->handle)->toBe('testuser');
    expect($user2->currentTeam->handle)->toBe('testuser1');
});

it('stores referral code when provided during registration', function () {
    Livewire::test('pages::register')
        ->set('name', 'Referral User')
        ->set('email', 'referral@example.com')
        ->set('password', 'password123')
        ->set('password_confirmation', 'password123')
        ->set('terms', true)
        ->call('register');

    $user = User::where('email', 'referral@example.com')->first();

    expect($user->referral_code)->toBeNull();
});

it('stores referral code from query parameter', function () {
    Livewire::test('pages::register')
        ->set('referral_code', 'CHEMA')
        ->set('name', 'Referral User 2')
        ->set('email', 'referral2@example.com')
        ->set('password', 'password123')
        ->set('password_confirmation', 'password123')
        ->set('terms', true)
        ->call('register');

    $user = User::where('email', 'referral2@example.com')->first();

    expect($user->referral_code)->toBe('CHEMA');
});
