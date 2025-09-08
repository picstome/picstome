<?php

use App\Models\Gallery;
use App\Models\Photo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

test('photo can be viewed when the gallery is shared', function () {
    $gallery = Gallery::factory()->shared()->has(Photo::factory())->create(['ulid' => '0123ABC']);

    $response = get('/shares/0123ABC/photos/1');

    $response->assertStatus(200);
});

test('photo cannot be viewed unless the gallery is shared', function () {
    $gallery = Gallery::factory(['ulid' => '0123ABC'])->unshared()->has(Photo::factory())->create();

    $response = get('/shares/0123ABC/photos/1');

    $response->assertStatus(404);
});

test('favorite photo when gallery is selectable', function () {
    $gallery = Gallery::factory()->selectable()->has(Photo::factory()->unfavorited())->create();
    expect($gallery->photos()->first()->isFavorited())->toBeFalse();

    $component = Volt::test('pages.shares.photos.show', ['photo' => $gallery->photos()->first()])
        ->call('favorite');

    expect($gallery->photos()->first()->isFavorited())->toBeTrue();
});

test('photo cannot be favorited when the gallery is not selectable', function () {
    $gallery = Gallery::factory()->unselectable()->has(Photo::factory()->unfavorited())->create();
    expect($gallery->photos()->first()->isFavorited())->toBeFalse();

    $component = Volt::test('pages.shares.photos.show', ['photo' => $gallery->photos()->first()])
        ->call('favorite');

    expect($gallery->photos()->first()->isFavorited())->toBeFalse();
});

test('favoriting a photo dispatches a selection limit reached event when the gallery selection limit is exceeded', function () {
    $gallery = Gallery::factory()->shared()->selectable(limit: 5)->has(
        Photo::factory()->favorited()->count(5)
    )->create();
    $gallery->photos()->save(Photo::factory()->unfavorited()->make());
    expect($gallery->photos()->favorited()->count())->toBe(5);

    $component = Volt::test('pages.shares.photos.show', ['photo' => $gallery->photos()->unfavorited()->first()])
        ->call('favorite');

    $component->assertDispatched('selection-limit-reached');
});

test('unauthenticated visitors to a password-protected gallery are redirected to the unlock page', function () {
    $gallery = Gallery::factory()->shared()->protected()->has(Photo::factory())->create(['ulid' => '0123ABC']);

    $response = get('/shares/0123ABC/photos/1');

    $response->assertRedirect('/shares/0123ABC/unlock');
});

test('visitors with unlocked gallery can view the password-protected photo', function () {
    $gallery = Gallery::factory()->shared()->protected()->has(Photo::factory())->create(['ulid' => '0123ABC']);
    session()->put('unlocked_gallery_ulid', '0123ABC');

    $response = get('/shares/0123ABC/photos/1');

    $response->assertStatus(200);
});

test('favorite photo via browser on watermarked gallery', function () {
    $gallery = Gallery::factory()->shared()->selectable()->watermarked()->has(Photo::factory()->unfavorited())->create(['ulid' => 'test123']);
    $photo = $gallery->photos()->first();

    $page = visit("/shares/test123/photos/{$photo->id}");

    $page->pressAndWaitFor('favorite', 0.1);

    expect($photo->fresh()->isFavorited())->toBeTrue();
});
