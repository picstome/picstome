<?php

use App\Models\Gallery;
use App\Models\Photo;
use App\Models\PhotoVersion;
use App\Services\StorageUsageService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('counts current files and historical versions exactly once', function () {
    $gallery = Gallery::factory()->create();

    $photo = Photo::factory()->for($gallery)->create([
        'size' => 100,
        'stored_size' => 100,
    ]);

    $version = PhotoVersion::factory()->for($photo)->create([
        'size' => 40,
        'stored_size' => 40,
    ]);

    expect($gallery->fresh()->getTotalStorageSize())->toBe(140);

    $photo->update(['current_version_id' => $version->id]);

    expect($gallery->fresh()->getTotalStorageSize())->toBe(100);
});

it('totals storage across every gallery without double counting current versions', function () {
    $firstGallery = Gallery::factory()->create();
    $secondGallery = Gallery::factory()->create();

    $firstPhoto = Photo::factory()->for($firstGallery)->create([
        'size' => 100,
        'stored_size' => 100,
    ]);

    $firstVersion = PhotoVersion::factory()->for($firstPhoto)->create([
        'size' => 40,
        'stored_size' => 40,
    ]);

    Photo::factory()->for($secondGallery)->create([
        'size' => 250,
        'stored_size' => 250,
    ]);

    expect(StorageUsageService::total())->toBe(390);

    $firstPhoto->update(['current_version_id' => $firstVersion->id]);

    expect(StorageUsageService::total())->toBe(350);
});
