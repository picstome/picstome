<?php

use App\Models\Gallery;
use App\Models\Photo;
use App\Models\PhotoComment;
use App\Models\PhotoVersion;
use App\Models\User;
use App\Notifications\SelectionLimitReached;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('s3');
});

test('shared viewer never exposes photographer rating controls', function () {
    $gallery = Gallery::factory()->shared()->selectable()->create(['ulid' => '0123ABC']);
    $photo = Photo::factory()->for($gallery)->rated(4)->favorited()->create();

    Livewire::test('pages::shares.photos.show', ['gallery' => $gallery, 'photo' => $photo])
        ->assertDontSeeHtml('data-photo-rating')
        ->assertDontSeeHtml('data-rating-stars')
        ->assertDontSeeHtml('data-photo-info');
});

test('photo can be viewed when the gallery is shared', function () {
    $gallery = Gallery::factory()->shared()->has(Photo::factory())->create(['ulid' => '0123ABC']);

    $response = get('/shares/0123ABC/photos/1')
        ->assertStatus(200);
});

test('the back link points to the shared photo position in the gallery', function () {
    $gallery = Gallery::factory()->shared()->create(['ulid' => '0123ABC']);
    $photo = Photo::factory()->for($gallery)->create();

    $component = Livewire::test('pages::shares.photos.show', ['gallery' => $gallery, 'photo' => $photo]);

    expect($component->instance()->galleryUrl)->toBe(
        route('shares.show', [
            'gallery' => $gallery,
            'slug' => $gallery->slug,
            'photo' => $photo->id,
        ]).'#photo-'.$photo->id
    );
});

test('pdf photo in a shared gallery shows an open button pointing at the inline route', function () {
    $gallery = Gallery::factory()->shared()->has(Photo::factory()->pdf())->create(['ulid' => '0123ABC']);

    $response = get('/shares/0123ABC/photos/1');

    $response->assertStatus(200);
    $response->assertSee('Open PDF');
    $response->assertSee('/shares/0123ABC/photos/1/pdf');
});

test('photo cannot be viewed unless the gallery is shared', function () {
    $gallery = Gallery::factory(['ulid' => '0123ABC'])->unshared()->has(Photo::factory())->create();

    $response = get('/shares/0123ABC/photos/1')
        ->assertStatus(404);
});

test('favorite photo when gallery is selectable', function () {
    $gallery = Gallery::factory()->shared()->selectable()->has(Photo::factory()->unfavorited())->create();

    expect($gallery->photos()->first()->isFavorited())->toBeFalse();

    $component = Livewire::test('pages::shares.photos.show', ['gallery' => $gallery, 'photo' => $gallery->photos()->first()])
        ->call('favorite');

    expect($gallery->photos()->first()->isFavorited())->toBeTrue()
        ->and($gallery->photos()->first()->rating)->toBe(5);
});

test('photo cannot be favorited when the gallery is not selectable', function () {
    $gallery = Gallery::factory()->shared()->unselectable()->has(Photo::factory()->unfavorited())->create();

    expect($gallery->photos()->first()->isFavorited())->toBeFalse();

    $component = Livewire::test('pages::shares.photos.show', ['gallery' => $gallery, 'photo' => $gallery->photos()->first()])
        ->call('favorite');

    expect($gallery->photos()->first()->isFavorited())->toBeFalse();
});

test('favoriting the photo that reaches the selection limit dispatches a selection limit reached event', function () {
    $gallery = Gallery::factory()->shared()->selectable(limit: 5)->has(Photo::factory()->favorited()->count(4))->create();
    $gallery->photos()->save(Photo::factory()->unfavorited()->make());

    $component = Livewire::test('pages::shares.photos.show', ['gallery' => $gallery, 'photo' => $gallery->photos()->unfavorited()->first()])->call('favorite');

    $component->assertDispatched('selection-limit-reached');
});

test('favoriting a photo beyond the selection limit does not dispatch a selection limit reached event', function () {
    $gallery = Gallery::factory()->shared()->selectable(limit: 5)->has(Photo::factory()->favorited()->count(5))->create();
    $gallery->photos()->save(Photo::factory()->unfavorited()->make());

    $component = Livewire::test('pages::shares.photos.show', ['gallery' => $gallery, 'photo' => $gallery->photos()->unfavorited()->first()])->call('favorite');

    $component->assertNotDispatched('selection-limit-reached');
});

test('unfavoriting a photo at the selection limit does not dispatch a selection limit reached event', function () {
    $gallery = Gallery::factory()->shared()->selectable(limit: 2)->has(Photo::factory()->favorited()->count(2))->create();
    $photo = $gallery->photos()->favorited()->first();

    $component = Livewire::test('pages::shares.photos.show', ['gallery' => $gallery, 'photo' => $photo])->call('favorite');

    $component->assertNotDispatched('selection-limit-reached');

    expect($photo->fresh()->isFavorited())->toBeFalse();
});

test('reaching the selection limit from the photo page notifies the gallery owner', function () {
    Notification::fake();

    $gallery = Gallery::factory()->shared()->selectable(limit: 2)->has(Photo::factory()->favorited())->create();
    $photo = Photo::factory()->for($gallery)->unfavorited()->create();

    Livewire::test('pages::shares.photos.show', ['gallery' => $gallery, 'photo' => $photo])->call('favorite');

    Notification::assertSentTo($gallery->team->owner, SelectionLimitReached::class);
});

test('unauthenticated visitors to a password-protected gallery are redirected to the unlock page', function () {
    $gallery = Gallery::factory()
        ->shared()
        ->protected()
        ->has(Photo::factory())->create(['ulid' => '0123ABC']);

    $response = get('/shares/0123ABC/photos/1')->assertRedirect('/shares/0123ABC/unlock');
});

test('visitors with unlocked gallery can view the password-protected photo', function () {
    $gallery = Gallery::factory()
        ->shared()
        ->protected()
        ->has(Photo::factory())->create(['ulid' => '0123ABC']);

    session()->put('unlocked_gallery_ulids', ['0123ABC']);

    $response = get('/shares/0123ABC/photos/1')->assertStatus(200);
});

test('favorite photo via browser on watermarked gallery', function () {
    $gallery = Gallery::factory()->shared()->selectable()->watermarked()->has(Photo::factory()->unfavorited())->create(['ulid' => 'test123']);

    $photo = $gallery->photos()->first();

    $page = visit("/shares/test123/photos/{$photo->id}");

    // The dock is icon-only: the F shortcut is the stable way in, and the
    // button itself is reached through its data hook.
    $page->script("window.dispatchEvent(new KeyboardEvent('keyup', { key: 'f', bubbles: true }))");
    $page->wait(0.3);

    expect($photo->fresh()->isFavorited())->toBeTrue();
})->group('browser');

test('comment is required when adding a comment to a shared photo', function () {
    $gallery = Gallery::factory()->shared()->has(Photo::factory())->create([
        'are_comments_enabled' => true,
    ]);
    $photo = $gallery->photos()->first();
    $user = $gallery->team->owner;

    Livewire::actingAs($user)
        ->test('pages::shares.photos.show', ['gallery' => $gallery, 'photo' => $photo])
        ->set('commentText', '')
        ->call('addComment')
        ->assertHasErrors(['commentText' => 'required']);

    expect($photo->comments()->count())->toBe(0);
});

test('user cannot delete another user\'s comment on a shared photo', function () {
    $gallery = Gallery::factory()->shared()->has(Photo::factory())->create([
        'are_comments_enabled' => true,
    ]);
    $photo = $gallery->photos()->first();
    $user = $gallery->team->owner;
    $otherUser = User::factory()->create();

    $comment = PhotoComment::factory()->for($photo)->create([
        'user_id' => $user->id,
    ]);

    Livewire::actingAs($otherUser)
        ->test('pages::shares.photos.show', ['gallery' => $gallery, 'photo' => $photo])
        ->call('deleteComment', $comment->id)
        ->assertStatus(403);

    expect($photo->comments()->find($comment->id))->not()->toBeNull();
});

test('guest can add a comment to a shared photo', function () {
    $gallery = Gallery::factory()->shared()->has(Photo::factory())->create([
        'are_comments_enabled' => true,
    ]);
    $photo = $gallery->photos()->first();

    Livewire::test('pages::shares.photos.show', ['gallery' => $gallery, 'photo' => $photo])
        ->set('commentText', 'Guest comment')
        ->call('addComment')
        ->assertHasNoErrors();

    $comment = $photo->comments()->latest()->first();

    expect($comment->comment)->toBe('Guest comment');
    expect($comment->user_id)->toBeNull();
});

test('comments are not allowed when comments are disabled', function () {
    $gallery = Gallery::factory()->shared()->has(Photo::factory())->create(['are_comments_enabled' => false]);
    $photo = $gallery->photos()->first();

    $component = Livewire::test('pages::shares.photos.show', ['gallery' => $gallery, 'photo' => $photo])
        ->set('commentText', 'Should not work')
        ->call('addComment');

    $component->assertStatus(403);

    $comment = PhotoComment::factory()->for($photo)->create();

    $component = Livewire::test('pages::shares.photos.show', ['gallery' => $gallery, 'photo' => $photo])
        ->call('deleteComment', $comment->id);

    $component->assertStatus(403);
});

test('only the team owner can add a comment as an authenticated user', function () {
    $gallery = Gallery::factory()->shared()->has(Photo::factory())->create([
        'are_comments_enabled' => true,
    ]);
    $photo = $gallery->photos()->first();
    $owner = $gallery->team->owner;

    Livewire::actingAs($owner)
        ->test('pages::shares.photos.show', ['gallery' => $gallery, 'photo' => $photo])
        ->set('commentText', 'This is a shared photo comment!')
        ->call('addComment')
        ->assertHasNoErrors();

    expect($photo->comments()->count())->toBe(1);

    $comment = $photo->comments()->first();

    expect($comment->comment)->toBe('This is a shared photo comment!');
    expect($comment->user_id)->toBe($owner->id);

    $otherUser = User::factory()->create();

    Livewire::actingAs($otherUser)
        ->test('pages::shares.photos.show', ['gallery' => $gallery, 'photo' => $photo])
        ->set('commentText', 'Should not be allowed')
        ->call('addComment')
        ->assertStatus(403);

    expect($photo->comments()->count())->toBe(1);
});

test('only the team owner can delete any comment', function () {
    $gallery = Gallery::factory()->shared()->has(Photo::factory())->create([
        'are_comments_enabled' => true,
    ]);
    $photo = $gallery->photos()->first();
    $owner = $gallery->team->owner;

    $guestComment = $photo->comments()->create([
        'user_id' => null,
        'comment' => 'Guest comment',
    ]);

    $userComment = $photo->comments()->create([
        'user_id' => $owner->id,
        'comment' => 'User comment',
    ]);

    Livewire::actingAs($owner)
        ->test('pages::shares.photos.show', ['gallery' => $gallery, 'photo' => $photo])
        ->call('deleteComment', $guestComment->id)
        ->assertHasNoErrors();

    expect($photo->comments()->find($guestComment->id))->toBeNull();

    Livewire::actingAs($owner)
        ->test('pages::shares.photos.show', ['gallery' => $gallery, 'photo' => $photo])
        ->call('deleteComment', $userComment->id)
        ->assertHasNoErrors();

    expect($photo->comments()->find($userComment->id))->toBeNull();

    $otherUser = User::factory()->create();

    $anotherComment = $photo->comments()->create([
        'user_id' => null,
        'comment' => 'Another guest comment',
    ]);

    Livewire::actingAs($otherUser)
        ->test('pages::shares.photos.show', ['gallery' => $gallery, 'photo' => $photo])
        ->call('deleteComment', $anotherComment->id)
        ->assertStatus(403);

    expect($photo->comments()->find($anotherComment->id))->not()->toBeNull();
});

test('shared gallery can show and compare versions when enabled', function () {
    // Downloadable so both actions are on screen and their order can be
    // asserted below.
    $gallery = Gallery::factory()->shared()->downloadable()->create([
        'ulid' => '0123ABC',
        'is_share_versions_visible' => true,
    ]);

    $photo = Photo::factory()->for($gallery)->create(['version_count' => 2]);

    PhotoVersion::factory()->for($photo)->create([
        'version_number' => 1,
        'path' => 'galleries/old.jpg',
    ]);

    $response = get('/shares/0123ABC/photos/1');

    $response->assertStatus(200);
    $response->assertSee('Compare versions');
    // The dock is icon-only: the versions trigger is reached by its hook, and
    // its label lives in the tooltip / accessible name.
    $response->assertSee('data-photo-versions', false);
    $response->assertSee('Versions (2)');
    $response->assertDontSee('Restore');

    // Order: versions sit immediately left of download.
    $html = $response->getContent();
    $versionsPosition = strpos($html, 'data-photo-versions');
    $downloadPosition = strpos($html, 'aria-label="Download"');
    expect($downloadPosition)->toBeInt()
        ->and($versionsPosition)->toBeInt()
        ->and($versionsPosition)->toBeLessThan($downloadPosition);
});

test('shared gallery hides versions by default', function () {
    $gallery = Gallery::factory()->shared()->create(['ulid' => '0123ABC']);

    $photo = Photo::factory()->for($gallery)->create(['version_count' => 2]);

    PhotoVersion::factory()->for($photo)->create([
        'version_number' => 1,
        'path' => 'galleries/old.jpg',
    ]);

    $response = get('/shares/0123ABC/photos/1');

    $response->assertStatus(200);
    $response->assertDontSee('Compare versions');
});

test('shared viewers cannot restore versions', function () {
    $gallery = Gallery::factory()->shared()->create(['ulid' => '0123ABC']);
    $photo = Photo::factory()->for($gallery)->create([
        'path' => 'galleries/current.jpg',
        'version_count' => 2,
    ]);
    $version = PhotoVersion::factory()->for($photo)->create([
        'version_number' => 1,
        'path' => 'galleries/old.jpg',
    ]);

    Livewire::test('photo-versions', ['photo' => $photo, 'manageable' => false])
        ->call('restoreVersion', $version->id);

    expect($photo->fresh()->path)->toBe('galleries/current.jpg');
});

test('guests cannot manage versions even with a tampered manageable flag', function () {
    $gallery = Gallery::factory()->shared()->create(['ulid' => '0123ABC']);
    $photo = Photo::factory()->for($gallery)->create([
        'path' => 'galleries/current.jpg',
        'version_count' => 2,
    ]);
    $version = PhotoVersion::factory()->for($photo)->create([
        'version_number' => 1,
        'path' => 'galleries/old.jpg',
    ]);

    Livewire::test('photo-versions', ['photo' => $photo, 'manageable' => true])
        ->call('restoreVersion', $version->id);

    expect($photo->fresh()->path)->toBe('galleries/current.jpg');
});

test('a guest can pin a comment to a shared photo when annotations are enabled', function () {
    $gallery = Gallery::factory()->shared()->has(Photo::factory())->create([
        'are_comments_enabled' => true,
        'are_annotations_enabled' => true,
    ]);
    $photo = $gallery->photos()->first();

    Livewire::test('pages::shares.photos.show', ['gallery' => $gallery, 'photo' => $photo])
        ->call('addAnnotation', 12.5, 87.5)
        ->assertSet('pendingPinX', 12.5)
        ->assertSet('pendingPinY', 87.5)
        ->set('commentText', 'Retouch this spot')
        ->call('addComment')
        ->assertHasNoErrors();

    $comment = $photo->comments()->first();

    expect($comment->x)->toBe(12.5);
    expect($comment->y)->toBe(87.5);
    expect($comment->annotated_version_number)->toBe(1);
    expect($comment->user_id)->toBeNull();
});

test('a guest cannot pin annotations when the gallery setting is disabled', function () {
    $gallery = Gallery::factory()->shared()->has(Photo::factory())->create([
        'are_comments_enabled' => true,
        'are_annotations_enabled' => false,
    ]);
    $photo = $gallery->photos()->first();

    Livewire::test('pages::shares.photos.show', ['gallery' => $gallery, 'photo' => $photo])
        ->call('addAnnotation', 10.0, 10.0)
        ->assertStatus(403);
});

test('the team owner can pin annotations on a shared photo', function () {
    $gallery = Gallery::factory()->shared()->has(Photo::factory())->create([
        'are_comments_enabled' => true,
        'are_annotations_enabled' => false,
    ]);
    $photo = $gallery->photos()->first();

    Livewire::actingAs($gallery->team->owner)
        ->test('pages::shares.photos.show', ['gallery' => $gallery, 'photo' => $photo])
        ->call('addAnnotation', 33.0, 66.0)
        ->assertSet('pendingPinX', 33.0)
        ->assertSet('pendingPinY', 66.0);
});

test('pins are rendered on the shared photo and removed with their comment', function () {
    $gallery = Gallery::factory()->shared()->create([
        'ulid' => '0123ABC',
        'are_comments_enabled' => true,
        'are_annotations_enabled' => true,
    ]);
    $photo = Photo::factory()->for($gallery)->create();

    $comment = PhotoComment::factory()->for($photo)->create([
        'x' => 30,
        'y' => 70,
        'annotated_version_number' => 1,
    ]);

    get('/shares/0123ABC/photos/'.$photo->id)
        ->assertOk()
        ->assertSee('left: 30%', false)
        ->assertSee('top: 70%', false)
        ->assertSee('photo-comment-'.$comment->id, false);

    Livewire::actingAs($gallery->team->owner)
        ->test('pages::shares.photos.show', ['gallery' => $gallery, 'photo' => $photo])
        ->call('deleteComment', $comment->id)
        ->assertHasNoErrors();

    get('/shares/0123ABC/photos/'.$photo->id)
        ->assertOk()
        ->assertDontSee('left: 30%', false);
});

test('annotation pins are positioned after the image loads', function () {
    $gallery = Gallery::factory()->shared()->create([
        'are_comments_enabled' => true,
        'are_annotations_enabled' => true,
    ]);
    $photo = Photo::factory()->for($gallery)->create();
    PhotoComment::factory()->for($photo)->create([
        'comment' => 'Pinned comment',
        'x' => 50,
        'y' => 50,
        'annotated_version_number' => 1,
    ]);

    $page = visit("/shares/{$gallery->ulid}/photos/{$photo->id}");
    $page->script("const image = document.querySelector('#photo img'); image.removeAttribute('srcset'); image.src = '/favicon-96x96.png';");
    $page->wait(0.6);

    expect($page->script("document.querySelectorAll('button[title=\"Photo annotation\"]').length"))
        ->toBeGreaterThan(0);

    expect($page->script("(() => { const pin = document.querySelector('button[title=\"Photo annotation\"]'); const image = document.querySelector('#photo img'); const p = pin.getBoundingClientRect(); const i = image.getBoundingClientRect(); return p.left >= i.left - 2 && p.right <= i.right + 2 && p.top >= i.top - 2 && p.bottom <= i.bottom + 2; })()"))
        ->toBeTrue();

    $page->assertNoJavaScriptErrors();
})->group('browser');

test('the shared comment list flags annotations made on an older version', function () {
    $gallery = Gallery::factory()->shared()->create([
        'ulid' => '0123ABC',
        'are_comments_enabled' => true,
        'are_annotations_enabled' => true,
    ]);
    $photo = Photo::factory()->for($gallery)->create(['version_count' => 1]);

    PhotoComment::factory()->for($photo)->create([
        'x' => 30,
        'y' => 70,
        'annotated_version_number' => 1,
    ]);

    $photo->update(['version_count' => 2]);

    get('/shares/0123ABC/photos/'.$photo->id)
        ->assertOk()
        ->assertSee('Version 1')
        ->assertSee('This annotation was made on version 1.');
});

test('annotation mode can be toggled from the toolbar in the browser', function () {
    $gallery = Gallery::factory()->shared()->create([
        'ulid' => 'annotate1',
        'are_comments_enabled' => true,
        'are_annotations_enabled' => true,
    ]);
    $photo = Photo::factory()->for($gallery)->create();

    $page = visit("/shares/annotate1/photos/{$photo->id}");

    // The dock is icon-only: labels live in the tooltip / accessible name,
    // not in visible text.
    expect($page->script(<<<'JS'
        (() => {
            const enter = document.querySelector('[data-annotate-button]')

            return enter ? enter.getAttribute('aria-label') : null
        })()
    JS))->toBe('Annotate');

    $page->click('[data-annotate-button]');
    $page->wait(0.2);

    $state = $page->script(<<<'JS'
        (() => {
            const exit = document.querySelector('[data-annotate-exit]')

            return {
                label: exit ? exit.getAttribute('aria-label') : null,
                visible: exit ? getComputedStyle(exit).display !== 'none' : false,
            }
        })()
    JS);

    expect($state)->toBe(['label' => 'Exit annotation mode', 'visible' => true]);

    $page->assertSee('Click on the photo to mark a spot');
})->group('browser');

test('a guest can create an annotation from the browser', function () {
    $gallery = Gallery::factory()->shared()->create([
        'ulid' => 'annotate2',
        'are_comments_enabled' => true,
        'are_annotations_enabled' => true,
    ]);
    $photo = Photo::factory()->for($gallery)->create();

    $page = visit("/shares/annotate2/photos/{$photo->id}");

    $page->script(<<<'JS'
        const image = document.querySelector('#photo img')
        image.removeAttribute('srcset')
        image.src = '/favicon-96x96.png'
    JS);

    $page->wait(0.3);

    $page->script("window.dispatchEvent(new CustomEvent('toggle-annotations'))");
    $page->wait(0.3);

    // The comments button hides and the toolbar stays clickable above the
    // annotation capture layer. The dock is icon-only, so "Annotate" is an
    // accessible name, not text.
    expect($page->script(<<<'JS'
        (() => {
            const enter = document.querySelector('[data-annotate-button]')

            return enter ? enter.getAttribute('aria-label') : null
        })()
    JS))->toBe('Annotate');

    $page->assertDontSee('Add comment')
        ->click('[data-annotate-exit]');

    $page->script("window.dispatchEvent(new CustomEvent('toggle-annotations'))");
    $page->wait(0.3);

    $page->script(<<<'JS'
        const layer = document.querySelector('#photo .cursor-crosshair')
        const rect = layer.getBoundingClientRect()
        layer.dispatchEvent(new MouseEvent('click', {
            clientX: rect.left + rect.width / 2,
            clientY: rect.top + rect.height / 2,
            bubbles: true,
        }))
    JS);

    $page->wait(0.7);

    $page->type('[name=commentText]', 'Retouch this spot');
    $page->click('Submit');
    $page->wait(0.7);

    $comment = $photo->comments()->first();

    expect($comment)->not->toBeNull();
    expect($comment->comment)->toBe('Retouch this spot');
    expect($comment->x)->toBeGreaterThan(49.9)->toBeLessThan(50.1);
    expect($comment->y)->toBeGreaterThan(49.9)->toBeLessThan(50.1);
    expect($comment->annotated_version_number)->toBe(1);

    $page->wait(0.5);

    // The annotation mode stays active until the user exits it.
    $page->assertSee('Click on the photo to mark a spot');

    $page->script("window.dispatchEvent(new CustomEvent('toggle-annotations'))");
    $page->wait(0.2);

    $pinIsVisible = fn (): bool => (bool) $page->script(<<<'JS'
        (() => {
            const pin = document.querySelector('#photo button[title="Photo annotation"]')

            return pin ? pin.offsetParent !== null : false
        })()
    JS);

    // The dock is icon-only, so toggles are reached by their data hooks.
    $clickToggle = function (string $selector) use ($page): void {
        $page->script("document.querySelector('{$selector}')?.click()");

        $page->wait(0.2);
    };

    expect($pinIsVisible())->toBeTrue();

    // One button, two colours: pins visible = green ("Hide annotations"),
    // pins hidden = sage ("Show annotations"). Same reading as the comments
    // button, where a filled tone means there is something to look at.
    expect($page->script(<<<'JS'
        (() => {
            const toggle = document.querySelector('[data-annotations-hide], [data-annotations-show]')

            return toggle ? [
                toggle.getAttribute('aria-label'),
                getComputedStyle(toggle).backgroundColor,
            ] : null
        })()
    JS))->toBe(['Hide annotations', 'rgb(49, 109, 97)']);

    $clickToggle('[data-annotations-hide]');
    expect($pinIsVisible())->toBeFalse();

    expect($page->script(<<<'JS'
        (() => {
            const toggle = document.querySelector('[data-annotations-hide], [data-annotations-show]')

            return toggle ? [
                toggle.getAttribute('aria-label'),
                getComputedStyle(toggle).backgroundColor,
            ] : null
        })()
    JS))->toBe(['Show annotations', 'rgb(198, 216, 175)']);

    $clickToggle('[data-annotations-show]');
    expect($pinIsVisible())->toBeTrue();

    // The pins toggle stays reachable. Whether the dock stays visible during
    // annotation mode is covered by the dedicated toggle test above.
    expect($page->script(<<<'JS'
        (() => {
            const button = document.querySelector('[data-annotations-hide]')

            return button ? button.offsetParent !== null : false
        })()
    JS))->toBeTrue();

    // The choice survives a new render: the key the overlay reads is the same
    // one the toggle writes, so the state restored on the next photo is the
    // one the photographer last chose (here: visible again).
    expect($page->script("localStorage.getItem('picstome-annotations-visible')"))
        ->toBe('1');
})->group('browser');

test('the hidden annotations state is kept while moving between photos', function () {
    $gallery = Gallery::factory()->shared()->create([
        'ulid' => 'annotate3',
        'are_comments_enabled' => true,
        'are_annotations_enabled' => true,
    ]);
    $first = Photo::factory()->for($gallery)->create();
    $second = Photo::factory()->for($gallery)->create();

    foreach ([$first, $second] as $photo) {
        PhotoComment::factory()->for($photo)->create([
            'x' => 40,
            'y' => 45,
            'annotated_version_number' => 1,
        ]);
    }

    $page = visit("/shares/annotate3/photos/{$first->id}");

    $pinIsVisible = fn (): bool => (bool) $page->script(<<<'JS'
        (() => {
            const pin = document.querySelector('#photo button[title="Photo annotation"]')

            return pin ? pin.offsetParent !== null : false
        })()
    JS);

    expect($pinIsVisible())->toBeTrue();

    $page->script("document.querySelector('[data-annotations-hide]')?.click()");
    $page->wait(0.2);
    expect($pinIsVisible())->toBeFalse();

    // Navigate to the next photo the same way the arrow keys do.
    $page->script("Livewire.navigate(document.querySelector('[data-viewer-dock] a[aria-label=\"Next photo (→)\"]').href)");
    $page->wait(0.8);

    expect($page->script('window.location.pathname'))->toContain('/photos/'.$second->id)
        ->and($page->script("localStorage.getItem('picstome-annotations-visible')"))->toBe('0');

    expect($pinIsVisible())->toBeFalse();
    expect($page->script(<<<'JS'
        (() => {
            const show = document.querySelector('[data-annotations-show]')

            return show ? show.offsetParent !== null : false
        })()
    JS))->toBeTrue();
})->group('browser');

test('the annotations toggle appears only when the photo has annotations', function () {
    $gallery = Gallery::factory()->shared()->create([
        'ulid' => '0123ABC',
        'are_comments_enabled' => true,
        'are_annotations_enabled' => true,
    ]);
    $photo = Photo::factory()->for($gallery)->create();

    get('/shares/0123ABC/photos/'.$photo->id)
        ->assertOk()
        ->assertDontSee('Hide annotations');

    PhotoComment::factory()->for($photo)->create([
        'x' => 30,
        'y' => 70,
        'annotated_version_number' => 1,
    ]);

    get('/shares/0123ABC/photos/'.$photo->id)
        ->assertOk()
        ->assertSee('Hide annotations');
});

test('a platform admin can pin annotations on a shared photo they do not own', function () {
    config(['picstome.admin_emails' => ['admin@example.com']]);

    $gallery = Gallery::factory()->shared()->has(Photo::factory())->create([
        'are_comments_enabled' => true,
        'are_annotations_enabled' => true,
    ]);
    $photo = $gallery->photos()->first();
    $admin = User::factory()->create(['email' => 'admin@example.com']);

    Livewire::actingAs($admin)
        ->test('pages::shares.photos.show', ['gallery' => $gallery, 'photo' => $photo])
        ->call('addAnnotation', 15.0, 25.0)
        ->assertSet('pendingPinX', 15.0)
        ->set('commentText', 'Admin annotation')
        ->call('addComment')
        ->assertHasNoErrors();

    $comment = $photo->comments()->first();

    expect($comment->comment)->toBe('Admin annotation');
    expect($comment->user_id)->toBe($admin->id);
    expect($comment->x)->toBe(15.0);
    expect($comment->y)->toBe(25.0);
});

test('a platform admin can comment and delete comments on a shared photo', function () {
    config(['picstome.admin_emails' => ['admin@example.com']]);

    $gallery = Gallery::factory()->shared()->has(Photo::factory())->create([
        'are_comments_enabled' => true,
    ]);
    $photo = $gallery->photos()->first();
    $admin = User::factory()->create(['email' => 'admin@example.com']);

    $guestComment = $photo->comments()->create([
        'user_id' => null,
        'comment' => 'Guest comment',
    ]);

    Livewire::actingAs($admin)
        ->test('pages::shares.photos.show', ['gallery' => $gallery, 'photo' => $photo])
        ->set('commentText', 'Admin comment')
        ->call('addComment')
        ->assertHasNoErrors();

    expect($photo->comments()->where('user_id', $admin->id)->count())->toBe(1);

    Livewire::actingAs($admin)
        ->test('pages::shares.photos.show', ['gallery' => $gallery, 'photo' => $photo])
        ->call('deleteComment', $guestComment->id)
        ->assertHasNoErrors();

    expect($photo->comments()->find($guestComment->id))->toBeNull();
});

test('the mobile dock opens the comments modal', function () {
    $gallery = Gallery::factory()->shared()->create([
        'are_comments_enabled' => true,
        'are_annotations_enabled' => true,
    ]);
    $photo = Photo::factory()->for($gallery)->create();
    PhotoComment::factory()->for($photo)->create(['comment' => 'Mobile comment']);

    $page = visit("/shares/{$gallery->ulid}/photos/{$photo->id}");
    $page->resize(375, 900);
    $page->wait(0.2);

    // There is no separate mobile toolbar any more: the dock carries every
    // action on every width, so mobile reaches comments through it.
    $page->click('[data-photo-comments]');
    $page->wait(0.3);

    expect($page->script("getComputedStyle(document.querySelector('#add-comment')).display"))
        ->not->toBe('none');

    expect($page->script("document.querySelector('#add-comment').innerText"))
        ->toContain('Mobile comment');

    expect($page->script('document.documentElement.scrollWidth <= document.documentElement.clientWidth'))
        ->toBeTrue();
})->group('browser');

test('a guest can comment at a second of a shared video', function () {
    $gallery = Gallery::factory()->shared()->create([
        'ulid' => '0123ABC',
        'are_comments_enabled' => true,
    ]);
    $photo = Photo::factory()->video()->for($gallery)->create(['version_count' => 1]);

    Livewire::test('pages::shares.photos.show', ['gallery' => $gallery, 'photo' => $photo])
        ->call('setPendingTime', 12.5)
        ->assertSet('pendingTimeSeconds', 12.5)
        ->set('commentText', 'The client wants this shot trimmed')
        ->call('addComment')
        ->assertHasNoErrors();

    $comment = $photo->comments()->first();

    expect($comment->time_seconds)->toBe(12.5)
        ->and($comment->x)->toBeNull()
        ->and($comment->user_id)->toBeNull()
        ->and($comment->annotated_version_number)->toBe(1);
});

test('a guest cannot comment at a second when comments are disabled', function () {
    $gallery = Gallery::factory()->shared()->create([
        'ulid' => '0123ABC',
        'are_comments_enabled' => false,
    ]);
    $photo = Photo::factory()->video()->for($gallery)->create();

    Livewire::test('pages::shares.photos.show', ['gallery' => $gallery, 'photo' => $photo])
        ->call('setPendingTime', 5.0)
        ->assertStatus(403);
});

test('a guest cannot capture a time on a photo that is not a video', function () {
    $gallery = Gallery::factory()->shared()->create([
        'ulid' => '0123ABC',
        'are_comments_enabled' => true,
    ]);
    $photo = Photo::factory()->for($gallery)->create();

    Livewire::test('pages::shares.photos.show', ['gallery' => $gallery, 'photo' => $photo])
        ->call('setPendingTime', 5.0)
        ->assertStatus(404);
});

test('timestamped comment chips are clickable in the shared viewer', function () {
    $gallery = Gallery::factory()->shared()->create([
        'ulid' => '0123ABC',
        'are_comments_enabled' => true,
    ]);
    $photo = Photo::factory()->video()->for($gallery)->create();

    PhotoComment::factory()->for($photo)->atTime(95.0)->create(['comment' => 'Hold on this frame']);

    get('/shares/0123ABC/photos/'.$photo->id)
        ->assertOk()
        ->assertSee('data-comment-time="01:35"', false)
        ->assertSee('Hold on this frame')
        ->assertSee('data-video-player', false);
});

test('the timestamped comment deep link seeks the shared video', function () {
    $gallery = Gallery::factory()->shared()->create([
        'ulid' => '0123ABC',
        'are_comments_enabled' => true,
    ]);
    $photo = Photo::factory()->video()->for($gallery)->create();
    $comment = PhotoComment::factory()->for($photo)->atTime(37.25)->create();

    Livewire::withQueryParams(['comment' => $comment->id])
        ->test('pages::shares.photos.show', ['gallery' => $gallery, 'photo' => $photo])
        ->assertSet('seekToSeconds', 37.25)
        ->assertDispatched('focus-comment', id: $comment->id);
});

/*
 * Regression: a Blade directive (`@if`, `@unless`) inside an element tag makes
 * the compiler emit `<!--[if BLOCK]-->` markers in the middle of the attribute
 * list, which swallows every attribute after it. It silently broke the
 * favourite shortcut and prevented Livewire components from compiling.
 */
test('no blade block markers are emitted inside the viewer tag', function () {
    $gallery = Gallery::factory()->shared()->create([
        'ulid' => '0123ABC',
        'are_comments_enabled' => true,
    ]);
    $video = Photo::factory()->video()->for($gallery)->create();
    $image = Photo::factory()->for($gallery)->create();

    foreach ([$video, $image] as $photo) {
        $html = get("/shares/0123ABC/photos/{$photo->id}")->getContent();

        preg_match_all('/<[^>]*<!--\[if BLOCK\]>/', $html, $matches);

        expect($matches[0])->toBeEmpty("Block markers inside a tag on photo {$photo->id}");
    }
});

test('clicking a timestamp chip seeks the player and closes the composer', function () {
    $gallery = Gallery::factory()->shared()->create([
        'ulid' => '0123ABC',
        'are_comments_enabled' => true,
    ]);
    $photo = Photo::factory()->video()->for($gallery)->create();

    PhotoComment::factory()->for($photo)->atTime(30.0)->create(['comment' => 'Trim from here']);

    $page = visit("/shares/0123ABC/photos/{$photo->id}");
    $page->wait(0.5);

    // The stored file is not fetchable in the test environment, so the player
    // is given a fake duration: seeking is then observable on `currentTime`
    // instead of on a decoded frame.
    $page->script(<<<'JS'
        (() => {
            const video = document.querySelector('[data-video-player] video')

            Object.defineProperty(video, 'duration', { value: 120, configurable: true })
            video.dispatchEvent(new Event('loadedmetadata'))

            return true
        })()
    JS);

    $page->click('[data-photo-comments]');
    $page->wait(0.3);
    $page->click('[data-comment-time]');
    $page->wait(0.3);

    $state = $page->script(<<<'JS'
        (() => {
            const video = document.querySelector('[data-video-player] video')

            return {
                time: Math.round(video.currentTime),
                modalHidden: getComputedStyle(document.querySelector('#add-comment')).display === 'none',
            }
        })()
    JS);

    expect($state['modalHidden'])->toBeTrue()
        ->and($state['time'])->toBe(30);
})->group('browser');

test('the player resumes from the position remembered in this browser', function () {
    $gallery = Gallery::factory()->shared()->create(['ulid' => '0123ABC']);
    $photo = Photo::factory()->video()->for($gallery)->create();

    $page = visit("/shares/0123ABC/photos/{$photo->id}");
    $page->wait(0.5);

    $script = <<<JS
        (() => {
            const video = document.querySelector('[data-video-player] video')

            // Seed the remembered position the way a previous visit would, then
            // replay the metadata event the player listens to.
            window.localStorage.setItem('picstome-video-position:{$photo->id}', '45')
            Object.defineProperty(video, 'duration', { value: 120, configurable: true })
            video.dispatchEvent(new Event('loadedmetadata'))

            return { time: Math.round(video.currentTime) }
        })()
    JS;

    $state = $page->script($script);

    expect($state['time'])->toBe(45);
})->group('browser');
