<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('shows the coming soon page to photographers without access', function () {
    config([
        'picstome.bookings_feature_enabled' => true,
        'picstome.bookings_feature_admin_only' => true,
        'picstome.admin_emails' => ['admin@example.com'],
    ]);

    $user = User::factory()->withPersonalTeam()->create(['email' => 'someone@example.com']);

    actingAs($user)
        ->get('/bookings/coming-soon')
        ->assertSuccessful()
        ->assertSee('Bookings are coming soon')
        ->assertSee('Coming soon');
});

it('sends administrators from the teaser to the agenda', function () {
    config([
        'picstome.bookings_feature_enabled' => true,
        'picstome.bookings_feature_admin_only' => true,
        'picstome.admin_emails' => ['admin@example.com'],
    ]);

    $admin = User::factory()->withPersonalTeam()->create(['email' => 'admin@example.com']);

    actingAs($admin)
        ->get('/bookings/coming-soon')
        ->assertRedirect(route('bookings'));
});
