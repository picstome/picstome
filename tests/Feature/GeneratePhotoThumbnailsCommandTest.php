<?php

use App\Models\Gallery;
use App\Models\Photo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('generates missing thumbnails and counts them in the stored size', function () {
    Storage::fake('s3');
    Storage::fake('local');

    $path = 'galleries/thumb-test/photo.jpg';
    Storage::disk('s3')->put($path, UploadedFile::fake()->image('photo.jpg', 800, 600)->getContent());

    $photo = Photo::factory()->for(Gallery::factory())->create([
        'path' => $path,
        'disk' => 's3',
        'size' => Storage::disk('s3')->size($path),
        'stored_size' => Storage::disk('s3')->size($path),
    ]);

    $this->artisan('photos:generate-thumbnails')->assertExitCode(0);

    $photo->refresh();

    expect($photo->thumb_path)->not->toBeNull()
        ->and(Storage::disk('s3')->exists($photo->thumb_path))->toBeTrue()
        ->and($photo->stored_size)->toBe(
            Storage::disk('s3')->size($photo->path) + Storage::disk('s3')->size($photo->thumb_path)
        );
});

it('does not write anything during a dry run', function () {
    Storage::fake('s3');
    Storage::fake('local');

    $path = 'galleries/thumb-test/photo.jpg';
    Storage::disk('s3')->put($path, UploadedFile::fake()->image('photo.jpg', 800, 600)->getContent());

    $photo = Photo::factory()->for(Gallery::factory())->create([
        'path' => $path,
        'disk' => 's3',
        'stored_size' => 0,
    ]);

    $initialStoredSize = $photo->fresh()->stored_size;

    $this->artisan('photos:generate-thumbnails', ['--dry-run' => true])->assertExitCode(0);

    expect($photo->fresh()->thumb_path)->toBeNull()
        ->and($photo->fresh()->stored_size)->toBe($initialStoredSize);
});
