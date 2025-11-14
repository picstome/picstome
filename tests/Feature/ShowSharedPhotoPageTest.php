<?php

use App\Models\Gallery;
use App\Models\Photo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

test('photo can be viewed when the gallery is shared', function () {
    $gallery = Gallery::factory()->shared()->has(Photo::factory())->create(['ulid' => '0123ABC']);
    $response = get('/shares/0123ABC/photos/1');
    $response->assertStatus(200);
});

test('photo cannot be viewed unless the gallery is shared', function () {
    $gallery = Gallery::factory(['ulid' => '0123ABC'])->unshared()->has(Photo::factory())->create();
    $response = get('/shares/0123ABC/photos/1');
    $response->assertStatus(404);
});

test('favorite photo when gallery is selectable', function () {
    $gallery = Gallery::factory()->selectable()->has(Photo::factory()->unfavorited())->create();
    expect($gallery->photos()->first()->isFavorited())->toBeFalse();
    $component = Volt::test('pages.shares.photos.show', ['photo' => $gallery->photos()->first()])->call('favorite');
    expect($gallery->photos()->first()->isFavorited())->toBeTrue();
});

test('photo cannot be favorited when the gallery is not selectable', function () {
    $gallery = Gallery::factory()->unselectable()->has(Photo::factory()->unfavorited())->create();
    expect($gallery->photos()->first()->isFavorited())->toBeFalse();
    $component = Volt::test('pages.shares.photos.show', ['photo' => $gallery->photos()->first()])->call('favorite');
    expect($gallery->photos()->first()->isFavorited())->toBeFalse();
});

test('favoriting a photo dispatches a selection limit reached event when the gallery selection limit is exceeded', function () {
    $gallery = Gallery::factory()->shared()->selectable(limit: 5)->has(Photo::factory()->favorited()->count(5))->create();
    $gallery->photos()->save(Photo::factory()->unfavorited()->make());
    expect($gallery->photos()->favorited()->count())->toBe(5);
    $component = Volt::test('pages.shares.photos.show', ['photo' => $gallery->photos()->unfavorited()->first()])->call('favorite');
    $component->assertDispatched('selection-limit-reached');
});

test('unauthenticated visitors to a password-protected gallery are redirected to the unlock page', function () {
    $gallery = Gallery::factory()->shared()->protected()->has(Photo::factory())->create(['ulid' => '0123ABC']);
    $response = get('/shares/0123ABC/photos/1');
    $response->assertRedirect('/shares/0123ABC/unlock');
});

test('visitors with unlocked gallery can view the password-protected photo', function () {
    $gallery = Gallery::factory()->shared()->protected()->has(Photo::factory())->create(['ulid' => '0123ABC']);
    session()->put('unlocked_gallery_ulid', '0123ABC');
    $response = get('/shares/0123ABC/photos/1');
    $response->assertStatus(200);
});

test('favorite photo via browser on watermarked gallery', function () {
    $gallery = Gallery::factory()->shared()->selectable()->watermarked()->has(Photo::factory()->unfavorited())->create(['ulid' => 'test123']);
    $photo = $gallery->photos()->first();
    $page = visit("/shares/test123/photos/{$photo->id}");
    $page->pressAndWaitFor('favorite', 0.1);
    expect($photo->fresh()->isFavorited())->toBeTrue();
});

// --- Comment functionality tests ---

test('comment is required when adding a comment to a shared photo', function () {
    $user = User::factory()->create();
    $gallery = Gallery::factory()->shared()->has(Photo::factory())->create();
    $photo = $gallery->photos()->first();
    Volt::actingAs($user)
        ->test('pages.shares.photos.show', ['photo' => $photo])
        ->set('commentText', '')
        ->call('addComment')
        ->assertHasErrors(['commentText' => 'required']);
    expect($photo->comments()->count())->toBe(0);
});

test('user cannot delete another user\'s comment on a shared photo', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $gallery = Gallery::factory()->shared()->has(Photo::factory())->create();
    $photo = $gallery->photos()->first();
    $comment = $photo->comments()->create([
        'user_id' => $otherUser->id,
        'comment' => 'Other user shared comment',
    ]);
    Volt::actingAs($user)
        ->test('pages.shares.photos.show', ['photo' => $photo])
        ->call('deleteComment', $comment->id)
        ->assertStatus(403);
    expect($photo->comments()->find($comment->id))->not()->toBeNull();
});

test('guest can add a comment to a shared photo', function () {
    $gallery = Gallery::factory()->shared()->has(Photo::factory())->create();
    $photo = $gallery->photos()->first();
    Volt::test('pages.shares.photos.show', ['photo' => $photo])
        ->set('commentText', 'Guest comment')
        ->call('addComment')
        ->assertHasNoErrors();
    $comment = $photo->comments()->latest()->first();
    expect($comment->comment)->toBe('Guest comment');
    expect($comment->user_id)->toBeNull();
});

// --- Team owner permission tests ---

test('only the team owner can add a comment as an authenticated user', function () {
    $owner = User::factory()->create();
    $team = \App\Models\Team::factory()->create(['user_id' => $owner->id]);
    $gallery = Gallery::factory()->shared()->for($team)->has(Photo::factory())->create();
    $photo = $gallery->photos()->first();
    // Team owner can comment
    Volt::actingAs($owner)
        ->test('pages.shares.photos.show', ['photo' => $photo])
        ->set('commentText', 'This is a shared photo comment!')
        ->call('addComment')
        ->assertHasNoErrors();
    expect($photo->comments()->count())->toBe(1);
    $comment = $photo->comments()->first();
    expect($comment->comment)->toBe('This is a shared photo comment!');
    expect($comment->user_id)->toBe($owner->id);
    // Non-owner cannot comment
    $otherUser = User::factory()->create();
    Volt::actingAs($otherUser)
        ->test('pages.shares.photos.show', ['photo' => $photo])
        ->set('commentText', 'Should not be allowed')
        ->call('addComment')
        ->assertStatus(403);
    expect($photo->comments()->count())->toBe(1); // Still only the owner's comment
});

test('only the team owner can delete any comment', function () {
    $owner = User::factory()->create();
    $team = \App\Models\Team::factory()->create(['user_id' => $owner->id]);
    $gallery = Gallery::factory()->shared()->for($team)->has(Photo::factory())->create();
    $photo = $gallery->photos()->first();
    $user = User::factory()->create();
    $guestComment = $photo->comments()->create([
        'user_id' => null,
        'comment' => 'Guest comment',
    ]);
    $userComment = $photo->comments()->create([
        'user_id' => $user->id,
        'comment' => 'User comment',
    ]);
    // Team owner can delete guest comment
    Volt::actingAs($owner)
        ->test('pages.shares.photos.show', ['photo' => $photo])
        ->call('deleteComment', $guestComment->id)
        ->assertHasNoErrors();
    expect($photo->comments()->find($guestComment->id))->toBeNull();
    // Team owner can delete user comment
    Volt::actingAs($owner)
        ->test('pages.shares.photos.show', ['photo' => $photo])
        ->call('deleteComment', $userComment->id)
        ->assertHasNoErrors();
    expect($photo->comments()->find($userComment->id))->toBeNull();
    // Non-owner cannot delete any comment
    $otherUser = User::factory()->create();
    $anotherComment = $photo->comments()->create([
        'user_id' => null,
        'comment' => 'Another guest comment',
    ]);
    Volt::actingAs($otherUser)
        ->test('pages.shares.photos.show', ['photo' => $photo])
        ->call('deleteComment', $anotherComment->id)
        ->assertStatus(403);
    expect($photo->comments()->find($anotherComment->id))->not()->toBeNull();
});
