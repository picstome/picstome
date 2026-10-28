<?php

use App\Models\Gallery;
use App\Models\Photo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->withPersonalTeam()->create();
    $this->team = $this->user->currentTeam;
});

test('falls back to a streamed download when the disk does not support temporary urls', function () {
    $local = fakeLocalDiskWithoutTemporaryUrls();
    Storage::disk($local['disk'])->put('photos/photo1.jpg', 'image');

    Photo::factory()->for(Gallery::factory()->shared()->downloadable()->state(['ulid' => '0123ABC'])->for($this->team))->create([
        'name' => 'photo1.jpg',
        'disk' => $local['disk'],
        'path' => 'photos/photo1.jpg',
    ]);

    $response = get('/shares/0123ABC/photos/1/download');

    $response->assertDownload('photo1.jpg');

    File::deleteDirectory($local['root']);
});

test('redirects shared photo downloads to a presigned url', function () {
    Storage::fake('s3');
    Storage::disk('s3')->put('photos/photo1.jpg', 'image');
    $signed = fakeTemporaryUrls();

    Photo::factory()->for(Gallery::factory()->shared()->downloadable()->state(['ulid' => '0123ABC'])->for($this->team))->create([
        'name' => 'photo1.jpg',
        'disk' => 's3',
        'path' => 'photos/photo1.jpg',
    ]);

    get('/shares/0123ABC/photos/1/download')
        ->assertRedirect('https://s3.test/photos/photo1.jpg');

    expect($signed->options[0]['ResponseContentDisposition'])->toContain('photo1.jpg');
});

test('redirects shared raw downloads to a presigned url', function () {
    Storage::fake('s3');
    Storage::disk('s3')->put('photos/photo1.jpg', 'image');
    Storage::disk('s3')->put('photos/photo1.cr2', 'raw');
    $signed = fakeTemporaryUrls();

    Photo::factory()->for(Gallery::factory()->shared()->downloadable()->state(['ulid' => '0123ABC'])->for($this->team))->create([
        'name' => 'photo1.cr2',
        'disk' => 's3',
        'path' => 'photos/photo1.jpg',
        'raw_path' => 'photos/photo1.cr2',
    ]);

    get('/shares/0123ABC/photos/1/download?type=raw')
        ->assertRedirect('https://s3.test/photos/photo1.cr2');

    expect($signed->options[0]['ResponseContentDisposition'])->toContain('photo1.cr2');
});

test('unshared gallery photo can not be downloaded', function () {
    Photo::factory()->for(Gallery::factory()->unshared()->state(['ulid' => '0123ABC']))->create();

    $response = get('/shares/0123ABC/photos/1/download');

    $response->assertStatus(404);
});

test('undownloadable shared gallery photo can not be downloaded', function () {
    Photo::factory()->for(Gallery::factory()->shared()->undownloadable()->state(['ulid' => '0123ABC']))->create();

    $response = get('/shares/0123ABC/photos/1/download');

    $response->assertStatus(401);
});

test('unauthenticated visitors to a password-protected gallery are redirected to the unlock page', function () {
    Photo::factory()->for(Gallery::factory()->shared()->protected()->downloadable()->state(['ulid' => '0123ABC']))->create();

    $response = get('/shares/0123ABC/photos/1/download');

    $response->assertRedirect('/shares/0123ABC/unlock');
});

test('visitors with unlocked gallery can download the password-protected gallery', function () {
    Storage::fake('s3');
    Storage::disk('s3')->put('photos/photo1.jpg', 'image');
    fakeTemporaryUrls();

    Photo::factory()->for(Gallery::factory()->shared()->protected()->downloadable()->state(['ulid' => '0123ABC']))->create([
        'name' => 'photo1.jpg',
        'disk' => 's3',
        'path' => 'photos/photo1.jpg',
    ]);
    session()->put('unlocked_gallery_ulids', ['0123ABC']);

    $response = get('/shares/0123ABC/photos/1/download');

    $response->assertRedirect('https://s3.test/photos/photo1.jpg');
});

test('falls back to streamed raw downloads when the disk does not support temporary urls', function () {
    $local = fakeLocalDiskWithoutTemporaryUrls();
    Storage::disk($local['disk'])->put('photos/photo1.jpg', 'image');
    Storage::disk($local['disk'])->put('photos/photo1.cr2', 'raw');

    Photo::factory()->for(Gallery::factory()->shared()->downloadable()->state(['ulid' => '0123ABC']))->create([
        'name' => 'photo1.cr2',
        'disk' => $local['disk'],
        'path' => 'photos/photo1.jpg',
        'raw_path' => 'photos/photo1.cr2',
    ]);

    get('/shares/0123ABC/photos/1/download')
        ->assertDownload('photo1.jpg');

    get('/shares/0123ABC/photos/1/download?type=raw')
        ->assertDownload('photo1.cr2');

    File::deleteDirectory($local['root']);
});
