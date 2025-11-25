<?php

use App\Models\Gallery;
use App\Models\Photo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->withPersonalTeam()->create();
    $this->team = $this->user->currentTeam;
});

test('can download a photo', function () {
    Storage::fake('s3');

    Photo::factory()->for(Gallery::factory()->shared()->downloadable()->state(['ulid' => '0123ABC'])->for($this->team))->create([
        'name' => 'photo1.jpg',
        'disk' => 's3',
        'path' => UploadedFile::fake()
            ->image('photo1.jpg')
            ->store('photo1.jpg', 's3'),
    ]);

    $response = get('/shares/0123ABC/photos/1/download');

    $response->assertDownload('photo1.jpg');
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

    Photo::factory()->for(Gallery::factory()->shared()->protected()->downloadable()->state(['ulid' => '0123ABC']))->create([
        'name' => 'photo1.jpg',
        'disk' => 's3',
        'path' => UploadedFile::fake()
            ->image('photo1.jpg')
            ->store('photo1.jpg', 's3'),
    ]);
    session()->put('unlocked_gallery_ulid', '0123ABC');

    $response = get('/shares/0123ABC/photos/1/download');

    $response->assertStatus(200);
});

test('can download a shared photo with raw_path when available', function () {
    Storage::fake('s3');

    Photo::factory()->for(Gallery::factory()->shared()->downloadable()->state(['ulid' => '0123ABC']))->create([
        'name' => 'photo1.jpg',
        'disk' => 's3',
        'path' => UploadedFile::fake()
            ->image('photo1.jpg')
            ->store('photo1.jpg', 's3'),
        'raw_path' => UploadedFile::fake()
            ->create('photo1.cr2', 2048)
            ->store('photo1.cr2', 's3'),
    ]);

    $response = get('/shares/0123ABC/photos/1/download');

    $response->assertDownload('photo1.jpg');
});

test('downloads processed shared photo when no raw_path exists', function () {
    Storage::fake('s3');

    Photo::factory()->for(Gallery::factory()->shared()->downloadable()->state(['ulid' => '0123ABC']))->create([
        'name' => 'photo1.jpg',
        'disk' => 's3',
        'path' => UploadedFile::fake()
            ->image('photo1.jpg')
            ->store('photo1.jpg', 's3'),
    ]);

    $response = get('/shares/0123ABC/photos/1/download');

    $response->assertDownload('photo1.jpg');
});
