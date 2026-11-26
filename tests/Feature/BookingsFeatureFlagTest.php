<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('hides bookings when the feature is disabled', function () {
    config([
        'picstome.bookings_feature_enabled' => false,
        'picstome.bookings_feature_admin_only' => false,
    ]);

    $user = User::factory()->create();

    expect($user->canSeeBookingsFeature())->toBeFalse();
});

it('is limited to admins while the feature is being tested', function () {
    config([
        'picstome.bookings_feature_enabled' => true,
        'picstome.bookings_feature_admin_only' => true,
        'picstome.admin_emails' => ['admin@example.com'],
    ]);

    $user = User::factory()->create(['email' => 'someone@example.com']);
    $admin = User::factory()->create(['email' => 'admin@example.com']);

    expect($user->canSeeBookingsFeature())->toBeFalse()
        ->and($admin->canSeeBookingsFeature())->toBeTrue();
});

it('publishes bookings to everyone when admin only is turned off', function () {
    config([
        'picstome.bookings_feature_enabled' => true,
        'picstome.bookings_feature_admin_only' => false,
    ]);

    $user = User::factory()->create();

    expect($user->canSeeBookingsFeature())->toBeTrue();
});
