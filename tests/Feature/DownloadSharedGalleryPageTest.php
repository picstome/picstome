<?php

use App\Models\Gallery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

test('a downloadable shared gallery can be downloaded as a zip file', function () {
    Storage::fake('public');
    $photos = collect([
        UploadedFile::fake()->image('photo1.jpg'),
        UploadedFile::fake()->image('photo2.jpg'),
    ]);
    $gallery = Gallery::factory()->shared()->downloadable()->create(['ulid' => '1234ABC', 'name' => 'Example Gallery']);
    $photos->each(function ($photo) use ($gallery) {
        $gallery->addPhoto($photo);
    });

    $response = get('shares/1234ABC/download');

    $response->assertDownload('example-gallery.zip');
});

test('unshared gallery can not be downloaded', function () {
    $gallery = Gallery::factory()->unshared()->create(['ulid' => '0123ABC']);

    $response = get('/shares/0123ABC/download');

    $response->assertStatus(404);
});

test('undownloadable shared gallery can not be downloaded', function () {
    $gallery = Gallery::factory()->shared()->undownloadable()->create(['ulid' => '0123ABC']);

    $response = get('/shares/0123ABC/download');

    $response->assertStatus(401);
});

test('unauthenticated visitors to a password-protected gallery are redirected to the unlock page', function () {
    $gallery = Gallery::factory()->shared()->protected()->create(['ulid' => '0123ABC']);

    $response = get('/shares/0123ABC/download');

    $response->assertRedirect('/shares/0123ABC/unlock');
});

test('visitors with unlocked gallery can download the password-protected gallery', function () {
    $gallery = Gallery::factory()->shared()->downloadable()->protected()->create(['ulid' => '0123ABC']);
    session()->put('unlocked_gallery_ulid', '0123ABC');

    $response = get('/shares/0123ABC/download');

    $response->assertStatus(200);
});
