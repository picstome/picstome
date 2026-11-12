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

it('uses the gallery cover focal point for the shared cover image', function () {
    Storage::fake('s3');
    config([
        'picstome.photo_cdn_domain' => 'wsrv.nl',
        'filesystems.disks.s3.url' => 'https://cdn.example.test/bucket',
    ]);

    $gallery = Gallery::factory()->shared()->create();
    $photo = Photo::factory()->for($gallery)->create([
        'disk' => 's3',
        'path' => 'galleries/cover.jpg',
    ]);

    $gallery->setCoverPhoto($photo);
    $gallery->setCoverFocalPoint(0.25, 0.75);
    $gallery->load('coverPhoto.gallery');

    parse_str(parse_url($gallery->coverPhoto->cover_image_url, PHP_URL_QUERY), $query);

    expect($query['fit'])->toBe('cover')
        ->and($query['a'])->toBe('focal')
        ->and((float) $query['fpx'])->toBe(0.25)
        ->and((float) $query['fpy'])->toBe(0.75);
});

it('uses focus crop for bunny cover images', function () {
    Storage::fake('s3');
    config([
        'picstome.photo_cdn_domain' => 'bunny',
        'filesystems.disks.s3.url' => 'https://cdn.example.test/bucket',
    ]);

    $gallery = Gallery::factory()->shared()->create();
    $photo = Photo::factory()->for($gallery)->create([
        'disk' => 's3',
        'path' => 'galleries/cover.jpg',
    ]);

    $gallery->setCoverPhoto($photo);
    $gallery->setCoverFocalPoint(0.25, 0.75);
    $gallery->load('coverPhoto.gallery');

    parse_str(parse_url($gallery->coverPhoto->cover_image_url, PHP_URL_QUERY), $query);

    expect($query['focus_crop'])->toBe('2560,960,0.25,0.75')
        ->and($query['format'])->toBe('webp');
});

it('builds a playable url for video photos and versions', function () {
    Storage::fake('s3');
    config([
        'picstome.photo_cdn_domain' => 'wsrv.nl',
        'filesystems.disks.s3.url' => 'https://cdn.example.test/bucket',
    ]);

    $photo = Photo::factory()->video()->for(Gallery::factory())->create([
        'disk' => 's3',
        'path' => 'galleries/clip.mp4',
    ]);

    $version = PhotoVersion::factory()->for($photo)->create([
        'path' => 'galleries/clip-old.mp4',
        'disk' => 's3',
    ]);

    expect(PhotoUrlService::make($photo)->video())
        ->toBe('https://cdn.example.test/bucket/galleries/clip.mp4')
        ->and(PhotoUrlService::make($version, $photo->gallery)->video())
        ->toBe('https://cdn.example.test/bucket/galleries/clip-old.mp4');
});

it('does not build a video url for images', function () {
    Storage::fake('s3');
    config(['filesystems.disks.s3.url' => 'https://cdn.example.test/bucket']);

    $photo = Photo::factory()->for(Gallery::factory())->create([
        'disk' => 's3',
        'path' => 'galleries/photo.jpg',
    ]);

    expect(PhotoUrlService::make($photo)->video())->toBe('');
});
