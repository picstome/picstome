<?php

use App\Events\PhotoAdded;
use App\Models\Gallery;
use App\Models\Photo;
use App\Models\PhotoVersion;
use App\Models\User;
use App\Services\GalleryUploadService;
use App\Support\PendingDirectUpload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Livewire\Features\SupportFileUploads\GenerateSignedUploadUrl;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

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

it('stores a regular HTTP upload when Livewire uses an S3 temporary disk', function () {
    Storage::fake('s3');
    config(['livewire.temporary_file_upload.disk' => 's3']);

    expect(FileUploadConfiguration::isUsingS3())->toBeTrue();

    $gallery = Gallery::factory()->for($this->team)->create();

    $this->actingAs($this->user)->post(
        route('galleries.uploads.store', $gallery),
        ['file' => UploadedFile::fake()->image('photo1.jpg')],
        ['Accept' => 'application/json'],
    )->assertOk();

    $photo = $gallery->photos()->sole();

    expect(Storage::disk('s3')->exists($photo->path))->toBeTrue();
});

it('replaces a version from a regular HTTP upload when Livewire uses an S3 temporary disk', function () {
    Storage::fake('s3');
    config(['livewire.temporary_file_upload.disk' => 's3']);

    $gallery = Gallery::factory()->for($this->team)->create();
    $photo = Photo::factory()->for($gallery)->create([
        'name' => 'photo1.jpg',
        'source_name' => 'photo1.jpg',
        'path' => 'old/current.jpg',
        'size' => 1024,
        'version_count' => 1,
    ]);

    $this->actingAs($this->user)->post(
        route('galleries.uploads.store', $gallery),
        ['file' => UploadedFile::fake()->image('photo1.jpg'), 'action' => 'replace'],
        ['Accept' => 'application/json'],
    )->assertOk();

    expect($photo->fresh()->path)->toBe('old/current.jpg')
        ->and(Storage::disk('s3')->exists('old/current.jpg'))->toBeTrue();
});

describe('direct S3 uploads', function () {
    beforeEach(function () {
        config(['livewire.temporary_file_upload.disk' => 's3']);

        $this->app->instance(GenerateSignedUploadUrl::class, new class extends GenerateSignedUploadUrl
        {
            public function forS3($file, $visibility = 'private')
            {
                $name = TemporaryUploadedFile::generateHashNameWithOriginalNameEmbedded($file);

                return [
                    'path' => TemporaryUploadedFile::signPath($name),
                    'url' => 'https://bucket.test/livewire-tmp/'.$name,
                    'headers' => ['Host' => ['bucket.test'], 'Content-Type' => $file->getMimeType()],
                ];
            }
        });
    });

    /**
     * Put a file in Livewire's temporary area (the S3 bucket in production,
     * Livewire's fake disk in tests) as the browser would, and return
     * the signed path the sign endpoint hands out for it.
     */
    function putDirectUpload(string $name, string $contents): string
    {
        $hashName = TemporaryUploadedFile::generateHashNameWithOriginalNameEmbedded(new PendingDirectUpload($name));
        FileUploadConfiguration::storage()->put(FileUploadConfiguration::path($hashName), $contents);

        return TemporaryUploadedFile::signPath($hashName);
    }

    it('signs a direct S3 upload without the Host header', function () {
        $gallery = Gallery::factory()->for($this->team)->create();

        $this->actingAs($this->user)->postJson(
            route('galleries.uploads.sign', $gallery),
            ['name' => 'clip.mp4', 'size' => 4 * 1024 * 1024 * 1024],
        )->assertOk()
            ->assertJsonPath('direct', true)
            ->assertJsonPath('headers.Content-Type', 'video/mp4')
            ->assertJsonMissingPath('headers.Host')
            ->assertJsonStructure(['path', 'url']);
    });

    it('tells the uploader to post the file when uploads are not on S3', function () {
        config(['livewire.temporary_file_upload.disk' => null]);
        $gallery = Gallery::factory()->for($this->team)->create();

        $this->actingAs($this->user)->postJson(
            route('galleries.uploads.sign', $gallery),
            ['name' => 'clip.mp4', 'size' => 100],
        )->assertOk()->assertExactJson(['direct' => false]);
    });

    it('refuses to sign files that break the upload rules', function (string $name, int $size, int $status, string $code) {
        $gallery = Gallery::factory()->for($this->team)->create();

        $this->actingAs($this->user)->postJson(
            route('galleries.uploads.sign', $gallery),
            ['name' => $name, 'size' => $size],
        )->assertStatus($status)->assertJsonPath('code', $code);
    })->with([
        'unsupported type' => ['evil.exe', 100, 422, 'unsupported_type'],
        'over 5 GB' => ['clip.mp4', GalleryUploadService::MAX_FILE_BYTES + 1, 413, 'file_too_large'],
    ]);

    it('refuses to sign when the team has no storage left', function () {
        $this->team->update(['custom_storage_limit' => 0]);
        $gallery = Gallery::factory()->for($this->team)->create();

        $this->actingAs($this->user)->postJson(
            route('galleries.uploads.sign', $gallery),
            ['name' => 'clip.mp4', 'size' => 100],
        )->assertStatus(413)->assertJsonPath('code', 'storage_quota');
    });

    it('stores a video the browser uploaded straight to S3', function () {
        Event::fake(PhotoAdded::class);

        $gallery = Gallery::factory()->for($this->team)->create();
        $video = "\x00\x00\x00\x18ftypmp42".str_repeat('video-bytes-', 1000);
        $path = putDirectUpload('clip.mp4', $video);

        $this->actingAs($this->user)->postJson(
            route('galleries.uploads.complete', $gallery),
            ['path' => $path],
        )->assertOk()->assertJsonStructure(['photo_id', 'status', 'remaining_storage_bytes']);

        $photo = $gallery->photos()->sole();

        expect($photo->name)->toBe('clip.mp4')
            ->and($photo->size)->toBe(strlen($video))
            ->and(Storage::disk('s3')->get($photo->path))->toBe($video)
            ->and(FileUploadConfiguration::storage()->files(FileUploadConfiguration::path()))->toBe([]);

        Event::assertDispatched(PhotoAdded::class);
    });

    it('rejects a tampered temporary path', function () {
        $gallery = Gallery::factory()->for($this->team)->create();
        $path = putDirectUpload('clip.mp4', "\x00\x00\x00\x18ftypmp42");

        $this->actingAs($this->user)->postJson(
            route('galleries.uploads.complete', $gallery),
            ['path' => 'deadbeef:'.explode(':', $path, 2)[1]],
        )->assertUnprocessable();

        expect($gallery->photos()->count())->toBe(0);
    });

    it('deletes the temporary object when the uploaded file is rejected', function () {
        $gallery = Gallery::factory()->for($this->team)->create();
        $path = putDirectUpload('clip.mp4', '<html>not a video</html>');

        $this->actingAs($this->user)->postJson(
            route('galleries.uploads.complete', $gallery),
            ['path' => $path],
        )->assertUnprocessable()->assertJsonPath('code', 'invalid_video');

        expect($gallery->photos()->count())->toBe(0)
            ->and(FileUploadConfiguration::storage()->files(FileUploadConfiguration::path()))->toBe([]);
    });

    it('forbids signing or completing uploads to another team gallery', function () {
        $gallery = Gallery::factory()->for($this->team)->create();
        $path = putDirectUpload('clip.mp4', "\x00\x00\x00\x18ftypmp42");

        $this->actingAs(User::factory()->withPersonalTeam()->create());

        $this->postJson(route('galleries.uploads.sign', $gallery), ['name' => 'clip.mp4', 'size' => 100])
            ->assertForbidden();
        $this->postJson(route('galleries.uploads.complete', $gallery), ['path' => $path])
            ->assertForbidden();
    });
});
