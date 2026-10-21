<?php

use App\Jobs\ProcessPhoto;
use App\Models\Gallery;
use App\Models\Photo;
use App\Models\PhotoVersion;
use Facades\App\Services\RawPhotoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('resizes an added photo', function () {
    config(['picstome.photo_resize' => 128]);
    Storage::fake('s3');
    Storage::fake('local');

    $gallery = Gallery::factory()->create(['ulid' => '1243ABC']);
    $photoFile = UploadedFile::fake()->image('photo1.jpg', 129, 129);
    $photo = $gallery->addPhoto($photoFile);

    (new ProcessPhoto($photo))->handle();

    $photo->refresh();
    $resizedImage = Storage::disk('s3')->get($photo->path);
    [$width, $height] = getimagesizefromstring($resizedImage);
    expect($width)->toBe(128);
    expect($height)->toBe(128);
    expect($photo->disk)->toBe('s3');
    expect($photo->status)->toBe('processed');
});

it('does not resize photo when keep original size is enabled', function () {
    config(['picstome.photo_resize' => 128]);
    Storage::fake('s3');
    Storage::fake('local');

    $gallery = Gallery::factory()->create(['ulid' => '1243ABC', 'keep_original_size' => true]);
    $photoFile = UploadedFile::fake()->image('photo1.jpg', 129, 129);
    $photo = $gallery->addPhoto($photoFile);

    (new ProcessPhoto($photo))->handle();

    $photo->refresh();
    $resizedImage = Storage::disk('s3')->get($photo->path);
    [$width, $height] = getimagesizefromstring($resizedImage);
    expect($width)->toBe(129);
    expect($height)->toBe(129);
    expect($photo->disk)->toBe('s3');
    expect($photo->status)->toBe('processed');
});

it('resizes photos when the gallery stops keeping the original size', function () {
    config(['picstome.photo_resize' => 128]);
    Storage::fake('s3');
    Storage::fake('local');

    $gallery = Gallery::factory()->create(['ulid' => '1243ABC', 'keep_original_size' => false]);
    $photoFile = UploadedFile::fake()->image('photo1.jpg', 129, 129);
    $photo = $gallery->addPhoto($photoFile);

    (new ProcessPhoto($photo))->handle();

    $photo->refresh();
    $resizedImage = Storage::disk('s3')->get($photo->path);
    [$width, $height] = getimagesizefromstring($resizedImage);
    expect($width)->toBe(128);
    expect($height)->toBe(128);
});

it('deletes the original photo from public disk after processing', function () {
    config(['picstome.photo_resize' => 128]);
    config(['picstome.photo_thumb_resize' => 64]);
    Storage::fake('s3');
    Storage::fake('local');

    $gallery = Gallery::factory()->create(['ulid' => '1243ABC']);
    $photoFile = UploadedFile::fake()->image('photo1.jpg', 129, 129);
    $photo = $gallery->addPhoto($photoFile);

    (new ProcessPhoto($photo))->handle();

    $photo->refresh();
    expect(Storage::disk('s3')->allFiles())->toHaveCount(1);
});

it('keeps oversized photo if keep_original_size is enabled and it has raw_path', function () {
    config(['picstome.max_photo_pixels' => 10000]); // 100x100
    Storage::fake('s3');
    Storage::fake('local');

    $gallery = Gallery::factory()->create(['ulid' => '1243ABC', 'keep_original_size' => true]);
    $photoFile = UploadedFile::fake()->image('oversized.jpg', 101, 100); // 10100 pixels
    $photo = $gallery->addPhoto($photoFile);
    $photo->update(['raw_path' => 'raw/path.jpg']); // Simulate having a raw file

    (new ProcessPhoto($photo))->handle();

    $photo->refresh();
    expect($photo->status)->toBe('pending'); // Regular photos remain pending if oversized with raw_path
    expect($gallery->fresh()->photos()->where('id', $photo->id)->exists())->toBeTrue();
});

it('deletes oversized photo if keep_original_size is enabled but no raw_path', function () {
    config(['picstome.max_photo_pixels' => 10000]); // 100x100
    Storage::fake('s3');
    Storage::fake('local');

    $gallery = Gallery::factory()->create(['ulid' => '1243ABC', 'keep_original_size' => true]);
    $photoFile = UploadedFile::fake()->image('oversized.jpg', 101, 100); // 10100 pixels
    $photo = $gallery->addPhoto($photoFile);
    $originalPath = $photo->path;

    (new ProcessPhoto($photo))->handle();

    expect($gallery->fresh()->photos()->where('id', $photo->id)->exists())->toBeFalse();
    expect(Storage::disk('s3')->exists($originalPath))->toBeFalse();
});

it('does not process photo if extension is not allowed', function () {
    config(['picstome.photo_resize' => 128]);
    Storage::fake('s3');
    Storage::fake('local');

    $gallery = Gallery::factory()->create(['ulid' => '1243ABC']);
    $photoFile = UploadedFile::fake()->create('photo1.gif', 100, 'image/gif');
    $photo = $gallery->addPhoto($photoFile);
    $originalPath = $photo->path;

    (new ProcessPhoto($photo))->handle();

    $photo->refresh();

    expect(Storage::disk('s3')->exists($originalPath))->toBeTrue();
    expect($photo->status)->toBe('skipped');
});

it('skips pdf photos without touching the stored file and stays idempotent', function () {
    config(['picstome.photo_resize' => 128]);
    Storage::fake('s3');
    Storage::fake('local');

    $gallery = Gallery::factory()->create(['ulid' => '1243ABC']);
    $photoFile = UploadedFile::fake()->createWithContent('composite.pdf', '%PDF-1.4 fake pdf body');
    $photo = $gallery->addPhoto($photoFile);
    $originalPath = $photo->path;

    (new ProcessPhoto($photo))->handle();

    $photo->refresh();
    expect(Storage::disk('s3')->exists($originalPath))->toBeTrue();
    expect(Storage::disk('s3')->get($originalPath))->toBe('%PDF-1.4 fake pdf body');
    expect($photo->status)->toBe('skipped');

    (new ProcessPhoto($photo))->handle();

    $photo->refresh();
    expect(Storage::disk('s3')->exists($originalPath))->toBeTrue();
    expect(Storage::disk('s3')->get($originalPath))->toBe('%PDF-1.4 fake pdf body');
    expect($photo->status)->toBe('skipped');
});

it('processes canon cr2 raw files by extracting jpg preview', function () {
    config(['picstome.photo_resize' => 128]);
    Storage::fake('s3');
    Storage::fake('local');

    $gallery = Gallery::factory()->create(['ulid' => '1243ABC']);

    $rawFile = UploadedFile::fake()->image('photo.cr2', 300, 300);
    $photo = $gallery->addPhoto($rawFile);

    RawPhotoService::shouldReceive('isRawFile')->with($photo->source_name)->andReturn(true);
    RawPhotoService::shouldReceive('isExifToolAvailable')->andReturn(true);
    RawPhotoService::shouldReceive('extractJpgFromRaw')->andReturn(true);
    RawPhotoService::shouldReceive('getExifOrientation')->andReturn(1); // No rotation needed
    RawPhotoService::shouldReceive('cleanupTempFile');

    (new ProcessPhoto($photo))->handle();

    $photo->refresh();
    expect($photo->status)->toBe('processed');
    expect($photo->disk)->toBe('s3');
    expect(Storage::disk('s3')->exists($photo->path))->toBeTrue();
});

it('skips processing when exiftool is not available', function () {
    config(['picstome.photo_resize' => 128]);
    Storage::fake('s3');
    Storage::fake('local');

    $gallery = Gallery::factory()->create(['ulid' => '1243ABC']);

    $rawFile = UploadedFile::fake()->create('photo.cr2', 1024);
    $photo = $gallery->addPhoto($rawFile);

    RawPhotoService::shouldReceive('isRawFile')->with($photo->source_name)->andReturn(true);
    RawPhotoService::shouldReceive('isExifToolAvailable')->andReturn(false);

    (new ProcessPhoto($photo))->handle();

    $photo->refresh();
    expect($photo->status)->toBe('skipped');
});

it('skips processing when raw file has no extractable image', function () {
    config(['picstome.photo_resize' => 128]);
    Storage::fake('s3');
    Storage::fake('local');

    $gallery = Gallery::factory()->create(['ulid' => '1243ABC']);

    $rawFile = UploadedFile::fake()->create('photo.cr2', 1024);
    $photo = $gallery->addPhoto($rawFile);

    RawPhotoService::shouldReceive('isRawFile')->with($photo->source_name)->andReturn(true);
    RawPhotoService::shouldReceive('isExifToolAvailable')->andReturn(true);
    RawPhotoService::shouldReceive('extractJpgFromRaw')->andReturn(false);
    RawPhotoService::shouldReceive('cleanupTempFile');

    (new ProcessPhoto($photo))->handle();

    $photo->refresh();
    expect($photo->status)->toBe('skipped');
});

it('resizes extracted jpg from raw files according to gallery settings', function () {
    config(['picstome.photo_resize' => 200]);
    Storage::fake('s3');
    Storage::fake('local');

    $gallery = Gallery::factory()->create(['ulid' => '1243ABC']);

    $rawFile = UploadedFile::fake()->image('photo.cr2', 300, 300);
    $photo = $gallery->addPhoto($rawFile);

    RawPhotoService::shouldReceive('isRawFile')->with($photo->source_name)->andReturn(true);
    RawPhotoService::shouldReceive('isExifToolAvailable')->andReturn(true);
    RawPhotoService::shouldReceive('extractJpgFromRaw')->andReturn(true);
    RawPhotoService::shouldReceive('getExifOrientation')->andReturn(1); // No rotation needed
    RawPhotoService::shouldReceive('cleanupTempFile');

    (new ProcessPhoto($photo))->handle();

    $photo->refresh();
    expect($photo->status)->toBe('processed');
    expect($photo->disk)->toBe('s3');
});

it('keeps original size of extracted jpg when gallery setting is enabled', function () {
    config(['picstome.photo_resize' => 128]);
    Storage::fake('s3');
    Storage::fake('local');

    $gallery = Gallery::factory()->create(['ulid' => '1243ABC', 'keep_original_size' => true]);

    $rawFile = UploadedFile::fake()->create('photo.cr2', 1024);
    $photo = $gallery->addPhoto($rawFile);

    RawPhotoService::shouldReceive('isRawFile')->with($photo->source_name)->andReturn(true);
    RawPhotoService::shouldReceive('isExifToolAvailable')->andReturn(true);
    RawPhotoService::shouldReceive('extractJpgFromRaw')->andReturn(true);
    RawPhotoService::shouldReceive('getExifOrientation')->andReturn(1); // No rotation needed
    RawPhotoService::shouldReceive('cleanupTempFile');

    (new ProcessPhoto($photo))->handle();

    $photo->refresh();
    expect($photo->status)->toBe('processed');
    expect($photo->disk)->toBe('s3');
    expect($photo->raw_path)->not->toBeNull();
});

it('keeps oversized extracted jpg when keep_original_size is enabled and raw_path exists', function () {
    config(['picstome.max_photo_pixels' => 10000]); // 100x100
    Storage::fake('s3');
    Storage::fake('local');

    $gallery = Gallery::factory()->create(['ulid' => '1243ABC', 'keep_original_size' => true]);

    $rawFile = UploadedFile::fake()->create('photo.cr2', 1024);
    $photo = $gallery->addPhoto($rawFile);

    RawPhotoService::shouldReceive('isRawFile')->with($photo->source_name)->andReturn(true);
    RawPhotoService::shouldReceive('isExifToolAvailable')->andReturn(true);
    RawPhotoService::shouldReceive('extractJpgFromRaw')->andReturn(true);
    RawPhotoService::shouldReceive('getExifOrientation')->andReturn(1); // No rotation needed
    RawPhotoService::shouldReceive('cleanupTempFile');

    (new ProcessPhoto($photo))->handle();

    $photo->refresh();
    expect($photo->status)->toBe('pending'); // Status remains pending for oversized RAW files
    expect($gallery->fresh()->photos()->where('id', $photo->id)->exists())->toBeTrue();
});

it('cleans up temporary files after raw processing', function () {
    config(['picstome.photo_resize' => 128]);
    Storage::fake('s3');
    Storage::fake('local');

    $gallery = Gallery::factory()->create(['ulid' => '1243ABC']);

    $rawFile = UploadedFile::fake()->image('photo.cr2', 300, 300);
    $photo = $gallery->addPhoto($rawFile);

    RawPhotoService::shouldReceive('isRawFile')->with($photo->source_name)->andReturn(true);
    RawPhotoService::shouldReceive('isExifToolAvailable')->andReturn(true);
    RawPhotoService::shouldReceive('extractJpgFromRaw')->andReturn(true);
    RawPhotoService::shouldReceive('getExifOrientation')->andReturn(1); // No rotation needed
    RawPhotoService::shouldReceive('cleanupTempFile');

    (new ProcessPhoto($photo))->handle();

    $tempFiles = Storage::disk('local')->allFiles('photo-processing-temp');
    expect($tempFiles)->toHaveCount(0);
});

it('uses raw file size when raw_path exists and keep_original_size is enabled', function () {
    config(['picstome.photo_resize' => 128]);
    Storage::fake('s3');
    Storage::fake('local');

    $gallery = Gallery::factory()->create(['ulid' => '1243ABC', 'keep_original_size' => true]);

    $rawFile = UploadedFile::fake()->create('photo.cr2', 2048); // 2KB raw file
    $photo = $gallery->addPhoto($rawFile);

    RawPhotoService::shouldReceive('isRawFile')->with($photo->source_name)->andReturn(true);
    RawPhotoService::shouldReceive('isExifToolAvailable')->andReturn(true);
    RawPhotoService::shouldReceive('extractJpgFromRaw')->andReturn(true);
    RawPhotoService::shouldReceive('getExifOrientation')->andReturn(1); // No rotation needed
    RawPhotoService::shouldReceive('cleanupTempFile');

    (new ProcessPhoto($photo))->handle();

    $photo->refresh();
    expect($photo->status)->toBe('processed');
    expect($photo->raw_path)->not->toBeNull();
    expect($photo->size)->toBeGreaterThan(0); // Should have a size from the raw file
});

it('sanitizes unsafe characters from uploaded file names', function () {
    Storage::fake('s3');
    Storage::fake('local');

    $gallery = Gallery::factory()->create(['ulid' => '1243ABC']);

    $photo = $gallery->addPhoto(UploadedFile::fake()->createWithContent('a`id`.cr2', 'raw-content'));
    expect($photo->name)->toBe('a_id_.cr2');

    $photo = $gallery->addPhoto(UploadedFile::fake()->createWithContent('shell; injection $(cmd).jpg', 'fake-image'));
    expect($photo->name)->toBe('shell__injection___cmd_.jpg');
});

it('processes the superseded version instead of corrupting the current one', function () {
    Storage::fake('s3');
    Storage::fake('local');

    $gallery = Gallery::factory()->create(['ulid' => '1243ABC']);
    $photo = $gallery->addPhoto(UploadedFile::fake()->image('photo1.jpg', 200, 200));

    $version = PhotoVersion::factory()->for($photo)->create([
        'version_number' => 1,
        'name' => 'photo1.jpg',
        'source_name' => 'photo1.jpg',
        'path' => $photo->path,
        'size' => $photo->size,
        'status' => 'pending',
    ]);

    $photo->update(['version_count' => 2]);

    (new ProcessPhoto($photo, 1))->handle();

    expect($version->fresh()->status)->toBe('processed');
    expect($photo->fresh()->status)->toBe('pending');
    expect(Storage::disk('s3')->exists($photo->path))->toBeTrue();
});

it('does not delete retained version files while processing the current version', function () {
    Storage::fake('s3');
    Storage::fake('local');

    $gallery = Gallery::factory()->create(['ulid' => '1243ABC']);
    $oldPath = 'galleries/1243ABC/old-version.jpg';

    Storage::disk('s3')->put($oldPath, UploadedFile::fake()->image('old.jpg')->getContent());

    $photo = Photo::factory()->for($gallery)->create([
        'name' => 'photo1.jpg',
        'source_name' => 'photo1.jpg',
        'path' => $oldPath,
        'size' => 1024,
        'status' => 'processed',
        'version_count' => 1,
    ]);

    $gallery->addVersion(UploadedFile::fake()->image('photo1.jpg', 200, 200), $photo);

    (new ProcessPhoto($photo->fresh(), $photo->fresh()->version_count))->handle();

    expect(Storage::disk('s3')->exists($oldPath))->toBeTrue();
    expect($photo->fresh()->versions()->where('path', $oldPath)->exists())->toBeTrue();
});
