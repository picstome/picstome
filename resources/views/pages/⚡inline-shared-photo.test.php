<?php

use App\Models\Gallery;
use App\Models\Photo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->withPersonalTeam()->create();
    $this->team = $this->user->currentTeam;
});

test('can open a shared gallery pdf inline', function () {
    Storage::fake('s3');
    Storage::disk('s3')->put('photos/document.pdf', '%PDF-1.4 fake pdf body');

    Photo::factory()->pdf()->for(Gallery::factory()->shared()->state(['ulid' => '0123ABC'])->for($this->team))->create([
        'disk' => 's3',
        'path' => 'photos/document.pdf',
    ]);

    $response = get('/shares/0123ABC/photos/1/pdf');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/pdf');
    $response->assertHeader('Content-Disposition', 'inline; filename=document.pdf');
    $response->assertHeader('X-Content-Type-Options', 'nosniff');
});

test('shared gallery pdfs can be opened inline even when the share is not downloadable', function () {
    Storage::fake('s3');
    Storage::disk('s3')->put('photos/document.pdf', '%PDF-1.4 fake pdf body');

    Photo::factory()->pdf()->for(Gallery::factory()->shared()->undownloadable()->state(['ulid' => '0123ABC'])->for($this->team))->create([
        'disk' => 's3',
        'path' => 'photos/document.pdf',
    ]);

    $response = get('/shares/0123ABC/photos/1/pdf');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/pdf');
});

test('unshared gallery pdf can not be opened inline', function () {
    Photo::factory()->pdf()->for(Gallery::factory()->unshared()->state(['ulid' => '0123ABC']))->create();

    $response = get('/shares/0123ABC/photos/1/pdf');

    $response->assertStatus(404);
});

test('pdf of another gallery can not be opened inline', function () {
    Gallery::factory()->shared()->state(['ulid' => '0123ABC'])->create();

    Photo::factory()->pdf()->for(Gallery::factory()->create())->create();

    $response = get('/shares/0123ABC/photos/1/pdf');

    $response->assertStatus(404);
});

test('unauthenticated visitors to a password-protected gallery are redirected to the unlock page', function () {
    Photo::factory()->pdf()->for(Gallery::factory()->shared()->protected()->state(['ulid' => '0123ABC']))->create();

    $response = get('/shares/0123ABC/photos/1/pdf');

    $response->assertRedirect('/shares/0123ABC/unlock');
});

test('visitors with unlocked gallery can open the password-protected gallery pdf inline', function () {
    Storage::fake('s3');
    Storage::disk('s3')->put('photos/document.pdf', '%PDF-1.4 fake pdf body');

    Photo::factory()->pdf()->for(Gallery::factory()->shared()->protected()->state(['ulid' => '0123ABC']))->create([
        'disk' => 's3',
        'path' => 'photos/document.pdf',
    ]);
    session()->put('unlocked_gallery_ulids', ['0123ABC']);

    $response = get('/shares/0123ABC/photos/1/pdf');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/pdf');
});
