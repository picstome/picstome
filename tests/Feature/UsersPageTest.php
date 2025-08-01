<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('render the users page', function () {
    $user = User::factory()->withPersonalTeam()->create([
        'email' => 'admin@example.com',
    ]);

    $response = actingAs($user)->get('/users');

    $response->assertStatus(200);
});

it('dies if user is not admin', function () {
    $user = User::factory()->withPersonalTeam()->create([
        'email' => 'user@example.com',
    ]);

    $response = actingAs($user)->get('/users');

    $response->assertStatus(403);
});
