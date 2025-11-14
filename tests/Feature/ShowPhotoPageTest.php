<?php

use App\Models\Gallery;
use App\Models\Photo;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->withPersonalTeam()->create();
    $this->team = $this->user->currentTeam;
});

test('users can view a team photo in the gallery', function () {
    $photo = Photo::factory()->for(Gallery::factory()->for($this->team))->create();

    $response = actingAs($this->user)->get('/galleries/1/photos/1');

    $response->assertStatus(200);
    $response->assertViewHas('photo');
    expect($response['photo']->is($photo))->toBeTrue();
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

    $photo = Photo::factory()->create([
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

    $component = Volt::test('pages.galleries.photos.show', ['photo' => $photo])
        ->call('delete');

    $component->assertRedirect('/galleries/1');
    expect(Photo::count())->toBe(0);
    Storage::disk('public')->assertMissing(['galleries/1/photos/photo1.jpg']);
    Storage::disk('public')->assertMissing(['galleries/1/photos/photo1_thumb.jpg']);
});

test('can favorite a photo', function () {
    $photo = Photo::factory()->unfavorited()->create();
    expect($photo->isFavorited())->toBeFalse();

    $component = Volt::test('pages.galleries.photos.show', ['photo' => $photo])
        ->call('favorite');

    expect($photo->fresh()->isFavorited())->toBeTrue();
});

test('can set a photo as the gallery cover', function () {
    $photo = Photo::factory()->for(Gallery::factory()->for($this->team))->create();

    $component = Volt::actingAs($this->user)->test('pages.galleries.photos.show', ['photo' => $photo])
        ->call('setAsCover');

    expect($photo->gallery->fresh()->cover_photo_id)->toBe($photo->id);
});

test('can change the cover photo', function () {
    $gallery = Gallery::factory()->for($this->team)->create();
    $photo1 = Photo::factory()->for($gallery)->create();
    $photo2 = Photo::factory()->for($gallery)->create();
    $gallery->update(['cover_photo_id' => $photo1->id]);

    $component = Volt::actingAs($this->user)->test('pages.galleries.photos.show', ['photo' => $photo2])
        ->call('setAsCover');

    expect($gallery->fresh()->cover_photo_id)->toBe($photo2->id);
});

test('can remove the cover photo', function () {
    $gallery = Gallery::factory()->for($this->team)->create();
    $photo = Photo::factory()->for($gallery)->create();
    $gallery->update(['cover_photo_id' => $photo->id]);

    $component = Volt::actingAs($this->user)->test('pages.galleries.photos.show', ['photo' => $photo])
        ->call('removeAsCover');

    expect($gallery->fresh()->cover_photo_id)->toBeNull();
});

test('prevents setting cover photo from another team', function () {
    $gallery = Gallery::factory()->for($this->team)->create();
    $otherTeam = Team::factory()->create();
    $otherGallery = Gallery::factory()->for($otherTeam)->create();
    $photo = Photo::factory()->for($otherGallery)->create();

    $component = Volt::actingAs($this->user)->test('pages.galleries.photos.show', ['photo' => $photo])
        ->call('setAsCover');

    $component->assertStatus(403);
    expect($gallery->fresh()->cover_photo_id)->toBeNull();
});

test('authenticated user can add a comment to a photo', function () {
    $photo = Photo::factory()->for(Gallery::factory()->for($this->team))->create();
    $user = $this->user;

    Volt::actingAs($user)
        ->test('pages.galleries.photos.show', ['photo' => $photo])
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

    Volt::actingAs($user)
        ->test('pages.galleries.photos.show', ['photo' => $photo])
        ->set('commentText', '')
        ->call('addComment')
        ->assertHasErrors(['commentText' => 'required']);

    expect($photo->comments()->count())->toBe(0);
});

test('user can delete their own comment', function () {
    $photo = Photo::factory()->for(Gallery::factory()->for($this->team))->create();
    $user = $this->user;
    $comment = $photo->comments()->create([
        'user_id' => $user->id,
        'comment' => 'My comment',
    ]);

    Volt::actingAs($user)
        ->test('pages.galleries.photos.show', ['photo' => $photo])
        ->call('deleteComment', $comment->id)
        ->assertHasNoErrors();

    expect($photo->comments()->find($comment->id))->toBeNull();
});

test('user cannot delete another user\'s comment', function () {
    $photo = Photo::factory()->for(Gallery::factory()->for($this->team))->create();
    $user = $this->user;
    $otherUser = User::factory()->create();
    $comment = $photo->comments()->create([
        'user_id' => $otherUser->id,
        'comment' => 'Other user comment',
    ]);

    Volt::actingAs($user)
        ->test('pages.galleries.photos.show', ['photo' => $photo])
        ->call('deleteComment', $comment->id)
        ->assertStatus(403);

    expect($photo->comments()->find($comment->id))->not()->toBeNull();
});

test('guest cannot delete any comment', function () {
    $photo = Photo::factory()->for(Gallery::factory()->for($this->team))->create();
    $user = $this->user;
    $comment = $photo->comments()->create([
        'user_id' => $user->id,
        'comment' => 'Guest cannot delete',
    ]);

    Volt::test('pages.galleries.photos.show', ['photo' => $photo])
        ->call('deleteComment', $comment->id)
        ->assertStatus(403);

    expect($photo->comments()->find($comment->id))->not()->toBeNull();
});
