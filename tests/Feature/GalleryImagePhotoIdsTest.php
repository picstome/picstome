<?php

use App\Models\Gallery;
use App\Models\Photo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->withPersonalTeam()->create();
    $this->team = $this->user->currentTeam;
});

it('lists the visitor-facing ids of displayable images only', function () {
    $gallery = Gallery::factory()->for($this->team)->create([
        'is_public' => true,
    ]);

    $image = Photo::factory()->for($gallery)->create([
        'name' => 'a.jpg',
        'path' => 'photos/a.jpg',
        'status' => 'processed',
    ]);

    // A video ships in the ZIP but is not part of the image viewer list.
    Photo::factory()->for($gallery)->video()->create(['status' => 'processed']);

    // Pending, failed and archived images are never paged through.
    Photo::factory()->for($gallery)->create([
        'name' => 'b.jpg',
        'path' => 'photos/b.jpg',
        'status' => 'processing',
    ]);

    $archived = Photo::factory()->for($gallery)->create([
        'name' => 'c.jpg',
        'path' => 'photos/c.jpg',
        'status' => 'processed',
    ]);
    $archived->archive();

    expect($gallery->readyImagePhotoIds()->all())->toBe([$image->id]);
});

it('keeps the image list in natural name order', function () {
    $gallery = Gallery::factory()->for($this->team)->create();

    $ten = Photo::factory()->for($gallery)->create([
        'name' => 'photo-10.jpg',
        'path' => 'photos/photo-10.jpg',
        'status' => 'processed',
    ]);

    $two = Photo::factory()->for($gallery)->create([
        'name' => 'photo-2.jpg',
        'path' => 'photos/photo-2.jpg',
        'status' => 'processed',
    ]);

    expect($gallery->readyImagePhotoIds()->all())->toBe([$two->id, $ten->id]);
});

it('invalidates the image list when a photo becomes visible or hidden', function () {
    $gallery = Gallery::factory()->for($this->team)->create();

    $photo = Photo::factory()->for($gallery)->create([
        'name' => 'a.jpg',
        'path' => 'photos/a.jpg',
        'status' => 'processing',
    ]);

    expect($gallery->readyImagePhotoIds())->toBeEmpty();

    $photo->update(['status' => 'processed']);

    expect($gallery->readyImagePhotoIds()->all())->toBe([$photo->id]);

    $photo->archive();

    expect($gallery->readyImagePhotoIds())->toBeEmpty();
});

it('pages through the public portfolio with the neighbours from the cached image list', function () {
    $gallery = Gallery::factory()->for($this->team)->create([
        'is_public' => true,
    ]);

    $first = Photo::factory()->for($gallery)->create([
        'name' => 'photo-1.jpg',
        'path' => 'photos/photo-1.jpg',
        'status' => 'processed',
    ]);

    $second = Photo::factory()->for($gallery)->create([
        'name' => 'photo-2.jpg',
        'path' => 'photos/photo-2.jpg',
        'status' => 'processed',
    ]);

    $third = Photo::factory()->for($gallery)->create([
        'name' => 'photo-3.jpg',
        'path' => 'photos/photo-3.jpg',
        'status' => 'processed',
    ]);

    $component = Livewire::test('pages::portfolio.photos.show', ['photo' => $second]);

    expect($component->instance()->previous?->id)->toBe($first->id)
        ->and($component->instance()->next?->id)->toBe($third->id)
        ->and($component->instance()->next?->gallery->is($gallery))->toBeTrue();
});
