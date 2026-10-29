<?php

use App\Jobs\BuildGalleryArchive;
use App\Models\Gallery;
use App\Models\Team;
use App\Models\User;
use App\Services\GalleryArchiveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('s3');
    Queue::fake();

    $this->user = User::factory()->withPersonalTeam()->create();
    $this->team = $this->user->currentTeam;
});

test('preparing an owner gallery download creates one archive and redirects to the download tab', function () {
    $gallery = Gallery::factory()->for($this->team)->create(['name' => 'Example Gallery']);
    $gallery->addPhoto(UploadedFile::fake()->image('photo1.jpg'));

    actingAs($this->user)
        ->get(route('galleries.download', $gallery))
        ->assertRedirect(route('galleries.show', ['gallery' => $gallery, 'activeTab' => 'download']));

    $this->assertDatabaseHas('gallery_archives', [
        'gallery_id' => $gallery->id,
        'variant' => 'all',
        'status' => 'pending',
    ]);

    Queue::assertPushed(BuildGalleryArchive::class, 1);
});

test('a ready owner archive redirects to a presigned storage url', function () {
    $gallery = Gallery::factory()->for($this->team)->create(['name' => 'Example Gallery']);
    $gallery->addPhoto(UploadedFile::fake()->image('photo1.jpg'));

    $service = app(GalleryArchiveService::class);
    $archive = $service->findOrCreate($gallery, 'all', $this->user);
    $service->build($archive->refresh());

    fakeTemporaryUrls();

    actingAs($this->user)
        ->get(route('galleries.download', $gallery))
        ->assertRedirect('https://s3.test/'.$archive->refresh()->path);

    expect($archive->refresh()->downloads_count)->toBe(1);
});

test('guests cannot download any galleries', function () {
    Gallery::factory()->for($this->team)->create();

    $response = get('/galleries/1/download');

    $response->assertRedirect('/login');
});

test('users cannot download the team gallery of others', function () {
    Gallery::factory()->for(Team::factory())->create();

    $response = actingAs($this->user)->get('/galleries/1/download');

    $response->assertStatus(403);
});

test('the legacy streamed zip is used when archives are disabled', function () {
    config()->set('picstome.gallery_archives.enabled', false);

    $gallery = Gallery::factory()->for($this->team)->create(['name' => 'Example Gallery']);
    $gallery->addPhoto(UploadedFile::fake()->image('photo1.jpg'));

    actingAs($this->user)
        ->get(route('galleries.download', $gallery))
        ->assertDownload('example-gallery.zip');
});
