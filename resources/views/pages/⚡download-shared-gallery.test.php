<?php

use App\Jobs\BuildGalleryArchive;
use App\Models\Gallery;
use App\Services\GalleryArchiveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('s3');
    Queue::fake();
});

test('preparing a shared gallery download redirects to the shared download tab', function () {
    $gallery = Gallery::factory()->shared()->downloadable()->create(['ulid' => '1234ABC', 'name' => 'Example Gallery']);
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

    Queue::assertPushed(BuildGalleryArchive::class, 1);
});

test('unshared gallery can not be downloaded', function () {
    $gallery = Gallery::factory()->unshared()->create(['ulid' => '0123ABC']);

    get('/shares/0123ABC/download')->assertNotFound();
});

test('undownloadable shared gallery can not be downloaded', function () {
    $gallery = Gallery::factory()->shared()->undownloadable()->create(['ulid' => '0123ABC']);

    get('/shares/0123ABC/download')->assertStatus(401);
});

test('unauthenticated visitors to a password-protected gallery are redirected to the unlock page', function () {
    $gallery = Gallery::factory()->shared()->protected()->create(['ulid' => '0123ABC']);

    get('/shares/0123ABC/download')->assertRedirect('/shares/0123ABC/unlock');
});

test('visitors with unlocked gallery can request the password-protected download', function () {
    $gallery = Gallery::factory()->shared()->downloadable()->protected()->create(['ulid' => '0123ABC']);
    $gallery->addPhoto(UploadedFile::fake()->image('photo1.jpg'));
    session()->put('unlocked_gallery_ulids', ['0123ABC']);

    get(route('shares.download', $gallery))
        ->assertRedirect(route('shares.show', [
            'gallery' => $gallery,
            'slug' => $gallery->slug,
            'activeTab' => 'download',
        ]));
});

test('a downloadable shared gallery can request only favorites as a zip archive', function () {
    $gallery = Gallery::factory()->shared()->downloadable()->create(['ulid' => '1234ABC', 'name' => 'Example Gallery']);
    $gallery->addPhoto(UploadedFile::fake()->image('photo1.jpg'))->toggleFavorite();
    $gallery->addPhoto(UploadedFile::fake()->image('photo2.jpg'));

    get('shares/1234ABC/download?favorites=true')
        ->assertRedirect(route('shares.show', [
            'gallery' => $gallery,
            'slug' => $gallery->slug,
            'activeTab' => 'download',
        ]));

    $this->assertDatabaseHas('gallery_archives', [
        'gallery_id' => $gallery->id,
        'variant' => 'favorites',
        'status' => 'pending',
    ]);
});

test('a ready shared archive redirects to a presigned storage url', function () {
    $gallery = Gallery::factory()->shared()->downloadable()->create(['ulid' => '1234ABC', 'name' => 'Example Gallery']);
    $gallery->addPhoto(UploadedFile::fake()->image('photo1.jpg'));

    $service = app(GalleryArchiveService::class);
    $archive = $service->findOrCreate($gallery, 'all');
    $service->build($archive->refresh());

    fakeTemporaryUrls();

    get(route('shares.download', $gallery))
        ->assertRedirect('https://s3.test/'.$archive->refresh()->path);

    expect($archive->refresh()->downloads_count)->toBe(1);
});

test('the legacy streamed shared zip is used when archives are disabled', function () {
    config()->set('picstome.gallery_archives.enabled', false);

    $gallery = Gallery::factory()->shared()->downloadable()->create(['ulid' => '1234ABC', 'name' => 'Example Gallery']);
    $gallery->addPhoto(UploadedFile::fake()->image('photo1.jpg'));

    get(route('shares.download', $gallery))
        ->assertDownload('example-gallery.zip');
});
