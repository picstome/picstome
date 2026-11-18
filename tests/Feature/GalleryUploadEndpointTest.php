<?php

use App\Events\PhotoAdded;
use App\Models\Gallery;
use App\Models\Photo;
use App\Models\PhotoVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('s3');

    $this->user = User::factory()->withPersonalTeam()->create();
    $this->team = $this->user->currentTeam;
});

it('stores an uploaded file and returns its processing status', function () {
    Queue::fake();
    Event::fake(PhotoAdded::class);

    $gallery = Gallery::factory()->for($this->team)->create();

    $response = $this->actingAs($this->user)->post(
        route('galleries.uploads.store', $gallery),
        ['file' => UploadedFile::fake()->image('photo1.jpg')],
        ['Accept' => 'application/json'],
    );

    $response->assertOk()
        ->assertJsonPath('status', Photo::STATUS_PENDING)
        ->assertJsonStructure(['photo_id', 'status', 'remaining_storage_bytes']);

    $photo = $gallery->photos()->sole();

    expect($photo->id)->toBe($response->json('photo_id'))
        ->and(Storage::disk('s3')->exists($photo->path))->toBeTrue();

    Event::assertDispatched(PhotoAdded::class);
});

it('creates a new version for a duplicate name', function () {
    Event::fake(PhotoAdded::class);

    $gallery = Gallery::factory()->for($this->team)->create();
    $photo = Photo::factory()->for($gallery)->create([
        'name' => 'photo1.jpg',
        'source_name' => 'photo1.jpg',
        'path' => 'old/photo1.jpg',
        'version_count' => 1,
    ]);

    $this->actingAs($this->user)->post(
        route('galleries.uploads.store', $gallery),
        ['file' => UploadedFile::fake()->image('photo1.jpg')],
        ['Accept' => 'application/json'],
    )->assertOk();

    expect($gallery->photos()->count())->toBe(1)
        ->and($photo->fresh()->version_count)->toBe(2)
        ->and(PhotoVersion::where('photo_id', $photo->id)->count())->toBe(1);
});

it('replaces the current version when asked', function () {
    Event::fake(PhotoAdded::class);

    $gallery = Gallery::factory()->for($this->team)->create();
    $photo = Photo::factory()->for($gallery)->create([
        'name' => 'photo1.jpg',
        'source_name' => 'photo1.jpg',
        'path' => 'old/current.jpg',
        'size' => 1024,
        'version_count' => 1,
    ]);
    PhotoVersion::factory()->for($photo)->create(['version_number' => 1, 'path' => 'old/history.jpg']);

    $this->actingAs($this->user)->post(
        route('galleries.uploads.store', $gallery),
        ['file' => UploadedFile::fake()->image('photo1.jpg'), 'action' => 'replace'],
        ['Accept' => 'application/json'],
    )->assertOk();

    expect($gallery->photos()->count())->toBe(1)
        ->and($photo->fresh()->version_count)->toBe(1)
        ->and($photo->fresh()->path)->toBe('old/current.jpg');
});

it('returns a validation error for unsupported extensions', function () {
    $gallery = Gallery::factory()->for($this->team)->create();

    $this->actingAs($this->user)->post(
        route('galleries.uploads.store', $gallery),
        ['file' => UploadedFile::fake()->create('evil.exe', 100)],
        ['Accept' => 'application/json'],
    )->assertUnprocessable();

    expect($gallery->photos()->count())->toBe(0);
});

it('rejects pdfs whose content is not a pdf', function () {
    $gallery = Gallery::factory()->for($this->team)->create();

    $this->actingAs($this->user)->post(
        route('galleries.uploads.store', $gallery),
        ['file' => UploadedFile::fake()->createWithContent('composite.pdf', '<html>not a pdf</html>')],
        ['Accept' => 'application/json'],
    )->assertUnprocessable()
        ->assertJsonPath('code', 'invalid_pdf');

    expect($gallery->photos()->count())->toBe(0);
});

it('returns 413 when the team has no storage left', function () {
    $this->team->update(['custom_storage_limit' => 0]);

    $gallery = Gallery::factory()->for($this->team)->create();

    $this->actingAs($this->user)->post(
        route('galleries.uploads.store', $gallery),
        ['file' => UploadedFile::fake()->image('photo1.jpg')],
        ['Accept' => 'application/json'],
    )->assertStatus(413)
        ->assertJsonPath('code', 'storage_quota');

    expect($gallery->photos()->count())->toBe(0);
});

it('lists the selected names that already exist in the gallery', function () {
    $gallery = Gallery::factory()->for($this->team)->create();
    Photo::factory()->for($gallery)->create(['name' => 'photo1.jpg']);
    Photo::factory()->for($gallery)->create(['name' => 'a_id_.jpg']);

    $this->actingAs($this->user)->postJson(
        route('galleries.uploads.check', $gallery),
        ['names' => ['photo1.jpg', 'photo2.jpg', 'a`id`.jpg', 'photo1.jpg']],
    )->assertOk()
        ->assertJsonPath('duplicates', ['photo1.jpg', 'a`id`.jpg'])
        ->assertJsonStructure(['duplicates', 'remaining_storage_bytes', 'extensions', 'file_versions_enabled']);
});

it('returns statuses only for photos in the current gallery', function () {
    $gallery = Gallery::factory()->for($this->team)->create();
    $photo = Photo::factory()->for($gallery)->create(['status' => Photo::STATUS_WARMING]);
    $other = Photo::factory()->for(Gallery::factory())->create(['status' => Photo::STATUS_PROCESSING]);

    $this->actingAs($this->user)->getJson(
        route('galleries.uploads.status', [$gallery, 'ids' => [$photo->id, $other->id]]),
    )->assertOk()
        ->assertJsonPath('statuses.'.$photo->id, Photo::STATUS_WARMING)
        ->assertJsonMissingPath('statuses.'.$other->id);
});

it('forbids uploads to another team gallery', function () {
    $gallery = Gallery::factory()->for($this->team)->create();
    $stranger = User::factory()->withPersonalTeam()->create();

    $this->actingAs($stranger)->post(
        route('galleries.uploads.store', $gallery),
        ['file' => UploadedFile::fake()->image('photo1.jpg')],
        ['Accept' => 'application/json'],
    )->assertForbidden();

    $this->actingAs($stranger)->postJson(
        route('galleries.uploads.check', $gallery),
        ['names' => ['photo1.jpg']],
    )->assertForbidden();

    expect($gallery->photos()->count())->toBe(0);
});

it('renders the persistent upload dock in the app layout', function () {
    $gallery = Gallery::factory()->for($this->team)->create();

    $this->actingAs($this->user)
        ->get(route('galleries.show', $gallery))
        ->assertOk()
        ->assertSee('data-test="upload-dock"', false)
        ->assertSee('data-test="upload-dock-chip"', false)
        ->assertSee('window.PicstomeUploadsConfig', false)
        ->assertSee('data-gallery-id="'.$gallery->id.'"', false)
        ->assertSee('Drop files to upload')
        ->assertDontSee('multiFileUploader', false);
});

it('renders the dock on the dashboard', function () {
    $this->actingAs($this->user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('data-test="upload-dock"', false);
});
