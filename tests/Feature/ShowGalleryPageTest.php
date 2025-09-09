<?php

use App\Events\PhotoAdded;
use App\Models\Gallery;
use App\Models\Photo;
use App\Models\Team;
use App\Models\User;
use App\Models\Photoshoot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Laravel\Cashier\Subscription;
use Livewire\Volt\Volt;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function () {
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
        $component = Volt::test('pages.galleries.show', ['gallery' => $gallery]);

        $response->assertStatus(200);
        $response->assertViewHas('gallery');
        expect($response['gallery']->is($gallery))->toBeTrue();

        $component->assertViewHas('allPhotos');
        expect($component->viewData('allPhotos')->contains($photoA))->toBeTrue();
        expect($component->viewData('allPhotos')->contains($photoB))->toBeFalse();
        expect($component->viewData('allPhotos')->contains($photoC))->toBeTrue();
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

describe('Photo Upload', function () {
    it('allows photos to be added to a gallery', function () {
        Storage::fake('public');
        Storage::fake('s3');

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

    it('resizes an added photo', function () {
        config(['picstome.photo_resize' => 128]);
        Storage::fake('public');
        Storage::fake('s3');

        $gallery = Gallery::factory()->create(['ulid' => '1243ABC']);
        $component = Volt::test('pages.galleries.show', ['gallery' => $gallery])->set([
            'photos' => [0 => UploadedFile::fake()->image('photo1.jpg', 129, 129)],
        ])->call('save', 0);

        tap($gallery->fresh()->photos[0], function ($photo) {
            $resizedImage = Storage::disk('s3')->get($photo->path);
            [$width, $height] = getimagesizefromstring($resizedImage);
            expect($width)->toBe(128);
            expect($height)->toBe(128);
            expect($photo->disk)->toBe('s3');
        });
    });

    it('does not resize photo when keep original size is enabled', function () {
        config(['picstome.photo_resize' => 128]);
        Storage::fake('public');
        Storage::fake('s3');

        $gallery = Gallery::factory()->create(['ulid' => '1243ABC', 'keep_original_size' => true]);
        $component = Volt::test('pages.galleries.show', ['gallery' => $gallery])->set([
            'photos' => [0 => UploadedFile::fake()->image('photo1.jpg', 129, 129)],
        ])->call('save', 0);

        tap($gallery->fresh()->photos[0], function ($photo) {
            $resizedImage = Storage::disk('s3')->get($photo->path);
            [$width, $height] = getimagesizefromstring($resizedImage);
            expect($width)->toBe(129);
            expect($height)->toBe(129);
            expect($photo->disk)->toBe('s3');
        });
    });

    it('generates a thumbnail from the added photo', function () {
        config(['picstome.photo_thumb_resize' => 64]);
        Storage::fake('public');
        Storage::fake('s3');

        $gallery = Gallery::factory()->create(['ulid' => '1243ABC']);
        $component = Volt::test('pages.galleries.show', ['gallery' => $gallery])->set([
            'photos' => [0 => UploadedFile::fake()->image('photo1.jpg', 65, 65)],
        ])->call('save', 0);

        tap($gallery->fresh()->photos[0], function ($photo) {
            $resizedImage = Storage::disk('s3')->get($photo->thumb_path);
            [$width, $height] = getimagesizefromstring($resizedImage);
            expect($width)->toBe(64);
            expect($height)->toBe(64);
        });
    });

    it('deletes the original photo from public disk after processing', function () {
        config(['picstome.photo_resize' => 128]);
        config(['picstome.photo_thumb_resize' => 64]);
        Storage::fake('public');
        Storage::fake('s3');

        $gallery = Gallery::factory()->create(['ulid' => '1243ABC']);
        $photoFile = UploadedFile::fake()->image('photo1.jpg', 129, 129);
        $component = Volt::test('pages.galleries.show', ['gallery' => $gallery])->set([
            'photos' => [0 => $photoFile],
        ])->call('save', 0);

        $photo = $gallery->fresh()->photos[0];

        expect(Storage::disk('public')->allFiles())->toBeEmpty();
    });
});

describe('Gallery Sharing', function () {
    it('can be shared with no options enabled', function () {
        $gallery = Gallery::factory()->create();

        $component = Volt::test('pages.galleries.show', ['gallery' => $gallery])->call('share');

        expect($gallery->fresh()->is_shared)->toBeTrue();
    });

    it('can be shared with selectable options enabled', function () {
        $gallery = Gallery::factory()->create();

        $component = Volt::test('pages.galleries.show', ['gallery' => $gallery])
            ->set('shareForm.selectable', true)
            ->call('share');

        expect($gallery->fresh()->is_shared)->toBeTrue();
        expect($gallery->fresh()->is_share_selectable)->toBeTrue();
    });

    it('can be shared with downloadable options enabled', function () {
        $gallery = Gallery::factory()->create();

        $component = Volt::test('pages.galleries.show', ['gallery' => $gallery])
            ->set('shareForm.downloadable', true)
            ->call('share');

        expect($gallery->fresh()->is_shared)->toBeTrue();
        expect($gallery->fresh()->is_share_downloadable)->toBeTrue();
    });

    it('can be shared with limited selection', function () {
        $gallery = Gallery::factory()->create();

        $component = Volt::test('pages.galleries.show', ['gallery' => $gallery])
            ->set('shareForm.limitedSelection', true)
            ->set('shareForm.selectionLimit', 10)
            ->call('share');

        expect($gallery->fresh()->share_selection_limit)->toBe(10);
    });

    it('can be disabled for sharing', function () {
        $gallery = Gallery::factory()->shared()->create();

        $component = Volt::test('pages.galleries.show', ['gallery' => $gallery])->call('disableSharing');

        expect($gallery->fresh()->is_shared)->toBeFalse();
    });

    it('can be shared with password protection', function () {
        $gallery = Gallery::factory()->create();
        expect($gallery->share_password)->toBeNull();

        $component = Volt::test('pages.galleries.show', ['gallery' => $gallery])
            ->set('shareForm.passwordProtected', true)
            ->set('shareForm.password', 'secret')
            ->call('share');

        expect($gallery->fresh()->share_password)->not->toBeNull();
    });

    it('can be shared with watermarked options enabled', function () {
        $gallery = Gallery::factory()->create();
        expect($gallery->fresh()->is_share_watermarked)->toBeFalse();

        $component = Volt::test('pages.galleries.show', ['gallery' => $gallery])
            ->set('shareForm.watermarked', true)
            ->call('share');

        expect($gallery->fresh()->is_shared)->toBeTrue();
        expect($gallery->fresh()->is_share_watermarked)->toBeTrue();
    });

    it('can disable password protection', function () {
        $gallery = Gallery::factory()->protected('password')->create();
        expect($gallery->share_password)->not->toBeNull();

        $component = Volt::actingAs($this->user)->test('pages.galleries.show', ['gallery' => $gallery])
            ->set('shareForm.passwordProtected', false)
            ->call('share');

        expect($gallery->fresh()->share_password)->toBeNull();
    });

    it('can be shared with a description', function () {
        $gallery = Gallery::factory()->create();
        expect($gallery->share_description)->toBeNull();

        $component = Volt::test('pages.galleries.show', ['gallery' => $gallery])
            ->set('shareForm.descriptionEnabled', true)
            ->set('shareForm.description', 'This is a beautiful wedding gallery')
            ->call('share');

        expect($gallery->fresh()->is_shared)->toBeTrue();
        expect($gallery->fresh()->share_description)->toBe('This is a beautiful wedding gallery');
    });

    it('can update the share description', function () {
        $gallery = Gallery::factory()->shared()->create(['share_description' => 'Old description']);
        expect($gallery->share_description)->toBe('Old description');

        $component = Volt::actingAs($this->user)->test('pages.galleries.show', ['gallery' => $gallery])
            ->set('shareForm.descriptionEnabled', true)
            ->set('shareForm.description', 'Updated description')
            ->call('share');

        expect($gallery->fresh()->share_description)->toBe('Updated description');
    });

    it('can be shared without a description', function () {
        $gallery = Gallery::factory()->create();

        $component = Volt::test('pages.galleries.show', ['gallery' => $gallery])
            ->set('shareForm.descriptionEnabled', false)
            ->call('share');

        expect($gallery->fresh()->is_shared)->toBeTrue();
        expect($gallery->fresh()->share_description)->toBeNull();
    });

    it('can remove the share description', function () {
        $gallery = Gallery::factory()->shared()->create(['share_description' => 'Some description']);
        expect($gallery->share_description)->toBe('Some description');

        $component = Volt::actingAs($this->user)->test('pages.galleries.show', ['gallery' => $gallery])
            ->set('shareForm.descriptionEnabled', false)
            ->call('share');

        expect($gallery->fresh()->share_description)->toBeNull();
    });

    it('requires description when description is enabled', function () {
        $gallery = Gallery::factory()->create();

        $component = Volt::test('pages.galleries.show', ['gallery' => $gallery])
            ->set('shareForm.descriptionEnabled', true)
            ->set('shareForm.description', '')
            ->call('share');

        $component->assertHasErrors('shareForm.description');
    });

    it('does not require description when description is disabled', function () {
        $gallery = Gallery::factory()->create();

        $component = Volt::test('pages.galleries.show', ['gallery' => $gallery])
            ->set('shareForm.descriptionEnabled', false)
            ->set('shareForm.description', '')
            ->call('share');

        $component->assertHasNoErrors('shareForm.description');
        expect($gallery->fresh()->share_description)->toBeNull();
    });
});

describe('Favorites', function () {
    it('allows users to view their team gallery favorites', function () {
        $gallery = Gallery::factory()->create();
        $favorite = Photo::factory()->for($gallery)->favorited()->create();

        $component = Volt::test('pages.galleries.show', ['gallery' => $gallery]);

        expect($component->favorites->contains($favorite))->toBeTrue();
    });

    it('allows users to favorite a photo', function () {
        $photo = Photo::factory()->unfavorited()->create();
        expect($photo->isFavorited())->toBeFalse();

        $component = Volt::actingAs($this->user)->test('photo-item', ['photo' => $photo])
            ->call('favorite', $photo->id);

        $component->assertDispatched('photo-favorited');
        expect($photo->fresh()->isFavorited())->toBeTrue();
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
        Storage::disk('s3')->assertExists('galleries/1/photos/photo1.jpg');
        expect(Photo::count())->toBe(1);

        $component = Volt::actingAs($this->user)->test('pages.galleries.show', ['gallery' => $gallery])
            ->call('deletePhoto', $photo->id);

        expect($gallery->photos()->count())->toBe(0);
        Storage::disk('s3')->assertMissing('galleries/1/photos/photo1.jpg');
    });

    it('prevents users from deleting another team\'s photo', function () {
        $gallery = Gallery::factory()->for($this->team)->create();
        $photo = Photo::factory()->for(Gallery::factory()->for(Team::factory()))->create();

        $component = Volt::actingAs($this->user)->test('pages.galleries.show', ['gallery' => $gallery])
            ->call('deletePhoto', $photo->id);

        $component->assertStatus(403);
    });

    it('allows users to delete their team gallery', function () {
        Storage::fake('public');
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
});

describe('Gallery Photoshoot Association', function () {
    it('allows assigning a gallery to a photoshoot', function () {
        $photoshoot = Photoshoot::factory()->for($this->team)->create();
        $gallery = Gallery::factory()->for($this->team)->withExpirationDate()->create();

        $component = Volt::actingAs($this->user)->test('pages.galleries.show', ['gallery' => $gallery])
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

        $component = Volt::actingAs($this->user)->test('pages.galleries.show', ['gallery' => $gallery])
            ->set('form.photoshoot_id', $photoshootB->id)
            ->call('update');

        $gallery->refresh();
        expect($gallery->photoshoot_id)->toBe($photoshootB->id);
    });

    it('prevents assigning a photoshoot from another team', function () {
        $otherTeam = Team::factory()->create();
        $photoshoot = Photoshoot::factory()->for($otherTeam)->create();
        $gallery = Gallery::factory()->for($this->team)->create();

        $component = Volt::actingAs($this->user)->test('pages.galleries.show', ['gallery' => $gallery])
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

        $component = Volt::actingAs($this->user)->test('pages.galleries.show', ['gallery' => $gallery])
            ->set('form.photoshoot_id', null)
            ->call('update');

        $gallery->refresh();
        expect($gallery->photoshoot_id)->toBeNull();
    });
});

describe('Gallery Editing', function () {
    it('allows editing a team gallery', function () {
        $gallery = Gallery::factory()->for($this->team)->withExpirationDate()->create();

        $component = Volt::actingAs($this->user)->test('pages.galleries.show', ['gallery' => $gallery])
            ->set('form.name', 'Edited Gallery')
            ->call('update');

        expect($gallery->fresh()->name)->toBe('Edited Gallery');
    });

    it('sets the gallery expiration date', function () {
        $gallery = Gallery::factory()->for($this->team)->create();
        $expirationDate = now()->addDays(10)->toDateString();

        $component = Volt::actingAs($this->user)->test('pages.galleries.show', ['gallery' => $gallery])
            ->set('form.expirationDate', $expirationDate)
            ->call('update');
        $gallery->refresh();
        expect($gallery->expiration_date->toDateString())->toBe($expirationDate);
    });

    it('changes the gallery expiration date', function () {
        $gallery = Gallery::factory()->for($this->team)->create(['expiration_date' => now()->addDays(5)]);
        $newExpiration = now()->addDays(20)->toDateString();

        $component = Volt::actingAs($this->user)->test('pages.galleries.show', ['gallery' => $gallery])
            ->set('form.expirationDate', $newExpiration)
            ->call('update');
        $gallery->refresh();
        expect($gallery->expiration_date->toDateString())->toBe($newExpiration);
    });

    it('removes the gallery expiration date when subscribed', function () {
        Subscription::factory()->for($this->user->currentTeam, 'owner')->create();
        $gallery = Gallery::factory()->for($this->team)->create(['expiration_date' => now()->addDays(5)]);

        $component = Volt::actingAs($this->user)->test('pages.galleries.show', ['gallery' => $gallery])
            ->set('form.expirationDate', '')
            ->call('update');
        $gallery->refresh();
        expect($gallery->expiration_date)->toBeNull();
    });

    it('cannot remove the gallery expiration date when not subscribed', function () {
        $gallery = Gallery::factory()->for($this->team)->create(['expiration_date' => now()->addDays(5)]);

        $component = Volt::actingAs($this->user)->test('pages.galleries.show', ['gallery' => $gallery])
            ->set('form.expirationDate', '')
            ->call('update');

        $component->assertHasErrors(['form.expirationDate' => 'required']);
        $gallery->refresh();
        expect($gallery->expiration_date)->not->toBeNull();
    });
});

describe('Storage Limits', function () {
    it('increments storage_used when team uploads photo and has enough storage', function () {
        Storage::fake('public');
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

        Volt::actingAs($this->user)->test('pages.galleries.show', ['gallery' => $gallery])
            ->set('photos', [0 => $photoFile])
            ->call('save', 0);

        expect($gallery->fresh()->photos()->count())->toBe(1);
        expect($this->team->calculateStorageUsed())->toBe($initialStorageUsed + $photoSize);
    });

    it('blocks photo upload when team storage limit would be exceeded', function () {
        Storage::fake('public');
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

        $component = Volt::actingAs($this->user)->test('pages.galleries.show', ['gallery' => $gallery])
            ->set('photos', [0 => $photoFile])
            ->call('save', 0);

        expect($gallery->fresh()->photos()->count())->toBe(0);
        expect($this->team->calculateStorageUsed())->toBe($initialStorageUsed);
        $component->assertHasErrors(['photos.0' => 'You do not have enough storage space to upload this photo.']);
    });

    it('blocks photo upload when team is exactly at the storage limit', function () {
        Storage::fake('public');
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

        $component = Volt::actingAs($this->user)->test('pages.galleries.show', ['gallery' => $gallery])
            ->set('photos', [0 => $photoFile])
            ->call('save', 0);

        expect($gallery->fresh()->photos()->count())->toBe(0);
        expect($this->team->calculateStorageUsed())->toBe($initialStorageUsed);
        $component->assertHasErrors(['photos.0' => 'You do not have enough storage space to upload this photo.']);
    });

    it('blocks photo upload when team is just under the storage limit', function () {
        Storage::fake('public');
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

        $component = Volt::actingAs($this->user)->test('pages.galleries.show', ['gallery' => $gallery])
            ->set('photos', [0 => $photoFile])
            ->call('save', 0);

        expect($gallery->fresh()->photos()->count())->toBe(0);
        expect($this->team->calculateStorageUsed())->toBe($initialStorageUsed);
        $component->assertHasErrors(['photos.0' => 'You do not have enough storage space to upload this photo.']);
    });

    it('does not count deleted photos towards storage usage', function () {
        Storage::fake('public');
        Storage::fake('s3');
        Event::fake(PhotoAdded::class);

        $this->team->update([
            'custom_storage_limit' => 100 * 1024 * 1024, // 100 MB
        ]);
        // No photos yet, so storage used is 0
        $gallery = Gallery::factory()->for($this->team)->create(['ulid' => 'STORAGEDELETE']);

        $photoFile = UploadedFile::fake()->image('photo_delete.jpg', 1200, 800)->size(5 * 1024); // 5 KB
        $photoSize = $photoFile->getSize();

        Volt::actingAs($this->user)->test('pages.galleries.show', ['gallery' => $gallery])
            ->set('photos', [0 => $photoFile])
            ->call('save', 0);

        $storageAfterUpload = $this->team->calculateStorageUsed();
        expect($storageAfterUpload)->toBe($photoSize);

        $photo = $gallery->fresh()->photos()->first();
        Volt::actingAs($this->user)->test('pages.galleries.show', ['gallery' => $gallery])
            ->call('deletePhoto', $photo->id);

        expect($this->team->calculateStorageUsed())->toBe(0);
    });

    it('does not block photo upload for teams with unlimited storage regardless of usage', function () {
        Storage::fake('public');
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

        $component = Volt::actingAs($this->user)->test('pages.galleries.show', ['gallery' => $gallery])
            ->set('photos', [0 => $photoFile])
            ->call('save', 0);

        expect($gallery->fresh()->photos()->count())->toBe(1);
        expect($this->team->calculateStorageUsed())->toBeGreaterThanOrEqual($initialStorageUsed);
        $component->assertHasNoErrors();
    });
});

describe('Cover Photo', function () {
    it('allows setting a photo as the gallery cover', function () {
        $gallery = Gallery::factory()->for($this->team)->create();
        $photo = Photo::factory()->for($gallery)->create();

        $component = Volt::actingAs($this->user)->test('photo-item', ['photo' => $photo])
            ->call('setAsCover');

        expect($gallery->fresh()->cover_photo_id)->toBe($photo->id);
    });

    it('allows changing the cover photo', function () {
        $gallery = Gallery::factory()->for($this->team)->create();
        $photo1 = Photo::factory()->for($gallery)->create();
        $photo2 = Photo::factory()->for($gallery)->create();
        $gallery->update(['cover_photo_id' => $photo1->id]);

        $component = Volt::actingAs($this->user)->test('photo-item', ['photo' => $photo2])
            ->call('setAsCover');

        expect($gallery->fresh()->cover_photo_id)->toBe($photo2->id);
    });

    it('allows removing the cover photo', function () {
        $gallery = Gallery::factory()->for($this->team)->create();
        $photo = Photo::factory()->for($gallery)->create();
        $gallery->update(['cover_photo_id' => $photo->id]);

        $component = Volt::actingAs($this->user)->test('photo-item', ['photo' => $photo])
            ->call('removeAsCover');

        expect($gallery->fresh()->cover_photo_id)->toBeNull();
    });

    it('prevents setting cover photo from another team', function () {
        $gallery = Gallery::factory()->for($this->team)->create();
        $otherTeam = Team::factory()->create();
        $otherGallery = Gallery::factory()->for($otherTeam)->create();
        $photo = Photo::factory()->for($otherGallery)->create();

        $component = Volt::actingAs($this->user)->test('photo-item', ['photo' => $photo])
            ->call('setAsCover');

        $component->assertStatus(403);
        expect($gallery->fresh()->cover_photo_id)->toBeNull();
    });
});

describe('Gallery Public Access', function () {
    it('allows marking a gallery as public', function () {
        $gallery = Gallery::factory()->for($this->team)->create();
        expect($gallery->is_public)->toBeFalse();

        $component = Volt::actingAs($this->user)->test('pages.galleries.show', ['gallery' => $gallery])
            ->call('togglePublic');

        expect($gallery->fresh()->is_public)->toBeTrue();
    });

    it('allows marking a public gallery as private', function () {
        $gallery = Gallery::factory()->for($this->team)->public()->create();
        expect($gallery->is_public)->toBeTrue();

        $component = Volt::actingAs($this->user)->test('pages.galleries.show', ['gallery' => $gallery])
            ->call('togglePublic');

        expect($gallery->fresh()->is_public)->toBeFalse();
    });

    it('allows guests to view public galleries via public route', function () {
        $gallery = Gallery::factory()->for($this->team)->public()->create(['ulid' => 'PUBLICGALLERY']);
        $photo = Photo::factory()->for($gallery)->create();

        $response = get('/@' . $this->team->handle . '/portfolio/' . $gallery->ulid);

        $response->assertStatus(200);
    });

    it('prevents guests from viewing private galleries via public route', function () {
        $gallery = Gallery::factory()->for($this->team)->create(['ulid' => 'PRIVATEGALLERY']);
        expect($gallery->is_public)->toBeFalse();

        $response = get('/@' . $this->team->handle . '/portfolio/' . $gallery->ulid);

        $response->assertStatus(404);
    });
});

describe('Portfolio Photo Public Access', function () {
    it('allows guests to view public portfolio photos', function () {
        $gallery = Gallery::factory()->for($this->team)->public()->create(['ulid' => 'PUBLICGALLERY']);
        $photo = Photo::factory()->for($gallery)->create();

        $response = get('/portfolio/' . $gallery->ulid . '/photos/' . $photo->id);

        $response->assertStatus(200);
    });

    it('prevents guests from viewing private portfolio photos', function () {
        $gallery = Gallery::factory()->for($this->team)->create(['ulid' => 'PRIVATEGALLERY']);
        $photo = Photo::factory()->for($gallery)->create();
        expect($gallery->is_public)->toBeFalse();

        $response = get('/portfolio/' . $gallery->ulid . '/photos/' . $photo->id);

        $response->assertStatus(404);
    });
});

describe('Portfolio Index Public Access', function () {
    it('allows guests to view portfolio index with public galleries', function () {
        $publicGallery = Gallery::factory()->for($this->team)->public()->create(['name' => 'Public Gallery']);
        $privateGallery = Gallery::factory()->for($this->team)->create(['name' => 'Private Gallery']);
        Photo::factory()->for($publicGallery)->create();

        $response = get('/@' . $this->team->handle . '/portfolio');

        $response->assertStatus(200);
        $response->assertSee($publicGallery->name);
        $response->assertDontSee($privateGallery->name);
    });

    it('shows empty state when no public galleries exist', function () {
        Gallery::factory()->for($this->team)->create(['is_public' => false]);

        $response = get('/@' . $this->team->handle . '/portfolio');

        $response->assertStatus(200);
        $response->assertSee('No public galleries');
    });

    it('returns 404 for non-existent team handle', function () {
        $response = get('/@nonexistent/portfolio');

        $response->assertStatus(404);
    });
});
