<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('shows the reorganized main navigation', function () {
    $user = User::factory()->withPersonalTeam()->create();

    actingAs($user)
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('Public Profile')
        ->assertSee('Manage')
        ->assertSee('Tools')
        ->assertSee('Black Screen')
        ->assertSee('Photo Stats')
        ->assertSee('Rater')
        ->assertSee(route('public-profile'), false)
        ->assertSee(route('portfolio'), false)
        ->assertSee(route('customers'), false)
        ->assertSee(route('contracts'), false)
        ->assertSee(route('payments'), false)
        ->assertSee(route('tools.invoice-generator'), false)
        ->assertSee(route('tools.calculator'), false)
        ->assertSee('https://picstome.com/blackscreen/', false)
        ->assertDontSee(route('stripe.connect'), false)
        ->assertDontSee('Install the app');
});

it('collapses the tools group by default and expands it on tool pages', function () {
    $user = User::factory()->withPersonalTeam()->create();

    $dashboard = actingAs($user)->get('/dashboard')->assertOk();

    expect($dashboard->getContent())
        ->toContain('data-flux-navlist-group')
        ->not->toMatch('/\sopen\s+data-flux-navlist-group/');

    $tools = actingAs($user)->get(route('tools.calculator'))->assertOk();

    expect($tools->getContent())->toMatch('/\sopen\s+data-flux-navlist-group/');
});

it('localizes the external app links', function () {
    $user = User::factory()->withPersonalTeam()->create(['language' => 'es']);

    actingAs($user)
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('https://picstome.com/es/descargar/', false)
        ->assertSee('https://picstome.com/es/photo-stats/', false)
        ->assertSee('Pantalla Negra');
});

it('shows the admin navigation only to admins', function () {
    config(['picstome.admin_emails' => []]);

    $user = User::factory()->withPersonalTeam()->create();

    actingAs($user)
        ->get('/dashboard')
        ->assertOk()
        ->assertDontSee(route('users'), false);

    config(['picstome.admin_emails' => [$user->email]]);

    actingAs($user)
        ->get('/dashboard')
        ->assertOk()
        ->assertSee(route('users'), false);
});
