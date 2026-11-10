<?php

use App\Http\Resources\Api\V1\PhotoResource;
use App\Models\Gallery;
use App\Models\Photo;
use App\Models\PhotoComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->withPersonalTeam()->create();
    $this->team = $this->user->currentTeam;
});

/*
 * Photo archiving (#247)
 *
 * Archiving is a photographer-only tool: it hides photos from clients and from
 * the gallery ZIP without deleting anything. Clients must never see the button,
 * the archived tab or an archived photo.
 */

it('archives and unarchives a photo', function () {
    $gallery = Gallery::factory()->for($this->team)->create();
    $photo = Photo::factory()->for($gallery)->create();

    expect($photo->isArchived())->toBeFalse();

    $photo->archive();
    expect($photo->fresh()->isArchived())->toBeTrue()
        ->and($photo->fresh()->archived_at)->not->toBeNull();

    $photo->unarchive();
    expect($photo->fresh()->isArchived())->toBeFalse()
        ->and($photo->fresh()->archived_at)->toBeNull();
});

it('hides archived photos from the live gallery lists', function () {
    $gallery = Gallery::factory()->for($this->team)->create();
    $live = Photo::factory()->for($gallery)->create();
    $archived = Photo::factory()->for($gallery)->create();

    $archived->archive();

    expect($gallery->photoIds())->toContain($live->id)
        ->and($gallery->photoIds())->not->toContain($archived->id)
        ->and($gallery->readyPhotoIds())->not->toContain($archived->id)
        ->and($gallery->archivedPhotoIds())->toContain($archived->id);
});

it('keeps favorite and commented tabs free of archived photos', function () {
    $gallery = Gallery::factory()->for($this->team)->create();

    $favorite = Photo::factory()->for($gallery)->favorited()->create();
    $archivedFavorite = Photo::factory()->for($gallery)->favorited()->create();
    $archivedFavorite->archive();

    $commented = Photo::factory()->for($gallery)->create();
    PhotoComment::factory()->for($commented)->create();

    $archivedCommented = Photo::factory()->for($gallery)->create();
    PhotoComment::factory()->for($archivedCommented)->create();
    $archivedCommented->archive();

    expect($gallery->favoritePhotoIds())->toContain($favorite->id)
        ->and($gallery->favoritePhotoIds())->not->toContain($archivedFavorite->id)
        ->and($gallery->commentedPhotoIds())->toContain($commented->id)
        ->and($gallery->commentedPhotoIds())->not->toContain($archivedCommented->id);
});

it('never shows archived photos to clients on the shared viewer', function () {
    $gallery = Gallery::factory()->for($this->team)->shared()->create(['ulid' => '0123ARCH']);
    $live = Photo::factory()->for($gallery)->create();
    $archived = Photo::factory()->for($gallery)->create();

    $archived->archive();

    $component = Livewire::test('pages::shares.show', ['gallery' => $gallery, 'slug' => $gallery->slug]);

    expect($component->instance()->photoIds)->toContain($live->id)
        ->and($component->instance()->photoIds)->not->toContain($archived->id);
});

it('excludes archived photos from the gallery download size and media counts', function () {
    $gallery = Gallery::factory()->for($this->team)->create();
    $live = Photo::factory()->for($gallery)->create(['size' => 2048]);
    $archived = Photo::factory()->for($gallery)->create(['size' => 4096]);

    $archived->archive();

    expect($gallery->mediaCounts()['all'])->toBe(1)
        ->and($gallery->mediaCounts()['images'])->toBe(1);
});

it('renders the archive button next to delete in the owner viewer, never for clients', function () {
    $gallery = Gallery::factory()->for($this->team)->create();
    $photo = Photo::factory()->for($gallery)->create();

    actingAs($this->user)
        ->get(route('galleries.photos.show', ['gallery' => $gallery, 'photo' => $photo]))
        ->assertOk()
        ->assertSeeHtml('data-photo-archive')
        ->assertSeeHtml('data-photo-delete')
        ->assertSee('Archive');

    $shareGallery = Gallery::factory()->for($this->team)->shared()->create();
    $sharedPhoto = Photo::factory()->for($shareGallery)->create();

    $this->get(route('shares.photos.show', ['gallery' => $shareGallery, 'photo' => $sharedPhoto]))
        ->assertOk()
        ->assertDontSeeHtml('data-photo-archive')
        ->assertDontSee('Archive');
});

it('shows the archived tab only when something is archived', function () {
    $gallery = Gallery::factory()->for($this->team)->create();
    $photo = Photo::factory()->for($gallery)->create();

    $component = Livewire::actingAs($this->user)
        ->test('pages::galleries.show', ['gallery' => $gallery]);

    expect($component->html())->not->toContain('data-archived-tab');

    $photo->archive();
    $gallery->unsetRelation('photos');

    $component = Livewire::actingAs($this->user)
        ->test('pages::galleries.show', ['gallery' => $gallery]);

    expect($component->html())->toContain('data-archived-tab')
        ->and($component->instance()->totalArchived)->toBe(1);
});

it('asks for confirmation before deleting all archived photos', function () {
    $gallery = Gallery::factory()->for($this->team)->create();
    $archived = Photo::factory()->for($gallery)->create();
    $live = Photo::factory()->for($gallery)->create();

    $archived->archive();

    $component = Livewire::actingAs($this->user)
        ->test('pages::galleries.show', ['gallery' => $gallery])
        ->call('requestDeleteArchived');

    // Requesting only opens the modal: nothing is deleted yet.
    expect($component->instance()->pendingArchivedCount)->toBe(1)
        ->and(Photo::find($archived->id))->not->toBeNull()
        ->and(Photo::find($live->id))->not->toBeNull();
});

it('deletes only the archived photos once confirmed', function () {
    $gallery = Gallery::factory()->for($this->team)->create();
    $archived = Photo::factory()->for($gallery)->create();
    $live = Photo::factory()->for($gallery)->create();

    $archived->archive();

    Livewire::actingAs($this->user)
        ->test('pages::galleries.show', ['gallery' => $gallery])
        ->call('deleteArchived');

    expect(Photo::find($archived->id))->toBeNull()
        ->and(Photo::find($live->id))->not->toBeNull();
});

it('exposes archived_at in the external API but still lists the photo', function () {
    $gallery = Gallery::factory()->for($this->team)->create();
    $photo = Photo::factory()->for($gallery)->create();
    $photo->archive();

    $resource = (new PhotoResource($photo->fresh()))->resolve(request());

    expect($resource)->toHaveKey('archived_at')
        ->and($resource['archived_at'])->not->toBeNull()
        ->and($resource['filename'])->toBe($photo->name);
});

it('returns 404 for the shared photo page of an archived photo', function () {
    $gallery = Gallery::factory()->for($this->team)->shared()->create(['ulid' => '0123ARCH']);
    $photo = Photo::factory()->for($gallery)->create();

    $photo->archive();

    $this->get(route('shares.photos.show', ['gallery' => $gallery, 'photo' => $photo]))
        ->assertNotFound();
});

it('returns 404 for the public portfolio page of an archived photo', function () {
    $this->team->update(['handle' => 'archstudio']);

    $gallery = Gallery::factory()->for($this->team)->create(['is_public' => true]);
    $photo = Photo::factory()->for($gallery)->create();

    $photo->archive();

    $this->get(route('portfolio.photos.show', [
        'handle' => $this->team->handle,
        'gallery' => $gallery,
        'photo' => $photo,
    ]))->assertNotFound();
});

it('keeps archived photos in the shared download of the whole gallery out', function () {
    $gallery = Gallery::factory()->for($this->team)->shared()->downloadable()->create(['ulid' => '0123ARCH']);
    $photo = Photo::factory()->for($gallery)->create();

    $photo->archive();

    $this->get(route('shares.photos.download', ['gallery' => $gallery, 'photo' => $photo]))
        ->assertNotFound();
});

it('pages through the archived set without leaving it', function () {
    $gallery = Gallery::factory()->for($this->team)->create();
    $live = Photo::factory()->for($gallery)->create(['name' => 'a.jpg']);
    $first = Photo::factory()->for($gallery)->create(['name' => 'b.jpg']);
    $second = Photo::factory()->for($gallery)->create(['name' => 'c.jpg']);

    $first->archive();
    $second->archive();

    $component = Livewire::actingAs($this->user)->test('pages::galleries.photos.show', [
        'photo' => $first->fresh(),
        'navigateArchived' => true,
    ]);

    expect($component->instance()->next?->id)->toBe($second->id)
        ->and($component->instance()->previous?->id)->toBeNull();

    expect($component->instance()->next?->id)->not->toBe($live->id);
});

it('restores an archived photo and advances to the next archived one', function () {
    $gallery = Gallery::factory()->for($this->team)->create();
    $first = Photo::factory()->for($gallery)->create(['name' => 'a.jpg']);
    $second = Photo::factory()->for($gallery)->create(['name' => 'b.jpg']);
    $third = Photo::factory()->for($gallery)->create(['name' => 'c.jpg']);

    foreach ([$first, $second, $third] as $photo) {
        $photo->archive();
    }

    // Same state as opening a photo from the Archived tab.
    $component = Livewire::actingAs($this->user)->test('pages::galleries.photos.show', [
        'photo' => $first->fresh(),
        'navigateArchived' => true,
    ]);

    expect($component->instance()->next?->id)->toBe($second->id);

    $component->call('archive');

    // A on an archived photo restores it...
    expect($first->fresh()->isArchived())->toBeFalse()
        // ...and pages on to the next archived one, staying in the archived set.
        ->and($second->fresh()->isArchived())->toBeTrue()
        ->and($third->fresh()->isArchived())->toBeTrue();
});

it('archives a live photo and advances to the next live one', function () {
    $gallery = Gallery::factory()->for($this->team)->create();
    $first = Photo::factory()->for($gallery)->create(['name' => 'a.jpg']);
    $second = Photo::factory()->for($gallery)->create(['name' => 'b.jpg']);

    $component = Livewire::actingAs($this->user)->test('pages::galleries.photos.show', [
        'photo' => $first->fresh(),
    ]);

    expect($component->instance()->next?->id)->toBe($second->id);

    $component->call('archive');

    expect($first->fresh()->isArchived())->toBeTrue()
        ->and($second->fresh()->isArchived())->toBeFalse();
});

it('shows the archived tab right after archiving with a warm cache', function () {
    $gallery = Gallery::factory()->for($this->team)->create();
    $photo = Photo::factory()->for($gallery)->create();

    // Prime every gallery cache the tabs read from, as a page load would.
    $gallery->photoIds();
    $gallery->readyPhotoIds();
    $gallery->favoritePhotoIds();
    $gallery->commentedPhotoIds();
    $gallery->archivedPhotoIds();
    $gallery->mediaCounts();

    expect($gallery->archivedPhotoIds())->toHaveCount(0);

    Livewire::actingAs($this->user)
        ->test('pages::galleries.show', ['gallery' => $gallery])
        ->call('toggleArchive', $photo->id)
        ->assertSeeHtml('data-archived-tab');
});

it('reflects an archive made outside the request immediately', function () {
    $gallery = Gallery::factory()->for($this->team)->create();
    $photo = Photo::factory()->for($gallery)->create();

    expect($gallery->archivedPhotoIds())->toHaveCount(0);

    // Archived from another process (a queue job, a second browser tab): the
    // archived list must not be served from a stale cache.
    (new Photo)->newQuery()->whereKey($photo->id)->first()->archive();

    expect($gallery->archivedPhotoIds())->toHaveCount(1)
        ->and($gallery->photoIds())->toHaveCount(0);
});

it('shows the archived tab late-cache free after archiving twice in a row', function () {
    $gallery = Gallery::factory()->for($this->team)->create();
    $first = Photo::factory()->for($gallery)->create();
    $second = Photo::factory()->for($gallery)->create();

    Livewire::actingAs($this->user)
        ->test('pages::galleries.show', ['gallery' => $gallery])
        ->call('toggleArchive', $first->id)
        ->call('toggleArchive', $second->id)
        ->assertSeeHtml('Archived (2)');
});

it('returns to All photos after restoring the last archived photo', function () {
    $gallery = Gallery::factory()->for($this->team)->create();
    $only = Photo::factory()->for($gallery)->create(['name' => 'a.jpg']);
    $live = Photo::factory()->for($gallery)->create(['name' => 'b.jpg']);

    $only->archive();

    $component = Livewire::actingAs($this->user)->test('pages::galleries.photos.show', [
        'photo' => $only->fresh(),
        'navigateArchived' => true,
    ]);

    expect($component->instance()->next)->toBeNull()
        ->and($component->instance()->previous)->toBeNull();

    $component->call('archive')
        ->assertDispatched('archive-saved', function (string $name, array $params) use ($gallery) {
            return $params['archived'] === false
                && $params['nextUrl'] === route('galleries.show', ['gallery' => $gallery]);
        });

    // The Archived tab no longer exists, so the grid must open on All photos,
    // where the restored photo and the live one are both visible.
    expect($only->fresh()->isArchived())->toBeFalse()
        ->and($live->fresh()->isArchived())->toBeFalse();
});

it('returns to All photos when the dock restores the last archived photo', function () {
    $gallery = Gallery::factory()->for($this->team)->create();
    $only = Photo::factory()->for($gallery)->create();

    $only->archive();

    $component = Livewire::actingAs($this->user)->test('pages::galleries.photos.show', [
        'photo' => $only->fresh(),
        'navigateArchived' => true,
    ]);

    $component->call('unarchive')
        ->assertDispatched('archive-saved', function (string $name, array $params) use ($gallery) {
            return $params['archived'] === false
                && $params['nextUrl'] === route('galleries.show', ['gallery' => $gallery]);
        });

    expect($only->fresh()->isArchived())->toBeFalse();
});

it('keeps looking at the photo when the dock restores one of several archived', function () {
    $gallery = Gallery::factory()->for($this->team)->create();
    $first = Photo::factory()->for($gallery)->create(['name' => 'a.jpg']);
    $second = Photo::factory()->for($gallery)->create(['name' => 'b.jpg']);

    $first->archive();
    $second->archive();

    $component = Livewire::actingAs($this->user)->test('pages::galleries.photos.show', [
        'photo' => $first->fresh(),
        'navigateArchived' => true,
    ]);

    $component->call('unarchive')
        ->assertNoRedirect();

    expect($first->fresh()->isArchived())->toBeFalse()
        ->and($second->fresh()->isArchived())->toBeTrue();
});

it('falls back to All photos in the grid when the last archived one is restored', function () {
    $gallery = Gallery::factory()->for($this->team)->create();
    $only = Photo::factory()->for($gallery)->create();

    $only->archive();

    $component = Livewire::actingAs($this->user)
        ->test('pages::galleries.show', ['gallery' => $gallery])
        ->set('activeTab', 'archived')
        ->call('toggleArchive', $only->id);

    // The Archived tab disappears with its last photo, so the grid must not
    // stay on an empty panel.
    expect($component->get('activeTab'))->toBe('all')
        ->and($only->fresh()->isArchived())->toBeFalse();
});

it('ignores a stale archived tab link when nothing is archived', function () {
    $gallery = Gallery::factory()->for($this->team)->create();
    Photo::factory()->for($gallery)->create();

    $component = Livewire::actingAs($this->user)
        ->withQueryParams(['activeTab' => 'archived'])
        ->test('pages::galleries.show', ['gallery' => $gallery]);

    expect($component->get('activeTab'))->toBe('all');
});
