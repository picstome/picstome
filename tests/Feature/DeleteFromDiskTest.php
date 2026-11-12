<?php

use App\Jobs\DeleteFromDisk;
use App\Models\Gallery;
use App\Models\Photo;
use App\Models\PhotoVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

it('deletes an unreferenced thumbnail from the disk', function () {
    Storage::fake('s3');
    Storage::disk('s3')->put('galleries/photo.jpg.thumb.webp', 'thumb');

    (new DeleteFromDisk('galleries/photo.jpg.thumb.webp', 's3'))->handle();

    Storage::disk('s3')->assertMissing('galleries/photo.jpg.thumb.webp');
});

it('does not delete a thumbnail referenced by a photo', function () {
    Storage::fake('s3');
    Storage::disk('s3')->put('galleries/photo.jpg.thumb.webp', 'thumb');

    Photo::factory()->for(Gallery::factory())->create([
        'thumb_path' => 'galleries/photo.jpg.thumb.webp',
        'disk' => 's3',
    ]);

    (new DeleteFromDisk('galleries/photo.jpg.thumb.webp', 's3'))->handle();

    Storage::disk('s3')->assertExists('galleries/photo.jpg.thumb.webp');
});

it('does not delete a file referenced as a raw_path by a photo', function () {
    Storage::fake('s3');
    Storage::disk('s3')->put('galleries/raw.cr2', 'raw');

    Photo::factory()->for(Gallery::factory())->create([
        'path' => 'galleries/current.jpg',
        'raw_path' => 'galleries/raw.cr2',
        'disk' => 's3',
    ]);

    (new DeleteFromDisk('galleries/raw.cr2', 's3'))->handle();

    Storage::disk('s3')->assertExists('galleries/raw.cr2');
});

it('does not delete a thumbnail referenced as a raw_path by a version', function () {
    Storage::fake('s3');
    Storage::disk('s3')->put('galleries/old.cr2', 'raw');

    $photo = Photo::factory()->for(Gallery::factory())->create([
        'path' => 'galleries/current.jpg',
        'disk' => 's3',
    ]);

    PhotoVersion::factory()->for($photo)->create([
        'path' => 'galleries/older.jpg',
        'raw_path' => 'galleries/old.cr2',
        'disk' => 's3',
    ]);

    (new DeleteFromDisk('galleries/old.cr2', 's3'))->handle();

    Storage::disk('s3')->assertExists('galleries/old.cr2');
});

it('ignores the given photo id when checking references', function () {
    Storage::fake('s3');
    Storage::disk('s3')->put('galleries/own.jpg', 'own');

    $photo = Photo::factory()->for(Gallery::factory())->create([
        'path' => 'galleries/own.jpg',
        'disk' => 's3',
    ]);

    // The row still exists (sync queue) but is the one asking for the delete.
    (new DeleteFromDisk('galleries/own.jpg', 's3', $photo->id))->handle();

    Storage::disk('s3')->assertMissing('galleries/own.jpg');
});

it('checks each path column independently', function () {
    Storage::fake('s3');
    Storage::disk('s3')->put('galleries/x.jpg', 'x');

    $photo = Photo::factory()->for(Gallery::factory())->create([
        'path' => 'galleries/other.jpg',
        'thumb_path' => 'galleries/x.jpg',
        'disk' => 's3',
    ]);

    // `thumb_path` is the third column checked; a bug that only inspects the
    // first one would delete a file that is still in use.
    (new DeleteFromDisk('galleries/x.jpg', 's3'))->handle();

    Storage::disk('s3')->assertExists('galleries/x.jpg');
});

it('checks references with one indexed query per column instead of an or-group', function () {
    Storage::fake('s3');
    Storage::disk('s3')->put('galleries/orphan.jpg', 'orphan');

    DB::enableQueryLog();

    (new DeleteFromDisk('galleries/orphan.jpg', 's3'))->handle();

    $queries = collect(DB::getQueryLog())
        ->pluck('query')
        ->filter(fn (string $sql) => str_contains($sql, 'select exists')
            && (str_contains($sql, 'photos') || str_contains($sql, 'photo_versions')));

    // Six independent lookups (three columns x two tables) and, crucially,
    // no `or "raw_path"` inside a single statement: MySQL cannot use an index
    // through an OR across different columns, which is what made each check a
    // full table scan.
    expect($queries)->toHaveCount(6)
        ->and($queries->contains(fn (string $sql) => str_contains($sql, 'or "')))->toBeFalse()
        ->and($queries->contains(fn (string $sql) => str_contains($sql, 'or `')))->toBeFalse();
});
