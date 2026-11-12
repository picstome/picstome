<?php

use App\Events\PhotoAdded;
use App\Jobs\DeleteFromDisk;
use App\Jobs\ProcessPhoto;
use App\Models\FtpAccount;
use App\Models\Gallery;
use App\Models\Photo;
use App\Models\PhotoComment;
use App\Models\Photoshoot;
use App\Models\PhotoVersion;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Cashier\Subscription;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\withoutDefer;

uses(RefreshDatabase::class);

beforeEach(function () {
    withoutDefer();

    $this->user = User::factory()->withPersonalTeam()->create();
    $this->team = $this->user->currentTeam;
});

describe('Gallery Viewing', function () {
    it('allows users to view their team gallery', function () {
        $gallery = Gallery::factory()->for($this->team)->create();
        $photoA = Photo::factory()->for($gallery)->create();
        $photoB = Photo::factory()->for(Gallery::factory())->create();
        $photoC = Photo::factory()->for($gallery)->create();

        $response = actingAs($this->user)->get('/galleries/1');
        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery]);

        $response->assertStatus(200);

        expect($component->allPhotos->contains($photoA))->toBeTrue();
        expect($component->allPhotos->contains($photoB))->toBeFalse();
        expect($component->allPhotos->contains($photoC))->toBeTrue();
    });

    it('shows the gallery tab labels with favorite and comment counts', function () {
        $gallery = Gallery::factory()->for($this->team)->create([
            'is_share_selectable' => true,
            'share_selection_limit' => 10,
            'are_comments_enabled' => true,
        ]);
        Photo::factory()->count(3)->for($gallery)->favorited()->create();
        $commentedPhoto = Photo::factory()->for($gallery)->create();
        PhotoComment::factory()->count(3)->for($commentedPhoto)->create();

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery]);

        $component->assertSee('Favorites (3/10)')
            ->assertSee('Comments (3)');
    });

    it('shows the public gallery explanation tooltip on hover', function () {
        $user = User::factory()->withPersonalTeam()->create(['language' => 'en']);
        $gallery = Gallery::factory()->for($user->currentTeam)->public()->create();

        $this->actingAs($user);

        $page = visit("/galleries/{$gallery->id}");
        $page->hover('[x-tooltip]');
        $page->wait(0.3);

        expect($page->script("document.querySelector('[data-tsui-tooltip]')?.innerText.trim()"))
            ->toContain('This gallery is public and visible in your portfolio section.');
    });

    it('shows the download tab on the owner gallery page', function () {
        $gallery = Gallery::factory()->for($this->team)->create();
        Photo::factory()->for($gallery)->create();

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->set('activeTab', 'download');

        $component->assertSee('Download')
            ->assertSeeHtml('gallery-download-panel');
    });

    it('labels the all photos tab as photos and keeps sync and download icon only', function () {
        config(['picstome.admin_emails' => [$this->user->email]]);

        $gallery = Gallery::factory()->for($this->team)->create();
        Photo::factory()->for($gallery)->create();

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery]);

        // The shorter label frees horizontal room in the tab bar.
        $component->assertSeeHtml('aria-label="Photos"')
            ->assertDontSeeHtml('aria-label="All photos"')
            // Sync and Download keep their accessible name but drop the visible
            // text at every width, so only the icon is rendered.
            ->assertSeeHtml('aria-label="Sync"')
            ->assertSeeHtml('aria-label="Download"')
            ->assertDontSeeHtml('<span class="max-sm:sr-only">Sync</span>')
            ->assertDontSeeHtml('<span class="max-sm:sr-only">Download</span>');
    });

    it('offers the export favorites shortcut next to the favorites tab', function () {
        $gallery = Gallery::factory()->for($this->team)->create();
        Photo::factory()->for($gallery)->favorited()->create();

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery]);

        $component->assertSeeHtml('data-export-favorites')
            ->assertSeeHtml('aria-label="Export favorites"')
            ->assertSeeHtml('x-tooltip="Export favorites"');
    });

    it('hides the export favorites shortcut when nothing is favorited', function () {
        $gallery = Gallery::factory()->for($this->team)->create();
        Photo::factory()->for($gallery)->create();

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery]);

        $component->assertDontSeeHtml('data-export-favorites');
    });

    it('shows the export favorites action only in the favorites view', function () {
        $gallery = Gallery::factory()->for($this->team)->create();
        Photo::factory()->for($gallery)->favorited()->create();

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery]);

        // The Photos tab stays clean: the always-available entry point is the
        // icon next to the Favorites tab, not a coral button over the wall.
        $component->assertDontSeeHtml('data-export-favorites-content');

        $component->call('showFavorites');

        $component->assertSeeHtml('data-export-favorites-content')
            ->assertSee('Export favorites');
    });

    it('hides the export favorites action inside the grid when nothing is favorited', function () {
        $gallery = Gallery::factory()->for($this->team)->create();
        Photo::factory()->for($gallery)->create();

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->call('showFavorites');

        $component->assertDontSeeHtml('data-export-favorites-content');
    });

    it('shows the sync tab and panel with the desktop app deep link', function () {
        config(['picstome.admin_emails' => [$this->user->email]]);

        $gallery = Gallery::factory()->for($this->team)->create(['name' => 'Mi Boda']);
        Photo::factory()->for($gallery)->create();

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->set('activeTab', 'sync');

        $component->assertSee('Sync with Picstome Sync')
            ->assertSee('Sync this gallery')
            ->assertSee('Download Picstome Sync')
            ->assertSee('Rate your favorite images on your computer.')
            ->assertSeeHtml('href="picstome://sync?gallery='.$gallery->ulid.'&name=Mi%20Boda"')
            ->assertSeeHtml('https://github.com/picstome/picstome-sync/releases/latest');
    });

    it('offers the sync action on an empty gallery', function () {
        config(['picstome.admin_emails' => [$this->user->email]]);

        $gallery = Gallery::factory()->for($this->team)->create(['name' => 'Mi Boda']);

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery]);

        $component->assertSee('Sync this gallery')
            ->assertSeeHtml('href="picstome://sync?gallery='.$gallery->ulid.'&name=Mi%20Boda"');
    });

    it('links the favorite export to Picstome Sync', function () {
        config(['picstome.admin_emails' => [$this->user->email]]);

        $gallery = Gallery::factory()->for($this->team)->create();
        Photo::factory()->for($gallery)->favorited()->create();

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery]);

        $component->assertSee('Rate your favorited images with the Picstome Sync desktop app.')
            ->assertSee('Download Picstome Sync')
            ->assertSeeHtml('https://github.com/picstome/picstome-sync/releases/latest');
    });

    it('hides the sync feature from non-admin users', function () {
        config(['picstome.admin_emails' => ['someone-else@example.com']]);

        $gallery = Gallery::factory()->for($this->team)->create();
        Photo::factory()->for($gallery)->favorited()->create();

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery]);

        $component->assertDontSee('Sync with Picstome Sync')
            ->assertDontSee('Sync this gallery')
            ->assertDontSeeHtml('picstome://sync')
            ->assertSee('Rate your favorited images with the Picstome Rater desktop app.')
            ->assertSee('Download Picstome Rater')
            ->assertSeeHtml('https://github.com/picstome/picstome-rater/releases/latest');
    });

    it('shows the sync feature to everyone when admin-only is disabled', function () {
        config([
            'picstome.admin_emails' => ['someone-else@example.com'],
            'picstome.sync_feature_admin_only' => false,
        ]);

        $gallery = Gallery::factory()->for($this->team)->create(['name' => 'Mi Boda']);
        Photo::factory()->for($gallery)->create();

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->set('activeTab', 'sync');

        $component->assertSee('Sync with Picstome Sync')
            ->assertSee('Sync this gallery')
            ->assertSeeHtml('href="picstome://sync?gallery='.$gallery->ulid.'&name=Mi%20Boda"');
    });

    it('hides the sync feature when the feature flag is disabled', function () {
        config([
            'picstome.admin_emails' => [$this->user->email],
            'picstome.sync_feature_enabled' => false,
        ]);

        $gallery = Gallery::factory()->for($this->team)->create();
        Photo::factory()->for($gallery)->create();

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery]);

        $component->assertDontSee('Sync with Picstome Sync')
            ->assertDontSeeHtml('picstome://sync');
    });

    it('selects a cover photo from the photo tile', function () {
        Storage::fake('s3');

        $gallery = Gallery::factory()->for($this->team)->create();
        $photo = Photo::factory()->for($gallery)->create(['status' => 'processed']);

        $this->actingAs($this->user);

        $page = visit("/galleries/{$gallery->id}");
        $page->hover('[data-photo-card]');
        $page->wait(0.2);
        $page->click('[data-photo-cover]');
        $page->wait(0.3);

        expect($gallery->fresh()->cover_photo_id)->toBe($photo->id);
    });

    it('keeps only cover, delete and favorite actions on the photo tile', function () {
        Storage::fake('s3');

        $gallery = Gallery::factory()->for($this->team)->create();
        Photo::factory()->for($gallery)->create(['status' => 'processed']);

        $this->actingAs($this->user);

        $page = visit("/galleries/{$gallery->id}");
        $page->hover('[data-photo-card]');
        $page->wait(0.2);

        expect($page->script(<<<'JS'
            (() => {
                const card = document.querySelector('[data-photo-card]')
                return {
                    cover: card.querySelectorAll('[data-photo-cover]').length,
                    delete: card.querySelectorAll('[data-photo-delete]').length,
                    favorite: card.querySelectorAll('[aria-label="Favorite"]').length,
                    download: card.querySelectorAll('[aria-label="Download"]').length,
                    more: card.querySelectorAll('[data-photo-actions]').length,
                }
            })()
        JS))->toBe(['cover' => 1, 'delete' => 1, 'favorite' => 1, 'download' => 0, 'more' => 0]);
    });

    it('centres the tile actions dock at the bottom of the thumbnail', function () {
        Storage::fake('s3');

        $gallery = Gallery::factory()->for($this->team)->create();
        Photo::factory()->for($gallery)->create(['status' => 'processed']);

        $this->actingAs($this->user);

        $page = visit("/galleries/{$gallery->id}");
        $page->hover('[data-photo-card]');
        $page->wait(0.2);

        // The same frosted pill as the viewer dock, in the same place: centred
        // at the bottom of the thumbnail, not tucked into a corner.
        expect($page->script(<<<'JS'
            (() => {
                const card = document.querySelector('[data-photo-card]')
                const dock = card.querySelector('.rounded-full')
                const d = dock.getBoundingClientRect()
                const c = card.getBoundingClientRect()

                return {
                    centred: Math.abs((d.left + d.width / 2) - (c.left + c.width / 2)) <= 2,
                    nearBottom: Math.round(c.bottom - d.bottom),
                    radius: dock.className.includes('rounded-full'),
                }
            })()
        JS))->toBe(['centred' => true, 'nearBottom' => 6, 'radius' => true]);
    });

    it('opens the favorite list from the actions menu', function () {
        $gallery = Gallery::factory()->for($this->team)->create();
        Photo::factory()->for($gallery)->favorited()->create();

        $this->actingAs($this->user);

        $page = visit("/galleries/{$gallery->id}");

        $page->click('[data-gallery-actions]');
        $page->wait(0.2);
        $page->click('[data-export-favorites-menu]');
        $page->wait(0.3);

        expect($page->script("getComputedStyle(document.querySelector('#favorite-list')).display"))
            ->not->toBe('none');

        $page->click('Finder/Explorer');
        $page->wait(0.2);

        expect($page->script("document.getElementById('finder-textarea').value"))
            ->not->toBeEmpty();
    });

    it('opens the favorite list from the export icon in the tab bar', function () {
        $gallery = Gallery::factory()->for($this->team)->create();
        Photo::factory()->for($gallery)->favorited()->create();

        $this->actingAs($this->user);

        $page = visit("/galleries/{$gallery->id}");
        $page->click('[data-export-favorites]');
        $page->wait(0.3);

        expect($page->script("getComputedStyle(document.querySelector('#favorite-list')).display"))
            ->not->toBe('none');
    });

    it('opens the favorites tab from the url', function () {
        $gallery = Gallery::factory()->for($this->team)->create();
        Photo::factory()->for($gallery)->favorited()->create();

        $this->actingAs($this->user);

        $page = visit("/galleries/{$gallery->id}?activeTab=favorited");
        $page->wait(0.2);

        expect($page->script("document.querySelector('[role=tablist] [data-current=true]')?.textContent.trim()"))
            ->toContain('Favorites');
    });

    it('applies the favorites filter from the favorites tab', function () {
        $gallery = Gallery::factory()->for($this->team)->create();
        Photo::factory()->for($gallery)->favorited()->create(['name' => 'a.jpg']);
        Photo::factory()->for($gallery)->create(['name' => 'b.jpg']);

        $this->actingAs($this->user);

        $page = visit("/galleries/{$gallery->id}");
        $page->click('[data-favorites-tab]');
        $page->wait(0.4);

        expect($page->script("document.querySelectorAll('[data-photo-card]').length"))->toBe(1)
            ->and($page->script("new URL(location.href).searchParams.get('favorites')"))->toBe('true');
    });

    it('does not overflow horizontally on small mobile screens', function () {
        $user = User::factory()->withPersonalTeam()->create(['language' => 'es']);
        $gallery = Gallery::factory()->for($user->currentTeam)->shared()->selectable(2)->public()->create([
            'name' => 'test recuerda',
            'are_comments_enabled' => true,
        ]);
        Photo::factory()->count(2)->for($gallery)->favorited()->create(['status' => 'processed']);

        $this->actingAs($user);

        $page = visit("/galleries/{$gallery->id}");

        foreach ([320, 375] as $width) {
            $page->resize($width, 900);
            $page->wait(0.1);

            expect($page->script('document.documentElement.scrollWidth <= document.documentElement.clientWidth'))
                ->toBeTrue();
        }
    });

    it('accepts files dropped anywhere while the add media modal is open', function () {
        $gallery = Gallery::factory()->for($this->team)->create();
        Photo::factory()->for($gallery)->create(['status' => 'processed']);

        $this->actingAs($this->user);

        $page = visit("/galleries/{$gallery->id}");

        $page->click('Add media');
        $page->wait(0.3);

        $page->script(<<<'JS'
            (() => {
                const dataTransfer = new DataTransfer();
                dataTransfer.items.add(new File(['x'], 'outside-drop.exe', { type: 'application/octet-stream' }));

                document.body.dispatchEvent(new DragEvent('dragover', {
                    bubbles: true,
                    cancelable: true,
                    dataTransfer,
                }));
            })()
        JS);

        $page->wait(0.2);
        $page->assertAttributeContains('#add-photos-uploader', 'class', 'ring-2');

        $page->script(<<<'JS'
            (() => {
                const dataTransfer = new DataTransfer();
                dataTransfer.items.add(new File(['x'], 'outside-drop.exe', { type: 'application/octet-stream' }));

                document.body.dispatchEvent(new DragEvent('drop', {
                    bubbles: true,
                    cancelable: true,
                    dataTransfer,
                }));
            })()
        JS);

        $page->wait(0.3);
        $page->assertSee('outside-drop.exe');
    });

    it('prevents guests from viewing any galleries', function () {
        Gallery::factory()->for($this->team)->create();

        $response = get('/galleries/1');

        $response->assertRedirect('/login');
    });

    it('prevents users from viewing other teams\' galleries', function () {
        Gallery::factory()->for(Team::factory())->create();

        $response = actingAs($this->user)->get('/galleries/1');

        $response->assertStatus(403);
    });
});

describe('Rating filters and sorting', function () {
    it('filters by an exact rating', function () {
        $gallery = Gallery::factory()->for($this->team)->create();
        Photo::factory()->for($gallery)->rated(1)->create(['name' => 'a.jpg']);
        $three = Photo::factory()->for($gallery)->rated(3)->create(['name' => 'b.jpg']);
        Photo::factory()->for($gallery)->rated(5)->create(['name' => 'c.jpg']);
        Photo::factory()->for($gallery)->create(['name' => 'd.jpg']);

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->call('filterByRating', 3);

        expect($component->get('ratingFilter'))->toBe(3)
            ->and($component->allPhotos->pluck('id')->all())->toBe([$three->id]);

        $component->call('clearRatingFilter');

        expect($component->get('ratingFilter'))->toBeNull()
            ->and($component->allPhotos)->toHaveCount(4);
    });

    it('the favorites tab applies the favorites filter on all photos', function () {
        $gallery = Gallery::factory()->for($this->team)->create();
        $favoriteHigh = Photo::factory()->for($gallery)->rated(4)->favorited()->create(['name' => 'a.jpg']);
        $favoriteLow = Photo::factory()->for($gallery)->rated(2)->favorited()->create(['name' => 'b.jpg']);
        Photo::factory()->for($gallery)->rated(5)->create(['name' => 'c.jpg']);

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->call('showFavorites');

        expect($component->get('favoritesOnly'))->toBeTrue()
            ->and($component->get('activeTab'))->toBe('all')
            ->and($component->allPhotos->pluck('id')->all())->toBe([$favoriteHigh->id, $favoriteLow->id]);

        $component->call('filterByRating', 4);

        expect($component->allPhotos->pluck('id')->all())->toBe([$favoriteHigh->id]);

        $component->call('showAllPhotos');

        expect($component->get('favoritesOnly'))->toBeFalse()
            ->and($component->get('ratingFilter'))->toBeNull()
            ->and($component->allPhotos)->toHaveCount(3);
    });

    it('leaving all photos drops the favorites filter', function () {
        $gallery = Gallery::factory()->for($this->team)->create();
        Photo::factory()->for($gallery)->favorited()->create();

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->call('showFavorites')
            ->call('showTab', 'commented');

        expect($component->get('activeTab'))->toBe('commented')
            ->and($component->get('favoritesOnly'))->toBeFalse();
    });

    it('clears every filter and restarts paging', function () {
        $gallery = Gallery::factory()->for($this->team)->create();
        Photo::factory()->for($gallery)->rated(5)->create();

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->set('allPhotoLimit', 120)
            ->call('filterByRating', 4)
            ->call('showFavorites')
            ->call('clearFilters');

        expect($component->get('ratingFilter'))->toBeNull()
            ->and($component->get('favoritesOnly'))->toBeFalse()
            ->and($component->get('allPhotoLimit'))->toBe(60);
    });

    it('sorts by rating with unrated photos last in both directions', function () {
        $gallery = Gallery::factory()->for($this->team)->create();
        $two = Photo::factory()->for($gallery)->rated(2)->create(['name' => 'a.jpg']);
        $five = Photo::factory()->for($gallery)->rated(5)->create(['name' => 'b.jpg']);
        $unrated = Photo::factory()->for($gallery)->create(['name' => 'c.jpg']);

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->call('sortBy', 'rating');

        expect($component->get('sortDirection'))->toBe('desc')
            ->and($component->allPhotos->pluck('id')->all())->toBe([$five->id, $two->id, $unrated->id]);

        $component->call('toggleSortDirection');

        expect($component->allPhotos->pluck('id')->all())->toBe([$two->id, $five->id, $unrated->id]);
    });

    it('sorts by date, size and version count', function () {
        $gallery = Gallery::factory()->for($this->team)->create();
        $older = Photo::factory()->for($gallery)->create([
            'name' => 'a.jpg',
            'uploaded_at' => now()->subDays(2),
            'size' => 500,
            'version_count' => 1,
        ]);
        $newer = Photo::factory()->for($gallery)->create([
            'name' => 'b.jpg',
            'uploaded_at' => now()->subDay(),
            'size' => 100,
            'version_count' => 3,
        ]);

        // The versions sort counts stored rows, not the `version_count`
        // sequence, so the fixture needs real history for the order to mean
        // anything.
        PhotoVersion::factory()->for($newer)->create(['version_number' => 1]);
        PhotoVersion::factory()->for($newer)->create(['version_number' => 2]);

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->call('sortBy', 'date');
        expect($component->allPhotos->pluck('id')->all())->toBe([$newer->id, $older->id]);

        $component->call('sortBy', 'size');
        expect($component->allPhotos->pluck('id')->all())->toBe([$older->id, $newer->id]);

        $component->call('sortBy', 'versions');
        expect($component->allPhotos->pluck('id')->all())->toBe([$newer->id, $older->id]);
    });

    it('sorts names naturally in both directions', function () {
        $gallery = Gallery::factory()->for($this->team)->create();
        Photo::factory()->for($gallery)->create(['name' => 'photo10.jpg']);
        Photo::factory()->for($gallery)->create(['name' => 'photo2.jpg']);

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery]);

        expect($component->allPhotos->pluck('name')->all())->toBe(['photo2.jpg', 'photo10.jpg']);

        $component->call('toggleSortDirection');

        expect($component->allPhotos->pluck('name')->all())->toBe(['photo10.jpg', 'photo2.jpg']);
    });

    it('shows a filtered empty state when nothing matches', function () {
        $gallery = Gallery::factory()->for($this->team)->create();
        Photo::factory()->for($gallery)->rated(2)->create();

        Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->call('filterByRating', 5)
            ->assertSee('No photos match these filters');
    });

    it('shows the compact sticky toolbar next to the tabs', function () {
        $gallery = Gallery::factory()->for($this->team)->create();
        Photo::factory()->for($gallery)->rated(3)->create();

        Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->assertSeeHtml('data-gallery-filter-bar')
            ->assertSeeHtml('data-rating-filter-trigger')
            ->assertSeeHtml('data-sort-trigger')
            ->assertSeeHtml('data-sort-direction')
            ->assertSeeHtml('data-favorites-tab');
    });

    it('filters by an exact rating from the sticky toolbar', function () {
        $gallery = Gallery::factory()->for($this->team)->create();
        Photo::factory()->for($gallery)->rated(2)->create(['name' => 'a.jpg']);
        Photo::factory()->for($gallery)->rated(5)->create(['name' => 'b.jpg']);

        $this->actingAs($this->user);

        $page = visit("/galleries/{$gallery->id}");

        $page->click('[data-rating-filter-trigger]');
        $page->click('[data-rating-filter-popover] [data-rating-star="5"]');
        $page->wait(0.4);

        expect($page->script("document.querySelectorAll('[data-photo-card]').length"))->toBe(1);
    });
});

describe('Rated count cache', function () {
    it('caches the rated count and drops it when a photo is rated', function () {
        $gallery = Gallery::factory()->for($this->team)->create();
        Photo::factory()->for($gallery)->rated(3)->create();
        Photo::factory()->for($gallery)->create(['rating' => null]);

        expect($gallery->ratedPhotoCount())->toBe(1);

        // The second read must not touch the database: the header asks for
        // this on every render, including each 2-second upload poll.
        DB::enableQueryLog();
        $gallery->ratedPhotoCount();
        expect(DB::getQueryLog())->toBeEmpty();

        // Rating another photo invalidates the cached value, so the header is
        // never a whole hour behind.
        $unrated = $gallery->photos()->whereNull('rating')->firstOrFail();
        $unrated->update(['rating' => 5]);

        expect($gallery->ratedPhotoCount())->toBe(2);
    });

    it('shows the rated count in the header on a fresh request after rating a photo', function () {
        $gallery = Gallery::factory()->for($this->team)->create();
        $photo = Photo::factory()->for($gallery)->create(['rating' => null]);

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery]);

        expect($component->instance()->totalRated)->toBe(0);

        $photo->update(['rating' => 4]);

        // A new request gets a new component instance, which is what a real
        // page load or upload poll does.
        $fresh = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery]);

        expect($fresh->instance()->totalRated)->toBe(1);
    });
});

describe('Photo Upload', function () {
    it('allows photos to be added to a gallery', function () {
        Storage::fake('s3');

        Event::fake(PhotoAdded::class);

        $gallery = Gallery::factory()->for($this->team)->create(['ulid' => '1243ABC']);

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])->set([
            'photos.0' => UploadedFile::fake()->image('photo1.jpg'),
            'photos.1' => UploadedFile::fake()->image('photo2.jpg'),
        ])->call('save', 0)->call('save', 1);

        expect($gallery->photos()->count())->toBe(2);
        tap($gallery->fresh()->photos[0], function ($photo) {
            expect($photo->name)->toBe('photo1.jpg');
            expect($photo->path)->toContain('galleries/1243ABC/photos/');
            expect($photo->url)->not()->toBeNull();
            expect($photo->size)->not()->toBeNull();
            expect(Storage::disk('s3')->exists($photo->path))->toBeTrue();
            expect($photo->status)->toBe('pending');
            Event::assertDispatched(PhotoAdded::class);
        });
        tap($gallery->fresh()->photos[1], function ($photo) {
            expect($photo->name)->toBe('photo2.jpg');
            expect($photo->path)->toContain('galleries/1243ABC/photos/');
            expect($photo->url)->not()->toBeNull();
            expect($photo->size)->not()->toBeNull();
            expect(Storage::disk('s3')->exists($photo->path))->toBeTrue();
            expect($photo->status)->toBe('pending');
            Event::assertDispatched(PhotoAdded::class);
        });
    });

    it('dispatches ProcessPhoto job when a photo is added', function () {
        Queue::fake();
        Storage::fake('s3');

        $gallery = Gallery::factory()->for($this->team)->create(['ulid' => '1243ABC']);

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])->set([
            'photos.0' => UploadedFile::fake()->image('photo1.jpg', 129, 129),
        ])->call('save', 0);

        $photo = $gallery->fresh()->photos[0];
        Queue::assertPushed(ProcessPhoto::class, function ($job) use ($photo) {
            return $job->photo->is($photo);
        });
    });

    it('allows pdfs to be added to a gallery', function () {
        Storage::fake('s3');

        Event::fake(PhotoAdded::class);

        $gallery = Gallery::factory()->for($this->team)->create(['ulid' => '1243ABC']);

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->set('photos.0', UploadedFile::fake()->createWithContent('composite.pdf', '%PDF-1.4 fake pdf body'))
            ->call('save', 0);

        $component->assertHasNoErrors();

        $photo = $gallery->fresh()->photos[0];
        expect($photo->name)->toBe('composite.pdf');
        expect($photo->path)->toEndWith('.pdf');
        expect($photo->status)->toBe('pending');
        expect(Storage::disk('s3')->exists($photo->path))->toBeTrue();
    });

    it('rejects files with extensions outside the upload allowlist', function () {
        Storage::fake('s3');

        Event::fake(PhotoAdded::class);

        $gallery = Gallery::factory()->for($this->team)->create(['ulid' => '1243ABC']);

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery]);

        foreach (['invoice.exe', 'evil.html', 'README'] as $index => $fileName) {
            $component->set("photos.{$index}", UploadedFile::fake()->create($fileName, 100))
                ->call('save', $index)
                ->assertHasErrors(["photos.{$index}" => 'This file type is not supported.']);
        }

        expect($gallery->fresh()->photos()->count())->toBe(0);
        Event::assertNotDispatched(PhotoAdded::class);
    });

    it('rejects pdfs whose content is not a pdf', function () {
        Storage::fake('s3');

        Event::fake(PhotoAdded::class);

        $gallery = Gallery::factory()->for($this->team)->create(['ulid' => '1243ABC']);

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->set('photos.0', UploadedFile::fake()->createWithContent('composite.pdf', '<html>not a pdf</html>'))
            ->call('save', 0);

        $component->assertHasErrors(['photos.0' => 'This file is not a valid PDF.']);
        expect($gallery->fresh()->photos()->count())->toBe(0);
    });

    it('rejects files whose name contains control characters', function () {
        Storage::fake('s3');

        Event::fake(PhotoAdded::class);

        $gallery = Gallery::factory()->for($this->team)->create(['ulid' => '1243ABC']);

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->set('photos.0', UploadedFile::fake()->createWithContent("evil\0composite.pdf", '%PDF-1.4 fake pdf body'))
            ->call('save', 0);

        $component->assertHasErrors(['photos.0' => 'The file name contains invalid characters.']);
        expect($gallery->fresh()->photos()->count())->toBe(0);
    });

    it('accepts every previously advertised upload extension', function (string $extension) {
        Storage::fake('s3');

        Event::fake(PhotoAdded::class);

        $gallery = Gallery::factory()->for($this->team)->create(['ulid' => '1243ABC']);

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->set('photos.0', UploadedFile::fake()->create("photo.{$extension}", 100))
            ->call('save', 0);

        $component->assertHasNoErrors();
        expect($gallery->fresh()->photos()->count())->toBe(1);
        expect($gallery->fresh()->photos[0]->name)->toBe("photo.{$extension}");
    })->with(['jpg', 'jpeg', 'png', 'tiff', 'mp4', 'webm', 'ogg', 'cr2', 'cr3', 'nef', 'arw', 'dng', 'orf', 'rw2', 'pef', 'srw', 'mos', 'mrw', '3fr']);

    it('keeps the upload extension allowlist equal to the advertised formats plus pdf', function () {
        expect(config('picstome.upload_extensions'))->toBe([
            'jpg', 'jpeg', 'png', 'tiff', 'mp4', 'webm', 'ogg',
            'cr2', 'cr3', 'nef', 'arw', 'dng', 'orf', 'rw2', 'pef', 'srw', 'mos', 'mrw', '3fr',
            'pdf',
        ]);
    });

    it('finds the selected file names that already exist in the gallery', function () {
        $gallery = Gallery::factory()->for($this->team)->create();
        Photo::factory()->for($gallery)->create(['name' => 'photo1.jpg']);
        Photo::factory()->for($gallery)->create(['name' => 'a_id_.jpg']);

        Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->call('findDuplicateNames', ['photo1.jpg', 'photo2.jpg', 'a`id`.jpg', 'photo1.jpg'])
            ->assertSet('existingPhotoNames', ['photo1.jpg', 'a`id`.jpg']);
    });

    it('creates a new version when a duplicate file name is uploaded', function () {
        Storage::fake('s3');

        Event::fake(PhotoAdded::class);

        $gallery = Gallery::factory()->for($this->team)->create();
        $photo = Photo::factory()->for($gallery)->create(['name' => 'photo1.jpg', 'path' => 'old/path/photo1.jpg', 'source_name' => 'photo1.jpg', 'version_count' => 1]);

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->set('photos.0', UploadedFile::fake()->image('photo1.jpg'))
            ->call('save', 0);

        $component->assertHasNoErrors();
        $photo->refresh();
        expect($gallery->photos()->count())->toBe(1);
        expect($photo->version_count)->toBe(2);
        expect(PhotoVersion::where('photo_id', $photo->id)->count())->toBe(1);
        expect($photo->path)->not->toBe('old/path/photo1.jpg');
    });

    it('records a new version when uploading the same file name again', function () {
        Storage::fake('s3');
        Queue::fake();

        $gallery = Gallery::factory()->for($this->team)->create();
        $photo = Photo::factory()->for($gallery)->favorited()->create([
            'name' => 'photo1.jpg',
            'source_name' => 'photo1.jpg',
            'path' => 'old/path/photo1.jpg',
            'size' => 1024,
            'status' => 'processed',
            'version_count' => 1,
        ]);
        PhotoComment::factory()->for($photo)->create();

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->set('photos.0', UploadedFile::fake()->image('photo1.jpg'))
            ->call('save', 0);

        $component->assertHasNoErrors();
        $photo->refresh();

        // Same row survives, so favorites and comments are preserved.
        expect($gallery->photos()->count())->toBe(1);
        expect($photo->favorited_at)->not->toBeNull();
        expect($photo->comments)->toHaveCount(1);

        // The older version is retained and processing is scheduled again.
        expect($photo->path)->not->toBe('old/path/photo1.jpg');
        expect($photo->size)->toBeGreaterThan(0);
        expect($photo->status)->toBe('pending');
        expect($photo->version_count)->toBe(2);
        expect(PhotoVersion::where('photo_id', $photo->id)->count())->toBe(1);

        Queue::assertPushed(ProcessPhoto::class);
        Queue::assertNotPushed(function (DeleteFromDisk $job) {
            return $job->path === 'old/path/photo1.jpg';
        });
    });

    it('replaces only the current version and keeps older versions', function () {
        Storage::fake('s3');
        Queue::fake();

        $gallery = Gallery::factory()->for($this->team)->create();
        $photo = Photo::factory()->for($gallery)->create([
            'name' => 'photo1.jpg',
            'source_name' => 'photo1.jpg',
            'path' => 'old/current.jpg',
            'size' => 1024,
            'status' => 'processed',
            'version_count' => 2,
        ]);
        $oldVersion = PhotoVersion::factory()->for($photo)->create([
            'version_number' => 1,
            'path' => 'old/history.jpg',
        ]);

        Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->set('photos.0', UploadedFile::fake()->image('photo1.jpg'))
            ->call('save', 0, 'replace')
            ->assertHasNoErrors();

        $photo->refresh();

        expect($photo->version_count)->toBe(2);
        expect($photo->current_version_id)->toBeNull();
        expect($photo->path)->toBe('old/current.jpg');
        expect($photo->versions()->count())->toBe(1);
        expect($oldVersion->fresh()->path)->toBe('old/history.jpg');
        Queue::assertNotPushed(DeleteFromDisk::class, fn ($job) => $job->path === 'old/history.jpg');
    });

    it('adds the photo when replace is chosen but the original was already deleted', function () {
        Storage::fake('s3');

        Event::fake(PhotoAdded::class);

        $gallery = Gallery::factory()->for($this->team)->create();

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->set('photos.0', UploadedFile::fake()->image('photo1.jpg'))
            ->call('save', 0, true);

        $component->assertHasNoErrors();
        expect($gallery->photos()->count())->toBe(1);
    });
});

describe('Gallery Sharing', function () {
    it('can be shared with no options enabled', function () {
        $gallery = Gallery::factory()->for($this->team)->create();

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->call('share');

        expect($gallery->fresh()->is_shared)->toBeTrue();
    });

    it('can expose versions in shared galleries and remembers the default', function () {
        $gallery = Gallery::factory()->for($this->team)->create();

        Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->set('shareForm.versionsVisible', true)
            ->call('share')
            ->assertHasNoErrors();

        expect($gallery->fresh()->is_share_versions_visible)->toBeTrue();
        expect($this->user->fresh()->shareGalleryDefaults()['versions_visible'])->toBeTrue();
    });

    it('prefills new gallery sharing options from the user defaults', function () {
        $this->user->rememberShareGalleryDefaults([
            'selectable' => true,
            'versions_visible' => true,
            'comments_enabled' => false,
        ]);

        $gallery = Gallery::factory()->for($this->team)->create();

        Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->assertSet('shareForm.selectable', true)
            ->assertSet('shareForm.versionsVisible', true)
            ->assertSet('shareForm.commentsEnabled', false);
    });

    it('can be shared with selectable options enabled', function () {
        $gallery = Gallery::factory()->for($this->team)->create();
        Subscription::factory()->for($this->user->currentTeam, 'owner')->create();

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->set('shareForm.selectable', true)
            ->call('share');

        expect($gallery->fresh()->is_shared)->toBeTrue();
        expect($gallery->fresh()->is_share_selectable)->toBeTrue();
    });

    it('can be shared with downloadable options enabled', function () {
        $gallery = Gallery::factory()->for($this->team)->create();

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->set('shareForm.downloadable', true)
            ->call('share');

        expect($gallery->fresh()->is_shared)->toBeTrue();
        expect($gallery->fresh()->is_share_downloadable)->toBeTrue();
    });

    it('can be shared with limited selection', function () {
        $gallery = Gallery::factory()->for($this->team)->create();

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->set('shareForm.limitedSelection', true)
            ->set('shareForm.selectionLimit', 10)
            ->call('share');

        expect($gallery->fresh()->share_selection_limit)->toBe(10);
    });

    it('can be disabled for sharing', function () {
        $gallery = Gallery::factory()->for($this->team)->shared()->create();

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])->call('disableSharing');

        expect($gallery->fresh()->is_shared)->toBeFalse();
    });

    it('can be shared with password protection', function () {
        Subscription::factory()->for($this->user->currentTeam, 'owner')->create();
        $gallery = Gallery::factory()->for($this->team)->create();
        expect($gallery->share_password)->toBeNull();

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->set('shareForm.passwordProtected', true)
            ->set('shareForm.password', 'secret')
            ->call('share');

        expect($gallery->fresh()->share_password)->not->toBeNull();
    });

    it('can be shared with watermarked options enabled', function () {
        $gallery = Gallery::factory()->for($this->team)->create();
        expect($gallery->fresh()->is_share_watermarked)->toBeFalse();

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->set('shareForm.watermarked', true)
            ->call('share');

        expect($gallery->fresh()->is_shared)->toBeTrue();
        expect($gallery->fresh()->is_share_watermarked)->toBeTrue();
    });

    it('can disable password protection', function () {
        $gallery = Gallery::factory()->for($this->team)->protected('password')->create();
        expect($gallery->share_password)->not->toBeNull();

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->set('shareForm.passwordProtected', false)
            ->call('share');

        expect($gallery->fresh()->share_password)->toBeNull();
    });

    it('can enable and disable photo comments for a shared gallery', function () {
        Subscription::factory()->for($this->user->currentTeam, 'owner')->create();
        $gallery = Gallery::factory()->for($this->team)->create();

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->set('shareForm.commentsEnabled', true)
            ->call('share');

        $gallery->refresh();

        expect($gallery->are_comments_enabled)->toBeTrue();

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->set('shareForm.commentsEnabled', false)
            ->call('share');

        $gallery->refresh();

        expect($gallery->are_comments_enabled)->toBeFalse();
    });

    it('can enable and disable photo annotations for a shared gallery', function () {
        Subscription::factory()->for($this->user->currentTeam, 'owner')->create();
        $gallery = Gallery::factory()->for($this->team)->create();

        Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->set('shareForm.annotationsEnabled', true)
            ->call('share');

        $gallery->refresh();

        expect($gallery->are_annotations_enabled)->toBeTrue();
        expect($gallery->are_comments_enabled)->toBeTrue();

        Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->set('shareForm.annotationsEnabled', false)
            ->call('share');

        expect($gallery->fresh()->are_annotations_enabled)->toBeFalse();
    });

    it('enables photo comments when photo annotations are enabled', function () {
        Subscription::factory()->for($this->user->currentTeam, 'owner')->create();
        $gallery = Gallery::factory()->for($this->team)->create();

        Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->set('shareForm.commentsEnabled', false)
            ->set('shareForm.annotationsEnabled', true)
            ->assertSet('shareForm.commentsEnabled', true)
            ->call('share');

        $gallery->refresh();

        expect($gallery->are_comments_enabled)->toBeTrue();
        expect($gallery->are_annotations_enabled)->toBeTrue();
    });

    it('disables photo annotations when photo comments are disabled', function () {
        Subscription::factory()->for($this->user->currentTeam, 'owner')->create();
        $gallery = Gallery::factory()->for($this->team)->create();

        Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->set('shareForm.annotationsEnabled', true)
            ->set('shareForm.commentsEnabled', false)
            ->assertSet('shareForm.annotationsEnabled', false)
            ->call('share');

        $gallery->refresh();

        expect($gallery->are_comments_enabled)->toBeFalse();
        expect($gallery->are_annotations_enabled)->toBeFalse();
    });

    it('does not seed photo annotations when photo comments are disabled', function () {
        Subscription::factory()->for($this->user->currentTeam, 'owner')->create();
        $gallery = Gallery::factory()->for($this->team)->shared()->create([
            'are_comments_enabled' => false,
            'are_annotations_enabled' => true,
        ]);

        Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->assertSet('shareForm.annotationsEnabled', false);
    });

    it('keeps photo annotations enabled for free teams', function () {
        $gallery = Gallery::factory()->for($this->team)->create();

        Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->set('shareForm.commentsEnabled', true)
            ->set('shareForm.annotationsEnabled', true)
            ->call('share');

        expect($gallery->fresh()->are_annotations_enabled)->toBeTrue();
    });

    it('can be shared with a description', function () {
        Subscription::factory()->for($this->user->currentTeam, 'owner')->create();
        $gallery = Gallery::factory()->for($this->team)->create();
        expect($gallery->share_description)->toBeNull();

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->set('shareForm.descriptionEnabled', true)
            ->set('shareForm.description', 'This is a beautiful wedding gallery')
            ->call('share');

        expect($gallery->fresh()->is_shared)->toBeTrue();
        expect($gallery->fresh()->share_description)->toBe('This is a beautiful wedding gallery');
    });

    it('can update the share description', function () {
        Subscription::factory()->for($this->user->currentTeam, 'owner')->create();
        $gallery = Gallery::factory()->for($this->team)->shared()->create(['share_description' => 'Old description']);
        expect($gallery->share_description)->toBe('Old description');

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->set('shareForm.descriptionEnabled', true)
            ->set('shareForm.description', 'Updated description')
            ->call('share');

        expect($gallery->fresh()->share_description)->toBe('Updated description');
    });

    it('can be shared without a description', function () {
        $gallery = Gallery::factory()->for($this->team)->create();

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->set('shareForm.descriptionEnabled', false)
            ->call('share');

        expect($gallery->fresh()->is_shared)->toBeTrue();
        expect($gallery->fresh()->share_description)->toBeNull();
    });

    it('can remove the share description', function () {
        $gallery = Gallery::factory()->for($this->team)->shared()->create(['share_description' => 'Some description']);
        expect($gallery->share_description)->toBe('Some description');

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->set('shareForm.descriptionEnabled', false)
            ->call('share');

        expect($gallery->fresh()->share_description)->toBeNull();
    });

    it('requires description when description is enabled', function () {
        Subscription::factory()->for($this->user->currentTeam, 'owner')->create();
        $gallery = Gallery::factory()->for($this->team)->create();

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->set('shareForm.descriptionEnabled', true)
            ->set('shareForm.description', '')
            ->call('share');

        $component->assertHasErrors('shareForm.description');
    });

    it('does not require description when description is disabled', function () {
        $gallery = Gallery::factory()->for($this->team)->create();

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->set('shareForm.descriptionEnabled', false)
            ->set('shareForm.description', '')
            ->call('share');

        $component->assertHasNoErrors('shareForm.description');
        expect($gallery->fresh()->share_description)->toBeNull();
    });

    it('can be shared without cover image visible (default)', function () {
        $gallery = Gallery::factory()->for($this->team)->create();

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->call('share');

        expect($gallery->fresh()->is_shared)->toBeTrue();
    });

    it('renders the share url for galleries whose name has no latin characters', function () {
        $gallery = Gallery::factory()->for($this->team)->create([
            'name' => '日本語ギャラリー',
            'ulid' => 'TESTGALLERY',
        ]);

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery]);

        $component->assertHasNoErrors();
        $component->assertSee('shares/TESTGALLERY/TESTGALLERY');
    });

    it('falls back to the ulid when the name has no slugifiable characters', function () {
        $gallery = Gallery::factory()->for($this->team)->create([
            'name' => '日本語ギャラリー',
            'ulid' => 'TESTGALLERY',
        ]);

        expect($gallery->slug)->toBe('TESTGALLERY');
        expect(Gallery::factory()->for($this->team)->create()->slug)->toBe('just-a-gallery');
    });
});

describe('Favorites', function () {
    it('allows users to view their team gallery favorites', function () {
        $gallery = Gallery::factory()->for($this->team)->create();
        $favorite = Photo::factory()->for($gallery)->favorited()->create();

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->call('showFavorites');

        expect($component->allPhotos->contains($favorite))->toBeTrue();
    });

    it('refreshes the favorites tab count after rating five stars in the viewer', function () {
        $gallery = Gallery::factory()->for($this->team)->create();
        $photo = Photo::factory()->for($gallery)->unfavorited()->create();

        // Warm the cached favorites list with the old (empty) value.
        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery]);
        expect($component->totalFavorites)->toBe(0);

        Livewire::actingAs($this->user)
            ->test('pages::galleries.photos.show', ['photo' => $photo])
            ->call('setRating', 5);

        // The gallery page is a fresh request after the viewer action.
        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery]);

        expect($component->totalFavorites)->toBe(1)
            ->and($photo->fresh()->isFavorited())->toBeTrue();
    });

    it('refreshes the favorites list after marking photos as favorites', function () {
        $gallery = Gallery::factory()->for($this->team)->create();
        Photo::factory()->for($gallery)->create(['name' => '1Z8A6159.jpg']);
        Photo::factory()->for($gallery)->favorited()->create(['name' => '1Z8A6190.jpg']);

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->call('showFavorites');

        expect($component->allPhotos->count())->toBe(1);

        $component->set('favoriteFileNames', '1Z8A6159')
            ->call('markFavorites');

        expect($component->allPhotos->pluck('name')->sort()->values()->all())->toBe(['1Z8A6159.jpg', '1Z8A6190.jpg'])
            ->and($gallery->photos()->where('name', '1Z8A6159.jpg')->first()->rating)->toBe(5);
    });

    it('allows users to favorite a photo', function () {
        $photo = Photo::factory()->unfavorited()->create();
        expect($photo->isFavorited())->toBeFalse();

        $component = Livewire::actingAs($this->user)->test('photo-item', ['photo' => $photo])
            ->call('favorite', $photo->id);

        $component->assertDispatched('photo-favorited');
        expect($photo->fresh()->isFavorited())->toBeTrue()
            ->and($photo->fresh()->rating)->toBe(5);
    });

    it('marks photos as favorites by filenames with extensions', function () {
        $gallery = Gallery::factory()->for($this->team)->create();
        Photo::factory()->for($gallery)->create(['name' => '1Z8A6159.jpg']);
        Photo::factory()->for($gallery)->create(['name' => '1Z8A6159.CR3']);
        Photo::factory()->for($gallery)->create(['name' => '1Z8A6190.jpg']);
        Photo::factory()->for($gallery)->create(['name' => '1Z8A6190.CR3']);
        Photo::factory()->for($gallery)->create(['name' => '1Z8A6255.jpg']);

        Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->set('favoriteFileNames', "1Z8A6159.jpg\n1Z8A6190.CR3")
            ->call('markFavorites');

        $favorites = $gallery->photos()->favorited()->pluck('name')->sort()->values();
        expect($favorites->count())->toBe(4);
        expect($favorites)->toContain('1Z8A6159.jpg', '1Z8A6159.CR3', '1Z8A6190.jpg', '1Z8A6190.CR3');
    });

    it('marks photos as favorites by base names without extensions', function () {
        $gallery = Gallery::factory()->for($this->team)->create();
        Photo::factory()->for($gallery)->create(['name' => '1Z8A6159.jpg']);
        Photo::factory()->for($gallery)->create(['name' => '1Z8A6159.CR3']);
        Photo::factory()->for($gallery)->create(['name' => '1Z8A6190.jpg']);
        Photo::factory()->for($gallery)->create(['name' => '1Z8A6190.CR3']);
        Photo::factory()->for($gallery)->create(['name' => '1Z8A6255.jpg']);

        Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->set('favoriteFileNames', "1Z8A6159\n1Z8A6190")
            ->call('markFavorites');

        $favorites = $gallery->photos()->favorited()->pluck('name')->sort()->values();
        expect($favorites->count())->toBe(4);
        expect($favorites)->toContain('1Z8A6159.jpg', '1Z8A6159.CR3', '1Z8A6190.jpg', '1Z8A6190.CR3');
    });

    it('only marks previously unfavorited photos', function () {
        $gallery = Gallery::factory()->for($this->team)->create();
        Photo::factory()->for($gallery)->favorited()->create(['name' => '1Z8A6198.jpg']);
        Photo::factory()->for($gallery)->create(['name' => '1Z8A6255.jpg']);

        Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->set('favoriteFileNames', "1Z8A6198\n1Z8A6255")
            ->call('markFavorites');

        expect($gallery->photos()->favorited()->count())->toBe(2);
    });

    it('does not mark photos from other galleries', function () {
        $gallery = Gallery::factory()->for($this->team)->create();
        $otherGallery = Gallery::factory()->for($this->team)->create();
        Photo::factory()->for($gallery)->create(['name' => '1Z8A6159.jpg']);
        Photo::factory()->for($otherGallery)->create(['name' => '1Z8A6159.jpg']);

        Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->set('favoriteFileNames', '1Z8A6159')
            ->call('markFavorites');

        expect($gallery->photos()->favorited()->count())->toBe(1);
        expect($otherGallery->photos()->favorited()->count())->toBe(0);
    });

    it('clears the input after marking favorites', function () {
        $gallery = Gallery::factory()->for($this->team)->create();
        Photo::factory()->for($gallery)->create(['name' => '1Z8A6159.jpg']);

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->set('favoriteFileNames', '1Z8A6159.jpg')
            ->call('markFavorites');

        $component->assertSet('favoriteFileNames', '');
    });

    it('validates that favoriteFileNames is required', function () {
        $gallery = Gallery::factory()->for($this->team)->create();

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->call('markFavorites');

        $component->assertHasErrors(['favoriteFileNames' => 'required']);
    });

    it('handles empty lines and whitespace gracefully', function () {
        $gallery = Gallery::factory()->for($this->team)->create();
        Photo::factory()->for($gallery)->create(['name' => '1Z8A6159.jpg']);
        Photo::factory()->for($gallery)->create(['name' => '1Z8A6159.CR3']);
        Photo::factory()->for($gallery)->create(['name' => '1Z8A6190.CR3']);

        Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->set('favoriteFileNames', "\n  1Z8A6159.jpg  \n\n  \n1Z8A6190.CR3\n")
            ->call('markFavorites');

        $favorites = $gallery->photos()->favorited()->pluck('name')->sort()->values();
        expect($favorites->count())->toBe(3);
        expect($favorites)->toContain('1Z8A6159.jpg', '1Z8A6159.CR3', '1Z8A6190.CR3');
    });
});

describe('Photo Deletion', function () {
    it('allows users to delete a photo', function () {
        Storage::fake('public');
        Storage::fake('s3');

        $gallery = Gallery::factory()->for($this->team)->create();
        $photo = Photo::factory()->for($gallery)->create([
            'name' => 'photo1.jpg',
            'disk' => 's3',
            'path' => UploadedFile::fake()
                ->image('photo1.jpg')
                ->storeAs('galleries/1/photos', 'photo1.jpg', 's3'),
        ]);
        expect(Storage::disk('s3')->exists('galleries/1/photos/photo1.jpg'))->toBeTrue();
        expect(Photo::count())->toBe(1);

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->call('deletePhoto', $photo->id);

        expect($gallery->photos()->count())->toBe(0);
        expect(Storage::disk('s3')->exists('galleries/1/photos/photo1.jpg'))->toBeFalse();
    });

    it('asks for confirmation before deleting a photo', function () {
        Storage::fake('s3');

        $gallery = Gallery::factory()->for($this->team)->create();
        $photo = Photo::factory()->for($gallery)->create([
            'name' => 'photo1.jpg',
            'disk' => 's3',
            'path' => UploadedFile::fake()
                ->image('photo1.jpg')
                ->storeAs('galleries/1/photos', 'photo1.jpg', 's3'),
        ]);

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->call('requestDeletePhoto', $photo->id);

        expect($component->get('pendingPhotoId'))->toBe($photo->id);
        expect($gallery->photos()->count())->toBe(1);
    });

    it('removes the deleted photo from the grid after confirmation', function () {
        Storage::fake('s3');

        $gallery = Gallery::factory()->for($this->team)->create();
        Photo::factory()->for($gallery)->create(['status' => 'processed']);
        Photo::factory()->for($gallery)->create(['status' => 'processed']);

        $this->actingAs($this->user);

        $page = visit("/galleries/{$gallery->id}");
        $page->hover('[data-photo-card]:first-of-type');
        $page->wait(0.2);
        $page->click('[data-photo-card]:first-of-type [data-photo-delete]');
        $page->wait(0.3);
        $page->click('#delete-photo button[wire\\:click]');
        $page->wait(1);

        expect($page->script("document.querySelectorAll('[data-photo-card]').length"))->toBe(1);
        expect($gallery->photos()->count())->toBe(1);
    });

    it('offers a quick delete button next to the favorite action on the photo tile', function () {
        Storage::fake('s3');

        $gallery = Gallery::factory()->for($this->team)->create();
        Photo::factory()->for($gallery)->create(['status' => 'processed']);

        $this->actingAs($this->user);

        $page = visit("/galleries/{$gallery->id}");
        $page->hover('[data-photo-card]');
        $page->wait(0.2);
        $page->click('[data-photo-delete]');
        $page->wait(0.5);

        expect($page->script(<<<'JS'
            [...document.querySelectorAll('[role="dialog"]')]
                .some((dialog) => getComputedStyle(dialog).display !== 'none'
                    && dialog.innerText.includes('Delete photo?'))
        JS))->toBeTrue();

        expect($gallery->photos()->count())->toBe(1);
    });

    it('prevents users from deleting another team\'s photo', function () {
        $gallery = Gallery::factory()->for($this->team)->create();
        $photo = Photo::factory()->for(Gallery::factory()->for(Team::factory()))->create();

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->call('deletePhoto', $photo->id);

        $component->assertStatus(403);
    });

    it('allows users to delete their team gallery', function () {
        Storage::fake('s3');

        $photos = collect([
            UploadedFile::fake()->image('photo1.jpg'),
            UploadedFile::fake()->image('photo2.jpg'),
        ]);
        $gallery = Gallery::factory()->for($this->team)->create(['name' => 'Example Gallery']);
        $photos->each(function ($photo) use ($gallery) {
            $gallery->addPhoto($photo);
        });
        $gallery->photos->each(function ($photo) {
            expect(Storage::disk('s3')->exists($photo->path))->toBeTrue();
        });

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])->call('delete');
        $component->assertRedirect('/galleries');
        expect(Gallery::count())->toBe(0);
        expect(Photo::count())->toBe(0);
        $gallery->photos->each(function ($photo) {
            expect(Storage::disk('s3')->exists($photo->path))->toBeFalse();
        });
    });
});

describe('Gallery Photoshoot Association', function () {
    it('allows assigning a gallery to a photoshoot', function () {
        $photoshoot = Photoshoot::factory()->for($this->team)->create();
        $gallery = Gallery::factory()->for($this->team)->withExpirationDate()->create();

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->set('form.photoshoot_id', $photoshoot->id)
            ->call('update');

        $gallery->refresh();
        expect($gallery->photoshoot_id)->toBe($photoshoot->id);
    });

    it('allows changing a gallery\'s photoshoot', function () {
        $photoshootA = Photoshoot::factory()->for($this->team)->create();
        $photoshootB = Photoshoot::factory()->for($this->team)->create();
        $gallery = Gallery::factory()->for($this->team)->withExpirationDate()->create([
            'photoshoot_id' => $photoshootA->id,
        ]);

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->set('form.photoshoot_id', $photoshootB->id)
            ->call('update');

        $gallery->refresh();
        expect($gallery->photoshoot_id)->toBe($photoshootB->id);
    });

    it('prevents assigning a photoshoot from another team', function () {
        $otherTeam = Team::factory()->create();
        $photoshoot = Photoshoot::factory()->for($otherTeam)->create();
        $gallery = Gallery::factory()->for($this->team)->create();

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->set('form.photoshoot_id', $photoshoot->id)
            ->call('update');

        $gallery->refresh();
        expect($gallery->photoshoot_id)->toBeNull();
        $component->assertHasErrors(['form.photoshoot_id' => 'exists']);
    });

    it('allows removing a gallery from a photoshoot', function () {
        $photoshoot = Photoshoot::factory()->for($this->team)->create();
        $gallery = Gallery::factory()->for($this->team)->withExpirationDate()->create([
            'photoshoot_id' => $photoshoot->id,
        ]);

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->set('form.photoshoot_id', null)
            ->call('update');

        $gallery->refresh();
        expect($gallery->photoshoot_id)->toBeNull();
    });
});

describe('Gallery Editing', function () {
    it('allows editing a team gallery', function () {
        $gallery = Gallery::factory()->for($this->team)->withExpirationDate()->create();

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->set('form.name', 'Edited Gallery')
            ->call('update');

        expect($gallery->fresh()->name)->toBe('Edited Gallery');
    });

    it('sets the gallery expiration date', function () {
        $gallery = Gallery::factory()->for($this->team)->create();
        $expirationDate = now()->addDays(10)->toDateString();

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->set('form.expirationDate', $expirationDate)
            ->call('update');
        $gallery->refresh();
        expect($gallery->expiration_date->toDateString())->toBe($expirationDate);
    });

    it('changes the gallery expiration date', function () {
        $gallery = Gallery::factory()->for($this->team)->create(['expiration_date' => now()->addDays(5)]);
        $newExpiration = now()->addDays(20)->toDateString();

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->set('form.expirationDate', $newExpiration)
            ->call('update');
        $gallery->refresh();
        expect($gallery->expiration_date->toDateString())->toBe($newExpiration);
    });

    it('removes the gallery expiration date when subscribed', function () {
        Subscription::factory()->for($this->user->currentTeam, 'owner')->create();
        $gallery = Gallery::factory()->for($this->team)->create(['expiration_date' => now()->addDays(5)]);

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->set('form.expirationDate', '')
            ->call('update');
        $gallery->refresh();
        expect($gallery->expiration_date)->toBeNull();
    });

    it('cannot remove the gallery expiration date when not subscribed', function () {
        $gallery = Gallery::factory()->for($this->team)->create(['expiration_date' => now()->addDays(5)]);

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->set('form.expirationDate', '')
            ->call('update');

        $component->assertHasErrors(['form.expirationDate' => 'required']);
        $gallery->refresh();
        expect($gallery->expiration_date)->not->toBeNull();
    });

    it('does not require expiration date when gallery is public, even if not subscribed', function () {
        $gallery = Gallery::factory()->for($this->team)->public()->create(['expiration_date' => now()->addDays(5)]);
        expect($gallery->is_public)->toBeTrue();

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->set('form.expirationDate', '')
            ->call('update')
            ->assertHasNoErrors('form.expirationDate');

        $gallery->refresh();

        expect($gallery->expiration_date)->toBeNull();
    });
});

describe('Storage Limits', function () {
    it('increments storage_used when team uploads photo and has enough storage', function () {
        Storage::fake('s3');

        Event::fake(PhotoAdded::class);

        $this->team->update([
            'custom_storage_limit' => 100 * 1024 * 1024, // 100 MB
        ]);
        // Add a photo to simulate 10 MB used
        $gallery = Gallery::factory()->for($this->team)->create(['ulid' => 'STORAGEOK']);
        Photo::factory()->for($gallery)->create(['size' => 10 * 1024 * 1024]);

        $gallery = Gallery::factory()->for($this->team)->create(['ulid' => 'STORAGEOK']);

        $photoFile = UploadedFile::fake()->image('photo_upload.jpg', 1200, 800)->size(5 * 1024); // 5 MB
        $photoSize = $photoFile->getSize();
        $initialStorageUsed = $this->team->calculateStorageUsed();

        Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->set('photos.0', $photoFile)
            ->call('save', 0);

        expect($gallery->fresh()->photos()->count())->toBe(1);
        expect($this->team->calculateStorageUsed())->toBe($initialStorageUsed + $photoSize);
    });

    it('blocks photo upload when team storage limit would be exceeded', function () {
        Storage::fake('s3');

        Event::fake(PhotoAdded::class);

        $this->team->update([
            'custom_storage_limit' => 20 * 1024, // 20 KB
        ]);

        Photo::factory()->for(
            Gallery::factory()->for($this->team)
        )->create(['size' => 18 * 1024]); // 18 KB

        $gallery = Gallery::factory()->for($this->team)->create();

        $photoFile = UploadedFile::fake()->image('photo_upload.jpg', 1200, 800)->size(5 * 1024); // 5 KB
        $initialStorageUsed = $this->team->calculateStorageUsed();

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->set('photos.0', $photoFile)
            ->call('save', 0);

        expect($gallery->fresh()->photos()->count())->toBe(0);
        expect($this->team->calculateStorageUsed())->toBe($initialStorageUsed);
        $component->assertHasErrors(['photos.0' => 'You do not have enough storage space to upload this photo.']);
    });

    it('blocks photo upload when team is exactly at the storage limit', function () {
        Storage::fake('s3');

        Event::fake(PhotoAdded::class);

        $this->team->update([
            'custom_storage_limit' => 20 * 1024, // 20 KB
        ]);

        Photo::factory()->for(
            Gallery::factory()->for($this->team)
        )->create(['size' => 20 * 1024]); // 20 KB

        $gallery = Gallery::factory()->for($this->team)->create(['ulid' => 'STORAGEFULL']);

        $photoFile = UploadedFile::fake()->image('photo_upload.jpg', 1200, 800)->size(5 * 1024); // 5 KB
        $initialStorageUsed = $this->team->calculateStorageUsed();

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->set('photos.0', $photoFile)
            ->call('save', 0);

        expect($gallery->fresh()->photos()->count())->toBe(0);
        expect($this->team->calculateStorageUsed())->toBe($initialStorageUsed);
        $component->assertHasErrors(['photos.0' => 'You do not have enough storage space to upload this photo.']);
    });

    it('blocks photo upload when team is just under the storage limit', function () {
        Storage::fake('s3');

        Event::fake(PhotoAdded::class);

        $this->team->update([
            'custom_storage_limit' => 20 * 1024, // 20 KB
        ]);

        Photo::factory()->for(
            Gallery::factory()->for($this->team)
        )->create(['size' => 19 * 1024]); // 19 KB

        $gallery = Gallery::factory()->for($this->team)->create(['ulid' => 'STORAGEALMOSTFULL']);

        $photoFile = UploadedFile::fake()->image('photo_upload.jpg', 1200, 800)->size(2 * 1024); // 2 KB
        $initialStorageUsed = $this->team->calculateStorageUsed();

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->set('photos.0', $photoFile)
            ->call('save', 0);

        expect($gallery->fresh()->photos()->count())->toBe(0);
        expect($this->team->calculateStorageUsed())->toBe($initialStorageUsed);
        $component->assertHasErrors(['photos.0' => 'You do not have enough storage space to upload this photo.']);
    });

    it('does not count deleted photos towards storage usage', function () {
        Storage::fake('s3');
        Event::fake(PhotoAdded::class);

        $this->team->update([
            'custom_storage_limit' => 100 * 1024 * 1024, // 100 MB
        ]);
        // No photos yet, so storage used is 0
        $gallery = Gallery::factory()->for($this->team)->create(['ulid' => 'STORAGEDELETE']);

        $photoFile = UploadedFile::fake()->image('photo_delete.jpg', 1200, 800)->size(5 * 1024); // 5 KB
        $photoSize = $photoFile->getSize();

        Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->set('photos.0', $photoFile)
            ->call('save', 0);

        $storageAfterUpload = $this->team->calculateStorageUsed();
        expect($storageAfterUpload)->toBe($photoSize);

        $photo = $gallery->fresh()->photos()->first();
        Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->call('deletePhoto', $photo->id);

        expect($this->team->calculateStorageUsed())->toBe(0);
    });

    it('does not block photo upload for teams with unlimited storage regardless of usage', function () {
        Storage::fake('s3');
        Event::fake(PhotoAdded::class);

        $this->team->update([
            'custom_storage_limit' => null, // Unlimited storage
        ]);

        Photo::factory()->for(
            Gallery::factory()->for($this->team)
        )->create(['size' => 999999999]); // 999 MB

        $gallery = Gallery::factory()->for($this->team)->create(['ulid' => 'UNLIMITED']);

        $photoFile = UploadedFile::fake()->image('photo_upload.jpg', 1200, 800)->size(5 * 1024); // 5 MB
        $initialStorageUsed = $this->team->calculateStorageUsed();

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->set('photos.0', $photoFile)
            ->call('save', 0);

        expect($gallery->fresh()->photos()->count())->toBe(1);
        expect($this->team->calculateStorageUsed())->toBeGreaterThanOrEqual($initialStorageUsed);
        $component->assertHasNoErrors();
    });

    it('charges only the size difference when replacing the current version', function () {
        Storage::fake('s3');

        Event::fake(PhotoAdded::class);

        $this->team->update([
            'custom_storage_limit' => 3 * 1024, // 3 KB
        ]);

        $gallery = Gallery::factory()->for($this->team)->create(['ulid' => 'REPLACEKB']);
        Photo::factory()->for($gallery)->create(['name' => 'photo1.jpg', 'source_name' => 'photo1.jpg', 'size' => 2 * 1024]); // 2 KB

        // Replacing 2 KB with 2 KB needs no additional storage.
        Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->set('photos.0', UploadedFile::fake()->image('photo1.jpg', 100, 100)->size(2))
            ->call('save', 0, 'replace')
            ->assertHasNoErrors();

        // A 10 KB replacement exceeds the 3 KB limit.
        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->set('photos.0', UploadedFile::fake()->image('photo1.jpg', 100, 100)->size(10))
            ->call('save', 0, 'replace');

        $component->assertHasErrors(['photos.0' => 'You do not have enough storage space to upload this photo.']);
    });

    it('requires full storage when adding a new version', function () {
        Storage::fake('s3');

        Event::fake(PhotoAdded::class);

        $this->team->update([
            'custom_storage_limit' => 3 * 1024, // 3 KB
        ]);

        $gallery = Gallery::factory()->for($this->team)->create(['ulid' => 'VERSIONKB']);
        Photo::factory()->for($gallery)->create(['name' => 'photo1.jpg', 'source_name' => 'photo1.jpg', 'size' => 2 * 1024]);

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->set('photos.0', UploadedFile::fake()->image('photo1.jpg', 100, 100)->size(2))
            ->call('save', 0, 'version');

        $component->assertHasErrors(['photos.0' => 'You do not have enough storage space to upload this photo.']);
    });
});

describe('Cover Photo', function () {
    it('allows setting a photo as the gallery cover', function () {
        $gallery = Gallery::factory()->for($this->team)->create();
        $photo = Photo::factory()->for($gallery)->create();

        $component = Livewire::actingAs($this->user)->test('photo-item', ['photo' => $photo])
            ->call('setAsCover');

        expect($gallery->fresh()->cover_photo_id)->toBe($photo->id);
    });

    it('allows changing the cover photo', function () {
        $gallery = Gallery::factory()->for($this->team)->create();
        $photo1 = Photo::factory()->for($gallery)->create();
        $photo2 = Photo::factory()->for($gallery)->create();
        $gallery->update(['cover_photo_id' => $photo1->id]);

        $component = Livewire::actingAs($this->user)->test('photo-item', ['photo' => $photo2])
            ->call('setAsCover');

        expect($gallery->fresh()->cover_photo_id)->toBe($photo2->id);
    });

    it('allows removing the cover photo', function () {
        $gallery = Gallery::factory()->for($this->team)->create();
        $photo = Photo::factory()->for($gallery)->create();
        $gallery->update(['cover_photo_id' => $photo->id]);

        $component = Livewire::actingAs($this->user)->test('photo-item', ['photo' => $photo])
            ->call('removeAsCover');

        expect($gallery->fresh()->cover_photo_id)->toBeNull();
    });

    it('prevents setting cover photo from another team', function () {
        $gallery = Gallery::factory()->for($this->team)->create();
        $otherTeam = Team::factory()->create();
        $otherGallery = Gallery::factory()->for($otherTeam)->create();
        $photo = Photo::factory()->for($otherGallery)->create();

        $component = Livewire::actingAs($this->user)->test('photo-item', ['photo' => $photo])
            ->call('setAsCover');

        $component->assertStatus(403);
        expect($gallery->fresh()->cover_photo_id)->toBeNull();
    });

    it('stores the cover focal point clamped to the image bounds', function () {
        $gallery = Gallery::factory()->for($this->team)->create();
        $photo = Photo::factory()->for($gallery)->create();
        $gallery->setCoverPhoto($photo);

        Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->call('setCoverFocalPoint', 0.25, 1.5);

        expect($gallery->fresh()->cover_focal_x)->toBe(0.25)
            ->and($gallery->fresh()->cover_focal_y)->toBe(1.0);
    });

    it('opens the cover focus overlay after selecting a cover', function () {
        $gallery = Gallery::factory()->for($this->team)->create();
        $photo = Photo::factory()->for($gallery)->create();

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->call('setAsCover', $photo);

        expect($component->get('adjustingCover'))->toBeTrue()
            ->and($component->html())->toContain('Adjust cover focus')
            ->and($component->html())->toContain(str_replace('&', '&amp;', $photo->large_thumbnail_url));
    });
});

describe('Live Session Original Size', function () {
    it('keeps the original size switch in sync with the gallery', function () {
        $gallery = Gallery::factory()->for($this->team)->create(['keep_original_size' => true]);

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery]);

        expect($component->get('keepOriginalSize'))->toBeTrue();
    });

    it('syncs the edit gallery modal with the camera upload modal', function () {
        $gallery = Gallery::factory()->for($this->team)->create(['keep_original_size' => true]);

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->set('keepOriginalSize', false);

        expect($gallery->fresh()->keep_original_size)->toBeFalse();
        expect($component->get('form')->keepOriginalSize)->toBeFalse();
    });

    it('syncs the camera upload modal when the gallery is edited', function () {
        $gallery = Gallery::factory()->for($this->team)->create([
            'keep_original_size' => true,
            'expiration_date' => now()->addMonth()->startOfDay(),
        ]);

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->set('form.keepOriginalSize', false)
            ->call('update')
            ->assertHasNoErrors();

        expect($gallery->fresh()->keep_original_size)->toBeFalse();
        expect($component->get('keepOriginalSize'))->toBeFalse();
    });

    it('stores the choice on the gallery and remembers it for new galleries', function () {
        $gallery = Gallery::factory()->for($this->team)->create(['keep_original_size' => false]);

        Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->set('keepOriginalSize', true);

        expect($gallery->fresh()->keep_original_size)->toBeTrue();
        expect($this->user->fresh()->galleryDefaults()['keep_original_size'])->toBeTrue();
    });
});

describe('Gallery Public Access', function () {
    it('dispatches photo processing and disables keep_original_size when toggling gallery to public', function () {
        Queue::fake();

        $gallery = Gallery::factory()->for($this->team)->create(['keep_original_size' => true]);
        $photos = Photo::factory()->for($gallery)->count(3)->create();

        Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->call('togglePublic');

        $gallery->refresh();

        expect($gallery->is_public)->toBeTrue();
        expect($gallery->keep_original_size)->toBeFalse();

        foreach ($photos as $photo) {
            Queue::assertPushed(ProcessPhoto::class, function ($job) use ($photo) {
                return $job->photo->is($photo);
            });
        }
    });

    it('disables expiration when toggling gallery to public', function () {
        $gallery = Gallery::factory()->for($this->team)->create(['expiration_date' => now()->addDays(5)]);
        expect($gallery->is_public)->toBeFalse();
        expect($gallery->expiration_date)->not->toBeNull();

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->call('togglePublic');

        $gallery->refresh();
        expect($gallery->is_public)->toBeTrue();
        expect($gallery->expiration_date)->toBeNull();
    });

    it('allows marking a gallery as public', function () {
        $gallery = Gallery::factory()->for($this->team)->create();
        expect($gallery->is_public)->toBeFalse();

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->call('togglePublic');

        expect($gallery->fresh()->is_public)->toBeTrue();
    });

    it('allows marking a public gallery as private', function () {
        $gallery = Gallery::factory()->for($this->team)->public()->create();
        expect($gallery->is_public)->toBeTrue();

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->call('togglePublic');

        expect($gallery->fresh()->is_public)->toBeFalse();
    });

    it('sets expiration date to one month when toggling gallery from public to private', function () {
        $gallery = Gallery::factory()->for($this->team)->public()->create([
            'expiration_date' => null,
        ]);

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->call('togglePublic');

        $gallery->refresh();
        expect($gallery->is_public)->toBeFalse();
        expect($gallery->expiration_date->toDateString())->toBe(now()->addMonth()->toDateString());
    });

    it('allows guests to view public galleries via public route', function () {
        $gallery = Gallery::factory()->for($this->team)->public()->create(['ulid' => 'PUBLICGALLERY']);
        $photo = Photo::factory()->for($gallery)->create();

        $response = get('/@'.$this->team->handle.'/portfolio/'.$gallery->ulid);

        $response->assertStatus(200);
    });

    it('prevents guests from viewing private galleries via public route', function () {
        $gallery = Gallery::factory()->for($this->team)->create(['ulid' => 'PRIVATEGALLERY']);
        expect($gallery->is_public)->toBeFalse();

        $response = get('/@'.$this->team->handle.'/portfolio/'.$gallery->ulid);

        $response->assertStatus(404);
    });
});

describe('Portfolio Photo Public Access', function () {
    it('allows guests to view public portfolio photos', function () {
        $gallery = Gallery::factory()->for($this->team)->public()->create(['ulid' => 'PUBLICGALLERY']);
        $photo = Photo::factory()->for($gallery)->create();

        $response = get('/@'.$this->team->handle.'/portfolio/'.$gallery->ulid.'/photos/'.$photo->id);

        $response->assertStatus(200);
    });

    it('prevents guests from viewing private portfolio photos', function () {
        $gallery = Gallery::factory()->for($this->team)->create(['ulid' => 'PRIVATEGALLERY']);
        $photo = Photo::factory()->for($gallery)->create();
        expect($gallery->is_public)->toBeFalse();

        $response = get('/@'.$this->team->handle.'/portfolio/'.$gallery->ulid.'/photos/'.$photo->id);

        $response->assertStatus(404);
    });
});

describe('Portfolio Index Public Access', function () {
    it('allows guests to view portfolio index with public galleries', function () {
        $publicGallery = Gallery::factory()->for($this->team)->public()->create(['name' => 'Public Gallery']);
        $privateGallery = Gallery::factory()->for($this->team)->create(['name' => 'Private Gallery']);
        Photo::factory()->for($publicGallery)->create();

        $response = get('/@'.$this->team->handle.'/portfolio');

        $response->assertStatus(200);
        $response->assertSee($publicGallery->name);
        $response->assertDontSee($privateGallery->name);
    });

    it('returns 404 for non-existent team handle', function () {
        $response = get('/@nonexistent/portfolio');

        $response->assertStatus(404);
    });
});

describe('Live Camera Upload', function () {
    it('opens the live upload modal when arriving with the live flag', function () {
        Subscription::factory()->for($this->user->currentTeam, 'owner')->create();

        $gallery = Gallery::factory()->for($this->team)->create();
        FtpAccount::factory()->for($this->team)->create(['active_gallery_id' => $gallery->id]);

        Livewire::actingAs($this->user)->withQueryParams(['live' => '1'])
            ->test('pages::galleries.show', ['gallery' => $gallery])
            ->assertHasNoErrors()
            ->assertSee('Camera setup');
    });

    it('offers camera upload to free teams too', function () {
        config(['picstome.ftp_uploads.host' => 'upload.picstome.com']);

        $gallery = Gallery::factory()->for($this->team)->create();

        Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->assertDontSee('Unlock camera upload')
            ->assertSee('Camera upload');
    });

    it('provisions a camera account for a free team', function () {
        config(['picstome.ftp_uploads.sftpgo_url' => null]);

        $gallery = Gallery::factory()->for($this->team)->create();

        Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->call('toggleFtpUploads');

        expect(FtpAccount::first())->not->toBeNull()
            ->and(FtpAccount::first()->routesTo($gallery))->toBeTrue();
    });

    it('does not create an ftp account when the team has no storage left', function () {
        $this->team->update(['custom_storage_limit' => 0]);

        $gallery = Gallery::factory()->for($this->team)->create();

        Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->call('toggleFtpUploads');

        expect(FtpAccount::count())->toBe(0);
    });

    it('provisions a team account and routes uploads to the gallery when subscribed', function () {
        config(['picstome.ftp_uploads.sftpgo_url' => null]);

        Subscription::factory()->for($this->user->currentTeam, 'owner')->create();

        $gallery = Gallery::factory()->for($this->team)->create();

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->call('toggleFtpUploads');

        tap(FtpAccount::first(), function ($account) use ($gallery) {
            expect($account)->not->toBeNull();
            expect($account->team_id)->toBe($this->team->id);
            expect($account->routesTo($gallery))->toBeTrue();
            expect($account->username)->toMatch('/^\d{6}$/');
            expect($account->password)->toMatch('/^\d{8}$/');
        });

        $component->assertSee(FtpAccount::first()->username);
    });

    it('stops routing uploads when toggled again without touching the credentials', function () {
        Subscription::factory()->for($this->user->currentTeam, 'owner')->create();

        $gallery = Gallery::factory()->for($this->team)->create();
        $account = FtpAccount::factory()->for($this->team)->create(['active_gallery_id' => $gallery->id]);

        Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->call('toggleFtpUploads');

        expect($account->fresh()->active)->toBeTrue();
        expect($account->fresh()->active_gallery_id)->toBeNull();
    });

    it('shows which gallery is receiving uploads when it is another one', function () {
        Subscription::factory()->for($this->user->currentTeam, 'owner')->create();

        $gallery = Gallery::factory()->for($this->team)->create();
        $otherGallery = Gallery::factory()->for($this->team)->create(['name' => 'Other Gallery']);
        FtpAccount::factory()->for($this->team)->create(['active_gallery_id' => $otherGallery->id]);

        Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->assertSee('Other Gallery');
    });

    it('rolls back the account when the sftpgo server cannot be reached', function () {
        config([
            'picstome.ftp_uploads.sftpgo_url' => 'https://sftpgo.test',
            'picstome.ftp_uploads.sftpgo_api_key' => 'secret-key',
        ]);

        Http::fake([
            'sftpgo.test/*' => Http::response('boom', 500),
        ]);

        Subscription::factory()->for($this->user->currentTeam, 'owner')->create();

        $gallery = Gallery::factory()->for($this->team)->create();

        Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->call('toggleFtpUploads');

        expect(FtpAccount::count())->toBe(0);
    });
});

test('the photographer gallery refreshes when the watcher detects a new photo', function () {
    $gallery = Gallery::factory()->for($this->team)->create();

    $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery]);

    expect($component->html())->toContain('wire:poll.5s');

    $photo = Photo::factory()->for($gallery)->create();

    $component->call('refreshPhotos');

    expect($component->allPhotos->contains($photo))->toBeTrue();
});

test('owner gallery renders a bounded page and loads more photos on demand', function () {
    $gallery = Gallery::factory()->for($this->team)->create();
    Photo::factory()->count(65)->for($gallery)->create(['status' => 'processed']);

    $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery]);

    expect($component->allPhotos)->toHaveCount(60)
        ->and(substr_count($component->html(), 'data-photo-id='))->toBe(60)
        ->and($component->html())->toContain('x-on:scroll.window.throttle.150ms')
        ->and($component->html())->not->toContain('wire:name="photo-item"');

    $component->call('loadMore', 'all');

    expect($component->allPhotos)->toHaveCount(65);
});

test('owner gallery reveals the photo referenced by the viewer back link', function () {
    $gallery = Gallery::factory()->for($this->team)->create();
    Photo::factory()->count(65)->for($gallery)->sequence(fn ($sequence) => [
        'name' => 'photo-'.($sequence->index + 1).'.jpg',
    ])->create(['status' => 'processed']);

    $target = $gallery->photos()->where('name', 'photo-65.jpg')->firstOrFail();

    $component = Livewire::actingAs($this->user)
        ->withQueryParams(['photo' => $target->id])
        ->test('pages::galleries.show', ['gallery' => $gallery]);

    expect($component->allPhotos)->toHaveCount(65)
        ->and($component->allPhotos->contains($target))->toBeTrue()
        ->and($component->get('allPhotoLimit'))->toBe(120);
});

test('owner gallery reveals a favorited photo referenced by the viewer back link', function () {
    $gallery = Gallery::factory()->for($this->team)->create();
    Photo::factory()->count(65)->for($gallery)->favorited()->sequence(fn ($sequence) => [
        'name' => 'photo-'.($sequence->index + 1).'.jpg',
    ])->create(['status' => 'processed']);

    $target = $gallery->photos()->where('name', 'photo-65.jpg')->firstOrFail();

    $component = Livewire::actingAs($this->user)
        ->withQueryParams(['activeTab' => 'favorited', 'photo' => $target->id])
        ->test('pages::galleries.show', ['gallery' => $gallery]);

    expect($component->get('activeTab'))->toBe('all')
        ->and($component->get('favoritesOnly'))->toBeTrue()
        ->and($component->allPhotos)->toHaveCount(65)
        ->and($component->allPhotos->contains($target))->toBeTrue()
        ->and($component->get('allPhotoLimit'))->toBe(120);
});

test('going back from a photo beyond the first page scrolls to it', function () {
    $gallery = Gallery::factory()->for($this->team)->create();
    Photo::factory()->count(65)->for($gallery)->sequence(fn ($sequence) => [
        'name' => 'photo-'.($sequence->index + 1).'.jpg',
    ])->create(['status' => 'processed']);

    $target = $gallery->photos()->where('name', 'photo-65.jpg')->firstOrFail();

    $this->actingAs($this->user);

    $page = visit('/galleries/'.$gallery->id.'/photos/'.$target->id);
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

it('returns the uploaded photo id and status so the modal can follow processing', function () {
    Storage::fake('s3');
    Event::fake(PhotoAdded::class);

    $gallery = Gallery::factory()->for($this->team)->create();

    $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
        ->set('photos.0', UploadedFile::fake()->image('photo1.jpg'))
        ->call('save', 0);

    $component->assertReturned(function ($result) {
        return is_array($result)
            && isset($result['photoId'], $result['status'])
            && $result['status'] === 'pending';
    });
});

it('returns upload statuses only for photos in the current gallery', function () {
    $gallery = Gallery::factory()->for($this->team)->create();
    $photo = Photo::factory()->for($gallery)->create(['status' => 'warming']);
    $other = Photo::factory()->for(Gallery::factory())->create(['status' => 'processing']);

    $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery]);

    expect($component->call('uploadStatuses', [$photo->id, $other->id])->effects['returns'][0])
        ->toBe([$photo->id => 'warming']);
});

test('owner gallery loads the next batch automatically near the bottom', function () {
    $gallery = Gallery::factory()->for($this->team)->create();
    Photo::factory()->count(65)->for($gallery)->create(['status' => 'processed']);

    $this->actingAs($this->user);

    $page = visit('/galleries/'.$gallery->id);
    $page->wait(0.5);

    $page->assertNoJavaScriptErrors();

    expect($page->script("document.querySelectorAll('[data-photo-card]').length"))->toBe(60);

    $page->script('window.scrollTo(0, document.body.scrollHeight)');
    $page->wait(4);

    expect($page->script("document.querySelectorAll('[data-photo-card]').length"))->toBe(65);
})->group('browser');

test('owner can switch between photos and download tabs', function () {
    $gallery = Gallery::factory()->for($this->team)->create();
    Photo::factory()->for($gallery)->create(['status' => 'processed']);

    $this->actingAs($this->user);

    $page = visit('/galleries/'.$gallery->id);
    $page->wait(0.5);

    $page->click('[role=tablist] button[aria-label="Download"]');
    $page->wait(0.5);

    $page->assertSee('Download gallery');

    $page->click('[role=tablist] button[aria-label="Photos"]');
    $page->wait(0.5);

    expect($page->script("document.querySelectorAll('[data-photo-card]').length"))->toBe(1);
})->group('browser');

it('enables comments when annotations are enabled in the share modal', function () {
    Subscription::factory()->for($this->user->currentTeam, 'owner')->create();
    $gallery = Gallery::factory()->for($this->team)->create();

    $this->actingAs($this->user);

    visit('/galleries/'.$gallery->id)
        ->click('@open-share-modal')
        ->click('@share-comments-toggle')
        ->assertNotChecked('@share-comments-toggle')
        ->click('@share-annotations-toggle')
        ->assertChecked('@share-comments-toggle')
        ->assertChecked('@share-annotations-toggle')
        ->assertNoJavascriptErrors();
})->group('browser');

test('the owner can open their own password-protected shared gallery', function () {
    $gallery = Gallery::factory()->shared()->protected()->for($this->team)->create();

    $this->actingAs($this->user)
        ->get('/galleries/'.$gallery->id)
        ->assertOk();
});

it('rejects a file in the modal without uploading it when the team has no storage left', function () {
    Storage::fake('s3');

    $this->team->update(['custom_storage_limit' => 0]);

    $gallery = Gallery::factory()->for($this->team)->create();

    $this->actingAs($this->user);

    $page = visit('/galleries/'.$gallery->id);

    $page->click('Add media');

    // The browser runs on a remote Playwright server, so local file paths
    // cannot be attached. Dispatch a real File through the input instead.
    $page->script(<<<'JS'
        const input = document.querySelector('#add-photos-uploader input[type=file]');
        const file = new File([new Uint8Array(200 * 1024)], 'quota.jpg', { type: 'image/jpeg' });
        const data = new DataTransfer();
        data.items.add(file);
        input.files = data.files;
        input.dispatchEvent(new Event('change', { bubbles: true }));
    JS);

    $page->assertSee('You do not have enough storage space to upload this photo.')
        ->assertNoJavascriptErrors();

    expect($gallery->fresh()->photos()->count())->toBe(0);
})->group('browser');
