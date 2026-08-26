<?php

use App\Models\Gallery;
use App\Models\Photo;
use App\Models\PhotoComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->withPersonalTeam()->create();
    $this->team = $this->user->currentTeam;
});

it('navigates photos in natural name order', function () {
    $gallery = Gallery::factory()->for($this->team)->create();

    $photo1 = Photo::factory()->for($gallery)->create(['name' => 'IMG_1.jpg']);
    $photo2 = Photo::factory()->for($gallery)->create(['name' => 'IMG_2.jpg']);
    $photo10 = Photo::factory()->for($gallery)->create(['name' => 'IMG_10.jpg']);

    expect($photo1->next()->id)->toBe($photo2->id);
    expect($photo2->next()->id)->toBe($photo10->id);
    expect($photo10->next())->toBeNull();

    expect($photo10->previous()->id)->toBe($photo2->id);
    expect($photo2->previous()->id)->toBe($photo1->id);
    expect($photo1->previous())->toBeNull();
});

it('navigates only favorited photos', function () {
    $gallery = Gallery::factory()->for($this->team)->create();

    $photoA = Photo::factory()->favorited()->for($gallery)->create(['name' => 'A.jpg']);
    Photo::factory()->unfavorited()->for($gallery)->create(['name' => 'B.jpg']);
    $photoC = Photo::factory()->favorited()->for($gallery)->create(['name' => 'C.jpg']);

    expect($photoA->nextFavorite()->id)->toBe($photoC->id);
    expect($photoC->previousFavorite()->id)->toBe($photoA->id);
    expect($photoC->nextFavorite())->toBeNull();
});

it('navigates only commented photos', function () {
    $gallery = Gallery::factory()->for($this->team)->create();

    $photoA = Photo::factory()->for($gallery)->create(['name' => 'A.jpg']);
    Photo::factory()->for($gallery)->create(['name' => 'B.jpg']);
    $photoC = Photo::factory()->for($gallery)->create(['name' => 'C.jpg']);

    PhotoComment::factory()->for($photoA)->create();
    PhotoComment::factory()->for($photoC)->create();

    expect($photoA->nextCommented()->id)->toBe($photoC->id);
    expect($photoC->previousCommented()->id)->toBe($photoA->id);
    expect($photoC->nextCommented())->toBeNull();
});

it('caches photo ids instead of photo models for navigation', function () {
    $gallery = Gallery::factory()->for($this->team)->create();

    $photo1 = Photo::factory()->for($gallery)->create(['name' => 'IMG_1.jpg']);
    $photo2 = Photo::factory()->for($gallery)->create(['name' => 'IMG_2.jpg']);

    $photo1->next();

    $cachedIds = Cache::get("gallery:{$gallery->id}:photos:ids");

    expect($cachedIds)->toBeInstanceOf(Collection::class)
        ->and($cachedIds->all())->toBe([$photo1->id, $photo2->id]);
});

it('caches favorite and commented photo ids under their own keys', function () {
    $gallery = Gallery::factory()->for($this->team)->create();

    $photo1 = Photo::factory()->favorited()->for($gallery)->create(['name' => 'IMG_1.jpg']);
    $photo2 = Photo::factory()->favorited()->for($gallery)->create(['name' => 'IMG_2.jpg']);

    $photo1->nextFavorite();

    expect(Cache::get("gallery:{$gallery->id}:favorites:ids")->all())->toBe([$photo1->id, $photo2->id])
        ->and(Cache::get("gallery:{$gallery->id}:photos:ids"))->toBeNull();

    PhotoComment::factory()->for($photo2)->create();

    $photo2->nextCommented();

    expect(Cache::get("gallery:{$gallery->id}:commented:ids")->all())->toBe([$photo2->id]);
});

it('refreshes navigation caches when photos change', function () {
    $gallery = Gallery::factory()->for($this->team)->create();

    $photo1 = Photo::factory()->for($gallery)->create(['name' => 'IMG_1.jpg']);
    $photo2 = Photo::factory()->for($gallery)->create(['name' => 'IMG_2.jpg']);

    expect($photo1->next()->id)->toBe($photo2->id);

    $photo2->delete();

    expect($photo1->next())->toBeNull();
});

it('updates favorite navigation when a photo is unfavorited', function () {
    $gallery = Gallery::factory()->for($this->team)->create();

    $photoA = Photo::factory()->favorited()->for($gallery)->create(['name' => 'A.jpg']);
    $photoB = Photo::factory()->favorited()->for($gallery)->create(['name' => 'B.jpg']);

    expect($photoA->nextFavorite()->id)->toBe($photoB->id);

    $photoB->toggleFavorite();

    expect($photoA->nextFavorite())->toBeNull();
});

it('returns no favorite navigation for a photo that is not favorited', function () {
    $gallery = Gallery::factory()->for($this->team)->create();

    Photo::factory()->favorited()->for($gallery)->create(['name' => 'A.jpg']);
    Photo::factory()->favorited()->for($gallery)->create(['name' => 'B.jpg']);
    $unfavorited = Photo::factory()->unfavorited()->for($gallery)->create(['name' => 'C.jpg']);

    expect($unfavorited->nextFavorite())->toBeNull()
        ->and($unfavorited->previousFavorite())->toBeNull();
});

it('updates commented navigation when comments are added or removed', function () {
    $gallery = Gallery::factory()->for($this->team)->create();

    $photoA = Photo::factory()->for($gallery)->create(['name' => 'A.jpg']);
    $photoB = Photo::factory()->for($gallery)->create(['name' => 'B.jpg']);

    $comment = PhotoComment::factory()->for($photoA)->create();

    expect($photoA->nextCommented())->toBeNull();

    PhotoComment::factory()->for($photoB)->create();

    expect($photoA->nextCommented()->id)->toBe($photoB->id);

    $comment->delete();

    expect($photoB->previousCommented())->toBeNull();
});

it('hydrates gallery photos in natural order with comment counts', function () {
    $gallery = Gallery::factory()->for($this->team)->create();

    $photo10 = Photo::factory()->for($gallery)->create(['name' => 'IMG_10.jpg']);
    $photo2 = Photo::factory()->for($gallery)->create(['name' => 'IMG_2.jpg']);
    PhotoComment::factory()->count(2)->for($photo10)->create();

    $photos = $gallery->photosInOrder($gallery->photoIds());

    expect($photos->pluck('name')->all())->toBe(['IMG_2.jpg', 'IMG_10.jpg'])
        ->and($photos->get(1)->comments_count)->toBe(2);
});

it('skips photos missing from the database when hydrating in order', function () {
    $gallery = Gallery::factory()->for($this->team)->create();

    $photo = Photo::factory()->for($gallery)->create(['name' => 'IMG_1.jpg']);
    $staleIds = collect([$photo->id, 999999]);

    $photos = $gallery->photosInOrder($staleIds);

    expect($photos->pluck('id')->all())->toBe([$photo->id]);
});
