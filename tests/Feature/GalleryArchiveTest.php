<?php

use App\Jobs\BuildGalleryArchive;
use App\Jobs\DeleteGalleryArchive;
use App\Models\Gallery;
use App\Models\GalleryArchive;
use App\Models\Photo;
use App\Models\PhotoVersion;
use App\Models\User;
use App\Services\GalleryArchiveService;
use App\Services\StorageUsageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('s3');
    Queue::fake();

    $this->user = User::factory()->withPersonalTeam()->create();
    $this->team = $this->user->currentTeam;
});

function makeGalleryArchive(Gallery $gallery, string $variant = 'all'): GalleryArchive
{
    $gallery->photos()->update(['status' => 'processed']);

    $service = app(GalleryArchiveService::class);
    $archive = $service->findOrCreate($gallery, $variant);
    $service->build($archive->refresh());

    return $archive->refresh();
}

function archiveZipEntries(GalleryArchive $archive): array
{
    $contents = Storage::disk($archive->disk)->get($archive->path);
    $path = tempnam(sys_get_temp_dir(), 'gallery-archive-test-');
    file_put_contents($path, $contents);

    $zip = new ZipArchive;
    $zip->open($path);

    $entries = [];

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $entries[] = $zip->getNameIndex($i);
    }

    $zip->close();
    unlink($path);

    return $entries;
}

it('builds a zip with processed images, videos and pdfs but no raw files', function () {
    $gallery = Gallery::factory()->for($this->team)->create(['name' => 'Example Gallery']);

    $gallery->addPhoto(UploadedFile::fake()->image('photo1.jpg'));
    $gallery->addPhoto(UploadedFile::fake()->create('clip.mp4', 10));
    $gallery->addPhoto(UploadedFile::fake()->createWithContent('document.pdf', '%PDF-1.4 fake pdf body'));

    $rawShot = $gallery->addPhoto(UploadedFile::fake()->image('rawshot.jpg'));
    Storage::disk('s3')->put('raw/rawshot.cr2', 'raw-content');
    $rawShot->update([
        'name' => 'rawshot.cr2',
        'raw_path' => 'raw/rawshot.cr2',
        'size' => 2000,
        'stored_size' => 3000,
    ]);

    Photo::factory()->for($gallery)->create([
        'name' => 'only-raw.cr2',
        'path' => 'raw/only-raw.cr2',
        'disk' => 's3',
    ]);

    $archive = makeGalleryArchive($gallery);

    expect($archive->status)->toBe('ready')
        ->and($archive->images_count)->toBe(2)
        ->and($archive->videos_count)->toBe(1)
        ->and($archive->pdfs_count)->toBe(1)
        ->and($archive->photos_skipped)->toBe(1)
        ->and($archive->photos_processed)->toBe(4)
        ->and($archive->photos_total)->toBe(5)
        ->and($archive->size_bytes)->toBeGreaterThan(0)
        ->and($archive->expires_at)->not->toBeNull();

    $entries = archiveZipEntries($archive);

    expect($entries)->toContain('photo1.jpg')
        ->toContain('clip.mp4')
        ->toContain('document.pdf')
        ->toContain('rawshot.jpg')
        ->not->toContain('rawshot.cr2')
        ->not->toContain('only-raw.cr2');
});

it('only includes the current version of an image', function () {
    $gallery = Gallery::factory()->for($this->team)->create();
    $photo = $gallery->addPhoto(UploadedFile::fake()->image('photo1.jpg'));

    Storage::disk('s3')->put('versions/old.jpg', 'old-version');

    PhotoVersion::factory()->for($photo)->create([
        'name' => 'old.jpg',
        'path' => 'versions/old.jpg',
        'disk' => 's3',
    ]);

    $archive = makeGalleryArchive($gallery);
    $entries = archiveZipEntries($archive);

    expect($entries)->toContain('photo1.jpg')
        ->not->toContain('old.jpg');
});

it('skips missing files and records them', function () {
    $gallery = Gallery::factory()->for($this->team)->create();

    Photo::factory()->for($gallery)->create([
        'name' => 'missing.jpg',
        'path' => 'missing/missing.jpg',
        'disk' => 's3',
    ]);

    $archive = makeGalleryArchive($gallery);

    expect($archive->status)->toBe('ready')
        ->and($archive->photos_processed)->toBe(0)
        ->and($archive->photos_skipped)->toBe(1)
        ->and(archiveZipEntries($archive))->toBe([]);
});

it('reuses a ready archive until the content changes', function () {
    $gallery = Gallery::factory()->for($this->team)->create();
    $gallery->addPhoto(UploadedFile::fake()->image('photo1.jpg'));

    $archive = makeGalleryArchive($gallery);

    $same = app(GalleryArchiveService::class)->findOrCreate($gallery, 'all', $this->user);

    expect($same->id)->toBe($archive->id);
    Queue::assertPushed(BuildGalleryArchive::class, 1);
});

it('invalidates and deletes the archive when a photo changes', function () {
    $gallery = Gallery::factory()->for($this->team)->create();
    $photo = $gallery->addPhoto(UploadedFile::fake()->image('photo1.jpg'));

    $archive = makeGalleryArchive($gallery);
    $oldPath = $archive->path;

    $photo->update(['name' => 'changed.jpg']);

    $archive->refresh();

    expect($archive->status)->toBe('expired');

    Queue::assertPushed(DeleteGalleryArchive::class, 1);
    Queue::pushed(DeleteGalleryArchive::class)->first()->handle();

    $archive->refresh();

    expect($archive->path)->toBeNull();

    Storage::disk('s3')->assertMissing($oldPath);

    $new = app(GalleryArchiveService::class)->findOrCreate($gallery, 'all', $this->user);

    expect($new->id)->not->toBe($archive->id)
        ->and($new->status)->toBe('pending');

    Queue::assertPushed(BuildGalleryArchive::class, 2);
});

it('invalidates the archive when a new photo is uploaded', function () {
    $gallery = Gallery::factory()->for($this->team)->create();
    $gallery->addPhoto(UploadedFile::fake()->image('first.jpg'));

    $archive = makeGalleryArchive($gallery);
    $oldPath = $archive->path;

    $gallery->addPhoto(UploadedFile::fake()->image('second.jpg'));

    $archive->refresh();

    expect($archive->status)->toBe('expired')
        ->and($archive->path)->toBeNull();

    Queue::assertPushed(DeleteGalleryArchive::class, 1);
    Queue::pushed(DeleteGalleryArchive::class)->first()->handle();

    Storage::disk('s3')->assertMissing($oldPath);
});

it('invalidates the archive when a photo is deleted', function () {

    $gallery = Gallery::factory()->for($this->team)->create();
    $photo = $gallery->addPhoto(UploadedFile::fake()->image('photo1.jpg'));

    $archive = makeGalleryArchive($gallery);
    $oldPath = $archive->path;

    $photo->delete();

    $archive->refresh();

    expect($archive->status)->toBe('expired');

    Queue::assertPushed(DeleteGalleryArchive::class, 1);
    Queue::pushed(DeleteGalleryArchive::class)->first()->handle();

    $archive->refresh();

    expect($archive->path)->toBeNull();

    Storage::disk('s3')->assertMissing($oldPath);
});

it('deletes stored archives when the gallery is deleted', function () {
    $gallery = Gallery::factory()->for($this->team)->create();
    $gallery->addPhoto(UploadedFile::fake()->image('photo1.jpg'));

    $archive = makeGalleryArchive($gallery);
    $oldPath = $archive->path;

    $gallery->delete();

    Queue::assertPushed(DeleteGalleryArchive::class, 1);
    Queue::pushed(DeleteGalleryArchive::class)->first()->handle();

    Storage::disk('s3')->assertMissing($oldPath);
    $this->assertDatabaseMissing('gallery_archives', ['id' => $archive->id]);
});

it('serves a ready archive through a presigned url', function () {
    $gallery = Gallery::factory()->for($this->team)->create();
    $gallery->addPhoto(UploadedFile::fake()->image('photo1.jpg'));

    $archive = makeGalleryArchive($gallery);

    Storage::disk('s3')->buildTemporaryUrlsUsing(function (string $path) {
        return 'https://s3.test/'.$path;
    });

    actingAs($this->user)
        ->get(route('galleries.download', $gallery))
        ->assertRedirect('https://s3.test/'.$archive->path);

    expect($archive->fresh()->downloads_count)->toBe(1);
});

it('uses a one-hour ttl for gallery archive download urls', function () {
    $gallery = Gallery::factory()->for($this->team)->create();
    $gallery->addPhoto(UploadedFile::fake()->image('photo1.jpg'));

    $archive = makeGalleryArchive($gallery);
    $signed = fakeTemporaryUrls();

    actingAs($this->user)
        ->get(route('galleries.download', $gallery))
        ->assertRedirect('https://s3.test/'.$archive->path);

    expect($signed->expirations[0]->getTimestamp())
        ->toBeGreaterThan(now()->addMinutes(59)->getTimestamp())
        ->toBeLessThan(now()->addMinutes(61)->getTimestamp());
});

it('redirects to the gallery download tab while the zip is being prepared', function () {

    $gallery = Gallery::factory()->for($this->team)->create();
    $gallery->addPhoto(UploadedFile::fake()->image('photo1.jpg'));

    actingAs($this->user)
        ->get(route('galleries.download', $gallery))
        ->assertRedirect(route('galleries.show', ['gallery' => $gallery, 'activeTab' => 'download']));

    $this->assertDatabaseHas('gallery_archives', [
        'gallery_id' => $gallery->id,
        'variant' => 'all',
        'status' => 'pending',
    ]);

    Queue::assertPushed(BuildGalleryArchive::class);
});

it('does not generate a zip when the gallery is viewed', function () {
    $gallery = Gallery::factory()->for($this->team)->create();
    $gallery->addPhoto(UploadedFile::fake()->image('photo1.jpg'));

    actingAs($this->user)->get(route('galleries.show', $gallery))->assertOk();

    $this->assertDatabaseCount('gallery_archives', 0);
    Queue::assertNothingPushed();
});

it('falls back to the legacy streamed download when archives are disabled', function () {
    config()->set('picstome.gallery_archives.enabled', false);

    $gallery = Gallery::factory()->for($this->team)->create(['name' => 'Example Gallery']);
    $gallery->addPhoto(UploadedFile::fake()->image('photo1.jpg'));

    actingAs($this->user)
        ->get(route('galleries.download', $gallery))
        ->assertDownload('example-gallery.zip');
});

it('redirects shared downloads to the shared gallery download tab while preparing', function () {
    $gallery = Gallery::factory()->shared()->downloadable()->for($this->team)->create(['ulid' => '0123ABC']);
    $gallery->addPhoto(UploadedFile::fake()->image('photo1.jpg'));

    get(route('shares.download', $gallery))
        ->assertRedirect(route('shares.show', [
            'gallery' => $gallery,
            'slug' => $gallery->slug,
            'activeTab' => 'download',
        ]));

    $this->assertDatabaseHas('gallery_archives', [
        'gallery_id' => $gallery->id,
        'variant' => 'all',
        'status' => 'pending',
    ]);
});

it('serves a ready shared archive through a presigned url', function () {
    $gallery = Gallery::factory()->shared()->downloadable()->for($this->team)->create(['ulid' => '0123ABC']);
    $gallery->addPhoto(UploadedFile::fake()->image('photo1.jpg'));

    $archive = makeGalleryArchive($gallery);

    Storage::disk('s3')->buildTemporaryUrlsUsing(function (string $path) {
        return 'https://s3.test/'.$path;
    });

    get(route('shares.download', $gallery))
        ->assertRedirect('https://s3.test/'.$archive->path);

    expect($archive->fresh()->downloads_count)->toBe(1);
});

it('enforces the shared download flags before creating an archive', function () {
    $shared = Gallery::factory()->shared()->undownloadable()->for($this->team)->create(['ulid' => '0123ABC']);
    $shared->addPhoto(UploadedFile::fake()->image('photo1.jpg'));

    get(route('shares.download', $shared))->assertStatus(401);

    $this->assertDatabaseCount('gallery_archives', 0);
    Queue::assertNothingPushed();
});

it('only includes favorites in the favorites variant', function () {
    $gallery = Gallery::factory()->for($this->team)->create();
    $favorite = $gallery->addPhoto(UploadedFile::fake()->image('favorite.jpg'));
    $favorite->toggleFavorite();
    $gallery->addPhoto(UploadedFile::fake()->image('other.jpg'));

    $archive = makeGalleryArchive($gallery, 'favorites');
    $entries = archiveZipEntries($archive);

    expect($archive->variant)->toBe('favorites')
        ->and($entries)->toContain('favorite.jpg')
        ->and($entries)->not->toContain('other.jpg');
});

it('handles duplicate archive entry names', function () {
    $gallery = Gallery::factory()->for($this->team)->create();
    $gallery->addPhoto(UploadedFile::fake()->image('same.jpg'));
    $gallery->addPhoto(UploadedFile::fake()->image('same.jpg'));

    $archive = makeGalleryArchive($gallery);
    $entries = archiveZipEntries($archive);

    expect($entries)->toContain('same.jpg')
        ->toContain('same (1).jpg');
});

it('does not invalidate the all archive when only a favorite changes', function () {
    $gallery = Gallery::factory()->for($this->team)->create();
    $photo = $gallery->addPhoto(UploadedFile::fake()->image('photo1.jpg'));

    $all = makeGalleryArchive($gallery);
    $favorites = makeGalleryArchive($gallery, 'favorites');

    $photo->toggleFavorite();

    expect($all->refresh()->status)->toBe('ready')
        ->and($favorites->refresh()->status)->toBe('expired');
});

it('invalidates both variants when the gallery name changes', function () {
    $gallery = Gallery::factory()->for($this->team)->create(['name' => 'Old Name']);
    $gallery->addPhoto(UploadedFile::fake()->image('photo1.jpg'));

    $all = makeGalleryArchive($gallery);
    $favorites = makeGalleryArchive($gallery, 'favorites');

    $gallery->update(['name' => 'New Name']);

    expect($all->refresh()->status)->toBe('expired')
        ->and($favorites->refresh()->status)->toBe('expired');
});

it('counts temporary archive storage without changing the upload quota', function () {
    $gallery = Gallery::factory()->for($this->team)->create();
    $gallery->addPhoto(UploadedFile::fake()->image('photo1.jpg'));

    $before = StorageUsageService::forTeam($this->team);
    $archive = makeGalleryArchive($gallery);

    expect(StorageUsageService::forTeam($this->team))->toBe($before)
        ->and(StorageUsageService::archivesForGallery($gallery))->toBe($archive->size_bytes)
        ->and(StorageUsageService::archivesForTeam($this->team))->toBe($archive->size_bytes);
});

it('deletes an archive object through the retry job', function () {
    $gallery = Gallery::factory()->for($this->team)->create();
    $gallery->addPhoto(UploadedFile::fake()->image('photo1.jpg'));

    $archive = makeGalleryArchive($gallery);
    $path = $archive->path;

    (new DeleteGalleryArchive($archive->id, $path, $archive->disk))->handle();

    Storage::disk('s3')->assertMissing($path);
    expect($archive->refresh()->path)->toBeNull();
});

it('does not generate a zip when the shared gallery is viewed', function () {
    $gallery = Gallery::factory()->shared()->downloadable()->for($this->team)->create(['ulid' => '0123ABC']);
    $gallery->addPhoto(UploadedFile::fake()->image('photo1.jpg'));

    Livewire::test('pages::shares.show', ['gallery' => $gallery, 'slug' => $gallery->slug]);

    $this->assertDatabaseCount('gallery_archives', 0);
    Queue::assertNothingPushed();
});

it('serves an owner archive through its direct archive route', function () {
    $gallery = Gallery::factory()->for($this->team)->create();
    $gallery->addPhoto(UploadedFile::fake()->image('photo1.jpg'));
    $archive = makeGalleryArchive($gallery);

    fakeTemporaryUrls();

    actingAs($this->user)
        ->get(route('galleries.archives.download', [$gallery, $archive]))
        ->assertRedirect('https://s3.test/'.$archive->path);
});

it('rejects an archive that belongs to another gallery', function () {
    $gallery = Gallery::factory()->for($this->team)->create();
    $gallery->addPhoto(UploadedFile::fake()->image('photo1.jpg'));
    $archive = makeGalleryArchive($gallery);

    $other = Gallery::factory()->for($this->team)->create();
    $other->addPhoto(UploadedFile::fake()->image('other.jpg'));

    actingAs($this->user)
        ->get(route('galleries.archives.download', [$other, $archive]))
        ->assertNotFound();
});

it('serves a shared archive through its direct route and enforces download flags', function () {
    $gallery = Gallery::factory()->shared()->downloadable()->for($this->team)->create(['ulid' => '0123ABC']);
    $gallery->addPhoto(UploadedFile::fake()->image('photo1.jpg'));
    $archive = makeGalleryArchive($gallery);

    fakeTemporaryUrls();

    get(route('shares.archives.download', [$gallery, $archive]))
        ->assertRedirect('https://s3.test/'.$archive->path);

    $gallery->update(['is_share_downloadable' => false]);

    get(route('shares.archives.download', [$gallery, $archive]))
        ->assertStatus(401);
});

it('has a valid archive factory', function () {
    $archive = GalleryArchive::factory()->create();

    expect($archive->gallery->team_id)->toBe($archive->team_id);
});
