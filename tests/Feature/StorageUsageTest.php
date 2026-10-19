<?php

use App\Models\Gallery;
use App\Models\Photo;
use App\Models\PhotoVersion;
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
