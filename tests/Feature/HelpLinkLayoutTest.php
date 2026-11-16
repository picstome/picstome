<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('renders the contextual help link in the application layout', function () {
    $user = User::factory()->withPersonalTeam()->create();

    actingAs($user)
        ->get(route('galleries'))
        ->assertOk()
        ->assertSee('data-help-link', false)
        ->assertSee('https://docs.picstome.com/start/first-gallery/', false)
        ->assertSee('target="_blank"', false)
        ->assertSee('Get help');
});

it('renders the Spanish documentation link for a Spanish user', function () {
    $user = User::factory()->withPersonalTeam()->create(['language' => 'es']);

    actingAs($user)
        ->get(route('galleries'))
        ->assertOk()
        ->assertSee('https://docs.picstome.com/es/start/first-gallery/', false)
        ->assertSee('Obtén ayuda');
});
