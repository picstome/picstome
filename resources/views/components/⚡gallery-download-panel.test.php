<?php

use App\Jobs\BuildGalleryArchive;
use App\Jobs\DeleteGalleryArchive;
use App\Models\Gallery;
use App\Models\GalleryArchive;
use App\Models\User;
use App\Services\GalleryArchiveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('s3');
    Queue::fake();

    $this->user = User::factory()->withPersonalTeam()->create();
    $this->team = $this->user->currentTeam;
});

function panelGallery(User $user, array $attributes = []): Gallery
{
    $gallery = Gallery::factory()->for($user->currentTeam)->create($attributes);
    $gallery->addPhoto(UploadedFile::fake()->image('photo1.jpg'));

    return $gallery;
}

function panelArchive(Gallery $gallery): GalleryArchive
{
    $service = app(GalleryArchiveService::class);
    $archive = $service->findOrCreate($gallery, 'all');
    $service->build($archive->refresh());

    return $archive->refresh();
}

it('shows the owner the contents and does not prepare an archive on mount', function () {
    $gallery = panelGallery($this->user);

    Livewire::actingAs($this->user)
        ->test('gallery-download-panel', [
            'gallery' => $gallery,
            'count' => 1,
            'size' => '1 MB',
            'imagesCount' => 1,
        ])
        ->assertSee('This ZIP includes processed images, videos and PDFs.')
        ->assertSee('1 image')
        ->assertSee('0 videos')
        ->assertSee('1 MB')
        ->assertSee('Prepare download');

    $this->assertDatabaseCount('gallery_archives', 0);
    Queue::assertNothingPushed();
});

it('prepares an owner archive only when asked', function () {
    $gallery = panelGallery($this->user);

    $component = Livewire::actingAs($this->user)
        ->test('gallery-download-panel', [
            'gallery' => $gallery,
            'count' => 1,
            'size' => '1 MB',
            'imagesCount' => 1,
        ])
        ->call('prepare');

    $this->assertDatabaseHas('gallery_archives', [
        'gallery_id' => $gallery->id,
        'variant' => 'all',
        'status' => 'pending',
    ]);

    Queue::assertPushed(BuildGalleryArchive::class);
    $component->assertSee('Waiting to start');
});

it('asks for confirmation before generating an archive', function () {
    $gallery = panelGallery($this->user);

    $component = Livewire::actingAs($this->user)
        ->test('gallery-download-panel', [
            'gallery' => $gallery,
            'count' => 1,
            'size' => '1 MB',
            'imagesCount' => 1,
        ])
        ->call('openPrepareModal', 'prepare')
        ->assertDispatched('modal:download-confirm-open')
        ->assertSee('Nothing is generated until you confirm.');

    $this->assertDatabaseCount('gallery_archives', 0);
    Queue::assertNothingPushed();

    $component->call('confirmPrepare');

    $this->assertDatabaseHas('gallery_archives', [
        'gallery_id' => $gallery->id,
        'variant' => 'all',
        'status' => 'pending',
    ]);

    Queue::assertPushed(BuildGalleryArchive::class);
});

it('shows packing progress when all photos have been processed', function () {
    $gallery = panelGallery($this->user);
    $archive = app(GalleryArchiveService::class)->findOrCreate($gallery, 'all');
    $archive->update([
        'status' => 'building',
        'photos_total' => 1,
        'photos_processed' => 1,
    ]);

    Livewire::actingAs($this->user)
        ->test('gallery-download-panel', [
            'gallery' => $gallery,
            'count' => 1,
            'size' => '1 MB',
            'imagesCount' => 1,
        ])
        ->assertSee('Packing and uploading the ZIP…')
        ->assertSee('Packing')
        ->assertSee('You can come back in a few minutes or close the browser and return later; the download will be ready.')
        ->assertDontSee('1 / 1');
});

it('shows the owner the archive details and can delete the zip', function () {

    $gallery = panelGallery($this->user);
    $archive = panelArchive($gallery);

    $component = Livewire::actingAs($this->user)
        ->test('gallery-download-panel', [
            'gallery' => $gallery,
            'count' => 1,
            'size' => '1 MB',
            'imagesCount' => 1,
        ])
        ->assertSee('1 image')
        ->assertSee('0 videos')
        ->assertSee('Generated')
        ->assertSee('Expires')
        ->assertSee('Downloads')
        ->assertSee('Delete ZIP')
        ->assertSee('Regenerate')
        ->call('delete');

    $this->assertDatabaseMissing('gallery_archives', ['id' => $archive->id]);
    Queue::assertPushed(DeleteGalleryArchive::class, 1);
    Queue::pushed(DeleteGalleryArchive::class)->first()->handle();
    Storage::disk('s3')->assertMissing($archive->path);
    $component->assertSee('Not generated')
        ->assertDontSee('Delete ZIP')
        ->assertDontSee('Regenerate');
});

it('regenerates an owner archive and removes the previous object', function () {
    $gallery = panelGallery($this->user);
    $archive = panelArchive($gallery);
    $oldPath = $archive->path;

    Livewire::actingAs($this->user)
        ->test('gallery-download-panel', [
            'gallery' => $gallery,
            'count' => 1,
            'size' => '1 MB',
            'imagesCount' => 1,
        ])
        ->call('regenerate');

    Queue::assertPushed(DeleteGalleryArchive::class, 1);
    Queue::pushed(DeleteGalleryArchive::class)->first()->handle();
    Storage::disk('s3')->assertMissing($oldPath);

    $this->assertDatabaseHas('gallery_archives', [
        'gallery_id' => $gallery->id,
        'variant' => 'all',
        'status' => 'pending',
    ]);

    Queue::assertPushed(BuildGalleryArchive::class, 2);
});

it('uses the current gallery counts when the archive is expired', function () {
    $gallery = Gallery::factory()->for($this->user->currentTeam)->create();
    $first = $gallery->addPhoto(UploadedFile::fake()->image('first.jpg'));
    $gallery->addPhoto(UploadedFile::fake()->image('second.jpg'));

    $archive = panelArchive($gallery);
    $first->delete();

    Livewire::actingAs($this->user)
        ->test('gallery-download-panel', [
            'gallery' => $gallery,
            'count' => 1,
            'size' => '1 MB',
            'imagesCount' => 1,
        ])
        ->assertSee('Expired')
        ->assertSee('1 image')
        ->assertSee('0 videos')
        ->assertSee('Prepare download')
        ->assertDontSee('Regenerate')
        ->assertDontSee('Delete ZIP');

    expect($archive->refresh()->status)->toBe('expired');
});

it('hides owner actions from the shared panel', function () {
    $gallery = panelGallery($this->user, [
        'is_shared' => true,
        'is_share_downloadable' => true,
    ]);

    Livewire::test('gallery-download-panel', [
        'gallery' => $gallery,
        'count' => 1,
        'size' => '1 MB',
        'imagesCount' => 1,
        'shared' => true,
    ])
        ->assertDontSee('Delete ZIP')
        ->assertDontSee('Regenerate')
        ->assertSee('Prepare download');
});

it('shows a toast to the requester when the archive becomes ready', function () {
    $gallery = panelGallery($this->user);
    $archive = app(GalleryArchiveService::class)->findOrCreate($gallery, 'all');
    $archive->update([
        'status' => 'building',
        'started_at' => now(),
    ]);

    $component = Livewire::actingAs($this->user)
        ->test('gallery-download-panel', [
            'gallery' => $gallery,
            'count' => 1,
            'size' => '1 MB',
            'imagesCount' => 1,
        ]);

    $archive->update([
        'status' => 'ready',
        'path' => 'teams/'.$gallery->team_id.'/galleries/'.$gallery->ulid.'/archives/'.$archive->ulid.'.zip',
        'size_bytes' => 1024,
        'completed_at' => now(),
        'expires_at' => now()->addDay(),
    ]);

    $component->call('refreshArchive')
        ->assertDispatched('ts-ui:toast');
});
