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
    $response->assertSee('Set as Cover');
});

test('pdf photos do not offer the cover menu', function () {
    Photo::factory()->pdf()->for(Gallery::factory()->for($this->team))->create();

    $response = actingAs($this->user)->get('/galleries/1/photos/1');

    $response->assertSuccessful();
    $response->assertDontSee('Set as Cover');
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

test('asks for confirmation before deleting a comment', function () {
    $photo = Photo::factory()->for(Gallery::factory()->for($this->team))->create();
    $comment = PhotoComment::factory()->for($photo)->create();

    $component = Livewire::actingAs($this->user)->test('pages::galleries.photos.show', ['photo' => $photo])
        ->call('requestDeleteComment', $comment->id);

    expect($component->get('pendingCommentId'))->toBe($comment->id);
    expect($photo->comments()->count())->toBe(1);
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
    $response->assertDontSee('Set as Cover');
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
