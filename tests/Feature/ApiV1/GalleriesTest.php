<?php

use App\Models\Gallery;
use App\Models\Photo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('requires authentication', function () {
    $this->getJson('/api/v1/galleries')->assertUnauthorized();
});

it('lists only the galleries of the current team', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->personalTeam();

    $gallery = Gallery::factory()->for($team)->create(['name' => 'Mine']);
    $cover = Photo::factory()->for($gallery)->create(['disk' => 's3']);

    Gallery::factory()->for($team)->create(['name' => 'Also mine']);
    Gallery::factory()->create(['name' => 'Someone else']);

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/v1/galleries');

    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonFragment(['id' => (string) $gallery->ulid, 'name' => 'Mine'])
        ->assertJsonFragment(['name' => 'Also mine'])
        ->assertJsonMissing(['name' => 'Someone else']);

    $mine = collect($response->json('data'))->firstWhere('id', (string) $gallery->ulid);

    expect($mine['cover_url'])->toBeString()->not->toBeEmpty()
        ->and($cover->gallery_id)->toBe($gallery->id);
});

it('filters galleries by name for the current team only', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->personalTeam();

    Gallery::factory()->for($team)->create(['name' => 'MiBoda']);
    Gallery::factory()->for($team)->create(['name' => 'Boda Ana']);
    Gallery::factory()->for($team)->create(['name' => 'Corporativo']);
    Gallery::factory()->create(['name' => 'MiBoda de otro equipo']);

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/v1/galleries?search=boda');

    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonFragment(['name' => 'MiBoda'])
        ->assertJsonFragment(['name' => 'Boda Ana'])
        ->assertJsonMissing(['name' => 'Corporativo'])
        ->assertJsonMissing(['name' => 'MiBoda de otro equipo']);

    $this->getJson('/api/v1/galleries?search=BODA')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('paginates galleries and exposes the next page', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $team = $user->personalTeam();

    Gallery::factory()->count(30)->for($team)->create();

    Sanctum::actingAs($user);

    $this->getJson('/api/v1/galleries')
        ->assertOk()
        ->assertJsonCount(25, 'data')
        ->assertJsonPath('meta.current_page', 1)
        ->assertJsonPath('meta.per_page', 25)
        ->assertJsonPath('meta.total', 30)
        ->assertJsonPath('meta.last_page', 2);

    $response = $this->getJson('/api/v1/galleries?per_page=10&page=2');

    $response->assertOk()
        ->assertJsonCount(10, 'data')
        ->assertJsonPath('meta.current_page', 2)
        ->assertJsonPath('meta.per_page', 10)
        ->assertJsonPath('meta.total', 30)
        ->assertJsonPath('meta.last_page', 3);

    expect($response->json('links.next'))->toBeString()->not->toBeEmpty();
});

it('validates the gallery list filters', function () {
    $user = User::factory()->withPersonalTeam()->create();

    Sanctum::actingAs($user);

    $this->getJson('/api/v1/galleries?per_page=101')
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation_error')
        ->assertJsonValidationErrors('per_page');

    $this->getJson('/api/v1/galleries?page=0')
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation_error')
        ->assertJsonValidationErrors('page');

    $this->getJson('/api/v1/galleries?search='.str_repeat('a', 256))
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation_error')
        ->assertJsonValidationErrors('search');
});

it('returns the connected account details', function () {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $this->getJson('/api/v1/me')
        ->assertOk()
        ->assertJson([
            'email' => $user->email,
            'display_name' => $user->name,
        ]);
});
