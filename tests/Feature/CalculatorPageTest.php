<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

it('shows the calculator to authenticated users', function () {
    $user = User::factory()->withPersonalTeam()->create();

    actingAs($user)
        ->get(route('tools.calculator'))
        ->assertOk()
        ->assertSee('Session price calculator')
        ->assertSee('Recommended price')
        ->assertDontSee('<iframe', false);
});

it('redirects guests to the login page', function () {
    get(route('tools.calculator'))
        ->assertRedirect(route('login'));
});

it('redirects unverified users to email verification', function () {
    $user = User::factory()->withPersonalTeam()->unverified()->create();

    actingAs($user)
        ->get(route('tools.calculator'))
        ->assertRedirect(route('verification.notice'));
});

it('localizes the calculator in Spanish', function () {
    $user = User::factory()->withPersonalTeam()->create(['language' => 'es']);

    actingAs($user)
        ->get(route('tools.calculator'))
        ->assertOk()
        ->assertSee('Calculadora de precios de sesiones')
        ->assertSee('Gastos fijos')
        ->assertSee('Tiempo por sesión');

    expect($user->currentTeam->calculatorState->state['fixed_expenses'][0]['name'])
        ->toBe('Cuota de Autónomos');
});
