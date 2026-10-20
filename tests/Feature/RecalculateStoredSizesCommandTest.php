<?php

use App\Models\Gallery;
use App\Models\Photo;
use App\Models\PhotoVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('recalculates stored sizes from the configured disk without counting missing files', function () {
    Storage::fake('s3');

    $gallery = Gallery::factory()->create();

    Storage::disk('s3')->put('photos/current.jpg', str_repeat('a', 100));
    Storage::disk('s3')->put('photos/raw.cr3', str_repeat('b', 50));
    Storage::disk('s3')->put('photos/version.jpg', str_repeat('c', 25));

    $photo = Photo::factory()->for($gallery)->create([
        'path' => 'photos/current.jpg',
        'raw_path' => 'photos/raw.cr3',
        'disk' => 's3',
        'size' => 100,
        'stored_size' => 0,
    ]);

    $version = PhotoVersion::factory()->for($photo)->create([
        'path' => 'photos/version.jpg',
        'disk' => 's3',
        'size' => 25,
        'stored_size' => 0,
    ]);

    $this->artisan('storage:recalculate')->assertExitCode(0);

    expect($photo->fresh()->stored_size)->toBe(150);
    expect($version->fresh()->stored_size)->toBe(25);
});

it('does not modify stored sizes during a dry run', function () {
    Storage::fake('s3');
    Storage::disk('s3')->put('photos/current.jpg', str_repeat('a', 100));

    $photo = Photo::factory()->for(Gallery::factory())->create([
        'path' => 'photos/current.jpg',
        'disk' => 's3',
        'stored_size' => 0,
    ]);

    $initial = $photo->fresh()->stored_size;

    $this->artisan('storage:recalculate', ['--dry-run' => true])->assertExitCode(0);

    expect($photo->fresh()->stored_size)->toBe($initial);
});
