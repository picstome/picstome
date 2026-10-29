<?php

use App\Jobs\BuildGalleryArchive;
use App\Models\Gallery;
use App\Models\User;
use App\Notifications\GalleryArchiveReady;
use App\Services\GalleryArchiveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('s3');
    Queue::fake();
    Notification::fake();

    $this->user = User::factory()->withPersonalTeam()->create();
    $this->team = $this->user->currentTeam;
});

function notificationGallery(User $user, array $attributes = []): Gallery
{
    $gallery = Gallery::factory()->for($user->currentTeam)->create($attributes);
    $gallery->addPhoto(UploadedFile::fake()->image('photo1.jpg'));

    return $gallery;
}

it('notifies the owner when an anonymous client archive is ready', function () {
    $gallery = notificationGallery($this->user);
    $archive = app(GalleryArchiveService::class)->findOrCreate($gallery, 'all');

    (new BuildGalleryArchive($archive->id))->handle(app(GalleryArchiveService::class));

    Notification::assertSentTo(
        $this->user,
        GalleryArchiveReady::class,
        fn (GalleryArchiveReady $notification): bool => $notification->archive->is($archive),
    );
});

it('notifies the owner when the owner requested the archive too', function () {
    $gallery = notificationGallery($this->user);
    $archive = app(GalleryArchiveService::class)->findOrCreate($gallery, 'all', $this->user);

    (new BuildGalleryArchive($archive->id))->handle(app(GalleryArchiveService::class));

    Notification::assertSentTo(
        $this->user,
        GalleryArchiveReady::class,
        fn (GalleryArchiveReady $notification): bool => $notification->archive->is($archive),
    );
});

it('mentions that the photographer can notify the client', function () {
    $gallery = notificationGallery($this->user, ['name' => 'Summer session']);
    $archive = app(GalleryArchiveService::class)->findOrCreate($gallery, 'all');

    (new BuildGalleryArchive($archive->id))->handle(app(GalleryArchiveService::class));

    $mail = (new GalleryArchiveReady($archive->refresh()))->toMail($this->user);

    expect($mail->subject)
        ->toContain('Summer session')
        ->and($mail->introLines)
        ->toContain('The ZIP archive for the gallery "Summer session" is ready to download.')
        ->toContain('You can notify your client that the photos are ready to download.');
});
