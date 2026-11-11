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

    // Regression: `handle()` used to let the RequestException escape, so the
    // photo stayed in `warming` and only `failed()` (after the queue retries
    // were exhausted) published it. Warming is best-effort and must not throw.
    (new WarmPhotoCdn($photo->id))->handle();

    expect($photo->fresh()->status)->toBe(Photo::STATUS_PROCESSED);
});

it('keeps warming the remaining variants when one of them returns a 500', function () {
    Storage::fake('s3');
    config([
        'picstome.photo_cdn_domain' => 'bunny',
        'filesystems.disks.s3.url' => 'https://cdn.example.test/bucket',
        'picstome.cdn_warming.enabled' => true,
    ]);

    $succeeded = [];
    Http::fake(function ($request) use (&$succeeded) {
        // First variant succeeds, the rest abort like the production CDN did.
        if ($succeeded === []) {
            $succeeded[] = $request->url();

            return Http::response('', 200);
        }

        return Http::response('<Code>InternalError</Code><Message>Transfer aborted</Message>', 500);
    });

    $photo = Photo::factory()->for(Gallery::factory())->create([
        'status' => Photo::STATUS_WARMING,
        'path' => 'galleries/photo.jpg',
        'thumb_path' => 'galleries/photo.jpg.thumb.webp',
        'stored_size' => 100,
    ]);

    (new WarmPhotoCdn($photo->id))->handle();

    expect($photo->fresh()->status)->toBe(Photo::STATUS_PROCESSED)
        ->and($succeeded)->toHaveCount(1);

    // Both variants were attempted even though only one succeeded.
    $requested = collect(Http::recorded())->map(fn ($pair) => $pair[0]->url())->unique();

    expect($requested)->toHaveCount(2)
        ->and($requested->contains(fn (string $url) => str_contains($url, 'photo.jpg.thumb.webp')))->toBeTrue();
});

it('does not retry a 4xx, only a 5xx', function () {
    Storage::fake('s3');
    config([
        'picstome.photo_cdn_domain' => 'bunny',
        'filesystems.disks.s3.url' => 'https://cdn.example.test/bucket',
        'picstome.cdn_warming.enabled' => true,
        'picstome.cdn_warming.tries' => 3,
    ]);
    Http::fake(['*' => Http::response('', 404)]);

    $photo = Photo::factory()->for(Gallery::factory())->create([
        'status' => Photo::STATUS_WARMING,
        'path' => 'galleries/photo.jpg',
        'thumb_path' => 'galleries/photo.jpg.thumb.webp',
        'stored_size' => 100,
    ]);

    (new WarmPhotoCdn($photo->id))->handle();

    // A missing object will not appear by asking again: one attempt per URL.
    expect(collect(Http::recorded()))->toHaveCount(2)
        ->and($photo->fresh()->status)->toBe(Photo::STATUS_PROCESSED);
});

it('retries a 5xx before giving up', function () {
    Storage::fake('s3');
    config([
        'picstome.photo_cdn_domain' => 'bunny',
        'filesystems.disks.s3.url' => 'https://cdn.example.test/bucket',
        'picstome.cdn_warming.enabled' => true,
        'picstome.cdn_warming.tries' => 3,
    ]);
    Http::fake(['*' => Http::response('', 500)]);

    $photo = Photo::factory()->for(Gallery::factory())->create([
        'status' => Photo::STATUS_WARMING,
        'path' => 'galleries/photo.jpg',
        'thumb_path' => 'galleries/photo.jpg.thumb.webp',
        'stored_size' => 100,
    ]);

    (new WarmPhotoCdn($photo->id))->handle();

    // Two variants, three attempts each.
    expect(collect(Http::recorded()))->toHaveCount(6)
        ->and($photo->fresh()->status)->toBe(Photo::STATUS_PROCESSED);
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
