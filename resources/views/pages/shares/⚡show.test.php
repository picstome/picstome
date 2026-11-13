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

test('shared gallery never exposes photographer ratings', function () {
    $gallery = Gallery::factory()->shared()->selectable()->for($this->team)->create();
    Photo::factory()->for($gallery)->rated(4)->favorited()->create();

    $component = Livewire::test('pages::shares.show', ['gallery' => $gallery, 'slug' => $gallery->slug]);

    $component->assertDontSeeHtml('data-photo-rating')
        ->assertDontSeeHtml('data-gallery-filter-bar');
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
})->group('browser');

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

    expect($page->script("Array.from(document.querySelectorAll('[data-gallery-panel]')).map(e => getComputedStyle(e).display).join('|')"))
        ->toBe('none|block|none');
})->group('browser');

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

test('stale share URL redirects to the canonical URL after the gallery is renamed', function () {
    Gallery::factory()->shared()->for($this->team)->create([
        'ulid' => '0123ABC',
        'name' => 'Renamed Gallery',
    ]);

    get('/shares/0123ABC/old-gallery-name')
        ->assertRedirect('/shares/0123ABC/renamed-gallery');
});

test('stale share URL keeps the query string when redirecting', function () {
    Gallery::factory()->shared()->for($this->team)->create([
        'ulid' => '0123ABC',
        'name' => 'Renamed Gallery',
    ]);

    get('/shares/0123ABC/old-gallery-name?activeTab=favorited&photo=42')
        ->assertRedirect('/shares/0123ABC/renamed-gallery?activeTab=favorited&photo=42');
});

test('stale share URL for an unshared gallery still returns 404', function () {
    Gallery::factory()->unshared()->for($this->team)->create([
        'ulid' => '0123ABC',
        'name' => 'Renamed Gallery',
    ]);

    get('/shares/0123ABC/old-gallery-name')->assertNotFound();
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
        ->assertSeeHtml('gallery-download-panel')
        ->assertDontSee('Sync this gallery')
        ->assertDontSeeHtml('picstome://sync');
});

test('shared gallery hides the download tab when downloads are disabled', function () {
    $gallery = Gallery::factory()->shared()->undownloadable()->for($this->team)->create();
    Photo::factory()->for($gallery)->create();

    $component = Livewire::test('pages::shares.show', ['gallery' => $gallery, 'slug' => $gallery->slug]);

    $component->assertDontSee('Download gallery')
        ->assertDontSee('This ZIP includes processed images');
});

test('the public download action uses the studio accent colour', function () {
    $this->team->update(['brand_color' => 'violet']);

    $gallery = Gallery::factory()->shared()->downloadable()->for($this->team)->create(['ulid' => '0123ABC']);
    Photo::factory()->for($gallery)->create(['status' => 'processed']);

    get('/shares/0123ABC/'.$gallery->slug)
        ->assertOk()
        // The guest layout maps the brand colour onto `--color-accent`, and the
        // download button fills with it so the client's main action matches the
        // photographer's branding.
        ->assertSee('--color-accent', false)
        ->assertSeeHtml('bg-(--color-accent)');
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

    expect($photo->fresh()->isFavorited())->toBeTrue()
        ->and($photo->fresh()->rating)->toBe(5);
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

    expect($page->script(<<<'JS'
        (() => {
            const wall = document.querySelector('[data-photo-masonry]');

            if (! wall) {
                return false;
            }

            const rect = wall.getBoundingClientRect();

            return Math.abs(rect.width - document.documentElement.clientWidth) < 2
                && getComputedStyle(wall).columnCount === '1';
        })()
    JS))->toBeTrue();
})->group('browser');

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
})->group('browser');

test('shared gallery renders a bounded page and loads more photos on demand', function () {
    $gallery = Gallery::factory()->shared()->for($this->team)->create();
    Photo::factory()->count(65)->for($gallery)->create(['status' => 'processed']);

    $component = Livewire::test('pages::shares.show', ['gallery' => $gallery, 'slug' => $gallery->slug]);

    expect($component->allPhotos)->toHaveCount(60)
        ->and(substr_count($component->html(), 'data-photo-id='))->toBe(60)
        ->and($component->html())->toContain('x-on:scroll.window.throttle.150ms')
        ->and($component->html())->not->toContain('wire:name="shared-photo-item"');

    $component->call('loadMore', 'all');

    expect($component->allPhotos)->toHaveCount(65);
});

test('shared gallery appends more photos below the existing masonry columns', function () {
    $gallery = Gallery::factory()->shared()->for($this->team)->create();
    Photo::factory()->count(65)->for($gallery)->create(['status' => 'processed']);

    $page = visit('/shares/'.$gallery->ulid.'/'.$gallery->slug);

    $ready = false;

    for ($attempt = 0; $attempt < 30; $attempt++) {
        $ready = $page->script(<<<'JS'
            (() => {
                const wall = document.querySelector('[data-photo-masonry]');

                return wall?.dataset.masonryReady === 'true'
                    && wall.querySelectorAll(':scope > [data-photo-card]').length === 60;
            })()
        JS);

        if ($ready) {
            break;
        }

        $page->wait(0.2);
    }

    expect($ready)->toBeTrue();

    // Load the placeholder/fallback images before recording column heights.
    $page->script("document.querySelectorAll('[data-photo-card] img').forEach((img) => { img.loading = 'eager'; })");
    $page->wait(1);
    $page->script('window.Alpine.$data(document.querySelector(\'[data-photo-masonry]\')).layout()');
    $page->wait(0.2);

    $before = $page->script(<<<'JS'
        (() => {
            const wall = document.querySelector('[data-photo-masonry]');
            const state = window.Alpine.$data(wall);
            const wallRect = wall.getBoundingClientRect();

            return Array.from(wall.querySelectorAll(':scope > [data-photo-card]')).map((tile) => {
                const rect = tile.getBoundingClientRect();

                return {
                    id: tile.dataset.photoId,
                    column: state.assignments.get(tile.dataset.photoId),
                    top: Math.round(rect.top - wallRect.top),
                    bottom: Math.round(rect.bottom - wallRect.top),
                };
            });
        })()
    JS);

    $page->script("window.Livewire.first().call('loadMore', 'all')");
    waitForPhotoCardCount($page, 65);

    $laidOut = false;

    for ($attempt = 0; $attempt < 30; $attempt++) {
        $laidOut = $page->script(<<<'JS'
            (() => {
                const wall = document.querySelector('[data-photo-masonry]');
                const state = window.Alpine.$data(wall);
                const tiles = Array.from(wall.querySelectorAll(':scope > [data-photo-card]'));

                return tiles.length === 65
                    && (state?.assignments?.size ?? 0) >= 65
                    && tiles.every((tile) => getComputedStyle(tile).position === 'absolute' && tile.style.top !== '');
            })()
        JS);

        if ($laidOut) {
            break;
        }

        $page->wait(0.2);
    }

    expect($laidOut)->toBeTrue()
        ->and($page->script("document.querySelectorAll('[data-photo-card]').length"))->toBe(65);

    $after = $page->script(<<<'JS'
        (() => {
            const wall = document.querySelector('[data-photo-masonry]');
            const state = window.Alpine.$data(wall);
            const wallRect = wall.getBoundingClientRect();

            return Array.from(wall.querySelectorAll(':scope > [data-photo-card]')).map((tile) => {
                const rect = tile.getBoundingClientRect();

                return {
                    id: tile.dataset.photoId,
                    column: state.assignments.get(tile.dataset.photoId),
                    top: Math.round(rect.top - wallRect.top),
                    bottom: Math.round(rect.bottom - wallRect.top),
                };
            });
        })()
    JS);

    $beforeById = collect($before)->keyBy('id');
    $newTiles = collect($after)->reject(fn ($tile) => $beforeById->has($tile['id']));
    $movedTiles = collect($after)->filter(fn ($tile) => $beforeById->has($tile['id'])
        && $beforeById[$tile['id']]['column'] !== $tile['column']);

    $overlaps = collect($after)
        ->groupBy('column')
        ->flatMap(function ($tiles) {
            $sorted = $tiles->sortBy('top')->values();
            $overlaps = [];

            for ($index = 1; $index < $sorted->count(); $index++) {
                if ($sorted[$index]['top'] < $sorted[$index - 1]['bottom'] - 1) {
                    $overlaps[] = [$sorted[$index - 1]['id'], $sorted[$index]['id']];
                }
            }

            return $overlaps;
        });

    expect($newTiles)->toHaveCount(5)
        ->and($movedTiles)->toBeEmpty()
        ->and($overlaps)->toBeEmpty();
})->group('browser');

test('shared gallery reveals the photo referenced by the viewer back link', function () {
    $gallery = Gallery::factory()->shared()->for($this->team)->create();
    Photo::factory()->count(65)->for($gallery)->sequence(fn ($sequence) => [
        'name' => 'photo-'.($sequence->index + 1).'.jpg',
    ])->create(['status' => 'processed']);

    $target = $gallery->photos()->where('name', 'photo-65.jpg')->firstOrFail();

    $component = Livewire::withQueryParams(['photo' => $target->id])
        ->test('pages::shares.show', ['gallery' => $gallery, 'slug' => $gallery->slug]);

    expect($component->allPhotos)->toHaveCount(65)
        ->and($component->allPhotos->contains($target))->toBeTrue()
        ->and($component->get('allPhotoLimit'))->toBe(120);
});

test('going back from a shared photo beyond the first page scrolls to it', function () {
    $gallery = Gallery::factory()->shared()->for($this->team)->create();
    Photo::factory()->count(65)->for($gallery)->sequence(fn ($sequence) => [
        'name' => 'photo-'.($sequence->index + 1).'.jpg',
    ])->create(['status' => 'processed']);

    $target = $gallery->photos()->where('name', 'photo-65.jpg')->firstOrFail();

    $page = visit('/shares/'.$gallery->ulid.'/photos/'.$target->id);
    $page->wait(0.5);

    $page->click('[data-photo-back]');
    waitForPhotoCardCount($page, 65);
    $page->wait(1.5);

    $page->assertNoJavaScriptErrors();

    expect($page->script('window.location.hash'))->toBe('#photo-'.$target->id)
        ->and($page->script("document.querySelectorAll('[data-photo-card]').length"))->toBe(65)
        ->and($page->script("(() => { const el = document.querySelector('#photo-{$target->id}'); if (! el) return false; const rect = el.getBoundingClientRect(); return rect.top >= 0 && rect.top < window.innerHeight; })()"))->toBeTrue()
        ->and($page->script('window.scrollY'))->toBeGreaterThan(0);
})->group('browser');

test('shared gallery hides photos that are still processing', function () {
    $gallery = Gallery::factory()->shared()->for($this->team)->create();
    Photo::factory()->for($gallery)->create(['status' => Photo::STATUS_PROCESSED]);
    Photo::factory()->for($gallery)->create(['status' => Photo::STATUS_PROCESSING]);

    $component = Livewire::test('pages::shares.show', ['gallery' => $gallery, 'slug' => $gallery->slug]);

    expect($component->allPhotos)->toHaveCount(1);
});
