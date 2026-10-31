<?php

use App\Models\FtpAccount;
use App\Models\Gallery;
use App\Models\Photo;
use App\Models\PhotoComment;
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

test('shared gallery tabs switch without javascript errors', function () {
    $gallery = Gallery::factory()->shared()->selectable()->for($this->team)->create([
        'are_comments_enabled' => true,
    ]);
    $photo = Photo::factory()->for($gallery)->favorited()->create(['status' => 'processed']);
    PhotoComment::factory()->for($photo)->create();

    $page = visit('/shares/'.$gallery->ulid.'/'.$gallery->slug);

    $page->assertNoJavaScriptErrors();

    $page->click('button[aria-label="Comments (1)"]');
    $page->wait(0.3);

    expect($page->script("document.querySelector('[role=tablist] [data-current=true]').textContent.trim()"))
        ->toContain('Comments');

    $page->click('button[aria-label="Favorites (1)"]');
    $page->wait(0.3);

    expect($page->script("document.querySelector('[role=tablist] [data-current=true]').textContent.trim()"))
        ->toContain('Favorites');

    $page->assertNoJavaScriptErrors();
});

test('shared gallery opens the requested tab from the url', function () {
    $gallery = Gallery::factory()->shared()->selectable()->for($this->team)->create([
        'are_comments_enabled' => true,
    ]);
    $photo = Photo::factory()->for($gallery)->favorited()->create(['status' => 'processed']);
    PhotoComment::factory()->for($photo)->create();

    $page = visit('/shares/'.$gallery->ulid.'/'.$gallery->slug.'?activeTab=commented#photo-'.$photo->id);

    $page->assertNoJavaScriptErrors();

    expect($page->script("document.querySelector('[role=tablist] [data-current=true]').textContent.trim()"))
        ->toContain('Comments');

    expect($page->script("Array.from(document.querySelectorAll('[role=tablist] ~ div')).map(e => getComputedStyle(e).display).join('|')"))
        ->toBe('none|block|none');
});

test('shared gallery applies the team brand color', function () {
    $this->team->update(['brand_color' => 'violet']);
    $gallery = Gallery::factory()->shared()->for($this->team)->create(['ulid' => '0123ABC']);

    get('/shares/0123ABC/'.$gallery->slug)
        ->assertOk()
        ->assertSee('--color-violet-500', false)
        ->assertSee('bg-(--color-accent)', false);
});

test('bare share URL redirects to the slug URL', function () {
    $gallery = Gallery::factory()->shared()->for($this->team)->create(['ulid' => '0123ABC']);

    $response = get('/shares/0123ABC');

    $response->assertRedirect('/shares/0123ABC/'.$gallery->slug);
});

test('visitors can view a shared gallery', function () {
    $gallery = Gallery::factory()->shared()->for($this->team)->create(['ulid' => '0123ABC']);
    $photoA = Photo::factory()->for($gallery)->create();
    $photoB = Photo::factory()->for(Gallery::factory()->for($this->team))->create();
    $photoC = Photo::factory()->for($gallery)->create();

    $response = get('/shares/0123ABC/'.$gallery->slug);
    $component = Livewire::test('pages::shares.show', ['gallery' => $gallery, 'slug' => $gallery->slug]);

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
    session()->put('unlocked_gallery_ulids', ['0123ABC']);

    $response = get('/shares/0123ABC/'.$gallery->slug);

    $response->assertStatus(200);
});

test('visitors can view shared gallery favorites', function () {
    $gallery = Gallery::factory()->shared()->create();
    $favorite = Photo::factory()->for($gallery)->favorited()->create();

    $component = Livewire::test('pages::shares.show', ['gallery' => $gallery, 'slug' => $gallery->slug]);

    expect($component->favorites->contains($favorite))->toBeTrue();
});

test('shared gallery shows the download tab with the photo count and total size', function () {
    $gallery = Gallery::factory()->shared()->downloadable()->for($this->team)->create();
    Photo::factory()->for($gallery)->create(['size' => 1024 * 1024]);
    Photo::factory()->for($gallery)->create(['size' => 1024 * 1024]);

    $component = Livewire::test('pages::shares.show', ['gallery' => $gallery, 'slug' => $gallery->slug])
        ->set('activeTab', 'download');

    $component->assertSee('Download')
        ->assertSeeHtml('gallery-download-panel');
});

test('shared gallery hides the download tab when downloads are disabled', function () {
    $gallery = Gallery::factory()->shared()->undownloadable()->for($this->team)->create();
    Photo::factory()->for($gallery)->create();

    $component = Livewire::test('pages::shares.show', ['gallery' => $gallery, 'slug' => $gallery->slug]);

    $component->assertDontSee('Download gallery')
        ->assertDontSee('This ZIP includes processed images');
});

test('shared gallery shows the favorite count and selection limit', function () {
    $gallery = Gallery::factory()->shared()->selectable(10)->for($this->team)->create();
    Photo::factory()->count(3)->for($gallery)->favorited()->create();

    $component = Livewire::test('pages::shares.show', ['gallery' => $gallery, 'slug' => $gallery->slug]);

    $component->assertSee('Favorites (3/10)');
});

test('shared gallery shows the favorite count without a selection limit', function () {
    $gallery = Gallery::factory()->shared()->selectable()->for($this->team)->create();
    Photo::factory()->count(2)->for($gallery)->favorited()->create();

    $component = Livewire::test('pages::shares.show', ['gallery' => $gallery, 'slug' => $gallery->slug]);

    $component->assertSee('Favorites (2)');
});

test('shared gallery shows the total comment count in the comments tab', function () {
    $gallery = Gallery::factory()->shared()->for($this->team)->create(['are_comments_enabled' => true]);
    $commentedPhoto = Photo::factory()->for($gallery)->create();
    $otherCommentedPhoto = Photo::factory()->for($gallery)->create();
    PhotoComment::factory()->count(2)->for($commentedPhoto)->create();
    PhotoComment::factory()->for($otherCommentedPhoto)->create();

    $component = Livewire::test('pages::shares.show', ['gallery' => $gallery, 'slug' => $gallery->slug]);

    $component->assertSee('Comments (3)');
});

test('shared gallery shows the comments label without a count when there are none', function () {
    $gallery = Gallery::factory()->shared()->for($this->team)->create(['are_comments_enabled' => true]);
    Photo::factory()->for($gallery)->create();

    $component = Livewire::test('pages::shares.show', ['gallery' => $gallery, 'slug' => $gallery->slug]);

    $component->assertSee('Comments')
        ->assertDontSee('Comments (');
});

test('selection limit modal shows the number of allowed photos', function () {
    $gallery = Gallery::factory()->shared()->selectable(2)->for($this->team)->create();
    Photo::factory()->for($gallery)->create();

    $component = Livewire::test('pages::shares.show', ['gallery' => $gallery, 'slug' => $gallery->slug]);

    $component->assertSee('You have reached the limit of 2 photos.');
});

test('shared gallery labels and download warning are translated to Spanish', function () {
    app()->setLocale('es');

    $gallery = Gallery::factory()->shared()->downloadable()->selectable(10)->for($this->team)->create([
        'are_comments_enabled' => true,
    ]);
    $favoritePhoto = Photo::factory()->for($gallery)->favorited()->create();
    Photo::factory()->for($gallery)->create();
    PhotoComment::factory()->for($favoritePhoto)->create();

    $component = Livewire::test('pages::shares.show', ['gallery' => $gallery, 'slug' => $gallery->slug])
        ->set('activeTab', 'download');

    $component->assertSee('Favoritos (1/10)')
        ->assertSee('Comentarios (1)')
        ->assertSee('Descargar')
        ->assertSeeHtml('gallery-download-panel');
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

test('shared gallery displays cover image when cover photo is set', function () {
    $gallery = Gallery::factory()->shared()->for($this->team)->create();
    $photo = Photo::factory()->for($gallery)->create();

    $gallery->update(['cover_photo_id' => $photo->id]);

    $component = Livewire::test('pages::shares.show', ['gallery' => $gallery, 'slug' => $gallery->slug]);

    expect($gallery->fresh()->coverPhoto)->not->toBeNull();
    expect($gallery->fresh()->cover_photo_id)->toBe($photo->id);
    expect($component->coverImage)->not->toBeNull();
});

test('shared gallery does not display cover image when cover photo is not set', function () {
    $gallery = Gallery::factory()->shared()->for($this->team)->create();
    $photo = Photo::factory()->for($gallery)->create();

    $component = Livewire::test('pages::shares.show', ['gallery' => $gallery, 'slug' => $gallery->slug]);

    expect($gallery->fresh()->coverPhoto)->toBeNull();
    expect($gallery->fresh()->cover_photo_id)->toBeNull();
});

test('shared gallery does not overflow on mobile', function () {
    $gallery = Gallery::factory()->shared()->for($this->team)->create();
    Photo::factory()->count(2)->for($gallery)->create(['status' => 'processed']);

    $page = visit('/shares/'.$gallery->ulid.'/'.$gallery->slug);

    foreach ([320, 375] as $width) {
        $page->resize($width, 900);
        $page->wait(0.1);

        expect($page->script('document.documentElement.scrollWidth <= document.documentElement.clientWidth'))
            ->toBeTrue();
    }
});

test('shared gallery only watches for new photos in remote mode', function () {
    $gallery = Gallery::factory()->shared()->for($this->team)->create(['ulid' => 'REMOTE1']);

    $component = Livewire::test('pages::shares.show', ['gallery' => $gallery, 'slug' => $gallery->slug]);

    expect($component->html())->not->toContain('wire:poll.visible.5s');

    FtpAccount::factory()->for($this->team)->create([
        'active' => true,
        'active_gallery_id' => $gallery->id,
    ]);

    $component = Livewire::test('pages::shares.show', ['gallery' => $gallery, 'slug' => $gallery->slug]);

    expect($component->html())->toContain('wire:poll.visible.5s');

    $photo = Photo::factory()->for($gallery)->create();

    $component->call('refreshPhotos');

    expect($component->allPhotos->contains($photo))->toBeTrue();
    expect($component->html())->toContain('data-photo-card');

    $component->call('stopRemoteMode');

    expect($component->remoteMode)->toBeFalse();
    expect($component->html())->not->toContain('wire:poll.visible.5s');
});

test('shared gallery shows a photo that arrives after the page loads', function () {
    config(['picstome.live_gallery_poll_seconds' => 1]);

    $gallery = Gallery::factory()->shared()->for($this->team)->create(['ulid' => 'LIVE123']);

    FtpAccount::factory()->for($this->team)->create([
        'active' => true,
        'active_gallery_id' => $gallery->id,
    ]);

    $page = visit('/shares/LIVE123/'.$gallery->slug);

    Photo::factory()->for($gallery)->create();

    $page->wait(3);

    expect($page->script("document.querySelectorAll('[data-photo-card]').length"))->toBe(1);
});


test('shared gallery renders a bounded page and loads more photos on demand', function () {
    $gallery = Gallery::factory()->shared()->for($this->team)->create();
    Photo::factory()->count(65)->for($gallery)->create(['status' => 'processed']);

    $component = Livewire::test('pages::shares.show', ['gallery' => $gallery, 'slug' => $gallery->slug]);

    expect($component->allPhotos)->toHaveCount(60)
        ->and(substr_count($component->html(), 'data-photo-card'))->toBe(60)
        ->and($component->html())->toContain('x-on:scroll.window.throttle.150ms')
        ->and($component->html())->not->toContain('wire:name="shared-photo-item"');

    $component->call('loadMore', 'all');

    expect($component->allPhotos)->toHaveCount(65);
});

test('shared gallery hides photos that are still processing', function () {
    $gallery = Gallery::factory()->shared()->for($this->team)->create();
    Photo::factory()->for($gallery)->create(['status' => Photo::STATUS_PROCESSED]);
    Photo::factory()->for($gallery)->create(['status' => Photo::STATUS_PROCESSING]);

    $component = Livewire::test('pages::shares.show', ['gallery' => $gallery, 'slug' => $gallery->slug]);

    expect($component->allPhotos)->toHaveCount(1);
});
