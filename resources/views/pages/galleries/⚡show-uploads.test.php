<?php

use App\Events\PhotoAdded;
use App\Jobs\DeleteFromDisk;
use App\Jobs\ProcessPhoto;
use App\Models\FtpAccount;
use App\Models\Gallery;
use App\Models\Photo;
use App\Models\PhotoComment;
use App\Models\PhotoVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Cashier\Subscription;
use Livewire\Livewire;

use function Pest\Laravel\withoutDefer;

uses(RefreshDatabase::class);

beforeEach(function () {
    withoutDefer();

    $this->user = User::factory()->withPersonalTeam()->create();
    $this->team = $this->user->currentTeam;
});

describe('Photo Upload', function () {
    it('allows photos to be added to a gallery', function () {
        Storage::fake('s3');

        Event::fake(PhotoAdded::class);

        $gallery = Gallery::factory()->for($this->team)->create(['ulid' => '1243ABC']);

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])->set([
            'photos.0' => UploadedFile::fake()->image('photo1.jpg'),
            'photos.1' => UploadedFile::fake()->image('photo2.jpg'),
        ])->call('save', 0)->call('save', 1);

        expect($gallery->photos()->count())->toBe(2);
        tap($gallery->fresh()->photos[0], function ($photo) {
            expect($photo->name)->toBe('photo1.jpg');
            expect($photo->path)->toContain('galleries/1243ABC/photos/');
            expect($photo->url)->not()->toBeNull();
            expect($photo->size)->not()->toBeNull();
            expect(Storage::disk('s3')->exists($photo->path))->toBeTrue();
            expect($photo->status)->toBe('pending');
            Event::assertDispatched(PhotoAdded::class);
        });
        tap($gallery->fresh()->photos[1], function ($photo) {
            expect($photo->name)->toBe('photo2.jpg');
            expect($photo->path)->toContain('galleries/1243ABC/photos/');
            expect($photo->url)->not()->toBeNull();
            expect($photo->size)->not()->toBeNull();
            expect(Storage::disk('s3')->exists($photo->path))->toBeTrue();
            expect($photo->status)->toBe('pending');
            Event::assertDispatched(PhotoAdded::class);
        });
    });

    it('dispatches ProcessPhoto job when a photo is added', function () {
        Queue::fake();
        Storage::fake('s3');

        $gallery = Gallery::factory()->for($this->team)->create(['ulid' => '1243ABC']);

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])->set([
            'photos.0' => UploadedFile::fake()->image('photo1.jpg', 129, 129),
        ])->call('save', 0);

        $photo = $gallery->fresh()->photos[0];
        Queue::assertPushed(ProcessPhoto::class, function ($job) use ($photo) {
            return $job->photo->is($photo);
        });
    });

    it('allows pdfs to be added to a gallery', function () {
        Storage::fake('s3');

        Event::fake(PhotoAdded::class);

        $gallery = Gallery::factory()->for($this->team)->create(['ulid' => '1243ABC']);

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->set('photos.0', UploadedFile::fake()->createWithContent('composite.pdf', '%PDF-1.4 fake pdf body'))
            ->call('save', 0);

        $component->assertHasNoErrors();

        $photo = $gallery->fresh()->photos[0];
        expect($photo->name)->toBe('composite.pdf');
        expect($photo->path)->toEndWith('.pdf');
        expect($photo->status)->toBe('pending');
        expect(Storage::disk('s3')->exists($photo->path))->toBeTrue();
    });

    it('rejects files with extensions outside the upload allowlist', function () {
        Storage::fake('s3');

        Event::fake(PhotoAdded::class);

        $gallery = Gallery::factory()->for($this->team)->create(['ulid' => '1243ABC']);

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery]);

        foreach (['invoice.exe', 'evil.html', 'README'] as $index => $fileName) {
            $component->set("photos.{$index}", UploadedFile::fake()->create($fileName, 100))
                ->call('save', $index)
                ->assertHasErrors(["photos.{$index}" => 'This file type is not supported.']);
        }

        expect($gallery->fresh()->photos()->count())->toBe(0);
        Event::assertNotDispatched(PhotoAdded::class);
    });

    it('rejects pdfs whose content is not a pdf', function () {
        Storage::fake('s3');

        Event::fake(PhotoAdded::class);

        $gallery = Gallery::factory()->for($this->team)->create(['ulid' => '1243ABC']);

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->set('photos.0', UploadedFile::fake()->createWithContent('composite.pdf', '<html>not a pdf</html>'))
            ->call('save', 0);

        $component->assertHasErrors(['photos.0' => 'This file is not a valid PDF.']);
        expect($gallery->fresh()->photos()->count())->toBe(0);
    });

    it('rejects files whose name contains control characters', function () {
        Storage::fake('s3');

        Event::fake(PhotoAdded::class);

        $gallery = Gallery::factory()->for($this->team)->create(['ulid' => '1243ABC']);

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->set('photos.0', UploadedFile::fake()->createWithContent("evil\0composite.pdf", '%PDF-1.4 fake pdf body'))
            ->call('save', 0);

        $component->assertHasErrors(['photos.0' => 'The file name contains invalid characters.']);
        expect($gallery->fresh()->photos()->count())->toBe(0);
    });

    it('accepts every previously advertised upload extension', function (string $extension) {
        Storage::fake('s3');

        Event::fake(PhotoAdded::class);

        $gallery = Gallery::factory()->for($this->team)->create(['ulid' => '1243ABC']);

        // The extension alone is not trusted any more: image and video bytes
        // are inspected, so each format is faked with a readable header.
        $file = match ($extension) {
            'jpg', 'jpeg', 'png', 'tiff' => UploadedFile::fake()->image("photo.{$extension}"),
            'mp4' => UploadedFile::fake()->createWithContent('photo.mp4', "\x00\x00\x00\x18ftypmp42\x00\x00\x00\x00"),
            'webm' => UploadedFile::fake()->createWithContent('photo.webm', "\x1A\x45\xDF\xA3\x01\x00\x00\x00"),
            'ogg' => UploadedFile::fake()->createWithContent('photo.ogg', 'OggS\x00\x02'),
            default => UploadedFile::fake()->create("photo.{$extension}", 100),
        };

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->submit([
                'photos.0' => $file,
            ], 'save', 0);

        $component->assertHasNoErrors();
        expect($gallery->fresh()->photos()->count())->toBe(1);
        expect($gallery->fresh()->photos[0]->name)->toBe("photo.{$extension}");
    })->with(['jpg', 'jpeg', 'png', 'tiff', 'mp4', 'webm', 'ogg', 'cr2', 'cr3', 'nef', 'arw', 'dng', 'orf', 'rw2', 'pef', 'srw', 'mos', 'mrw', '3fr']);

    it('rejects an image whose bytes are not an image', function () {
        Storage::fake('s3');

        Event::fake(PhotoAdded::class);

        $gallery = Gallery::factory()->for($this->team)->create(['ulid' => '1243ABC']);

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->set('photos.0', UploadedFile::fake()->createWithContent('photo.jpg', '<html>not an image</html>'))
            ->call('save', 0);

        $component->assertHasErrors(['photos.0' => 'This file is not a readable image.']);
        expect($gallery->fresh()->photos()->count())->toBe(0);
    });

    it('rejects a video whose bytes are not a video', function () {
        Storage::fake('s3');

        Event::fake(PhotoAdded::class);

        $gallery = Gallery::factory()->for($this->team)->create(['ulid' => '1243ABC']);

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->set('photos.0', UploadedFile::fake()->createWithContent('video.mp4', 'this is not a video container'))
            ->call('save', 0);

        $component->assertHasErrors(['photos.0' => 'This file is not a readable video.']);
        expect($gallery->fresh()->photos()->count())->toBe(0);
    });

    it('keeps the upload extension allowlist equal to the advertised formats plus pdf', function () {
        expect(config('picstome.upload_extensions'))->toBe([
            'jpg', 'jpeg', 'png', 'tiff', 'mp4', 'webm', 'ogg',
            'cr2', 'cr3', 'nef', 'arw', 'dng', 'orf', 'rw2', 'pef', 'srw', 'mos', 'mrw', '3fr',
            'pdf',
        ]);
    });

    it('finds the selected file names that already exist in the gallery', function () {
        $gallery = Gallery::factory()->for($this->team)->create();
        Photo::factory()->for($gallery)->create(['name' => 'photo1.jpg']);
        Photo::factory()->for($gallery)->create(['name' => 'a_id_.jpg']);

        Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->call('findDuplicateNames', ['photo1.jpg', 'photo2.jpg', 'a`id`.jpg', 'photo1.jpg'])
            ->assertSet('existingPhotoNames', ['photo1.jpg', 'a`id`.jpg']);
    });

    it('creates a new version when a duplicate file name is uploaded', function () {
        Storage::fake('s3');

        Event::fake(PhotoAdded::class);

        $gallery = Gallery::factory()->for($this->team)->create();
        $photo = Photo::factory()->for($gallery)->create(['name' => 'photo1.jpg', 'path' => 'old/path/photo1.jpg', 'source_name' => 'photo1.jpg', 'version_count' => 1]);

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->set('photos.0', UploadedFile::fake()->image('photo1.jpg'))
            ->call('save', 0);

        $component->assertHasNoErrors();
        $photo->refresh();
        expect($gallery->photos()->count())->toBe(1);
        expect($photo->version_count)->toBe(2);
        expect(PhotoVersion::where('photo_id', $photo->id)->count())->toBe(1);
        expect($photo->path)->not->toBe('old/path/photo1.jpg');
    });

    it('records a new version when uploading the same file name again', function () {
        Storage::fake('s3');
        Queue::fake();

        $gallery = Gallery::factory()->for($this->team)->create();
        $photo = Photo::factory()->for($gallery)->favorited()->create([
            'name' => 'photo1.jpg',
            'source_name' => 'photo1.jpg',
            'path' => 'old/path/photo1.jpg',
            'size' => 1024,
            'status' => 'processed',
            'version_count' => 1,
        ]);
        PhotoComment::factory()->for($photo)->create();

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->set('photos.0', UploadedFile::fake()->image('photo1.jpg'))
            ->call('save', 0);

        $component->assertHasNoErrors();
        $photo->refresh();

        // Same row survives, so favorites and comments are preserved.
        expect($gallery->photos()->count())->toBe(1);
        expect($photo->favorited_at)->not->toBeNull();
        expect($photo->comments)->toHaveCount(1);

        // The older version is retained and processing is scheduled again.
        expect($photo->path)->not->toBe('old/path/photo1.jpg');
        expect($photo->size)->toBeGreaterThan(0);
        expect($photo->status)->toBe('pending');
        expect($photo->version_count)->toBe(2);
        expect(PhotoVersion::where('photo_id', $photo->id)->count())->toBe(1);

        Queue::assertPushed(ProcessPhoto::class);
        Queue::assertNotPushed(function (DeleteFromDisk $job) {
            return $job->path === 'old/path/photo1.jpg';
        });
    });

    it('replaces only the current version and keeps older versions', function () {
        Storage::fake('s3');
        Queue::fake();

        $gallery = Gallery::factory()->for($this->team)->create();
        $photo = Photo::factory()->for($gallery)->create([
            'name' => 'photo1.jpg',
            'source_name' => 'photo1.jpg',
            'path' => 'old/current.jpg',
            'size' => 1024,
            'status' => 'processed',
            'version_count' => 2,
        ]);
        $oldVersion = PhotoVersion::factory()->for($photo)->create([
            'version_number' => 1,
            'path' => 'old/history.jpg',
        ]);

        Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->set('photos.0', UploadedFile::fake()->image('photo1.jpg'))
            ->call('save', 0, 'replace')
            ->assertHasNoErrors();

        $photo->refresh();

        expect($photo->version_count)->toBe(2);
        expect($photo->current_version_id)->toBeNull();
        expect($photo->path)->toBe('old/current.jpg');
        expect($photo->versions()->count())->toBe(1);
        expect($oldVersion->fresh()->path)->toBe('old/history.jpg');
        Queue::assertNotPushed(DeleteFromDisk::class, fn ($job) => $job->path === 'old/history.jpg');
    });

    it('adds the photo when replace is chosen but the original was already deleted', function () {
        Storage::fake('s3');

        Event::fake(PhotoAdded::class);

        $gallery = Gallery::factory()->for($this->team)->create();

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->set('photos.0', UploadedFile::fake()->image('photo1.jpg'))
            ->call('save', 0, true);

        $component->assertHasNoErrors();
        expect($gallery->photos()->count())->toBe(1);
    });
});

describe('Live Session Original Size', function () {
    it('keeps the original size switch in sync with the gallery', function () {
        $gallery = Gallery::factory()->for($this->team)->create(['keep_original_size' => true]);

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery]);

        expect($component->get('keepOriginalSize'))->toBeTrue();
    });

    it('syncs the edit gallery modal with the camera upload modal', function () {
        $gallery = Gallery::factory()->for($this->team)->create(['keep_original_size' => true]);

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->set('keepOriginalSize', false);

        expect($gallery->fresh()->keep_original_size)->toBeFalse();
        expect($component->get('form')->keepOriginalSize)->toBeFalse();
    });

    it('syncs the camera upload modal when the gallery is edited', function () {
        $gallery = Gallery::factory()->for($this->team)->create([
            'keep_original_size' => true,
            'expiration_date' => now()->addMonth()->startOfDay(),
        ]);

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->submit([
                'form.keepOriginalSize' => false,
            ], 'update')
            ->assertHasNoErrors();

        expect($gallery->fresh()->keep_original_size)->toBeFalse();
        expect($component->get('keepOriginalSize'))->toBeFalse();
    });

    it('stores the choice on the gallery and remembers it for new galleries', function () {
        $gallery = Gallery::factory()->for($this->team)->create(['keep_original_size' => false]);

        Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->set('keepOriginalSize', true);

        expect($gallery->fresh()->keep_original_size)->toBeTrue();
        expect($this->user->fresh()->galleryDefaults()['keep_original_size'])->toBeTrue();
    });
});

describe('Live Camera Upload', function () {
    it('opens the live upload modal when arriving with the live flag', function () {
        Subscription::factory()->for($this->user->currentTeam, 'owner')->create();

        $gallery = Gallery::factory()->for($this->team)->create();
        FtpAccount::factory()->for($this->team)->create(['active_gallery_id' => $gallery->id]);

        Livewire::actingAs($this->user)->withQueryParams(['live' => '1'])
            ->test('pages::galleries.show', ['gallery' => $gallery])
            ->assertHasNoErrors()
            ->assertSee('Camera setup');
    });

    it('offers camera upload to free teams too', function () {
        config(['picstome.ftp_uploads.host' => 'upload.picstome.com']);

        $gallery = Gallery::factory()->for($this->team)->create();

        Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->assertDontSee('Unlock camera upload')
            ->assertSee('Camera upload');
    });

    it('provisions a camera account for a free team', function () {
        config(['picstome.ftp_uploads.sftpgo_url' => null]);

        $gallery = Gallery::factory()->for($this->team)->create();

        Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->call('toggleFtpUploads');

        expect(FtpAccount::first())->not->toBeNull()
            ->and(FtpAccount::first()->routesTo($gallery))->toBeTrue();
    });

    it('does not create an ftp account when the team has no storage left', function () {
        $this->team->update(['custom_storage_limit' => 0]);

        $gallery = Gallery::factory()->for($this->team)->create();

        Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->call('toggleFtpUploads');

        expect(FtpAccount::count())->toBe(0);
    });

    it('provisions a team account and routes uploads to the gallery when subscribed', function () {
        config(['picstome.ftp_uploads.sftpgo_url' => null]);

        Subscription::factory()->for($this->user->currentTeam, 'owner')->create();

        $gallery = Gallery::factory()->for($this->team)->create();

        $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->call('toggleFtpUploads');

        tap(FtpAccount::first(), function ($account) use ($gallery) {
            expect($account)->not->toBeNull();
            expect($account->team_id)->toBe($this->team->id);
            expect($account->routesTo($gallery))->toBeTrue();
            expect($account->username)->toMatch('/^\d{6}$/');
            expect($account->password)->toMatch('/^\d{8}$/');
        });

        $component->assertSee(FtpAccount::first()->username);
    });

    it('stops routing uploads when toggled again without touching the credentials', function () {
        Subscription::factory()->for($this->user->currentTeam, 'owner')->create();

        $gallery = Gallery::factory()->for($this->team)->create();
        $account = FtpAccount::factory()->for($this->team)->create(['active_gallery_id' => $gallery->id]);

        Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->call('toggleFtpUploads');

        expect($account->fresh()->active)->toBeTrue();
        expect($account->fresh()->active_gallery_id)->toBeNull();
    });

    it('shows which gallery is receiving uploads when it is another one', function () {
        Subscription::factory()->for($this->user->currentTeam, 'owner')->create();

        $gallery = Gallery::factory()->for($this->team)->create();
        $otherGallery = Gallery::factory()->for($this->team)->create(['name' => 'Other Gallery']);
        FtpAccount::factory()->for($this->team)->create(['active_gallery_id' => $otherGallery->id]);

        Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->assertSee('Other Gallery');
    });

    it('rolls back the account when the sftpgo server cannot be reached', function () {
        config([
            'picstome.ftp_uploads.sftpgo_url' => 'https://sftpgo.test',
            'picstome.ftp_uploads.sftpgo_api_key' => 'secret-key',
        ]);

        Http::fake([
            'sftpgo.test/*' => Http::response('boom', 500),
        ]);

        Subscription::factory()->for($this->user->currentTeam, 'owner')->create();

        $gallery = Gallery::factory()->for($this->team)->create();

        Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
            ->call('toggleFtpUploads');

        expect(FtpAccount::count())->toBe(0);
    });
});

it('returns the uploaded photo id and status so the modal can follow processing', function () {
    Storage::fake('s3');
    Event::fake(PhotoAdded::class);

    $gallery = Gallery::factory()->for($this->team)->create();

    $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery])
        ->set('photos.0', UploadedFile::fake()->image('photo1.jpg'))
        ->call('save', 0);

    $component->assertReturned(function ($result) {
        return is_array($result)
            && isset($result['photoId'], $result['status'])
            && $result['status'] === 'pending';
    });
});

it('returns upload statuses only for photos in the current gallery', function () {
    $gallery = Gallery::factory()->for($this->team)->create();
    $photo = Photo::factory()->for($gallery)->create(['status' => 'warming']);
    $other = Photo::factory()->for(Gallery::factory())->create(['status' => 'processing']);

    $component = Livewire::actingAs($this->user)->test('pages::galleries.show', ['gallery' => $gallery]);

    expect($component->call('uploadStatuses', [$photo->id, $other->id])->effects['returns'][0])
        ->toBe([$photo->id => 'warming']);
});

it('refreshes the remaining storage when camera uploads are paused for quota', function () {
    $this->team->update(['custom_storage_limit' => 1000]);
    $gallery = Gallery::factory()->for($this->team)->create();

    $component = Livewire::actingAs($this->user)
        ->test('pages::galleries.show', ['gallery' => $gallery]);

    expect($component->get('remainingStorageBytes'))->toBe(1000);

    $this->team->update(['custom_storage_limit' => 10]);

    $component->dispatch('ftp-quota-paused');

    expect($component->get('remainingStorageBytes'))->toBe(10);
});
