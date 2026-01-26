<?php

use App\Models\Gallery;
use App\Models\Photo;
use App\Models\PhotoComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

test('photo can be viewed when the gallery is shared', function () {
    $gallery = Gallery::factory()->shared()->has(Photo::factory())->create(['ulid' => '0123ABC']);

    $response = get('/shares/0123ABC/photos/1')
        ->assertStatus(200);
});

test('photo cannot be viewed unless the gallery is shared', function () {
    $gallery = Gallery::factory(['ulid' => '0123ABC'])->unshared()->has(Photo::factory())->create();

    $response = get('/shares/0123ABC/photos/1')
        ->assertStatus(404);
});

test('favorite photo when gallery is selectable', function () {
    $gallery = Gallery::factory()->shared()->selectable()->has(Photo::factory()->unfavorited())->create();

    expect($gallery->photos()->first()->isFavorited())->toBeFalse();

    $component = Livewire::test('pages.shares.photos.show', ['photo' => $gallery->photos()->first()])
        ->call('favorite');

    expect($gallery->photos()->first()->isFavorited())->toBeTrue();
});

test('photo cannot be favorited when the gallery is not selectable', function () {
    $gallery = Gallery::factory()->shared()->unselectable()->has(Photo::factory()->unfavorited())->create();

    expect($gallery->photos()->first()->isFavorited())->toBeFalse();

    $component = Livewire::test('pages.shares.photos.show', ['photo' => $gallery->photos()->first()])
        ->call('favorite');

    expect($gallery->photos()->first()->isFavorited())->toBeFalse();
});

test('favoriting a photo dispatches a selection limit reached event when the gallery selection limit is exceeded', function () {
    $gallery = Gallery::factory()->shared()->selectable(limit: 5)->has(Photo::factory()->favorited()->count(5))->create();
    $gallery->photos()->save(Photo::factory()->unfavorited()->make());

    expect($gallery->photos()->favorited()->count())->toBe(5);

    $component = Livewire::test('pages.shares.photos.show', ['photo' => $gallery->photos()->unfavorited()->first()])->call('favorite');

    $component->assertDispatched('selection-limit-reached');
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

    session()->put('unlocked_gallery_ulid', '0123ABC');

    $response = get('/shares/0123ABC/photos/1')->assertStatus(200);
});

test('favorite photo via browser on watermarked gallery', function () {
    $gallery = Gallery::factory()->shared()->selectable()->watermarked()->has(Photo::factory()->unfavorited())->create(['ulid' => 'test123']);

    $photo = $gallery->photos()->first();

    $page = visit("/shares/test123/photos/{$photo->id}");

    $page->pressAndWaitFor('favorite', 0.1);

    expect($photo->fresh()->isFavorited())->toBeTrue();
});

test('comment is required when adding a comment to a shared photo', function () {
    $gallery = Gallery::factory()->shared()->has(Photo::factory())->create([
        'are_comments_enabled' => true,
    ]);
    $photo = $gallery->photos()->first();
    $user = $gallery->team->owner;

    Livewire::actingAs($user)
        ->test('pages.shares.photos.show', ['photo' => $photo])
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
        ->test('pages.shares.photos.show', ['photo' => $photo])
        ->call('deleteComment', $comment->id)
        ->assertStatus(403);

    expect($photo->comments()->find($comment->id))->not()->toBeNull();
});

test('guest can add a comment to a shared photo', function () {
    $gallery = Gallery::factory()->shared()->has(Photo::factory())->create([
        'are_comments_enabled' => true,
    ]);
    $photo = $gallery->photos()->first();

    Livewire::test('pages.shares.photos.show', ['photo' => $photo])
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

    $component = Livewire::test('pages.shares.photos.show', ['photo' => $photo])
        ->set('commentText', 'Should not work')
        ->call('addComment');

    $component->assertStatus(403);

    $comment = PhotoComment::factory()->for($photo)->create();

    $component = Livewire::test('pages.shares.photos.show', ['photo' => $photo])
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
        ->test('pages.shares.photos.show', ['photo' => $photo])
        ->set('commentText', 'This is a shared photo comment!')
        ->call('addComment')
        ->assertHasNoErrors();

    expect($photo->comments()->count())->toBe(1);

    $comment = $photo->comments()->first();

    expect($comment->comment)->toBe('This is a shared photo comment!');
    expect($comment->user_id)->toBe($owner->id);

    $otherUser = User::factory()->create();

    Livewire::actingAs($otherUser)
        ->test('pages.shares.photos.show', ['photo' => $photo])
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
        ->test('pages.shares.photos.show', ['photo' => $photo])
        ->call('deleteComment', $guestComment->id)
        ->assertHasNoErrors();

    expect($photo->comments()->find($guestComment->id))->toBeNull();

    Livewire::actingAs($owner)
        ->test('pages.shares.photos.show', ['photo' => $photo])
        ->call('deleteComment', $userComment->id)
        ->assertHasNoErrors();

    expect($photo->comments()->find($userComment->id))->toBeNull();

    $otherUser = User::factory()->create();

    $anotherComment = $photo->comments()->create([
        'user_id' => null,
        'comment' => 'Another guest comment',
    ]);

    Livewire::actingAs($otherUser)
        ->test('pages.shares.photos.show', ['photo' => $photo])
        ->call('deleteComment', $anotherComment->id)
        ->assertStatus(403);

    expect($photo->comments()->find($anotherComment->id))->not()->toBeNull();
});
