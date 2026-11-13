<?php

use App\Models\Gallery;
use App\Models\Photo;
use App\Models\PhotoComment;
use App\Models\PhotoVersion;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('s3');
    $this->user = User::factory()->withPersonalTeam()->create();
    $this->team = $this->user->currentTeam;
});

test('users can view a team photo in the gallery', function () {
    $photo = Photo::factory()->for(Gallery::factory()->for($this->team))->create();

    $response = actingAs($this->user)->get('/galleries/1/photos/1');

    $response->assertStatus(200);
});

test('the back link points to the photo position in the gallery', function () {
    $gallery = Gallery::factory()->for($this->team)->create();
    $photo = Photo::factory()->for($gallery)->create();

    $component = Livewire::actingAs($this->user)->test('pages::galleries.photos.show', ['photo' => $photo]);

    expect($component->instance()->galleryUrl)->toBe(
        route('galleries.show', ['gallery' => $gallery, 'photo' => $photo->id]).'#photo-'.$photo->id
    );
});

test('the back link keeps the favorites filter and target anchor', function () {
    $gallery = Gallery::factory()->for($this->team)->create();
    $photo = Photo::factory()->for($gallery)->favorited()->create();

    $component = Livewire::actingAs($this->user)
        ->withQueryParams(['navigateFavorites' => true])
        ->test('pages::galleries.photos.show', ['photo' => $photo]);

    expect($component->instance()->galleryUrl)->toBe(
        route('galleries.show', [
            'gallery' => $gallery,
            'favorites' => 1,
            'photo' => $photo->id,
        ]).'#photo-'.$photo->id
    );
});

test('users can open a pdf photo from an inline open button', function () {
    $photo = Photo::factory()->pdf()->for(Gallery::factory()->for($this->team))->create();

    $response = actingAs($this->user)->get('/galleries/1/photos/1');

    $response->assertStatus(200);
    $response->assertSee('Open PDF');
    $response->assertSee('/galleries/1/photos/1/pdf');
    $response->assertSee($photo->formattedSize());
});

test('image photos offer the cover menu', function () {
    Photo::factory()->for(Gallery::factory()->for($this->team))->create();

    $response = actingAs($this->user)->get('/galleries/1/photos/1');

    $response->assertSuccessful();
    $response->assertSee('Set as cover');
});

test('pdf photos do not offer the cover menu', function () {
    Photo::factory()->pdf()->for(Gallery::factory()->for($this->team))->create();

    $response = actingAs($this->user)->get('/galleries/1/photos/1');

    $response->assertSuccessful();
    $response->assertDontSee('Set as cover');
    $response->assertDontSee('Remove as Cover');
});

test('guests cannot view any photos', function () {
    $photo = Photo::factory()->create();

    Gallery::factory()->for($this->team)->create();

    $response = get('/galleries/1/photos/1');

    $response->assertRedirect('/login');
});

test('users cannot view the team gallery photos of other users', function () {
    $photo = Photo::factory()->for(Gallery::factory()->for(Team::factory()))->create();

    $response = actingAs($this->user)->get('/galleries/1/photos/1');

    $response->assertStatus(403);
});

test('can delete a photo', function () {
    Storage::fake('public');
    Storage::fake('s3');

    $photo = Photo::factory()->for(Gallery::factory()->for($this->team))->create([
        'name' => 'photo1.jpg',
        'path' => UploadedFile::fake()
            ->image('photo1.jpg')
            ->storeAs('galleries/1/photos', 'photo1.jpg', 'public'),
        'thumb_path' => UploadedFile::fake()
            ->image('photo1_thumb.jpg')
            ->storeAs('galleries/1/photos', 'photo1_thumb.jpg', 'public'),

    ]);
    Storage::disk('public')->assertExists(['galleries/1/photos/photo1.jpg']);
    expect(Photo::count())->toBe(1);

    $component = Livewire::actingAs($this->user)->test('pages::galleries.photos.show', ['photo' => $photo])
        ->call('delete');

    $component->assertRedirect('/galleries/1');
    expect(Photo::count())->toBe(0);
    Storage::disk('public')->assertMissing(['galleries/1/photos/photo1.jpg']);
    Storage::disk('public')->assertMissing(['galleries/1/photos/photo1_thumb.jpg']);
});

test('deleting a photo opens the next photo in the viewer', function () {
    Storage::fake('s3');

    $gallery = Gallery::factory()->for($this->team)->create();
    $first = Photo::factory()->for($gallery)->create(['name' => 'a.jpg']);
    $second = Photo::factory()->for($gallery)->create(['name' => 'b.jpg']);

    $component = Livewire::actingAs($this->user)
        ->test('pages::galleries.photos.show', ['photo' => $first])
        ->call('delete');

    $component->assertRedirect(route('galleries.photos.show', [
        'gallery' => $gallery,
        'photo' => $second,
    ]));

    expect(Photo::find($first->id))->toBeNull();
});

test('deleting the last photo opens the previous photo in the viewer', function () {
    Storage::fake('s3');

    $gallery = Gallery::factory()->for($this->team)->create();
    $first = Photo::factory()->for($gallery)->create(['name' => 'a.jpg']);
    $second = Photo::factory()->for($gallery)->create(['name' => 'b.jpg']);

    $component = Livewire::actingAs($this->user)
        ->test('pages::galleries.photos.show', ['photo' => $second])
        ->call('delete');

    $component->assertRedirect(route('galleries.photos.show', [
        'gallery' => $gallery,
        'photo' => $first,
    ]));

    expect(Photo::find($second->id))->toBeNull();
});

test('deleting a photo keeps the favorites navigation mode', function () {
    Storage::fake('s3');

    $gallery = Gallery::factory()->for($this->team)->create();
    $first = Photo::factory()->favorited()->for($gallery)->create(['name' => 'a.jpg']);
    $second = Photo::factory()->favorited()->for($gallery)->create(['name' => 'b.jpg']);

    $component = Livewire::actingAs($this->user)
        ->withQueryParams(['navigateFavorites' => true])
        ->test('pages::galleries.photos.show', ['photo' => $first])
        ->call('delete');

    $component->assertRedirect(route('galleries.photos.show', [
        'gallery' => $gallery,
        'photo' => $second,
        'navigateFavorites' => true,
    ]));

    expect(Photo::find($first->id))->toBeNull();
});

test('deleting the only photo returns to the gallery', function () {
    Storage::fake('s3');

    $gallery = Gallery::factory()->for($this->team)->create();
    $photo = Photo::factory()->for($gallery)->create();

    $component = Livewire::actingAs($this->user)
        ->test('pages::galleries.photos.show', ['photo' => $photo])
        ->call('delete');

    $component->assertRedirect("/galleries/{$gallery->id}");
    expect(Photo::find($photo->id))->toBeNull();
});

test('asks for confirmation before deleting a comment', function () {
    $photo = Photo::factory()->for(Gallery::factory()->for($this->team))->create();
    $comment = PhotoComment::factory()->for($photo)->create();

    $component = Livewire::actingAs($this->user)->test('pages::galleries.photos.show', ['photo' => $photo])
        ->call('requestDeleteComment', $comment->id);

    expect($component->get('pendingCommentId'))->toBe($comment->id);
    expect($photo->comments()->count())->toBe(1);
});

test('rates a photo and advances to the next photo in the set', function () {
    $gallery = Gallery::factory()->for($this->team)->create();
    $first = Photo::factory()->for($gallery)->create(['name' => 'a.jpg']);
    $second = Photo::factory()->for($gallery)->create(['name' => 'b.jpg']);

    $component = Livewire::actingAs($this->user)
        ->test('pages::galleries.photos.show', ['photo' => $first])
        ->call('setRating', 3);

    expect($first->fresh()->rating)->toBe(3)
        ->and($first->fresh()->isFavorited())->toBeFalse();

    $component->assertDispatched('rating-saved', function (string $name, array $params) use ($gallery, $second) {
        return $params['rating'] === 3
            && $params['nextUrl'] === route('galleries.photos.show', [
                'gallery' => $gallery,
                'photo' => $second,
            ]);
    });
});

test('five stars also favorites the photo', function () {
    $gallery = Gallery::factory()->for($this->team)->create();
    $photo = Photo::factory()->for($gallery)->unfavorited()->create();

    $component = Livewire::actingAs($this->user)
        ->test('pages::galleries.photos.show', ['photo' => $photo])
        ->call('setRating', 5);

    expect($photo->fresh()->rating)->toBe(5)
        ->and($photo->fresh()->isFavorited())->toBeTrue();

    $component->assertDispatched('favorite-toggled', id: $photo->id, favorited: true);
});

test('hearting a photo sets five stars', function () {
    $gallery = Gallery::factory()->for($this->team)->create();
    $photo = Photo::factory()->for($gallery)->unfavorited()->create();

    $component = Livewire::actingAs($this->user)
        ->test('pages::galleries.photos.show', ['photo' => $photo])
        ->call('favorite');

    expect($photo->fresh()->isFavorited())->toBeTrue()
        ->and($photo->fresh()->rating)->toBe(5);

    $component->assertDispatched('favorite-toggled', id: $photo->id, favorited: true);
});

test('unhearting a photo clears the rating', function () {
    $gallery = Gallery::factory()->for($this->team)->create();
    $photo = Photo::factory()->for($gallery)->favorited()->rated(5)->create();

    Livewire::actingAs($this->user)
        ->test('pages::galleries.photos.show', ['photo' => $photo])
        ->call('favorite');

    expect($photo->fresh()->isFavorited())->toBeFalse()
        ->and($photo->fresh()->rating)->toBeNull();
});

test('ratings one to four leave the heart untouched', function () {
    $gallery = Gallery::factory()->for($this->team)->create();
    $unfavorited = Photo::factory()->for($gallery)->unfavorited()->create(['name' => 'a.jpg']);
    $favorited = Photo::factory()->for($gallery)->favorited()->create(['name' => 'b.jpg']);

    Livewire::actingAs($this->user)
        ->test('pages::galleries.photos.show', ['photo' => $unfavorited])
        ->call('setRating', 2);

    Livewire::actingAs($this->user)
        ->test('pages::galleries.photos.show', ['photo' => $favorited])
        ->call('setRating', 4);

    expect($unfavorited->fresh()->isFavorited())->toBeFalse()
        ->and($favorited->fresh()->isFavorited())->toBeTrue();
});

test('zero clears the rating and the heart without advancing', function () {
    $gallery = Gallery::factory()->for($this->team)->create();
    $first = Photo::factory()->for($gallery)->rated(4)->favorited()->create(['name' => 'a.jpg']);
    Photo::factory()->for($gallery)->create(['name' => 'b.jpg']);

    $component = Livewire::actingAs($this->user)
        ->test('pages::galleries.photos.show', ['photo' => $first])
        ->call('setRating', 0);

    expect($first->fresh()->rating)->toBeNull()
        ->and($first->fresh()->isFavorited())->toBeFalse();

    $component->assertDispatched('rating-saved', function (string $name, array $params) {
        return $params['rating'] === null && $params['nextUrl'] === null;
    });
});

test('the viewer pages through the filtered rating set', function () {
    $gallery = Gallery::factory()->for($this->team)->create();
    Photo::factory()->for($gallery)->rated(1)->create(['name' => 'a.jpg']);
    $three = Photo::factory()->for($gallery)->rated(3)->create(['name' => 'b.jpg']);
    $nextThree = Photo::factory()->for($gallery)->rated(3)->create(['name' => 'c.jpg']);
    $lastThree = Photo::factory()->for($gallery)->rated(3)->create(['name' => 'd.jpg']);

    $component = Livewire::actingAs($this->user)
        ->withQueryParams(['rating' => 3])
        ->test('pages::galleries.photos.show', ['photo' => $three]);

    expect($component->get('previous'))->toBeNull()
        ->and($component->get('next')->id)->toBe($nextThree->id);

    $component = Livewire::actingAs($this->user)
        ->withQueryParams(['rating' => 3, 'sort' => 'rating', 'direction' => 'desc'])
        ->test('pages::galleries.photos.show', ['photo' => $nextThree]);

    expect($component->get('previous')->id)->toBe($three->id)
        ->and($component->get('next')->id)->toBe($lastThree->id);
});

test('the back link keeps the active filters and sort', function () {
    $gallery = Gallery::factory()->for($this->team)->create();
    $photo = Photo::factory()->for($gallery)->rated(3)->create();

    $component = Livewire::actingAs($this->user)
        ->withQueryParams(['rating' => 3, 'favorites' => 1, 'sort' => 'rating'])
        ->test('pages::galleries.photos.show', ['photo' => $photo]);

    expect($component->galleryUrl)->toContain('rating=3')
        ->and($component->galleryUrl)->toContain('favorites=1')
        ->and($component->galleryUrl)->toContain('sort=rating');
});

test('rates from the dock popover and advances to the next photo', function () {
    $gallery = Gallery::factory()->for($this->team)->create();
    $first = Photo::factory()->for($gallery)->create(['name' => 'a.jpg']);
    $second = Photo::factory()->for($gallery)->create(['name' => 'b.jpg']);

    $this->actingAs($this->user);

    $page = visit("/galleries/{$gallery->id}/photos/{$first->id}");
    $page->click('[data-dock-rating]');
    $page->wait(0.2);
    $page->click('[data-rating-popover] [data-rating-star="4"]');
    $page->wait(0.8);

    expect($first->fresh()->rating)->toBe(4);

    $page->assertPathIs("/galleries/{$gallery->id}/photos/{$second->id}");
})->group('browser');

test('archiving dispatches the confirmation and the next photo url', function () {
    $gallery = Gallery::factory()->for($this->team)->create();
    $first = Photo::factory()->for($gallery)->create(['name' => 'a.jpg']);
    $second = Photo::factory()->for($gallery)->create(['name' => 'b.jpg']);

    $component = Livewire::actingAs($this->user)
        ->test('pages::galleries.photos.show', ['photo' => $first])
        ->call('archive');

    expect($first->fresh()->isArchived())->toBeTrue();

    $component->assertDispatched('archive-saved', function (string $name, array $params) use ($gallery, $second) {
        return $params['archived'] === true
            && $params['nextUrl'] === route('galleries.photos.show', [
                'gallery' => $gallery,
                'photo' => $second,
            ]);
    });
});

test('unarchiving dispatches the restore confirmation without navigating', function () {
    $gallery = Gallery::factory()->for($this->team)->create();
    $photo = Photo::factory()->for($gallery)->create(['archived_at' => now()]);

    $component = Livewire::actingAs($this->user)
        ->test('pages::galleries.photos.show', ['photo' => $photo])
        ->call('unarchive');

    expect($photo->fresh()->isArchived())->toBeFalse();

    $component->assertDispatched('archive-saved', function (string $name, array $params) {
        return $params['archived'] === false && $params['nextUrl'] === null;
    });
});

test('the photo info panel lists rating, comments, favorite, cover and archive state', function () {
    $gallery = Gallery::factory()->for($this->team)->create();
    $photo = Photo::factory()->for($gallery)->rated(4)->favorited()->create([
        'archived_at' => now(),
    ]);
    PhotoComment::factory()->count(2)->for($photo)->create();
    $gallery->update(['cover_photo_id' => $photo->id]);
    $photo->unsetRelation('gallery');

    $component = Livewire::actingAs($this->user)
        ->test('pages::galleries.photos.show', ['photo' => $photo]);

    $component
        ->assertSeeHtml('data-photo-info-rating')
        ->assertSeeHtml('data-photo-info-comments')
        ->assertSeeHtml('data-photo-info-favorite')
        ->assertSeeHtml('data-photo-info-cover')
        ->assertSeeHtml('data-photo-info-archived')
        // Livewire must not morph the native <details>: doing so closes it when
        // a favorite/rating re-render adds a row to the panel.
        ->assertSeeHtml('wire:ignore.self');
});

test('can favorite a photo', function () {
    $photo = Photo::factory()->unfavorited()->for(Gallery::factory()->for($this->team))->create();
    expect($photo->isFavorited())->toBeFalse();

    $component = Livewire::actingAs($this->user)->test('pages::galleries.photos.show', ['photo' => $photo])
        ->call('favorite');

    expect($photo->fresh()->isFavorited())->toBeTrue();
});

test('can set a photo as the gallery cover', function () {
    $photo = Photo::factory()->for(Gallery::factory()->for($this->team))->create();

    $component = Livewire::actingAs($this->user)->test('pages::galleries.photos.show', ['photo' => $photo])
        ->call('setAsCover');

    expect($photo->gallery->fresh()->cover_photo_id)->toBe($photo->id);
});

test('can change the cover photo', function () {
    $gallery = Gallery::factory()->for($this->team)->create();
    $photo1 = Photo::factory()->for($gallery)->create();
    $photo2 = Photo::factory()->for($gallery)->create();
    $gallery->update(['cover_photo_id' => $photo1->id]);

    $component = Livewire::actingAs($this->user)->test('pages::galleries.photos.show', ['photo' => $photo2])
        ->call('setAsCover');

    expect($gallery->fresh()->cover_photo_id)->toBe($photo2->id);
});

test('can remove the cover photo', function () {
    $gallery = Gallery::factory()->for($this->team)->create();
    $photo = Photo::factory()->for($gallery)->create();
    $gallery->update(['cover_photo_id' => $photo->id]);

    $component = Livewire::actingAs($this->user)->test('pages::galleries.photos.show', ['photo' => $photo])
        ->call('removeAsCover');

    expect($gallery->fresh()->cover_photo_id)->toBeNull();
});

test('authenticated user can add a comment to a photo', function () {
    $photo = Photo::factory()->for(Gallery::factory()->for($this->team))->create();
    $user = $this->user;

    Livewire::actingAs($user)
        ->test('pages::galleries.photos.show', ['photo' => $photo])
        ->set('commentText', 'This is a test comment!')
        ->call('addComment')
        ->assertHasNoErrors();

    expect($photo->comments()->count())->toBe(1);
    $comment = $photo->comments()->first();
    expect($comment->comment)->toBe('This is a test comment!');
    expect($comment->user_id)->toBe($user->id);
});

test('comment is required when adding a comment', function () {
    $photo = Photo::factory()->for(Gallery::factory()->for($this->team))->create();
    $user = $this->user;

    Livewire::actingAs($user)
        ->test('pages::galleries.photos.show', ['photo' => $photo])
        ->set('commentText', '')
        ->call('addComment')
        ->assertHasErrors(['commentText' => 'required']);

    expect($photo->comments()->count())->toBe(0);
});

test('user can delete their own comment', function () {
    $photo = Photo::factory()->for(Gallery::factory()->for($this->team))->create();
    $user = $this->user;
    $comment = PhotoComment::factory()->for($photo)->create([
        'user_id' => $user->id,
    ]);

    Livewire::actingAs($user)
        ->test('pages::galleries.photos.show', ['photo' => $photo])
        ->call('deleteComment', $comment->id)
        ->assertHasNoErrors();

    expect($photo->comments()->find($comment->id))->toBeNull();
});

test('restoring a version keeps the previous current as history without duplicating it', function () {
    $gallery = Gallery::factory()->for($this->team)->create();
    $photo = Photo::factory()->for($gallery)->create([
        'path' => 'galleries/current.jpg',
        'uploaded_at' => now(),
        'version_count' => 2,
    ]);

    $old = PhotoVersion::factory()->for($photo)->create([
        'version_number' => 1,
        'path' => 'galleries/old.jpg',
        'uploaded_at' => now()->subDay(),
        'status' => 'processed',
    ]);

    Livewire::actingAs($this->user)
        ->test('photo-versions', ['photo' => $photo, 'manageable' => true])
        ->call('restoreVersion', $old->id)
        ->assertHasNoErrors();

    $photo->refresh();

    expect($photo->current_version_id)->toBe($old->id);
    expect($photo->path)->toBe('galleries/old.jpg');
    expect($photo->uploaded_at->timestamp)->toBe($old->uploaded_at->timestamp);
    expect($photo->versions()->where('path', 'galleries/old.jpg')->count())->toBe(1);
    expect($photo->versions()->where('path', 'galleries/current.jpg')->count())->toBe(1);
});

test('deleting a version removes only that version and its files', function () {
    Storage::fake('s3');

    $gallery = Gallery::factory()->for($this->team)->create();
    $photo = Photo::factory()->for($gallery)->create([
        'path' => 'galleries/current.jpg',
        'disk' => 's3',
        'version_count' => 2,
    ]);
    Storage::disk('s3')->put('galleries/current.jpg', 'current');

    $version = PhotoVersion::factory()->for($photo)->create([
        'version_number' => 1,
        'path' => 'galleries/old.jpg',
        'disk' => 's3',
    ]);
    Storage::disk('s3')->put('galleries/old.jpg', 'old');

    Livewire::actingAs($this->user)
        ->test('photo-versions', ['photo' => $photo, 'manageable' => true])
        ->call('deleteVersion', $version->id)
        ->assertHasNoErrors();

    expect(PhotoVersion::find($version->id))->toBeNull();
    Storage::disk('s3')->assertMissing('galleries/old.jpg');
    Storage::disk('s3')->assertExists('galleries/current.jpg');
});

test('the current version cannot be deleted', function () {
    $gallery = Gallery::factory()->for($this->team)->create();
    $photo = Photo::factory()->for($gallery)->create();

    $version = PhotoVersion::factory()->for($photo)->create([
        'version_number' => 1,
        'path' => 'galleries/current.jpg',
    ]);

    $photo->update(['current_version_id' => $version->id, 'path' => 'galleries/current.jpg']);

    Livewire::actingAs($this->user)
        ->test('photo-versions', ['photo' => $photo, 'manageable' => true])
        ->call('deleteVersion', $version->id);

    expect(PhotoVersion::find($version->id))->not->toBeNull();
});

test('the photographer can pin a comment to a photo', function () {
    $gallery = Gallery::factory()->for($this->team)->create();
    $photo = Photo::factory()->for($gallery)->create(['version_count' => 1]);

    Livewire::actingAs($this->user)
        ->test('pages::galleries.photos.show', ['photo' => $photo])
        ->call('addAnnotation', 25.5, 60.0)
        ->assertSet('pendingPinX', 25.5)
        ->assertSet('pendingPinY', 60.0)
        ->set('commentText', 'Retouch this corner')
        ->call('addComment')
        ->assertHasNoErrors();

    $comment = $photo->comments()->first();

    expect($comment->comment)->toBe('Retouch this corner');
    expect($comment->x)->toBe(25.5);
    expect($comment->y)->toBe(60.0);
    expect($comment->annotated_version_number)->toBe(1);
    expect($comment->hasCoordinates())->toBeTrue();
});

test('annotation coordinates are clamped to the image bounds', function () {
    $gallery = Gallery::factory()->for($this->team)->create();
    $photo = Photo::factory()->for($gallery)->create();

    Livewire::actingAs($this->user)
        ->test('pages::galleries.photos.show', ['photo' => $photo])
        ->call('addAnnotation', 140.0, -20.0)
        ->assertSet('pendingPinX', 100.0)
        ->assertSet('pendingPinY', 0.0);
});

test('annotations cannot be pinned to a non-image photo', function () {
    $gallery = Gallery::factory()->for($this->team)->create();
    $photo = Photo::factory()->pdf()->for($gallery)->create();

    Livewire::actingAs($this->user)
        ->test('pages::galleries.photos.show', ['photo' => $photo])
        ->call('addAnnotation', 10.0, 10.0)
        ->assertStatus(404);
});

test('a plain comment keeps the annotation fields empty', function () {
    $gallery = Gallery::factory()->for($this->team)->create();
    $photo = Photo::factory()->for($gallery)->create();

    Livewire::actingAs($this->user)
        ->test('pages::galleries.photos.show', ['photo' => $photo])
        ->set('commentText', 'Just a comment')
        ->call('addComment')
        ->assertHasNoErrors();

    $comment = $photo->comments()->first();

    expect($comment->x)->toBeNull();
    expect($comment->y)->toBeNull();
    expect($comment->annotated_version_number)->toBeNull();
    expect($comment->hasCoordinates())->toBeFalse();
});

test('the annotation overlay stays hidden until annotate mode is enabled', function () {
    $gallery = Gallery::factory()->for($this->team)->create();
    $photo = Photo::factory()->for($gallery)->create();

    $this->actingAs($this->user);

    $page = visit("/galleries/{$gallery->id}/photos/{$photo->id}");
    $page->wait(0.2);

    // The dock is icon-only: the toggles are reached by their data hooks.
    // "Quiet by default" means the annotate button is NOT filled until the mode
    // actually runs, and then the exit button takes its place.
    expect($page->script(<<<'JS'
        (() => {
            const enter = document.querySelector('[data-annotate-button]')
            const exit = document.querySelector('[data-annotate-exit]')

            return {
                enter: getComputedStyle(enter).backgroundColor,
                exitVisible: exit ? getComputedStyle(exit).display !== 'none' : null,
            }
        })()
    JS))->toBe(['enter' => 'rgba(0, 0, 0, 0)', 'exitVisible' => false]);

    $page->click('[data-annotate-button]');
    $page->wait(0.2);

    expect($page->script("getComputedStyle(document.querySelector('.cursor-crosshair')).display"))
        ->toBe('block')
        ->and($page->script(<<<'JS'
            (() => {
                const exit = document.querySelector('[data-annotate-exit]')

                return {
                    exitVisible: exit ? getComputedStyle(exit).display !== 'none' : false,
                    exitFilled: exit ? getComputedStyle(exit).backgroundColor !== 'rgba(0, 0, 0, 0)' : false,
                }
            })()
        JS))->toBe(['exitVisible' => true, 'exitFilled' => true]);

    $page->click('[data-annotate-exit]');
    $page->wait(0.2);

    expect($page->script("getComputedStyle(document.querySelector('.cursor-crosshair')).display"))
        ->toBe('none');
})->group('browser');

test('annotations from an older version are flagged in the comment list', function () {
    $gallery = Gallery::factory()->for($this->team)->create();
    $photo = Photo::factory()->for($gallery)->create(['version_count' => 1]);

    Livewire::actingAs($this->user)
        ->test('pages::galleries.photos.show', ['photo' => $photo])
        ->call('addAnnotation', 40.0, 40.0)
        ->set('commentText', 'Check the left eye')
        ->call('addComment');

    $photo->update(['version_count' => 2]);

    Livewire::actingAs($this->user)
        ->test('pages::galleries.photos.show', ['photo' => $photo->fresh()])
        ->assertSee('Version 1')
        ->assertSee('Annotations from older versions: 1')
        ->assertSee('This annotation was made on version 1.');
});

test('an annotation from a deleted version is marked as unavailable', function () {
    $gallery = Gallery::factory()->for($this->team)->create();
    $photo = Photo::factory()->for($gallery)->create(['version_count' => 3]);

    $version = PhotoVersion::factory()->for($photo)->create([
        'version_number' => 2,
        'path' => 'galleries/old.jpg',
    ]);

    PhotoComment::factory()->for($photo)->create([
        'x' => 10,
        'y' => 20,
        'annotated_version_number' => 2,
    ]);

    $version->delete();

    Livewire::actingAs($this->user)
        ->test('pages::galleries.photos.show', ['photo' => $photo->fresh()])
        ->assertSee('That version is no longer available.');
});

test('the comment deep link opens and focuses the linked comment', function () {
    $gallery = Gallery::factory()->for($this->team)->create();
    $photo = Photo::factory()->for($gallery)->create();
    $comment = PhotoComment::factory()->for($photo)->create();

    Livewire::actingAs($this->user)
        ->withQueryParams(['comment' => $comment->id])
        ->test('pages::galleries.photos.show', ['photo' => $photo])
        ->assertDispatched('focus-comment', id: $comment->id);
});

test('the annotations toggle appears only when the photo has annotations', function () {
    $gallery = Gallery::factory()->for($this->team)->create();
    $photo = Photo::factory()->for($gallery)->create();

    actingAs($this->user)->get('/galleries/1/photos/1')
        ->assertOk()
        ->assertDontSee('Hide annotations');

    PhotoComment::factory()->for($photo)->create([
        'x' => 30,
        'y' => 70,
        'annotated_version_number' => 1,
    ]);

    actingAs($this->user)->get('/galleries/1/photos/1')
        ->assertOk()
        ->assertSee('Hide annotations');
});

test('video photos do not offer the cover menu', function () {
    Photo::factory()->video()->for(Gallery::factory()->for($this->team))->create();

    $response = actingAs($this->user)->get('/galleries/1/photos/1');

    $response->assertSuccessful();
    $response->assertDontSee('Set as cover');
    $response->assertDontSee('Remove as Cover');
});

test('videos cannot be set as gallery cover from the photo page', function () {
    $gallery = Gallery::factory()->for($this->team)->create();
    $photo = Photo::factory()->video()->for($gallery)->create();

    Livewire::actingAs($this->user)->test('pages::galleries.photos.show', ['photo' => $photo])
        ->call('setAsCover')
        ->assertStatus(422);

    expect($gallery->fresh()->cover_photo_id)->toBeNull();
});

test('full-size images use the thumbnail as a placeholder while loading', function () {
    Photo::factory()->for(Gallery::factory()->for($this->team))->create();

    actingAs($this->user)
        ->get('/galleries/1/photos/1')
        ->assertSuccessful()
        ->assertSee('background-image: url', false)
        ->assertSee('background-size: cover', false)
        ->assertSee('background-repeat: no-repeat', false);
});

test('pinch uses the full image and disables swipe until zoom is left', function () {
    $gallery = Gallery::factory()->for($this->team)->create();
    $first = Photo::factory()->for($gallery)->create(['status' => 'processed']);
    $second = Photo::factory()->for($gallery)->create(['status' => 'processed']);

    $this->actingAs($this->user);

    $swipeLeft = <<<'JS'
        (() => {
            const el = document.querySelector('#photo')
            const touch = (x) => new Touch({ identifier: 1, target: el, clientX: x, clientY: 200 })
            const start = touch(300)
            el.dispatchEvent(new TouchEvent('touchstart', {
                touches: [start],
                targetTouches: [start],
                changedTouches: [start],
                bubbles: true,
                cancelable: true,
            }))
            const end = touch(80)
            el.dispatchEvent(new TouchEvent('touchend', {
                touches: [],
                targetTouches: [],
                changedTouches: [end],
                bubbles: true,
                cancelable: true,
            }))
        })()
    JS;

    $pinch = <<<'JS'
        (() => {
            const el = document.querySelector('#photo')
            const touch = (id, x) => new Touch({ identifier: id, target: el, clientX: x, clientY: 200 })
            const a = touch(1, 100)
            const b = touch(2, 300)
            el.dispatchEvent(new TouchEvent('touchstart', {
                touches: [a, b],
                targetTouches: [a, b],
                changedTouches: [a, b],
                bubbles: true,
                cancelable: true,
            }))
            const a2 = touch(1, 50)
            const b2 = touch(2, 350)
            el.dispatchEvent(new TouchEvent('touchmove', {
                touches: [a2, b2],
                targetTouches: [a2, b2],
                changedTouches: [a2, b2],
                bubbles: true,
                cancelable: true,
            }))
            el.dispatchEvent(new TouchEvent('touchend', {
                touches: [],
                targetTouches: [],
                changedTouches: [a2, b2],
                bubbles: true,
                cancelable: true,
            }))
        })()
    JS;

    $pinchOut = <<<'JS'
        (() => {
            const el = document.querySelector('#photo')
            const touch = (id, x) => new Touch({ identifier: id, target: el, clientX: x, clientY: 200 })
            const a = touch(1, 100)
            const b = touch(2, 300)
            el.dispatchEvent(new TouchEvent('touchstart', {
                touches: [a, b],
                targetTouches: [a, b],
                changedTouches: [a, b],
                bubbles: true,
                cancelable: true,
            }))
            const a2 = touch(1, 150)
            const b2 = touch(2, 250)
            el.dispatchEvent(new TouchEvent('touchmove', {
                touches: [a2, b2],
                targetTouches: [a2, b2],
                changedTouches: [a2, b2],
                bubbles: true,
                cancelable: true,
            }))
            el.dispatchEvent(new TouchEvent('touchend', {
                touches: [],
                targetTouches: [],
                changedTouches: [a2, b2],
                bubbles: true,
                cancelable: true,
            }))
        })()
    JS;

    $page = visit('/galleries/'.$gallery->id.'/photos/'.$first->id);
    $page->wait(0.5);
    $page->script($swipeLeft);
    $page->wait(1);

    expect($page->script('window.location.pathname'))->toContain('/photos/'.$second->id);

    $page = visit('/galleries/'.$gallery->id.'/photos/'.$first->id);
    $page->wait(0.5);
    $page->script($pinch);
    $page->wait(0.3);

    expect($page->script("document.querySelector('[data-full-image]').style.display"))->not->toBe('none')
        ->and($page->script("document.querySelector('[data-full-image]').getAttribute('src')"))->toContain($photoUrl = $first->url);

    $page->script(<<<'JS'
        (() => {
            const el = document.querySelector('#photo')
            const touch = (x) => new Touch({ identifier: 1, target: el, clientX: x, clientY: 200 })
            const start = touch(300)
            el.dispatchEvent(new TouchEvent('touchstart', {
                touches: [start],
                targetTouches: [start],
                changedTouches: [start],
                bubbles: true,
                cancelable: true,
            }))
            const moved = touch(250)
            el.dispatchEvent(new TouchEvent('touchmove', {
                touches: [moved],
                targetTouches: [moved],
                changedTouches: [moved],
                bubbles: true,
                cancelable: true,
            }))
            el.dispatchEvent(new TouchEvent('touchend', {
                touches: [],
                targetTouches: [],
                changedTouches: [moved],
                bubbles: true,
                cancelable: true,
            }))
        })()
    JS);
    $page->wait(0.3);

    expect($page->script("document.querySelector('[data-full-image]').style.display"))->not->toBe('none');

    $page->script($swipeLeft);
    $page->wait(1);

    expect($page->script('window.location.pathname'))->toBe('/galleries/'.$gallery->id.'/photos/'.$first->id);

    $page->script($pinchOut);
    $page->wait(0.3);
    $page->script($swipeLeft);
    $page->wait(1);

    expect($page->script('window.location.pathname'))->toContain('/photos/'.$second->id);

    $page = visit('/galleries/'.$gallery->id.'/photos/'.$first->id);
    $page->wait(0.5);
    $page->script("window.dispatchEvent(new KeyboardEvent('keyup', { key: 'ArrowRight', bubbles: true }))");
    $page->wait(1);

    expect($page->script('window.location.pathname'))->toContain('/photos/'.$second->id);
})->group('browser');

test('the hidden annotations state is kept while moving between photos', function () {
    $gallery = Gallery::factory()->for($this->team)->create();
    $first = Photo::factory()->for($gallery)->create();
    $second = Photo::factory()->for($gallery)->create();

    foreach ([$first, $second] as $photo) {
        PhotoComment::factory()->for($photo)->create([
            'x' => 40,
            'y' => 45,
            'annotated_version_number' => 1,
        ]);
    }

    $this->actingAs($this->user);

    $page = visit("/galleries/{$gallery->id}/photos/{$first->id}");

    $pinIsVisible = fn (): bool => (bool) $page->script(<<<'JS'
        (() => {
            const pin = document.querySelector('#photo button[title="Photo annotation"]')

            return pin ? pin.offsetParent !== null : false
        })()
    JS);

    expect($pinIsVisible())->toBeTrue();

    // Filled state, same rule as the comments button: one toggle, green when
    // the pins are visible.
    expect($page->script(<<<'JS'
        (() => {
            const toggle = document.querySelector('[data-annotations-hide], [data-annotations-show]')

            return toggle ? [
                toggle.getAttribute('aria-label'),
                getComputedStyle(toggle).backgroundColor,
            ] : null
        })()
    JS))->toBe(['Hide annotations', 'rgb(49, 109, 97)']);

    $page->script("document.querySelector('[data-annotations-hide]')?.click()");
    $page->wait(0.2);

    expect($pinIsVisible())->toBeFalse()
        ->and($page->script("localStorage.getItem('picstome-annotations-visible')"))->toBe('0');

    // Arrow-key navigation is a fresh Livewire render of the next photo.
    $page->script("window.dispatchEvent(new KeyboardEvent('keyup', { key: 'ArrowRight', bubbles: true }))");
    $page->wait(0.8);

    expect($page->script('window.location.pathname'))->toContain('/photos/'.$second->id);

    expect($pinIsVisible())->toBeFalse();
    expect($page->script(<<<'JS'
        (() => {
            const show = document.querySelector('[data-annotations-show]')

            return show ? show.offsetParent !== null : false
        })()
    JS))->toBeTrue();
})->group('browser');

test('the photo viewer offers a quick delete action with a confirmation', function () {
    Photo::factory()->for(Gallery::factory()->for($this->team))->create();

    actingAs($this->user)
        ->get('/galleries/1/photos/1')
        ->assertOk()
        ->assertSee('data-photo-delete', false)
        ->assertSee('data-photo-delete-mobile', false)
        ->assertSee('keyup.window.delete', false)
        ->assertSee('keyup.window.backspace', false)
        ->assertSee('modal:delete-photo-open', false);
});

test('pressing delete asks for confirmation before removing the photo', function () {
    $gallery = Gallery::factory()->for($this->team)->create();
    $photo = Photo::factory()->for($gallery)->create();

    $this->actingAs($this->user);

    $page = visit("/galleries/{$gallery->id}/photos/{$photo->id}");
    $page->wait(0.5);
    $page->script("window.dispatchEvent(new KeyboardEvent('keyup', { key: 'Delete', bubbles: true }))");
    $page->wait(0.3);

    expect($page->script(<<<'JS'
        [...document.querySelectorAll('[role="dialog"]')]
            .some((dialog) => getComputedStyle(dialog).display !== 'none'
                && dialog.innerText.includes('Delete photo?'))
    JS))->toBeTrue();

    expect(Photo::find($photo->id))->not->toBeNull();
})->group('browser');

test('the quick delete button asks for confirmation before removing the photo', function () {
    $gallery = Gallery::factory()->for($this->team)->create();
    $photo = Photo::factory()->for($gallery)->create();

    $this->actingAs($this->user);

    $page = visit("/galleries/{$gallery->id}/photos/{$photo->id}");
    $page->wait(0.5);
    $page->click('[data-photo-delete]');
    $page->wait(0.3);

    expect($page->script(<<<'JS'
        [...document.querySelectorAll('[role="dialog"]')]
            .some((dialog) => getComputedStyle(dialog).display !== 'none'
                && dialog.innerText.includes('Delete photo?'))
    JS))->toBeTrue();

    expect(Photo::find($photo->id))->not->toBeNull();
})->group('browser');

test('the quick delete button is reachable at the bottom of the mobile viewer', function () {
    $gallery = Gallery::factory()->for($this->team)->create();
    $photo = Photo::factory()->for($gallery)->create();

    $this->actingAs($this->user);

    $page = visit("/galleries/{$gallery->id}/photos/{$photo->id}");
    $page->resize(375, 900);
    $page->wait(0.3);

    expect($page->script(<<<'JS'
        (() => {
            const button = document.querySelector('[data-photo-delete-mobile]')

            if (! button) return false

            const rect = button.getBoundingClientRect()

            return getComputedStyle(button).display !== 'none'
                && rect.top >= 0
                && rect.bottom <= window.innerHeight
                && rect.left >= 0
                && rect.right <= window.innerWidth
        })()
    JS))->toBeTrue();

    $page->click('[data-photo-delete-mobile]');
    $page->wait(0.3);

    expect($page->script(<<<'JS'
        [...document.querySelectorAll('[role="dialog"]')]
            .some((dialog) => getComputedStyle(dialog).display !== 'none'
                && dialog.innerText.includes('Delete photo?'))
    JS))->toBeTrue();

    expect(Photo::find($photo->id))->not->toBeNull();
})->group('browser');

test('a comment on a video stores the playhead second', function () {
    $gallery = Gallery::factory()->for($this->team)->create();
    $photo = Photo::factory()->video()->for($gallery)->create(['version_count' => 1]);

    Livewire::actingAs($this->user)
        ->test('pages::galleries.photos.show', ['photo' => $photo])
        ->call('setPendingTime', 42.37)
        ->assertSet('pendingTimeSeconds', 42.37)
        ->set('commentText', 'Cut here')
        ->call('addComment')
        ->assertHasNoErrors();

    $comment = $photo->comments()->first();

    expect($comment->time_seconds)->toBe(42.37)
        ->and($comment->x)->toBeNull()
        ->and($comment->annotated_version_number)->toBe(1)
        ->and($comment->hasTimestamp())->toBeTrue()
        ->and($comment->timecode())->toBe('00:42');
});

test('the playhead cannot be captured for a non-video photo', function () {
    $gallery = Gallery::factory()->for($this->team)->create();
    $photo = Photo::factory()->for($gallery)->create();

    Livewire::actingAs($this->user)
        ->test('pages::galleries.photos.show', ['photo' => $photo])
        ->call('setPendingTime', 12.0)
        ->assertStatus(404);
});

test('cancelling the timestamp leaves the next comment unanchored', function () {
    $gallery = Gallery::factory()->for($this->team)->create();
    $photo = Photo::factory()->video()->for($gallery)->create();

    Livewire::actingAs($this->user)
        ->test('pages::galleries.photos.show', ['photo' => $photo])
        ->call('setPendingTime', 30.0)
        ->call('cancelPendingTime')
        ->assertSet('pendingTimeSeconds', null)
        ->set('commentText', 'No anchor')
        ->call('addComment');

    expect($photo->comments()->first()->time_seconds)->toBeNull();
});

test('the video player renders for video photos and not for images', function () {
    $gallery = Gallery::factory()->for($this->team)->create();
    $video = Photo::factory()->video()->for($gallery)->create();

    actingAs($this->user)
        ->get("/galleries/{$gallery->id}/photos/{$video->id}")
        ->assertOk()
        ->assertSee('data-video-player', false)
        ->assertSee('data-video-toggle', false);

    $image = Photo::factory()->for($gallery)->create();

    actingAs($this->user)
        ->get("/galleries/{$gallery->id}/photos/{$image->id}")
        ->assertOk()
        ->assertDontSee('data-video-player', false);
});

test('timestamped comments render a seek chip and a marker', function () {
    $gallery = Gallery::factory()->for($this->team)->create();
    $photo = Photo::factory()->video()->for($gallery)->create();

    PhotoComment::factory()->for($photo)->atTime(83.0)->create(['comment' => 'Slow down here']);

    actingAs($this->user)
        ->get("/galleries/{$gallery->id}/photos/{$photo->id}")
        ->assertOk()
        ->assertSee('data-comment-time="01:23"', false)
        ->assertSee('Slow down here');
});

test('a deep link to a timestamped comment starts the video at that second', function () {
    $gallery = Gallery::factory()->for($this->team)->create();
    $photo = Photo::factory()->video()->for($gallery)->create();
    $comment = PhotoComment::factory()->for($photo)->atTime(61.5)->create();

    Livewire::actingAs($this->user)
        ->withQueryParams(['comment' => $comment->id])
        ->test('pages::galleries.photos.show', ['photo' => $photo])
        ->assertSet('seekToSeconds', 61.5)
        ->assertDispatched('focus-comment', id: $comment->id);
});

test('a video photo exposes its playable url', function () {
    Storage::fake('s3');
    config(['filesystems.disks.s3.url' => 'https://cdn.example.test/bucket']);

    $photo = Photo::factory()->video()->for(Gallery::factory()->for($this->team))->create([
        'disk' => 's3',
        'path' => 'galleries/clip.mp4',
    ]);

    expect($photo->video_url)->toBe('https://cdn.example.test/bucket/galleries/clip.mp4')
        ->and(Photo::factory()->for(Gallery::factory()->for($this->team))->create()->video_url)->toBeNull();
});

test('a video photo offers a synchronized comparison stage', function () {
    Storage::fake('s3');
    config(['filesystems.disks.s3.url' => 'https://cdn.example.test/bucket']);

    $gallery = Gallery::factory()->for($this->team)->create();
    $photo = Photo::factory()->video()->for($gallery)->create([
        'version_count' => 2,
        'path' => 'galleries/current.mp4',
        'disk' => 's3',
    ]);

    PhotoVersion::factory()->for($photo)->create([
        'version_number' => 1,
        'path' => 'galleries/old.mp4',
        'source_name' => 'old.mp4',
        'disk' => 's3',
    ]);

    $component = Livewire::actingAs($this->user)
        ->test('photo-versions', ['photo' => $photo, 'manageable' => true]);

    expect($component->instance()->isVideo)->toBeTrue()
        ->and($component->instance()->versions->first()['is_video'])->toBeTrue()
        ->and($component->instance()->versions->first()['video_url'])
        ->toBe('https://cdn.example.test/bucket/galleries/current.mp4');

    $component->assertSee('data-video-compare', false)
        ->assertSee('data-video-compare-right', false)
        ->assertSee('data-video-compare-toggle', false)
        ->assertSee('Restore')
        ->assertSee('Delete');
});

test('a video version can be restored', function () {
    Storage::fake('s3');
    Storage::disk('s3')->put('galleries/current.mp4', 'new');
    Storage::disk('s3')->put('galleries/old.mp4', 'old');

    $gallery = Gallery::factory()->for($this->team)->create();
    $photo = Photo::factory()->video()->for($gallery)->create([
        'version_count' => 2,
        'path' => 'galleries/current.mp4',
        'name' => 'current.mp4',
        'disk' => 's3',
    ]);

    $old = PhotoVersion::factory()->for($photo)->create([
        'version_number' => 1,
        'path' => 'galleries/old.mp4',
        'name' => 'old.mp4',
        'source_name' => 'old.mp4',
        'disk' => 's3',
    ]);

    Livewire::actingAs($this->user)
        ->test('photo-versions', ['photo' => $photo, 'manageable' => true])
        ->call('restoreVersion', $old->id)
        ->assertHasNoErrors();

    expect($photo->fresh()->path)->toBe('galleries/old.mp4')
        ->and($photo->fresh()->current_version_id)->toBe($old->id);
});

test('a superseded video version can be deleted', function () {
    Storage::fake('s3');
    Storage::disk('s3')->put('galleries/current.mp4', 'new');
    Storage::disk('s3')->put('galleries/old.mp4', 'old');

    $gallery = Gallery::factory()->for($this->team)->create();
    $photo = Photo::factory()->video()->for($gallery)->create([
        'version_count' => 3,
        'path' => 'galleries/current.mp4',
        'name' => 'current.mp4',
        'disk' => 's3',
    ]);

    PhotoVersion::factory()->for($photo)->create([
        'version_number' => 1,
        'path' => 'galleries/oldest.mp4',
        'disk' => 's3',
    ]);

    $superseded = PhotoVersion::factory()->for($photo)->create([
        'version_number' => 2,
        'path' => 'galleries/old.mp4',
        'disk' => 's3',
    ]);

    Livewire::actingAs($this->user)
        ->test('photo-versions', ['photo' => $photo, 'manageable' => true])
        ->call('deleteVersion', $superseded->id)
        ->assertHasNoErrors();

    expect(PhotoVersion::find($superseded->id))->toBeNull();
});

test('a shared viewer compares video versions without restore or delete', function () {
    Storage::fake('s3');

    $gallery = Gallery::factory()->shared()->create([
        'ulid' => '0123ABC',
        'is_share_versions_visible' => true,
    ]);
    $photo = Photo::factory()->video()->for($gallery)->create(['version_count' => 2]);

    PhotoVersion::factory()->for($photo)->create([
        'version_number' => 1,
        'path' => 'galleries/old.mp4',
        'disk' => 's3',
    ]);

    get('/shares/0123ABC/photos/'.$photo->id)
        ->assertOk()
        ->assertSee('data-photo-versions', false);

    $component = Livewire::test('photo-versions', ['photo' => $photo, 'manageable' => false])
        ->assertSee('data-video-compare', false)
        ->assertDontSee('wire:click="restoreVersion', false)
        ->assertDontSee('wire:click="deleteVersion', false);
});

test('adding a timestamped comment pushes the new rail markers to the player', function () {
    $gallery = Gallery::factory()->for($this->team)->create();
    $photo = Photo::factory()->video()->for($gallery)->create();

    $component = Livewire::actingAs($this->user)
        ->test('pages::galleries.photos.show', ['photo' => $photo])
        ->call('setPendingTime', 15.0)
        ->set('commentText', 'Marker here')
        ->call('addComment');

    expect($component->instance()->videoMarkers)->toHaveCount(1)
        ->and($component->instance()->videoMarkers[0]['label'])->toBe('00:15');

    $component->assertDispatched('video-markers-updated', photoId: $photo->id);
});

test('deleting a timestamped comment refreshes the markers', function () {
    $gallery = Gallery::factory()->for($this->team)->create();
    $photo = Photo::factory()->video()->for($gallery)->create();
    $comment = PhotoComment::factory()->for($photo)->atTime(20.0)->create();

    Livewire::actingAs($this->team->owner)
        ->test('pages::galleries.photos.show', ['photo' => $photo])
        ->call('deleteComment', $comment->id)
        ->assertDispatched('video-markers-updated', photoId: $photo->id);
});

test('the video comparison opens with two players and one control bar', function () {
    Storage::fake('s3');
    config(['filesystems.disks.s3.url' => 'https://cdn.example.test/bucket']);

    $gallery = Gallery::factory()->for($this->team)->create();
    $photo = Photo::factory()->video()->for($gallery)->create([
        'version_count' => 2,
        'path' => 'galleries/current.mp4',
        'disk' => 's3',
    ]);

    PhotoVersion::factory()->for($photo)->create([
        'version_number' => 1,
        'path' => 'galleries/old.mp4',
        'disk' => 's3',
    ]);

    $this->actingAs($this->user);

    $page = visit("/galleries/{$gallery->id}/photos/{$photo->id}");
    $page->wait(0.5);

    $page->click('[data-photo-versions]');
    $page->wait(0.5);

    $state = $page->script(<<<'JS'
        (() => {
            const stage = document.querySelector('[data-video-compare]')

            if (! stage) {
                return { stage: false }
            }

            return {
                stage: true,
                players: stage.querySelectorAll('video').length,
                controls: stage.querySelectorAll('[data-video-compare-controls]').length,
                leftSrc: stage.querySelector('[data-video-compare-left]').getAttribute('src'),
                rightSrc: stage.querySelector('[data-video-compare-right]').getAttribute('src'),
                rightMuted: stage.querySelector('[data-video-compare-right]').muted,
            }
        })()
    JS);

    expect($state)->toBe([
        'stage' => true,
        'players' => 2,
        'controls' => 1,
        'leftSrc' => 'https://cdn.example.test/bucket/galleries/current.mp4',
        'rightSrc' => 'https://cdn.example.test/bucket/galleries/old.mp4',
        'rightMuted' => true,
    ]);
});

/*
 * Regression: the version badge showed `version_count`, a monotonically
 * growing sequence counter, so deleting every retained version still showed
 * "2 versions" on the wall and in the viewer.
 */
test('the version badge counts stored versions, not the sequence counter', function () {
    Storage::fake('s3');

    $gallery = Gallery::factory()->for($this->team)->create();
    $photo = Photo::factory()->for($gallery)->create([
        'version_count' => 3,
        'status' => 'processed',
        'path' => 'galleries/current.jpg',
    ]);

    $first = PhotoVersion::factory()->for($photo)->create(['version_number' => 1, 'path' => 'galleries/v1.jpg']);
    PhotoVersion::factory()->for($photo)->create(['version_number' => 2, 'path' => 'galleries/v2.jpg']);

    expect($photo->fresh()->versionTotalCount())->toBe(3)
        ->and($photo->fresh()->hasStoredVersions())->toBeTrue();

    $first->delete();

    expect($photo->fresh()->versionTotalCount())->toBe(2)
        ->and($photo->fresh()->hasStoredVersions())->toBeTrue();
});

test('the wall hides the version badge once every version is deleted', function () {
    Storage::fake('s3');

    $gallery = Gallery::factory()->for($this->team)->create();
    $photo = Photo::factory()->for($gallery)->create([
        'version_count' => 2,
        'status' => 'processed',
        'path' => 'galleries/current.jpg',
    ]);

    $version = PhotoVersion::factory()->for($photo)->create(['version_number' => 1, 'path' => 'galleries/v1.jpg']);

    Livewire::actingAs($this->user)
        ->test('photo-versions', ['photo' => $photo, 'manageable' => true])
        ->assertSee('data-photo-versions', false);

    $version->delete();

    $fresh = $photo->fresh();

    expect($fresh->hasStoredVersions())->toBeFalse()
        ->and($fresh->versionTotalCount())->toBe(1);

    Livewire::actingAs($this->user)
        ->test('photo-versions', ['photo' => $fresh, 'manageable' => true])
        ->assertDontSee('data-photo-versions', false);
});

test('deleting a version invalidates the cached gallery listing', function () {
    Storage::fake('s3');

    $gallery = Gallery::factory()->for($this->team)->create();
    $photo = Photo::factory()->for($gallery)->create([
        'version_count' => 2,
        'status' => 'processed',
        'path' => 'galleries/current.jpg',
    ]);

    $version = PhotoVersion::factory()->for($photo)->create(['version_number' => 1, 'path' => 'galleries/v1.jpg']);

    // Warm the cache the wall reads its ordered ids from.
    $before = Photo::galleryCacheVersion($gallery->id);

    $version->delete();

    expect(Photo::galleryCacheVersion($gallery->id))->toBeGreaterThan($before);
});
