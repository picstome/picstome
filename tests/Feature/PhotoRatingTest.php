<?php

use App\Models\Gallery;
use App\Models\Photo;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('invalidates the cached favorites list when five stars selects a photo', function () {
    $gallery = Gallery::factory()->create();
    $photo = Photo::factory()->for($gallery)->unfavorited()->create();

    expect($gallery->favoritePhotoIds())->toHaveCount(0);

    $photo->setRating(5);

    expect($photo->fresh()->isFavorited())->toBeTrue()
        ->and($gallery->favoritePhotoIds())->toHaveCount(1);
});

it('invalidates the cached favorites list when zero clears a rating', function () {
    $gallery = Gallery::factory()->create();
    $photo = Photo::factory()->for($gallery)->rated(5)->favorited()->create();

    expect($gallery->favoritePhotoIds())->toHaveCount(1);

    $photo->setRating(0);

    expect($photo->fresh()->isFavorited())->toBeFalse()
        ->and($gallery->favoritePhotoIds())->toHaveCount(0);
});

it('keeps the heart untouched when rating between one and four stars', function () {
    $gallery = Gallery::factory()->create();
    $photo = Photo::factory()->for($gallery)->unfavorited()->create();

    $photo->setRating(4);

    expect($photo->fresh()->isFavorited())->toBeFalse()
        ->and($gallery->favoritePhotoIds())->toHaveCount(0);
});
