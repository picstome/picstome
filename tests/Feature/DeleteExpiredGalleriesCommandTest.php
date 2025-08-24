<?php

use App\Models\Gallery;
use App\Models\Photo;
use App\Models\Team;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use function Pest\Laravel\artisan;

uses(RefreshDatabase::class);

describe('DeleteExpiredGalleriesCommand', function () {
    beforeEach(function () {
        $this->disk = config('picstome.disk');
        Storage::fake($this->disk);
        $this->team = Team::factory()->create();
    });

    it('deletes galleries with expiration dates more than one day in the past', function () {
        $gallery = Gallery::factory()->for($this->team)->create([
            'expiration_date' => now()->subDays(2),
        ]);
        $file = UploadedFile::fake()->image('photo.jpg');
        $path = $file->store('galleries/photos', ['disk' => $this->disk]);
        $photo = Photo::factory()->for($gallery)->create([
            'path' => $path,
            'disk' => $this->disk,
        ]);

        artisan('galleries:delete-expired')->assertExitCode(0);

        expect(Gallery::find($gallery->id))->toBeNull();
        expect(Photo::find($photo->id))->toBeNull();
        expect(Storage::disk($photo->disk)->exists($photo->path))->toBeFalse();
    });

    it('does not delete galleries expired less than one day ago', function () {
        $gallery = Gallery::factory()->for($this->team)->create([
            'expiration_date' => now()->subDay(), // exactly 1 day ago
        ]);
        $file = UploadedFile::fake()->image('photo.jpg');
        $path = $file->store('galleries/photos', ['disk' => $this->disk]);
        $photo = Photo::factory()->for($gallery)->create([
            'path' => $path,
            'disk' => $this->disk,
        ]);

        artisan('galleries:delete-expired')->assertExitCode(0);

        expect(Gallery::find($gallery->id))->not->toBeNull();
        expect(Photo::find($photo->id))->not->toBeNull();
        expect(Storage::disk($photo->disk)->exists($photo->path))->toBeTrue();
    });

    it('does not delete galleries with expiration dates in the future', function () {
        $gallery = Gallery::factory()->for($this->team)->create([
            'expiration_date' => now()->addDay(),
        ]);
        $file = UploadedFile::fake()->image('photo.jpg');
        $path = $file->store('galleries/photos', ['disk' => $this->disk]);
        $photo = Photo::factory()->for($gallery)->create([
            'path' => $path,
            'disk' => $this->disk,
        ]);

        artisan('galleries:delete-expired')->assertExitCode(0);

        expect(Gallery::find($gallery->id))->not->toBeNull();
        expect(Photo::find($photo->id))->not->toBeNull();
        expect(Storage::disk($photo->disk)->exists($photo->path))->toBeTrue();
    });

    it('does not delete galleries with no expiration date', function () {
        $gallery = Gallery::factory()->for($this->team)->create([
            'expiration_date' => null,
        ]);
        $file = UploadedFile::fake()->image('photo.jpg');
        $path = $file->store('galleries/photos', ['disk' => $this->disk]);
        $photo = Photo::factory()->for($gallery)->create([
            'path' => $path,
            'disk' => $this->disk,
        ]);

        artisan('galleries:delete-expired')->assertExitCode(0);

        expect(Gallery::find($gallery->id))->not->toBeNull();
        expect(Photo::find($photo->id))->not->toBeNull();
        expect(Storage::disk($photo->disk)->exists($photo->path))->toBeTrue();
    });
});
