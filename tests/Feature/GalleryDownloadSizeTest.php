<?php

use App\Models\Gallery;
use App\Models\Photo;
use App\Models\User;
use App\Services\GalleryArchiveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('s3');

    $this->user = User::factory()->withPersonalTeam()->create();
    $this->team = $this->user->currentTeam;
});

it('estimates the download size without touching storage', function () {
    $gallery = Gallery::factory()->for($this->team)->create();

    Photo::factory()->for($gallery)->create([
        'path' => 'photos/photo1.jpg',
        'size' => 2048,
        'stored_size' => 3072,
    ]);

    // Nothing is written to the fake disk on purpose: the estimate must come
    // from the row columns, so asking storage here would resolve to 0.
    expect(Storage::disk('s3')->exists('photos/photo1.jpg'))->toBeFalse();

    expect(app(GalleryArchiveService::class)->estimatedSize($gallery))->toBe(2048);
});

it('counts videos and PDFs by their stored bytes', function () {
    $gallery = Gallery::factory()->for($this->team)->create();

    Photo::factory()->for($gallery)->video()->create(['size' => 100, 'stored_size' => 100]);
    Photo::factory()->for($gallery)->pdf()->create(['size' => 200, 'stored_size' => 200]);

    expect(app(GalleryArchiveService::class)->estimatedSize($gallery))->toBe(300);
});

it('ignores archived photos and unsupported files', function () {
    $gallery = Gallery::factory()->for($this->team)->create();

    Photo::factory()->for($gallery)->create(['size' => 500, 'stored_size' => 500]);
    $archived = Photo::factory()->for($gallery)->create(['size' => 900, 'stored_size' => 900]);
    $archived->archive();

    Photo::factory()->for($gallery)->create([
        'name' => 'notes.txt',
        'path' => 'galleries/notes.txt',
        'size' => 700,
        'stored_size' => 700,
    ]);

    expect(app(GalleryArchiveService::class)->estimatedSize($gallery))->toBe(500);
});

it('keeps the SQL estimate and the per-photo PHP estimate equal', function () {
    $gallery = Gallery::factory()->for($this->team)->create();

    $photos = Photo::factory()->for($gallery)->count(3)->create([
        'size' => 1024,
        'stored_size' => 2048,
    ]);

    $service = app(GalleryArchiveService::class);

    expect($service->estimatedSize($gallery))->toBe(3072)
        ->and($service->estimatedSizeForPhotos($photos))->toBe(3072)
        ->and($gallery->downloadSize())->toBe(3072);
});

it('falls back to stored_size when a legacy image row has no size', function () {
    $gallery = Gallery::factory()->for($this->team)->create();

    Photo::factory()->for($gallery)->create([
        'path' => 'photos/legacy.jpg',
        'size' => 0,
        'stored_size' => 4096,
    ]);

    expect(app(GalleryArchiveService::class)->estimatedSize($gallery))->toBe(4096);
});

it('uses the processed file size for a gallery that keeps the RAW originals', function () {
    $gallery = Gallery::factory()->for($this->team)->create(['keep_original_size' => true]);

    Photo::factory()->for($gallery)->create([
        'name' => 'shot.cr2',
        'path' => 'photos/shot.jpg',
        'raw_path' => 'photos/shot.cr2',
        'size' => 5000,
        'stored_size' => 9000,
    ]);

    // stored_size = processed (4000) + raw (5000); the RAW never ships.
    expect(app(GalleryArchiveService::class)->estimatedSize($gallery))->toBe(4000);
});
