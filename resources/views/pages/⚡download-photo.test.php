<?php

use App\Models\Gallery;
use App\Models\Photo;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->withPersonalTeam()->create();
    $this->team = $this->user->currentTeam;
});

test('falls back to a streamed download when the disk does not support temporary urls', function () {
    $local = fakeLocalDiskWithoutTemporaryUrls();
    Storage::disk($local['disk'])->put('photos/photo1.jpg', 'image');

    Photo::factory()->for(Gallery::factory()->for($this->team))->create([
        'name' => 'photo1.jpg',
        'disk' => $local['disk'],
        'path' => 'photos/photo1.jpg',
    ]);

    $response = actingAs($this->user)->get('galleries/1/photos/1/download');

    $response->assertDownload('photo1.jpg');

    File::deleteDirectory($local['root']);
});

test('redirects photo downloads to a presigned url', function () {
    Storage::fake('s3');
    Storage::disk('s3')->put('photos/photo1.jpg', 'image');
    $signed = fakeTemporaryUrls();

    Photo::factory()->for(Gallery::factory()->for($this->team))->create([
        'name' => 'photo1.jpg',
        'disk' => 's3',
        'path' => 'photos/photo1.jpg',
    ]);

    $response = actingAs($this->user)->get('galleries/1/photos/1/download');

    $response->assertRedirect('https://s3.test/photos/photo1.jpg');

    expect($signed->options)->toHaveCount(1)
        ->and($signed->options[0]['ResponseContentType'])->toBe('application/octet-stream')
        ->and($signed->options[0]['ResponseContentDisposition'])->toContain('photo1.jpg')
        ->and($signed->expirations[0]->getTimestamp())->toBeGreaterThan(now()->addMinutes(9)->getTimestamp())
        ->and($signed->expirations[0]->getTimestamp())->toBeLessThan(now()->addMinutes(11)->getTimestamp());
});

test('redirects raw and processed downloads with their current filenames', function () {
    Storage::fake('s3');
    Storage::disk('s3')->put('photos/photo1.jpg', 'image');
    Storage::disk('s3')->put('photos/photo1.cr2', 'raw');
    $signed = fakeTemporaryUrls();

    Photo::factory()->for(Gallery::factory()->for($this->team))->create([
        'name' => 'photo1.cr2',
        'disk' => 's3',
        'path' => 'photos/photo1.jpg',
        'raw_path' => 'photos/photo1.cr2',
    ]);

    actingAs($this->user)
        ->get('galleries/1/photos/1/download')
        ->assertRedirect('https://s3.test/photos/photo1.jpg');

    actingAs($this->user)
        ->get('galleries/1/photos/1/download?type=raw')
        ->assertRedirect('https://s3.test/photos/photo1.cr2');

    expect($signed->options[0]['ResponseContentDisposition'])->toContain('photo1.jpg')
        ->and($signed->options[1]['ResponseContentDisposition'])->toContain('photo1.cr2');
});

test('uses a longer presigned url ttl for videos', function () {
    Storage::fake('s3');
    Storage::disk('s3')->put('videos/video.mp4', 'video');
    $signed = fakeTemporaryUrls();

    Photo::factory()->video()->for(Gallery::factory()->for($this->team))->create([
        'disk' => 's3',
        'path' => 'videos/video.mp4',
    ]);

    actingAs($this->user)
        ->get('galleries/1/photos/1/download')
        ->assertRedirect('https://s3.test/videos/video.mp4');

    expect($signed->expirations[0]->getTimestamp())
        ->toBeGreaterThan(now()->addMinutes(359)->getTimestamp())
        ->toBeLessThan(now()->addMinutes(361)->getTimestamp());
});

test('uses the configured presigned url ttl for photos', function () {
    config()->set('picstome.photo_download_url_ttl_minutes', 42);

    Storage::fake('s3');
    Storage::disk('s3')->put('photos/photo1.jpg', 'image');
    $signed = fakeTemporaryUrls();

    Photo::factory()->for(Gallery::factory()->for($this->team))->create([
        'name' => 'photo1.jpg',
        'disk' => 's3',
        'path' => 'photos/photo1.jpg',
    ]);

    actingAs($this->user)
        ->get('galleries/1/photos/1/download')
        ->assertRedirect('https://s3.test/photos/photo1.jpg');

    expect($signed->expirations[0]->getTimestamp())
        ->toBeGreaterThan(now()->addMinutes(41)->getTimestamp())
        ->toBeLessThan(now()->addMinutes(43)->getTimestamp());
});

test('does not generate a presigned url for other teams', function () {
    Storage::fake('s3');
    $signed = fakeTemporaryUrls();

    Photo::factory()->for(Gallery::factory()->for(Team::factory()))->create(['disk' => 's3']);

    actingAs($this->user)->get('/galleries/1/photos/1/download')->assertForbidden();

    expect($signed->urls)->toBeEmpty();
});

test('guests cannot download any galleries', function () {
    Photo::factory()->create();

    $response = get('/galleries/1/photos/1/download');

    $response->assertRedirect('/login');
});

test('users cannot download the team gallery photos of other users', function () {
    $photo = Photo::factory()->for(Gallery::factory()->for(Team::factory()))->create();

    $response = actingAs($this->user)->get('/galleries/1/photos/1/download');

    $response->assertStatus(403);
});

test('can download a pdf keeping its pdf name', function () {
    Storage::fake('s3');
    Storage::disk('s3')->put('photos/composite.pdf', '%PDF-1.4 fake pdf body');
    $signed = fakeTemporaryUrls();

    Photo::factory()->pdf()->for(Gallery::factory()->for($this->team))->create([
        'name' => 'composite.pdf',
        'disk' => 's3',
        'path' => 'photos/composite.pdf',
    ]);

    actingAs($this->user)
        ->get('galleries/1/photos/1/download')
        ->assertRedirect('https://s3.test/photos/composite.pdf');

    expect($signed->options[0]['ResponseContentDisposition'])->toContain('composite.pdf');
});

test('falls back to streamed raw downloads when the disk does not support temporary urls', function () {
    $local = fakeLocalDiskWithoutTemporaryUrls();
    Storage::disk($local['disk'])->put('photos/photo1.jpg', 'image');
    Storage::disk($local['disk'])->put('photos/photo1.cr2', 'raw');

    Photo::factory()->for(Gallery::factory()->for($this->team))->create([
        'name' => 'photo1.cr2',
        'disk' => $local['disk'],
        'path' => 'photos/photo1.jpg',
        'raw_path' => 'photos/photo1.cr2',
    ]);

    actingAs($this->user)
        ->get('galleries/1/photos/1/download')
        ->assertDownload('photo1.jpg');

    actingAs($this->user)
        ->get('galleries/1/photos/1/download?type=raw')
        ->assertDownload('photo1.cr2');

    File::deleteDirectory($local['root']);
});
