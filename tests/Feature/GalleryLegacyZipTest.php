<?php

use App\Models\Gallery;
use App\Models\Photo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->withPersonalTeam()->create();
    $this->team = $this->user->currentTeam;
});

it('excludes pending and archived photos from the legacy download', function () {
    $gallery = Gallery::factory()->for($this->team)->create();

    $ready = Photo::factory()->for($gallery)->create(['status' => 'processed']);
    Photo::factory()->for($gallery)->create(['status' => 'pending']);
    Photo::factory()->for($gallery)->create([
        'status' => 'processed',
        'archived_at' => now(),
    ]);

    expect($gallery->downloadablePhotos()->pluck('id')->all())
        ->toBe([$ready->id]);
});

it('limits the favorites download variant to ready non-archived photos', function () {
    $gallery = Gallery::factory()->for($this->team)->create();

    $ready = Photo::factory()->for($gallery)->favorited()->create(['status' => 'processed']);
    Photo::factory()->for($gallery)->favorited()->create(['status' => 'pending']);
    Photo::factory()->for($gallery)->create([
        'status' => 'processed',
        'archived_at' => now(),
    ]);
    Photo::factory()->for($gallery)->create(['status' => 'processed']);

    expect($gallery->downloadablePhotos(favorites: true)->pluck('id')->all())
        ->toBe([$ready->id]);
});

it('streams the legacy zip without a pending or archived file', function () {
    config()->set('picstome.gallery_archives.enabled', false);

    Storage::fake('s3');

    $gallery = Gallery::factory()->for($this->team)->create(['name' => 'Example Gallery']);
    $ready = Photo::factory()->for($gallery)->create(['status' => 'processed']);

    Storage::disk('s3')->put($ready->path, 'ready-bytes');
    Storage::disk('s3')->put(
        Photo::factory()->for($gallery)->create(['status' => 'pending'])->path,
        'pending-bytes',
    );

    $response = $this->actingAs($this->user)->get(route('galleries.download', $gallery));

    expect($response->isSuccessful())->toBeTrue()
        ->and($response->baseResponse->headers->get('content-disposition'))
        ->toContain('example-gallery.zip');
});
