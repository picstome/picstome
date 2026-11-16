<?php

use App\Models\User;
use App\Services\StripeAttributionMetadata;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('builds attribution metadata from the stored first touch', function () {
    $user = User::factory()->create();
    $user->forceFill([
        'attr_source' => 'qa',
        'attr_medium' => 'test',
        'attr_campaign' => 'prueba',
        'attr_content' => 'hero',
        'attr_at' => CarbonImmutable::parse('2026-11-15 10:00:00'),
    ])->save();

    $metadata = app(StripeAttributionMetadata::class)->forUser($user->fresh());

    expect($metadata)->toMatchArray([
        'ps_user_id' => (string) $user->id,
        'ps_source' => 'qa',
        'ps_medium' => 'test',
        'ps_campaign' => 'prueba',
        'ps_content' => 'hero',
        'ps_touch' => 'signup',
    ])->and($metadata['ps_at'])->toStartWith('2026-11-15T10:00:00');
});

it('falls back to the last touch when the user has no attribution', function () {
    $user = User::factory()->create();
    $at = CarbonImmutable::parse('2026-11-15 11:00:00');

    $metadata = app(StripeAttributionMetadata::class)->forUser($user, [
        'source' => 'google',
        'medium' => 'organic',
        'campaign' => 'brand',
        'at' => $at,
    ]);

    expect($metadata)->toMatchArray([
        'ps_source' => 'google',
        'ps_medium' => 'organic',
        'ps_campaign' => 'brand',
        'ps_touch' => 'checkout',
    ])->and($metadata['ps_at'])->toStartWith('2026-11-15T11:00:00');
});

it('keeps only attribution keys from subscription metadata', function () {
    $metadata = app(StripeAttributionMetadata::class)->fromMetadata([
        'ps_source' => 'qa',
        'ps_medium' => 'test',
        'name' => 'default',
        'type' => 'default',
        'tier' => '100gb',
    ]);

    expect($metadata)->toBe([
        'ps_source' => 'qa',
        'ps_medium' => 'test',
    ]);
});

it('does not send personal data in the metadata', function () {
    $user = User::factory()->create([
        'name' => 'Private Person',
        'email' => 'private@example.com',
    ]);

    $metadata = app(StripeAttributionMetadata::class)->forUser($user);

    expect($metadata)->not->toHaveKey('email')
        ->and($metadata)->not->toHaveKey('name')
        ->and(json_encode($metadata))->not->toContain('private@example.com');
});

it('reads the owner when building metadata from a team', function () {
    $user = User::factory()->withPersonalTeam()->create();

    $metadata = app(StripeAttributionMetadata::class)->forTeam($user->personalTeam());

    expect($metadata['ps_user_id'])->toBe((string) $user->id);
});
