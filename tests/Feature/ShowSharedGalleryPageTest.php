<?php

use App\Models\Gallery;
use App\Models\Photo;
use App\Models\User;
use App\Notifications\SelectionLimitReached;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->withPersonalTeam()->create();
    $this->team = $this->user->currentTeam;
});

test('shared gallery can be viewed', function () {
    $gallery = Gallery::factory()->shared()->for($this->team)->create(['ulid' => '0123ABC']);

    $response = get('/shares/0123ABC/'.$gallery->slug);

    $response->assertStatus(200);
});

test('visitors can view a shared gallery', function () {
    $gallery = Gallery::factory()->shared()->for($this->team)->create(['ulid' => '0123ABC']);
    $photoA = Photo::factory()->for($gallery)->create();
    $photoB = Photo::factory()->for(Gallery::factory()->for($this->team))->create();
    $photoC = Photo::factory()->for($gallery)->create();

    $response = get('/shares/0123ABC/'.$gallery->slug);
    $component = Livewire::test('pages.shares.show', ['gallery' => $gallery, 'slug' => $gallery->slug]);

    $response->assertStatus(200);
    expect($component->allPhotos->contains($photoA))->toBeTrue();
    expect($component->allPhotos->contains($photoB))->toBeFalse();
    expect($component->allPhotos->contains($photoC))->toBeTrue();
});

test('unshared gallery can not be viewed', function () {
    $gallery = Gallery::factory()->unshared()->for($this->team)->create(['ulid' => '0123ABC']);

    $response = get('/shares/0123ABC/'.$gallery->slug);

    $response->assertStatus(404);
});

test('unauthenticated visitors to a password-protected gallery are redirected to the unlock page', function () {
    $gallery = Gallery::factory()->shared()->protected()->for($this->team)->create(['ulid' => '0123ABC']);

    $response = get('/shares/0123ABC/'.$gallery->slug);

    $response->assertRedirect('/shares/0123ABC/unlock');
});

test('visitors with unlocked gallery can view the password-protected gallery', function () {
    $gallery = Gallery::factory()->shared()->protected()->create(['ulid' => '0123ABC']);
    session()->put('unlocked_gallery_ulid', '0123ABC');

    $response = get('/shares/0123ABC/'.$gallery->slug);

    $response->assertStatus(200);
});

test('visitors can view shared gallery favorites', function () {
    $gallery = Gallery::factory()->shared()->create();
    $favorite = Photo::factory()->for($gallery)->favorited()->create();

    $component = Livewire::test('pages.shares.show', ['gallery' => $gallery, 'slug' => $gallery->slug]);

    expect($component->favorites->contains($favorite))->toBeTrue();
});

test('shared gallery displays description when present', function () {
    $gallery = Gallery::factory()->shared()->for($this->team)->create([
        'share_description' => 'This is a beautiful wedding gallery showcasing our special day',
    ]);

    $response = get('/shares/'.$gallery->ulid.'/'.$gallery->slug);

    $response->assertStatus(200)
        ->assertSee('This is a beautiful wedding gallery showcasing our special day');
});

test('shared gallery does not display description when empty', function () {
    $gallery = Gallery::factory()->shared()->for($this->team)->create([
        'share_description' => null,
    ]);

    $response = get('/shares/'.$gallery->ulid.'/'.$gallery->slug);

    $response->assertStatus(200)
        ->assertDontSee('<x-subheading');
});

test('visitors can favorite a photo', function () {
    $photo = Photo::factory()->for(Gallery::factory()->selectable()->for($this->team))->unfavorited()->create();
    expect($photo->isFavorited())->toBeFalse();

    $component = Livewire::test('shared-photo-item', ['photo' => $photo])
        ->call('favorite');

    expect($photo->fresh()->isFavorited())->toBeTrue();
});

test('visitors cannot favorite a photo when gallery is not selectable', function () {
    $photo = Photo::factory()->for(Gallery::factory()->unselectable()->for($this->team))->unfavorited()->create();
    expect($photo->isFavorited())->toBeFalse();

    $component = Livewire::test('shared-photo-item', ['photo' => $photo])
        ->call('favorite');

    $component->assertStatus(403);
});

test('team owner is notified when selection limit is reached', function () {
    Notification::fake();

    $gallery = Gallery::factory()->shared()->selectable()->for($this->team)->create(['share_selection_limit' => 2]);
    $photo1 = Photo::factory()->for($gallery)->unfavorited()->create();
    $photo2 = Photo::factory()->for($gallery)->unfavorited()->create();
    $teamOwner = $gallery->team->owner;

    Livewire::test('shared-photo-item', ['photo' => $photo1])->call('favorite');

    expect($photo1->fresh()->isFavorited())->toBeTrue();
    Notification::assertNotSentTo($teamOwner, SelectionLimitReached::class);

    Livewire::test('shared-photo-item', ['photo' => $photo2])->call('favorite');

    expect($photo2->fresh()->isFavorited())->toBeTrue();
    Notification::assertSentTo($teamOwner, SelectionLimitReached::class, function ($notification) use ($gallery) {
        return $notification->gallery->is($gallery);
    });

    Notification::assertSentTo($teamOwner, SelectionLimitReached::class, function ($notification) use ($teamOwner) {
        $mailData = $notification->toMail($teamOwner);

        return str_contains($mailData->subject, 'Selection Limit Reached') &&
               str_contains($mailData->render(), 'customer may have changed pictures');
    });
});

test('notification is sent only once per gallery when selection limit is reached', function () {
    Notification::fake();

    $gallery = Gallery::factory()->shared()->selectable()->for($this->team)->create(['share_selection_limit' => 2]);
    $photo1 = Photo::factory()->for($gallery)->unfavorited()->create();
    $photo2 = Photo::factory()->for($gallery)->unfavorited()->create();
    $photo3 = Photo::factory()->for($gallery)->unfavorited()->create();
    $teamOwner = $gallery->team->owner;

    // Favorite first two photos, reaching limit
    Livewire::test('shared-photo-item', ['photo' => $photo1])->call('favorite');
    Livewire::test('shared-photo-item', ['photo' => $photo2])->call('favorite');

    // Notification sent
    Notification::assertSentTo($teamOwner, SelectionLimitReached::class, 1);

    // Unfavorite one photo
    Livewire::test('shared-photo-item', ['photo' => $photo1])->call('favorite'); // toggle off
    expect($photo1->fresh()->isFavorited())->toBeFalse();

    // Favorite another photo, reaching limit again
    Livewire::test('shared-photo-item', ['photo' => $photo3])->call('favorite');
    expect($photo3->fresh()->isFavorited())->toBeTrue();

    // No additional notification sent
    Notification::assertSentTo($teamOwner, SelectionLimitReached::class, 1);
});
