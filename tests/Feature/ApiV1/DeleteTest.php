<?php

use App\Models\Gallery;
use App\Models\Photo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('requires authentication', function () {
    $this->deleteJson('/api/v1/photos')->assertUnauthorized();
});

it('deletes photos in the gallery and reports unknown ids', function () {
    Storage::fake('s3');

    $user = User::factory()->withPersonalTeam()->create();
    $gallery = Gallery::factory()->for($user->personalTeam())->create();
    $first = Photo::factory()->for($gallery)->create(['disk' => 's3']);
    $second = Photo::factory()->for($gallery)->create(['disk' => 's3']);
    $otherGallery = Gallery::factory()->for($user->personalTeam())->create();
    $otherPhoto = Photo::factory()->for($otherGallery)->create(['disk' => 's3']);

    Sanctum::actingAs($user);

    $before = $this->getJson('/api/v1/manifest?gallery='.$gallery->ulid)->json('rev');

    $response = $this->deleteJson('/api/v1/photos', [
        'gallery_id' => (string) $gallery->ulid,
        'photo_ids' => [$first->id, $second->id, $otherPhoto->id, 999999],
    ]);

    $response->assertOk()
        ->assertJsonPath('deleted', [(string) $first->id, (string) $second->id])
        ->assertJsonPath('not_found', [(string) $otherPhoto->id, '999999']);

    $this->assertDatabaseMissing('photos', ['id' => $first->id]);
    $this->assertDatabaseMissing('photos', ['id' => $second->id]);
    $this->assertDatabaseHas('photos', ['id' => $otherPhoto->id]);

    $after = $this->getJson('/api/v1/manifest?gallery='.$gallery->ulid)->json('rev');

    expect($after)->not->toBe($before)
        ->and($response->json('rev'))->toBe($after);
});

it('logs the deletion for audit purposes', function () {
    Storage::fake('s3');
    Log::spy();

    $user = User::factory()->withPersonalTeam()->create();
    $gallery = Gallery::factory()->for($user->personalTeam())->create();
    $photo = Photo::factory()->for($gallery)->create(['disk' => 's3']);

    Sanctum::actingAs($user);

    $this->deleteJson('/api/v1/photos', [
        'gallery_id' => (string) $gallery->ulid,
        'photo_ids' => [$photo->id],
    ])->assertOk();

    Log::shouldHaveReceived('info')
        ->withArgs(function (string $message, array $context) use ($user, $gallery, $photo): bool {
            return $message === 'external.photos.delete'
                && $context['user_id'] === $user->id
                && $context['gallery_id'] === (string) $gallery->ulid
                && $context['photo_ids'] === [(string) $photo->id];
        })
        ->once();
});

it('denies deleting photos from another team gallery', function () {
    Storage::fake('s3');

    $user = User::factory()->withPersonalTeam()->create();
    $gallery = Gallery::factory()->create();
    $photo = Photo::factory()->for($gallery)->create(['disk' => 's3']);

    Sanctum::actingAs($user);

    $this->deleteJson('/api/v1/photos', [
        'gallery_id' => (string) $gallery->ulid,
        'photo_ids' => [$photo->id],
    ])
        ->assertForbidden()
        ->assertJsonPath('code', 'not_member');

    $this->assertDatabaseHas('photos', ['id' => $photo->id]);
});

it('validates the photo id list', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $gallery = Gallery::factory()->for($user->personalTeam())->create();

    Sanctum::actingAs($user);

    $this->deleteJson('/api/v1/photos', [
        'gallery_id' => (string) $gallery->ulid,
        'photo_ids' => [],
    ])
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation_error')
        ->assertJsonValidationErrors('photo_ids');
});
