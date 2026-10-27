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
        ->assertSee(route('invoices'), false)
        ->assertSee(route('payments'), false)
        ->assertSee(route('tools.calculator'), false)
        ->assertSee(route('tools.black-screen'), false)
        ->assertSee('data-black-screen-shortcut', false)
        ->assertDontSee('https://picstome.com/blackscreen/', false)
        ->assertDontSee(route('stripe.connect'), false)
        ->assertDontSee('Install the app');
});

it('does not show the external invoice generator anymore', function () {
    $user = User::factory()->withPersonalTeam()->create();

    actingAs($user)
        ->get('/dashboard')
        ->assertOk()
        ->assertDontSee('picstome.com/invoice-generator/', false)
        ->assertDontSee('Invoice Generator');
});

it('collapses the tools group by default and expands it on tool pages', function () {
    $user = User::factory()->withPersonalTeam()->create();

    $dashboard = actingAs($user)->get('/dashboard')->assertOk();

    expect($dashboard->getContent())->toContain('data-sidebar-tools="closed"');

    $tools = actingAs($user)->get(route('tools.calculator'))->assertOk();

    expect($tools->getContent())->toContain('data-sidebar-tools="open"');
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
        ->assertDontSee(route('users'), false)
        ->assertDontSee(route('admin.stats'), false)
        ->assertDontSee('Statistics');

    config(['picstome.admin_emails' => [$user->email]]);

    actingAs($user)
        ->get('/dashboard')
        ->assertOk()
        ->assertSee(route('users'), false)
        ->assertSee(route('admin.stats'), false)
        ->assertSee('Statistics');
});
