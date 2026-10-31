<?php

use App\Models\Gallery;
use App\Models\Photo;
use App\Models\PhotoVersion;
use App\Services\PhotoUrlService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('builds distinct thumbnail sizes and the original url for versions', function () {
    Storage::fake('s3');
    config([
        'picstome.photo_cdn_domain' => 'wsrv.nl',
        'filesystems.disks.s3.url' => null,
        'filesystems.disks.s3.origin' => null,
    ]);

    $photo = Photo::factory()->for(Gallery::factory())->create(['disk' => 's3']);
    $version = PhotoVersion::factory()->for($photo)->create([
        'path' => 'galleries/version.jpg',
        'disk' => 's3',
    ]);

    $service = PhotoUrlService::make($version, $photo->gallery);

    parse_str(parse_url($service->thumbnail(), PHP_URL_QUERY), $thumbnail);
    parse_str(parse_url($service->smallThumbnail(), PHP_URL_QUERY), $small);
    parse_str(parse_url($service->largeThumbnail(), PHP_URL_QUERY), $large);

    expect($thumbnail['h'])->toBe((string) config('picstome.photo_thumb_resize', 1000));
    expect($small['h'])->toBe((string) config('picstome.photo_small_thumb_resize', 500));
    expect($large['h'])->toBe((string) config('picstome.photo_resize', 2048));
    expect($service->original())->toBe(Storage::disk('s3')->url('galleries/version.jpg'));
});

it('uses the configured disk url for videos', function () {
    Storage::fake('s3');
    config(['filesystems.disks.s3.url' => 'https://cdn.example.test/bucket']);

    $photo = Photo::factory()->for(Gallery::factory())->create([
        'disk' => 's3',
        'path' => 'galleries/video.mp4',
    ]);

    expect($photo->url)->toBe('https://cdn.example.test/bucket/galleries/video.mp4');
});

it('uses the stored thumbnail for grids and versions it for cdn invalidation', function () {
    Storage::fake('s3');
    config([
        'picstome.photo_cdn_domain' => 'bunny',
        'filesystems.disks.s3.url' => 'https://cdn.example.test/bucket',
    ]);

    $photo = Photo::factory()->for(Gallery::factory())->create([
        'disk' => 's3',
        'path' => 'galleries/photo.jpg',
        'thumb_path' => 'galleries/photo.jpg.thumb.webp',
        'stored_size' => 321,
    ]);

    expect($photo->small_thumbnail_url)
        ->toBe('https://cdn.example.test/bucket/galleries/photo.jpg.thumb.webp?v=321')
        ->and($photo->thumbnail_url)->toContain('width=')
        ->and($photo->thumbnail_url)->toContain('v=321');
});

it('falls back to the cdn resize when a photo has no stored thumbnail', function () {
    Storage::fake('s3');
    config(['picstome.photo_cdn_domain' => 'bunny']);
    config(['filesystems.disks.s3.url' => 'https://cdn.example.test/bucket']);

    $photo = Photo::factory()->for(Gallery::factory())->create([
        'disk' => 's3',
        'path' => 'galleries/photo.jpg',
        'thumb_path' => null,
        'stored_size' => 123,
    ]);

    expect($photo->small_thumbnail_url)
        ->toContain('width=500')
        ->toContain('v=123');
});
