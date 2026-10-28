<?php

use App\Jobs\IngestFtpUpload;
use App\Models\FtpAccount;
use App\Models\Gallery;
use App\Models\Photo;
use App\Services\FtpInboxIngestor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('s3');
});

function liveAccountFor(Gallery $gallery): FtpAccount
{
    return FtpAccount::factory()->for($gallery->team)->create([
        'active' => true,
        'active_gallery_id' => $gallery->id,
    ]);
}

function putLiveInboxFile(FtpAccount $account, string $name = 'DSC_0001.JPG'): string
{
    $disk = Storage::disk('s3');
    $path = $account->inboxPath().'/'.$name;

    $disk->put($path, 'camera photo');
    touch($disk->path($path), time() - 10);

    return $path;
}

it('dispatches a photo event when a new photo is ingested', function () {
    $gallery = Gallery::factory()->shared()->create();

    $component = Livewire::test('gallery-live-watcher', ['gallery' => $gallery]);

    Photo::factory()->for($gallery)->create();

    $component->call('check')->assertDispatched('gallery-photos-updated');
});

it('does not dispatch a photo event when nothing changed', function () {
    $gallery = Gallery::factory()->shared()->create();
    Photo::factory()->for($gallery)->create();

    Livewire::test('gallery-live-watcher', ['gallery' => $gallery])
        ->call('check')
        ->assertNotDispatched('gallery-photos-updated');
});

it('lets the photographer scan the camera inbox', function () {
    Queue::fake();

    $gallery = Gallery::factory()->shared()->create();
    $account = liveAccountFor($gallery);
    $path = putLiveInboxFile($account);

    Livewire::test('gallery-live-watcher', [
        'gallery' => $gallery,
        'scanInbox' => true,
    ])->call('check');

    Queue::assertPushed(IngestFtpUpload::class, fn ($job) => $job->path === $path);
});

it('never scans the inbox from the client watcher', function () {
    Queue::fake();

    $gallery = Gallery::factory()->shared()->create();
    $account = liveAccountFor($gallery);
    putLiveInboxFile($account);

    Livewire::test('gallery-live-watcher', ['gallery' => $gallery])->call('check');

    Queue::assertNotPushed(IngestFtpUpload::class);
});

it('does not scan when the gallery is not the active upload target', function () {
    Queue::fake();

    $gallery = Gallery::factory()->shared()->create();
    $account = FtpAccount::factory()->for($gallery->team)->create([
        'active' => true,
        'active_gallery_id' => null,
    ]);
    putLiveInboxFile($account);

    Livewire::test('gallery-live-watcher', [
        'gallery' => $gallery,
        'scanInbox' => true,
    ])->call('check');

    Queue::assertNotPushed(IngestFtpUpload::class);
});

it('notifies the client page when remote mode ends', function () {
    $gallery = Gallery::factory()->shared()->create();
    $account = liveAccountFor($gallery);

    $component = Livewire::test('gallery-live-watcher', [
        'gallery' => $gallery,
        'stopWhenRemoteModeEnds' => true,
    ]);

    $account->update(['active_gallery_id' => null]);

    $component->call('check')->assertDispatched('gallery-remote-mode-ended');
});

it('scans the inbox at most once per poll interval', function () {
    $gallery = Gallery::factory()->shared()->create();
    liveAccountFor($gallery);

    $this->mock(FtpInboxIngestor::class, function ($mock): void {
        $mock->shouldReceive('liveScanStabilitySeconds')->once()->andReturn(2);
        $mock->shouldReceive('dispatchStableUploads')->once()->andReturn(0);
    });

    $component = Livewire::test('gallery-live-watcher', [
        'gallery' => $gallery,
        'scanInbox' => true,
    ]);

    $component->call('check');
    $component->call('check');
});

it('polls at the configured interval', function () {
    config(['picstome.live_gallery_poll_seconds' => 3]);

    $gallery = Gallery::factory()->shared()->create();

    expect(Livewire::test('gallery-live-watcher', ['gallery' => $gallery])->html())
        ->toContain('wire:poll.visible.3s="check"');

    expect(Livewire::test('gallery-live-watcher', [
        'gallery' => $gallery,
        'pollOnlyWhenVisible' => false,
    ])->html())
        ->toContain('wire:poll.3s="check"');
});

it('rejects a locked shared gallery', function () {
    $gallery = Gallery::factory()->shared()->protected()->create();

    Livewire::test('gallery-live-watcher', ['gallery' => $gallery])
        ->assertStatus(404);
});
