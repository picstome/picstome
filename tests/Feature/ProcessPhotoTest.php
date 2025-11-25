<?php

use App\Jobs\ProcessPhoto;
use App\Models\Gallery;
use \Facades\App\Services\RawPhotoService;
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

it('deletes oversized photo if keep_original_size is enabled', function () {
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

it('processes canon cr2 raw files by extracting jpg preview', function () {
    config(['picstome.photo_resize' => 128]);
    Storage::fake('s3');
    Storage::fake('local');

    $gallery = Gallery::factory()->create(['ulid' => '1243ABC']);

    $rawFile = UploadedFile::fake()->image('photo.cr2', 300, 300);
    $photo = $gallery->addPhoto($rawFile);

    RawPhotoService::shouldReceive('isRawFile')->with($photo->path)->andReturn(true);
    RawPhotoService::shouldReceive('isExifToolAvailable')->andReturn(true);
    RawPhotoService::shouldReceive('extractJpgFromRaw')->andReturn(true);
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

    RawPhotoService::shouldReceive('isRawFile')->with($photo->path)->andReturn(true);
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

    RawPhotoService::shouldReceive('isRawFile')->with($photo->path)->andReturn(true);
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

    RawPhotoService::shouldReceive('isRawFile')->with($photo->path)->andReturn(true);
    RawPhotoService::shouldReceive('isExifToolAvailable')->andReturn(true);
    RawPhotoService::shouldReceive('extractJpgFromRaw')->andReturn(true);
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

    RawPhotoService::shouldReceive('isRawFile')->with($photo->path)->andReturn(true);
    RawPhotoService::shouldReceive('isExifToolAvailable')->andReturn(true);
    RawPhotoService::shouldReceive('extractJpgFromRaw')->andReturn(true);
    RawPhotoService::shouldReceive('cleanupTempFile');

    (new ProcessPhoto($photo))->handle();

    $photo->refresh();
    expect($photo->status)->toBe('processed');
    expect($photo->disk)->toBe('s3');
});

it('deletes oversized extracted jpg when keep_original_size is enabled', function () {
    config(['picstome.max_photo_pixels' => 10000]); // 100x100
    Storage::fake('s3');
    Storage::fake('local');

    $gallery = Gallery::factory()->create(['ulid' => '1243ABC', 'keep_original_size' => true]);

    $rawFile = UploadedFile::fake()->create('photo.cr2', 1024);
    $photo = $gallery->addPhoto($rawFile);

    RawPhotoService::shouldReceive('isRawFile')->with($photo->path)->andReturn(true);
    RawPhotoService::shouldReceive('isExifToolAvailable')->andReturn(true);
    RawPhotoService::shouldReceive('extractJpgFromRaw')->andReturn(true);
    RawPhotoService::shouldReceive('cleanupTempFile');

    (new ProcessPhoto($photo))->handle();

    expect($gallery->fresh()->photos()->where('id', $photo->id)->exists())->toBeFalse();
});

it('cleans up temporary files after raw processing', function () {
    config(['picstome.photo_resize' => 128]);
    Storage::fake('s3');
    Storage::fake('local');

    $gallery = Gallery::factory()->create(['ulid' => '1243ABC']);

    $rawFile = UploadedFile::fake()->image('photo.cr2', 300, 300);
    $photo = $gallery->addPhoto($rawFile);

    RawPhotoService::shouldReceive('isRawFile')->with($photo->path)->andReturn(true);
    RawPhotoService::shouldReceive('isExifToolAvailable')->andReturn(true);
    RawPhotoService::shouldReceive('extractJpgFromRaw')->andReturn(true);
    RawPhotoService::shouldReceive('cleanupTempFile');

    (new ProcessPhoto($photo))->handle();

    $tempFiles = Storage::disk('local')->allFiles('photo-processing-temp');
    expect($tempFiles)->toHaveCount(0);
});
