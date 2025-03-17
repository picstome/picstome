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
    Storage::fake('public');
    $photo = Photo::factory()->for(Gallery::factory()->shared()->downloadable()->state(['ulid' => '0123ABC'])->for($this->team))->create([
        'name' => 'photo1.jpg',
        'path' => UploadedFile::fake()
            ->image('photo1.jpg')
            ->store('photo1.jpg', 'public'),
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
    Storage::fake('public');
    Photo::factory()->for(Gallery::factory()->shared()->protected()->downloadable()->state(['ulid' => '0123ABC']))->create([
        'name' => 'photo1.jpg',
        'path' => UploadedFile::fake()
            ->image('photo1.jpg')
            ->store('photo1.jpg', 'public'),
    ]);
    session()->put('unlocked_gallery_ulid', '0123ABC');

    $response = get('/shares/0123ABC/photos/1/download');

    $response->assertStatus(200);
});
