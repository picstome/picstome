<?php

use App\Models\Gallery;
use App\Models\Photo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('requires authentication', function () {
    $this->getJson('/api/v1/manifest?gallery='.Str::ulid())
        ->assertUnauthorized();
});

it('returns 404 for an unknown gallery', function () {
    $user = User::factory()->withPersonalTeam()->create();

    Sanctum::actingAs($user);

    $this->getJson('/api/v1/manifest?gallery='.Str::ulid())
        ->assertNotFound()
        ->assertJsonPath('code', 'unknown_gallery');
});

it('denies galleries from another team', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $gallery = Gallery::factory()->create();

    Sanctum::actingAs($user);

    $this->getJson('/api/v1/manifest?gallery='.$gallery->ulid)
        ->assertForbidden()
        ->assertJsonPath('code', 'not_member');
});

it('returns the full manifest with stable ids and signed urls', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $gallery = Gallery::factory()->for($user->personalTeam())->create(['name' => 'MiBoda']);

    $photo = Photo::factory()->for($gallery)->create([
        'name' => '1Z8A4331.jpg',
        'source_name' => '1Z8A4331.jpg',
        'path' => 'galleries/'.$gallery->ulid.'/1Z8A4331.jpg',
        'disk' => 's3',
        'size' => 4820133,
        'hash' => str_repeat('a', 64),
        'stored_hash' => str_repeat('b', 64),
        'hash_algo' => 'sha256',
        'width' => 6000,
        'height' => 4000,
    ]);

    Storage::fake('s3');
    $urls = fakeTemporaryUrls();
    Sanctum::actingAs($user);

    $response = $this->getJson('/api/v1/manifest?gallery='.$gallery->ulid.'&since=anything');

    $response->assertOk()
        ->assertJsonPath('gallery_id', (string) $gallery->ulid)
        ->assertJsonPath('name', 'MiBoda')
        ->assertJsonPath('web_url', route('galleries.show', ['gallery' => $gallery->id]))
        ->assertJsonPath('photos.0.id', (string) $photo->id)
        ->assertJsonPath('photos.0.filename', '1Z8A4331.jpg')
        ->assertJsonPath('photos.0.size', 4820133)
        ->assertJsonPath('photos.0.hash', str_repeat('a', 64))
        ->assertJsonPath('photos.0.download_hash', str_repeat('b', 64))
        ->assertJsonPath('photos.0.hash_algo', 'sha256')
        ->assertJsonPath('photos.0.width', 6000)
        ->assertJsonPath('photos.0.height', 4000)
        ->assertJsonPath('photos.0.url', 'https://s3.test/'.$photo->path);

    expect($response->json('rev'))->toBeString()->not->toBeEmpty()
        ->and($urls->expirations[0]->diffInMinutes(now()))->toBeLessThanOrEqual(15);
});

it('reports a null hash when the server has not computed it', function () {
    $user = User::factory()->withPersonalTeam()->create();
    $gallery = Gallery::factory()->for($user->personalTeam())->create();
    $photo = Photo::factory()->for($gallery)->create(['disk' => 's3', 'hash' => null]);

    Storage::fake('s3');
    fakeTemporaryUrls();
    Sanctum::actingAs($user);

    $response = $this->getJson('/api/v1/manifest?gallery='.$gallery->ulid);

    $response->assertOk()
        ->assertJsonPath('photos.0.id', (string) $photo->id)
        ->assertJsonPath('photos.0.hash', null)
        ->assertJsonPath('photos.0.download_hash', null)
        ->assertJsonPath('photos.0.hash_algo', 'sha256');
});
