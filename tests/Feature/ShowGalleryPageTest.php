<?php

use App\Events\PhotoAdded;
use App\Models\Gallery;
use App\Models\Photo;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->withPersonalTeam()->create();
    $this->team = $this->user->currentTeam;
});

test('users can view their team gallery', function () {
    $gallery = Gallery::factory()->for($this->team)->create();
    $photoA = Photo::factory()->for($gallery)->create();
    $photoB = Photo::factory()->for(Gallery::factory())->create();
    $photoC = Photo::factory()->for($gallery)->create();

    $response = actingAs($this->user)->get('/galleries/1');
    $component = Volt::test('pages.galleries.show', ['gallery' => $gallery]);

    $response->assertStatus(200);
    $response->assertViewHas('gallery');
    expect($response['gallery']->is($gallery))->toBeTrue();

    $component->assertViewHas('allPhotos');
    expect($component->viewData('allPhotos')->contains($photoA))->toBeTrue();
    expect($component->viewData('allPhotos')->contains($photoB))->toBeFalse();
    expect($component->viewData('allPhotos')->contains($photoC))->toBeTrue();
});

test('guests cannot view any galleries', function () {
    Gallery::factory()->for($this->team)->create();

    $response = get('/galleries/1');

    $response->assertRedirect('/login');
});

test('users cannot view the team gallery of others', function () {
    Gallery::factory()->for(Team::factory())->create();

    $response = actingAs($this->user)->get('/galleries/1');

    $response->assertStatus(403);
});

test('photos can be added to a gallery', function () {
    Storage::fake('public');
    Event::fake(PhotoAdded::class);

    $gallery = Gallery::factory()->create(['ulid' => '1243ABC']);

    $component = Volt::test('pages.galleries.show', ['gallery' => $gallery])->set([
        'photos' => [
            0 => UploadedFile::fake()->image('photo1.jpg'),
            1 => UploadedFile::fake()->image('photo2.jpg'),
        ],
    ])->call('save', 0)->call('save', 1);

    expect($gallery->photos()->count())->toBe(2);
    tap($gallery->fresh()->photos[0], function ($photo) {
        expect($photo->name)->toBe('photo1.jpg');
        expect($photo->path)->toContain('galleries/1243ABC/photos/');
        expect($photo->url)->not()->toBeNull();
        expect($photo->size)->not()->toBeNull();
        Storage::disk('public')->assertExists($photo->path);
        Event::assertDispatched(PhotoAdded::class);
    });
    tap($gallery->fresh()->photos[1], function ($photo) {
        expect($photo->name)->toBe('photo2.jpg');
        expect($photo->path)->toContain('galleries/1243ABC/photos/');
        expect($photo->url)->not()->toBeNull();
        expect($photo->size)->not()->toBeNull();
        Storage::disk('public')->assertExists($photo->path);
        Event::assertDispatched(PhotoAdded::class);
    });
});

test('an added photo has been resized', function () {
    config(['picstome.photo_resize' => 128]);
    Storage::fake('public');

    $gallery = Gallery::factory()->create(['ulid' => '1243ABC']);
    $component = Volt::test('pages.galleries.show', ['gallery' => $gallery])->set([
        'photos' => [0 => UploadedFile::fake()->image('photo1.jpg', 129, 129)],
    ])->call('save', 0);

    tap($gallery->fresh()->photos[0], function ($photo) {
        $resizedImage = Storage::disk('public')->get($photo->path);
        [$width, $height] = getimagesizefromstring($resizedImage);
        expect($width)->toBe(128);
        expect($height)->toBe(128);
    });
});

test('an added photo is not resized when the keep original size option is enabled', function () {
    config(['picstome.photo_resize' => 128]);
    Storage::fake('public');
    $gallery = Gallery::factory()->create(['ulid' => '1243ABC', 'keep_original_size' => true]);
    $component = Volt::test('pages.galleries.show', ['gallery' => $gallery])->set([
        'photos' => [0 => UploadedFile::fake()->image('photo1.jpg', 129, 129)],
    ])->call('save', 0);

    tap($gallery->fresh()->photos[0], function ($photo) {
        $resizedImage = Storage::disk('public')->get($photo->path);
        [$width, $height] = getimagesizefromstring($resizedImage);
        expect($width)->toBe(129);
        expect($height)->toBe(129);
    });
});

test('a thumbnail has been generated from the added photo', function () {
    config(['picstome.photo_thumb_resize' => 64]);
    Storage::fake('public');
    $gallery = Gallery::factory()->create(['ulid' => '1243ABC']);
    $component = Volt::test('pages.galleries.show', ['gallery' => $gallery])->set([
        'photos' => [0 => UploadedFile::fake()->image('photo1.jpg', 65, 65)],
    ])->call('save', 0);

    tap($gallery->fresh()->photos[0], function ($photo) {
        $resizedImage = Storage::disk('public')->get($photo->thumb_path);
        [$width, $height] = getimagesizefromstring($resizedImage);
        expect($width)->toBe(64);
        expect($height)->toBe(64);
    });
});

test('gallery can be shared with no options enabled', function () {
    $gallery = Gallery::factory()->create();

    $component = Volt::test('pages.galleries.show', ['gallery' => $gallery])->call('share');

    expect($gallery->fresh()->is_shared)->toBeTrue();
});

test('gallery can be shared with selectable options enabled', function () {
    $gallery = Gallery::factory()->create();

    $component = Volt::test('pages.galleries.show', ['gallery' => $gallery])
        ->set('shareForm.selectable', true)
        ->call('share');

    expect($gallery->fresh()->is_shared)->toBeTrue();
    expect($gallery->fresh()->is_share_selectable)->toBeTrue();
});

test('gallery can be shared with downloadable options enabled', function () {
    $gallery = Gallery::factory()->create();

    $component = Volt::test('pages.galleries.show', ['gallery' => $gallery])
        ->set('shareForm.downloadable', true)
        ->call('share');

    expect($gallery->fresh()->is_shared)->toBeTrue();
    expect($gallery->fresh()->is_share_downloadable)->toBeTrue();
});

test('gallery can be shared with limited selection', function () {
    $gallery = Gallery::factory()->create();

    $component = Volt::test('pages.galleries.show', ['gallery' => $gallery])
        ->set('shareForm.limitedSelection', true)
        ->set('shareForm.selectionLimit', 10)
        ->call('share');

    expect($gallery->fresh()->share_selection_limit)->toBe(10);
});

test('shared gallery can be disabled', function () {
    $gallery = Gallery::factory()->shared()->create();

    $component = Volt::test('pages.galleries.show', ['gallery' => $gallery])->call('disableSharing');

    expect($gallery->fresh()->is_shared)->toBeFalse();
});

test('gallery can be shared with password protection', function () {
    $gallery = Gallery::factory()->create();
    expect($gallery->share_password)->toBeNull();

    $component = Volt::test('pages.galleries.show', ['gallery' => $gallery])
        ->set('shareForm.passwordProtected', true)
        ->set('shareForm.password', 'secret')
        ->call('share');

    expect($gallery->fresh()->share_password)->not->toBeNull();
});

test('gallery can be shared with watermarked options enabled', function () {
    $gallery = Gallery::factory()->create();
    expect($gallery->fresh()->is_share_watermarked)->toBeFalse();

    $component = Volt::test('pages.galleries.show', ['gallery' => $gallery])
        ->set('shareForm.watermarked', true)
        ->call('share');

    expect($gallery->fresh()->is_shared)->toBeTrue();
    expect($gallery->fresh()->is_share_watermarked)->toBeTrue();
});

test('users can view their team gallery favorites', function () {
    $gallery = Gallery::factory()->create();
    $favorite = Photo::factory()->for($gallery)->favorited()->create();

    $component = Volt::test('pages.galleries.show', ['gallery' => $gallery]);

    expect($component->favorites->contains($favorite))->toBeTrue();
});

test('users can favorite a photo', function () {
    $photo = Photo::factory()->unfavorited()->create();
    expect($photo->isFavorited())->toBeFalse();

    $component = Volt::actingAs($this->user)->test('photo-item', ['photo' => $photo])
        ->call('favorite', $photo->id);

    $component->assertDispatched('photo-favorited');
    expect($photo->fresh()->isFavorited())->toBeTrue();
});

test('can delete a photo', function () {
    Storage::fake('public');
    $gallery = Gallery::factory()->for($this->team)->create();
    $photo = Photo::factory()->for($gallery)->create([
        'name' => 'photo1.jpg',
        'path' => UploadedFile::fake()
            ->image('photo1.jpg')
            ->storeAs('galleries/1/photos', 'photo1.jpg', 'public'),
    ]);
    Storage::disk('public')->assertExists('galleries/1/photos/photo1.jpg');
    expect(Photo::count())->toBe(1);

    $component = Volt::actingAs($this->user)->test('pages.galleries.show', ['gallery' => $gallery])
        ->call('deletePhoto', $photo->id);

    expect($gallery->photos()->count())->toBe(0);
    Storage::disk('public')->assertMissing('galleries/1/photos/photo1.jpg');
});

test('users cannot delete another team photo', function () {
    $gallery = Gallery::factory()->for($this->team)->create();
    $photo = Photo::factory()->for(Gallery::factory()->for(Team::factory()))->create();

    $component = Volt::actingAs($this->user)->test('pages.galleries.show', ['gallery' => $gallery])
        ->call('deletePhoto', $photo->id);

    $component->assertStatus(403);
});

test('users can delete their team gallery', function () {
    Storage::fake('public');
    $photos = collect([
        UploadedFile::fake()->image('photo1.jpg'),
        UploadedFile::fake()->image('photo2.jpg'),
    ]);
    $gallery = Gallery::factory()->for($this->team)->create(['name' => 'Example Gallery']);
    $photos->each(function ($photo) use ($gallery) {
        $gallery->addPhoto($photo);
    });
    $gallery->photos->each(function ($photo) {
        Storage::disk('public')->assertExists($photo->path);
    });

    $component = Volt::test('pages.galleries.show', ['gallery' => $gallery])->call('delete');
    $component->assertRedirect('/galleries');
    expect(Gallery::count())->toBe(0);
    expect(Photo::count())->toBe(0);
    $gallery->photos->each(function ($photo) {
        Storage::disk('public')->assertMissing($photo->path);
    });
});

test('can edit a team gallery', function () {
    $gallery = Gallery::factory()->create();

    $component = Volt::test('pages.galleries.show', ['gallery' => $gallery])
        ->set('form.name', 'Edited Gallery')
        ->call('update');

    expect($gallery->fresh()->name)->toBe('Edited Gallery');
});

test('password protection can be disabled', function () {
    $gallery = Gallery::factory()->protected('password')->create();
    expect($gallery->share_password)->not->toBeNull();

    $component = Volt::test('pages.galleries.show', ['gallery' => $gallery])
        ->set('shareForm.passwordProtected', false)
        ->call('share');

    expect($gallery->fresh()->share_password)->toBeNull();
});

test('increments storage_used when team uploads photo and has enough storage', function () {
    Storage::fake('public');
    Event::fake(PhotoAdded::class);

    $this->team->update([
        'custom_storage_limit' => 100 * 1024 * 1024, // 100 MB
        'storage_used' => 10 * 1024 * 1024,   // 10 MB used
    ]);

    $gallery = Gallery::factory()->for($this->team)->create(['ulid' => 'STORAGEOK']);

    $photoFile = UploadedFile::fake()->image('photo_upload.jpg', 1200, 800)->size(5 * 1024); // 5 MB
    $photoSize = $photoFile->getSize();
    $initialStorageUsed = $this->team->fresh()->storage_used;

    Volt::actingAs($this->user)->test('pages.galleries.show', ['gallery' => $gallery])
        ->set('photos', [0 => $photoFile])
        ->call('save', 0);

    expect($gallery->fresh()->photos()->count())->toBe(1);
    expect($this->team->fresh()->storage_used)->toBe($initialStorageUsed + $photoSize);
});

test('blocks photo upload when team storage limit would be exceeded', function () {
    Storage::fake('public');
    Event::fake(PhotoAdded::class);

    $this->team->update([
        'custom_storage_limit' => 20 * 1024, // 20 KB
        'storage_used' => 18 * 1024, // 18 KB used
    ]);
    $gallery = Gallery::factory()->for($this->team)->create();

    $photoFile = UploadedFile::fake()->image('photo_upload.jpg', 1200, 800)->size(5 * 1024); // 5 KB
    $initialStorageUsed = $this->team->fresh()->storage_used;

    $component = Volt::actingAs($this->user)->test('pages.galleries.show', ['gallery' => $gallery])
        ->set('photos', [0 => $photoFile])
        ->call('save', 0);

    expect($gallery->fresh()->photos()->count())->toBe(0);
    expect($this->team->fresh()->storage_used)->toBe($initialStorageUsed);
    $component->assertHasErrors(['photos.0' => 'You do not have enough storage space to upload this photo.']);
});

test('blocks photo upload when team is exactly at the storage limit', function () {
    Storage::fake('public');
    Event::fake(PhotoAdded::class);

    $this->team->update([
        'custom_storage_limit' => 20 * 1024, // 20 KB
        'storage_used' => 20 * 1024, // exactly at the limit
    ]);
    $gallery = Gallery::factory()->for($this->team)->create(['ulid' => 'STORAGEFULL']);

    $photoFile = UploadedFile::fake()->image('photo_upload.jpg', 1200, 800)->size(5 * 1024); // 5 KB
    $initialStorageUsed = $this->team->fresh()->storage_used;

    $component = Volt::actingAs($this->user)->test('pages.galleries.show', ['gallery' => $gallery])
        ->set('photos', [0 => $photoFile])
        ->call('save', 0);

    expect($gallery->fresh()->photos()->count())->toBe(0);
    expect($this->team->fresh()->storage_used)->toBe($initialStorageUsed);
    $component->assertHasErrors(['photos.0' => 'You do not have enough storage space to upload this photo.']);
});

test('block photo upload when team is just under the storage limit', function () {
    Storage::fake('public');
    Event::fake(PhotoAdded::class);

    $this->team->update([
        'custom_storage_limit' => 20 * 1024, // 20 KB
        'storage_used' => 19 * 1024, // just under the limit
    ]);
    $gallery = Gallery::factory()->for($this->team)->create(['ulid' => 'STORAGEALMOSTFULL']);

    $photoFile = UploadedFile::fake()->image('photo_upload.jpg', 1200, 800)->size(2 * 1024); // 2 KB
    $initialStorageUsed = $this->team->fresh()->storage_used;

    $component = Volt::actingAs($this->user)->test('pages.galleries.show', ['gallery' => $gallery])
        ->set('photos', [0 => $photoFile])
        ->call('save', 0);

    expect($gallery->fresh()->photos()->count())->toBe(0);
    expect($this->team->fresh()->storage_used)->toBe($initialStorageUsed);
    $component->assertHasErrors(['photos.0' => 'You do not have enough storage space to upload this photo.']);
});

test('does not count deleted photos towards storage usage', function () {
    Storage::fake('public');
    Event::fake(PhotoAdded::class);

    $this->team->update([
        'custom_storage_limit' => 100 * 1024 * 1024, // 100 MB
        'storage_used' => 0,
    ]);
    $gallery = Gallery::factory()->for($this->team)->create(['ulid' => 'STORAGEDELETE']);

    $photoFile = UploadedFile::fake()->image('photo_delete.jpg', 1200, 800)->size(5 * 1024); // 5 KB
    $photoSize = $photoFile->getSize();

    Volt::actingAs($this->user)->test('pages.galleries.show', ['gallery' => $gallery])
        ->set('photos', [0 => $photoFile])
        ->call('save', 0);

    $storageAfterUpload = $this->team->fresh()->storage_used;
    expect($storageAfterUpload)->toBe($photoSize);

    $photo = $gallery->fresh()->photos()->first();
    Volt::actingAs($this->user)->test('pages.galleries.show', ['gallery' => $gallery])
        ->call('deletePhoto', $photo->id);

    expect($this->team->fresh()->storage_used)->toBe(0);
});

test('does not block photo upload for teams with unlimited storage regardless of usage', function () {
    Storage::fake('public');
    Event::fake(PhotoAdded::class);

    $this->team->update([
        'custom_storage_limit' => null, // Unlimited storage
        'storage_used' => 999999999, // Simulate huge usage
    ]);
    $gallery = Gallery::factory()->for($this->team)->create(['ulid' => 'UNLIMITED']);

    $photoFile = UploadedFile::fake()->image('photo_upload.jpg', 1200, 800)->size(5 * 1024); // 5 MB
    $initialStorageUsed = $this->team->fresh()->storage_used;

    $component = Volt::actingAs($this->user)->test('pages.galleries.show', ['gallery' => $gallery])
        ->set('photos', [0 => $photoFile])
        ->call('save', 0);

    expect($gallery->fresh()->photos()->count())->toBe(1);
    expect($this->team->fresh()->storage_used)->toBeGreaterThanOrEqual($initialStorageUsed);
    $component->assertHasNoErrors();
});
