<?php

use App\Jobs\DeleteFromDisk;
use App\Models\Gallery;
use App\Models\Photo;
use App\Models\PhotoVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('deletes unreferenced files from the disk', function () {
    Storage::fake('s3');
    Storage::disk('s3')->put('galleries/orphan.jpg', 'orphan');

    (new DeleteFromDisk('galleries/orphan.jpg', 's3'))->handle();

    Storage::disk('s3')->assertMissing('galleries/orphan.jpg');
});

it('does not delete a file referenced by a photo', function () {
    Storage::fake('s3');
    Storage::disk('s3')->put('galleries/current.jpg', 'current');

    Photo::factory()->for(Gallery::factory())->create([
        'path' => 'galleries/current.jpg',
        'disk' => 's3',
    ]);

    (new DeleteFromDisk('galleries/current.jpg', 's3'))->handle();

    Storage::disk('s3')->assertExists('galleries/current.jpg');
});

it('does not delete a file referenced by a photo version', function () {
    Storage::fake('s3');
    Storage::disk('s3')->put('galleries/old.jpg', 'old');

    $photo = Photo::factory()->for(Gallery::factory())->create([
        'path' => 'galleries/current.jpg',
        'disk' => 's3',
    ]);

    PhotoVersion::factory()->for($photo)->create([
        'path' => 'galleries/old.jpg',
        'disk' => 's3',
    ]);

    (new DeleteFromDisk('galleries/old.jpg', 's3'))->handle();

    Storage::disk('s3')->assertExists('galleries/old.jpg');
});

it('does not immediately delete a version file still referenced by the current photo', function () {
    Storage::fake('s3');
    Storage::disk('s3')->put('galleries/shared.jpg', 'shared');

    $photo = Photo::factory()->for(Gallery::factory())->create([
        'path' => 'galleries/shared.jpg',
        'disk' => 's3',
    ]);

    $version = PhotoVersion::factory()->for($photo)->create([
        'path' => 'galleries/shared.jpg',
        'disk' => 's3',
    ]);

    $version->deleteFilesImmediately();

    Storage::disk('s3')->assertExists('galleries/shared.jpg');
});
