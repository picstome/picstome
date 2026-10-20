<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

it('shows the black screen tool to authenticated users', function () {
    $user = User::factory()->withPersonalTeam()->create();

    actingAs($user)
        ->get(route('tools.black-screen'))
        ->assertOk()
        ->assertSee('Black Screen')
        ->assertSee('Turn your screen completely black.')
        ->assertSee('Go Black')
        ->assertSee('Press ESC or click anywhere to exit.')
        ->assertSee('data-black-screen-start', false)
        ->assertSee('data-black-screen-overlay', false)
        ->assertSee('black-screen:start', false);
});

it('shows the black screen overlay and shortcut on app pages', function () {
    $user = User::factory()->withPersonalTeam()->create();

    actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('data-black-screen-overlay', false)
        ->assertSee('data-black-screen-shortcut', false);
});

it('redirects guests to the login page', function () {
    get(route('tools.black-screen'))
        ->assertRedirect(route('login'));
});

it('redirects unverified users to email verification', function () {
    $user = User::factory()->withPersonalTeam()->unverified()->create();

    actingAs($user)
        ->get(route('tools.black-screen'))
        ->assertRedirect(route('verification.notice'));
});

it('localizes the black screen tool in Spanish', function () {
    $user = User::factory()->withPersonalTeam()->create(['language' => 'es']);

    actingAs($user)
        ->get(route('tools.black-screen'))
        ->assertOk()
        ->assertSee('Pantalla Negra')
        ->assertSee('Poner en negro')
        ->assertSee('Pulsa ESC o haz clic en cualquier lugar para salir.');
});
