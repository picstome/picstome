<?php

use App\Jobs\DeleteFromDisk;
use App\Models\Moodboard;
use App\Models\MoodboardPhoto;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\withoutDefer;

uses(RefreshDatabase::class);

beforeEach(function () {
    withoutDefer();

    $this->user = User::factory()->withPersonalTeam()->create();
    $this->team = $this->user->currentTeam;
});

describe('Moodboard Viewing', function () {
    it('allows users to view their team moodboard', function () {
        $moodboard = Moodboard::factory()->for($this->team)->create();
        $photoA = MoodboardPhoto::factory()->for($moodboard)->create();
        $photoB = MoodboardPhoto::factory()->for(Moodboard::factory())->create();
        $photoC = MoodboardPhoto::factory()->for($moodboard)->create();

        $response = actingAs($this->user)->get('/moodboards/1');
        $component = Volt::actingAs($this->user)->test('pages.moodboards.show', ['moodboard' => $moodboard]);

        $response->assertStatus(200);
        $response->assertViewHas('moodboard');
        expect($response['moodboard']->is($moodboard))->toBeTrue();

        expect($moodboard->photos->contains($photoA))->toBeTrue();
        expect($moodboard->photos->contains($photoB))->toBeFalse();
        expect($moodboard->photos->contains($photoC))->toBeTrue();
    });

    it('prevents guests from viewing any moodboards', function () {
        Moodboard::factory()->for($this->team)->create();

        $response = get('/moodboards/1');

        $response->assertRedirect('/login');
    });

    it('prevents users from viewing other teams\' moodboards', function () {
        Moodboard::factory()->for(Team::factory())->create();

        $response = actingAs($this->user)->get('/moodboards/1');

        $response->assertStatus(403);
    });
});

describe('Photo Upload', function () {
    it('allows photos to be added to a moodboard', function () {
        Storage::fake('s3');

        $moodboard = Moodboard::factory()->for($this->team)->create(['ulid' => '123ABC']);

        $component = Volt::actingAs($this->user)->test('pages.moodboards.show', ['moodboard' => $moodboard])->set([
            'photos.0' => UploadedFile::fake()->image('photo1.jpg'),
            'photos.1' => UploadedFile::fake()->image('photo2.jpg'),
        ])->call('savePhoto', 0)->call('savePhoto', 1);

        expect($moodboard->photos()->count())->toBe(2);
        tap($moodboard->fresh()->photos[0], function ($photo) {
            expect($photo->name)->toBe('photo1.jpg');
            expect($photo->path)->toContain('moodboards/123ABC/photos/');
            expect($photo->url)->not()->toBeNull();
            expect($photo->size)->not()->toBeNull();
            expect(Storage::disk('s3')->exists($photo->path))->toBeTrue();
        });
        tap($moodboard->fresh()->photos[1], function ($photo) {
            expect($photo->name)->toBe('photo2.jpg');
            expect($photo->path)->toContain('moodboards/123ABC/photos/');
            expect($photo->url)->not()->toBeNull();
            expect($photo->size)->not()->toBeNull();
            expect(Storage::disk('s3')->exists($photo->path))->toBeTrue();
        });
    });
});

describe('Moodboard Editing', function () {
    it('allows editing a team moodboard', function () {
        $moodboard = Moodboard::factory()->for($this->team)->create();

        $component = Volt::actingAs($this->user)->test('pages.moodboards.show', ['moodboard' => $moodboard])
            ->set('form.title', 'Edited Moodboard')
            ->set('form.description', 'Updated description')
            ->call('update');

        expect($moodboard->fresh()->title)->toBe('Edited Moodboard');
        expect($moodboard->fresh()->description)->toBe('Updated description');
    });

    it('allows updating moodboard title only', function () {
        $moodboard = Moodboard::factory()->for($this->team)->create();

        $component = Volt::actingAs($this->user)->test('pages.moodboards.show', ['moodboard' => $moodboard])
            ->set('form.title', 'New Title')
            ->call('update');

        expect($moodboard->fresh()->title)->toBe('New Title');
    });
});

describe('Photo Deletion', function () {
    it('allows users to delete a photo', function () {
        Storage::fake('public');
        Storage::fake('s3');

        $moodboard = Moodboard::factory()->for($this->team)->create();
        $photo = MoodboardPhoto::factory()->for($moodboard)->create([
            'name' => 'photo1.jpg',
            'disk' => 's3',
            'path' => UploadedFile::fake()
                ->image('photo1.jpg')
                ->storeAs('moodboards/1/photos', 'photo1.jpg', 's3'),
        ]);
        expect(Storage::disk('s3')->exists('moodboards/1/photos/photo1.jpg'))->toBeTrue();
        expect(MoodboardPhoto::count())->toBe(1);

        Queue::fake();

        $component = Volt::actingAs($this->user)->test('pages.moodboards.show', ['moodboard' => $moodboard])
            ->call('deletePhoto', $photo->id);

        expect($moodboard->photos()->count())->toBe(0);
        Queue::assertPushed(DeleteFromDisk::class);
    });

    it('prevents users from deleting another team\'s photo', function () {
        $moodboard = Moodboard::factory()->for($this->team)->create();
        $photo = MoodboardPhoto::factory()->for(Moodboard::factory()->for(Team::factory()))->create();

        $component = Volt::actingAs($this->user)->test('pages.moodboards.show', ['moodboard' => $moodboard])
            ->call('deletePhoto', $photo->id);

        $component->assertStatus(403);
    });
});

describe('Moodboard Deletion', function () {
    it('allows users to delete their team moodboard', function () {
        Storage::fake('s3');
        Queue::fake();

        $photos = collect([
            UploadedFile::fake()->image('photo1.jpg'),
            UploadedFile::fake()->image('photo2.jpg'),
        ]);
        $moodboard = Moodboard::factory()->for($this->team)->create(['title' => 'Example Moodboard']);
        $photos->each(function ($photo) use ($moodboard) {
            $moodboard->addPhoto($photo);
        });

        $component = Volt::actingAs($this->user)->test('pages.moodboards.show', ['moodboard' => $moodboard])->call('delete');
        $component->assertRedirect('/moodboards');
        expect(Moodboard::count())->toBe(0);
        expect(MoodboardPhoto::count())->toBe(0);

        $moodboard->photos->each(function ($photo) {
            Queue::assertPushed(DeleteFromDisk::class);
        });
    });
});

describe('Storage Limits', function () {
    it('blocks photo upload when team storage limit would be exceeded', function () {
        Storage::fake('s3');

        $this->team->update([
            'custom_storage_limit' => 20 * 1024,
        ]);

        MoodboardPhoto::factory()->for(
            Moodboard::factory()->for($this->team)
        )->create(['size' => 18 * 1024]);

        $moodboard = Moodboard::factory()->for($this->team)->create();

        $photoFile = UploadedFile::fake()->image('photo_upload.jpg', 1200, 800)->size(5 * 1024);
        $initialStorageUsed = $this->team->calculateStorageUsed();

        $component = Volt::actingAs($this->user)->test('pages.moodboards.show', ['moodboard' => $moodboard])
            ->set('photos.0', $photoFile)
            ->call('savePhoto', 0);

        expect($moodboard->fresh()->photos()->count())->toBe(0);
        expect($this->team->calculateStorageUsed())->toBe($initialStorageUsed);
    });

    it('blocks photo upload when team is exactly at the storage limit', function () {
        Storage::fake('s3');

        $this->team->update([
            'custom_storage_limit' => 20 * 1024,
        ]);

        MoodboardPhoto::factory()->for(
            Moodboard::factory()->for($this->team)
        )->create(['size' => 20 * 1024]);

        $moodboard = Moodboard::factory()->for($this->team)->create(['ulid' => 'STORAGEFULL']);

        $photoFile = UploadedFile::fake()->image('photo_upload.jpg', 1200, 800)->size(5 * 1024);
        $initialStorageUsed = $this->team->calculateStorageUsed();

        $component = Volt::actingAs($this->user)->test('pages.moodboards.show', ['moodboard' => $moodboard])
            ->set('photos.0', $photoFile)
            ->call('savePhoto', 0);

        expect($moodboard->fresh()->photos()->count())->toBe(0);
        expect($this->team->calculateStorageUsed())->toBe($initialStorageUsed);
    });

    it('blocks photo upload when team is just under the storage limit', function () {
        Storage::fake('s3');

        $this->team->update([
            'custom_storage_limit' => 20 * 1024,
        ]);

        MoodboardPhoto::factory()->for(
            Moodboard::factory()->for($this->team)
        )->create(['size' => 19 * 1024]);

        $moodboard = Moodboard::factory()->for($this->team)->create(['ulid' => 'STORAGEALMOSTFULL']);

        $photoFile = UploadedFile::fake()->image('photo_upload.jpg', 1200, 800)->size(2 * 1024);
        $initialStorageUsed = $this->team->calculateStorageUsed();

        $component = Volt::actingAs($this->user)->test('pages.moodboards.show', ['moodboard' => $moodboard])
            ->set('photos.0', $photoFile)
            ->call('savePhoto', 0);

        expect($moodboard->fresh()->photos()->count())->toBe(0);
        expect($this->team->calculateStorageUsed())->toBe($initialStorageUsed);
    });

    it('does not count deleted photos towards storage usage', function () {
        Storage::fake('s3');
        Queue::fake();

        $this->team->update([
            'custom_storage_limit' => 100 * 1024 * 1024,
        ]);

        $moodboard = Moodboard::factory()->for($this->team)->create(['ulid' => 'STORAGEDELETE']);

        $photoFile = UploadedFile::fake()->image('photo_delete.jpg', 1200, 800)->size(5 * 1024);
        $photoSize = $photoFile->getSize();

        Volt::actingAs($this->user)->test('pages.moodboards.show', ['moodboard' => $moodboard])
            ->set('photos.0', $photoFile)
            ->call('savePhoto', 0);

        $storageAfterUpload = $this->team->calculateStorageUsed();
        expect($storageAfterUpload)->toBe($photoSize);

        $photo = $moodboard->fresh()->photos()->first();
        Volt::actingAs($this->user)->test('pages.moodboards.show', ['moodboard' => $moodboard])
            ->call('deletePhoto', $photo->id);

        expect($this->team->calculateStorageUsed())->toBe(0);
    });

    it('does not block photo upload for teams with unlimited storage regardless of usage', function () {
        Storage::fake('s3');

        $this->team->update([
            'custom_storage_limit' => null,
        ]);

        MoodboardPhoto::factory()->for(
            Moodboard::factory()->for($this->team)
        )->create(['size' => 999999999]);

        $moodboard = Moodboard::factory()->for($this->team)->create(['ulid' => 'UNLIMITED']);

        $photoFile = UploadedFile::fake()->image('photo_upload.jpg', 1200, 800)->size(5 * 1024);
        $initialStorageUsed = $this->team->calculateStorageUsed();

        $component = Volt::actingAs($this->user)->test('pages.moodboards.show', ['moodboard' => $moodboard])
            ->set('photos.0', $photoFile)
            ->call('savePhoto', 0);

        expect($moodboard->fresh()->photos()->count())->toBe(1);
        expect($this->team->calculateStorageUsed())->toBeGreaterThanOrEqual($initialStorageUsed);
        $component->assertHasNoErrors();
    });
});
