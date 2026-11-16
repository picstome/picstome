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

    $component = Livewire::test('pages::login')
        ->set('form.email', 'test@example.com')
        ->set('form.password', 'password');

    $component->call('login');

    $component
        ->assertHasNoErrors()
        ->assertRedirect('/');

    assertAuthenticated();
});

test('returns to the intended URL with its query string after login', function () {
    User::factory()->withPersonalTeam()->create([
        'email' => 'intended@example.com',
        'password' => Hash::make('password'),
    ]);

    get('/galleries?foo=bar&utm_source=newsletter')->assertRedirect('/login');

    Livewire::test('pages::login')
        ->set('form.email', 'intended@example.com')
        ->set('form.password', 'password')
        ->call('login')
        ->assertRedirect('/galleries?foo=bar&utm_source=newsletter');

    assertAuthenticated();
});

test('unverified users must verify their email after login', function () {
    User::factory()->unverified()->create([
        'email' => 'unverified@example.com',
        'password' => Hash::make('password'),
    ]);

    Livewire::withQueryParams(['redirect' => '/subscribe?plan=pro&ps_from=bf2026'])
        ->test('pages::login')
        ->set('form.email', 'unverified@example.com')
        ->set('form.password', 'password')
        ->call('login')
        ->assertRedirect(route('verification.notice'));

    assertAuthenticated();
    expect(session('verification.redirect'))->toBe('/subscribe?plan=pro&ps_from=bf2026');
});

test('keeps the redirect when switching from login to register', function () {
    $redirect = '/subscribe?plan=pro&storage=250gb&ps_from=bf2026';

    $html = get('/login?redirect='.urlencode($redirect))->assertOk()->getContent();

    preg_match('/href="([^"]*\/register\?redirect=[^"]*)"/', $html, $matches);

    expect($matches[1] ?? null)->not->toBeNull();

    parse_str((string) parse_url(html_entity_decode($matches[1]), PHP_URL_QUERY), $query);

    expect($query['redirect'] ?? null)->toBe($redirect);
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->withPersonalTeam()->create([
        'email' => 'test@example.com',
        'password' => Hash::make('password'),
    ]);

    $component = Livewire::test('pages::login')
        ->set('form.email', 'test@example.com')
        ->set('form.password', 'invalid-password');

    $component->call('login');

    $component
        ->assertHasErrors()
        ->assertNoRedirect();

    assertGuest();
});
