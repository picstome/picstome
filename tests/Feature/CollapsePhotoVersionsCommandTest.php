<?php

use App\Models\Gallery;
use App\Models\Photo;
use App\Models\PhotoVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('reports versions without changing data by default', function () {
    $photo = Photo::factory()->for(Gallery::factory())->create(['version_count' => 2]);
    PhotoVersion::factory()->for($photo)->create(['version_number' => 1]);

    $this->artisan('photos:collapse-versions')->assertExitCode(0);

    expect($photo->fresh()->version_count)->toBe(2);
    expect($photo->versions()->count())->toBe(1);
});

it('removes historical rows but keeps files unless requested', function () {
    Storage::fake('s3');
    Storage::disk('s3')->put('galleries/current.jpg', 'current');
    Storage::disk('s3')->put('galleries/old.jpg', 'old');

    $photo = Photo::factory()->for(Gallery::factory())->create([
        'path' => 'galleries/current.jpg',
        'disk' => 's3',
        'version_count' => 2,
    ]);
    PhotoVersion::factory()->for($photo)->create([
        'version_number' => 1,
        'path' => 'galleries/old.jpg',
        'disk' => 's3',
    ]);

    $this->artisan('photos:collapse-versions', ['--force' => true])->assertExitCode(0);

    $photo->refresh();

    expect($photo->version_count)->toBe(1);
    expect($photo->current_version_id)->toBeNull();
    expect($photo->versions()->count())->toBe(0);
    expect(Storage::disk('s3')->exists('galleries/current.jpg'))->toBeTrue();
    expect(Storage::disk('s3')->exists('galleries/old.jpg'))->toBeTrue();
});

it('deletes historical files when explicitly requested', function () {
    Storage::fake('s3');
    Storage::disk('s3')->put('galleries/current.jpg', 'current');
    Storage::disk('s3')->put('galleries/old.jpg', 'old');

    $photo = Photo::factory()->for(Gallery::factory())->create([
        'path' => 'galleries/current.jpg',
        'disk' => 's3',
        'version_count' => 2,
    ]);
    PhotoVersion::factory()->for($photo)->create([
        'version_number' => 1,
        'path' => 'galleries/old.jpg',
        'disk' => 's3',
    ]);

    $this->artisan('photos:collapse-versions', ['--force' => true, '--delete-files' => true])
        ->assertExitCode(0);

    expect(Storage::disk('s3')->exists('galleries/current.jpg'))->toBeTrue();
    expect(Storage::disk('s3')->exists('galleries/old.jpg'))->toBeFalse();
});
