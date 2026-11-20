<?php

use App\Events\PhotoAdded;
use App\Models\Gallery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('requires authentication', function () {
    $this->postJson('/api/v1/photos')->assertUnauthorized();
});

it('rejects tokens without the sync ability', function () {
    $user = User::factory()->withPersonalTeam()->create();
    Sanctum::actingAs($user, ['other']);

    $this->getJson('/api/v1/me')->assertForbidden();
});

it('uploads a photo, recomputes its hash and moves the revision', function () {
    Storage::fake('s3');
    Event::fake([PhotoAdded::class]);

    $user = User::factory()->withPersonalTeam()->create();
    $gallery = Gallery::factory()->for($user->personalTeam())->create();

    Sanctum::actingAs($user, ['sync']);

    $before = $this->getJson('/api/v1/manifest?gallery='.$gallery->ulid)->json('rev');

    $response = $this->post('/api/v1/photos', [
        'gallery_id' => (string) $gallery->ulid,
        'filename' => 'new-photo.jpg',
        'file' => UploadedFile::fake()->image('original-name.jpg', 10, 20),
    ], ['Accept' => 'application/json']);

    $response->assertCreated()
        ->assertJsonPath('filename', 'new-photo.jpg')
        ->assertJsonStructure(['id', 'filename', 'rev']);

    $photo = $gallery->photos()->firstOrFail();

    expect($photo->name)->toBe('new-photo.jpg')
        ->and($photo->hash)->not->toBeNull()
        ->and($photo->hash_algo)->toBe('sha256')
        ->and($photo->width)->toBe(10)
        ->and($photo->height)->toBe(20)
        ->and(Storage::disk('s3')->exists($photo->path))->toBeTrue();

    Event::assertDispatched(PhotoAdded::class);

    $after = $this->getJson('/api/v1/manifest?gallery='.$gallery->ulid)->json('rev');

    expect($after)->not->toBe($before)
        ->and($response->json('rev'))->toBe($after);
});

it('returns the storage quota error when the team is out of space', function () {
    Storage::fake('s3');

    $user = User::factory()->withPersonalTeam()->create();
    $user->currentTeam->update(['custom_storage_limit' => 1]);

    $gallery = Gallery::factory()->for($user->currentTeam)->create();

    Sanctum::actingAs($user, ['sync']);

    $this->post('/api/v1/photos', [
        'gallery_id' => (string) $gallery->ulid,
        'filename' => 'quota.jpg',
        'file' => UploadedFile::fake()->image('quota.jpg', 10, 20),
    ], ['Accept' => 'application/json'])
        ->assertStatus(413)
        ->assertJsonPath('code', 'storage_quota_exceeded');
});

it('denies uploading to another team gallery', function () {
    Storage::fake('s3');

    $user = User::factory()->withPersonalTeam()->create();
    $gallery = Gallery::factory()->create();

    Sanctum::actingAs($user, ['sync']);

    $this->post('/api/v1/photos', [
        'gallery_id' => (string) $gallery->ulid,
        'filename' => 'new-photo.jpg',
        'file' => UploadedFile::fake()->image('new-photo.jpg'),
    ], ['Accept' => 'application/json'])
        ->assertForbidden()
        ->assertJsonPath('code', 'not_member');
});

it('rejects unsupported file extensions', function () {
    Storage::fake('s3');

    $user = User::factory()->withPersonalTeam()->create();
    $gallery = Gallery::factory()->for($user->personalTeam())->create();

    Sanctum::actingAs($user, ['sync']);

    $this->post('/api/v1/photos', [
        'gallery_id' => (string) $gallery->ulid,
        'filename' => 'malware.exe',
        'file' => UploadedFile::fake()->create('malware.exe', 10),
    ], ['Accept' => 'application/json'])
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation_error')
        ->assertJsonValidationErrors('file');

    expect($gallery->photos()->count())->toBe(0);
});

it('rejects a video whose bytes are not a video', function () {
    Storage::fake('s3');

    $user = User::factory()->withPersonalTeam()->create();
    $gallery = Gallery::factory()->for($user->personalTeam())->create();

    Sanctum::actingAs($user, ['sync']);

    $this->post('/api/v1/photos', [
        'gallery_id' => (string) $gallery->ulid,
        'filename' => 'broken.mp4',
        'file' => UploadedFile::fake()->createWithContent('broken.mp4', 'this is not a video container'),
    ], ['Accept' => 'application/json'])
        ->assertStatus(422)
        ->assertJsonPath('code', 'invalid_video');

    expect($gallery->photos()->count())->toBe(0);
});

it('rejects a file that is not a readable image before storing it', function () {
    Storage::fake('s3');

    $user = User::factory()->withPersonalTeam()->create();
    $gallery = Gallery::factory()->for($user->personalTeam())->create();

    Sanctum::actingAs($user, ['sync']);

    $this->post('/api/v1/photos', [
        'gallery_id' => (string) $gallery->ulid,
        'filename' => 'broken.jpg',
        'file' => UploadedFile::fake()->createWithContent('broken.jpg', 'not an image'),
    ], ['Accept' => 'application/json'])
        ->assertStatus(422)
        ->assertJsonPath('code', 'invalid_image');

    expect($gallery->photos()->count())->toBe(0);
});

it('returns 404 for an unknown gallery', function () {
    Storage::fake('s3');

    $user = User::factory()->withPersonalTeam()->create();
    Sanctum::actingAs($user, ['sync']);

    $this->post('/api/v1/photos', [
        'gallery_id' => (string) str()->ulid(),
        'filename' => 'new-photo.jpg',
        'file' => UploadedFile::fake()->image('new-photo.jpg'),
    ], ['Accept' => 'application/json'])
        ->assertNotFound()
        ->assertJsonPath('code', 'unknown_gallery');
});
