<?php

use App\Models\Gallery;
use App\Models\Photo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->withPersonalTeam()->create();
    $this->team = $this->user->currentTeam;
});

test('shared gallery can be viewed', function () {
    $gallery = Gallery::factory()->shared()->create(['ulid' => '0123ABC']);

    $response = get('/shares/0123ABC');

    $response->assertStatus(200);
});

test('visitors can view a shared gallery', function () {
    $gallery = Gallery::factory()->shared()->create(['ulid' => '0123ABC']);
    $photoA = Photo::factory()->for($gallery)->create();
    $photoB = Photo::factory()->for(Gallery::factory())->create();
    $photoC = Photo::factory()->for($gallery)->create();

    $response = get('/shares/0123ABC');
    $component = Volt::test('pages.shares.show', ['gallery' => $gallery]);

    $response->assertStatus(200);
    $response->assertViewHas('gallery');
    expect($response['gallery']->is($gallery))->toBeTrue();

    $component->assertViewHas('allPhotos');
    expect($component->viewData('allPhotos')->contains($photoA))->toBeTrue();
    expect($component->viewData('allPhotos')->contains($photoB))->toBeFalse();
    expect($component->viewData('allPhotos')->contains($photoC))->toBeTrue();
});

test('unshared gallery can not be viewed', function () {
    $gallery = Gallery::factory()->unshared()->create(['ulid' => '0123ABC']);

    $response = get('/shares/0123ABC');

    $response->assertStatus(404);
});

test('unauthenticated visitors to a password-protected gallery are redirected to the unlock page', function () {
    $gallery = Gallery::factory()->shared()->protected()->create(['ulid' => '0123ABC']);

    $response = get('/shares/0123ABC');

    $response->assertRedirect('/shares/0123ABC/unlock');
});

test('visitors with unlocked gallery can view the password-protected gallery', function () {
    $gallery = Gallery::factory()->shared()->protected()->create(['ulid' => '0123ABC']);
    session()->put('unlocked_gallery_ulid', '0123ABC');

    $response = get('/shares/0123ABC');

    $response->assertStatus(200);
});

test('visitors can view shared gallery favorites', function () {
    $gallery = Gallery::factory()->shared()->create();
    $favorite = Photo::factory()->for($gallery)->favorited()->create();

    $component = Volt::test('pages.shares.show', ['gallery' => $gallery]);

    expect($component->favorites->contains($favorite))->toBeTrue();
});

test('visitors can favorite a photo', function () {
    $photo = Photo::factory()->for(Gallery::factory()->selectable())->unfavorited()->create();
    expect($photo->isFavorited())->toBeFalse();

    $component = Volt::test('shared-photo-item', ['photo' => $photo])
        ->call('favorite');

    expect($photo->fresh()->isFavorited())->toBeTrue();
});

test('visitors cannot favorite a photo when gallery is not selectable', function () {
    $photo = Photo::factory()->for(Gallery::factory()->unselectable())->unfavorited()->create();
    expect($photo->isFavorited())->toBeFalse();

    $component = Volt::test('shared-photo-item', ['photo' => $photo])
        ->call('favorite');

    $component->assertStatus(403);
});
