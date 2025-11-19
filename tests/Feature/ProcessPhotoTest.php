<?php

use App\Jobs\ProcessPhoto;
use App\Models\Gallery;
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
});
