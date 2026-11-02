<?php

use App\Jobs\ProcessPhoto;
use App\Jobs\WarmPhotoCdn;
use App\Models\Gallery;
use App\Models\Photo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('warms the grid variants and marks the photo processed', function () {
    Storage::fake('s3');
    config([
        'picstome.photo_cdn_domain' => 'bunny',
        'filesystems.disks.s3.url' => 'https://cdn.example.test/bucket',
        'picstome.cdn_warming.enabled' => true,
    ]);
    Http::fake(['*' => Http::response('', 200)]);

    $photo = Photo::factory()->for(Gallery::factory())->create([
        'status' => Photo::STATUS_WARMING,
        'path' => 'galleries/photo.jpg',
        'thumb_path' => 'galleries/photo.jpg.thumb.webp',
        'stored_size' => 100,
    ]);

    (new WarmPhotoCdn($photo->id))->handle();

    expect($photo->fresh()->status)->toBe(Photo::STATUS_PROCESSED);

    Http::assertSent(fn ($request) => str_contains($request->url(), 'photo.jpg.thumb.webp'));
});

it('publishes the photo anyway when warming keeps failing', function () {
    Storage::fake('s3');
    config(['picstome.photo_cdn_domain' => 'bunny']);
    Http::fake(['*' => Http::response('', 500)]);

    $photo = Photo::factory()->for(Gallery::factory())->create([
        'status' => Photo::STATUS_WARMING,
        'path' => 'galleries/photo.jpg',
        'stored_size' => 100,
    ]);

    try {
        (new WarmPhotoCdn($photo->id))->handle();
    } catch (Throwable $exception) {
        (new WarmPhotoCdn($photo->id))->failed($exception);
    }

    expect($photo->fresh()->status)->toBe(Photo::STATUS_PROCESSED);
});

it('dispatches the warming job after processing when enabled', function () {
    Storage::fake('s3');
    Storage::fake('local');
    Queue::fake();
    config(['picstome.cdn_warming.enabled' => true]);

    $gallery = Gallery::factory()->create(['ulid' => '1243ABC']);
    $photo = $gallery->addPhoto(UploadedFile::fake()->image('photo1.jpg', 300, 200));

    (new ProcessPhoto($photo))->handle();

    expect($photo->fresh()->status)->toBe(Photo::STATUS_WARMING);
    Queue::assertPushed(WarmPhotoCdn::class, fn ($job) => $job->photoId === $photo->id);
});
