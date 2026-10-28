<?php

use App\Models\Gallery;
use App\Models\Photo;
use App\Models\PhotoVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('falls back to a streamed version download on disks without temporary urls', function () {
    $local = fakeLocalDiskWithoutTemporaryUrls();
    Storage::disk($local['disk'])->put('galleries/version.jpg', 'version');

    $user = User::factory()->withPersonalTeam()->create();
    $gallery = Gallery::factory()->for($user->currentTeam)->create();
    $photo = Photo::factory()->for($gallery)->create();
    $version = PhotoVersion::factory()->for($photo)->create([
        'name' => 'version.jpg',
        'path' => 'galleries/version.jpg',
        'disk' => $local['disk'],
    ]);

    actingAs($user)
        ->get(route('galleries.photos.versions.download', [$gallery, $photo, $version]))
        ->assertDownload('version.jpg');

    File::deleteDirectory($local['root']);
});

it('redirects a version download to a presigned url', function () {
    Storage::fake('s3');
    Storage::disk('s3')->put('galleries/version.jpg', 'version');
    $signed = fakeTemporaryUrls();

    $user = User::factory()->withPersonalTeam()->create();
    $gallery = Gallery::factory()->for($user->currentTeam)->create();
    $photo = Photo::factory()->for($gallery)->create();
    $version = PhotoVersion::factory()->for($photo)->create([
        'name' => 'version.jpg',
        'path' => 'galleries/version.jpg',
        'disk' => 's3',
    ]);

    actingAs($user)
        ->get(route('galleries.photos.versions.download', [$gallery, $photo, $version]))
        ->assertRedirect('https://s3.test/galleries/version.jpg');

    expect($signed->options[0]['ResponseContentDisposition'])->toContain('version.jpg');
});

it('redirects raw version downloads with the raw filename', function () {
    Storage::fake('s3');
    Storage::disk('s3')->put('galleries/version.jpg', 'version');
    Storage::disk('s3')->put('galleries/version.cr2', 'raw');
    $signed = fakeTemporaryUrls();

    $user = User::factory()->withPersonalTeam()->create();
    $gallery = Gallery::factory()->for($user->currentTeam)->create();
    $photo = Photo::factory()->for($gallery)->create();
    $version = PhotoVersion::factory()->for($photo)->create([
        'name' => 'version.cr2',
        'path' => 'galleries/version.jpg',
        'raw_path' => 'galleries/version.cr2',
        'disk' => 's3',
    ]);

    actingAs($user)
        ->get(route('galleries.photos.versions.download', [$gallery, $photo, $version]))
        ->assertRedirect('https://s3.test/galleries/version.jpg');

    actingAs($user)
        ->get(route('galleries.photos.versions.download', [$gallery, $photo, $version]).'?type=raw')
        ->assertRedirect('https://s3.test/galleries/version.cr2');

    expect($signed->options[0]['ResponseContentDisposition'])->toContain('version.jpg')
        ->and($signed->options[1]['ResponseContentDisposition'])->toContain('version.cr2');
});

it('uses the video ttl for video versions', function () {
    Storage::fake('s3');
    Storage::disk('s3')->put('galleries/version.mp4', 'video');
    $signed = fakeTemporaryUrls();

    $user = User::factory()->withPersonalTeam()->create();
    $gallery = Gallery::factory()->for($user->currentTeam)->create();
    $photo = Photo::factory()->for($gallery)->create();
    $version = PhotoVersion::factory()->for($photo)->create([
        'name' => 'version.mp4',
        'path' => 'galleries/version.mp4',
        'disk' => 's3',
    ]);

    actingAs($user)
        ->get(route('galleries.photos.versions.download', [$gallery, $photo, $version]))
        ->assertRedirect('https://s3.test/galleries/version.mp4');

    expect($signed->expirations[0]->getTimestamp())
        ->toBeGreaterThan(now()->addMinutes(359)->getTimestamp())
        ->toBeLessThan(now()->addMinutes(361)->getTimestamp());
});

it('forbids other teams from downloading a version', function () {
    Storage::fake('s3');

    $owner = User::factory()->withPersonalTeam()->create();
    $gallery = Gallery::factory()->for($owner->currentTeam)->create();
    $photo = Photo::factory()->for($gallery)->create();
    $version = PhotoVersion::factory()->for($photo)->create(['disk' => 's3']);

    $other = User::factory()->withPersonalTeam()->create();

    actingAs($other)
        ->get(route('galleries.photos.versions.download', [$gallery, $photo, $version]))
        ->assertForbidden();
});
