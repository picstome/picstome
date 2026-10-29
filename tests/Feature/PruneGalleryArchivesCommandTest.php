<?php

use App\Models\Gallery;
use App\Models\GalleryArchive;
use App\Models\Team;
use App\Models\User;
use App\Services\GalleryArchiveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('s3');

    $this->user = User::factory()->withPersonalTeam()->create();
    $this->team = $this->user->currentTeam;
});

function expiredArchive(string $teamId): GalleryArchive
{
    $gallery = Gallery::factory()->for(Team::find($teamId))->create();
    $gallery->addPhoto(UploadedFile::fake()->image('photo1.jpg'));

    $service = app(GalleryArchiveService::class);
    $archive = $service->findOrCreate($gallery, 'all');
    $service->build($archive->refresh());

    $archive->refresh()->update(['expires_at' => now()->subDay()]);

    return $archive->refresh();
}

it('deletes expired archive objects and marks the row expired', function () {
    $archive = expiredArchive($this->team->id);
    $path = $archive->path;

    $this->artisan('gallery-archives:prune', ['--force' => true])->assertSuccessful();

    Storage::disk('s3')->assertMissing($path);

    expect($archive->refresh()->status)->toBe('expired')
        ->and($archive->path)->toBeNull();
});

it('does not delete anything in dry-run mode', function () {
    $archive = expiredArchive($this->team->id);
    $path = $archive->path;

    $this->artisan('gallery-archives:prune', ['--dry-run' => true])->assertSuccessful();

    Storage::disk('s3')->assertExists($path);

    expect($archive->refresh()->status)->toBe('ready');
});

it('removes old expired rows', function () {
    $archive = expiredArchive($this->team->id);
    $archive->update([
        'status' => 'expired',
        'path' => null,
        'updated_at' => now()->subDays(8),
    ]);

    $this->artisan('gallery-archives:prune', ['--force' => true])->assertSuccessful();

    $this->assertDatabaseMissing('gallery_archives', ['id' => $archive->id]);
});

it('marks stuck builds as expired', function () {
    $archive = expiredArchive($this->team->id);
    $archive->update([
        'status' => 'building',
        'expires_at' => null,
        'started_at' => now()->subHours(3),
    ]);

    $this->artisan('gallery-archives:prune', ['--force' => true])->assertSuccessful();

    expect($archive->refresh()->status)->toBe('expired');
});

it('requires an explicit force flag for destructive runs', function () {
    expiredArchive($this->team->id);

    $this->artisan('gallery-archives:prune')->assertFailed();
});
