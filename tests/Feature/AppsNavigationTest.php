<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('shows localized app links in the sidebar', function () {
    $user = User::factory()->withPersonalTeam()->create(['language' => 'es']);

    actingAs($user)
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('https://picstome.com/es/descargar/', false)
        ->assertSee('https://picstome.com/es/photo-stats/', false)
        ->assertSee('https://github.com/picstome/picstome-rater/releases/latest', false);
});
