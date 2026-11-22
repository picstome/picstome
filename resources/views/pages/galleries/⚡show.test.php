<?php

use App\Jobs\ProcessPhoto;
use App\Models\Gallery;
use App\Models\Photo;
use App\Models\PhotoComment;
use App\Models\PhotoVersion;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
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
        $page->resize(1280, 800);

        waitForBrowserValue($page, <<<'JS'
            return document.querySelector('[x-tooltip*="This gallery is public"]') ? 1 : null;
        JS, 10000);

        $page->hover('[x-tooltip*="This gallery is public"]');

        $tooltip = waitForBrowserValue($page, <<<'JS'
            return document.querySelector('[data-tsui-tooltip]')?.innerText.trim() ?? null;
        JS, 10000);

        expect($tooltip)->toContain('This gallery is public and visible in your portfolio section.');
    })->group('browser');

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

        Livewire::actingAs($this->user)
            ->test('pages::galleries.show', ['gallery' => $gallery])
            ->call('setAsCover', $photo->id)
            ->assertDispatched('gallery-cover-changed');

        expect($gallery->fresh()->cover_photo_id)->toBe($photo->id);
    });

    it('keeps only cover, delete and favorite actions on the photo tile', function () {
        Storage::fake('s3');

        $gallery = Gallery::factory()->for($this->team)->create();
        Photo::factory()->for($gallery)->create(['status' => 'processed']);

        $this->actingAs($this->user);

        $page = visit("/galleries/{$gallery->id}");
        $page->hover('[data-photo-card]');

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
    })->group('browser');

    it('centres the tile actions dock at the bottom of the thumbnail', function () {
        Storage::fake('s3');

        $gallery = Gallery::factory()->for($this->team)->create();
        Photo::factory()->for($gallery)->create(['status' => 'processed']);

        $this->actingAs($this->user);

        $page = visit("/galleries/{$gallery->id}");
        $page->hover('[data-photo-card]');

        waitForBrowserValue($page, <<<'JS'
            const card = document.querySelector('[data-photo-card]');
            const dock = card?.querySelector('.rounded-full');

            return dock && parseFloat(getComputedStyle(dock).opacity) > 0 ? 1 : null;
        JS, 10000);

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
    })->group('browser');

    it('opens the favorite list from the actions menu', function () {
        $gallery = Gallery::factory()->for($this->team)->create();
        Photo::factory()->for($gallery)->favorited()->create();

        $this->actingAs($this->user);

        $page = visit("/galleries/{$gallery->id}");

        $page->click('[data-gallery-actions]');

        waitForBrowserValue($page, <<<'JS'
            return document.querySelector('[data-export-favorites-menu]') ? 1 : null;
        JS, 10000);

        $page->click('[data-export-favorites-menu]');

        $opened = waitForBrowserValue($page, <<<'JS'
            const modal = document.querySelector('#favorite-list');

            return modal && getComputedStyle(modal).display !== 'none' ? 1 : null;
        JS, 10000);

        expect($opened)->toBe(1);

        $page->click('Finder/Explorer');

        $filter = waitForBrowserValue($page, <<<'JS'
            return document.getElementById('finder-textarea')?.value ? 1 : null;
        JS, 10000);

        expect($filter)->toBe(1);
    })->group('browser');

    it('opens the favorite list from the export icon in the tab bar', function () {
        $gallery = Gallery::factory()->for($this->team)->create();
        Photo::factory()->for($gallery)->favorited()->create();

        $this->actingAs($this->user);

        $page = visit("/galleries/{$gallery->id}");
        $page->click('[data-export-favorites]');

        $opened = waitForBrowserValue($page, <<<'JS'
            const modal = document.querySelector('#favorite-list');

            return modal && getComputedStyle(modal).display !== 'none' ? 1 : null;
        JS, 10000);

        expect($opened)->toBe(1);
    })->group('browser');

    it('opens the favorites tab from the url', function () {
        $gallery = Gallery::factory()->for($this->team)->create();
        Photo::factory()->for($gallery)->favorited()->create();

        Livewire::actingAs($this->user)
            ->withQueryParams(['activeTab' => 'favorited'])
            ->test('pages::galleries.show', ['gallery' => $gallery])
            ->assertSet('activeTab', 'all')
            ->assertSet('favoritesOnly', true);
    });

    it('applies the favorites filter from the favorites tab', function () {
        $gallery = Gallery::factory()->for($this->team)->create();
        Photo::factory()->for($gallery)->favorited()->create(['name' => 'a.jpg']);
        Photo::factory()->for($gallery)->create(['name' => 'b.jpg']);

        $component = Livewire::actingAs($this->user)
            ->test('pages::galleries.show', ['gallery' => $gallery])
            ->call('showFavorites')
            ->assertSet('activeTab', 'all')
            ->assertSet('favoritesOnly', true);

        expect($component->instance()->filteredPhotoCount)->toBe(1);
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
    })->group('browser');

    it('accepts files dropped anywhere on the gallery page', function () {
        $gallery = Gallery::factory()->for($this->team)->create();
        Photo::factory()->for($gallery)->create(['status' => 'processed']);

        $this->actingAs($this->user);

        $page = visit("/galleries/{$gallery->id}");

        waitForAlpineRoot($page, '[x-data="galleryDrop"]');

        waitForBrowserValue($page, <<<'JS'
            return window.PicstomeUploads ? 1 : null;
        JS, 10000);

        $page->script(<<<'JS'
            (() => {
                const dataTransfer = new DataTransfer();
                dataTransfer.items.add(new File(['x'], 'outside-drop.jpg', { type: 'image/jpeg' }));

                document.body.dispatchEvent(new DragEvent('dragover', {
                    bubbles: true,
                    cancelable: true,
                    dataTransfer,
                }));
            })()
        JS);

        $overlayVisible = waitForBrowserValue($page, <<<'JS'
            return document.querySelector('[data-test=upload-drop-overlay]')
                && getComputedStyle(document.querySelector('[data-test=upload-drop-overlay]')).display !== 'none'
                ? 1
                : null;
        JS, 10000);

        expect($overlayVisible)->toBe(1);

        $page->script(<<<'JS'
            (() => {
                const dataTransfer = new DataTransfer();
                dataTransfer.items.add(new File(['x'], 'outside-drop.jpg', { type: 'image/jpeg' }));

                const event = new Event('drop', { bubbles: true, cancelable: true });
                Object.defineProperty(event, 'dataTransfer', { value: dataTransfer });
                window.dispatchEvent(event);
            })()
        JS);

        waitForBrowserValue($page, <<<'JS'
            return window.PicstomeUploads.entries.length > 0 ? 1 : null;
        JS, 10000);

        $page->assertVisible('@upload-dock')
            ->assertSee('outside-drop.jpg')
            ->assertNoJavascriptErrors();
    })->group('browser');

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
            ->submit([
                'allPhotoLimit' => 120,
            ], 'filterByRating', 4)
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

        waitForBrowserValue($page, <<<'JS'
            const popover = document.querySelector('[data-rating-filter-popover]');

            return popover && getComputedStyle(popover).display !== 'none' ? 1 : null;
        JS, 10000);

        $page->click('[data-rating-filter-popover] [data-rating-star="5"]');

        $count = waitForBrowserValue($page, <<<'JS'
            return document.querySelectorAll('[data-photo-card]').length === 1 ? 1 : null;
        JS, 10000);

        expect($count)->toBe(1);
    })->group('browser');
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
