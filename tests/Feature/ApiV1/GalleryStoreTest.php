<?php

use App\Models\Gallery;
use App\Models\Photoshoot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Cashier\Subscription;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('requires authentication', function () {
    $this->postJson('/api/v1/galleries')->assertUnauthorized();
});

it('rejects tokens without the sync ability', function () {
    $user = User::factory()->withPersonalTeam()->create();
    Sanctum::actingAs($user, ['other']);

    $this->postJson('/api/v1/galleries')->assertForbidden();
});

it('creates a gallery with a photoshoot and the default one-month expiration', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->personalTeam();
    $photoshoot = Photoshoot::factory()->for($team)->create();

    Sanctum::actingAs($user, ['sync']);

    $response = $this->postJson('/api/v1/galleries', [
        'name' => 'Boda Ana',
        'photoshoot_id' => $photoshoot->id,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Boda Ana')
        ->assertJsonPath('data.photoshoot_id', $photoshoot->id)
        ->assertJsonPath('data.expiration_date', now()->addMonth()->toDateString())
        ->assertJsonPath('data.is_public', false)
        ->assertJsonPath('data.cover_url', null)
        ->assertJsonStructure([
            'data' => ['id', 'name', 'photoshoot_id', 'expiration_date', 'is_public', 'cover_url', 'updated_at'],
        ]);

    $gallery = Gallery::query()->firstOrFail();

    expect($gallery->team_id)->toBe($team->id)
        ->and($gallery->photoshoot_id)->toBe($photoshoot->id)
        ->and($gallery->expiration_date?->toDateString())->toBe(now()->addMonth()->toDateString())
        ->and($gallery->keep_original_size)->toBeTrue();
});

it('defaults the name and uses the saved original-size preference', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $user->rememberGalleryDefaults(['keep_original_size' => false]);

    Sanctum::actingAs($user, ['sync']);

    $this->postJson('/api/v1/galleries')
        ->assertCreated()
        ->assertJsonPath('data.name', 'Untitled');

    expect(Gallery::query()->firstOrFail()->keep_original_size)->toBeFalse();
});

it('accepts an explicit expiration date inside the allowed year', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $date = now()->addMonths(6)->toDateString();

    Sanctum::actingAs($user, ['sync']);

    $this->postJson('/api/v1/galleries', ['expiration_date' => $date])
        ->assertCreated()
        ->assertJsonPath('data.expiration_date', $date);

    expect(Gallery::query()->firstOrFail()->expiration_date?->toDateString())->toBe($date);
});

it('rejects an expiration date beyond one year and returns the maximum', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $max = now()->addYear()->toDateString();

    Sanctum::actingAs($user, ['sync']);

    $this->postJson('/api/v1/galleries', [
        'expiration_date' => now()->addYear()->addDay()->toDateString(),
    ])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'validation_error')
        ->assertJsonPath('max_expiration_date', $max)
        ->assertJsonValidationErrors('expiration_date');

    expect(Gallery::query()->count())->toBe(0);
});

it('rejects a past expiration date', function () {
    $user = User::factory()->withPersonalTeam()->create();

    Sanctum::actingAs($user, ['sync']);

    $this->postJson('/api/v1/galleries', [
        'expiration_date' => now()->subDay()->toDateString(),
    ])
        ->assertUnprocessable()
        ->assertJsonPath('max_expiration_date', now()->addYear()->toDateString())
        ->assertJsonValidationErrors('expiration_date');
});

it('rejects an explicit null expiration without unlimited retention', function () {
    $user = User::factory()->withPersonalTeam()->create();

    Sanctum::actingAs($user, ['sync']);

    $this->postJson('/api/v1/galleries', ['expiration_date' => null])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'validation_error')
        ->assertJsonPath('max_expiration_date', now()->addYear()->toDateString())
        ->assertJsonValidationErrors('expiration_date');

    expect(Gallery::query()->count())->toBe(0);
});

it('creates a never-expiring gallery for an active subscription', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->personalTeam();

    Subscription::factory()->for($team, 'owner')->create();

    Sanctum::actingAs($user, ['sync']);

    $this->postJson('/api/v1/galleries', ['expiration_date' => null])
        ->assertCreated()
        ->assertJsonPath('data.expiration_date', null);

    expect(Gallery::query()->firstOrFail()->expiration_date)->toBeNull();
});

it('uses one month by default even with an active subscription', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->personalTeam();

    Subscription::factory()->for($team, 'owner')->create();

    Sanctum::actingAs($user, ['sync']);

    $this->postJson('/api/v1/galleries')
        ->assertCreated()
        ->assertJsonPath('data.expiration_date', now()->addMonth()->toDateString());
});

it('rejects a photoshoot from another team', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $foreignPhotoshoot = Photoshoot::factory()->create();

    Sanctum::actingAs($user, ['sync']);

    $this->postJson('/api/v1/galleries', [
        'photoshoot_id' => $foreignPhotoshoot->id,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('photoshoot_id');

    expect(Gallery::query()->count())->toBe(0);
});

it('rejects an unknown photoshoot', function () {
    $user = User::factory()->withPersonalTeam()->create();

    Sanctum::actingAs($user, ['sync']);

    $this->postJson('/api/v1/galleries', ['photoshoot_id' => 999999])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('photoshoot_id');
});
